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

 Date: 10/06/2026 17:44:25
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
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `grup_id_turnamen_foreign`(`id_turnamen` ASC) USING BTREE,
  CONSTRAINT `grup_id_turnamen_foreign` FOREIGN KEY (`id_turnamen`) REFERENCES `turnamen` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 3 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of grup
-- ----------------------------
INSERT INTO `grup` VALUES (1, 1, 'Grup A', '2026-06-10 10:06:04', '2026-06-10 10:06:04');
INSERT INTO `grup` VALUES (2, 1, 'Grup B', '2026-06-10 10:06:04', '2026-06-10 10:06:04');

-- ----------------------------
-- Table structure for grup_member
-- ----------------------------
DROP TABLE IF EXISTS `grup_member`;
CREATE TABLE `grup_member`  (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_grup` bigint UNSIGNED NOT NULL,
  `id_pemain` bigint UNSIGNED NOT NULL,
  `poin_didapat` int UNSIGNED NOT NULL DEFAULT 0,
  `set_menang` int UNSIGNED NOT NULL DEFAULT 0,
  `games_menang` int UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `grup_member_id_grup_id_pemain_unique`(`id_grup` ASC, `id_pemain` ASC) USING BTREE,
  INDEX `grup_member_id_pemain_foreign`(`id_pemain` ASC) USING BTREE,
  CONSTRAINT `grup_member_id_grup_foreign` FOREIGN KEY (`id_grup`) REFERENCES `grup` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `grup_member_id_pemain_foreign` FOREIGN KEY (`id_pemain`) REFERENCES `pemain` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 8 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of grup_member
-- ----------------------------
INSERT INTO `grup_member` VALUES (1, 1, 8, 4, 4, 140, '2026-06-10 10:06:04', '2026-06-10 10:09:32');
INSERT INTO `grup_member` VALUES (2, 1, 3, 2, 2, 109, '2026-06-10 10:06:04', '2026-06-10 10:09:57');
INSERT INTO `grup_member` VALUES (3, 1, 4, 0, 2, 148, '2026-06-10 10:06:04', '2026-06-10 10:10:12');
INSERT INTO `grup_member` VALUES (4, 1, 1, 6, 6, 145, '2026-06-10 10:06:04', '2026-06-10 10:10:12');
INSERT INTO `grup_member` VALUES (5, 2, 6, 4, 4, 103, '2026-06-10 10:06:04', '2026-06-10 10:11:47');
INSERT INTO `grup_member` VALUES (6, 2, 2, 2, 3, 100, '2026-06-10 10:06:04', '2026-06-10 10:11:57');
INSERT INTO `grup_member` VALUES (7, 2, 5, 0, 0, 66, '2026-06-10 10:06:04', '2026-06-10 10:11:57');

-- ----------------------------
-- Table structure for migrations
-- ----------------------------
DROP TABLE IF EXISTS `migrations`;
CREATE TABLE `migrations`  (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 13 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

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
INSERT INTO `migrations` VALUES (11, '2024_01_02_000001_add_username_to_users_table', 2);
INSERT INTO `migrations` VALUES (12, '2024_01_03_000001_make_pertandingan_pemain_nullable', 3);

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
-- Table structure for pemain
-- ----------------------------
DROP TABLE IF EXISTS `pemain`;
CREATE TABLE `pemain`  (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tgl_lahir` date NOT NULL,
  `usia` tinyint UNSIGNED NOT NULL,
  `gender` enum('male','female') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `no_hp` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rating` decimal(5, 2) NOT NULL DEFAULT 0.00,
  `foto` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `status` enum('pending','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 9 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of pemain
-- ----------------------------
INSERT INTO `pemain` VALUES (1, 'Andi Wijaya', '1995-03-12', 31, 'male', '081234567801', 4.50, NULL, 'approved', '2026-06-10 09:33:04', '2026-06-10 09:33:04');
INSERT INTO `pemain` VALUES (2, 'Budi Santoso', '1992-07-25', 33, 'male', '081234567802', 4.20, NULL, 'approved', '2026-06-10 09:33:04', '2026-06-10 09:33:04');
INSERT INTO `pemain` VALUES (3, 'Citra Dewi', '1998-11-08', 27, 'female', '081234567803', 3.80, NULL, 'approved', '2026-06-10 09:33:04', '2026-06-10 09:33:04');
INSERT INTO `pemain` VALUES (4, 'Dian Pratama', '1990-01-30', 36, 'male', '081234567804', 5.00, NULL, 'approved', '2026-06-10 09:33:04', '2026-06-10 09:33:04');
INSERT INTO `pemain` VALUES (5, 'Eka Putri', '1999-05-17', 27, 'female', '081234567805', 3.50, NULL, 'approved', '2026-06-10 09:33:04', '2026-06-10 09:33:04');
INSERT INTO `pemain` VALUES (6, 'Fajar Nugroho', '1994-09-03', 31, 'male', '081234567806', 4.00, NULL, 'approved', '2026-06-10 09:33:04', '2026-06-10 09:33:04');
INSERT INTO `pemain` VALUES (7, 'Gita Rahayu', '2000-12-22', 25, 'female', '081234567807', 3.20, NULL, 'approved', '2026-06-10 09:33:04', '2026-06-10 10:11:03');
INSERT INTO `pemain` VALUES (8, 'Hendra Kusuma', '1988-06-14', 37, 'male', '081234567808', 4.80, NULL, 'approved', '2026-06-10 09:33:04', '2026-06-10 09:33:04');

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
  `nama_ronde` enum('Fase Grup','Perempatfinal','Semifinal','Final') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_pemain1` bigint UNSIGNED NULL DEFAULT NULL,
  `id_pemain2` bigint UNSIGNED NULL DEFAULT NULL,
  `id_pemenang` bigint UNSIGNED NULL DEFAULT NULL,
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
  CONSTRAINT `pertandingan_id_grup_foreign` FOREIGN KEY (`id_grup`) REFERENCES `grup` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `pertandingan_id_next_pertandingan_foreign` FOREIGN KEY (`id_next_pertandingan`) REFERENCES `pertandingan` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `pertandingan_id_pemenang_foreign` FOREIGN KEY (`id_pemenang`) REFERENCES `pemain` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `pertandingan_id_turnamen_foreign` FOREIGN KEY (`id_turnamen`) REFERENCES `turnamen` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 13 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of pertandingan
-- ----------------------------
INSERT INTO `pertandingan` VALUES (1, 1, 1, 'Fase Grup', 8, 3, 8, 'completed', NULL, '2026-06-10 10:06:04', '2026-06-10 10:07:18');
INSERT INTO `pertandingan` VALUES (2, 1, 1, 'Fase Grup', 8, 4, 8, 'completed', NULL, '2026-06-10 10:06:04', '2026-06-10 10:08:42');
INSERT INTO `pertandingan` VALUES (3, 1, 1, 'Fase Grup', 8, 1, 1, 'completed', NULL, '2026-06-10 10:06:04', '2026-06-10 10:09:32');
INSERT INTO `pertandingan` VALUES (4, 1, 1, 'Fase Grup', 3, 4, 3, 'completed', NULL, '2026-06-10 10:06:04', '2026-06-10 10:09:57');
INSERT INTO `pertandingan` VALUES (5, 1, 1, 'Fase Grup', 3, 1, 1, 'completed', NULL, '2026-06-10 10:06:04', '2026-06-10 10:09:45');
INSERT INTO `pertandingan` VALUES (6, 1, 1, 'Fase Grup', 4, 1, 1, 'completed', NULL, '2026-06-10 10:06:04', '2026-06-10 10:10:12');
INSERT INTO `pertandingan` VALUES (7, 1, 2, 'Fase Grup', 6, 2, 6, 'completed', NULL, '2026-06-10 10:06:04', '2026-06-10 10:11:37');
INSERT INTO `pertandingan` VALUES (8, 1, 2, 'Fase Grup', 6, 5, 6, 'completed', NULL, '2026-06-10 10:06:04', '2026-06-10 10:11:47');
INSERT INTO `pertandingan` VALUES (9, 1, 2, 'Fase Grup', 2, 5, 2, 'completed', NULL, '2026-06-10 10:06:04', '2026-06-10 10:11:57');
INSERT INTO `pertandingan` VALUES (10, 1, NULL, 'Final', 1, 6, 6, 'completed', NULL, '2026-06-10 10:20:26', '2026-06-10 10:22:24');
INSERT INTO `pertandingan` VALUES (11, 1, NULL, 'Semifinal', 1, 2, 1, 'completed', 10, '2026-06-10 10:20:26', '2026-06-10 10:21:42');
INSERT INTO `pertandingan` VALUES (12, 1, NULL, 'Semifinal', 6, 8, 6, 'completed', 10, '2026-06-10 10:20:26', '2026-06-10 10:21:53');

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
) ENGINE = InnoDB AUTO_INCREMENT = 30 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of pertandingan_skor
-- ----------------------------
INSERT INTO `pertandingan_skor` VALUES (1, 1, 1, 21, 19, '2026-06-10 10:07:18', '2026-06-10 10:07:18');
INSERT INTO `pertandingan_skor` VALUES (2, 1, 2, 21, 15, '2026-06-10 10:07:18', '2026-06-10 10:07:18');
INSERT INTO `pertandingan_skor` VALUES (3, 2, 1, 19, 21, '2026-06-10 10:08:42', '2026-06-10 10:08:42');
INSERT INTO `pertandingan_skor` VALUES (4, 2, 2, 21, 18, '2026-06-10 10:08:42', '2026-06-10 10:08:42');
INSERT INTO `pertandingan_skor` VALUES (5, 2, 3, 21, 16, '2026-06-10 10:08:42', '2026-06-10 10:08:42');
INSERT INTO `pertandingan_skor` VALUES (6, 3, 1, 18, 21, '2026-06-10 10:09:32', '2026-06-10 10:09:32');
INSERT INTO `pertandingan_skor` VALUES (7, 3, 2, 19, 21, '2026-06-10 10:09:32', '2026-06-10 10:09:32');
INSERT INTO `pertandingan_skor` VALUES (8, 5, 1, 15, 21, '2026-06-10 10:09:45', '2026-06-10 10:09:45');
INSERT INTO `pertandingan_skor` VALUES (9, 5, 2, 18, 21, '2026-06-10 10:09:45', '2026-06-10 10:09:45');
INSERT INTO `pertandingan_skor` VALUES (10, 4, 1, 21, 19, '2026-06-10 10:09:57', '2026-06-10 10:09:57');
INSERT INTO `pertandingan_skor` VALUES (11, 4, 2, 21, 19, '2026-06-10 10:09:57', '2026-06-10 10:09:57');
INSERT INTO `pertandingan_skor` VALUES (12, 6, 1, 18, 21, '2026-06-10 10:10:12', '2026-06-10 10:10:12');
INSERT INTO `pertandingan_skor` VALUES (13, 6, 2, 21, 19, '2026-06-10 10:10:12', '2026-06-10 10:10:12');
INSERT INTO `pertandingan_skor` VALUES (14, 6, 3, 16, 21, '2026-06-10 10:10:12', '2026-06-10 10:10:12');
INSERT INTO `pertandingan_skor` VALUES (15, 7, 1, 21, 19, '2026-06-10 10:11:37', '2026-06-10 10:11:37');
INSERT INTO `pertandingan_skor` VALUES (16, 7, 2, 19, 21, '2026-06-10 10:11:37', '2026-06-10 10:11:37');
INSERT INTO `pertandingan_skor` VALUES (17, 7, 3, 21, 18, '2026-06-10 10:11:37', '2026-06-10 10:11:37');
INSERT INTO `pertandingan_skor` VALUES (18, 8, 1, 21, 15, '2026-06-10 10:11:47', '2026-06-10 10:11:47');
INSERT INTO `pertandingan_skor` VALUES (19, 8, 2, 21, 15, '2026-06-10 10:11:47', '2026-06-10 10:11:47');
INSERT INTO `pertandingan_skor` VALUES (20, 9, 1, 21, 19, '2026-06-10 10:11:57', '2026-06-10 10:11:57');
INSERT INTO `pertandingan_skor` VALUES (21, 9, 2, 21, 17, '2026-06-10 10:11:57', '2026-06-10 10:11:57');
INSERT INTO `pertandingan_skor` VALUES (22, 11, 1, 21, 19, '2026-06-10 10:21:42', '2026-06-10 10:21:42');
INSERT INTO `pertandingan_skor` VALUES (23, 11, 2, 19, 21, '2026-06-10 10:21:42', '2026-06-10 10:21:42');
INSERT INTO `pertandingan_skor` VALUES (24, 11, 3, 21, 19, '2026-06-10 10:21:42', '2026-06-10 10:21:42');
INSERT INTO `pertandingan_skor` VALUES (25, 12, 1, 21, 19, '2026-06-10 10:21:53', '2026-06-10 10:21:53');
INSERT INTO `pertandingan_skor` VALUES (26, 12, 2, 21, 19, '2026-06-10 10:21:53', '2026-06-10 10:21:53');
INSERT INTO `pertandingan_skor` VALUES (27, 10, 1, 21, 16, '2026-06-10 10:22:24', '2026-06-10 10:22:24');
INSERT INTO `pertandingan_skor` VALUES (28, 10, 2, 19, 21, '2026-06-10 10:22:24', '2026-06-10 10:22:24');
INSERT INTO `pertandingan_skor` VALUES (29, 10, 3, 18, 21, '2026-06-10 10:22:24', '2026-06-10 10:22:24');

-- ----------------------------
-- Table structure for turnamen
-- ----------------------------
DROP TABLE IF EXISTS `turnamen`;
CREATE TABLE `turnamen`  (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `harga` decimal(12, 2) NOT NULL DEFAULT 0.00,
  `syarat` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `status` enum('draft','open','ongoing','completed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `doc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `dom` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 3 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of turnamen
-- ----------------------------
INSERT INTO `turnamen` VALUES (1, 'Born Padel Open 2026', 250000.00, 'Minimal usia 18 tahun, rating WPT minimal 2.0, membawa raket sendiri.', 'ongoing', '2026-06-10 09:33:04', '2026-06-10 10:05:17');
INSERT INTO `turnamen` VALUES (2, 'Born Padel Club Championship', 150000.00, 'Terbuka untuk member aktif Born Padel Club.', 'draft', '2026-06-10 09:33:04', '2026-06-10 09:33:04');

-- ----------------------------
-- Table structure for users
-- ----------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users`  (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `users_email_unique`(`email` ASC) USING BTREE,
  UNIQUE INDEX `users_username_unique`(`username` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 3 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of users
-- ----------------------------
INSERT INTO `users` VALUES (1, 'Admin Born Padel', 'admin', 'admin@bornpadel.com', NULL, '$2y$10$bxW/0ZGcFEzTKIz1bbQv9OL0YtSLmvWR8wb2tJ6vEg.fbY7gMcd12', NULL, '2026-06-10 09:33:04', '2026-06-10 10:02:37');

SET FOREIGN_KEY_CHECKS = 1;
