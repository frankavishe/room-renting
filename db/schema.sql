-- TZ-Rooms Platform Database Schema
-- Run this entire file in phpMyAdmin (SQL tab) to set up your database.
-- ============================================================

-- 1. Create and select the database
CREATE DATABASE IF NOT EXISTS tz_rooms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tz_rooms;

-- ============================================================
-- TABLE 1: users
-- Stores all platform users: tenants, landlords, and admins.
-- Every other table links back to this one via owner_id or tenant_id.
-- ============================================================
CREATE TABLE `users` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `name`       VARCHAR(100) NOT NULL,
    `email`      VARCHAR(100) UNIQUE NULL,
    `phone`      VARCHAR(20)  UNIQUE NOT NULL,   -- Format: +255XXXXXXXXX
    `password`   VARCHAR(255) NOT NULL,           -- Stored as bcrypt hash, never plain text
    `role`       ENUM('tenant', 'landlord', 'admin') DEFAULT 'tenant',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE 2: rooms
-- Every room listing. owner_id is a Foreign Key pointing to users.id
-- If a user (landlord) is deleted, their rooms are also deleted (CASCADE).
-- ============================================================
CREATE TABLE `rooms` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY,
    `title`           VARCHAR(150) NOT NULL,
    `description`     TEXT NOT NULL,
    `price_per_month` DECIMAL(10,2) NOT NULL,
    `location`        VARCHAR(150) NOT NULL,   -- e.g. "Kinondoni, Dar es Salaam"
    `amenities`       TEXT NOT NULL,           -- JSON array e.g. ["WiFi","Water","Security"]
    `images`          TEXT NOT NULL,           -- JSON array of image URLs
    `is_available`    TINYINT(1) DEFAULT 1,    -- 1 = available, 0 = occupied
    `owner_id`        INT NOT NULL,
    `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`owner_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE 3: bookings
-- Records when a tenant reserves a room.
-- ON DELETE RESTRICT on room_id means you cannot delete a room that has bookings.
-- ON DELETE CASCADE on tenant_id means if tenant is deleted, their bookings go too.
-- ============================================================
CREATE TABLE `bookings` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `room_id`    INT NOT NULL,
    `tenant_id`  INT NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date`   DATE NOT NULL,
    `status`     ENUM('pending', 'confirmed', 'cancelled') DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`room_id`)   REFERENCES `rooms`(`id`)  ON DELETE RESTRICT,
    FOREIGN KEY (`tenant_id`) REFERENCES `users`(`id`)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE 4: payments
-- One payment per booking (UNIQUE on booking_id enforces this).
-- callback_raw stores the raw JSON response from the mobile money provider.
-- ============================================================
CREATE TABLE `payments` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `booking_id`   INT NOT NULL UNIQUE,
    `amount`       DECIMAL(10,2) NOT NULL,
    `provider`     VARCHAR(50)  NOT NULL,          -- MPESA, TIGOPESA, AIRTEL, HALOPESA
    `reference`    VARCHAR(100) UNIQUE NOT NULL,   -- Unique transaction ID from provider
    `status`       ENUM('pending', 'successful', 'failed') DEFAULT 'pending',
    `callback_raw` TEXT NULL,                      -- Raw webhook payload stored for auditing
    `updated_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
