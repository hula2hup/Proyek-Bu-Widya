-- 1. Membuat database baru jika belum ada
CREATE DATABASE IF NOT EXISTS db_data_proyek;

-- 2. Menggunakan database yang baru dibuat
USE db_data_proyek;

-- 3. Membuat tabel PERTAMA: data_proyek_cr
CREATE TABLE IF NOT EXISTS data_proyek_cr (
    id VARCHAR(50) NOT NULL,
    timestamp DATETIME NOT NULL,
    nama_proyek VARCHAR(255) DEFAULT NULL,
    lokasi VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Membuat tabel KEDUA: data_proyek_risk
CREATE TABLE IF NOT EXISTS data_proyek_risk (
    id VARCHAR(50) NOT NULL,
    timestamp DATETIME NOT NULL,
    nama_proyek VARCHAR(255) DEFAULT NULL,
    lokasi VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Membuat tabel KETIGA: data_proyek_kb
CREATE TABLE IF NOT EXISTS data_proyek_kb (
    id VARCHAR(50) NOT NULL,
    timestamp DATETIME NOT NULL,
    nama_proyek VARCHAR(255) DEFAULT NULL,
    lokasi VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;