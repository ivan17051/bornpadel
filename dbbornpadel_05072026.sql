-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               5.7.39 - MySQL Community Server (GPL)
-- Server OS:                    Win64
-- HeidiSQL Version:             11.3.0.6295
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dumping database structure for dbbornpadel
CREATE DATABASE IF NOT EXISTS `dbbornpadel` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;
USE `dbbornpadel`;

-- Dumping structure for table dbbornpadel.failed_jobs
CREATE TABLE IF NOT EXISTS `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Dumping data for table dbbornpadel.failed_jobs: ~0 rows (approximately)
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;

-- Dumping structure for table dbbornpadel.grup
CREATE TABLE IF NOT EXISTS `grup` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_turnamen` bigint(20) unsigned NOT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `babak` smallint(5) unsigned NOT NULL DEFAULT '1',
  `is_aktif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `grup_id_turnamen_foreign` (`id_turnamen`) USING BTREE,
  CONSTRAINT `grup_id_turnamen_foreign` FOREIGN KEY (`id_turnamen`) REFERENCES `m_turnamen` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Dumping data for table dbbornpadel.grup: ~5 rows (approximately)
/*!40000 ALTER TABLE `grup` DISABLE KEYS */;
INSERT INTO `grup` (`id`, `id_turnamen`, `nama`, `babak`, `is_aktif`, `created_at`, `updated_at`) VALUES
	(1, 1, 'Grup A', 1, 1, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
INSERT INTO `grup` (`id`, `id_turnamen`, `nama`, `babak`, `is_aktif`, `created_at`, `updated_at`) VALUES
	(2, 1, 'Grup B', 1, 1, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
INSERT INTO `grup` (`id`, `id_turnamen`, `nama`, `babak`, `is_aktif`, `created_at`, `updated_at`) VALUES
	(3, 1, 'Grup C', 1, 1, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
INSERT INTO `grup` (`id`, `id_turnamen`, `nama`, `babak`, `is_aktif`, `created_at`, `updated_at`) VALUES
	(4, 1, 'Grup D', 1, 1, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
INSERT INTO `grup` (`id`, `id_turnamen`, `nama`, `babak`, `is_aktif`, `created_at`, `updated_at`) VALUES
	(5, 2, 'Grup A', 1, 0, '2026-07-05 15:47:26', '2026-07-05 15:49:56');
INSERT INTO `grup` (`id`, `id_turnamen`, `nama`, `babak`, `is_aktif`, `created_at`, `updated_at`) VALUES
	(6, 2, 'Grup B', 1, 0, '2026-07-05 15:47:26', '2026-07-05 15:49:56');
INSERT INTO `grup` (`id`, `id_turnamen`, `nama`, `babak`, `is_aktif`, `created_at`, `updated_at`) VALUES
	(7, 2, 'Grup C', 1, 0, '2026-07-05 15:47:26', '2026-07-05 15:49:56');
INSERT INTO `grup` (`id`, `id_turnamen`, `nama`, `babak`, `is_aktif`, `created_at`, `updated_at`) VALUES
	(8, 2, 'Grup D', 1, 0, '2026-07-05 15:47:26', '2026-07-05 15:49:56');
INSERT INTO `grup` (`id`, `id_turnamen`, `nama`, `babak`, `is_aktif`, `created_at`, `updated_at`) VALUES
	(9, 2, 'Grup A', 1, 0, '2026-07-05 15:49:56', '2026-07-05 16:03:10');
INSERT INTO `grup` (`id`, `id_turnamen`, `nama`, `babak`, `is_aktif`, `created_at`, `updated_at`) VALUES
	(10, 2, 'Grup B', 1, 0, '2026-07-05 15:49:56', '2026-07-05 16:03:10');
INSERT INTO `grup` (`id`, `id_turnamen`, `nama`, `babak`, `is_aktif`, `created_at`, `updated_at`) VALUES
	(11, 2, 'Grup C', 1, 0, '2026-07-05 15:49:56', '2026-07-05 16:03:10');
INSERT INTO `grup` (`id`, `id_turnamen`, `nama`, `babak`, `is_aktif`, `created_at`, `updated_at`) VALUES
	(12, 2, 'Grup D', 1, 0, '2026-07-05 15:49:56', '2026-07-05 16:03:10');
INSERT INTO `grup` (`id`, `id_turnamen`, `nama`, `babak`, `is_aktif`, `created_at`, `updated_at`) VALUES
	(13, 2, 'Grup A', 1, 0, '2026-07-05 16:03:10', '2026-07-05 16:04:44');
INSERT INTO `grup` (`id`, `id_turnamen`, `nama`, `babak`, `is_aktif`, `created_at`, `updated_at`) VALUES
	(14, 2, 'Grup B', 1, 0, '2026-07-05 16:03:10', '2026-07-05 16:04:44');
INSERT INTO `grup` (`id`, `id_turnamen`, `nama`, `babak`, `is_aktif`, `created_at`, `updated_at`) VALUES
	(15, 2, 'Grup C', 1, 0, '2026-07-05 16:03:10', '2026-07-05 16:04:44');
INSERT INTO `grup` (`id`, `id_turnamen`, `nama`, `babak`, `is_aktif`, `created_at`, `updated_at`) VALUES
	(16, 2, 'Grup D', 1, 0, '2026-07-05 16:03:10', '2026-07-05 16:04:44');
INSERT INTO `grup` (`id`, `id_turnamen`, `nama`, `babak`, `is_aktif`, `created_at`, `updated_at`) VALUES
	(17, 2, 'Grup A', 1, 0, '2026-07-05 16:04:44', '2026-07-05 16:05:37');
INSERT INTO `grup` (`id`, `id_turnamen`, `nama`, `babak`, `is_aktif`, `created_at`, `updated_at`) VALUES
	(18, 2, 'Grup B', 1, 0, '2026-07-05 16:04:44', '2026-07-05 16:05:37');
INSERT INTO `grup` (`id`, `id_turnamen`, `nama`, `babak`, `is_aktif`, `created_at`, `updated_at`) VALUES
	(19, 2, 'Grup C', 1, 0, '2026-07-05 16:04:44', '2026-07-05 16:05:37');
INSERT INTO `grup` (`id`, `id_turnamen`, `nama`, `babak`, `is_aktif`, `created_at`, `updated_at`) VALUES
	(20, 2, 'Grup D', 1, 0, '2026-07-05 16:04:44', '2026-07-05 16:05:37');
INSERT INTO `grup` (`id`, `id_turnamen`, `nama`, `babak`, `is_aktif`, `created_at`, `updated_at`) VALUES
	(21, 2, 'Grup A', 2, 1, '2026-07-05 16:05:37', '2026-07-05 16:05:37');
INSERT INTO `grup` (`id`, `id_turnamen`, `nama`, `babak`, `is_aktif`, `created_at`, `updated_at`) VALUES
	(22, 2, 'Grup B', 2, 1, '2026-07-05 16:05:37', '2026-07-05 16:05:37');
/*!40000 ALTER TABLE `grup` ENABLE KEYS */;

-- Dumping structure for table dbbornpadel.grup_member
CREATE TABLE IF NOT EXISTS `grup_member` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_grup` bigint(20) unsigned NOT NULL,
  `id_pemain` bigint(20) unsigned NOT NULL,
  `id_turnamen_peserta` bigint(20) unsigned DEFAULT NULL,
  `poin_didapat` int(11) NOT NULL DEFAULT '0',
  `poin_akumulasi` int(11) NOT NULL DEFAULT '0',
  `set_menang` int(10) unsigned NOT NULL DEFAULT '0',
  `games_menang` int(10) unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `grup_member_id_grup_id_pemain_unique` (`id_grup`,`id_pemain`) USING BTREE,
  KEY `grup_member_id_pemain_foreign` (`id_pemain`) USING BTREE,
  KEY `grup_member_id_turnamen_peserta_foreign` (`id_turnamen_peserta`),
  CONSTRAINT `grup_member_id_grup_foreign` FOREIGN KEY (`id_grup`) REFERENCES `grup` (`id`) ON DELETE CASCADE,
  CONSTRAINT `grup_member_id_pemain_foreign` FOREIGN KEY (`id_pemain`) REFERENCES `m_pemain` (`id`) ON DELETE CASCADE,
  CONSTRAINT `grup_member_id_turnamen_peserta_foreign` FOREIGN KEY (`id_turnamen_peserta`) REFERENCES `turnamen_peserta` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=89 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Dumping data for table dbbornpadel.grup_member: ~23 rows (approximately)
/*!40000 ALTER TABLE `grup_member` DISABLE KEYS */;
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(1, 1, 29, 15, 0, 0, 0, 0, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(2, 1, 25, 13, 0, 0, 0, 0, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(3, 1, 9, 5, 0, 0, 0, 0, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(4, 1, 33, 17, 0, 0, 0, 0, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(5, 2, 31, 16, 0, 0, 0, 0, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(6, 2, 35, 18, 0, 0, 0, 0, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(7, 2, 7, 4, 0, 0, 0, 0, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(8, 2, 27, 14, 0, 0, 0, 0, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(9, 3, 13, 7, 0, 0, 0, 0, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(10, 3, 23, 12, 0, 0, 0, 0, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(11, 3, 37, 19, 0, 0, 0, 0, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(12, 3, 17, 9, 0, 0, 0, 0, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(13, 4, 5, 3, 0, 0, 0, 0, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(14, 4, 15, 8, 0, 0, 0, 0, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(15, 4, 11, 6, 0, 0, 0, 0, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(16, 4, 3, 2, 0, 0, 0, 0, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(17, 5, 49, 29, 20, 0, 0, 0, '2026-07-05 15:47:26', '2026-07-05 15:48:19');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(18, 5, 53, 33, 10, 0, 0, 0, '2026-07-05 15:47:26', '2026-07-05 15:48:22');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(19, 5, 48, 28, -30, 0, 0, 0, '2026-07-05 15:47:26', '2026-07-05 15:48:28');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(20, 5, 52, 32, 0, 0, 0, 0, '2026-07-05 15:47:26', '2026-07-05 15:47:26');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(21, 6, 47, 27, 30, 0, 0, 0, '2026-07-05 15:47:26', '2026-07-05 15:48:36');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(22, 6, 58, 38, 40, 0, 0, 0, '2026-07-05 15:47:26', '2026-07-05 15:48:40');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(23, 6, 43, 23, -50, 0, 0, 0, '2026-07-05 15:47:26', '2026-07-05 15:48:45');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(24, 6, 59, 39, -20, 0, 0, 0, '2026-07-05 15:47:26', '2026-07-05 15:48:47');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(25, 7, 57, 37, 30, 0, 0, 0, '2026-07-05 15:47:26', '2026-07-05 15:49:16');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(26, 7, 56, 36, 10, 0, 0, 0, '2026-07-05 15:47:26', '2026-07-05 15:49:18');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(27, 7, 46, 26, -20, 0, 0, 0, '2026-07-05 15:47:26', '2026-07-05 15:49:21');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(28, 7, 45, 25, -20, 0, 0, 0, '2026-07-05 15:47:26', '2026-07-05 15:49:23');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(29, 8, 44, 24, 40, 0, 0, 0, '2026-07-05 15:47:26', '2026-07-05 15:49:27');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(30, 8, 54, 34, -10, 0, 0, 0, '2026-07-05 15:47:26', '2026-07-05 15:49:29');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(31, 8, 55, 35, -10, 0, 0, 0, '2026-07-05 15:47:26', '2026-07-05 15:49:32');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(32, 8, 42, 22, -20, 0, 0, 0, '2026-07-05 15:47:26', '2026-07-05 15:49:34');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(33, 9, 59, 39, 0, 20, 0, 0, '2026-07-05 15:49:56', '2026-07-05 16:03:10');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(34, 9, 42, 22, 0, 10, 0, 0, '2026-07-05 15:49:56', '2026-07-05 16:03:10');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(35, 9, 46, 26, 0, -30, 0, 0, '2026-07-05 15:49:56', '2026-07-05 16:03:10');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(36, 9, 49, 29, 0, 0, 0, 0, '2026-07-05 15:49:56', '2026-07-05 15:49:56');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(37, 10, 55, 35, 0, 30, 0, 0, '2026-07-05 15:49:56', '2026-07-05 16:03:10');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(38, 10, 43, 23, 0, 40, 0, 0, '2026-07-05 15:49:56', '2026-07-05 16:03:10');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(39, 10, 54, 34, 0, -50, 0, 0, '2026-07-05 15:49:56', '2026-07-05 16:03:10');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(40, 10, 56, 36, 0, -20, 0, 0, '2026-07-05 15:49:56', '2026-07-05 16:03:10');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(41, 11, 58, 38, 0, 30, 0, 0, '2026-07-05 15:49:56', '2026-07-05 16:03:10');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(42, 11, 52, 32, 0, 10, 0, 0, '2026-07-05 15:49:56', '2026-07-05 16:03:10');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(43, 11, 48, 28, 0, -20, 0, 0, '2026-07-05 15:49:56', '2026-07-05 16:03:10');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(44, 11, 45, 25, 0, -20, 0, 0, '2026-07-05 15:49:56', '2026-07-05 16:03:10');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(45, 12, 47, 27, 0, 40, 0, 0, '2026-07-05 15:49:56', '2026-07-05 16:03:10');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(46, 12, 57, 37, 0, -10, 0, 0, '2026-07-05 15:49:56', '2026-07-05 16:03:10');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(47, 12, 53, 33, 0, -10, 0, 0, '2026-07-05 15:49:56', '2026-07-05 16:03:10');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(48, 12, 44, 24, 0, -20, 0, 0, '2026-07-05 15:49:56', '2026-07-05 16:03:10');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(49, 13, 49, 29, 0, 40, 0, 0, '2026-07-05 16:03:10', '2026-07-05 16:04:44');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(50, 13, 52, 32, 0, 0, 0, 0, '2026-07-05 16:03:10', '2026-07-05 16:04:44');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(51, 13, 48, 28, 0, -60, 0, 0, '2026-07-05 16:03:10', '2026-07-05 16:04:44');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(52, 13, 44, 24, 0, -10, 0, 0, '2026-07-05 16:03:10', '2026-07-05 16:04:44');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(53, 14, 54, 34, 0, -10, 0, 0, '2026-07-05 16:03:10', '2026-07-05 16:04:44');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(54, 14, 43, 23, 0, 30, 0, 0, '2026-07-05 16:03:10', '2026-07-05 16:04:44');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(55, 14, 57, 37, 0, -40, 0, 0, '2026-07-05 16:03:10', '2026-07-05 16:04:44');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(56, 14, 53, 33, 0, -10, 0, 0, '2026-07-05 16:03:10', '2026-07-05 16:03:10');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(57, 15, 46, 26, 0, -20, 0, 0, '2026-07-05 16:03:10', '2026-07-05 16:04:44');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(58, 15, 45, 25, 0, -10, 0, 0, '2026-07-05 16:03:10', '2026-07-05 16:04:44');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(59, 15, 58, 38, 0, 20, 0, 0, '2026-07-05 16:03:10', '2026-07-05 16:04:44');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(60, 15, 59, 39, 0, 10, 0, 0, '2026-07-05 16:03:10', '2026-07-05 16:04:44');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(61, 16, 56, 36, 0, -10, 0, 0, '2026-07-05 16:03:10', '2026-07-05 16:04:44');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(62, 16, 42, 22, 0, 30, 0, 0, '2026-07-05 16:03:10', '2026-07-05 16:04:44');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(63, 16, 47, 27, 0, 15, 0, 0, '2026-07-05 16:03:10', '2026-07-05 16:04:44');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(64, 16, 55, 35, 0, 25, 0, 0, '2026-07-05 16:03:10', '2026-07-05 16:04:44');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(65, 17, 43, 23, 0, 30, 0, 0, '2026-07-05 16:04:44', '2026-07-05 16:04:44');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(66, 17, 55, 35, 0, 25, 0, 0, '2026-07-05 16:04:44', '2026-07-05 16:04:44');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(67, 17, 54, 34, 0, -10, 0, 0, '2026-07-05 16:04:44', '2026-07-05 16:04:44');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(68, 17, 53, 33, 0, -10, 0, 0, '2026-07-05 16:04:44', '2026-07-05 16:04:44');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(69, 18, 57, 37, 0, -40, 0, 0, '2026-07-05 16:04:44', '2026-07-05 16:04:44');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(70, 18, 59, 39, 0, 10, 0, 0, '2026-07-05 16:04:44', '2026-07-05 16:04:44');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(71, 18, 47, 27, 0, 15, 0, 0, '2026-07-05 16:04:44', '2026-07-05 16:04:44');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(72, 18, 44, 24, 0, -10, 0, 0, '2026-07-05 16:04:44', '2026-07-05 16:04:44');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(73, 19, 46, 26, 0, -20, 0, 0, '2026-07-05 16:04:44', '2026-07-05 16:04:44');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(74, 19, 56, 36, 0, -10, 0, 0, '2026-07-05 16:04:44', '2026-07-05 16:04:44');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(75, 19, 52, 32, 0, 0, 0, 0, '2026-07-05 16:04:44', '2026-07-05 16:04:44');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(76, 19, 58, 38, 0, 20, 0, 0, '2026-07-05 16:04:44', '2026-07-05 16:04:44');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(77, 20, 45, 25, 0, -10, 0, 0, '2026-07-05 16:04:44', '2026-07-05 16:04:44');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(78, 20, 42, 22, 0, 30, 0, 0, '2026-07-05 16:04:44', '2026-07-05 16:04:44');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(79, 20, 49, 29, 0, 40, 0, 0, '2026-07-05 16:04:44', '2026-07-05 16:04:44');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(80, 20, 48, 28, 0, -60, 0, 0, '2026-07-05 16:04:44', '2026-07-05 16:04:44');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(81, 21, 49, 29, 0, 0, 0, 0, '2026-07-05 16:05:37', '2026-07-05 16:05:37');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(82, 21, 43, 23, 0, 0, 0, 0, '2026-07-05 16:05:37', '2026-07-05 16:05:37');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(83, 21, 42, 22, 0, 0, 0, 0, '2026-07-05 16:05:37', '2026-07-05 16:05:37');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(84, 21, 55, 35, 0, 0, 0, 0, '2026-07-05 16:05:37', '2026-07-05 16:05:37');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(85, 22, 58, 38, 0, 0, 0, 0, '2026-07-05 16:05:37', '2026-07-05 16:05:37');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(86, 22, 47, 27, 0, 0, 0, 0, '2026-07-05 16:05:37', '2026-07-05 16:05:37');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(87, 22, 59, 39, 0, 0, 0, 0, '2026-07-05 16:05:37', '2026-07-05 16:05:37');
INSERT INTO `grup_member` (`id`, `id_grup`, `id_pemain`, `id_turnamen_peserta`, `poin_didapat`, `poin_akumulasi`, `set_menang`, `games_menang`, `created_at`, `updated_at`) VALUES
	(88, 22, 52, 32, 0, 0, 0, 0, '2026-07-05 16:05:37', '2026-07-05 16:05:37');
/*!40000 ALTER TABLE `grup_member` ENABLE KEYS */;

-- Dumping structure for table dbbornpadel.migrations
CREATE TABLE IF NOT EXISTS `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Dumping data for table dbbornpadel.migrations: ~29 rows (approximately)
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
	(1, '2014_10_12_000000_create_users_table', 1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
	(2, '2014_10_12_100000_create_password_resets_table', 1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
	(3, '2019_08_19_000000_create_failed_jobs_table', 1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
	(4, '2019_12_14_000001_create_personal_access_tokens_table', 1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
	(5, '2024_01_01_000001_create_turnamen_table', 1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
	(6, '2024_01_01_000002_create_pemain_table', 1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
	(7, '2024_01_01_000003_create_grup_table', 1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
	(8, '2024_01_01_000004_create_grup_member_table', 1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
	(9, '2024_01_01_000005_create_pertandingan_table', 1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
	(10, '2024_01_01_000006_create_pertandingan_skor_table', 1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
	(11, '2024_01_02_000001_add_username_to_users_table', 2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
	(12, '2024_01_03_000001_make_pertandingan_pemain_nullable', 3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
	(13, '2024_01_04_000001_create_turnamen_peserta_table', 4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
	(14, '2024_01_05_000001_migrate_pemain_foto_to_public', 5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
	(15, '2024_01_06_000001_add_role_and_id_turnamen_to_users_table', 6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
	(16, '2024_01_06_000002_make_email_nullable_on_users_table', 7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
	(17, '2024_01_07_000001_rename_master_tables', 8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
	(18, '2024_01_08_000001_add_jenis_to_m_turnamen_table', 9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
	(19, '2024_01_09_000001_rename_pemain_columns_on_turnamen_peserta_table', 10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
	(20, '2024_01_10_000001_add_peserta_columns_for_double_competition', 11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
	(21, '2024_01_12_000001_make_id_pemain1_nullable_on_turnamen_peserta_table', 12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
	(22, '2024_01_13_000001_add_bukti_bayar_and_expand_status_on_turnamen_peserta', 13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
	(23, '2024_01_13_000002_add_total_poin_to_m_pemain_table', 14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
	(24, '2024_01_13_000003_add_babak_16_to_pertandingan_nama_ronde', 15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
	(25, '2024_01_14_000001_add_tanggal_to_m_turnamen_table', 16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
	(26, '2024_01_11_000001_make_tgl_lahir_nullable_on_m_pemain_table', 17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
	(27, '2024_01_15_000001_add_mahjong_jenis_to_m_turnamen_table', 18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
	(28, '2024_01_15_000002_add_mahjong_columns_to_grup_table', 18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
	(29, '2024_01_15_000003_add_poin_akumulasi_to_grup_member_table', 18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
	(30, '2024_01_15_000004_create_turnamen_pemenang_table', 18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
	(31, '2024_01_16_000001_allow_negative_mahjong_points_on_grup_member_table', 19);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;

-- Dumping structure for table dbbornpadel.m_pemain
CREATE TABLE IF NOT EXISTS `m_pemain` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tgl_lahir` date DEFAULT NULL,
  `usia` tinyint(3) unsigned DEFAULT NULL,
  `gender` enum('male','female') COLLATE utf8mb4_unicode_ci NOT NULL,
  `no_hp` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rating` decimal(5,2) NOT NULL DEFAULT '0.00',
  `total_poin` int(10) unsigned NOT NULL DEFAULT '0',
  `foto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Dumping data for table dbbornpadel.m_pemain: ~14 rows (approximately)
/*!40000 ALTER TABLE `m_pemain` DISABLE KEYS */;
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(1, 'Double Pasangan 1 — Pemain 1', '1992-09-18', 33, 'male', '082100000001', 3.50, 0, NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(2, 'Double Pasangan 1 — Pemain 2', '1989-07-10', 36, 'female', '082100000002', 3.80, 0, NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(3, 'Double Pasangan 2 — Pemain 1', '1995-08-20', 30, 'female', '082100000003', 4.20, 0, NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(4, 'Double Pasangan 2 — Pemain 2', '1989-02-14', 37, 'male', '082100000004', 4.50, 0, NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(5, 'Double Pasangan 3 — Pemain 1', '2001-06-27', 25, 'male', '082100000005', 4.90, 0, NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(6, 'Double Pasangan 3 — Pemain 2', '1988-04-19', 38, 'female', '082100000006', 2.60, 0, NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(7, 'Double Pasangan 4 — Pemain 1', '2000-10-10', 25, 'female', '082100000007', 3.00, 0, NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(8, 'Double Pasangan 4 — Pemain 2', '1988-12-27', 37, 'male', '082100000008', 3.30, 0, NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(9, 'Double Pasangan 5 — Pemain 1', '1991-08-08', 34, 'male', '082100000009', 3.70, 0, NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(10, 'Double Pasangan 5 — Pemain 2', '1990-02-05', 36, 'female', '082100000010', 4.00, 0, NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(11, 'Double Pasangan 6 — Pemain 1', '1989-01-25', 37, 'female', '082100000011', 4.40, 0, NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(12, 'Double Pasangan 6 — Pemain 2', '1999-11-20', 26, 'male', '082100000012', 4.70, 0, NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(13, 'Double Pasangan 7 — Pemain 1', '1990-11-17', 35, 'male', '082100000013', 2.50, 0, NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(14, 'Double Pasangan 7 — Pemain 2', '2002-09-03', 23, 'female', '082100000014', 2.80, 0, NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(15, 'Double Pasangan 8 — Pemain 1', '1991-05-11', 35, 'female', '082100000015', 3.20, 0, NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(16, 'Double Pasangan 8 — Pemain 2', '2001-03-03', 25, 'male', '082100000016', 3.50, 0, NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(17, 'Double Pasangan 9 — Pemain 1', '1990-07-25', 35, 'male', '082100000017', 3.90, 0, NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(18, 'Double Pasangan 9 — Pemain 2', '1993-05-20', 33, 'female', '082100000018', 4.20, 0, NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(19, 'Double Pasangan 10 — Pemain 1', '1999-07-07', 26, 'female', '082100000019', 4.60, 0, NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(20, 'Double Pasangan 10 — Pemain 2', '1995-09-12', 30, 'male', '082100000020', 4.90, 0, NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(21, 'Double Pasangan 11 — Pemain 1', '1988-05-26', 38, 'male', '082100000021', 2.70, 0, NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(22, 'Double Pasangan 11 — Pemain 2', '1997-11-08', 28, 'female', '082100000022', 3.00, 0, NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(23, 'Double Pasangan 12 — Pemain 1', '1999-04-10', 27, 'female', '082100000023', 3.40, 0, NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(24, 'Double Pasangan 12 — Pemain 2', '1990-10-01', 35, 'male', '082100000024', 3.70, 0, NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(25, 'Double Pasangan 13 — Pemain 1', '1990-06-09', 36, 'male', '082100000025', 4.10, 0, NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(26, 'Double Pasangan 13 — Pemain 2', '1990-12-09', 35, 'female', '082100000026', 4.40, 0, NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(27, 'Double Pasangan 14 — Pemain 1', '1989-06-21', 37, 'female', '082100000027', 4.80, 0, NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(28, 'Double Pasangan 14 — Pemain 2', '1988-04-10', 38, 'male', '082100000028', 2.50, 0, NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(29, 'Double Pasangan 15 — Pemain 1', '1993-04-13', 33, 'male', '082100000029', 2.90, 0, NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(30, 'Double Pasangan 15 — Pemain 2', '1992-10-16', 33, 'female', '082100000030', 3.20, 0, NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(31, 'Double Pasangan 16 — Pemain 1', '1989-05-17', 37, 'female', '082100000031', 3.60, 0, NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(32, 'Double Pasangan 16 — Pemain 2', '1992-03-09', 34, 'male', '082100000032', 3.90, 0, NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(33, 'Double Pasangan 17 — Pemain 1', '1998-07-01', 28, 'male', '082100000033', 4.30, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(34, 'Double Pasangan 17 — Pemain 2', '1988-01-22', 38, 'female', '082100000034', 4.60, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(35, 'Double Pasangan 18 — Pemain 1', '1999-09-03', 26, 'female', '082100000035', 5.00, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(36, 'Double Pasangan 18 — Pemain 2', '1988-07-10', 37, 'male', '082100000036', 2.70, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(37, 'Double Pasangan 19 — Pemain 1', '1998-11-05', 27, 'male', '082100000037', 3.10, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(38, 'Double Pasangan 19 — Pemain 2', '1996-01-07', 30, 'female', '082100000038', 3.40, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(39, 'Double Pasangan 20 — Pemain 1', '1994-08-26', 31, 'female', '082100000039', 3.80, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(40, 'Double Pasangan 20 — Pemain 2', '1998-06-24', 28, 'male', '082100000040', 4.10, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(41, 'Mahjong Pemain 01', '1989-04-13', 37, 'male', '082200000001', 3.20, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(42, 'Mahjong Pemain 02', '2000-06-24', 26, 'female', '082200000002', 3.90, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(43, 'Mahjong Pemain 03', '2000-12-21', 25, 'male', '082200000003', 4.60, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(44, 'Mahjong Pemain 04', '1994-01-10', 32, 'female', '082200000004', 2.70, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(45, 'Mahjong Pemain 05', '1992-07-04', 34, 'male', '082200000005', 3.40, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(46, 'Mahjong Pemain 06', '1988-05-17', 38, 'female', '082200000006', 4.10, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(47, 'Mahjong Pemain 07', '2000-11-02', 25, 'male', '082200000007', 4.80, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(48, 'Mahjong Pemain 08', '1994-08-08', 31, 'female', '082200000008', 2.90, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(49, 'Mahjong Pemain 09', '2001-06-06', 25, 'male', '082200000009', 3.60, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(50, 'Mahjong Pemain 10', '1996-01-25', 30, 'female', '082200000010', 4.30, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(51, 'Mahjong Pemain 11', '1996-03-27', 30, 'male', '082200000011', 5.00, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(52, 'Mahjong Pemain 12', '1990-09-27', 35, 'female', '082200000012', 3.10, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(53, 'Mahjong Pemain 13', '1997-11-11', 28, 'male', '082200000013', 3.80, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(54, 'Mahjong Pemain 14', '1992-02-14', 34, 'female', '082200000014', 4.50, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(55, 'Mahjong Pemain 15', '1991-12-18', 34, 'male', '082200000015', 2.60, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(56, 'Mahjong Pemain 16', '1990-06-24', 36, 'female', '082200000016', 3.30, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(57, 'Mahjong Pemain 17', '2002-08-11', 23, 'male', '082200000017', 4.00, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(58, 'Mahjong Pemain 18', '1997-07-10', 28, 'female', '082200000018', 4.70, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(59, 'Mahjong Pemain 19', '1993-09-06', 32, 'male', '082200000019', 2.80, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(60, 'Mahjong Pemain 20', '1997-08-08', 28, 'female', '082200000020', 3.50, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(61, 'Single Pemain 01', '1991-02-23', 35, 'male', '082300000001', 3.20, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(62, 'Single Pemain 02', '1992-08-02', 33, 'female', '082300000002', 3.90, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(63, 'Single Pemain 03', '1995-06-12', 31, 'male', '082300000003', 4.60, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(64, 'Single Pemain 04', '1998-03-21', 28, 'female', '082300000004', 2.70, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(65, 'Single Pemain 05', '2000-09-21', 25, 'male', '082300000005', 3.40, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(66, 'Single Pemain 06', '1991-11-23', 34, 'female', '082300000006', 4.10, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(67, 'Single Pemain 07', '1992-01-19', 34, 'male', '082300000007', 4.80, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(68, 'Single Pemain 08', '1989-06-15', 37, 'female', '082300000008', 2.90, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(69, 'Single Pemain 09', '1996-04-25', 30, 'male', '082300000009', 3.60, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(70, 'Single Pemain 10', '1995-11-20', 30, 'female', '082300000010', 4.30, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(71, 'Single Pemain 11', '1991-05-20', 35, 'male', '082300000011', 5.00, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(72, 'Single Pemain 12', '1988-11-11', 37, 'female', '082300000012', 3.10, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(73, 'Single Pemain 13', '1991-05-20', 35, 'male', '082300000013', 3.80, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(74, 'Single Pemain 14', '1999-04-13', 27, 'female', '082300000014', 4.50, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(75, 'Single Pemain 15', '1993-02-17', 33, 'male', '082300000015', 2.60, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(76, 'Single Pemain 16', '1990-04-10', 36, 'female', '082300000016', 3.30, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(77, 'Single Pemain 17', '1998-06-18', 28, 'male', '082300000017', 4.00, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(78, 'Single Pemain 18', '1998-05-23', 28, 'female', '082300000018', 4.70, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(79, 'Single Pemain 19', '1994-03-21', 32, 'male', '082300000019', 2.80, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `m_pemain` (`id`, `nama`, `tgl_lahir`, `usia`, `gender`, `no_hp`, `rating`, `total_poin`, `foto`, `created_at`, `updated_at`) VALUES
	(80, 'Single Pemain 20', '1999-10-16', 26, 'female', '082300000020', 3.50, 0, NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
/*!40000 ALTER TABLE `m_pemain` ENABLE KEYS */;

-- Dumping structure for table dbbornpadel.m_turnamen
CREATE TABLE IF NOT EXISTS `m_turnamen` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tanggal` date DEFAULT NULL,
  `harga` decimal(12,2) NOT NULL DEFAULT '0.00',
  `syarat` text COLLATE utf8mb4_unicode_ci,
  `jenis` enum('single','double','mahjong') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'single',
  `status` enum('draft','open','ongoing','completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `mahjong_is_final` tinyint(1) NOT NULL DEFAULT '0',
  `doc` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `dom` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Dumping data for table dbbornpadel.m_turnamen: ~3 rows (approximately)
/*!40000 ALTER TABLE `m_turnamen` DISABLE KEYS */;
INSERT INTO `m_turnamen` (`id`, `nama`, `tanggal`, `harga`, `syarat`, `jenis`, `status`, `mahjong_is_final`, `doc`, `dom`) VALUES
	(1, 'Born Padel Double Open 2026', '2026-06-15', 300000.00, 'Terbuka untuk pasangan padel. Minimal rating 3.0 per pemain.', 'double', 'ongoing', 0, '2026-07-05 15:40:20', '2026-07-05 15:46:07');
INSERT INTO `m_turnamen` (`id`, `nama`, `tanggal`, `harga`, `syarat`, `jenis`, `status`, `mahjong_is_final`, `doc`, `dom`) VALUES
	(2, 'Born Mahjong Championship 2026', '2026-07-10', 200000.00, 'Turnamen Mahjong poin akumulasi. Pemain terdaftar akan dibagi grup 4.', 'mahjong', 'ongoing', 0, '2026-07-05 15:40:20', '2026-07-05 15:47:11');
INSERT INTO `m_turnamen` (`id`, `nama`, `tanggal`, `harga`, `syarat`, `jenis`, `status`, `mahjong_is_final`, `doc`, `dom`) VALUES
	(3, 'Born Padel Singles Cup 2026', '2026-08-05', 175000.00, 'Turnamen single padel terbuka untuk semua level.', 'single', 'open', 0, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
/*!40000 ALTER TABLE `m_turnamen` ENABLE KEYS */;

-- Dumping structure for table dbbornpadel.m_users
CREATE TABLE IF NOT EXISTS `m_users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','panitia') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'panitia',
  `id_turnamen` bigint(20) unsigned DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `users_username_unique` (`username`) USING BTREE,
  UNIQUE KEY `users_email_unique` (`email`) USING BTREE,
  KEY `users_id_turnamen_foreign` (`id_turnamen`),
  CONSTRAINT `users_id_turnamen_foreign` FOREIGN KEY (`id_turnamen`) REFERENCES `m_turnamen` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Dumping data for table dbbornpadel.m_users: ~1 rows (approximately)
/*!40000 ALTER TABLE `m_users` DISABLE KEYS */;
INSERT INTO `m_users` (`id`, `name`, `username`, `email`, `email_verified_at`, `password`, `role`, `id_turnamen`, `remember_token`, `created_at`, `updated_at`) VALUES
	(1, 'Admin Born Padel', 'admin', 'admin@bornpadel.com', NULL, '$2y$10$VWgXQ6jl8b/FgaXlOpe8ietdH5FGH4bE2f5CCsuDmhd5OrrwRavta', 'admin', NULL, 'BxVuv5t8Ij08ndywhYyA6pdsJcbGEY70EjfqCDutXlJfHQO5E7VZ84wWAJBv', '2026-06-10 09:33:04', '2026-06-17 14:28:50');
INSERT INTO `m_users` (`id`, `name`, `username`, `email`, `email_verified_at`, `password`, `role`, `id_turnamen`, `remember_token`, `created_at`, `updated_at`) VALUES
	(2, 'Panitia Born Padel', 'panit_bornpadel', NULL, NULL, '$2y$10$5tMIMPKtFSCfezYlOrn2L.4xXzCea4dcs2.0mq6cTxbGmdSNywATe', 'panitia', 3, NULL, '2026-06-17 14:50:50', '2026-06-18 16:16:25');
/*!40000 ALTER TABLE `m_users` ENABLE KEYS */;

-- Dumping structure for table dbbornpadel.password_resets
CREATE TABLE IF NOT EXISTS `password_resets` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  KEY `password_resets_email_index` (`email`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Dumping data for table dbbornpadel.password_resets: ~0 rows (approximately)
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;

-- Dumping structure for table dbbornpadel.personal_access_tokens
CREATE TABLE IF NOT EXISTS `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`) USING BTREE,
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Dumping data for table dbbornpadel.personal_access_tokens: ~0 rows (approximately)
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;

-- Dumping structure for table dbbornpadel.pertandingan
CREATE TABLE IF NOT EXISTS `pertandingan` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_turnamen` bigint(20) unsigned NOT NULL,
  `id_grup` bigint(20) unsigned DEFAULT NULL,
  `nama_ronde` enum('Fase Grup','Babak 16 Besar','Perempatfinal','Semifinal','Final') COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_pemain1` bigint(20) unsigned DEFAULT NULL,
  `id_pemain2` bigint(20) unsigned DEFAULT NULL,
  `id_peserta1` bigint(20) unsigned DEFAULT NULL,
  `id_peserta2` bigint(20) unsigned DEFAULT NULL,
  `id_pemenang` bigint(20) unsigned DEFAULT NULL,
  `id_peserta_pemenang` bigint(20) unsigned DEFAULT NULL,
  `status` enum('scheduled','ongoing','completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'scheduled',
  `id_next_pertandingan` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `pertandingan_id_turnamen_foreign` (`id_turnamen`) USING BTREE,
  KEY `pertandingan_id_grup_foreign` (`id_grup`) USING BTREE,
  KEY `pertandingan_id_pemain1_foreign` (`id_pemain1`) USING BTREE,
  KEY `pertandingan_id_pemain2_foreign` (`id_pemain2`) USING BTREE,
  KEY `pertandingan_id_pemenang_foreign` (`id_pemenang`) USING BTREE,
  KEY `pertandingan_id_next_pertandingan_foreign` (`id_next_pertandingan`) USING BTREE,
  KEY `pertandingan_id_peserta1_foreign` (`id_peserta1`),
  KEY `pertandingan_id_peserta2_foreign` (`id_peserta2`),
  KEY `pertandingan_id_peserta_pemenang_foreign` (`id_peserta_pemenang`),
  CONSTRAINT `pertandingan_id_grup_foreign` FOREIGN KEY (`id_grup`) REFERENCES `grup` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pertandingan_id_next_pertandingan_foreign` FOREIGN KEY (`id_next_pertandingan`) REFERENCES `pertandingan` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pertandingan_id_pemenang_foreign` FOREIGN KEY (`id_pemenang`) REFERENCES `m_pemain` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pertandingan_id_peserta1_foreign` FOREIGN KEY (`id_peserta1`) REFERENCES `turnamen_peserta` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pertandingan_id_peserta2_foreign` FOREIGN KEY (`id_peserta2`) REFERENCES `turnamen_peserta` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pertandingan_id_peserta_pemenang_foreign` FOREIGN KEY (`id_peserta_pemenang`) REFERENCES `turnamen_peserta` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pertandingan_id_turnamen_foreign` FOREIGN KEY (`id_turnamen`) REFERENCES `m_turnamen` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Dumping data for table dbbornpadel.pertandingan: ~4 rows (approximately)
/*!40000 ALTER TABLE `pertandingan` DISABLE KEYS */;
INSERT INTO `pertandingan` (`id`, `id_turnamen`, `id_grup`, `nama_ronde`, `id_pemain1`, `id_pemain2`, `id_peserta1`, `id_peserta2`, `id_pemenang`, `id_peserta_pemenang`, `status`, `id_next_pertandingan`, `created_at`, `updated_at`) VALUES
	(1, 1, 1, 'Fase Grup', 29, 25, 15, 13, NULL, NULL, 'scheduled', NULL, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
INSERT INTO `pertandingan` (`id`, `id_turnamen`, `id_grup`, `nama_ronde`, `id_pemain1`, `id_pemain2`, `id_peserta1`, `id_peserta2`, `id_pemenang`, `id_peserta_pemenang`, `status`, `id_next_pertandingan`, `created_at`, `updated_at`) VALUES
	(2, 1, 1, 'Fase Grup', 29, 9, 15, 5, NULL, NULL, 'scheduled', NULL, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
INSERT INTO `pertandingan` (`id`, `id_turnamen`, `id_grup`, `nama_ronde`, `id_pemain1`, `id_pemain2`, `id_peserta1`, `id_peserta2`, `id_pemenang`, `id_peserta_pemenang`, `status`, `id_next_pertandingan`, `created_at`, `updated_at`) VALUES
	(3, 1, 1, 'Fase Grup', 29, 33, 15, 17, NULL, NULL, 'scheduled', NULL, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
INSERT INTO `pertandingan` (`id`, `id_turnamen`, `id_grup`, `nama_ronde`, `id_pemain1`, `id_pemain2`, `id_peserta1`, `id_peserta2`, `id_pemenang`, `id_peserta_pemenang`, `status`, `id_next_pertandingan`, `created_at`, `updated_at`) VALUES
	(4, 1, 1, 'Fase Grup', 25, 9, 13, 5, NULL, NULL, 'scheduled', NULL, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
INSERT INTO `pertandingan` (`id`, `id_turnamen`, `id_grup`, `nama_ronde`, `id_pemain1`, `id_pemain2`, `id_peserta1`, `id_peserta2`, `id_pemenang`, `id_peserta_pemenang`, `status`, `id_next_pertandingan`, `created_at`, `updated_at`) VALUES
	(5, 1, 1, 'Fase Grup', 25, 33, 13, 17, NULL, NULL, 'scheduled', NULL, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
INSERT INTO `pertandingan` (`id`, `id_turnamen`, `id_grup`, `nama_ronde`, `id_pemain1`, `id_pemain2`, `id_peserta1`, `id_peserta2`, `id_pemenang`, `id_peserta_pemenang`, `status`, `id_next_pertandingan`, `created_at`, `updated_at`) VALUES
	(6, 1, 1, 'Fase Grup', 9, 33, 5, 17, NULL, NULL, 'scheduled', NULL, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
INSERT INTO `pertandingan` (`id`, `id_turnamen`, `id_grup`, `nama_ronde`, `id_pemain1`, `id_pemain2`, `id_peserta1`, `id_peserta2`, `id_pemenang`, `id_peserta_pemenang`, `status`, `id_next_pertandingan`, `created_at`, `updated_at`) VALUES
	(7, 1, 2, 'Fase Grup', 31, 35, 16, 18, NULL, NULL, 'scheduled', NULL, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
INSERT INTO `pertandingan` (`id`, `id_turnamen`, `id_grup`, `nama_ronde`, `id_pemain1`, `id_pemain2`, `id_peserta1`, `id_peserta2`, `id_pemenang`, `id_peserta_pemenang`, `status`, `id_next_pertandingan`, `created_at`, `updated_at`) VALUES
	(8, 1, 2, 'Fase Grup', 31, 7, 16, 4, NULL, NULL, 'scheduled', NULL, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
INSERT INTO `pertandingan` (`id`, `id_turnamen`, `id_grup`, `nama_ronde`, `id_pemain1`, `id_pemain2`, `id_peserta1`, `id_peserta2`, `id_pemenang`, `id_peserta_pemenang`, `status`, `id_next_pertandingan`, `created_at`, `updated_at`) VALUES
	(9, 1, 2, 'Fase Grup', 31, 27, 16, 14, NULL, NULL, 'scheduled', NULL, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
INSERT INTO `pertandingan` (`id`, `id_turnamen`, `id_grup`, `nama_ronde`, `id_pemain1`, `id_pemain2`, `id_peserta1`, `id_peserta2`, `id_pemenang`, `id_peserta_pemenang`, `status`, `id_next_pertandingan`, `created_at`, `updated_at`) VALUES
	(10, 1, 2, 'Fase Grup', 35, 7, 18, 4, NULL, NULL, 'scheduled', NULL, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
INSERT INTO `pertandingan` (`id`, `id_turnamen`, `id_grup`, `nama_ronde`, `id_pemain1`, `id_pemain2`, `id_peserta1`, `id_peserta2`, `id_pemenang`, `id_peserta_pemenang`, `status`, `id_next_pertandingan`, `created_at`, `updated_at`) VALUES
	(11, 1, 2, 'Fase Grup', 35, 27, 18, 14, NULL, NULL, 'scheduled', NULL, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
INSERT INTO `pertandingan` (`id`, `id_turnamen`, `id_grup`, `nama_ronde`, `id_pemain1`, `id_pemain2`, `id_peserta1`, `id_peserta2`, `id_pemenang`, `id_peserta_pemenang`, `status`, `id_next_pertandingan`, `created_at`, `updated_at`) VALUES
	(12, 1, 2, 'Fase Grup', 7, 27, 4, 14, NULL, NULL, 'scheduled', NULL, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
INSERT INTO `pertandingan` (`id`, `id_turnamen`, `id_grup`, `nama_ronde`, `id_pemain1`, `id_pemain2`, `id_peserta1`, `id_peserta2`, `id_pemenang`, `id_peserta_pemenang`, `status`, `id_next_pertandingan`, `created_at`, `updated_at`) VALUES
	(13, 1, 3, 'Fase Grup', 13, 23, 7, 12, NULL, NULL, 'scheduled', NULL, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
INSERT INTO `pertandingan` (`id`, `id_turnamen`, `id_grup`, `nama_ronde`, `id_pemain1`, `id_pemain2`, `id_peserta1`, `id_peserta2`, `id_pemenang`, `id_peserta_pemenang`, `status`, `id_next_pertandingan`, `created_at`, `updated_at`) VALUES
	(14, 1, 3, 'Fase Grup', 13, 37, 7, 19, NULL, NULL, 'scheduled', NULL, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
INSERT INTO `pertandingan` (`id`, `id_turnamen`, `id_grup`, `nama_ronde`, `id_pemain1`, `id_pemain2`, `id_peserta1`, `id_peserta2`, `id_pemenang`, `id_peserta_pemenang`, `status`, `id_next_pertandingan`, `created_at`, `updated_at`) VALUES
	(15, 1, 3, 'Fase Grup', 13, 17, 7, 9, NULL, NULL, 'scheduled', NULL, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
INSERT INTO `pertandingan` (`id`, `id_turnamen`, `id_grup`, `nama_ronde`, `id_pemain1`, `id_pemain2`, `id_peserta1`, `id_peserta2`, `id_pemenang`, `id_peserta_pemenang`, `status`, `id_next_pertandingan`, `created_at`, `updated_at`) VALUES
	(16, 1, 3, 'Fase Grup', 23, 37, 12, 19, NULL, NULL, 'scheduled', NULL, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
INSERT INTO `pertandingan` (`id`, `id_turnamen`, `id_grup`, `nama_ronde`, `id_pemain1`, `id_pemain2`, `id_peserta1`, `id_peserta2`, `id_pemenang`, `id_peserta_pemenang`, `status`, `id_next_pertandingan`, `created_at`, `updated_at`) VALUES
	(17, 1, 3, 'Fase Grup', 23, 17, 12, 9, NULL, NULL, 'scheduled', NULL, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
INSERT INTO `pertandingan` (`id`, `id_turnamen`, `id_grup`, `nama_ronde`, `id_pemain1`, `id_pemain2`, `id_peserta1`, `id_peserta2`, `id_pemenang`, `id_peserta_pemenang`, `status`, `id_next_pertandingan`, `created_at`, `updated_at`) VALUES
	(18, 1, 3, 'Fase Grup', 37, 17, 19, 9, NULL, NULL, 'scheduled', NULL, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
INSERT INTO `pertandingan` (`id`, `id_turnamen`, `id_grup`, `nama_ronde`, `id_pemain1`, `id_pemain2`, `id_peserta1`, `id_peserta2`, `id_pemenang`, `id_peserta_pemenang`, `status`, `id_next_pertandingan`, `created_at`, `updated_at`) VALUES
	(19, 1, 4, 'Fase Grup', 5, 15, 3, 8, NULL, NULL, 'scheduled', NULL, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
INSERT INTO `pertandingan` (`id`, `id_turnamen`, `id_grup`, `nama_ronde`, `id_pemain1`, `id_pemain2`, `id_peserta1`, `id_peserta2`, `id_pemenang`, `id_peserta_pemenang`, `status`, `id_next_pertandingan`, `created_at`, `updated_at`) VALUES
	(20, 1, 4, 'Fase Grup', 5, 11, 3, 6, NULL, NULL, 'scheduled', NULL, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
INSERT INTO `pertandingan` (`id`, `id_turnamen`, `id_grup`, `nama_ronde`, `id_pemain1`, `id_pemain2`, `id_peserta1`, `id_peserta2`, `id_pemenang`, `id_peserta_pemenang`, `status`, `id_next_pertandingan`, `created_at`, `updated_at`) VALUES
	(21, 1, 4, 'Fase Grup', 5, 3, 3, 2, NULL, NULL, 'scheduled', NULL, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
INSERT INTO `pertandingan` (`id`, `id_turnamen`, `id_grup`, `nama_ronde`, `id_pemain1`, `id_pemain2`, `id_peserta1`, `id_peserta2`, `id_pemenang`, `id_peserta_pemenang`, `status`, `id_next_pertandingan`, `created_at`, `updated_at`) VALUES
	(22, 1, 4, 'Fase Grup', 15, 11, 8, 6, NULL, NULL, 'scheduled', NULL, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
INSERT INTO `pertandingan` (`id`, `id_turnamen`, `id_grup`, `nama_ronde`, `id_pemain1`, `id_pemain2`, `id_peserta1`, `id_peserta2`, `id_pemenang`, `id_peserta_pemenang`, `status`, `id_next_pertandingan`, `created_at`, `updated_at`) VALUES
	(23, 1, 4, 'Fase Grup', 15, 3, 8, 2, NULL, NULL, 'scheduled', NULL, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
INSERT INTO `pertandingan` (`id`, `id_turnamen`, `id_grup`, `nama_ronde`, `id_pemain1`, `id_pemain2`, `id_peserta1`, `id_peserta2`, `id_pemenang`, `id_peserta_pemenang`, `status`, `id_next_pertandingan`, `created_at`, `updated_at`) VALUES
	(24, 1, 4, 'Fase Grup', 11, 3, 6, 2, NULL, NULL, 'scheduled', NULL, '2026-07-05 15:46:15', '2026-07-05 15:46:15');
/*!40000 ALTER TABLE `pertandingan` ENABLE KEYS */;

-- Dumping structure for table dbbornpadel.pertandingan_skor
CREATE TABLE IF NOT EXISTS `pertandingan_skor` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_pertandingan` bigint(20) unsigned NOT NULL,
  `set_ke` tinyint(3) unsigned NOT NULL,
  `skor_pemain1` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `skor_pemain2` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `pertandingan_skor_id_pertandingan_set_ke_unique` (`id_pertandingan`,`set_ke`) USING BTREE,
  CONSTRAINT `pertandingan_skor_id_pertandingan_foreign` FOREIGN KEY (`id_pertandingan`) REFERENCES `pertandingan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Dumping data for table dbbornpadel.pertandingan_skor: ~11 rows (approximately)
/*!40000 ALTER TABLE `pertandingan_skor` DISABLE KEYS */;
/*!40000 ALTER TABLE `pertandingan_skor` ENABLE KEYS */;

-- Dumping structure for table dbbornpadel.turnamen_pemenang
CREATE TABLE IF NOT EXISTS `turnamen_pemenang` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_turnamen` bigint(20) unsigned NOT NULL,
  `peringkat` tinyint(3) unsigned NOT NULL,
  `id_pemain` bigint(20) unsigned NOT NULL,
  `id_turnamen_peserta` bigint(20) unsigned DEFAULT NULL,
  `total_poin` int(10) unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `turnamen_pemenang_id_turnamen_peringkat_unique` (`id_turnamen`,`peringkat`),
  KEY `turnamen_pemenang_id_pemain_foreign` (`id_pemain`),
  KEY `turnamen_pemenang_id_turnamen_peserta_foreign` (`id_turnamen_peserta`),
  CONSTRAINT `turnamen_pemenang_id_pemain_foreign` FOREIGN KEY (`id_pemain`) REFERENCES `m_pemain` (`id`) ON DELETE CASCADE,
  CONSTRAINT `turnamen_pemenang_id_turnamen_foreign` FOREIGN KEY (`id_turnamen`) REFERENCES `m_turnamen` (`id`) ON DELETE CASCADE,
  CONSTRAINT `turnamen_pemenang_id_turnamen_peserta_foreign` FOREIGN KEY (`id_turnamen_peserta`) REFERENCES `turnamen_peserta` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table dbbornpadel.turnamen_pemenang: ~0 rows (approximately)
/*!40000 ALTER TABLE `turnamen_pemenang` DISABLE KEYS */;
/*!40000 ALTER TABLE `turnamen_pemenang` ENABLE KEYS */;

-- Dumping structure for table dbbornpadel.turnamen_peserta
CREATE TABLE IF NOT EXISTS `turnamen_peserta` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_turnamen` bigint(20) unsigned NOT NULL,
  `id_pemain1` bigint(20) unsigned DEFAULT NULL,
  `id_pemain2` bigint(20) unsigned DEFAULT NULL,
  `status` enum('pending','approved','rejected','unpaid','paid') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `bukti_bayar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `turnamen_peserta_id_turnamen_id_pemain1_unique` (`id_turnamen`,`id_pemain1`),
  KEY `turnamen_peserta_id_pemain_foreign` (`id_pemain1`) USING BTREE,
  KEY `turnamen_peserta_id_pemain2_foreign` (`id_pemain2`),
  CONSTRAINT `turnamen_peserta_id_pemain1_foreign` FOREIGN KEY (`id_pemain1`) REFERENCES `m_pemain` (`id`) ON DELETE CASCADE,
  CONSTRAINT `turnamen_peserta_id_pemain2_foreign` FOREIGN KEY (`id_pemain2`) REFERENCES `m_pemain` (`id`) ON DELETE CASCADE,
  CONSTRAINT `turnamen_peserta_id_turnamen_foreign` FOREIGN KEY (`id_turnamen`) REFERENCES `m_turnamen` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Dumping data for table dbbornpadel.turnamen_peserta: ~12 rows (approximately)
/*!40000 ALTER TABLE `turnamen_peserta` DISABLE KEYS */;
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(1, 1, 1, 2, 'pending', NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(2, 1, 3, 4, 'approved', NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(3, 1, 5, 6, 'approved', NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(4, 1, 7, 8, 'approved', NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(5, 1, 9, 10, 'approved', NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(6, 1, 11, 12, 'approved', NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(7, 1, 13, 14, 'approved', NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(8, 1, 15, 16, 'approved', NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(9, 1, 17, 18, 'approved', NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(10, 1, 19, 20, 'paid', NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(11, 1, 21, 22, 'pending', NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(12, 1, 23, 24, 'approved', NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(13, 1, 25, 26, 'approved', NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(14, 1, 27, 28, 'approved', NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(15, 1, 29, 30, 'approved', NULL, '2026-07-05 15:40:20', '2026-07-05 15:40:20');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(16, 1, 31, 32, 'approved', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(17, 1, 33, 34, 'approved', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(18, 1, 35, 36, 'approved', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(19, 1, 37, 38, 'approved', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(20, 1, 39, 40, 'paid', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(21, 2, 41, NULL, 'pending', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(22, 2, 42, NULL, 'approved', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(23, 2, 43, NULL, 'approved', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(24, 2, 44, NULL, 'approved', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(25, 2, 45, NULL, 'approved', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(26, 2, 46, NULL, 'approved', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(27, 2, 47, NULL, 'approved', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(28, 2, 48, NULL, 'approved', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(29, 2, 49, NULL, 'approved', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(30, 2, 50, NULL, 'paid', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(31, 2, 51, NULL, 'pending', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(32, 2, 52, NULL, 'approved', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(33, 2, 53, NULL, 'approved', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(34, 2, 54, NULL, 'approved', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(35, 2, 55, NULL, 'approved', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(36, 2, 56, NULL, 'approved', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(37, 2, 57, NULL, 'approved', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(38, 2, 58, NULL, 'approved', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(39, 2, 59, NULL, 'approved', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(40, 2, 60, NULL, 'paid', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(41, 3, 61, NULL, 'pending', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(42, 3, 62, NULL, 'approved', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(43, 3, 63, NULL, 'approved', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(44, 3, 64, NULL, 'approved', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(45, 3, 65, NULL, 'approved', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(46, 3, 66, NULL, 'approved', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(47, 3, 67, NULL, 'approved', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(48, 3, 68, NULL, 'approved', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(49, 3, 69, NULL, 'approved', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(50, 3, 70, NULL, 'paid', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(51, 3, 71, NULL, 'pending', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(52, 3, 72, NULL, 'approved', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(53, 3, 73, NULL, 'approved', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(54, 3, 74, NULL, 'approved', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(55, 3, 75, NULL, 'approved', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(56, 3, 76, NULL, 'approved', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(57, 3, 77, NULL, 'approved', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(58, 3, 78, NULL, 'approved', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(59, 3, 79, NULL, 'approved', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
INSERT INTO `turnamen_peserta` (`id`, `id_turnamen`, `id_pemain1`, `id_pemain2`, `status`, `bukti_bayar`, `created_at`, `updated_at`) VALUES
	(60, 3, 80, NULL, 'paid', NULL, '2026-07-05 15:40:21', '2026-07-05 15:40:21');
/*!40000 ALTER TABLE `turnamen_peserta` ENABLE KEYS */;

/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
