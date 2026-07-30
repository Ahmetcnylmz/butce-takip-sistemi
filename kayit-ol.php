<?php
require "config.php";
require "includes/fonksiyonlar.php";

if (isset($_SESSION['kullanici_id'])) {
    header("Location: index.php");
    exit;
}

$hata = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfDogrula();
    botKontrolEt();
    $adSoyad = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $kullaniciAdi = trim($_POST['username'] ?? '');
    $sifre = $_POST['password'] ?? '';
    $sifreTekrar = $_POST['password2'] ?? '';

    if ($adSoyad === '' || $email === '' || $kullaniciAdi === '' || $sifre === '') {
        $hata = "Lütfen tüm alanları doldur.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $hata = "Geçerli bir e-posta adresi gir.";
    } elseif (strlen($sifre) < 6) {
        $hata = "Şifre en az 6 karakter olmalı.";
    } elseif ($sifre !== $sifreTekrar) {
        $hata = "Şifreler birbiriyle eşleşmiyor.";
    } else {
        // Kullanıcı adı veya email zaten kayıtlı mı?
        $kontrol = $baglanti->prepare("SELECT id FROM kullanicilar WHERE email = ? OR kullanici_adi = ?");
        $kontrol->bind_param("ss", $email, $kullaniciAdi);
        $kontrol->execute();
        if ($kontrol->get_result()->num_rows > 0) {
            $hata = "Bu e-posta veya kullanıcı adı zaten kullanılıyor.";
        } else {
            $sifreHash = password_hash($sifre, PASSWORD_DEFAULT);
            $ekle = $baglanti->prepare("INSERT INTO kullanicilar (ad_soyad, email, kullanici_adi, sifre, aylik_gelir) VALUES (?, ?, ?, ?, 0)");
            $ekle->bind_param("ssss", $adSoyad, $email, $kullaniciAdi, $sifreHash);

            if ($ekle->execute()) {
                $yeniKullaniciId = $baglanti->insert_id;
                varsayilanKategorileriEkle($baglanti, $yeniKullaniciId);

                // Kayıttan sonra otomatik giriş yap (önce oturum kimliğini yenile)
                session_regenerate_id(true);
                $_SESSION['kullanici_id'] = $yeniKullaniciId;
                $_SESSION['ad_soyad'] = $adSoyad;
                $_SESSION['kullanici_adi'] = $kullaniciAdi;
                $_SESSION['email'] = $email;
                $_SESSION['rol'] = 'kullanici';
                $_SESSION['dogrulandi'] = 0;
                header("Location: index.php");
                exit;
            } else {
                $hata = "Kayıt sırasında bir hata oluştu, tekrar dener misin?";
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
  <title>Bütçe Takip | Kayıt Ol</title>
  <link rel="icon" type="image/svg+xml" href="logo/favicon.svg">
  <link rel="manifest" href="manifest.json">
  <meta name="theme-color" content="#4f46e5">
  <link rel="apple-touch-icon" href="logo/pwa/apple-touch-icon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
  <link rel="stylesheet" href="css/register.css">
</head>
<body>
  <div class="container">
    <div class="left">
      <div class="logo">
        <i class="fa-solid fa-wallet"></i>
        <span>Bütçe Takip</span>
      </div>

      <h1>Hesabını Oluştur<br>ve Kontrolü Ele Al</h1>
      <p>Gelirlerini, giderlerini ve bütçeni tek bir yerden yönet.</p>

      <div class="feature">
        <i class="fa-solid fa-chart-line"></i>
        <span>Gelir Takibi</span>
      </div>
      <div class="feature">
        <i class="fa-solid fa-credit-card"></i>
        <span>Gider Yönetimi</span>
      </div>
      <div class="feature">
        <i class="fa-solid fa-chart-pie"></i>
        <span>Grafikler ve Raporlar</span>
      </div>
      <div class="feature">
        <i class="fa-solid fa-layer-group"></i>
        <span>Kategori Yönetimi</span>
      </div>
    </div>

    <div class="right">
      <div class="register">
        <h2>Kayıt Ol</h2>
        <p>Yeni bir hesap oluştur.</p>

        <form action="kayit-ol.php" method="post">
        <?php csrfAlanYaz(); ?>
        <?php botAlaniYaz(); ?>
          <div class="input">
            <label for="fullname">Ad Soyad</label>
            <input type="text" id="fullname" name="fullname" placeholder="Ad Soyad" value="<?= htmlspecialchars($_POST['fullname'] ?? '') ?>">
          </div>

          <div class="input">
            <label for="email">E-Posta</label>
            <input type="email" id="email" name="email" placeholder="ornek@mail.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
          </div>

          <div class="input">
            <label for="username">Kullanıcı Adı</label>
            <input type="text" id="username" name="username" placeholder="Kullanıcı Adı" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
          </div>

          <div class="input password">
            <label for="password">Şifre</label>
            <input type="password" id="password" name="password" placeholder="Şifre (en az 6 karakter)">
            <i class="fa-solid fa-eye" id="passwordIcon"></i>
          </div>
          <div id="sifreGucuKutu" style="display:none; margin:-10px 0 15px;">
            <div style="height:5px; border-radius:3px; background:#e6e9f0; overflow:hidden;">
              <div id="sifreGucuCubuk" style="height:100%; width:0%; border-radius:3px; transition:.25s;"></div>
            </div>
            <small id="sifreGucuYazi" style="font-size:0.75rem; color:#888;"></small>
          </div>

          <div class="input">
            <label for="password2">Şifre Tekrar</label>
            <input type="password" id="password2" name="password2" placeholder="Şifre Tekrar">
          </div>

          <?php if ($hata !== "") : ?>
            <p style="color:#dc2626; margin-bottom:15px; font-size:0.9rem;"><?= htmlspecialchars($hata) ?></p>
          <?php endif; ?>

          <button type="submit" class="register-btn" name="registerBtn" id="registerBtn">
            <i class="fa-solid fa-user-plus"></i>
            Kayıt Ol
          </button>
        </form>

        <div class="login-link">
          Zaten hesabın var mı?
          <a href="login.php">Giriş Yap</a>
        </div>
      </div>
    </div>
  </div>

  <script src="assets/js/register.js"></script>
  <script>
    const sifreInput = document.getElementById("password");
    const sifreGucuKutu = document.getElementById("sifreGucuKutu");
    const sifreGucuCubuk = document.getElementById("sifreGucuCubuk");
    const sifreGucuYazi = document.getElementById("sifreGucuYazi");

    if (sifreInput) {
      sifreInput.addEventListener("input", () => {
        const deger = sifreInput.value;
        if (deger.length === 0) {
          sifreGucuKutu.style.display = "none";
          return;
        }
        sifreGucuKutu.style.display = "block";

        let puan = 0;
        if (deger.length >= 6) puan++;
        if (deger.length >= 10) puan++;
        if (/[A-Z]/.test(deger)) puan++;
        if (/[0-9]/.test(deger)) puan++;
        if (/[^A-Za-z0-9]/.test(deger)) puan++;

        const seviyeler = [
          { yuzde: 20, renk: "#ef4444", yazi: "Çok zayıf" },
          { yuzde: 40, renk: "#f97316", yazi: "Zayıf" },
          { yuzde: 60, renk: "#eab308", yazi: "Orta" },
          { yuzde: 80, renk: "#22c55e", yazi: "İyi" },
          { yuzde: 100, renk: "#16a34a", yazi: "Güçlü" },
        ];
        const seviye = seviyeler[Math.min(puan, 5) - 1] || seviyeler[0];
        sifreGucuCubuk.style.width = seviye.yuzde + "%";
        sifreGucuCubuk.style.background = seviye.renk;
        sifreGucuYazi.textContent = "Şifre gücü: " + seviye.yazi;
        sifreGucuYazi.style.color = seviye.renk;
      });
    }
  </script>
</body>
</html>
