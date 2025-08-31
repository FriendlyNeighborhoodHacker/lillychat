<?php
// Lightweight image resizer with disk cache
// Usage examples:
//   /uploads/avatars/foo.jpg?width=100
//   /image_resizer.php?path=/uploads/avatars/foo.jpg&width=100
//
// Notes:
// - Only serves paths under /uploads for safety
// - Caches resized output to /uploads/cache/{width}/{relative_path}
// - Preserves aspect ratio; no upscaling by default (enable via &up=1)
// - Requires GD extension; WebP output if supported (optional)

declare(strict_types=1);

$docRoot = realpath(__DIR__);
$uploadsBase = realpath(__DIR__ . '/uploads');
if (!$docRoot || !$uploadsBase) {
  http_response_code(500);
  exit('Server not configured for uploads');
}

// Parse inputs
$path = $_GET['path'] ?? null;
// When using direct query (?width=) on an existing file (with .htaccess), path will be fed via rewrite.
// If invoked without rewrite, accept REQUEST_URI parsing fallback (best-effort).
if (!$path) {
  $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
  // Expect something like /uploads/... when calling directly with ?width
  if (strpos($uri, '/uploads/') === 0) $path = $uri;
}
$path = is_string($path) ? ('/' . ltrim($path, '/')) : null;

$width = (int)($_GET['width'] ?? $_GET['w'] ?? 0);
$allowUpscale = !empty($_GET['up']) ? true : false;
if ($width <= 0) $width = 200;         // default width
if ($width < 8) $width = 8;            // min guard
if ($width > 4096) $width = 4096;     // max guard

if (!$path) {
  http_response_code(400);
  exit('Missing path');
}

$srcAbs = realpath($docRoot . '/' . ltrim($path, '/'));
if (!$srcAbs || !is_file($srcAbs)) {
  http_response_code(404);
  exit('File not found');
}

// Security: ensure source under uploads/
if (strpos($srcAbs, $uploadsBase) !== 0) {
  http_response_code(403);
  exit('Forbidden');
}

// Image info
$info = @getimagesize($srcAbs);
if ($info === false || empty($info['mime'])) {
  http_response_code(415);
  exit('Unsupported media type');
}
$mime = strtolower(trim($info['mime']));
$validInput = in_array($mime, ['image/jpeg','image/png','image/gif','image/webp'], true);
if (!$validInput) {
  http_response_code(415);
  exit('Unsupported media type');
}

// Build cache file path
$relFromDocRoot = ltrim(str_replace($docRoot, '', $srcAbs), '/'); // e.g. uploads/avatars/foo.jpg
$cacheRel = 'uploads/cache/' . $width . '/' . $relFromDocRoot;
$cacheAbs = $docRoot . '/' . $cacheRel;

// Ensure cache dir exists
$cacheDir = dirname($cacheAbs);
if (!is_dir($cacheDir)) {
  @mkdir($cacheDir, 0775, true);
}

// If cache exists and is fresh enough, serve it
$srcMtime = (int)@filemtime($srcAbs);
$cacheMtime = is_file($cacheAbs) ? (int)@filemtime($cacheAbs) : 0;
if ($cacheMtime >= $srcMtime && $cacheMtime > 0) {
  return streamImage($cacheAbs, $mimeFromExt($cacheAbs), $srcMtime, true);
}

// Load source
$srcIm = loadImage($srcAbs, $mime);
if (!$srcIm) {
  http_response_code(500);
  exit('Failed to load image');
}

$origW = imagesx($srcIm);
$origH = imagesy($srcIm);
if ($origW <= 0 || $origH <= 0) {
  imagedestroy($srcIm);
  http_response_code(500);
  exit('Bad image dimensions');
}

// Determine target dimensions (preserve aspect)
if (!$allowUpscale && $width >= $origW) {
  // No resizing; just stream original
  imagedestroy($srcIm);
  return streamImage($srcAbs, $mime, $srcMtime, false);
}

$targetW = $width;
$targetH = (int)round(($origH * $targetW) / max(1, $origW));
if ($targetW < 1) $targetW = 1;
if ($targetH < 1) $targetH = 1;

// Create destination with alpha for PNG/WebP
$dstIm = imagecreatetruecolor($targetW, $targetH);
if (in_array($mime, ['image/png','image/webp','image/gif'], true)) {
  imagealphablending($dstIm, false);
  imagesavealpha($dstIm, true);
  $transparent = imagecolorallocatealpha($dstIm, 0, 0, 0, 127);
  imagefilledrectangle($dstIm, 0, 0, $targetW, $targetH, $transparent);
}

// Resample
imagecopyresampled($dstIm, $srcIm, 0, 0, 0, 0, $targetW, $targetH, $origW, $origH);
imagedestroy($srcIm);

// Choose output format (prefer same as source; convert GIF -> PNG to avoid multi-frame issues)
$outMime = $mime;
if ($mime === 'image/gif') $outMime = 'image/png';

// If WebP source, ensure we can write webp; else fallback to jpeg/png
if ($outMime === 'image/webp' && !function_exists('imagewebp')) {
  $outMime = 'image/jpeg';
}

// Save to cache atomically
$tmp = $cacheAbs . '.tmp_' . getmypid() . '_' . bin2hex(random_bytes(4));
$ok = saveImage($dstIm, $tmp, $outMime);
imagedestroy($dstIm);

if (!$ok) {
  @unlink($tmp);
  // If cannot save in intended mime, fallback to JPEG
  $tmp = $cacheAbs . '.tmp_' . getmypid() . '_' . bin2hex(random_bytes(4));
  $ok = saveImageFallbackJPEG($srcAbs, $targetW, $targetH, $tmp);
  if (!$ok) {
    http_response_code(500);
    exit('Failed to save image');
  }
}

// Ensure target dir exists (again) and move into place
@mkdir(dirname($cacheAbs), 0775, true);
@rename($tmp, $cacheAbs);

// Serve cached file
return streamImage($cacheAbs, $mimeFromExt($cacheAbs) ?: $outMime, $srcMtime, false);

// -------------- Helpers ----------------

function loadImage(string $path, string $mime) {
  switch ($mime) {
    case 'image/jpeg':
      return @imagecreatefromjpeg($path);
    case 'image/png':
      return @imagecreatefrompng($path);
    case 'image/gif':
      return @imagecreatefromgif($path);
    case 'image/webp':
      return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null;
    default:
      return null;
  }
}

function saveImage($im, string $dest, string $mime): bool {
  switch ($mime) {
    case 'image/jpeg':
      return @imagejpeg($im, $dest, 82);
    case 'image/png':
      // compression 0 (none) to 9; 6 is a reasonable trade-off
      return @imagepng($im, $dest, 6);
    case 'image/webp':
      if (function_exists('imagewebp')) {
        return @imagewebp($im, $dest, 82);
      }
      return false;
    default:
      // Fallback to PNG
      return @imagepng($im, $dest, 6);
  }
}

function saveImageFallbackJPEG(string $srcPath, int $w, int $h, string $dest): bool {
  $im = @imagecreatefromstring(@file_get_contents($srcPath));
  if (!$im) return false;
  $origW = imagesx($im);
  $origH = imagesy($im);
  $dst = imagecreatetruecolor($w, $h);
  imagecopyresampled($dst, $im, 0, 0, 0, 0, $w, $h, $origW, $origH);
  imagedestroy($im);
  $ok = @imagejpeg($dst, $dest, 82);
  imagedestroy($dst);
  return $ok;
}

function mimeFromExt(string $path): ?string {
  $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
  if ($ext === 'jpg' || $ext === 'jpeg') return 'image/jpeg';
  if ($ext === 'png') return 'image/png';
  if ($ext === 'webp') return 'image/webp';
  if ($ext === 'gif') return 'image/gif';
  return null;
}

function streamImage(string $file, ?string $mime, int $srcMtime, bool $cacheHit) {
  if (!is_file($file)) {
    http_response_code(500);
    exit('Cache missing');
  }
  if (!$mime) {
    $mime = mime_content_type($file) ?: 'application/octet-stream';
  }
  $etag = '"' . sha1($file . '|' . (string)@filesize($file) . '|' . (string)@filemtime($file)) . '"';

  // Client caching (ETag/Last-Modified)
  $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
  $ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';

  header('Content-Type: ' . $mime);
  header('Cache-Control: public, max-age=31536000, immutable');
  header('ETag: ' . $etag);
  header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $srcMtime) . ' GMT');
  header('X-Cache: ' . ($cacheHit ? 'hit' : 'miss'));

  if ($ifNoneMatch === $etag) {
    http_response_code(304);
    exit;
  }
  if ($ifModifiedSince) {
    $ims = strtotime($ifModifiedSince);
    if ($ims !== false && $ims >= $srcMtime) {
      http_response_code(304);
      exit;
    }
  }

  $fp = @fopen($file, 'rb');
  if (!$fp) {
    http_response_code(500);
    exit('Failed to open image');
  }
  header('Content-Length: ' . (string)filesize($file));
  fpassthru($fp);
  fclose($fp);
  exit;
}
