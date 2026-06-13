-- 1. Membuat database baru jika belum ada
CREATE DATABASE IF NOT EXISTS db_data_proyek;

-- 2. Menggunakan database yang baru dibuat
USE db_data_proyek;

-- 3. Membuat tabel PERTAMA: change_requests
CREATE TABLE IF NOT EXISTS change_requests (
    changeId VARCHAR(50) NOT NULL,
    changeDate DATE DEFAULT NULL,
    submittedBy VARCHAR(50) DEFAULT NULL,
    wbsLevel4 VARCHAR(50) DEFAULT NULL,
    wbsLevel5 VARCHAR(50) DEFAULT NULL,
    wbsLevel6 VARCHAR(50) DEFAULT NULL,
    changeCategory VARCHAR(50) DEFAULT NULL,
    priority VARCHAR(50) DEFAULT NULL,
    risk VARCHAR(50) DEFAULT NULL,
    projectArea VARCHAR(50) DEFAULT NULL,
    location VARCHAR(50) DEFAULT NULL,
    bimObjectId VARCHAR(50) DEFAULT NULL,
    riskCategory VARCHAR(50) DEFAULT NULL,
    riskVariable VARCHAR(50) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    changeType VARCHAR(50) DEFAULT NULL,
    siteCondition VARCHAR(50) DEFAULT NULL,
    ownerRequest VARCHAR(50) DEFAULT NULL,
    materialChange VARCHAR(50) DEFAULT NULL,
    methodChange VARCHAR(50) DEFAULT NULL,
    scheduleChange VARCHAR(50) DEFAULT NULL,
    safetyChange VARCHAR(50) DEFAULT NULL,
    impactArea VARCHAR(50) DEFAULT NULL,
    descriptionDetail TEXT DEFAULT NULL,
    photoEvidence VARCHAR(255) DEFAULT NULL,
    status VARCHAR(50) DEFAULT NULL,
    PRIMARY KEY (changeId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;