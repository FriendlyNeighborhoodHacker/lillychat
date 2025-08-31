-- Add profile photo column to users table (for existing databases)
ALTER TABLE users
  ADD COLUMN profile_photo VARCHAR(255) DEFAULT NULL;
