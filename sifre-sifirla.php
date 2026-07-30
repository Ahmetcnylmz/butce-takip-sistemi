<?php
require "config.php";
require "includes/fonksiyonlar.php";

if (isset($_SESSION['kullanici_id'])) {
    header("Location: index.php");
    exit;
}

$kullaniciId = $_SESSION['sifirlama_bekleyen_id'] ?? 0;
$hata = "";
$basari = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfDogrula();
    botKontrolEt();

    $girilenKod = trim($_POST['kod'] ?? '');
    $yeniSifre = $_POST['yeni_sifre'] ?? '';
    $yeniSifreTekrar = $_POST['yeni_sifre_tekrar'] ?? '';

    $sorgu = $baglanti->prepare("SELECT * FROM kullanicilar WHERE id = ? AND sifirlama_kodu = ?");
    $sorgu->bind_param("is", $kullaniciId, $girilenKod);
    $sorgu->execute();
    $kullanici = $sorgu->get_result()->fetch_assoc();

    if (!$kullanici || $girilenKod === '') {
        $hata = "Kod yanlış. Lütfen tekrar dene.";
    } elseif (strtotime($kullanici['sifirlama_son_tarih']) < time()) {
        $hata = "Kodun süresi dolmuş. Lütfen yeni bir kod iste.";
    } elseif (strlen($yeniSifre) < 6) {
        $hata = "Yeni şifre en az 6 karakter olmalı.";
    } elseif ($yeniSifre !== $yeniSifreTekrar) {
        $hata = "Şifreler birbiriyle eşleşmiyor.";
    } else {
        $yeniHash = password_hash($yeniSifre, PASSWORD_DEFAULT);
        $guncelle = $baglanti->prepare("UPDATE kullanicilar SET sifre = ?, sifirlama_kodu = NULL, sifirlama_son_tarih = NULL WHERE id = ?");
        $guncelle->bind_param("si", $yeniHash, $kullaniciId);
        $guncelle->execute();
        unset($_SESSION['sifirlama_bekleyen_id']);
        $basari = "Şifren güncellendi. Şimdi giriş yapabilirsin.";
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Şifre Sıfırla</title>
  <link rel="icon" type="image/svg+xml" href="logo/favicon.svg">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
  <link rel="stylesheet" href="css/sifirlama.css">
</head>
<body>
  <div class="sifirla-kutu">
    <i class="fa-solid fa-lock baslik-ikon"></i>
    <h2>Yeni Şifre Belirle</h2>
    <p>E-postana gönderilen 6 haneli kodu ve yeni şifreni gir.</p>

    <?php if ($hata !== "") : ?>
      <p class="sifirla-mesaj hata"><?= htmlspecialchars($hata) ?></p>
    <?php endif; ?>
    <?php if ($basari !== "") : ?>
      <p class="sifirla-mesaj basari"><?= htmlspecialchars($basari) ?></p>
    <?php endif; ?>

    <?php if ($basari === "") : ?>
    <form method="post" action="sifre-sifirla.php">
      <?php csrfAlanYaz(); ?>
      <?php botAlaniYaz(); ?>
      <label for="kod">Doğrulama Kodu</label>
      <input type="text" id="kod" name="kod" maxlength="6" placeholder="------" inputmode="numeric" required>

      <label for="yeni_sifre">Yeni Şifre</label>
      <input type="password" id="yeni_sifre" name="yeni_sifre" placeholder="En az 6 karakter" required>

      <label for="yeni_sifre_tekrar">Yeni Şifre (Tekrar)</label>
      <input type="password" id="yeni_sifre_tekrar" name="yeni_sifre_tekrar" required>

      <button type="submit">Şifreyi Güncelle</button>
    </form>
    <a href="sifremi-unuttum.php" class="sifirla-alt-link">Kod gelmedi mi? Tekrar iste</a>
    <?php else : ?>
    <a href="login.php" class="sifirla-alt-link">Giriş sayfasına git</a>
    <?php endif; ?>
  </div>
</body>
</html>
