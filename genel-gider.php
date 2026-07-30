<?php
require "config.php";
require "includes/fonksiyonlar.php";
girisSartiKontrolEt();

$kullaniciId = $_SESSION['kullanici_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfDogrula();

    // Yeni harcama ekle
    if (isset($_POST['harcamaEkle'])) {
        $aciklama = trim($_POST['aciklama']);
        $tutar = (float)str_replace(',', '.', $_POST['tutar']);
        $kategoriId = (int)$_POST['kategori_id'];
        $tarih = $_POST['tarih'] ?: date("Y-m-d");
        $tekrarlayan = isset($_POST['tekrarlayan']) ? 1 : 0;
        if ($aciklama !== '' && $tutar > 0 && $kategoriId > 0) {
            $ekle = $baglanti->prepare("INSERT INTO ev_market_harcamalar (kullanici_id, kategori_id, aciklama, tutar, tarih, tekrarlayan) VALUES (?, ?, ?, ?, ?, ?)");
            $ekle->bind_param("iisdsi", $kullaniciId, $kategoriId, $aciklama, $tutar, $tarih, $tekrarlayan);
            $ekle->execute();
        }
        header("Location: genel-gider.php");
        exit;
    }

    // Toplam Genel Gider limitini güncelle
    if (isset($_POST['toplamLimitGuncelle'])) {
        $yeniToplamLimit = (float)str_replace(',', '.', ($_POST['yeni_toplam_limit'] ?: 0));
        if ($yeniToplamLimit >= 0) {
            $guncelle = $baglanti->prepare("UPDATE kullanicilar SET ev_market_limiti = ? WHERE id = ?");
            $guncelle->bind_param("di", $yeniToplamLimit, $kullaniciId);
            $guncelle->execute();
        }
        header("Location: genel-gider.php");
        exit;
    }

    // Yeni kategori ekle
    if (isset($_POST['kategoriEkle'])) {
        $ad = trim($_POST['kategori_ad']);
        $ikon = $_POST['kategori_ikon'] ?: 'fa-tag';
        $limit = (float)($_POST['kategori_limit'] ?: 0);
        if ($ad !== '') {
            $ekle = $baglanti->prepare("INSERT INTO ev_market_kategoriler (kullanici_id, ad, ikon, aylik_limit) VALUES (?, ?, ?, ?)");
            $ekle->bind_param("issd", $kullaniciId, $ad, $ikon, $limit);
            $ekle->execute();
        }
        header("Location: genel-gider.php");
        exit;
    }

    // Kategori limitini güncelle
    if (isset($_POST['kategoriGuncelle'])) {
        $kategoriId = (int)$_POST['kategori_id'];
        $yeniLimit = (float)($_POST['yeni_limit'] ?: 0);
        $guncelle = $baglanti->prepare("UPDATE ev_market_kategoriler SET aylik_limit = ? WHERE id = ? AND kullanici_id = ?");
        $guncelle->bind_param("dii", $yeniLimit, $kategoriId, $kullaniciId);
        $guncelle->execute();
        header("Location: genel-gider.php");
        exit;
    }

    // Kategori sil (harcaması olmayan kategoriler silinebilir)
    if (isset($_POST['kategoriSil'])) {
        $kategoriId = (int)$_POST['kategori_id'];
        $kontrol = $baglanti->prepare("SELECT COUNT(*) AS adet FROM ev_market_harcamalar WHERE kategori_id = ?");
        $kontrol->bind_param("i", $kategoriId);
        $kontrol->execute();
        $adet = $kontrol->get_result()->fetch_assoc()['adet'];
        if ($adet == 0) {
            $sil = $baglanti->prepare("DELETE FROM ev_market_kategoriler WHERE id = ? AND kullanici_id = ?");
            $sil->bind_param("ii", $kategoriId, $kullaniciId);
            $sil->execute();
        }
        header("Location: genel-gider.php");
        exit;
    }

    // Harcama güncelle (düzenleme formu)
    if (isset($_POST['harcamaGuncelle'])) {
        $harcamaId = (int)$_POST['harcama_id'];
        $aciklama = trim($_POST['aciklama']);
        $tutar = (float)str_replace(',', '.', $_POST['tutar']);
        $kategoriId = (int)$_POST['kategori_id'];
        $tarih = $_POST['tarih'] ?: date("Y-m-d");
        $tekrarlayan = isset($_POST['tekrarlayan']) ? 1 : 0;
        if ($aciklama !== '' && $tutar > 0 && $kategoriId > 0) {
            $guncelle = $baglanti->prepare("UPDATE ev_market_harcamalar SET aciklama = ?, tutar = ?, kategori_id = ?, tarih = ?, tekrarlayan = ? WHERE id = ? AND kullanici_id = ?");
            $guncelle->bind_param("sdisiii", $aciklama, $tutar, $kategoriId, $tarih, $tekrarlayan, $harcamaId, $kullaniciId);
            $guncelle->execute();
        }
        header("Location: genel-gider.php");
        exit;
    }

    // Harcama sil
    if (isset($_POST['harcamaSil'])) {
        $harcamaId = (int)$_POST['harcama_id'];
        $sil = $baglanti->prepare("DELETE FROM ev_market_harcamalar WHERE id = ? AND kullanici_id = ?");
        $sil->bind_param("ii", $harcamaId, $kullaniciId);
        $sil->execute();
        header("Location: genel-gider.php");
        exit;
    }
}

$ayFiltre = $_GET['ay'] ?? date("Y-m");
$kategoriler = evMarketKategorileriGetir($baglanti, $kullaniciId);
$toplamLimit = kullaniciEvMarketLimitiGetir($baglanti, $kullaniciId);
$harcamalarTumu = evMarketHarcamalariGetir($baglanti, $kullaniciId); // ay filtresi için tüm ay listesi
$harcamalarBuAy = evMarketHarcamalariGetir($baglanti, $kullaniciId, $ayFiltre);
$harcananToplam = array_sum(array_column($harcamalarBuAy, 'tutar'));
$kalan = $toplamLimit - $harcananToplam;
$kullanimYuzdesi = $toplamLimit > 0 ? min(round($harcananToplam / $toplamLimit * 100), 100) : 0;

// Kategori bazlı bütçe önerisi: son 3 ayın ortalamasına göre
$butceOnerileri = [];
foreach ($kategoriler as $k) {
    $ortalama = kategoriOrtalamaHarcama($baglanti, $kullaniciId, $k['id'], 3);
    if ($ortalama > 0) {
        $onerilenLimit = ceil($ortalama * 1.1 / 50) * 50;
        if (abs($onerilenLimit - $k['aylik_limit']) >= 50) {
            $butceOnerileri[] = ["kategori" => $k, "ortalama" => $ortalama, "onerilen" => $onerilenLimit];
        }
    }
}

// Düzenlenmek istenen harcama var mı? (?duzenle=ID)
$duzenlenecekHarcama = null;
if (isset($_GET['duzenle'])) {
    $duzenleId = (int)$_GET['duzenle'];
    $sorgu = $baglanti->prepare("SELECT * FROM ev_market_harcamalar WHERE id = ? AND kullanici_id = ?");
    $sorgu->bind_param("ii", $duzenleId, $kullaniciId);
    $sorgu->execute();
    $duzenlenecekHarcama = $sorgu->get_result()->fetch_assoc();
}

$mevcutAylar = array_unique(array_map(fn($h) => date("Y-m", strtotime($h['tarih'])), $harcamalarTumu));
rsort($mevcutAylar);
if (!in_array(date("Y-m"), $mevcutAylar)) array_unshift($mevcutAylar, date("Y-m"));

$aylikTurkce = ["","Ocak","Şubat","Mart","Nisan","Mayıs","Haziran","Temmuz","Ağustos","Eylül","Ekim","Kasım","Aralık"];
function ayAdiUret($ayKodu, $aylikTurkce) {
    [$yil, $ay] = explode("-", $ayKodu);
    return $aylikTurkce[(int)$ay] . " " . $yil;
}

$aktifSayfa = "genel-gider";
$sayfaBasligi = "Genel Gider Harcamaları";
$ekstraCss = ["css/ev-market.css"];
require "includes/ust-kisim.php";
?>

    <div class="summary-card reveal">
      <div class="summary-main">
        <span class="summary-label">Kalan Genel Gider Bütçesi (<?= ayAdiUret($ayFiltre, $aylikTurkce) ?>)</span>
        <h1 class="summary-value"><?= paraFormatla($kalan) ?></h1>
        <div class="budget-usage-bar">
          <div class="budget-usage-fill" style="width: <?= $kullanimYuzdesi ?>%;"></div>
        </div>
        <span class="budget-usage-caption">Bütçenizin %<?= $kullanimYuzdesi ?>'ünü harcadınız.</span>
        <?php if ($kalan < 0) : ?>
          <span class="ev-budget-warning" style="display:inline-flex;"><i class="fa-solid fa-triangle-exclamation"></i> Bütçe limiti aşıldı</span>
        <?php endif; ?>
      </div>

      <div class="summary-side">
        <div class="summary-stat">
          <span class="stat-icon stat-icon-income"><i class="fa-solid fa-bullseye"></i></span>
          <div>
            <span class="summary-stat-label">Toplam Limit</span>
            <p class="summary-stat-value">
              <?= paraFormatla($toplamLimit) ?>
              <button type="button" onclick="toplamLimitiDuzenle(<?= (int)$toplamLimit ?>)" style="border:none; background:transparent; color:var(--text-muted); cursor:pointer; font-size:0.75rem;" title="Limiti düzenle"><i class="fa-solid fa-pen"></i></button>
            </p>
          </div>
        </div>
        <div class="summary-stat">
          <span class="stat-icon stat-icon-expense"><i class="fa-solid fa-arrow-down"></i></span>
          <div>
            <span class="summary-stat-label">Bu Ayki Harcama</span>
            <p class="summary-stat-value"><?= paraFormatla($harcananToplam) ?></p>
          </div>
        </div>
      </div>
    </div>

    <div class="dual-grid">

      <div class="chart-section-wrapper reveal">
        <div class="section-head">
          <h2><i class="fa-solid <?= $duzenlenecekHarcama ? 'fa-pen' : 'fa-cart-plus' ?>"></i> <?= $duzenlenecekHarcama ? 'Harcamayı Düzenle' : 'Yeni Harcama Ekle' ?></h2>
          <?php if ($duzenlenecekHarcama) : ?>
            <a href="genel-gider.php" class="btn-kategori-ekle"><i class="fa-solid fa-xmark"></i> Vazgeç</a>
          <?php endif; ?>
        </div>

        <?php if (empty($kategoriler)) : ?>
          <p class="empty-state">Önce sağdaki formdan bir kategori eklemelisin.</p>
        <?php else : ?>
        <form class="ev-market-form" method="post" action="genel-gider.php">
        <?php csrfAlanYaz(); ?>
          <?php if ($duzenlenecekHarcama) : ?>
            <input type="hidden" name="harcama_id" value="<?= $duzenlenecekHarcama['id'] ?>">
          <?php endif; ?>
          <div class="ev-form-group">
            <label for="expenseDesc">Harcama Açıklaması</label>
            <input type="text" id="expenseDesc" name="aciklama" placeholder="Örn: Mutfak Alışverişi" value="<?= htmlspecialchars($duzenlenecekHarcama['aciklama'] ?? '') ?>" required>
          </div>

          <div class="ev-form-row">
            <div class="ev-form-group">
              <label for="expenseAmount">Tutar (₺)</label>
              <input type="number" id="expenseAmount" name="tutar" min="0" step="0.01" placeholder="0.00" value="<?= $duzenlenecekHarcama['tutar'] ?? '' ?>" required>
            </div>
            <div class="ev-form-group">
              <label for="expenseCategory">Kategori</label>
              <select id="expenseCategory" name="kategori_id" required>
                <?php foreach ($kategoriler as $k) : ?>
                  <option value="<?= $k['id'] ?>" <?= ($duzenlenecekHarcama && $duzenlenecekHarcama['kategori_id'] == $k['id']) ? 'selected' : '' ?>><?= htmlspecialchars($k['ad']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="ev-form-group">
              <label for="expenseDate">Tarih</label>
              <input type="date" id="expenseDate" name="tarih" value="<?= $duzenlenecekHarcama['tarih'] ?? date('Y-m-d') ?>" required>
            </div>
          </div>

          <label style="display:flex; align-items:center; gap:8px; font-size:0.85rem; color:var(--text-muted); margin:4px 0 14px;">
            <input type="checkbox" name="tekrarlayan" value="1" <?= (!empty($duzenlenecekHarcama['tekrarlayan'])) ? 'checked' : '' ?> style="width:auto;">
            Bu harcama her ay tekrarlansın (ör. kira, sabit fatura)
          </label>

          <button type="submit" name="<?= $duzenlenecekHarcama ? 'harcamaGuncelle' : 'harcamaEkle' ?>" class="ev-btn-submit"><?= $duzenlenecekHarcama ? 'Değişiklikleri Kaydet' : 'Harcamayı Kaydet' ?></button>
        </form>
        <?php endif; ?>
      </div>

      <div class="chart-section-wrapper reveal">
        <div class="section-head">
          <h2><i class="fa-solid fa-tags"></i> Kategoriler ve Limitler</h2>
          <button type="button" class="btn-kategori-ekle" onclick="document.getElementById('kategoriEkleForm').classList.toggle('show')"><i class="fa-solid fa-plus"></i> Kategori Ekle</button>
        </div>

        <form class="kategori-ekle-form" id="kategoriEkleForm" method="post" action="genel-gider.php">
        <?php csrfAlanYaz(); ?>
          <div>
            <label for="yeniKategoriAd">Kategori adı</label>
            <input type="text" id="yeniKategoriAd" name="kategori_ad" placeholder="ör. Eğitim" required>
          </div>
          <div>
            <label for="yeniKategoriIkon">İkon</label>
            <select id="yeniKategoriIkon" name="kategori_ikon">
              <option value="fa-tag">Genel</option>
              <option value="fa-graduation-cap">Eğitim</option>
              <option value="fa-heart-pulse">Sağlık</option>
              <option value="fa-paw">Evcil Hayvan</option>
              <option value="fa-gift">Hediye</option>
              <option value="fa-plane">Seyahat</option>
            </select>
          </div>
          <div>
            <label for="yeniKategoriLimit">Aylık limit (₺)</label>
            <input type="number" id="yeniKategoriLimit" name="kategori_limit" min="0" placeholder="500">
          </div>
          <button type="submit" name="kategoriEkle"><i class="fa-solid fa-check"></i> Ekle</button>
        </form>

        <div class="ev-category-list">
          <?php
          // Her kategoriye sırayla bir renk sınıfı ata (cat-home, cat-shop, cat-car, cat-bill, cat-sub, cat-duzen, cat-genel)
          $renkSiniflari = ["cat-home", "cat-shop", "cat-car", "cat-bill", "cat-sub", "cat-duzen", "cat-genel"];
          foreach ($kategoriler as $i => $k) :
              $harcanan = 0;
              foreach ($harcamalarBuAy as $h) if ($h['kategori_id'] == $k['id']) $harcanan += $h['tutar'];
              $harcananYuzde = $harcananToplam > 0 ? round($harcanan / $harcananToplam * 100) : 0;
              $limitYuzde = $k['aylik_limit'] > 0 ? round($harcanan / $k['aylik_limit'] * 100) : 0;
              $limitAsildi = $k['aylik_limit'] > 0 && $harcanan > $k['aylik_limit'];
              $renkSinifi = $renkSiniflari[$i % count($renkSiniflari)]; ?>
            <div class="ev-category-box <?= $renkSinifi ?><?= $limitAsildi ? ' over' : '' ?>">
              <span class="ev-category-icon"><i class="fa-solid <?= htmlspecialchars($k['ikon']) ?>"></i></span>
              <div class="ev-category-info">
                <strong><?= htmlspecialchars($k['ad']) ?></strong>
                <small><?= $k['aylik_limit'] > 0 ? 'Limit: ' . paraFormatla($k['aylik_limit']) : 'Limit belirlenmedi' ?></small>
                <span class="ev-category-warn"><i class="fa-solid fa-triangle-exclamation"></i> Bu kategori limitini aştı (%<?= $limitYuzde ?>)</span>
              </div>
              <div class="ev-category-right">
                <span class="ev-category-amount"><?= paraFormatla($harcanan) ?></span>
                <span class="ev-category-pct">%<?= $harcananYuzde ?></span>
              </div>
              <div class="ev-category-manage">
                <button type="button" class="kategori-duzenle" title="Limiti düzenle"
                        onclick="kategoriLimitiDuzenle(<?= $k['id'] ?>, '<?= htmlspecialchars($k['ad'], ENT_QUOTES) ?>', <?= (int)$k['aylik_limit'] ?>)">
                  <i class="fa-solid fa-pen"></i>
                </button>
                <form method="post" action="genel-gider.php" style="display:inline;" onsubmit="return confirm('Bu kategoriyi silmek istediğine emin misin?');">
                <?php csrfAlanYaz(); ?>
                  <input type="hidden" name="kategori_id" value="<?= $k['id'] ?>">
                  <button type="submit" name="kategoriSil" class="kategori-sil" title="Kategoriyi sil"><i class="fa-solid fa-trash"></i></button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
          <?php if (empty($kategoriler)) : ?>
            <p class="empty-state">Henüz kategori eklemedin. Yukarıdaki "Kategori Ekle" butonuyla ilk kategorini oluşturabilirsin.</p>
          <?php endif; ?>
        </div>

        <!-- Limit düzenleme için gizli form: pencil ikonuna tıklayınca JS ile doldurulup gönderiliyor -->
        <form method="post" action="genel-gider.php" id="limitDuzenleForm" style="display:none;">
        <?php csrfAlanYaz(); ?>
          <input type="hidden" name="kategori_id" id="limitDuzenleId">
          <input type="hidden" name="yeni_limit" id="limitDuzenleDeger">
          <input type="hidden" name="kategoriGuncelle" value="1">
        </form>

        <form method="post" action="genel-gider.php" id="toplamLimitForm" style="display:none;">
        <?php csrfAlanYaz(); ?>
          <input type="hidden" name="yeni_toplam_limit" id="toplamLimitDeger">
          <input type="hidden" name="toplamLimitGuncelle" value="1">
        </form>
      </div>

    </div>

    <?php if (!empty($butceOnerileri)) : ?>
    <div class="chart-section-wrapper reveal">
      <div class="section-head">
        <h2><i class="fa-solid fa-lightbulb"></i> Kategori Bazlı Bütçe Önerisi</h2>
      </div>
      <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:14px;">
        Son 3 ayın ortalama harcamana göre, bu kategorilerin limitini güncellemeni önerebiliriz.
      </p>
      <div class="ev-category-list">
        <?php foreach ($butceOnerileri as $oneri) : ?>
          <div class="ev-category-box cat-genel">
            <span class="ev-category-icon"><i class="fa-solid <?= htmlspecialchars($oneri['kategori']['ikon']) ?>"></i></span>
            <div class="ev-category-info">
              <strong><?= htmlspecialchars($oneri['kategori']['ad']) ?></strong>
              <small>Ortalama: <?= paraFormatla($oneri['ortalama']) ?> · Mevcut limit: <?= paraFormatla($oneri['kategori']['aylik_limit']) ?></small>
            </div>
            <div class="ev-category-right">
              <span class="ev-category-amount"><?= paraFormatla($oneri['onerilen']) ?></span>
              <span class="ev-category-pct">önerilen</span>
            </div>
            <div class="ev-category-manage">
              <form method="post" action="genel-gider.php">
                <?php csrfAlanYaz(); ?>
                <input type="hidden" name="kategori_id" value="<?= $oneri['kategori']['id'] ?>">
                <input type="hidden" name="yeni_limit" value="<?= $oneri['onerilen'] ?>">
                <button type="submit" name="kategoriGuncelle" class="kategori-duzenle" title="Öneriyi uygula"><i class="fa-solid fa-check"></i></button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="table-container reveal">
      <div class="section-head">
        <h2><i class="fa-solid fa-receipt"></i> Genel Gider Harcamaları</h2>
        <div class="section-head-actions">
          <form method="get" action="genel-gider.php">
            <select class="ay-filtre-select" name="ay" onchange="this.form.submit()">
              <?php foreach ($mevcutAylar as $ay) : ?>
                <option value="<?= $ay ?>" <?= $ay === $ayFiltre ? 'selected' : '' ?>><?= ayAdiUret($ay, $aylikTurkce) ?></option>
              <?php endforeach; ?>
            </select>
          </form>
          <a href="disa-aktar.php?ay=<?= $ayFiltre ?>" class="btn-kategori-ekle"><i class="fa-solid fa-file-excel"></i> Excel'e Aktar</a>
        </div>
      </div>
      <table>
        <thead>
          <tr>
            <th>Tarih</th>
            <th>Harcama Türü</th>
            <th>Açıklama</th>
            <th>Tutar</th>
            <th class="ev-th-action"></th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($harcamalarBuAy)) : ?>
            <tr><td colspan="5" style="text-align:center; color:var(--text-muted);">Bu ayda harcama kaydı yok.</td></tr>
          <?php endif; ?>
          <?php foreach ($harcamalarBuAy as $h) : ?>
            <tr>
              <td data-label="Tarih"><?= date("d.m.Y", strtotime($h['tarih'])) ?></td>
              <td data-label="Harcama Türü"><i class="fa-solid <?= htmlspecialchars($h['kategori_ikon']) ?>"></i> <?= htmlspecialchars($h['kategori_ad']) ?></td>
              <td data-label="Açıklama"><?= htmlspecialchars($h['aciklama']) ?> <?php if ($h['tekrarlayan']) : ?><i class="fa-solid fa-rotate" title="Tekrarlayan harcama" style="color:var(--primary); font-size:0.75rem;"></i><?php endif; ?></td>
              <td data-label="Tutar"><?= paraFormatla($h['tutar']) ?></td>
              <td class="ev-th-action" data-label="">
                <a href="genel-gider.php?duzenle=<?= $h['id'] ?>#expenseDesc" class="ev-row-edit" title="Düzenle"><i class="fa-solid fa-pen"></i></a>
                <form method="post" action="genel-gider.php" style="display:inline;" onsubmit="return confirm('Bu harcamayı silmek istediğine emin misin?');">
                <?php csrfAlanYaz(); ?>
                  <input type="hidden" name="harcama_id" value="<?= $h['id'] ?>">
                  <button type="submit" name="harcamaSil" class="ev-row-delete" title="Sil"><i class="fa-solid fa-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

<?php require "includes/alt-kisim.php"; ?>
<script>
  // Tıklayınca yeni limit sorulur, gizli form ile gönderilir
  function kategoriLimitiDuzenle(id, ad, mevcutLimit) {
    const yeni = prompt('"' + ad + '" için aylık limit (₺):', mevcutLimit || 0);
    if (yeni === null) return;
    const sayi = parseFloat(yeni);
    if (isNaN(sayi) || sayi < 0) { alert("Geçerli bir tutar gir."); return; }
    document.getElementById("limitDuzenleId").value = id;
    document.getElementById("limitDuzenleDeger").value = sayi;
    document.getElementById("limitDuzenleForm").submit();
  }

  // Toplam Genel Gider limitini düzenle
  function toplamLimitiDuzenle(mevcutLimit) {
    const yeni = prompt("Aylık toplam Genel Gider limitin (₺):", mevcutLimit || 0);
    if (yeni === null) return;
    const sayi = parseFloat(yeni);
    if (isNaN(sayi) || sayi < 0) { alert("Geçerli bir tutar gir."); return; }
    document.getElementById("toplamLimitDeger").value = sayi;
    document.getElementById("toplamLimitForm").submit();
  }
</script>
