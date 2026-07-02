/*
 Navicat Premium Data Transfer

 Source Server         : Local
 Source Server Type    : MySQL
 Source Server Version : 80040 (8.0.40)
 Source Host           : localhost:3306
 Source Schema         : dbbornpadel

 Target Server Type    : MySQL
 Target Server Version : 80040 (8.0.40)
 File Encoding         : 65001

 Date: 02/07/2026 14:57:23
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for failed_jobs
-- ----------------------------
DROP TABLE IF EXISTS `failed_jobs`;
CREATE TABLE `failed_jobs`  (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `failed_jobs_uuid_unique`(`uuid` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of failed_jobs
-- ----------------------------

-- ----------------------------
-- Table structure for grup
-- ----------------------------
DROP TABLE IF EXISTS `grup`;
CREATE TABLE `grup`  (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_turnamen` bigint UNSIGNED NOT NULL,
  `nama` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `babak` smallint UNSIGNED NOT NULL DEFAULT 1,
  `is_aktif` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `grup_id_turnamen_foreign`(`id_turnamen` ASC) USING BTREE,
  CONSTRAINT `grup_id_turnamen_foreign` FOREIGN KEY (`id_turnamen`) REFERENCES `m_turnamen` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 7 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of grup
-- ----------------------------
INSERT INTO `grup` VALUES (1, 3, 'Grup A', 1, 0, '2026-07-02 03:32:10', '2026-07-02 03:33:18');
INSERT INTO `grup` VALUES (2, 3, 'Grup B', 1, 0, '2026-07-02 03:32:10', '2026-07-02 03:33:18');
INSERT INTO `grup` VALUES (3, 3, 'Grup A', 1, 0, '2026-07-02 03:33:18', '2026-07-02 03:33:52');
INSERT INTO `grup` VALUES (4, 3, 'Grup B', 1, 0, '2026-07-02 03:33:18', '2026-07-02 03:33:52');
INSERT INTO `grup` VALUES (5, 3, 'Grup A', 2, 0, '2026-07-02 03:33:52', '2026-07-02 04:01:37');
INSERT INTO `grup` VALUES (6, 1, 'Grup A', 1, 1, '2026-07-02 04:12:12', '2026-07-02 04:12:12');

-- ----------------------------
-- Table structure for grup_member
-- ----------------------------
DROP TABLE IF EXISTS `grup_member`;
CREATE TABLE `grup_member`  (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_grup` bigint UNSIGNED NOT NULL,
  `id_pemain` bigint UNSIGNED NOT NULL,
  `id_turnamen_peserta` bigint UNSIGNED NULL DEFAULT NULL,
  `poin_didapat` int NOT NULL DEFAULT 0,
  `poin_akumulasi` int NOT NULL DEFAULT 0,
  `set_menang` int UNSIGNED NOT NULL DEFAULT 0,
  `games_menang` int UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `grup_member_id_grup_id_pemain_unique`(`id_grup` ASC, `id_pemain` ASC) USING BTREE,
  INDEX `grup_member_id_pemain_foreign`(`id_pemain` ASC) USING BTREE,
  INDEX `grup_member_id_turnamen_peserta_foreign`(`id_turnamen_peserta` ASC) USING BTREE,
  CONSTRAINT `grup_member_id_grup_foreign` FOREIGN KEY (`id_grup`) REFERENCES `grup` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `grup_member_id_pemain_foreign` FOREIGN KEY (`id_pemain`) REFERENCES `m_pemain` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `grup_member_id_turnamen_peserta_foreign` FOREIGN KEY (`id_turnamen_peserta`) REFERENCES `turnamen_peserta` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 26 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of grup_member
-- ----------------------------
INSERT INTO `grup_member` VALUES (1, 1, 5, 12, 50, 0, 0, 0, '2026-07-02 03:32:10', '2026-07-02 03:32:25');
INSERT INTO `grup_member` VALUES (2, 1, 1, 14, 10, 0, 0, 0, '2026-07-02 03:32:10', '2026-07-02 03:32:26');
INSERT INTO `grup_member` VALUES (3, 1, 7, 13, -30, 0, 0, 0, '2026-07-02 03:32:10', '2026-07-02 03:32:26');
INSERT INTO `grup_member` VALUES (4, 1, 2, 15, -30, 0, 0, 0, '2026-07-02 03:32:10', '2026-07-02 03:32:27');
INSERT INTO `grup_member` VALUES (5, 2, 9, 9, 20, 0, 0, 0, '2026-07-02 03:32:10', '2026-07-02 03:33:03');
INSERT INTO `grup_member` VALUES (6, 2, 10, 10, -10, 0, 0, 0, '2026-07-02 03:32:10', '2026-07-02 03:33:04');
INSERT INTO `grup_member` VALUES (7, 2, 3, 11, 10, 0, 0, 0, '2026-07-02 03:32:10', '2026-07-02 03:33:05');
INSERT INTO `grup_member` VALUES (8, 2, 6, 16, -20, 0, 0, 0, '2026-07-02 03:32:10', '2026-07-02 03:33:06');
INSERT INTO `grup_member` VALUES (9, 3, 2, 15, 0, 10, 0, 0, '2026-07-02 03:33:18', '2026-07-02 03:33:52');
INSERT INTO `grup_member` VALUES (10, 3, 3, 11, 0, -30, 0, 0, '2026-07-02 03:33:18', '2026-07-02 03:33:52');
INSERT INTO `grup_member` VALUES (11, 3, 1, 14, 0, 50, 0, 0, '2026-07-02 03:33:18', '2026-07-02 03:33:52');
INSERT INTO `grup_member` VALUES (12, 3, 7, 13, 0, -30, 0, 0, '2026-07-02 03:33:18', '2026-07-02 03:33:52');
INSERT INTO `grup_member` VALUES (13, 4, 6, 16, 0, -10, 0, 0, '2026-07-02 03:33:18', '2026-07-02 03:33:52');
INSERT INTO `grup_member` VALUES (14, 4, 5, 12, 0, 20, 0, 0, '2026-07-02 03:33:18', '2026-07-02 03:33:52');
INSERT INTO `grup_member` VALUES (15, 4, 10, 10, 0, -20, 0, 0, '2026-07-02 03:33:18', '2026-07-02 03:33:52');
INSERT INTO `grup_member` VALUES (16, 4, 9, 9, 0, 10, 0, 0, '2026-07-02 03:33:18', '2026-07-02 03:33:52');
INSERT INTO `grup_member` VALUES (17, 5, 1, 14, 0, 50, 0, 0, '2026-07-02 03:33:52', '2026-07-02 04:01:37');
INSERT INTO `grup_member` VALUES (18, 5, 5, 12, 0, 10, 0, 0, '2026-07-02 03:33:52', '2026-07-02 04:01:37');
INSERT INTO `grup_member` VALUES (19, 5, 2, 15, 0, 10, 0, 0, '2026-07-02 03:33:52', '2026-07-02 04:01:37');
INSERT INTO `grup_member` VALUES (20, 5, 9, 9, 0, -70, 0, 0, '2026-07-02 03:33:52', '2026-07-02 04:01:37');
INSERT INTO `grup_member` VALUES (21, 6, 5, 5, 6, 0, 6, 150, '2026-07-02 04:12:12', '2026-07-02 04:34:32');
INSERT INTO `grup_member` VALUES (22, 6, 2, 2, 4, 0, 4, 174, '2026-07-02 04:12:12', '2026-07-02 04:35:52');
INSERT INTO `grup_member` VALUES (23, 6, 8, 8, 6, 0, 6, 149, '2026-07-02 04:12:12', '2026-07-02 04:40:27');
INSERT INTO `grup_member` VALUES (24, 6, 6, 6, 0, 0, 1, 145, '2026-07-02 04:12:12', '2026-07-02 04:40:36');
INSERT INTO `grup_member` VALUES (25, 6, 1, 1, 4, 0, 5, 175, '2026-07-02 04:12:12', '2026-07-02 04:40:36');

-- ----------------------------
-- Table structure for m_pemain
-- ----------------------------
DROP TABLE IF EXISTS `m_pemain`;
CREATE TABLE `m_pemain`  (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tgl_lahir` date NULL DEFAULT NULL,
  `usia` tinyint UNSIGNED NULL DEFAULT NULL,
  `gender` enum('male','female') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `no_hp` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rating` decimal(5, 2) NOT NULL DEFAULT 0.00,
  `total_poin` int UNSIGNED NOT NULL DEFAULT 0,
  `foto` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 11 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of m_pemain
-- ----------------------------
INSERT INTO `m_pemain` VALUES (1, 'Andi Wijaya', '1995-03-12', 31, 'male', '+6281234567801', 4.50, 130, NULL, '2026-07-02 02:17:39', '2026-07-02 06:36:41');
INSERT INTO `m_pemain` VALUES (2, 'Budi Santoso', '1992-07-25', 33, 'male', '+6281234567802', 4.20, 70, NULL, '2026-07-02 02:17:39', '2026-07-02 04:35:52');
INSERT INTO `m_pemain` VALUES (3, 'Citra Dewi', '1998-11-08', 27, 'female', '+6281234567803', 3.80, 20, NULL, '2026-07-02 02:17:39', '2026-07-02 04:35:52');
INSERT INTO `m_pemain` VALUES (4, 'Dian Pratama', '1990-01-30', 36, 'male', '+6281234567804', 5.00, 30, NULL, '2026-07-02 02:17:39', '2026-07-02 04:40:17');
INSERT INTO `m_pemain` VALUES (5, 'Eka Putri', '1999-05-17', 27, 'female', '+6281234567805', 3.50, 55, NULL, '2026-07-02 02:17:39', '2026-07-02 04:34:32');
INSERT INTO `m_pemain` VALUES (6, 'Fajar Nugroho', '1994-09-03', 31, 'male', '+6281234567806', 4.00, 0, NULL, '2026-07-02 02:17:39', '2026-07-02 03:31:07');
INSERT INTO `m_pemain` VALUES (7, 'Gita Rahayu', '2000-12-22', 25, 'female', '+6281234567807', 3.20, 0, NULL, '2026-07-02 02:17:39', '2026-07-02 03:28:10');
INSERT INTO `m_pemain` VALUES (8, 'Hendra Kusuma', '1988-06-14', 37, 'male', '081234567808', 4.80, 30, NULL, '2026-07-02 02:17:39', '2026-07-02 04:40:17');
INSERT INTO `m_pemain` VALUES (9, 'Jonah', NULL, NULL, 'female', '+629812312312', 7.00, 30, 'img/pemain/pemain_6a45cdbc907901.54346694.webp', '2026-07-02 02:32:28', '2026-07-02 06:36:41');
INSERT INTO `m_pemain` VALUES (10, 'Andik Firman', NULL, NULL, 'male', '+6281276812312', 7.00, 30, NULL, '2026-07-02 03:21:11', '2026-07-02 04:34:32');

-- ----------------------------
-- Table structure for m_turnamen
-- ----------------------------
DROP TABLE IF EXISTS `m_turnamen`;
CREATE TABLE `m_turnamen`  (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tanggal` date NULL DEFAULT NULL,
  `harga` decimal(12, 2) NOT NULL DEFAULT 0.00,
  `syarat` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `jenis` enum('single','double','mahjong') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'single',
  `status` enum('draft','open','ongoing','completed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `mahjong_is_final` tinyint(1) NOT NULL DEFAULT 0,
  `doc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `dom` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 4 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of m_turnamen
-- ----------------------------
INSERT INTO `m_turnamen` VALUES (1, 'Born Padel Open 2026', '2026-03-15', 250000.00, 'Minimal usia 18 tahun, rating WPT minimal 2.0, membawa raket sendiri.', 'double', 'ongoing', 0, '2026-07-02 02:17:39', '2026-07-02 04:11:44');
INSERT INTO `m_turnamen` VALUES (2, 'Born Padel Club Championship', '2026-04-20', 150000.00, 'Terbuka untuk member aktif Born Padel Club.', 'single', 'draft', 0, '2026-07-02 02:17:39', '2026-07-02 02:17:39');
INSERT INTO `m_turnamen` VALUES (3, 'Omahjong Tournament 2026', '2026-08-01', 0.00, '- Bisa main Mahjong\r\n- Paham aturan Mahjong', 'mahjong', 'completed', 1, '2026-07-02 02:23:41', '2026-07-02 04:01:37');

-- ----------------------------
-- Table structure for m_users
-- ----------------------------
DROP TABLE IF EXISTS `m_users`;
CREATE TABLE `m_users`  (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','panitia') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'panitia',
  `id_turnamen` bigint UNSIGNED NULL DEFAULT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `m_users_username_unique`(`username` ASC) USING BTREE,
  UNIQUE INDEX `m_users_email_unique`(`email` ASC) USING BTREE,
  INDEX `m_users_id_turnamen_foreign`(`id_turnamen` ASC) USING BTREE,
  CONSTRAINT `m_users_id_turnamen_foreign` FOREIGN KEY (`id_turnamen`) REFERENCES `m_turnamen` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of m_users
-- ----------------------------
INSERT INTO `m_users` VALUES (1, 'Admin Born Padel', 'admin', 'admin@bornpadel.com', NULL, '$2y$10$NS1cV/lG4ZfslzeHnuYkRu8kd.LzgX/14B4ZENdSQW5HFb3YQceoG', 'admin', NULL, NULL, '2026-07-02 02:17:39', '2026-07-02 02:17:39');

-- ----------------------------
-- Table structure for migrations
-- ----------------------------
DROP TABLE IF EXISTS `migrations`;
CREATE TABLE `migrations`  (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 32 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of migrations
-- ----------------------------
INSERT INTO `migrations` VALUES (1, '2014_10_12_000000_create_users_table', 1);
INSERT INTO `migrations` VALUES (2, '2014_10_12_100000_create_password_resets_table', 1);
INSERT INTO `migrations` VALUES (3, '2019_08_19_000000_create_failed_jobs_table', 1);
INSERT INTO `migrations` VALUES (4, '2019_12_14_000001_create_personal_access_tokens_table', 1);
INSERT INTO `migrations` VALUES (5, '2024_01_01_000001_create_turnamen_table', 1);
INSERT INTO `migrations` VALUES (6, '2024_01_01_000002_create_pemain_table', 1);
INSERT INTO `migrations` VALUES (7, '2024_01_01_000003_create_grup_table', 1);
INSERT INTO `migrations` VALUES (8, '2024_01_01_000004_create_grup_member_table', 1);
INSERT INTO `migrations` VALUES (9, '2024_01_01_000005_create_pertandingan_table', 1);
INSERT INTO `migrations` VALUES (10, '2024_01_01_000006_create_pertandingan_skor_table', 1);
INSERT INTO `migrations` VALUES (11, '2024_01_02_000001_add_username_to_users_table', 1);
INSERT INTO `migrations` VALUES (12, '2024_01_03_000001_make_pertandingan_pemain_nullable', 1);
INSERT INTO `migrations` VALUES (13, '2024_01_04_000001_create_turnamen_peserta_table', 1);
INSERT INTO `migrations` VALUES (14, '2024_01_05_000001_migrate_pemain_foto_to_public', 1);
INSERT INTO `migrations` VALUES (15, '2024_01_06_000001_add_role_and_id_turnamen_to_users_table', 1);
INSERT INTO `migrations` VALUES (16, '2024_01_06_000002_make_email_nullable_on_users_table', 1);
INSERT INTO `migrations` VALUES (17, '2024_01_07_000001_rename_master_tables', 1);
INSERT INTO `migrations` VALUES (18, '2024_01_08_000001_add_jenis_to_m_turnamen_table', 1);
INSERT INTO `migrations` VALUES (19, '2024_01_09_000001_rename_pemain_columns_on_turnamen_peserta_table', 1);
INSERT INTO `migrations` VALUES (20, '2024_01_10_000001_add_peserta_columns_for_double_competition', 1);
INSERT INTO `migrations` VALUES (21, '2024_01_11_000001_make_tgl_lahir_nullable_on_m_pemain_table', 1);
INSERT INTO `migrations` VALUES (22, '2024_01_12_000001_make_id_pemain1_nullable_on_turnamen_peserta_table', 1);
INSERT INTO `migrations` VALUES (23, '2024_01_13_000001_add_bukti_bayar_and_expand_status_on_turnamen_peserta', 1);
INSERT INTO `migrations` VALUES (24, '2024_01_13_000002_add_total_poin_to_m_pemain_table', 1);
INSERT INTO `migrations` VALUES (25, '2024_01_13_000003_add_babak_16_to_pertandingan_nama_ronde', 1);
INSERT INTO `migrations` VALUES (26, '2024_01_14_000001_add_tanggal_to_m_turnamen_table', 1);
INSERT INTO `migrations` VALUES (27, '2024_01_15_000001_add_mahjong_jenis_to_m_turnamen_table', 1);
INSERT INTO `migrations` VALUES (28, '2024_01_15_000002_add_mahjong_columns_to_grup_table', 1);
INSERT INTO `migrations` VALUES (29, '2024_01_15_000003_add_poin_akumulasi_to_grup_member_table', 1);
INSERT INTO `migrations` VALUES (30, '2024_01_15_000004_create_turnamen_pemenang_table', 1);
INSERT INTO `migrations` VALUES (31, '2024_01_16_000001_allow_negative_mahjong_points_on_grup_member_table', 1);

-- ----------------------------
-- Table structure for password_resets
-- ----------------------------
DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets`  (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  INDEX `password_resets_email_index`(`email` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of password_resets
-- ----------------------------

-- ----------------------------
-- Table structure for personal_access_tokens
-- ----------------------------
DROP TABLE IF EXISTS `personal_access_tokens`;
CREATE TABLE `personal_access_tokens`  (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `personal_access_tokens_token_unique`(`token` ASC) USING BTREE,
  INDEX `personal_access_tokens_tokenable_type_tokenable_id_index`(`tokenable_type` ASC, `tokenable_id` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of personal_access_tokens
-- ----------------------------

-- ----------------------------
-- Table structure for pertandingan
-- ----------------------------
DROP TABLE IF EXISTS `pertandingan`;
CREATE TABLE `pertandingan`  (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_turnamen` bigint UNSIGNED NOT NULL,
  `id_grup` bigint UNSIGNED NULL DEFAULT NULL,
  `nama_ronde` enum('Fase Grup','Babak 16 Besar','Perempatfinal','Semifinal','Final') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_pemain1` bigint UNSIGNED NULL DEFAULT NULL,
  `id_pemain2` bigint UNSIGNED NULL DEFAULT NULL,
  `id_peserta1` bigint UNSIGNED NULL DEFAULT NULL,
  `id_peserta2` bigint UNSIGNED NULL DEFAULT NULL,
  `id_pemenang` bigint UNSIGNED NULL DEFAULT NULL,
  `id_peserta_pemenang` bigint UNSIGNED NULL DEFAULT NULL,
  `status` enum('scheduled','ongoing','completed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'scheduled',
  `id_next_pertandingan` bigint UNSIGNED NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `pertandingan_id_turnamen_foreign`(`id_turnamen` ASC) USING BTREE,
  INDEX `pertandingan_id_grup_foreign`(`id_grup` ASC) USING BTREE,
  INDEX `pertandingan_id_pemain1_foreign`(`id_pemain1` ASC) USING BTREE,
  INDEX `pertandingan_id_pemain2_foreign`(`id_pemain2` ASC) USING BTREE,
  INDEX `pertandingan_id_pemenang_foreign`(`id_pemenang` ASC) USING BTREE,
  INDEX `pertandingan_id_next_pertandingan_foreign`(`id_next_pertandingan` ASC) USING BTREE,
  INDEX `pertandingan_id_peserta1_foreign`(`id_peserta1` ASC) USING BTREE,
  INDEX `pertandingan_id_peserta2_foreign`(`id_peserta2` ASC) USING BTREE,
  INDEX `pertandingan_id_peserta_pemenang_foreign`(`id_peserta_pemenang` ASC) USING BTREE,
  CONSTRAINT `pertandingan_id_grup_foreign` FOREIGN KEY (`id_grup`) REFERENCES `grup` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `pertandingan_id_next_pertandingan_foreign` FOREIGN KEY (`id_next_pertandingan`) REFERENCES `pertandingan` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `pertandingan_id_pemain1_foreign` FOREIGN KEY (`id_pemain1`) REFERENCES `m_pemain` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `pertandingan_id_pemain2_foreign` FOREIGN KEY (`id_pemain2`) REFERENCES `m_pemain` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `pertandingan_id_pemenang_foreign` FOREIGN KEY (`id_pemenang`) REFERENCES `m_pemain` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `pertandingan_id_peserta1_foreign` FOREIGN KEY (`id_peserta1`) REFERENCES `turnamen_peserta` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `pertandingan_id_peserta2_foreign` FOREIGN KEY (`id_peserta2`) REFERENCES `turnamen_peserta` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `pertandingan_id_peserta_pemenang_foreign` FOREIGN KEY (`id_peserta_pemenang`) REFERENCES `turnamen_peserta` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `pertandingan_id_turnamen_foreign` FOREIGN KEY (`id_turnamen`) REFERENCES `m_turnamen` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 14 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of pertandingan
-- ----------------------------
INSERT INTO `pertandingan` VALUES (1, 1, 6, 'Fase Grup', 5, 2, 5, 2, 5, 5, 'completed', NULL, '2026-07-02 04:12:12', '2026-07-02 04:32:46');
INSERT INTO `pertandingan` VALUES (2, 1, 6, 'Fase Grup', 5, 8, 5, 8, 8, 8, 'completed', NULL, '2026-07-02 04:12:12', '2026-07-02 04:33:17');
INSERT INTO `pertandingan` VALUES (3, 1, 6, 'Fase Grup', 5, 6, 5, 6, 5, 5, 'completed', NULL, '2026-07-02 04:12:12', '2026-07-02 04:33:44');
INSERT INTO `pertandingan` VALUES (4, 1, 6, 'Fase Grup', 5, 1, 5, 1, 5, 5, 'completed', NULL, '2026-07-02 04:12:12', '2026-07-02 04:34:32');
INSERT INTO `pertandingan` VALUES (5, 1, 6, 'Fase Grup', 2, 8, 2, 8, 8, 8, 'completed', NULL, '2026-07-02 04:12:12', '2026-07-02 04:35:03');
INSERT INTO `pertandingan` VALUES (6, 1, 6, 'Fase Grup', 2, 6, 2, 6, 2, 2, 'completed', NULL, '2026-07-02 04:12:12', '2026-07-02 04:35:17');
INSERT INTO `pertandingan` VALUES (7, 1, 6, 'Fase Grup', 2, 1, 2, 1, 2, 2, 'completed', NULL, '2026-07-02 04:12:12', '2026-07-02 04:35:52');
INSERT INTO `pertandingan` VALUES (8, 1, 6, 'Fase Grup', 8, 6, 8, 6, 8, 8, 'completed', NULL, '2026-07-02 04:12:12', '2026-07-02 04:40:17');
INSERT INTO `pertandingan` VALUES (9, 1, 6, 'Fase Grup', 8, 1, 8, 1, 1, 1, 'completed', NULL, '2026-07-02 04:12:12', '2026-07-02 04:40:27');
INSERT INTO `pertandingan` VALUES (10, 1, 6, 'Fase Grup', 6, 1, 6, 1, 1, 1, 'completed', NULL, '2026-07-02 04:12:12', '2026-07-02 04:40:36');
INSERT INTO `pertandingan` VALUES (11, 1, NULL, 'Semifinal', 5, NULL, 5, NULL, 5, 5, 'completed', 13, '2026-07-02 04:41:12', '2026-07-02 04:41:12');
INSERT INTO `pertandingan` VALUES (12, 1, NULL, 'Semifinal', 8, 1, 8, 1, 1, 1, 'completed', 13, '2026-07-02 04:41:12', '2026-07-02 06:36:41');
INSERT INTO `pertandingan` VALUES (13, 1, NULL, 'Final', 5, 1, 5, 1, NULL, NULL, 'scheduled', NULL, '2026-07-02 04:41:12', '2026-07-02 06:36:41');

-- ----------------------------
-- Table structure for pertandingan_skor
-- ----------------------------
DROP TABLE IF EXISTS `pertandingan_skor`;
CREATE TABLE `pertandingan_skor`  (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_pertandingan` bigint UNSIGNED NOT NULL,
  `set_ke` tinyint UNSIGNED NOT NULL,
  `skor_pemain1` tinyint UNSIGNED NOT NULL DEFAULT 0,
  `skor_pemain2` tinyint UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `pertandingan_skor_id_pertandingan_set_ke_unique`(`id_pertandingan` ASC, `set_ke` ASC) USING BTREE,
  CONSTRAINT `pertandingan_skor_id_pertandingan_foreign` FOREIGN KEY (`id_pertandingan`) REFERENCES `pertandingan` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 25 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of pertandingan_skor
-- ----------------------------
INSERT INTO `pertandingan_skor` VALUES (1, 1, 1, 20, 18, '2026-07-02 04:32:46', '2026-07-02 04:32:46');
INSERT INTO `pertandingan_skor` VALUES (2, 1, 2, 20, 18, '2026-07-02 04:32:46', '2026-07-02 04:32:46');
INSERT INTO `pertandingan_skor` VALUES (3, 2, 1, 10, 20, '2026-07-02 04:33:17', '2026-07-02 04:33:17');
INSERT INTO `pertandingan_skor` VALUES (4, 2, 2, 10, 20, '2026-07-02 04:33:17', '2026-07-02 04:33:17');
INSERT INTO `pertandingan_skor` VALUES (5, 3, 1, 20, 10, '2026-07-02 04:33:44', '2026-07-02 04:33:44');
INSERT INTO `pertandingan_skor` VALUES (6, 3, 2, 10, 20, '2026-07-02 04:33:44', '2026-07-02 04:33:44');
INSERT INTO `pertandingan_skor` VALUES (7, 3, 3, 20, 10, '2026-07-02 04:33:44', '2026-07-02 04:33:44');
INSERT INTO `pertandingan_skor` VALUES (8, 4, 1, 20, 18, '2026-07-02 04:34:32', '2026-07-02 04:34:32');
INSERT INTO `pertandingan_skor` VALUES (9, 4, 2, 20, 18, '2026-07-02 04:34:32', '2026-07-02 04:34:32');
INSERT INTO `pertandingan_skor` VALUES (10, 5, 1, 18, 20, '2026-07-02 04:35:03', '2026-07-02 04:35:03');
INSERT INTO `pertandingan_skor` VALUES (11, 5, 2, 18, 20, '2026-07-02 04:35:03', '2026-07-02 04:35:03');
INSERT INTO `pertandingan_skor` VALUES (12, 6, 1, 20, 19, '2026-07-02 04:35:17', '2026-07-02 04:35:17');
INSERT INTO `pertandingan_skor` VALUES (13, 6, 2, 20, 18, '2026-07-02 04:35:17', '2026-07-02 04:35:17');
INSERT INTO `pertandingan_skor` VALUES (14, 7, 1, 20, 18, '2026-07-02 04:35:52', '2026-07-02 04:35:52');
INSERT INTO `pertandingan_skor` VALUES (15, 7, 2, 22, 24, '2026-07-02 04:35:52', '2026-07-02 04:35:52');
INSERT INTO `pertandingan_skor` VALUES (16, 7, 3, 20, 16, '2026-07-02 04:35:52', '2026-07-02 04:35:52');
INSERT INTO `pertandingan_skor` VALUES (17, 8, 1, 20, 18, '2026-07-02 04:40:17', '2026-07-02 04:40:17');
INSERT INTO `pertandingan_skor` VALUES (18, 8, 2, 20, 17, '2026-07-02 04:40:17', '2026-07-02 04:40:17');
INSERT INTO `pertandingan_skor` VALUES (19, 9, 1, 10, 20, '2026-07-02 04:40:27', '2026-07-02 04:40:27');
INSERT INTO `pertandingan_skor` VALUES (20, 9, 2, 19, 21, '2026-07-02 04:40:27', '2026-07-02 04:40:27');
INSERT INTO `pertandingan_skor` VALUES (21, 10, 1, 18, 20, '2026-07-02 04:40:36', '2026-07-02 04:40:36');
INSERT INTO `pertandingan_skor` VALUES (22, 10, 2, 15, 20, '2026-07-02 04:40:36', '2026-07-02 04:40:36');
INSERT INTO `pertandingan_skor` VALUES (23, 12, 1, 19, 21, '2026-07-02 06:36:41', '2026-07-02 06:36:41');
INSERT INTO `pertandingan_skor` VALUES (24, 12, 2, 18, 20, '2026-07-02 06:36:41', '2026-07-02 06:36:41');

-- ----------------------------
-- Table structure for turnamen_pemenang
-- ----------------------------
DROP TABLE IF EXISTS `turnamen_pemenang`;
CREATE TABLE `turnamen_pemenang`  (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_turnamen` bigint UNSIGNED NOT NULL,
  `peringkat` tinyint UNSIGNED NOT NULL,
  `id_pemain` bigint UNSIGNED NOT NULL,
  `id_turnamen_peserta` bigint UNSIGNED NULL DEFAULT NULL,
  `total_poin` int UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `turnamen_pemenang_id_turnamen_peringkat_unique`(`id_turnamen` ASC, `peringkat` ASC) USING BTREE,
  INDEX `turnamen_pemenang_id_pemain_foreign`(`id_pemain` ASC) USING BTREE,
  INDEX `turnamen_pemenang_id_turnamen_peserta_foreign`(`id_turnamen_peserta` ASC) USING BTREE,
  CONSTRAINT `turnamen_pemenang_id_pemain_foreign` FOREIGN KEY (`id_pemain`) REFERENCES `m_pemain` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `turnamen_pemenang_id_turnamen_foreign` FOREIGN KEY (`id_turnamen`) REFERENCES `m_turnamen` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `turnamen_pemenang_id_turnamen_peserta_foreign` FOREIGN KEY (`id_turnamen_peserta`) REFERENCES `turnamen_peserta` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 4 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of turnamen_pemenang
-- ----------------------------
INSERT INTO `turnamen_pemenang` VALUES (1, 3, 1, 1, 14, 50, '2026-07-02 04:01:37', '2026-07-02 04:01:37');
INSERT INTO `turnamen_pemenang` VALUES (2, 3, 2, 2, 15, 10, '2026-07-02 04:01:37', '2026-07-02 04:01:37');
INSERT INTO `turnamen_pemenang` VALUES (3, 3, 3, 5, 12, 10, '2026-07-02 04:01:37', '2026-07-02 04:01:37');

-- ----------------------------
-- Table structure for turnamen_peserta
-- ----------------------------
DROP TABLE IF EXISTS `turnamen_peserta`;
CREATE TABLE `turnamen_peserta`  (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_turnamen` bigint UNSIGNED NOT NULL,
  `id_pemain1` bigint UNSIGNED NULL DEFAULT NULL,
  `id_pemain2` bigint UNSIGNED NULL DEFAULT NULL,
  `status` enum('pending','approved','rejected','unpaid','paid') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `bukti_bayar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `turnamen_peserta_id_turnamen_id_pemain1_unique`(`id_turnamen` ASC, `id_pemain1` ASC) USING BTREE,
  INDEX `turnamen_peserta_id_pemain2_foreign`(`id_pemain2` ASC) USING BTREE,
  INDEX `turnamen_peserta_id_pemain1_foreign`(`id_pemain1` ASC) USING BTREE,
  CONSTRAINT `turnamen_peserta_id_pemain1_foreign` FOREIGN KEY (`id_pemain1`) REFERENCES `m_pemain` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `turnamen_peserta_id_pemain2_foreign` FOREIGN KEY (`id_pemain2`) REFERENCES `m_pemain` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `turnamen_peserta_id_turnamen_foreign` FOREIGN KEY (`id_turnamen`) REFERENCES `m_turnamen` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 17 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of turnamen_peserta
-- ----------------------------
INSERT INTO `turnamen_peserta` VALUES (1, 1, 1, 9, 'approved', NULL, '2026-07-02 02:17:39', '2026-07-02 03:20:19');
INSERT INTO `turnamen_peserta` VALUES (2, 1, 2, 3, 'approved', NULL, '2026-07-02 02:17:39', '2026-07-02 04:08:16');
INSERT INTO `turnamen_peserta` VALUES (5, 1, 5, 10, 'approved', NULL, '2026-07-02 02:17:39', '2026-07-02 04:10:48');
INSERT INTO `turnamen_peserta` VALUES (6, 1, 6, 7, 'approved', NULL, '2026-07-02 02:17:39', '2026-07-02 04:11:04');
INSERT INTO `turnamen_peserta` VALUES (8, 1, 8, 4, 'approved', NULL, '2026-07-02 02:17:39', '2026-07-02 04:11:30');
INSERT INTO `turnamen_peserta` VALUES (9, 3, 9, NULL, 'approved', NULL, '2026-07-02 02:32:28', '2026-07-02 03:26:32');
INSERT INTO `turnamen_peserta` VALUES (10, 3, 10, NULL, 'approved', 'img/bukti-bayar/bayar_6a45d927e82f63.00174928.jpg', '2026-07-02 03:21:11', '2026-07-02 03:27:31');
INSERT INTO `turnamen_peserta` VALUES (11, 3, 3, NULL, 'approved', NULL, '2026-07-02 03:28:55', '2026-07-02 03:28:55');
INSERT INTO `turnamen_peserta` VALUES (12, 3, 5, NULL, 'approved', NULL, '2026-07-02 03:29:11', '2026-07-02 03:29:11');
INSERT INTO `turnamen_peserta` VALUES (13, 3, 7, NULL, 'approved', NULL, '2026-07-02 03:29:59', '2026-07-02 03:29:59');
INSERT INTO `turnamen_peserta` VALUES (14, 3, 1, NULL, 'approved', NULL, '2026-07-02 03:30:28', '2026-07-02 03:30:28');
INSERT INTO `turnamen_peserta` VALUES (15, 3, 2, NULL, 'approved', NULL, '2026-07-02 03:30:49', '2026-07-02 03:30:49');
INSERT INTO `turnamen_peserta` VALUES (16, 3, 6, NULL, 'approved', NULL, '2026-07-02 03:31:18', '2026-07-02 03:31:18');

SET FOREIGN_KEY_CHECKS = 1;
