-- =====================================================
--  BÜTÇE TAKİP SİSTEMİ - VERİTABANI ŞEMASI
-- =====================================================
--  Not: config.php dosyası bu tabloları zaten OTOMATİK
--  olarak oluşturur (CREATE TABLE IF NOT EXISTS).
--  Yani sadece aşağıdaki ilk satırla boş bir "butcetakip"
--  veritabanı oluşturup config.php'deki bilgileri girmen
--  yeterli; siteyi ilk açtığında tablolar kendiliğinden kurulur.
--
--  Bu dosyayı sadece elle / phpMyAdmin üzerinden aynı
--  yapıyı kurmak istersen kullanabilirsin.
-- =====================================================

CREATE DATABASE IF NOT EXISTS butcetakip CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci;
USE butcetakip;

-- Kullanıcılar
CREATE TABLE IF NOT EXISTS kullanicilar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ad_soyad VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    kullanici_adi VARCHAR(50) NOT NULL UNIQUE,
    sifre VARCHAR(255) NOT NULL,
    aylik_gelir DECIMAL(12,2) NOT NULL DEFAULT 0,
    aylik_gider_hedefi DECIMAL(12,2) NOT NULL DEFAULT 0,
    ev_market_limiti DECIMAL(12,2) NOT NULL DEFAULT 0,
    rol ENUM('kullanici','admin') NOT NULL DEFAULT 'kullanici',
    sifirlama_kodu VARCHAR(64) DEFAULT NULL,
    sifirlama_son_tarih DATETIME DEFAULT NULL,
    dogrulandi TINYINT(1) NOT NULL DEFAULT 0,
    dogrulama_kodu VARCHAR(6) DEFAULT NULL,
    hatirla_token VARCHAR(64) DEFAULT NULL,
    kayit_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Ev/Market kategorileri (her kullanıcının kendi kategorileri olur)
CREATE TABLE IF NOT EXISTS ev_market_kategoriler (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kullanici_id INT NOT NULL,
    ad VARCHAR(100) NOT NULL,
    ikon VARCHAR(50) NOT NULL DEFAULT 'fa-tag',
    aylik_limit DECIMAL(12,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Ev/Market harcamaları
CREATE TABLE IF NOT EXISTS ev_market_harcamalar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kullanici_id INT NOT NULL,
    kategori_id INT NOT NULL,
    aciklama VARCHAR(200) NOT NULL,
    tutar DECIMAL(12,2) NOT NULL,
    tarih DATE NOT NULL,
    tekrarlayan TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
    FOREIGN KEY (kategori_id) REFERENCES ev_market_kategoriler(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Abonelikler
CREATE TABLE IF NOT EXISTS abonelikler (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kullanici_id INT NOT NULL,
    ad VARCHAR(100) NOT NULL,
    fiyat DECIMAL(12,2) NOT NULL,
    yenileme_tarihi DATE NOT NULL,
    donge ENUM('monthly','yearly') NOT NULL DEFAULT 'monthly',
    kategori VARCHAR(30) NOT NULL DEFAULT 'diger',
    eklenme_tarihi DATE DEFAULT NULL,
    durum ENUM('aktif','iptal') NOT NULL DEFAULT 'aktif',
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Hatırlatıcılar
CREATE TABLE IF NOT EXISTS hatirlaticilar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kullanici_id INT NOT NULL,
    baslik VARCHAR(150) NOT NULL,
    tarih DATE NOT NULL,
    tip ENUM('odeme','fatura','genel') NOT NULL DEFAULT 'genel',
    eposta_gonderildi TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Borç / Alacak
CREATE TABLE IF NOT EXISTS borclar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kullanici_id INT NOT NULL,
    tip ENUM('borc','alacak') NOT NULL,
    baslik VARCHAR(150) NOT NULL,
    tutar DECIMAL(12,2) NOT NULL,
    aylik_taksit DECIMAL(12,2) NOT NULL DEFAULT 0,
    vade_tarihi DATE NOT NULL,
    odendi TINYINT(1) NOT NULL DEFAULT 0,
    eklenme_tarihi DATE DEFAULT NULL,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Admin işlem geçmişi
CREATE TABLE IF NOT EXISTS admin_loglari (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_adi VARCHAR(100) NOT NULL,
    islem VARCHAR(255) NOT NULL,
    tarih DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
