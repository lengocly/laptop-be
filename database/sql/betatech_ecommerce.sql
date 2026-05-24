-- =============================================================================
-- BETA TECH — FILE SQL DUY NHẤT: danh mục + 40 sản phẩm mẫu
-- =============================================================================
-- MỤC ĐÍCH:
--   • Tạo database `betatech_ecommerce` (nếu chưa có).
--   • Tạo lại bảng `categories` (menu 2 cấp) và `products` (laptop + phụ kiện).
--   • Chèn đầy đủ dữ liệu demo — ảnh Unsplash crop vuông 640×640 (khớp UI).
--
-- CÁCH IMPORT TRONG HeidiSQL:
--   1) Mở file này (database/sql/betatech_ecommerce.sql).
--   2) Nhấn F9 (Execute) — không cần chọn DB trước; script tự USE.
--
-- CẢNH BÁO:
--   • Script DROP và tạo lại `products`, `categories` → mất dữ liệu cũ trong 2 bảng.
--   • Không đụng bảng khác (users, migrations, sessions, ...).
--
-- SAU KHI CHẠY — API Laravel:
--   GET /api/v1/categories              → cây menu.
--   GET /api/v1/product                 → tất cả sản phẩm.
--   GET /api/v1/product?category=slug → lọc theo danh mục lá (vd. laptop-gaming).
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `betatech_ecommerce`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `betatech_ecommerce`;

DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `categories`;

CREATE TABLE `categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `parent_id` bigint unsigned DEFAULT NULL,
  `sort_order` smallint unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `categories_slug_unique` (`slug`),
  KEY `categories_parent_id_foreign` (`parent_id`),
  CONSTRAINT `categories_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `category_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `price_display` varchar(255) NOT NULL,
  `image_main` varchar(2048) NOT NULL,
  `image_hover` varchar(2048) DEFAULT NULL,
  `cpu` varchar(255) DEFAULT NULL,
  `ram` varchar(255) DEFAULT NULL,
  `storage` varchar(255) DEFAULT NULL,
  `screen` varchar(255) DEFAULT NULL,
  `stock` int unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `products_slug_unique` (`slug`),
  KEY `products_category_id_foreign` (`category_id`),
  CONSTRAINT `products_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categories` (`id`, `name`, `slug`, `parent_id`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'Laptop', 'laptop', NULL, 1, NOW(), NOW()),
(2, 'Phụ kiện', 'phu-kien', NULL, 2, NOW(), NOW()),
(3, 'Laptop gaming', 'laptop-gaming', 1, 1, NOW(), NOW()),
(4, 'Laptop văn phòng & mỏng nhẹ', 'laptop-van-phong', 1, 2, NOW(), NOW()),
(5, 'Chuột & lót chuột', 'chuot-lot-chuot', 2, 1, NOW(), NOW()),
(6, 'Bàn phím', 'ban-phim', 2, 2, NOW(), NOW()),
(7, 'Tai nghe & âm thanh', 'tai-nghe-am-thanh', 2, 3, NOW(), NOW()),
(8, 'Sạc & cáp', 'sac-cap', 2, 4, NOW(), NOW()),
(9, 'Túi & balo', 'tui-balo', 2, 5, NOW(), NOW());

INSERT INTO `products` (`category_id`, `name`, `slug`, `price_display`, `image_main`, `image_hover`, `cpu`, `ram`, `storage`, `screen`, `stock`, `is_active`, `created_at`, `updated_at`) VALUES
(3, 'ASUS ROG Strix G16 — RTX 4060', 'asus-rog-strix-g16', '42.990.000đ', 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?auto=format&fit=crop&w=640&h=640&q=80', 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=640&h=640&q=80', 'Intel Core i7-13650HX', '16GB', '1TB SSD', '16" QHD 165Hz', 6, 1, NOW(), NOW()),
(3, 'MSI Stealth 16 Studio — RTX 4070', 'msi-stealth-16-studio', '48.490.000đ', 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=640&h=640&q=80', 'https://images.unsplash.com/photo-1525547719571-a2d4ac8945e2?auto=format&fit=crop&w=640&h=640&q=80', 'Intel Core Ultra 7', '32GB', '1TB SSD', '16" QHD+ 240Hz', 4, 1, NOW(), NOW()),
(3, 'Lenovo Legion Pro 5 — RTX 4050', 'lenovo-legion-pro-5', '36.990.000đ', 'https://images.unsplash.com/photo-1525547719571-a2d4ac8945e2?auto=format&fit=crop&w=640&h=640&q=80', 'https://images.unsplash.com/photo-1541807084-5c52b6b3adef?auto=format&fit=crop&w=640&h=640&q=80', 'AMD Ryzen 7 7840HS', '16GB', '512GB SSD', '16" WQXGA 165Hz', 7, 1, NOW(), NOW()),
(3, 'Acer Nitro 17 — RTX 4050', 'acer-nitro-17', '31.490.000đ', 'https://images.unsplash.com/photo-1541807084-5c52b6b3adef?auto=format&fit=crop&w=640&h=640&q=80', 'https://images.unsplash.com/photo-1593642632823-8f785ba67e45?auto=format&fit=crop&w=640&h=640&q=80', 'AMD Ryzen 5 8645HS', '16GB', '512GB SSD', '17.3" FHD 165Hz', 5, 1, NOW(), NOW()),
(3, 'HP Omen 16 — RTX 4060', 'hp-omen-16', '39.900.000đ', 'https://images.unsplash.com/photo-1593642632823-8f785ba67e45?auto=format&fit=crop&w=640&h=640&q=80', 'https://images.unsplash.com/photo-1588872657578-7efd1f1555ed?auto=format&fit=crop&w=640&h=640&q=80', 'Intel Core i7-13700H', '16GB', '1TB SSD', '16.1" QHD 165Hz', 5, 1, NOW(), NOW()),
(3, 'Gigabyte G5 — RTX 4050', 'gigabyte-g5', '27.990.000đ', 'https://images.unsplash.com/photo-1588872657578-7efd1f1555ed?auto=format&fit=crop&w=640&h=640&q=80', 'https://images.unsplash.com/photo-1611186871348-b1ce696e52c9?auto=format&fit=crop&w=640&h=640&q=80', 'Intel Core i5-12500H', '16GB', '512GB SSD', '15.6" FHD 144Hz', 8, 1, NOW(), NOW()),
(3, 'ASUS TUF Gaming A15', 'asus-tuf-a15', '29.490.000đ', 'https://images.unsplash.com/photo-1611186871348-b1ce696e52c9?auto=format&fit=crop&w=640&h=640&q=80', 'https://images.unsplash.com/photo-1531297484001-80022131e5a1?auto=format&fit=crop&w=640&h=640&q=80', 'AMD Ryzen 7 7735HS', '16GB', '512GB SSD', '15.6" FHD 144Hz', 9, 1, NOW(), NOW()),
(3, 'Razer Blade 14 — RTX 4070', 'razer-blade-14', '62.990.000đ', 'https://images.unsplash.com/photo-1531297484001-80022131e5a1?auto=format&fit=crop&w=640&h=640&q=80', 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?auto=format&fit=crop&w=640&h=640&q=80', 'AMD Ryzen 9 7940HS', '32GB', '1TB SSD', '14" QHD+ 240Hz', 2, 1, NOW(), NOW()),
(3, 'Dell Alienware m16 R2', 'dell-alienware-m16-r2', '55.900.000đ', 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?auto=format&fit=crop&w=640&h=640&q=80', 'https://images.unsplash.com/photo-1541807084-5c52b6b3adef?auto=format&fit=crop&w=640&h=640&q=80', 'Intel Core Ultra 9', '32GB', '1TB SSD', '16" QHD+ 240Hz', 3, 1, NOW(), NOW()),
(3, 'MSI Cyborg 15 — RTX 4050', 'msi-cyborg-15', '24.990.000đ', 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=640&h=640&q=80', 'https://images.unsplash.com/photo-1588872657578-7efd1f1555ed?auto=format&fit=crop&w=640&h=640&q=80', 'Intel Core i5-13420H', '8GB', '512GB SSD', '15.6" FHD 144Hz', 11, 1, NOW(), NOW()),

(4, 'MacBook Air M3 13" 256GB', 'macbook-air-m3-13', '26.990.000đ', 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=640&h=640&q=80', 'https://images.unsplash.com/photo-1611186871348-b1ce696e52c9?auto=format&fit=crop&w=640&h=640&q=80', 'Apple M3', '8GB', '256GB SSD', '13.6" Liquid Retina', 10, 1, NOW(), NOW()),
(4, 'MacBook Pro 14" M4 Pro', 'macbook-pro-14-m4', '51.990.000đ', 'https://images.unsplash.com/photo-1611186871348-b1ce696e52c9?auto=format&fit=crop&w=640&h=640&q=80', 'https://images.unsplash.com/photo-1541807084-5c52b6b3adef?auto=format&fit=crop&w=640&h=640&q=80', 'Apple M4 Pro', '18GB', '512GB SSD', '14.2" Liquid Retina XDR', 4, 1, NOW(), NOW()),
(4, 'Dell XPS 15 — Core i7', 'dell-xps-15-i7', '38.490.000đ', 'https://images.unsplash.com/photo-1593642632823-8f785ba67e45?auto=format&fit=crop&w=640&h=640&q=80', 'https://images.unsplash.com/photo-1588872657578-7efd1f1555ed?auto=format&fit=crop&w=640&h=640&q=80', 'Intel Core i7-13700H', '16GB', '512GB SSD', '15.6" FHD+', 5, 1, NOW(), NOW()),
(4, 'HP Pavilion 15 — Ryzen 7', 'hp-pavilion-15', '16.490.000đ', 'https://images.unsplash.com/photo-1525547719571-a2d4ac8945e2?auto=format&fit=crop&w=640&h=640&q=80', 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?auto=format&fit=crop&w=640&h=640&q=80', 'AMD Ryzen 7 7730U', '16GB', '512GB SSD', '15.6" FHD', 12, 1, NOW(), NOW()),
(4, 'Acer Swift Go 14 — Intel Ultra', 'acer-swift-go-14', '18.990.000đ', 'https://images.unsplash.com/photo-1541807084-5c52b6b3adef?auto=format&fit=crop&w=640&h=640&q=80', 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=640&h=640&q=80', 'Intel Core Ultra 5', '16GB', '512GB SSD', '14" IPS 2.2K', 9, 1, NOW(), NOW()),
(4, 'LG Gram 17 siêu nhẹ', 'lg-gram-17', '41.990.000đ', 'https://images.unsplash.com/photo-1531297484001-80022131e5a1?auto=format&fit=crop&w=640&h=640&q=80', 'https://images.unsplash.com/photo-1525547719571-a2d4ac8945e2?auto=format&fit=crop&w=640&h=640&q=80', 'Intel Core Ultra 7', '16GB', '512GB SSD', '17" WQXGA', 4, 1, NOW(), NOW()),
(4, 'Lenovo ThinkPad E14 Gen 5', 'lenovo-thinkpad-e14', '17.990.000đ', 'https://images.unsplash.com/photo-1588872657578-7efd1f1555ed?auto=format&fit=crop&w=640&h=640&q=80', 'https://images.unsplash.com/photo-1593642632823-8f785ba67e45?auto=format&fit=crop&w=640&h=640&q=80', 'AMD Ryzen 5 7530U', '16GB', '512GB SSD', '14" FHD', 8, 1, NOW(), NOW()),
(4, 'Surface Laptop 7 — Snapdragon X Elite', 'surface-laptop-7', '37.490.000đ', 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?auto=format&fit=crop&w=640&h=640&q=80', 'https://images.unsplash.com/photo-1611186871348-b1ce696e52c9?auto=format&fit=crop&w=640&h=640&q=80', 'Snapdragon X Elite', '16GB', '512GB SSD', '13.8" PixelSense', 3, 1, NOW(), NOW()),
(4, 'Dell Inspiron 14 Plus', 'dell-inspiron-14-plus', '19.290.000đ', 'https://images.unsplash.com/photo-1611186871348-b1ce696e52c9?auto=format&fit=crop&w=640&h=640&q=80', 'https://images.unsplash.com/photo-1531297484001-80022131e5a1?auto=format&fit=crop&w=640&h=640&q=80', 'Intel Core i5-1335U', '16GB', '512GB SSD', '14" FHD+', 11, 1, NOW(), NOW()),
(4, 'Samsung Galaxy Book4 Pro 14"', 'samsung-galaxy-book4-pro', '32.990.000đ', 'https://images.unsplash.com/photo-1525547719571-a2d4ac8945e2?auto=format&fit=crop&w=640&h=640&q=80', 'https://images.unsplash.com/photo-1541807084-5c52b6b3adef?auto=format&fit=crop&w=640&h=640&q=80', 'Intel Core Ultra 7', '16GB', '512GB SSD', '14" AMOLED', 6, 1, NOW(), NOW()),

(5, 'Chuột Logitech MX Master 3S', 'chuot-mx-master-3s', '2.890.000đ', 'https://images.unsplash.com/photo-1527814050087-3793815479db?auto=format&fit=crop&w=640&h=640&q=80', 'https://images.unsplash.com/photo-1563297007-0686b7003af7?auto=format&fit=crop&w=640&h=640&q=80', NULL, NULL, 'Bluetooth / USB', NULL, 25, 1, NOW(), NOW()),
(5, 'Chuột Razer DeathAdder V3', 'chuot-razer-deathadder-v3', '1.990.000đ', 'https://images.unsplash.com/photo-1563297007-0686b7003af7?auto=format&fit=crop&w=640&h=640&q=80', 'https://images.unsplash.com/photo-1615663245857-ac93bb7c39e7?auto=format&fit=crop&w=640&h=640&q=80', NULL, NULL, 'USB có dây', NULL, 18, 1, NOW(), NOW()),
(5, 'Chuột không dây Microsoft Bluetooth', 'chuot-microsoft-bluetooth', '890.000đ', 'https://images.unsplash.com/photo-1615663245857-ac93bb7c39e7?auto=format&fit=crop&w=640&h=640&q=80', 'https://images.unsplash.com/photo-1527814050087-3793815479db?auto=format&fit=crop&w=640&h=640&q=80', NULL, NULL, 'Bluetooth 5.0', NULL, 30, 1, NOW(), NOW()),
(5, 'Lót chuột desk pad 90×40 cm', 'lot-chuot-desk-pad-90', '290.000đ', 'https://images.unsplash.com/photo-1618384887929-16ec33fab0ef?auto=format&fit=crop&w=640&h=640&q=80', 'https://images.unsplash.com/photo-1587825140708-dfaf72ae4b04?auto=format&fit=crop&w=640&h=640&q=80', NULL, NULL, 'Vải + cao su', NULL, 40, 1, NOW(), NOW()),
(5, 'Chuột Corsair M75 Air Wireless', 'chuot-corsair-m75-air', '2.190.000đ', 'https://images.unsplash.com/photo-1527814050087-3793815479db?auto=format&fit=crop&w=640&h=640&q=80', 'https://images.unsplash.com/photo-1618384887929-16ec33fab0ef?auto=format&fit=crop&w=640&h=640&q=80', NULL, NULL, 'Siêu nhẹ', NULL, 14, 1, NOW(), NOW()),

(6, 'Bàn phím cơ Keychron K2', 'ban-phim-keychron-k2', '2.490.000đ', 'https://images.unsplash.com/photo-1587829741301-dc798b83add3?auto=format&fit=crop&w=640&h=640&q=80', 'https://images.unsplash.com/photo-1541140532154-b024d605b90d?auto=format&fit=crop&w=640&h=640&q=80', NULL, NULL, '75% Bluetooth', NULL, 15, 1, NOW(), NOW()),
(6, 'Bàn phím Logitech MX Keys S', 'ban-phim-mx-keys-s', '2.990.000đ', 'https://images.unsplash.com/photo-1541140532154-b024d605b90d?auto=format&fit=crop&w=640&h=640&q=80', 'https://images.unsplash.com/photo-1587829741301-dc798b83add3?auto=format&fit=crop&w=640&h=640&q=80', NULL, NULL, 'Không dây', NULL, 20, 1, NOW(), NOW()),
(6, 'Bàn phím cơ ROG Strix Scope II', 'ban-phim-rog-strix-scope-2', '3.490.000đ', 'https://images.unsplash.com/photo-1587829741301-dc798b83add3?auto=format&fit=crop&w=640&h=640&q=80', 'https://images.unsplash.com/photo-1587825140708-dfaf72ae4b04?auto=format&fit=crop&w=640&h=640&q=80', NULL, NULL, 'RX Optical', NULL, 10, 1, NOW(), NOW()),
(6, 'Bàn phím Apple Magic Keyboard', 'ban-phim-apple-magic', '3.290.000đ', 'https://images.unsplash.com/photo-1541140532154-b024d605b90d?auto=format&fit=crop&w=640&h=640&q=80', 'https://images.unsplash.com/photo-1618384887929-16ec33fab0ef?auto=format&fit=crop&w=640&h=640&q=80', NULL, NULL, 'Bluetooth', NULL, 12, 1, NOW(), NOW()),

(7, 'Tai nghe Sony WH-1000XM5', 'tai-nghe-sony-wh1000xm5', '8.990.000đ', 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=640&h=640&q=80', 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?auto=format&fit=crop&w=640&h=640&q=80', NULL, NULL, 'Bluetooth chống ồn', NULL, 8, 1, NOW(), NOW()),
(7, 'Tai nghe HyperX Cloud III', 'tai-nghe-hyperx-cloud-3', '2.190.000đ', 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?auto=format&fit=crop&w=640&h=640&q=80', 'https://images.unsplash.com/photo-1484704849700-f032a568e944?auto=format&fit=crop&w=640&h=640&q=80', NULL, NULL, 'Jack / USB', NULL, 14, 1, NOW(), NOW()),
(7, 'Loa Bluetooth JBL Flip 6', 'loa-jbl-flip-6', '2.590.000đ', 'https://images.unsplash.com/photo-1545454675-3531b543be5d?auto=format&fit=crop&w=640&h=640&q=80', 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=640&h=640&q=80', NULL, NULL, 'Chống nước IP67', NULL, 16, 1, NOW(), NOW()),
(7, 'Tai nghe AirPods Pro 2', 'tai-nghe-airpods-pro-2', '5.990.000đ', 'https://images.unsplash.com/photo-1572569511124-845ff709f9ac?auto=format&fit=crop&w=640&h=640&q=80', 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=640&h=640&q=80', NULL, NULL, 'USB-C', NULL, 20, 1, NOW(), NOW()),

(8, 'Củ sạc GaN 65W 2 cổng USB-C', 'cu-sac-gan-65w', '590.000đ', 'https://images.unsplash.com/photo-1583863788434-eab5f23f05b3?auto=format&fit=crop&w=640&h=640&q=80', 'https://images.unsplash.com/photo-1609091839311-d5365f9ff1c5?auto=format&fit=crop&w=640&h=640&q=80', NULL, NULL, 'PD 65W', NULL, 50, 1, NOW(), NOW()),
(8, 'Cáp USB-C 2m 100W', 'cap-usbc-2m-100w', '250.000đ', 'https://images.unsplash.com/photo-1625948515291-69613efd103f?auto=format&fit=crop&w=640&h=640&q=80', 'https://images.unsplash.com/photo-1583863788434-eab5f23f05b3?auto=format&fit=crop&w=640&h=640&q=80', NULL, NULL, 'Bện nylon', NULL, 60, 1, NOW(), NOW()),
(8, 'Hub USB-C 7 trong 1', 'hub-usbc-7in1', '890.000đ', 'https://images.unsplash.com/photo-1609091839311-d5365f9ff1c5?auto=format&fit=crop&w=640&h=640&q=80', 'https://images.unsplash.com/photo-1625948515291-69613efd103f?auto=format&fit=crop&w=640&h=640&q=80', NULL, NULL, 'HDMI 4K', NULL, 28, 1, NOW(), NOW()),
(8, 'Sạc dự phòng 20000mAh 22.5W', 'sac-du-phong-20000', '790.000đ', 'https://images.unsplash.com/photo-1625948515291-69613efd103f?auto=format&fit=crop&w=640&h=640&q=80', 'https://images.unsplash.com/photo-1609091839311-d5365f9ff1c5?auto=format&fit=crop&w=640&h=640&q=80', NULL, NULL, 'USB-A + C', NULL, 35, 1, NOW(), NOW()),

(9, 'Túi chống sốc laptop 15.6"', 'tui-chong-soc-156', '350.000đ', 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?auto=format&fit=crop&w=640&h=640&q=80', 'https://images.unsplash.com/photo-1622560480605-d83c853bc5c3?auto=format&fit=crop&w=640&h=640&q=80', NULL, NULL, 'Neoprene', NULL, 30, 1, NOW(), NOW()),
(9, 'Balo laptop chống nước 15.6"', 'balo-laptop-chong-nuoc', '890.000đ', 'https://images.unsplash.com/photo-1622560480605-d83c853bc5c3?auto=format&fit=crop&w=640&h=640&q=80', 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?auto=format&fit=crop&w=640&h=640&q=80', NULL, NULL, 'Polyester', NULL, 22, 1, NOW(), NOW()),
(9, 'Túi xách da laptop 14"', 'tui-xach-da-14', '1.290.000đ', 'https://images.unsplash.com/photo-1473187983305-f615310e7daa?auto=format&fit=crop&w=640&h=640&q=80', 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?auto=format&fit=crop&w=640&h=640&q=80', NULL, NULL, 'Da tổng hợp', NULL, 15, 1, NOW(), NOW());

SET FOREIGN_KEY_CHECKS = 1;
