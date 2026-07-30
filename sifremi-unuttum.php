<?php
require "config.php";
require "includes/fonksiyonlar.php";

if (isset($_SESSION['kullanici_id'])) {
    header("Location: index.php");
    exit;
}

$hata = "";
$basari = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfDogrula();
    botKontrolEt();

    $email = trim($_POST['email'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $hata = "Lütfen geçerli bir e-posta adresi gir.";
    } else {
        $sorgu = $baglanti->prepare("SELECT id FROM kullanicilar WHERE email = ?");
        $sorgu->bind_param("s", $email);
        $sorgu->execute();
        $kullanici = $sorgu->get_result()->fetch_assoc();

        // Kullanıcı bulunamasa bile aynı mesajı gösteriyoruz;
        // böylece hangi e-postaların kayıtlı olduğu dışarıdan anlaşılamaz.
        if ($kullanici) {
            $kod = (string)random_int(100000, 999999);
            $sonTarih = date("Y-m-d H:i:s", strtotime("+30 minutes"));
            $guncelle = $baglanti->prepare("UPDATE kullanicilar SET sifirlama_kodu = ?, sifirlama_son_tarih = ? WHERE id = ?");
            $guncelle->bind_param("ssi", $kod, $sonTarih, $kullanici['id']);
            $guncelle->execute();
            sifirlamaKoduGonder($email, $kod);
            $_SESSION['sifirlama_bekleyen_id'] = $kullanici['id'];
        }

        header("Location: sifre-sifirla.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Şifremi Unuttum</title>
  <link rel="icon" type="image/svg+xml" href="logo/favicon.svg">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
  <link rel="stylesheet" href="css/sifirlama.css">
</head>
<body>
  <div class="sifirla-kutu">
    <i class="fa-solid fa-key baslik-ikon"></i>
    <h2>Şifremi Unuttum</h2>
    <p>Kayıtlı e-posta adresini gir, sana bir sıfırlama kodu gönderelim.</p>

    <?php if ($hata !== "") : ?>
      <p class="sifirla-mesaj hata"><?= htmlspecialchars($hata) ?></p>
    <?php endif; ?>

    <form method="post" action="sifremi-unuttum.php">
      <?php csrfAlanYaz(); ?>
      <?php botAlaniYaz(); ?>
      <label for="email">E-posta</label>
      <input type="email" id="email" name="email" placeholder="ornek@mail.com" required>
      <button type="submit">Kod Gönder</button>
    </form>

    <a href="login.php" class="sifirla-alt-link">Giriş sayfasına dön</a>
  </div>
</body>
</html>
