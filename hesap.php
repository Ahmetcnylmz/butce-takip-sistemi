<?php
require "config.php";
require "includes/fonksiyonlar.php";
girisSartiKontrolEt();

$kullaniciId = $_SESSION['kullanici_id'];
$basariMesaji = "";
$hataMesaji = "";

$oncekiSorgu = $baglanti->prepare("SELECT * FROM kullanicilar WHERE id = ?");
$oncekiSorgu->bind_param("i", $kullaniciId);
$oncekiSorgu->execute();
$kullaniciBilgiOncesi = $oncekiSorgu->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfDogrula();

    // Aylık gelir güncelleme
    if (isset($_POST['gelirKaydet'])) {
        $yeniGelir = (float)str_replace(',', '.', $_POST['gelir']);
        if ($yeniGelir >= 0) {
            $guncelle = $baglanti->prepare("UPDATE kullanicilar SET aylik_gelir = ? WHERE id = ?");
            $guncelle->bind_param("di", $yeniGelir, $kullaniciId);
            $guncelle->execute();
            $basariMesaji = "Aylık gelir güncellendi.";
        }
    }

    // Aylık gider hedefi güncelleme
    if (isset($_POST['hedefKaydet'])) {
        $yeniHedef = (float)str_replace(',', '.', $_POST['hedef']);
        if ($yeniHedef >= 0) {
            $guncelle = $baglanti->prepare("UPDATE kullanicilar SET aylik_gider_hedefi = ? WHERE id = ?");
            $guncelle->bind_param("di", $yeniHedef, $kullaniciId);
            $guncelle->execute();
            $basariMesaji = "Aylık gider hedefin güncellendi.";
        }
    }

    // Profil bilgileri güncelleme
    if (isset($_POST['profilKaydet'])) {
        $adSoyad = trim($_POST['ad_soyad']);
        $email = trim($_POST['email']);
        if ($adSoyad === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $hataMesaji = "Lütfen geçerli bir ad soyad ve e-posta gir.";
        } else {
            $kontrol = $baglanti->prepare("SELECT id FROM kullanicilar WHERE email = ? AND id != ?");
            $kontrol->bind_param("si", $email, $kullaniciId);
            $kontrol->execute();
            if ($kontrol->get_result()->num_rows > 0) {
                $hataMesaji = "Bu e-posta başka bir hesap tarafından kullanılıyor.";
            } else {
                // E-posta değiştiyse doğrulama sıfırlanır, tekrar doğrulamak gerekir
                $emailDegisti = $email !== $kullaniciBilgiOncesi['email'];
                if ($emailDegisti) {
                    $guncelle = $baglanti->prepare("UPDATE kullanicilar SET ad_soyad = ?, email = ?, dogrulandi = 0, dogrulama_kodu = NULL WHERE id = ?");
                    $_SESSION['dogrulandi'] = 0;
                } else {
                    $guncelle = $baglanti->prepare("UPDATE kullanicilar SET ad_soyad = ?, email = ? WHERE id = ?");
                }
                $guncelle->bind_param("ssi", $adSoyad, $email, $kullaniciId);
                $guncelle->execute();
                $_SESSION['ad_soyad'] = $adSoyad;
                $_SESSION['email'] = $email;
                $basariMesaji = $emailDegisti
                    ? "Profil bilgilerin güncellendi. Yeni e-postanı tekrar doğrulaman gerekiyor."
                    : "Profil bilgilerin güncellendi.";
            }
        }
    }

    // E-posta doğrulama kodu gönder
    if (isset($_POST['dogrulamaKoduGonder'])) {
        $kod = (string)random_int(100000, 999999);
        $guncelle = $baglanti->prepare("UPDATE kullanicilar SET dogrulama_kodu = ? WHERE id = ?");
        $guncelle->bind_param("si", $kod, $kullaniciId);
        $guncelle->execute();
        dogrulamaKoduGonder($_SESSION['email'], $kod);
        $basariMesaji = "Doğrulama kodu e-postana gönderildi.";
    }

    // Girilen doğrulama kodunu kontrol et
    if (isset($_POST['dogrulamaKoduKontrolEt'])) {
        $girilenKod = trim($_POST['kod'] ?? '');
        $sorgu = $baglanti->prepare("SELECT dogrulama_kodu FROM kullanicilar WHERE id = ?");
        $sorgu->bind_param("i", $kullaniciId);
        $sorgu->execute();
        $kayitliKod = $sorgu->get_result()->fetch_assoc()['dogrulama_kodu'];

        if ($girilenKod !== '' && $girilenKod === $kayitliKod) {
            $guncelle = $baglanti->prepare("UPDATE kullanicilar SET dogrulandi = 1, dogrulama_kodu = NULL WHERE id = ?");
            $guncelle->bind_param("i", $kullaniciId);
            $guncelle->execute();
            $_SESSION['dogrulandi'] = 1;
            $basariMesaji = "E-postan doğrulandı! Artık hatırlatıcı bildirimlerini e-postana da alacaksın.";
        } else {
            $hataMesaji = "Girdiğin kod yanlış.";
        }
    }

    // Şifre değiştirme
    if (isset($_POST['sifreKaydet'])) {
        $mevcutSifre = $_POST['mevcut_sifre'];
        $yeniSifre = $_POST['yeni_sifre'];
        $yeniSifreTekrar = $_POST['yeni_sifre_tekrar'];

        $sorgu = $baglanti->prepare("SELECT sifre FROM kullanicilar WHERE id = ?");
        $sorgu->bind_param("i", $kullaniciId);
        $sorgu->execute();
        $mevcutHash = $sorgu->get_result()->fetch_assoc()['sifre'];

        if (!password_verify($mevcutSifre, $mevcutHash)) {
            $hataMesaji = "Mevcut şifren yanlış.";
        } elseif (strlen($yeniSifre) < 6) {
            $hataMesaji = "Yeni şifre en az 6 karakter olmalı.";
        } elseif ($yeniSifre !== $yeniSifreTekrar) {
            $hataMesaji = "Yeni şifreler eşleşmiyor.";
        } else {
            $yeniHash = password_hash($yeniSifre, PASSWORD_DEFAULT);
            $guncelle = $baglanti->prepare("UPDATE kullanicilar SET sifre = ? WHERE id = ?");
            $guncelle->bind_param("si", $yeniHash, $kullaniciId);
            $guncelle->execute();
            $basariMesaji = "Şifren başarıyla güncellendi.";
        }
    }
}

$kullanici = $baglanti->prepare("SELECT * FROM kullanicilar WHERE id = ?");
$kullanici->bind_param("i", $kullaniciId);
$kullanici->execute();
$kullaniciBilgi = $kullanici->get_result()->fetch_assoc();

$gelir = (float)$kullaniciBilgi['aylik_gelir'];
$hedef = (float)$kullaniciBilgi['aylik_gider_hedefi'];
$gider = round(abonelikAylikToplam($baglanti, $kullaniciId) + evMarketAylikToplam($baglanti, $kullaniciId) + borcAylikTaksitToplami($baglanti, $kullaniciId));

$aktifSayfa = "hesap";
$sayfaBasligi = "Hesap Ayarları";
require "includes/ust-kisim.php";
?>

    <?php if ($basariMesaji) : ?>
      <p style="max-width:600px; margin:0 auto 16px; color:var(--success); font-size:0.9rem;"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($basariMesaji) ?></p>
    <?php endif; ?>
    <?php if ($hataMesaji) : ?>
      <p style="max-width:600px; margin:0 auto 16px; color:var(--danger); font-size:0.9rem;"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($hataMesaji) ?></p>
    <?php endif; ?>

    <div class="dynamic-form-card reveal">
      <h2><i class="fa-solid fa-sack-dollar"></i> Aylık Gelir</h2>
      <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:10px;">
        Buraya girdiğin tutar; ana sayfadaki ve tüm sayfaların üst kısmındaki
        "toplam bütçe" hesaplamasında gelir olarak kullanılır.
      </p>
      <form method="post" action="hesap.php">
      <?php csrfAlanYaz(); ?>
        <input type="number" name="gelir" min="0" step="0.01" value="<?= $gelir ?>" placeholder="ör. 14500" required>
        <button type="submit" name="gelirKaydet" class="btn-submit-income"><i class="fa-solid fa-floppy-disk"></i> Kaydet</button>
      </form>
    </div>

    <div class="dynamic-form-card reveal">
      <h2><i class="fa-solid fa-bullseye"></i> Aylık Gider Hedefi</h2>
      <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:10px;">
        Toplam giderin bu tutarı geçerse ana sayfada uyarı gösterilir.
      </p>
      <form method="post" action="hesap.php">
      <?php csrfAlanYaz(); ?>
        <input type="number" name="hedef" min="0" step="0.01" value="<?= $hedef ?>" placeholder="ör. 10000" required>
        <button type="submit" name="hedefKaydet" class="btn-submit-income"><i class="fa-solid fa-floppy-disk"></i> Kaydet</button>
      </form>
    </div>

    <div class="chart-section-wrapper reveal" style="max-width:600px; margin:20px auto 0;">
      <div class="section-head">
        <h2><i class="fa-solid fa-chart-pie"></i> Aylık Özet</h2>
      </div>
      <div class="summary-side" style="justify-content: space-between;">
        <div class="summary-stat">
          <span class="stat-icon stat-icon-income"><i class="fa-solid fa-arrow-up"></i></span>
          <div>
            <span class="summary-stat-label">Aylık gelir</span>
            <p class="summary-stat-value"><?= paraFormatla($gelir) ?></p>
          </div>
        </div>
        <div class="summary-stat">
          <span class="stat-icon stat-icon-expense"><i class="fa-solid fa-arrow-down"></i></span>
          <div>
            <span class="summary-stat-label">Abonelik + Genel Gider + Taksit</span>
            <p class="summary-stat-value"><?= paraFormatla($gider) ?></p>
          </div>
        </div>
      </div>
    </div>

    <div class="dynamic-form-card reveal">
      <h2><i class="fa-solid fa-user-pen"></i> Profil Bilgileri</h2>
      <form method="post" action="hesap.php">
      <?php csrfAlanYaz(); ?>
        <label style="font-size:0.8rem; font-weight:600; color:var(--text-muted);">Ad Soyad</label>
        <input type="text" name="ad_soyad" value="<?= htmlspecialchars($kullaniciBilgi['ad_soyad']) ?>" required>

        <label style="font-size:0.8rem; font-weight:600; color:var(--text-muted);">E-posta</label>
        <input type="email" name="email" value="<?= htmlspecialchars($kullaniciBilgi['email']) ?>" required>

        <label style="font-size:0.8rem; font-weight:600; color:var(--text-muted);">Kullanıcı adı</label>
        <input type="text" value="<?= htmlspecialchars($kullaniciBilgi['kullanici_adi']) ?>" disabled style="opacity:.6;">

        <button type="submit" name="profilKaydet" class="btn-submit-primary"><i class="fa-solid fa-floppy-disk"></i> Profili Kaydet</button>
      </form>
    </div>

    <div class="dynamic-form-card reveal">
      <h2><i class="fa-solid fa-envelope-circle-check"></i> E-posta Doğrulama</h2>
      <?php if ((int)$kullaniciBilgi['dogrulandi'] === 1) : ?>
        <p style="color:var(--success); font-weight:600; font-size:0.9rem;"><i class="fa-solid fa-circle-check"></i> E-postan doğrulanmış. Hatırlatıcı bildirimlerin e-postana da gidiyor.</p>
      <?php else : ?>
        <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:12px;">
          E-postanı doğrularsan, hatırlatıcıların yaklaşınca (son 5 gün içinde) e-postana da bildirim gönderilir.
        </p>
        <form method="post" action="hesap.php" style="margin-bottom:12px;">
          <?php csrfAlanYaz(); ?>
          <button type="submit" name="dogrulamaKoduGonder" class="btn-submit-primary" style="width:auto; padding:11px 18px;"><i class="fa-solid fa-paper-plane"></i> Doğrulama Kodu Gönder</button>
        </form>
        <form method="post" action="hesap.php" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
          <?php csrfAlanYaz(); ?>
          <div style="flex:1; min-width:140px;">
            <label style="font-size:0.8rem; font-weight:600; color:var(--text-muted);">Gelen Kod</label>
            <input type="text" name="kod" maxlength="6" placeholder="------" inputmode="numeric">
          </div>
          <button type="submit" name="dogrulamaKoduKontrolEt" class="btn-submit-primary" style="width:auto; padding:11px 18px;">Doğrula</button>
        </form>
      <?php endif; ?>
    </div>

    <div class="dynamic-form-card reveal">
      <h2><i class="fa-solid fa-key"></i> Şifre Değiştir</h2>
      <form method="post" action="hesap.php">
      <?php csrfAlanYaz(); ?>
        <label style="font-size:0.8rem; font-weight:600; color:var(--text-muted);">Mevcut şifre</label>
        <input type="password" name="mevcut_sifre" required>

        <label style="font-size:0.8rem; font-weight:600; color:var(--text-muted);">Yeni şifre</label>
        <input type="password" name="yeni_sifre" required>

        <label style="font-size:0.8rem; font-weight:600; color:var(--text-muted);">Yeni şifre (tekrar)</label>
        <input type="password" name="yeni_sifre_tekrar" required>

        <button type="submit" name="sifreKaydet" class="btn-submit-primary"><i class="fa-solid fa-key"></i> Şifreyi Güncelle</button>
      </form>
    </div>

<?php require "includes/alt-kisim.php"; ?>
