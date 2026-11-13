SET NAMES utf8mb4;

ALTER TABLE condominiums
  ADD COLUMN photo_original_name VARCHAR(255) NULL AFTER state,
  ADD COLUMN photo_path VARCHAR(255) NULL AFTER photo_original_name;

ALTER TABLE works
  ADD COLUMN cover_photo_original_name VARCHAR(255) NULL AFTER description,
  ADD COLUMN cover_photo_path VARCHAR(255) NULL AFTER cover_photo_original_name;
