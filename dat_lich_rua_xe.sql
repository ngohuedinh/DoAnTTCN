-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1:3306
-- Thời gian đã tạo: Th12 28, 2025 lúc 03:37 AM
-- Phiên bản máy phục vụ: 9.1.0
-- Phiên bản PHP: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `dat_lich_rua_xe`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `cau_hinh`
--

DROP TABLE IF EXISTS `cau_hinh`;
CREATE TABLE IF NOT EXISTS `cau_hinh` (
  `id` int NOT NULL AUTO_INCREMENT,
  `key_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_name` (`key_name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `cau_hinh`
--

INSERT INTO `cau_hinh` (`id`, `key_name`, `value`, `description`) VALUES
(1, 'max_xe_moi_gio', '3', 'Số xe tối đa mỗi khung giờ'),
(2, 'gio_mo_cua', '07:00', 'Giờ mở cửa'),
(3, 'gio_dong_cua', '18:30', 'Giờ đóng cửa'),
(4, 'ngay_nghi', 'Chủ nhật', 'Các ngày nghỉ trong tuần (ngăn cách bởi dấu phẩy)'),
(5, 'ngay_le_nghi', '', 'Các ngày lễ nghỉ (định dạng: 01/01,30/04,01/05)');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `chi_tiet_dat_lich`
--

DROP TABLE IF EXISTS `chi_tiet_dat_lich`;
CREATE TABLE IF NOT EXISTS `chi_tiet_dat_lich` (
  `MaDatLich` int NOT NULL,
  `MaDichVu` int NOT NULL,
  `GiaDichVu` decimal(15,2) DEFAULT '0.00' COMMENT 'Lưu giá tại thời điểm đặt',
  PRIMARY KEY (`MaDatLich`,`MaDichVu`),
  KEY `MaDichVu` (`MaDichVu`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `chi_tiet_dat_lich`
--

INSERT INTO `chi_tiet_dat_lich` (`MaDatLich`, `MaDichVu`, `GiaDichVu`) VALUES
(1, 2, 25000.00),
(1, 3, 50000.00),
(1, 4, 100000.00);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `dat_lich`
--

DROP TABLE IF EXISTS `dat_lich`;
CREATE TABLE IF NOT EXISTS `dat_lich` (
  `MaDatLich` int NOT NULL AUTO_INCREMENT,
  `MaNguoiDung` int NOT NULL,
  `NgayDat` date NOT NULL,
  `GioDat` time NOT NULL,
  `LoaiXe` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `GhiChu` text COLLATE utf8mb4_unicode_ci,
  `TongTien` decimal(15,2) DEFAULT '0.00',
  `TrangThai` enum('Mới','Đang xử lý','Đã hoàn thành','Đã hủy') COLLATE utf8mb4_unicode_ci DEFAULT 'Mới',
  `NgayTao` datetime DEFAULT CURRENT_TIMESTAMP,
  `TrangThaiThongBao` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`MaDatLich`),
  KEY `idx_NgayGio` (`NgayDat`,`GioDat`),
  KEY `idx_TrangThai` (`TrangThai`),
  KEY `MaNguoiDung` (`MaNguoiDung`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `dat_lich`
--

INSERT INTO `dat_lich` (`MaDatLich`, `MaNguoiDung`, `NgayDat`, `GioDat`, `LoaiXe`, `GhiChu`, `TongTien`, `TrangThai`, `NgayTao`, `TrangThaiThongBao`) VALUES
(1, 2, '2025-12-28', '12:00:00', 'Xe máy', '', 175000.00, 'Đã hủy', '2025-12-27 22:00:28', 0);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `dich_vu`
--

DROP TABLE IF EXISTS `dich_vu`;
CREATE TABLE IF NOT EXISTS `dich_vu` (
  `MaDichVu` int NOT NULL AUTO_INCREMENT,
  `TenDichVu` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Gia` decimal(15,2) NOT NULL DEFAULT '0.00',
  `LoaiDV` enum('chinh','them') COLLATE utf8mb4_unicode_ci DEFAULT 'chinh',
  `ThoiGian` int DEFAULT '30' COMMENT 'Thời gian thực hiện (phút)',
  `TrangThai` enum('hoatdong','tamngung') COLLATE utf8mb4_unicode_ci DEFAULT 'hoatdong',
  PRIMARY KEY (`MaDichVu`),
  UNIQUE KEY `TenDichVu` (`TenDichVu`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `dich_vu`
--

INSERT INTO `dich_vu` (`MaDichVu`, `TenDichVu`, `Gia`, `LoaiDV`, `ThoiGian`, `TrangThai`) VALUES
(1, 'Rửa Xe Cơ Bản', 15000.00, 'chinh', 30, 'hoatdong'),
(2, 'Rửa Xe Kỹ', 25000.00, 'chinh', 45, 'hoatdong'),
(3, 'Tẩy ố', 50000.00, 'them', 60, 'hoatdong'),
(4, 'Phủ Bóng', 100000.00, 'them', 90, 'hoatdong'),
(5, 'Vệ sinh nội thất', 250000.00, 'them', 120, 'hoatdong');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `nguoi_dung`
--

DROP TABLE IF EXISTS `nguoi_dung`;
CREATE TABLE IF NOT EXISTS `nguoi_dung` (
  `MaNguoiDung` int NOT NULL AUTO_INCREMENT,
  `HoVaTen` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `SoDienThoai` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `TenDangNhap` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `MatKhau` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `DiaChi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `VaiTro` enum('customer','admin') COLLATE utf8mb4_unicode_ci DEFAULT 'customer',
  `verify_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verify_expire` datetime DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT '0',
  `reset_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reset_expire` datetime DEFAULT NULL,
  `NgayTao` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`MaNguoiDung`),
  UNIQUE KEY `SoDienThoai` (`SoDienThoai`),
  UNIQUE KEY `Email` (`Email`),
  UNIQUE KEY `TenDangNhap` (`TenDangNhap`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `nguoi_dung`
--

INSERT INTO `nguoi_dung` (`MaNguoiDung`, `HoVaTen`, `SoDienThoai`, `Email`, `TenDangNhap`, `MatKhau`, `DiaChi`, `VaiTro`, `verify_token`, `verify_expire`, `is_verified`, `reset_token`, `reset_expire`, `NgayTao`) VALUES
(2, 'Huế Đình', '0389836316', 'ngohuedinhit@gmail.com', 'ngohuedinh', '$2y$10$lAoG1DoUZA7iZlmLZraH2.iREpgDDD.SDrQ/SRkwmtopUmU8Gacvi', 'đ', 'customer', 'e5b6c8f7a6b9a5f32aa638280f9f6559', '2025-12-27 13:13:20', 1, NULL, NULL, '2025-12-27 20:03:20'),
(5, 'Administrator', '0999999999', 'admin@d2auto.com', 'admin', '$2y$10$k1f0JPQHS6wTRyEG66m5zeGaj1bWG2YRjfIg8EskUFwy817uiklNy', 'D2AUTO HQ', 'admin', NULL, NULL, 1, NULL, NULL, '2025-12-27 20:51:59');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `phan_hoi`
--

DROP TABLE IF EXISTS `phan_hoi`;
CREATE TABLE IF NOT EXISTS `phan_hoi` (
  `MaPhanHoi` int NOT NULL AUTO_INCREMENT,
  `MaDatLich` int NOT NULL,
  `DiemDanhGia` tinyint(1) DEFAULT NULL COMMENT 'Điểm đánh giá từ 1-5 sao',
  `NoiDung` text COLLATE utf8mb4_unicode_ci,
  `NgayPhanHoi` datetime DEFAULT CURRENT_TIMESTAMP,
  `TrangThai` enum('hienthi','an') COLLATE utf8mb4_unicode_ci DEFAULT 'hienthi',
  PRIMARY KEY (`MaPhanHoi`),
  UNIQUE KEY `UK_PH` (`MaDatLich`)
) ;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `chi_tiet_dat_lich`
--
ALTER TABLE `chi_tiet_dat_lich`
  ADD CONSTRAINT `chi_tiet_dat_lich_ibfk_1` FOREIGN KEY (`MaDatLich`) REFERENCES `dat_lich` (`MaDatLich`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `chi_tiet_dat_lich_ibfk_2` FOREIGN KEY (`MaDichVu`) REFERENCES `dich_vu` (`MaDichVu`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `dat_lich`
--
ALTER TABLE `dat_lich`
  ADD CONSTRAINT `dat_lich_ibfk_1` FOREIGN KEY (`MaNguoiDung`) REFERENCES `nguoi_dung` (`MaNguoiDung`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `phan_hoi`
--
ALTER TABLE `phan_hoi`
  ADD CONSTRAINT `phan_hoi_ibfk_1` FOREIGN KEY (`MaDatLich`) REFERENCES `dat_lich` (`MaDatLich`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
