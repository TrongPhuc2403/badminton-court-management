-- Migration: Add image_path column to courts table
-- Run this script to update existing database

ALTER TABLE courts ADD COLUMN image_path VARCHAR(255) AFTER name;

-- Update existing courts with image path
UPDATE courts SET image_path = 'image/san-cau.jpg';
