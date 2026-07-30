<?php
require "config.php";
require "includes/fonksiyonlar.php";
girisSartiKontrolEt();

$kullaniciId = $_SESSION['kullanici_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfDogrula();

    if (isset($_POST['hatirlaticiEkle'])) {
        $baslik = trim($_POST['baslik']);
        $tarih = $_POST['tarih'];
        $tip = in_array($_POST['tip'], ['odeme', 'fatura', 'genel']) ? $_POST['tip'] : 'genel';
        if ($baslik !== '' && $tarih) {
            $ekle = $baglanti->prepare("INSERT INTO hatirlaticilar (kullanici_id, baslik, tarih, tip) VALUES (?, ?, ?, ?)");
            $ekle->bind_param("isss", $kullaniciId, $baslik, $tarih, $tip);
            $ekle->execute();
        }
        header("Location: hatirlatici.php");
        exit;
    }

    if (isset($_POST['hatirlaticiGuncelle'])) {
        $id = (int)$_POST['hatirlatici_id'];
        $baslik = trim($_POST['baslik']);
        $tarih = $_POST['tarih'];
        $tip = in_array($_POST['tip'], ['odeme', 'fatura', 'genel']) ? $_POST['tip'] : 'genel';
        if ($baslik !== '' && $tarih) {
            $guncelle = $baglanti->prepare("UPDATE hatirlaticilar SET baslik = ?, tarih = ?, tip = ?, eposta_gonderildi = 0 WHERE id = ? AND kullanici_id = ?");
            $guncelle->bind_param("sssii", $baslik, $tarih, $tip, $id, $kullaniciId);
            $guncelle->execute();
        }
        header("Location: hatirlatici.php");
        exit;
    }

    if (isset($_POST['hatirlaticiSil'])) {
        $id = (int)$_POST['hatirlatici_id'];
        $sil = $baglanti->prepare("DELETE FROM hatirlaticilar WHERE id = ? AND kullanici_id = ?");
        $sil->bind_param("ii", $id, $kullaniciId);
        $sil->execute();
        header("Location: hatirlatici.php");
        exit;
    }
}

$tipEtiket = ["odeme" => "Ödeme", "fatura" => "Fatura", "genel" => "Genel"];
$tumHatirlaticilar = hatirlaticilariGetir($baglanti, $kullaniciId);

// Düzenlenmek istenen hatırlatıcı var mı? (?duzenle=ID)
$duzenlenecekHatirlatici = null;
if (isset($_GET['duzenle'])) {
    $duzenleId = (int)$_GET['duzenle'];
    foreach ($tumHatirlaticilar as $h) {
        if ($h['id'] == $duzenleId) { $duzenlenecekHatirlatici = $h; break; }
    }
}

// Mini takvim (görüntülenen ay, ?ay=2026-07 ile gezilebilir)
$gorunenAy = $_GET['ay'] ?? date("Y-m");
$zamanDamgasi = strtotime($gorunenAy . "-01");
$onceki = date("Y-m", strtotime("-1 month", $zamanDamgasi));
$sonraki = date("Y-m", strtotime("+1 month", $zamanDamgasi));
$aylikTurkce = ["","Ocak","Şubat","Mart","Nisan","Mayıs","Haziran","Temmuz","Ağustos","Eylül","Ekim","Kasım","Aralık"];
$baslikAyYil = $aylikTurkce[(int)date("n", $zamanDamgasi)] . " " . date("Y", $zamanDamgasi);

$gunlerVar = [];
foreach ($tumHatirlaticilar as $h) {
    if (date("Y-m", strtotime($h['tarih'])) === $gorunenAy) {
        $gunlerVar[(int)date("j", strtotime($h['tarih']))] = true;
    }
}
$ayinIlkGunu = (int)date("N", $zamanDamgasi); // 1 (Pzt) - 7 (Paz)
$ayinGunSayisi = (int)date("t", $zamanDamgasi);
$bugunKodu = date("Y-m-d");

// Takvimde bir güne tıklanmışsa (?gun=2026-07-16) sadece o günün hatırlatıcılarını göster
$seciliGun = $_GET['gun'] ?? null;
if ($seciliGun) {
    $gosterilecekListe = array_values(array_filter($tumHatirlaticilar, fn($h) => $h['tarih'] === $seciliGun));
} else {
    $gosterilecekListe = $tumHatirlaticilar;
}

$aktifSayfa = "hatirlatici";
$sayfaBasligi = "Hatırlatıcılar";
require "includes/ust-kisim.php";
?>

    <div class="chart-section-wrapper reveal" id="hatirlaticiFormAlan">
      <div class="section-head">
        <h2><i class="fa-solid <?= $duzenlenecekHatirlatici ? 'fa-pen' : 'fa-bell' ?>"></i> <?= $duzenlenecekHatirlatici ? 'Hatırlatıcıyı Düzenle' : 'Yeni Hatırlatıcı' ?></h2>
        <?php if ($duzenlenecekHatirlatici) : ?>
          <a href="hatirlatici.php" class="btn-kategori-ekle"><i class="fa-solid fa-xmark"></i> Vazgeç</a>
        <?php endif; ?>
      </div>

      <form class="hatirlatma-form" method="post" action="hatirlatici.php">
      <?php csrfAlanYaz(); ?>
        <?php if ($duzenlenecekHatirlatici) : ?>
          <input type="hidden" name="hatirlatici_id" value="<?= $duzenlenecekHatirlatici['id'] ?>">
        <?php endif; ?>
        <input type="text" name="baslik" placeholder="ör. Kira ödemesi" value="<?= htmlspecialchars($duzenlenecekHatirlatici['baslik'] ?? '') ?>" required>
        <input type="date" name="tarih" value="<?= $duzenlenecekHatirlatici['tarih'] ?? ($seciliGun ?? '') ?>" required>
        <select name="tip">
          <option value="odeme" <?= ($duzenlenecekHatirlatici && $duzenlenecekHatirlatici['tip'] === 'odeme') ? 'selected' : '' ?>>Ödeme</option>
          <option value="fatura" <?= ($duzenlenecekHatirlatici && $duzenlenecekHatirlatici['tip'] === 'fatura') ? 'selected' : '' ?>>Fatura</option>
          <option value="genel" <?= (!$duzenlenecekHatirlatici || $duzenlenecekHatirlatici['tip'] === 'genel') ? 'selected' : '' ?>>Genel</option>
        </select>
        <button type="submit" name="<?= $duzenlenecekHatirlatici ? 'hatirlaticiGuncelle' : 'hatirlaticiEkle' ?>"><i class="fa-solid <?= $duzenlenecekHatirlatici ? 'fa-floppy-disk' : 'fa-plus' ?>"></i> <?= $duzenlenecekHatirlatici ? 'Kaydet' : 'Ekle' ?></button>
      </form>

      <div class="hatirlatici-grid">
        <div>
          <div class="mini-takvim-baslik">
            <a href="hatirlatici.php?ay=<?= $onceki ?>" aria-label="Önceki ay"><i class="fa-solid fa-chevron-left"></i></a>
            <span><?= $baslikAyYil ?></span>
            <a href="hatirlatici.php?ay=<?= $sonraki ?>" aria-label="Sonraki ay"><i class="fa-solid fa-chevron-right"></i></a>
          </div>
          <div class="mini-takvim">
            <?php foreach (["Pt","Sa","Ça","Pe","Cu","Ct","Pz"] as $g) : ?>
              <span class="gun-adi"><?= $g ?></span>
            <?php endforeach; ?>

            <?php for ($i = 1; $i < $ayinIlkGunu; $i++) : ?>
              <span class="gun-hucre bos"></span>
            <?php endfor; ?>

            <?php for ($gun = 1; $gun <= $ayinGunSayisi; $gun++) :
                $tarihKodu = $gorunenAy . "-" . str_pad($gun, 2, "0", STR_PAD_LEFT);
                $siniflar = ["gun-hucre"];
                if ($tarihKodu === $bugunKodu) $siniflar[] = "bugun";
                if (isset($gunlerVar[$gun])) $siniflar[] = "hatirlatma-var";
                if ($tarihKodu === $seciliGun) $siniflar[] = "secili"; ?>
              <a href="hatirlatici.php?ay=<?= $gorunenAy ?>&gun=<?= $tarihKodu ?>#hatirlatmaListesi" class="<?= implode(' ', $siniflar) ?>"><?= $gun ?></a>
            <?php endfor; ?>
          </div>
        </div>
        <div>
          <div class="hatirlatma-liste-baslik" id="hatirlatmaListesi">
            <?php if ($seciliGun) : ?>
              <span><i class="fa-solid fa-calendar-day"></i> <?= date("j M Y", strtotime($seciliGun)) ?> hatırlatıcıları</span>
              <a href="hatirlatici.php?ay=<?= $gorunenAy ?>" class="hatirlatma-liste-temizle">Tümünü göster</a>
            <?php else : ?>
              <span><i class="fa-solid fa-list"></i> Tüm hatırlatıcılar</span>
            <?php endif; ?>
          </div>
          <div class="hatirlatma-liste">
            <?php if (empty($gosterilecekListe)) : ?>
              <p class="hatirlatma-bos"><i class="fa-solid fa-calendar-check"></i> <?= $seciliGun ? 'Bu tarihte hatırlatıcı yok.' : 'Henüz hatırlatıcı eklenmedi.' ?></p>
            <?php endif; ?>
            <?php foreach ($gosterilecekListe as $h) :
                $fark = gunFarkiHesapla($h['tarih']);
                $durum = $fark < 0 ? "gecmis" : ($fark <= 3 ? "yakin" : "");
                $durumMetni = $fark === 0 ? "Bugün" : ($fark < 0 ? "Geçti" : "$fark gün kaldı"); ?>
              <div class="hatirlatma-item <?= $durum ?>">
                <span class="hatirlatma-tarih-rozet"><?= date("j M", strtotime($h['tarih'])) ?></span>
                <div class="hatirlatma-bilgi">
                  <strong><?= htmlspecialchars($h['baslik']) ?></strong>
                  <small><?= $tipEtiket[$h['tip']] ?> · <?= $durumMetni ?></small>
                </div>
                <a href="hatirlatici.php?duzenle=<?= $h['id'] ?>#hatirlaticiFormAlan" class="hatirlatma-duzenle" aria-label="Düzenle"><i class="fa-solid fa-pen"></i></a>
                <form method="post" action="hatirlatici.php" onsubmit="return confirm('Silmek istediğine emin misin?');">
                <?php csrfAlanYaz(); ?>
                  <input type="hidden" name="hatirlatici_id" value="<?= $h['id'] ?>">
                  <button type="submit" name="hatirlaticiSil" class="hatirlatma-sil" aria-label="Sil"><i class="fa-solid fa-trash"></i></button>
                </form>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

<?php require "includes/alt-kisim.php"; ?>
