<?php
require "config.php";
require "includes/fonksiyonlar.php";
girisSartiKontrolEt();

$kullaniciId = $_SESSION['kullanici_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfDogrula();

    if (isset($_POST['borcEkle'])) {
        $tip = $_POST['tip'] === 'alacak' ? 'alacak' : 'borc';
        $baslik = trim($_POST['baslik']);
        $tutar = (float)str_replace(',', '.', $_POST['tutar']);
        $vade = $_POST['vade'];
        $taksit = (float)str_replace(',', '.', ($_POST['taksit'] ?: 0));
        if ($baslik !== '' && $tutar > 0 && $vade) {
            $bugun = date("Y-m-d");
            $ekle = $baglanti->prepare("INSERT INTO borclar (kullanici_id, tip, baslik, tutar, aylik_taksit, vade_tarihi, eklenme_tarihi) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $ekle->bind_param("issddss", $kullaniciId, $tip, $baslik, $tutar, $taksit, $vade, $bugun);
            $ekle->execute();
        }
        header("Location: borc-alacak.php");
        exit;
    }

    if (isset($_POST['borcGuncelle'])) {
        $id = (int)$_POST['borc_id'];
        $tip = $_POST['tip'] === 'alacak' ? 'alacak' : 'borc';
        $baslik = trim($_POST['baslik']);
        $tutar = (float)str_replace(',', '.', $_POST['tutar']);
        $vade = $_POST['vade'];
        $taksit = (float)str_replace(',', '.', ($_POST['taksit'] ?: 0));
        if ($baslik !== '' && $tutar > 0 && $vade) {
            $guncelle = $baglanti->prepare("UPDATE borclar SET tip = ?, baslik = ?, tutar = ?, aylik_taksit = ?, vade_tarihi = ? WHERE id = ? AND kullanici_id = ?");
            $guncelle->bind_param("ssddsii", $tip, $baslik, $tutar, $taksit, $vade, $id, $kullaniciId);
            $guncelle->execute();
        }
        header("Location: borc-alacak.php");
        exit;
    }

    if (isset($_POST['borcDurumDegistir'])) {
        $id = (int)$_POST['borc_id'];
        $guncelle = $baglanti->prepare("UPDATE borclar SET odendi = NOT odendi WHERE id = ? AND kullanici_id = ?");
        $guncelle->bind_param("ii", $id, $kullaniciId);
        $guncelle->execute();
        header("Location: borc-alacak.php");
        exit;
    }

    if (isset($_POST['borcSil'])) {
        $id = (int)$_POST['borc_id'];
        $sil = $baglanti->prepare("DELETE FROM borclar WHERE id = ? AND kullanici_id = ?");
        $sil->bind_param("ii", $id, $kullaniciId);
        $sil->execute();
        header("Location: borc-alacak.php");
        exit;
    }
}

$seciliTip = $_GET['tip'] ?? 'borc';

$toplamBorcTutari = toplamBorc($baglanti, $kullaniciId);
$toplamAlacakTutari = toplamAlacak($baglanti, $kullaniciId);
$aylikTaksitToplami = borcAylikTaksitToplami($baglanti, $kullaniciId);
$net = $toplamAlacakTutari - $toplamBorcTutari;

$borclarListesi = borclariGetir($baglanti, $kullaniciId, 'borc');
$alacaklarListesi = borclariGetir($baglanti, $kullaniciId, 'alacak');

// Düzenlenmek istenen kayıt var mı? (?duzenle=ID)
$duzenlenecekBorc = null;
if (isset($_GET['duzenle'])) {
    $duzenleId = (int)$_GET['duzenle'];
    $sorgu = $baglanti->prepare("SELECT * FROM borclar WHERE id = ? AND kullanici_id = ?");
    $sorgu->bind_param("ii", $duzenleId, $kullaniciId);
    $sorgu->execute();
    $duzenlenecekBorc = $sorgu->get_result()->fetch_assoc();
    if ($duzenlenecekBorc) $seciliTip = $duzenlenecekBorc['tip'];
}

$aktifSayfa = "borc-alacak";
$sayfaBasligi = "Borç / Alacak Takibi";
$ekstraCss = ["css/borc-alacak.css"];
require "includes/ust-kisim.php";

function borcKartiCiz($kayit) {
    $fark = gunFarkiHesapla($kayit['vade_tarihi']);
    if ($kayit['odendi']) {
        $vadeDurumu = "Ödendi";
    } elseif ($fark < 0) {
        $vadeDurumu = "Vadesi geçti";
    } elseif ($fark === 0) {
        $vadeDurumu = "Bugün vadesi";
    } else {
        $vadeDurumu = "$fark gün kaldı";
    }
    $vadeTarih = date("d M Y", strtotime($kayit['vade_tarihi']));
    $alacakMi = $kayit['tip'] === 'alacak';
    ?>
    <div class="borc-item <?= $alacakMi ? 'alacak' : '' ?> <?= $kayit['odendi'] ? 'odendi' : '' ?>">
      <span class="borc-icon"><i class="fa-solid <?= $alacakMi ? 'fa-hand-holding-dollar' : 'fa-credit-card' ?>"></i></span>
      <div class="borc-bilgi">
        <strong><?= htmlspecialchars($kayit['baslik']) ?></strong>
        <small><?= $vadeTarih ?> · <?= $vadeDurumu ?><?= $kayit['aylik_taksit'] > 0 ? ' · Aylık taksit: ' . paraFormatla($kayit['aylik_taksit']) : '' ?></small>
      </div>
      <div class="borc-sag">
        <span class="borc-tutar"><?= paraFormatla($kayit['tutar']) ?></span>
        <div class="borc-aksiyon">
          <a href="borc-alacak.php?duzenle=<?= $kayit['id'] ?>#borcFormAlan" class="borc-duzenle" title="Düzenle"><i class="fa-solid fa-pen"></i></a>
          <form method="post" action="borc-alacak.php">
          <?php csrfAlanYaz(); ?>
            <input type="hidden" name="borc_id" value="<?= $kayit['id'] ?>">
            <button type="submit" name="borcDurumDegistir" class="borc-ode" title="<?= $kayit['odendi'] ? 'Ödenmedi olarak işaretle' : 'Ödendi olarak işaretle' ?>">
              <i class="fa-solid <?= $kayit['odendi'] ? 'fa-rotate-left' : 'fa-check' ?>"></i>
            </button>
          </form>
          <form method="post" action="borc-alacak.php" onsubmit="return confirm('Bu kaydı silmek istediğine emin misin?');">
          <?php csrfAlanYaz(); ?>
            <input type="hidden" name="borc_id" value="<?= $kayit['id'] ?>">
            <button type="submit" name="borcSil" class="borc-sil" title="Sil"><i class="fa-solid fa-trash"></i></button>
          </form>
        </div>
      </div>
    </div>
    <?php
}
?>

    <!-- ÖZET KARTI -->
    <div class="summary-card reveal">
      <div class="summary-main">
        <span class="summary-label">Net durum (Alacak - Borç)</span>
        <h1 class="summary-value" style="color: <?= $net < 0 ? 'var(--danger)' : 'var(--heading-color)' ?>"><?= paraFormatla($net) ?></h1>
        <span class="budget-usage-caption"><?= $net < 0 ? 'Borçların alacaklarından fazla.' : 'Alacakların borçlarından fazla ya da eşit.' ?></span>
      </div>
      <div class="summary-side">
        <div class="summary-stat">
          <span class="stat-icon stat-icon-income"><i class="fa-solid fa-hand-holding-dollar"></i></span>
          <div>
            <span class="summary-stat-label">Toplam Alacak</span>
            <p class="summary-stat-value"><?= paraFormatla($toplamAlacakTutari) ?></p>
          </div>
        </div>
        <div class="summary-stat">
          <span class="stat-icon stat-icon-expense"><i class="fa-solid fa-credit-card"></i></span>
          <div>
            <span class="summary-stat-label">Toplam Borç</span>
            <p class="summary-stat-value"><?= paraFormatla($toplamBorcTutari) ?></p>
          </div>
        </div>
        <div class="summary-stat">
          <span class="stat-icon stat-icon-expense"><i class="fa-solid fa-calendar-day"></i></span>
          <div>
            <span class="summary-stat-label">Aylık taksit yükü</span>
            <p class="summary-stat-value"><?= paraFormatla($aylikTaksitToplami) ?></p>
          </div>
        </div>
      </div>
    </div>

    <!-- YENİ KAYIT EKLE -->
    <div class="chart-section-wrapper reveal" id="borcFormAlan">
      <div class="section-head">
        <h2><i class="fa-solid <?= $duzenlenecekBorc ? 'fa-pen' : 'fa-plus' ?>"></i> <?= $duzenlenecekBorc ? 'Kaydı Düzenle' : 'Yeni Borç / Alacak Ekle' ?></h2>
        <?php if ($duzenlenecekBorc) : ?>
          <a href="borc-alacak.php" class="btn-kategori-ekle"><i class="fa-solid fa-xmark"></i> Vazgeç</a>
        <?php endif; ?>
      </div>

      <div class="borc-tip-secim">
        <a href="borc-alacak.php?tip=borc" class="borc-tip-btn <?= $seciliTip === 'borc' ? 'active' : '' ?>"><i class="fa-solid fa-arrow-trend-down"></i> Borcum</a>
        <a href="borc-alacak.php?tip=alacak" class="borc-tip-btn <?= $seciliTip === 'alacak' ? 'active' : '' ?>"><i class="fa-solid fa-arrow-trend-up"></i> Alacağım</a>
      </div>

      <form method="post" action="borc-alacak.php" class="sub-form">
      <?php csrfAlanYaz(); ?>
        <input type="hidden" name="tip" value="<?= $seciliTip ?>">
        <?php if ($duzenlenecekBorc) : ?>
          <input type="hidden" name="borc_id" value="<?= $duzenlenecekBorc['id'] ?>">
        <?php endif; ?>
        <div class="sub-form-row">
          <div class="form-field">
            <label for="borcBaslik">Kişi / Kurum</label>
            <input type="text" id="borcBaslik" name="baslik" placeholder="ör. Kredi Kartı, Ahmet vb." value="<?= htmlspecialchars($duzenlenecekBorc['baslik'] ?? '') ?>" required>
          </div>
          <div class="form-field">
            <label for="borcTutar">Toplam Tutar (₺)</label>
            <input type="number" id="borcTutar" name="tutar" min="0" step="0.01" placeholder="1000" value="<?= $duzenlenecekBorc['tutar'] ?? '' ?>" required>
          </div>
        </div>
        <div class="sub-form-row">
          <div class="form-field">
            <label for="borcVade">Vade / Ödeme tarihi</label>
            <input type="date" id="borcVade" name="vade" value="<?= $duzenlenecekBorc['vade_tarihi'] ?? '' ?>" required>
          </div>
          <div class="form-field">
            <label for="borcTaksit">Aylık taksit tutarı (₺) <small>(varsa)</small></label>
            <input type="number" id="borcTaksit" name="taksit" min="0" step="0.01" placeholder="0" value="<?= $duzenlenecekBorc['aylik_taksit'] ?? '' ?>">
          </div>
        </div>
        <button type="submit" name="<?= $duzenlenecekBorc ? 'borcGuncelle' : 'borcEkle' ?>" class="btn-add-sub"><i class="fa-solid <?= $duzenlenecekBorc ? 'fa-floppy-disk' : 'fa-plus' ?>"></i> <?= $duzenlenecekBorc ? 'Değişiklikleri kaydet' : 'Kaydı ekle' ?></button>
      </form>
    </div>

    <!-- BORÇ LİSTESİ -->
    <div class="table-container reveal">
      <div class="section-head">
        <h2><i class="fa-solid fa-arrow-trend-down"></i> Borçlarım</h2>
      </div>
      <div class="borc-liste"><?php foreach ($borclarListesi as $b) borcKartiCiz($b); ?></div>
      <?php if (empty($borclarListesi)) : ?>
        <p class="empty-state">Kayıtlı borcun yok.</p>
      <?php endif; ?>
    </div>

    <!-- ALACAK LİSTESİ -->
    <div class="table-container reveal">
      <div class="section-head">
        <h2><i class="fa-solid fa-arrow-trend-up"></i> Alacaklarım</h2>
      </div>
      <div class="borc-liste"><?php foreach ($alacaklarListesi as $a) borcKartiCiz($a); ?></div>
      <?php if (empty($alacaklarListesi)) : ?>
        <p class="empty-state">Kayıtlı alacağın yok.</p>
      <?php endif; ?>
    </div>

<?php require "includes/alt-kisim.php"; ?>
