<?php
require "config.php";
require "includes/fonksiyonlar.php";
girisSartiKontrolEt();

$kullaniciId = $_SESSION['kullanici_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfDogrula();

    if (isset($_POST['abonelikEkle'])) {
        $ad = trim($_POST['ad']);
        $fiyat = (float)str_replace(',', '.', $_POST['fiyat']);
        $tarih = $_POST['tarih'];
        $donge = $_POST['donge'] === 'yearly' ? 'yearly' : 'monthly';
        $kategori = $_POST['kategori'] ?: 'diger';
        if ($ad !== '' && $fiyat > 0 && $tarih) {
            $bugun = date("Y-m-d");
            $ekle = $baglanti->prepare("INSERT INTO abonelikler (kullanici_id, ad, fiyat, yenileme_tarihi, donge, kategori, eklenme_tarihi) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $ekle->bind_param("isdssss", $kullaniciId, $ad, $fiyat, $tarih, $donge, $kategori, $bugun);
            $ekle->execute();
        }
        header("Location: abonelikler.php");
        exit;
    }

    if (isset($_POST['abonelikGuncelle'])) {
        $id = (int)$_POST['abonelik_id'];
        $ad = trim($_POST['ad']);
        $fiyat = (float)str_replace(',', '.', $_POST['fiyat']);
        $tarih = $_POST['tarih'];
        $donge = $_POST['donge'] === 'yearly' ? 'yearly' : 'monthly';
        $kategori = $_POST['kategori'] ?: 'diger';
        if ($ad !== '' && $fiyat > 0 && $tarih) {
            $guncelle = $baglanti->prepare("UPDATE abonelikler SET ad = ?, fiyat = ?, yenileme_tarihi = ?, donge = ?, kategori = ? WHERE id = ? AND kullanici_id = ?");
            $guncelle->bind_param("sdsssii", $ad, $fiyat, $tarih, $donge, $kategori, $id, $kullaniciId);
            $guncelle->execute();
        }
        header("Location: abonelikler.php");
        exit;
    }

    if (isset($_POST['abonelikSil'])) {
        $id = (int)$_POST['abonelik_id'];
        $sil = $baglanti->prepare("DELETE FROM abonelikler WHERE id = ? AND kullanici_id = ?");
        $sil->bind_param("ii", $id, $kullaniciId);
        $sil->execute();
        header("Location: abonelikler.php");
        exit;
    }

    if (isset($_POST['abonelikIptal'])) {
        $id = (int)$_POST['abonelik_id'];
        $guncelle = $baglanti->prepare("UPDATE abonelikler SET durum = 'iptal' WHERE id = ? AND kullanici_id = ?");
        $guncelle->bind_param("ii", $id, $kullaniciId);
        $guncelle->execute();
        header("Location: abonelikler.php");
        exit;
    }

    if (isset($_POST['abonelikAktifEt'])) {
        $id = (int)$_POST['abonelik_id'];
        $bugun = date("Y-m-d");
        $guncelle = $baglanti->prepare("UPDATE abonelikler SET durum = 'aktif', yenileme_tarihi = ? WHERE id = ? AND kullanici_id = ?");
        $guncelle->bind_param("sii", $bugun, $id, $kullaniciId);
        $guncelle->execute();
        header("Location: abonelikler.php");
        exit;
    }
}

// Hızlı seçim platformları (sadece formu otomatik dolduran istemci verisi)
$platformlar = [
    ["ad" => "Netflix",          "kategori" => "video",   "fiyat" => 289.99, "ikon" => "fa-solid fa-clapperboard", "renk" => "#e11d48"],
    ["ad" => "Spotify",          "kategori" => "muzik",   "fiyat" => 135,    "ikon" => "fa-solid fa-music",        "renk" => "#16a34a"],
    ["ad" => "YouTube Premium",  "kategori" => "video",   "fiyat" => 119.99, "ikon" => "fa-solid fa-tv",           "renk" => "#dc2626"],
    ["ad" => "Disney+",          "kategori" => "video",   "fiyat" => 249.99, "ikon" => "fa-solid fa-film",         "renk" => "#2563eb"],
    ["ad" => "Amazon Prime",     "kategori" => "video",   "fiyat" => 39,     "ikon" => "fa-solid fa-play",         "renk" => "#f59e0b"],
    ["ad" => "PlayStation Plus", "kategori" => "oyun",    "fiyat" => 400,    "ikon" => "fa-solid fa-gamepad",      "renk" => "#0ea5e9"],
    ["ad" => "Xbox Game Pass",   "kategori" => "oyun",    "fiyat" => 269,    "ikon" => "fa-solid fa-gamepad",      "renk" => "#22c55e"],
    ["ad" => "iCloud+",          "kategori" => "yazilim", "fiyat" => 24.99,  "ikon" => "fa-solid fa-cloud",        "renk" => "#64748b"],
    ["ad" => "Diğer",            "kategori" => "diger",   "fiyat" => "",     "ikon" => "fa-solid fa-ellipsis",     "renk" => "#64748b", "ozel" => true],
];
$kategoriEtiket = ["video" => "Video / Dizi-Film", "muzik" => "Müzik", "oyun" => "Oyun", "yazilim" => "Yazılım & Bulut", "diger" => "Diğer"];
$kategoriSinif  = ["video" => "cat-shop", "muzik" => "cat-sub", "oyun" => "cat-duzen", "yazilim" => "cat-car", "diger" => "cat-genel"];

$abonelikler = abonelikleriGetir($baglanti, $kullaniciId, 'aktif');
$iptalEdilenler = abonelikleriGetir($baglanti, $kullaniciId, 'iptal');

// Platform adına göre ikon eşlemesi (kart üstünde göstermek için)
$platformIkon = [];
foreach ($platformlar as $p) {
    $platformIkon[mb_strtolower($p['ad'])] = $p;
}
$kategoriRenkKodu = ["video" => "#ec4899", "muzik" => "#38bdf8", "oyun" => "#14b8a6", "yazilim" => "#a855f7", "diger" => "#64748b"];

// Düzenlenmek istenen abonelik var mı? (?duzenle=ID)
$duzenlenecekAbonelik = null;
if (isset($_GET['duzenle'])) {
    $duzenleId = (int)$_GET['duzenle'];
    foreach (array_merge($abonelikler, $iptalEdilenler) as $s) {
        if ($s['id'] == $duzenleId) { $duzenlenecekAbonelik = $s; break; }
    }
}

$aylikToplam = 0;
$yillikToplam = 0;
foreach ($abonelikler as $s) {
    $aylikToplam += $s['donge'] === 'yearly' ? $s['fiyat'] / 12 : $s['fiyat'];
    $yillikToplam += $s['donge'] === 'yearly' ? $s['fiyat'] : $s['fiyat'] * 12;
}

// En yakın yenileme tarihi
$enYakin = null;
foreach ($abonelikler as $s) {
    if ($enYakin === null || $s['yenileme_tarihi'] < $enYakin['yenileme_tarihi']) $enYakin = $s;
}

// Kategoriye göre toplam
$kategoriToplamlari = [];
foreach ($abonelikler as $s) {
    $aylikTutar = $s['donge'] === 'yearly' ? $s['fiyat'] / 12 : $s['fiyat'];
    $kategoriToplamlari[$s['kategori']] = ($kategoriToplamlari[$s['kategori']] ?? 0) + $aylikTutar;
}

$aktifSayfa = "abonelikler";
$sayfaBasligi = "Abonelikler";
$ekstraCss = ["css/aboneliklercss.css"];
require "includes/ust-kisim.php";
?>

    <div class="summary-card">
      <div class="summary-main">
        <span class="summary-label">Aylık toplam abonelik gideri</span>
        <h1 class="summary-value"><?= paraFormatla($aylikToplam) ?></h1>
        <span class="budget-usage-caption"><?= count($abonelikler) ?> aktif abonelik</span>
      </div>
      <div class="summary-side">
        <div class="summary-stat">
          <span class="stat-icon stat-icon-income"><i class="fa-solid fa-calendar-check"></i></span>
          <div>
            <span class="summary-stat-label">Yıllık toplam</span>
            <p class="summary-stat-value"><?= paraFormatla($yillikToplam) ?></p>
          </div>
        </div>
        <div class="summary-stat">
          <span class="stat-icon stat-icon-expense"><i class="fa-solid fa-clock"></i></span>
          <div>
            <span class="summary-stat-label">En yakın yenileme</span>
            <p class="summary-stat-value"><?= $enYakin ? date("d.m.Y", strtotime($enYakin['yenileme_tarihi'])) : "-" ?></p>
          </div>
        </div>
      </div>
    </div>

    <div class="chart-section-wrapper">
      <div class="section-head">
        <h2><i class="fa-solid fa-tags"></i> Kategoriye göre abonelik giderleri</h2>
      </div>
      <div class="ev-category-list">
        <?php if (empty($kategoriToplamlari)) : ?>
          <p class="empty-state">Henüz abonelik eklenmedi.</p>
        <?php endif; ?>
        <?php foreach ($kategoriToplamlari as $kat => $tutar) : ?>
          <div class="chart-legend-item <?= $kategoriSinif[$kat] ?? 'cat-genel' ?>">
            <span class="legend-left">
              <span class="legend-dot tinted"></span>
              <span class="cat-label"><?= $kategoriEtiket[$kat] ?? 'Diğer' ?></span>
            </span>
            <span class="amount"><?= paraFormatla($tutar) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="chart-section-wrapper" id="subFormAlan">
      <div class="section-head">
        <h2><i class="fa-solid <?= $duzenlenecekAbonelik ? 'fa-pen' : 'fa-plus' ?>"></i> <?= $duzenlenecekAbonelik ? 'Aboneliği düzenle' : 'Yeni abonelik ekle' ?></h2>
        <?php if ($duzenlenecekAbonelik) : ?>
          <a href="abonelikler.php" class="btn-kategori-ekle"><i class="fa-solid fa-xmark"></i> Vazgeç</a>
        <?php endif; ?>
      </div>

      <?php if (!$duzenlenecekAbonelik) : ?>
      <div class="subs-quick-grid" id="platformGrid">
        <?php foreach ($platformlar as $p) : ?>
          <div class="platform-chip<?= !empty($p['ozel']) ? ' platform-chip-ozel' : '' ?>"
               data-ad="<?= htmlspecialchars($p['ad']) ?>"
               data-fiyat="<?= $p['fiyat'] ?>"
               data-kategori="<?= $p['kategori'] ?>"
               data-ozel="<?= !empty($p['ozel']) ? '1' : '0' ?>">
            <span class="platform-chip-icon" style="background:<?= $p['renk'] ?? '#e2e8f0' ?>1a;">
              <i class="<?= $p['ikon'] ?>" style="color:<?= $p['renk'] ?? 'var(--text-muted)' ?>;"></i>
            </span>
            <span><?= htmlspecialchars($p['ad']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
      <p class="platform-fiyat-notu"><i class="fa-solid fa-circle-info"></i> Bir platforma tıklayınca güncel fiyatı otomatik doldurulur, "Diğer"e tıklayınca da formu boş şekilde kendin doldurabilirsin.</p>
      <?php endif; ?>


      <form id="subForm" class="sub-form" method="post" action="abonelikler.php">
      <?php csrfAlanYaz(); ?>
        <?php if ($duzenlenecekAbonelik) : ?>
          <input type="hidden" name="abonelik_id" value="<?= $duzenlenecekAbonelik['id'] ?>">
        <?php endif; ?>
        <div class="sub-form-row">
          <div class="form-field">
            <label for="subName">Platform adı</label>
            <input type="text" id="subName" name="ad" placeholder="ör. Netflix" value="<?= htmlspecialchars($duzenlenecekAbonelik['ad'] ?? '') ?>" required>
          </div>
          <div class="form-field">
            <label for="subPrice">Fiyat (₺)</label>
            <input type="number" id="subPrice" name="fiyat" min="0" step="0.01" placeholder="149.99" value="<?= $duzenlenecekAbonelik['fiyat'] ?? '' ?>" required>
          </div>
        </div>
        <div class="sub-form-row">
          <div class="form-field">
            <label for="subDate">Yenileme tarihi</label>
            <input type="date" id="subDate" name="tarih" value="<?= $duzenlenecekAbonelik['yenileme_tarihi'] ?? '' ?>" required>
          </div>
          <div class="form-field">
            <label for="subCycle">Fatura döngüsü</label>
            <select id="subCycle" name="donge">
              <option value="monthly" <?= (!$duzenlenecekAbonelik || $duzenlenecekAbonelik['donge'] === 'monthly') ? 'selected' : '' ?>>Aylık</option>
              <option value="yearly" <?= ($duzenlenecekAbonelik && $duzenlenecekAbonelik['donge'] === 'yearly') ? 'selected' : '' ?>>Yıllık</option>
            </select>
          </div>
        </div>
        <div class="sub-form-row">
          <div class="form-field">
            <label for="subCategory">Kategori</label>
            <select id="subCategory" name="kategori">
              <?php foreach ($kategoriEtiket as $katKey => $katAd) : ?>
                <option value="<?= $katKey ?>" <?= ($duzenlenecekAbonelik && $duzenlenecekAbonelik['kategori'] === $katKey) ? 'selected' : '' ?>><?= $katAd ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <button type="submit" name="<?= $duzenlenecekAbonelik ? 'abonelikGuncelle' : 'abonelikEkle' ?>" class="btn-add-sub"><i class="fa-solid <?= $duzenlenecekAbonelik ? 'fa-floppy-disk' : 'fa-plus' ?>"></i> <?= $duzenlenecekAbonelik ? 'Değişiklikleri kaydet' : 'Aboneliği ekle' ?></button>
      </form>
    </div>

    <div class="table-container">
      <div class="section-head">
        <h2><i class="fa-solid fa-list-check"></i> Aktif abonelikler</h2>
      </div>
      <div class="sub-grid">
        <?php foreach ($abonelikler as $s) :
            $ikonBilgi = $platformIkon[mb_strtolower($s['ad'])] ?? null;
            $renk = $ikonBilgi['renk'] ?? ($kategoriRenkKodu[$s['kategori']] ?? '#64748b');
            $kalanGun = gunFarkiHesapla($s['yenileme_tarihi']);
            $durumSinif = $kalanGun <= 3 ? 'over' : ($kalanGun <= 10 ? 'warn' : 'ok');
            $durumMetin = $kalanGun === 0 ? 'Bugün ödeme günü' : "$kalanGun gün sonra ödenecek"; ?>
          <div class="sub-card reveal">
            <a href="abonelikler.php?duzenle=<?= $s['id'] ?>#subFormAlan" class="sub-card-edit" title="Düzenle">
              <i class="fa-solid fa-pen"></i>
            </a>
            <form method="post" action="abonelikler.php" onsubmit="return confirm('Bu aboneliği iptal etmek istediğine emin misin? Bütçe hesaplarından çıkarılır.');" style="display:contents;">
            <?php csrfAlanYaz(); ?>
              <input type="hidden" name="abonelik_id" value="<?= $s['id'] ?>">
              <button type="submit" name="abonelikIptal" class="sub-card-delete" aria-label="Aboneliği iptal et" title="Aboneliği iptal et"><i class="fa-solid fa-ban"></i></button>
            </form>
            <div class="sub-card-top">
              <span class="sub-card-logo">
                <?php if ($ikonBilgi) : ?>
                  <i class="<?= $ikonBilgi['ikon'] ?>" style="color:<?= $renk ?>"></i>
                <?php else : ?>
                  <i class="fa-solid fa-file-invoice" style="color:<?= $renk ?>"></i>
                <?php endif; ?>
              </span>
              <div>
                <div class="sub-card-name"><?= htmlspecialchars($s['ad']) ?></div>
                <div class="sub-card-cycle"><?= $s['donge'] === 'yearly' ? 'Yıllık' : 'Aylık' ?> fatura</div>
              </div>
            </div>
            <span class="cat-badge <?= $kategoriSinif[$s['kategori']] ?? 'cat-genel' ?>"><?= $kategoriEtiket[$s['kategori']] ?? 'Diğer' ?></span>
            <div class="sub-card-price"><?= paraFormatla($s['fiyat']) ?></div>
            <div class="sub-card-bottom">
              <span class="sub-card-date"><?= date("j M Y", strtotime($s['yenileme_tarihi'])) ?></span>
              <span class="progress-pct-badge <?= $durumSinif ?>"><?= $durumMetin ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if (empty($abonelikler)) : ?>
        <p class="empty-state" style="display:block;">Henüz abonelik eklenmedi. Yukarıdaki formdan ilk aboneliğini ekleyebilirsin.</p>
      <?php endif; ?>
    </div>

    <?php if (!empty($iptalEdilenler)) : ?>
    <div class="table-container">
      <div class="section-head">
        <h2><i class="fa-solid fa-ban"></i> İptal Edilenler (<?= count($iptalEdilenler) ?>)</h2>
      </div>
      <div class="sub-grid">
        <?php foreach ($iptalEdilenler as $s) :
            $ikonBilgi = $platformIkon[mb_strtolower($s['ad'])] ?? null; ?>
          <div class="sub-card reveal" style="opacity:0.6;">
            <div class="sub-card-top">
              <span class="sub-card-logo">
                <?php if ($ikonBilgi) : ?>
                  <i class="<?= $ikonBilgi['ikon'] ?>" style="color:#94a3b8"></i>
                <?php else : ?>
                  <i class="fa-solid fa-file-invoice" style="color:#94a3b8"></i>
                <?php endif; ?>
              </span>
              <div>
                <div class="sub-card-name"><?= htmlspecialchars($s['ad']) ?></div>
                <div class="sub-card-cycle">İptal edildi</div>
              </div>
            </div>
            <div class="sub-card-price"><?= paraFormatla($s['fiyat']) ?></div>
            <div class="sub-card-bottom" style="gap:8px;">
              <form method="post" action="abonelikler.php">
                <?php csrfAlanYaz(); ?>
                <input type="hidden" name="abonelik_id" value="<?= $s['id'] ?>">
                <button type="submit" name="abonelikAktifEt" class="btn-add-sub" style="padding:6px 12px; font-size:0.75rem;"><i class="fa-solid fa-rotate-left"></i> Tekrar Aktif Et</button>
              </form>
              <form method="post" action="abonelikler.php" onsubmit="return confirm('Bu aboneliği kalıcı olarak silmek istediğine emin misin?');">
                <?php csrfAlanYaz(); ?>
                <input type="hidden" name="abonelik_id" value="<?= $s['id'] ?>">
                <button type="submit" name="abonelikSil" class="ev-row-delete" title="Kalıcı olarak sil"><i class="fa-solid fa-trash"></i></button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

<?php require "includes/alt-kisim.php"; ?>
<script>
  // Platform kartına tıklayınca formu otomatik doldur (sadece istemci tarafı kolaylık)
  document.querySelectorAll(".platform-chip").forEach(chip => {
    chip.addEventListener("click", () => {
      document.querySelectorAll(".platform-chip").forEach(c => c.classList.remove("selected"));
      chip.classList.add("selected");

      const subNameInput = document.getElementById("subName");
      const subPriceInput = document.getElementById("subPrice");

      if (chip.dataset.ozel === "1") {
        // "Diğer" seçildiyse formu boşaltıp kullanıcının kendi girmesine bırak
        subNameInput.value = "";
        subPriceInput.value = "";
        document.getElementById("subCategory").value = chip.dataset.kategori;
        subNameInput.focus();
      } else {
        subNameInput.value = chip.dataset.ad;
        subPriceInput.value = chip.dataset.fiyat;
        document.getElementById("subCategory").value = chip.dataset.kategori;
      }
    });
  });
</script>
