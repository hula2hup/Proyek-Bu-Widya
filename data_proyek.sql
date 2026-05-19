-- 1. Membuat database baru jika belum ada
CREATE DATABASE IF NOT EXISTS db_data_proyek;

-- 2. Menggunakan database yang baru dibuat
USE db_data_proyek;

-- 3. Membuat tabel 'data_proyek' sesuai kebutuhan form HTML & PHP
CREATE TABLE IF NOT EXISTS data_proyek (
    id VARCHAR(50) NOT NULL,
    timestamp DATETIME NOT NULL,
    nama_proyek VARCHAR(255) DEFAULT NULL,
    lokasi VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;