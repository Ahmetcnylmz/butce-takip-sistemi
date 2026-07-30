<?php
// Veritabanı bağlantı bilgileri - kendi ortamına göre değiştir
$sunucu       = "localhost";
$kullanici    = "root";
$sifre        = "";
$veritabani   = "butcetakip";

// Hatalar ekranda gösterilmesin, log dosyasına yazılsın
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

$baglanti = new mysqli($sunucu, $kullanici, $sifre);
if ($baglanti->connect_error) {
    die("Veritabanı sunucusuna bağlanılamadı: " . $baglanti->connect_error);
}

$baglanti->query("CREATE DATABASE IF NOT EXISTS `$veritabani` CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci");
$baglanti->select_db($veritabani);
$baglanti->set_charset("utf8mb4");

// Tablolar yoksa otomatik oluşturulur
$baglanti->query("
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
        kayit_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB
");

// Daha önce kurulmuş eski veritabanlarında yeni sütunlar yoksa ekle
$sutunVarMi = $baglanti->query("SHOW COLUMNS FROM kullanicilar LIKE 'rol'");
if ($sutunVarMi && $sutunVarMi->num_rows === 0) {
    $baglanti->query("ALTER TABLE kullanicilar ADD COLUMN rol ENUM('kullanici','admin') NOT NULL DEFAULT 'kullanici'");
}
$sutunVarMi = $baglanti->query("SHOW COLUMNS FROM kullanicilar LIKE 'aylik_gider_hedefi'");
if ($sutunVarMi && $sutunVarMi->num_rows === 0) {
    $baglanti->query("ALTER TABLE kullanicilar ADD COLUMN aylik_gider_hedefi DECIMAL(12,2) NOT NULL DEFAULT 0");
    $baglanti->query("ALTER TABLE kullanicilar ADD COLUMN sifirlama_kodu VARCHAR(64) DEFAULT NULL");
    $baglanti->query("ALTER TABLE kullanicilar ADD COLUMN sifirlama_son_tarih DATETIME DEFAULT NULL");
}
$sutunVarMi = $baglanti->query("SHOW COLUMNS FROM kullanicilar LIKE 'ev_market_limiti'");
if ($sutunVarMi && $sutunVarMi->num_rows === 0) {
    $baglanti->query("ALTER TABLE kullanicilar ADD COLUMN ev_market_limiti DECIMAL(12,2) NOT NULL DEFAULT 0");
    // Geçiş kolaylığı: eski kullanıcılar için kategori limitleri toplamı başlangıç değeri olsun
    $baglanti->query("
        UPDATE kullanicilar k
        SET ev_market_limiti = (
            SELECT COALESCE(SUM(aylik_limit), 0) FROM ev_market_kategoriler WHERE kullanici_id = k.id
        )
    ");
}
$sutunVarMi = $baglanti->query("SHOW COLUMNS FROM kullanicilar LIKE 'dogrulandi'");
if ($sutunVarMi && $sutunVarMi->num_rows === 0) {
    $baglanti->query("ALTER TABLE kullanicilar ADD COLUMN dogrulandi TINYINT(1) NOT NULL DEFAULT 0");
    $baglanti->query("ALTER TABLE kullanicilar ADD COLUMN dogrulama_kodu VARCHAR(6) DEFAULT NULL");
    $baglanti->query("ALTER TABLE kullanicilar ADD COLUMN hatirla_token VARCHAR(64) DEFAULT NULL");
}

$baglanti->query("
    CREATE TABLE IF NOT EXISTS ev_market_kategoriler (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kullanici_id INT NOT NULL,
        ad VARCHAR(100) NOT NULL,
        ikon VARCHAR(50) NOT NULL DEFAULT 'fa-tag',
        aylik_limit DECIMAL(12,2) NOT NULL DEFAULT 0,
        FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE
    ) ENGINE=InnoDB
");

$baglanti->query("
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
    ) ENGINE=InnoDB
");

$sutunVarMi = $baglanti->query("SHOW COLUMNS FROM ev_market_harcamalar LIKE 'tekrarlayan'");
if ($sutunVarMi && $sutunVarMi->num_rows === 0) {
    $baglanti->query("ALTER TABLE ev_market_harcamalar ADD COLUMN tekrarlayan TINYINT(1) NOT NULL DEFAULT 0");
}

$baglanti->query("
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
    ) ENGINE=InnoDB
");

$sutunVarMi = $baglanti->query("SHOW COLUMNS FROM abonelikler LIKE 'eklenme_tarihi'");
if ($sutunVarMi && $sutunVarMi->num_rows === 0) {
    $baglanti->query("ALTER TABLE abonelikler ADD COLUMN eklenme_tarihi DATE DEFAULT NULL");
    $baglanti->query("UPDATE abonelikler SET eklenme_tarihi = CURDATE() WHERE eklenme_tarihi IS NULL");
}
$sutunVarMi = $baglanti->query("SHOW COLUMNS FROM abonelikler LIKE 'durum'");
if ($sutunVarMi && $sutunVarMi->num_rows === 0) {
    $baglanti->query("ALTER TABLE abonelikler ADD COLUMN durum ENUM('aktif','iptal') NOT NULL DEFAULT 'aktif'");
}

$baglanti->query("
    CREATE TABLE IF NOT EXISTS hatirlaticilar (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kullanici_id INT NOT NULL,
        baslik VARCHAR(150) NOT NULL,
        tarih DATE NOT NULL,
        tip ENUM('odeme','fatura','genel') NOT NULL DEFAULT 'genel',
        eposta_gonderildi TINYINT(1) NOT NULL DEFAULT 0,
        FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE
    ) ENGINE=InnoDB
");

$sutunVarMi = $baglanti->query("SHOW COLUMNS FROM hatirlaticilar LIKE 'eposta_gonderildi'");
if ($sutunVarMi && $sutunVarMi->num_rows === 0) {
    $baglanti->query("ALTER TABLE hatirlaticilar ADD COLUMN eposta_gonderildi TINYINT(1) NOT NULL DEFAULT 0");
}

$baglanti->query("
    CREATE TABLE IF NOT EXISTS admin_loglari (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_adi VARCHAR(100) NOT NULL,
        islem VARCHAR(255) NOT NULL,
        tarih DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB
");

$baglanti->query("
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
    ) ENGINE=InnoDB
");

$sutunVarMi = $baglanti->query("SHOW COLUMNS FROM borclar LIKE 'eklenme_tarihi'");
if ($sutunVarMi && $sutunVarMi->num_rows === 0) {
    $baglanti->query("ALTER TABLE borclar ADD COLUMN eklenme_tarihi DATE DEFAULT NULL");
    $baglanti->query("UPDATE borclar SET eklenme_tarihi = CURDATE() WHERE eklenme_tarihi IS NULL");
}

// "Beni Hatırla" ile giriş yapılmışsa ve oturum düşmüşse, çerezdeki
// token ile otomatik olarak tekrar giriş yap.
if (!isset($_SESSION['kullanici_id']) && !empty($_COOKIE['hatirla_token'])) {
    $tokenHash = hash('sha256', $_COOKIE['hatirla_token']);
    $sorgu = $baglanti->prepare("SELECT * FROM kullanicilar WHERE hatirla_token = ?");
    $sorgu->bind_param("s", $tokenHash);
    $sorgu->execute();
    $bulunanKullanici = $sorgu->get_result()->fetch_assoc();

    if ($bulunanKullanici) {
        session_regenerate_id(true);
        $_SESSION['kullanici_id'] = $bulunanKullanici['id'];
        $_SESSION['ad_soyad'] = $bulunanKullanici['ad_soyad'];
        $_SESSION['kullanici_adi'] = $bulunanKullanici['kullanici_adi'];
        $_SESSION['email'] = $bulunanKullanici['email'];
        $_SESSION['rol'] = $bulunanKullanici['rol'];
        $_SESSION['dogrulandi'] = $bulunanKullanici['dogrulandi'];
    } else {
        setcookie("hatirla_token", "", time() - 3600, "/");
    }
}

