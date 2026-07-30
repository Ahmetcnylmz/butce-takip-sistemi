<?php
require "config.php";
require "includes/fonksiyonlar.php";

// Zaten giriş yapmışsa direkt ana sayfaya gönder
if (isset($_SESSION['kullanici_id'])) {
    header("Location: index.php");
    exit;
}

$hata = "";

// Çok fazla yanlış deneme yapılırsa 60 saniye bekletiyoruz
if (!isset($_SESSION['giris_deneme'])) {
    $_SESSION['giris_deneme'] = 0;
    $_SESSION['giris_kilit_zamani'] = 0;
}
$kilitliMi = $_SESSION['giris_deneme'] >= 5 && (time() - $_SESSION['giris_kilit_zamani']) < 60;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfDogrula();
    botKontrolEt();

    if ($kilitliMi) {
        $hata = "Çok fazla yanlış deneme yaptın. Lütfen 1 dakika sonra tekrar dene.";
    } else {
    $kullaniciAdi = trim($_POST['username'] ?? '');
    $sifre = $_POST['password'] ?? '';

    if ($kullaniciAdi === '' || $sifre === '') {
        $hata = "Lütfen kullanıcı adı ve şifreni gir.";
    } else {
        $sorgu = $baglanti->prepare("SELECT * FROM kullanicilar WHERE kullanici_adi = ? OR email = ?");
        $sorgu->bind_param("ss", $kullaniciAdi, $kullaniciAdi);
        $sorgu->execute();
        $kullanici = $sorgu->get_result()->fetch_assoc();

        if ($kullanici && password_verify($sifre, $kullanici['sifre'])) {
            $_SESSION['giris_deneme'] = 0;

            // Giriş başarılı: oturum kimliğini yenile
            session_regenerate_id(true);
            $_SESSION['kullanici_id'] = $kullanici['id'];
            $_SESSION['ad_soyad'] = $kullanici['ad_soyad'];
            $_SESSION['kullanici_adi'] = $kullanici['kullanici_adi'];
            $_SESSION['email'] = $kullanici['email'];
            $_SESSION['rol'] = $kullanici['rol'];
            $_SESSION['dogrulandi'] = $kullanici['dogrulandi'];

            // "Beni Hatırla" işaretliyse 30 gün geçerli bir çerez oluştur
            if (isset($_POST['beni_hatirla'])) {
                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $guncelle = $baglanti->prepare("UPDATE kullanicilar SET hatirla_token = ? WHERE id = ?");
                $guncelle->bind_param("si", $tokenHash, $kullanici['id']);
                $guncelle->execute();
                setcookie("hatirla_token", $token, time() + 60 * 60 * 24 * 30, "/", "", false, true);
            }

            header("Location: index.php");
            exit;
        } else {
            $_SESSION['giris_deneme']++;
            $_SESSION['giris_kilit_zamani'] = time();
            $hata = "Kullanıcı adı veya şifre hatalı.";
        }
    }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Giriş Yap</title>
  <link rel="icon" type="image/svg+xml" href="logo/favicon.svg">
  <link rel="manifest" href="manifest.json">
  <meta name="theme-color" content="#4f46e5">
  <link rel="apple-touch-icon" href="logo/pwa/apple-touch-icon.png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
  <link rel="stylesheet" href="css/login.css">
</head>
<body>
  <div class="container">
    <div class="left">
      <div class="logo">
        <i class="fa-solid fa-wallet"></i>
        <span>Bütçe Takip</span>
      </div>
      <h1>Finansını<br>Akıllıca Yönet</h1>
      <p>Gelirlerini takip et.<br>Giderlerini kontrol altında tut.<br>Grafiklerle bütçeni analiz et.</p>
      <div class="features">
        <div class="feature">
          <i class="fa-solid fa-chart-line"></i>
          <div>
            <h3>Gelir Takibi</h3>
            <small>Tüm gelirlerini kaydet.</small>
          </div>
        </div>
        <div class="feature">
          <i class="fa-solid fa-credit-card"></i>
          <div>
            <h3>Gider Yönetimi</h3>
            <small>Harcamalarını analiz et.</small>
          </div>
        </div>
        <div class="feature">
          <i class="fa-solid fa-chart-pie"></i>
          <div>
            <h3>Grafikler</h3>
            <small>Anlık raporlar.</small>
          </div>
        </div>
        <div class="feature">
          <i class="fa-solid fa-layer-group"></i>
          <div>
            <h3>Kategoriler</h3>
            <small>Düzenli bütçe planı.</small>
          </div>
        </div>
      </div>
    </div>
    <div class="right">
      <div class="login">
        <h2>Hoş Geldiniz</h2>
        <p>Devam etmek için giriş yapın.</p>

        <?php if ($hata !== "") : ?>
          <p style="color:#dc2626; margin-bottom:15px; font-size:0.9rem;"><?= htmlspecialchars($hata) ?></p>
        <?php endif; ?>

        <form action="login.php" method="post">
        <?php csrfAlanYaz(); ?>
        <?php botAlaniYaz(); ?>
          <div class="input">
            <label>Kullanıcı Adı veya E-posta</label>
            <input type="text" id="username" name="username" placeholder="Kullanıcı adınızı giriniz" required>
          </div>
          <div class="input password">
            <label>Şifre</label>
            <input type="password" id="password" name="password" placeholder="Şifrenizi giriniz" required>
            <i class="fa-solid fa-eye" id="passwordIcon"></i>
          </div>
          <label style="display:flex; align-items:center; gap:8px; font-size:0.85rem; color:#555; margin:-6px 0 14px;">
            <input type="checkbox" name="beni_hatirla" value="1" style="width:auto;">
            Beni hatırla
          </label>
          <button class="login-btn" type="submit">Giriş Yap</button>
        </form>

        <div class="register" style="margin-top:8px;">
          <a href="sifremi-unuttum.php">Şifremi unuttum</a>
        </div>

        <div class="register">
          Hesabın yok mu?
          <a href="kayit-ol.php">Kayıt Ol</a>
        </div>
      </div>
    </div>
  </div>
  <script src="assets/js/login.js"></script>
</body>
</html>
