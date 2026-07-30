<?php
// Her korumalı sayfanın başında $aktifSayfa ve $sayfaBasligi tanımlanıp bu dosya include edilir

if (!isset($_SESSION['kullanici_id'])) {
    header("Location: login.php");
    exit;
}

$toplamButceDeger = toplamButce($baglanti, $_SESSION['kullanici_id']);
$yaklasanHatirlaticilar = yaklasanHatirlaticilariGetir($baglanti, $_SESSION['kullanici_id'], 5);
$mevcutSayfaUrl = basename($_SERVER['PHP_SELF']) . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '');
tekrarlayanHarcamalariUygula($baglanti, $_SESSION['kullanici_id']);
abonelikleriIlerlet($baglanti, $_SESSION['kullanici_id']);

// E-postası doğrulanmış kullanıcılara, 5 gün içindeki hatırlatıcılar için
// (daha önce gönderilmemişse) bir kez e-posta gönder
if (($_SESSION['dogrulandi'] ?? 0) == 1) {
    foreach ($yaklasanHatirlaticilar as $h) {
        if ($h['eposta_gonderildi'] == 0) {
            $fark = gunFarkiHesapla($h['tarih']);
            hatirlaticiMailGonder($_SESSION['email'], $h['baslik'], $h['tarih'], $fark);
            $guncelle = $baglanti->prepare("UPDATE hatirlaticilar SET eposta_gonderildi = 1 WHERE id = ?");
            $guncelle->bind_param("i", $h['id']);
            $guncelle->execute();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bütçe Takip Sistemi<?= isset($sayfaBasligi) ? " - " . htmlspecialchars($sayfaBasligi) : "" ?></title>
  <link rel="icon" type="image/svg+xml" href="logo/favicon.svg">

  <!-- PWA: uygulama olarak yüklenebilir / masaüstüne eklenebilir olması için -->
  <link rel="manifest" href="manifest.json">
  <meta name="theme-color" content="#4f46e5">
  <link rel="apple-touch-icon" href="logo/pwa/apple-touch-icon.png">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="Bütçe Takip">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="css/index.css">
  <link rel="stylesheet" href="css/ortak.css">
  <?php if (!empty($ekstraCss)) foreach ($ekstraCss as $dosya) : ?>
  <link rel="stylesheet" href="<?= $dosya ?>">
  <?php endforeach; ?>
  <script>
    // CSS yüklenmeden önce kayıtlı tema varsa hemen uygula (ekranın kısa süre
    // açık renkte parlayıp sonra kararmasını önler)
    if (localStorage.getItem("tema") === "karanlik") {
      document.documentElement.setAttribute("data-tema", "karanlik");
    }
  </script>
</head>
<body>

  <div class="sidebar" id="sidebarEl">
    <div class="sidebar-top">
      <div class="sidebar-brand">
        <a href="index.php" class="sidebar-title-link">
          <span class="brand-mark"><img src="logo/icon.svg" alt="" width="34" height="34"></span>
          <span>Bütçe Takip</span>
        </a>
      </div>

      <p class="nav-label">Menü</p>
      <ul>
        <li class="<?= $aktifSayfa === 'index' ? 'active' : '' ?>">
          <a href="index.php">
            <span class="nav-icon nav-icon-home"><i class="fa-solid fa-house-user"></i></span>
            <span>Ana Sayfa</span>
          </a>
        </li>
        <li class="<?= $aktifSayfa === 'abonelikler' ? 'active' : '' ?>">
          <a href="abonelikler.php">
            <span class="nav-icon nav-icon-sub"><i class="fa-solid fa-rotate"></i></span>
            <span>Abonelikler</span>
          </a>
        </li>
        <li class="<?= $aktifSayfa === 'genel-gider' ? 'active' : '' ?>">
          <a href="genel-gider.php">
            <span class="nav-icon nav-icon-home2"><i class="fa-solid fa-house"></i></span>
            <span>Genel Gider</span>
          </a>
        </li>
        <li class="<?= $aktifSayfa === 'hatirlatici' ? 'active' : '' ?>">
          <a href="hatirlatici.php">
            <span class="nav-icon nav-icon-bell"><i class="fa-solid fa-bell"></i></span>
            <span>Hatırlatıcılar</span>
          </a>
        </li>
        <li class="<?= $aktifSayfa === 'borc-alacak' ? 'active' : '' ?>">
          <a href="borc-alacak.php">
            <span class="nav-icon nav-icon-debt"><i class="fa-solid fa-credit-card"></i></span>
            <span>Borç/Alacak</span>
          </a>
        </li>
        <li class="<?= $aktifSayfa === 'yillik-ozet' ? 'active' : '' ?>">
          <a href="yillik-ozet.php">
            <span class="nav-icon nav-icon-home2"><i class="fa-solid fa-calendar-days"></i></span>
            <span>Yıllık Özet</span>
          </a>
        </li>
        <?php if (($_SESSION['rol'] ?? '') === 'admin') : ?>
        <li class="<?= $aktifSayfa === 'admin' ? 'active' : '' ?>">
          <a href="admin.php">
            <span class="nav-icon nav-icon-debt"><i class="fa-solid fa-user-shield"></i></span>
            <span>Yönetim Paneli</span>
          </a>
        </li>
        <?php endif; ?>
      </ul>
    </div>

    <div class="sidebar-actions">
      <button type="button" class="btn-side btn-yukle-side" id="uygulamaYukleBtn" style="display:none;">
        <i class="fa-solid fa-download"></i> Uygulamayı Yükle
      </button>
      <a href="hesap.php" class="btn-side btn-side-account <?= $aktifSayfa === 'hesap' ? 'active' : '' ?>">
        <i class="fa-solid fa-user-gear"></i> Hesap
      </a>
    </div>
  </div>

  <!-- Kenar çubuğu mobilde açıkken arkayı karartan katman; tıklanınca menü kapanır -->
  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <div class="topbar">
    <button type="button" class="menu-toggle-btn" id="menuToggleBtn" aria-label="Menüyü aç" aria-expanded="false">
      <i class="fa-solid fa-bars"></i>
    </button>

    <div class="topbar-heading">
      <h1><?= htmlspecialchars($sayfaBasligi ?? 'Genel bakış') ?></h1>
      <p id="todayDate"><?= turkceTarih() ?></p>
    </div>

    <div class="topbar-butce-rozeti <?= $toplamButceDeger < 0 ? 'negatif' : '' ?>" title="Aylık gelir - abonelik, genel gider ve borç taksitleri">
      <span class="topbar-butce-icon"><i class="fa-solid fa-wallet"></i></span>
      <div>
        <span class="topbar-butce-label">Toplam bütçen</span>
        <strong class="topbar-butce-value"><?= paraFormatla($toplamButceDeger) ?></strong>
      </div>
    </div>

    <div class="topbar-actions">
      <button type="button" class="bildirim-zil" id="temaDegistirBtn" title="Karanlık/Aydınlık mod">
        <i class="fa-solid fa-moon" id="temaIkon"></i>
      </button>

      <div class="bildirim-alani">
        <button type="button" class="bildirim-zil" id="bildirimZilBtn" title="Yaklaşan hatırlatıcılar">
          <i class="fa-solid fa-bell"></i>
          <?php if (count($yaklasanHatirlaticilar) > 0) : ?>
            <span class="bildirim-rozet"><?= count($yaklasanHatirlaticilar) ?></span>
          <?php endif; ?>
        </button>

        <div class="bildirim-panel" id="bildirimPanel">
          <div class="bildirim-panel-baslik">
            <span>Yaklaşan Hatırlatıcılar</span>
            <small>Önümüzdeki 5 gün</small>
          </div>
          <div class="bildirim-panel-liste">
            <?php if (empty($yaklasanHatirlaticilar)) : ?>
              <p class="bildirim-bos"><i class="fa-solid fa-circle-check"></i> Önümüzdeki 5 günde hatırlatıcın yok.</p>
            <?php endif; ?>
            <?php foreach ($yaklasanHatirlaticilar as $bh) :
                $bhFark = gunFarkiHesapla($bh['tarih']);
                $bhMetin = $bhFark === 0 ? "Bugün" : "$bhFark gün kaldı"; ?>
              <div class="bildirim-item">
                <span class="bildirim-tarih-rozet"><?= date("j M", strtotime($bh['tarih'])) ?></span>
                <div class="bildirim-bilgi">
                  <strong><?= htmlspecialchars($bh['baslik']) ?></strong>
                  <small><?= $bhMetin ?></small>
                </div>
                <form method="post" action="bildirim-sil.php">
                <?php csrfAlanYaz(); ?>
                  <input type="hidden" name="hatirlatici_id" value="<?= $bh['id'] ?>">
                  <input type="hidden" name="donus" value="<?= htmlspecialchars($mevcutSayfaUrl) ?>">
                  <button type="submit" class="bildirim-sil" title="Sil"><i class="fa-solid fa-trash"></i></button>
                </form>
              </div>
            <?php endforeach; ?>
          </div>
          <a href="hatirlatici.php" class="bildirim-panel-link">Tüm hatırlatıcıları gör</a>
        </div>
      </div>

      <form action="cikis.php" method="post" style="display:inline;">
        <button type="submit" class="logout"><i class="fa-solid fa-right-from-bracket"></i> <span class="logout-text">Çıkış Yap</span></button>
      </form>
    </div>
  </div>

  <div class="content" id="mainContent">
