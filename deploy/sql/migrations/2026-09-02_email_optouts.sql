-- Migration: add the email_optouts table for unsubscribe handling.
-- Run this once on an existing database (phpMyAdmin, Import or SQL tab).
-- Safe to run more than once (IF NOT EXISTS).

CREATE TABLE IF NOT EXISTS email_optouts (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email      VARCHAR(190) NOT NULL UNIQUE,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
