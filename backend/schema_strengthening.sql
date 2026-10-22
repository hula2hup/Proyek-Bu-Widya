-- ========================================================
-- WYLCORE / RQ12 — Database Strengthening Migration
-- Decision-to-Knowledge Transfer Contracts T1 - T6
-- ========================================================

USE `db_data_proyek`;

-- 1. Table: decision_trace (T4: Decision Provenance & Atomic Audit Trail)
CREATE TABLE IF NOT EXISTS `decision_trace` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `changeId` VARCHAR(50) NOT NULL,
  `project_id` VARCHAR(50) NOT NULL,
  `decision` ENUM('PENDING','APPROVED','REJECTED') NOT NULL,
  `actor_user_id` INT NOT NULL,
  `actor_username` VARCHAR(50) NOT NULL,
  `actor_role` VARCHAR(50) NOT NULL,
  `decision_notes` TEXT,
  `cost_impact` DECIMAL(21,2) DEFAULT 0.00,
  `time_impact` INT DEFAULT 0,
  `wbs_code_snapshot` VARCHAR(100),
  `wbs_version_snapshot` VARCHAR(50) DEFAULT 'v1.0',
  `risk_code_snapshot` VARCHAR(50),
  `bim_object_snapshot` VARCHAR(50),
  `bim_model_urn_snapshot` VARCHAR(255),
  `bim_model_version_snapshot` VARCHAR(50) DEFAULT 'v1',
  `recommendation_id_snapshot` INT DEFAULT NULL,
  `recommendation_status_snapshot` VARCHAR(50),
  `timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `tx_hash` VARCHAR(64) NOT NULL,
  INDEX `idx_trace_change` (`changeId`),
  INDEX `idx_trace_project` (`project_id`),
  INDEX `idx_trace_timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 2. Table: wbs_dictionary (T1: Controlled Project-WBS Catalog & Versioning)
CREATE TABLE IF NOT EXISTS `wbs_dictionary` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `project_id` VARCHAR(50) NOT NULL,
  `wbs_code` VARCHAR(100) NOT NULL,
  `wbs_level` INT NOT NULL DEFAULT 5,
  `wbs_name` VARCHAR(255) NOT NULL,
  `version` VARCHAR(50) NOT NULL DEFAULT 'v1.0',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_proj_wbs_ver` (`project_id`, `wbs_code`, `version`),
  INDEX `idx_wbs_code` (`wbs_code`),
  INDEX `idx_proj_active` (`project_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 3. Table: cr_executions (P0 03 / UT12-14: Field Implementation Tracking)
CREATE TABLE IF NOT EXISTS `cr_executions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `changeId` VARCHAR(50) NOT NULL,
  `execution_status` ENUM('NOT_STARTED','IN_PROGRESS','COMPLETED','FAILED') NOT NULL DEFAULT 'NOT_STARTED',
  `pic_user_id` INT DEFAULT NULL,
  `pic_name` VARCHAR(100) DEFAULT NULL,
  `execution_date` DATE DEFAULT NULL,
  `actions_taken` TEXT,
  `field_evidence` TEXT,
  `actual_outcome` TEXT,
  `verified_by_pm` TINYINT(1) NOT NULL DEFAULT 0,
  `verified_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_cr_exec` (`changeId`),
  INDEX `idx_exec_status` (`execution_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 4. Alter change_requests: Add versioning and concurrency columns if not exist
SET @dbname = DATABASE();
SET @tablename = 'change_requests';

-- wbs_version
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'wbs_version') > 0,
  'SELECT 1',
  'ALTER TABLE change_requests ADD COLUMN `wbs_version` VARCHAR(50) DEFAULT "v1.0" AFTER `wbsLevel6`'
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- bim_model_urn
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'bim_model_urn') > 0,
  'SELECT 1',
  'ALTER TABLE change_requests ADD COLUMN `bim_model_urn` VARCHAR(255) DEFAULT NULL AFTER `bimObjectId`'
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- bim_model_version
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'bim_model_version') > 0,
  'SELECT 1',
  'ALTER TABLE change_requests ADD COLUMN `bim_model_version` VARCHAR(50) DEFAULT "v1" AFTER `bim_model_urn`'
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- lock_version
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'lock_version') > 0,
  'SELECT 1',
  'ALTER TABLE change_requests ADD COLUMN `lock_version` INT NOT NULL DEFAULT 1 AFTER `approvalNotes`'
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5. Alter knowledge_base: Add curation fields if not exist
SET @tablename_kb = 'knowledge_base';

-- curator_user_id
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename_kb AND COLUMN_NAME = 'curator_user_id') > 0,
  'SELECT 1',
  'ALTER TABLE knowledge_base ADD COLUMN `curator_user_id` INT DEFAULT NULL AFTER `validationStatus`'
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- curator_name
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename_kb AND COLUMN_NAME = 'curator_name') > 0,
  'SELECT 1',
  'ALTER TABLE knowledge_base ADD COLUMN `curator_name` VARCHAR(100) DEFAULT NULL AFTER `curator_user_id`'
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- curated_at
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename_kb AND COLUMN_NAME = 'curated_at') > 0,
  'SELECT 1',
  'ALTER TABLE knowledge_base ADD COLUMN `curated_at` DATETIME DEFAULT NULL AFTER `curator_name`'
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- curation_notes
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename_kb AND COLUMN_NAME = 'curation_notes') > 0,
  'SELECT 1',
  'ALTER TABLE knowledge_base ADD COLUMN `curation_notes` TEXT DEFAULT NULL AFTER `curated_at`'
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- evidence_verified
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename_kb AND COLUMN_NAME = 'evidence_verified') > 0,
  'SELECT 1',
  'ALTER TABLE knowledge_base ADD COLUMN `evidence_verified` TINYINT(1) NOT NULL DEFAULT 0 AFTER `curation_notes`'
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 6. Populate default WBS dictionary from knowledge_repository for PRJ-001
INSERT IGNORE INTO `wbs_dictionary` (`project_id`, `wbs_code`, `wbs_level`, `wbs_name`, `version`, `is_active`)
SELECT DISTINCT 'PRJ-001', TRIM(wbs_kode), 5, TRIM(wbs_nama), 'v1.0', 1
FROM `knowledge_repository`
WHERE wbs_kode IS NOT NULL AND TRIM(wbs_kode) <> '';

-- Also ensure PRJ-001 has standard codes from change_requests
INSERT IGNORE INTO `wbs_dictionary` (`project_id`, `wbs_code`, `wbs_level`, `wbs_name`, `version`, `is_active`)
VALUES 
('PRJ-001', '1.7.2.1', 5, 'Pembuatan kantor K3 dan fasilitasnya', 'v1.0', 1),
('PRJ-001', '1.1.1.1', 5, 'Mobilisasi Material', 'v1.0', 1);

-- Seed Synthetic Projects and WBS fixtures for technical verification
INSERT IGNORE INTO `projects` (`project_id`, `project_name`, `status`, `progress`)
VALUES 
('PRJ-SYN-01', 'Synthetic Test Project Alpha', 'In Progress', 50),
('PRJ-SYN-02', 'Synthetic Test Project Beta', 'In Progress', 30);

INSERT IGNORE INTO `wbs_dictionary` (`project_id`, `wbs_code`, `wbs_level`, `wbs_name`, `version`, `is_active`)
VALUES 
('PRJ-SYN-01', 'W-ERECT-001', 5, 'Erection Girder Spans 1-4', 'v1.0', 1),
('PRJ-SYN-01', 'W-ERECT-002', 5, 'Erection Girder Spans 5-8', 'v1.0', 1),
('PRJ-SYN-02', 'W-P2-FOUND-01', 5, 'Bored Piling Pier P2', 'v1.0', 1);

-- Seed Synthetic PM and SE assignments for synthetic projects
-- User 4 (budisantoso) assigned to PRJ-SYN-01 as Project Manager
-- User 5 (naufaliman) assigned to PRJ-SYN-02 as Project Manager
-- User 12 (dedikurnia) assigned to PRJ-SYN-01 as Site Engineer
INSERT IGNORE INTO `project_assignments` (`project_id`, `user_id`, `role_assigned`)
VALUES 
('PRJ-SYN-01', 4, 'Project Manager'),
('PRJ-SYN-02', 5, 'Project Manager'),
('PRJ-SYN-01', 12, 'Site Engineer');

-- Synthetic knowledge_repository entries for controlled T2 testing
INSERT IGNORE INTO `knowledge_repository` (`id`, `wbs_kode`, `wbs_nama`, `risk_kode`, `risk_nama`, `risk_kategori`, `insight`, `saran`)
VALUES 
(99901, 'W-ERECT-001', 'Erection Girder Spans 1-4', 'R-WIND-01', 'High Wind Velocity', 'Metode Pelaksanaan', 'Kecapatan angin melebihi 25 knot membahayakan lifting girder.', 'Lakukan penundaan lifting jika kecepatan angin > 20 knot dan pasang anemometer terkalibrasi.'),
(99902, 'W-TIE-001', 'Tie Breaker Test Activity', 'R-TIE-01', 'Tie Candidate A', 'Metode Kerja', 'Candidate A insight description', 'Candidate A recommended action rule.'),
(99903, 'W-TIE-001', 'Tie Breaker Test Activity', 'R-TIE-01', 'Tie Candidate B', 'Metode Kerja', 'Candidate B insight description', 'Candidate B recommended action rule.');

-- Synthetic project_scurve for PRJ-SYN-01 (T6 testing)
INSERT IGNORE INTO `project_scurve` (`project_id`, `periode_ke`, `tanggal_target`, `rencana_kumulatif`, `realisasi_kumulatif`)
VALUES 
('PRJ-SYN-01', 1, '2026-06-07', 10.00, 9.50),
('PRJ-SYN-01', 2, '2026-06-14', 25.00, 23.00),
('PRJ-SYN-01', 3, '2026-06-21', 45.00, 42.00);

