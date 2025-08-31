// LillyChat basic enhancements
(function(){
  function autoScrollMessages() {
    var pane = document.querySelector('.content-body');
    if (pane) {
      pane.scrollTop = pane.scrollHeight;
    }
  }

  function focusComposer() {
    var ta = document.querySelector('.compose textarea');
    if (ta) ta.focus();
  }

  // Auto-resize textarea
  function attachAutosize() {
    var ta = document.querySelector('.compose textarea');
    if (!ta) return;
    var resize = function() {
      ta.style.height = 'auto';
      ta.style.height = (ta.scrollHeight) + 'px';
    };
    ta.addEventListener('input', resize);
    resize();
  }

  document.addEventListener('DOMContentLoaded', function(){
    autoScrollMessages();
    focusComposer();
    attachAutosize();
  });
})();
