<?php
require "config.php";
require "includes/fonksiyonlar.php";
girisSartiKontrolEt();

$kullaniciId = $_SESSION['kullanici_id'];

$secilenYil = isset($_GET['yil']) ? (int)$_GET['yil'] : (int)date("Y");
$oncekiYil = $secilenYil - 1;
$sonrakiYil = $secilenYil + 1;

$aylikTurkce = ["","Ocak","Şubat","Mart","Nisan","Mayıs","Haziran","Temmuz","Ağustos","Eylül","Ekim","Kasım","Aralık"];

// Tüm abonelik ve borçları eklenme tarihleriyle birlikte çek
$tumAbonelikler = abonelikleriGetir($baglanti, $kullaniciId);
$tumBorclar = borclariGetir($baglanti, $kullaniciId, 'borc');

$aylikEvMarket = [];
$aylikGenelGider = [];
$aylikEtiket = [];
$yillikEvMarketToplam = 0;
$yillikAbonelikToplam = 0;
$yillikTaksitToplam = 0;

for ($ay = 1; $ay <= 12; $ay++) {
    $ayKodu = $secilenYil . "-" . str_pad($ay, 2, "0", STR_PAD_LEFT);
    $ayinSonGunu = date("Y-m-t", strtotime($ayKodu . "-01"));

    $evMarket = evMarketAylikToplam($baglanti, $kullaniciId, $ayKodu);

    // Bu ay için: sadece o tarihte eklenmiş olan abonelik/borç kayıtları sayılır
    $abonelikBuAy = 0;
    foreach ($tumAbonelikler as $a) {
        $eklendi = $a['eklenme_tarihi'] ?: date("Y-m-d");
        if ($eklendi <= $ayinSonGunu) {
            $abonelikBuAy += $a['donge'] === 'yearly' ? $a['fiyat'] / 12 : $a['fiyat'];
        }
    }
    $taksitBuAy = 0;
    foreach ($tumBorclar as $b) {
        $eklendi = $b['eklenme_tarihi'] ?: date("Y-m-d");
        if ($eklendi <= $ayinSonGunu) {
            $taksitBuAy += $b['aylik_taksit'];
        }
    }

    $aylikEvMarket[] = $evMarket;
    $aylikGenelGider[] = $evMarket + $abonelikBuAy + $taksitBuAy;
    $aylikEtiket[] = $aylikTurkce[$ay];
    $yillikEvMarketToplam += $evMarket;
    $yillikAbonelikToplam += $abonelikBuAy;
    $yillikTaksitToplam += $taksitBuAy;
}

$yillikGenelToplam = $yillikEvMarketToplam + $yillikAbonelikToplam + $yillikTaksitToplam;

$enYuksekIndeks = array_search(max($aylikEvMarket), $aylikEvMarket);
$enYuksekAy = $aylikTurkce[$enYuksekIndeks + 1];

// Kategori bazlı yıllık kırılım
$yilBaslangic = $secilenYil . "-01-01";
$yilBitis = $secilenYil . "-12-31";
$kategoriKirilimSorgu = $baglanti->prepare("
    SELECT k.ad, k.ikon, COALESCE(SUM(h.tutar), 0) AS toplam
    FROM ev_market_kategoriler k
    LEFT JOIN ev_market_harcamalar h ON h.kategori_id = k.id AND h.tarih BETWEEN ? AND ?
    WHERE k.kullanici_id = ?
    GROUP BY k.id
    ORDER BY toplam DESC
");
$kategoriKirilimSorgu->bind_param("ssi", $yilBaslangic, $yilBitis, $kullaniciId);
$kategoriKirilimSorgu->execute();
$kategoriKirilim = $kategoriKirilimSorgu->get_result()->fetch_all(MYSQLI_ASSOC);

$aktifSayfa = "yillik-ozet";
$sayfaBasligi = "Yıllık Özet";
require "includes/ust-kisim.php";
?>

    <div class="summary-card reveal">
      <div class="summary-main">
        <span class="summary-label"><?= $secilenYil ?> Yılı Toplam Gideri</span>
        <h1 class="summary-value"><?= paraFormatla($yillikGenelToplam) ?></h1>
        <span class="budget-usage-caption">En çok harcanan ay: <strong><?= $enYuksekAy ?></strong></span>
      </div>
      <div class="summary-side">
        <a href="yillik-ozet.php?yil=<?= $oncekiYil ?>" class="btn-side" style="background:var(--bg-main); color:var(--text-main); width:auto; padding:10px 16px; text-decoration:none;"><i class="fa-solid fa-chevron-left"></i> <?= $oncekiYil ?></a>
        <a href="yillik-ozet.php?yil=<?= $sonrakiYil ?>" class="btn-side" style="background:var(--bg-main); color:var(--text-main); width:auto; padding:10px 16px; text-decoration:none;"><?= $sonrakiYil ?> <i class="fa-solid fa-chevron-right"></i></a>
        <a href="disa-aktar.php?yil=<?= $secilenYil ?>" class="btn-side" style="background:var(--success); color:#fff; width:auto; padding:10px 16px; text-decoration:none;"><i class="fa-solid fa-file-excel"></i> Yılı Aktar</a>
      </div>
    </div>

    <div class="progress-grid">
      <div class="progress-card cat-home">
        <div class="progress-card-top">
          <span class="progress-card-name"><span class="progress-card-icon tinted"><i class="fa-solid fa-house"></i></span> Genel Gider (yıllık)</span>
        </div>
        <span class="progress-card-amount" style="font-size:1.2rem; font-weight:700;"><?= paraFormatla($yillikEvMarketToplam) ?></span>
      </div>
      <div class="progress-card cat-sub">
        <div class="progress-card-top">
          <span class="progress-card-name"><span class="progress-card-icon tinted"><i class="fa-solid fa-rotate"></i></span> Abonelikler (yıllık)</span>
        </div>
        <span class="progress-card-amount" style="font-size:1.2rem; font-weight:700;"><?= paraFormatla($yillikAbonelikToplam) ?></span>
      </div>
      <div class="progress-card cat-car">
        <div class="progress-card-top">
          <span class="progress-card-name"><span class="progress-card-icon tinted"><i class="fa-solid fa-credit-card"></i></span> Borç Taksiti (yıllık)</span>
        </div>
        <span class="progress-card-amount" style="font-size:1.2rem; font-weight:700;"><?= paraFormatla($yillikTaksitToplam) ?></span>
      </div>
    </div>

    <div class="chart-section-wrapper reveal">
      <div class="section-head">
        <h2><i class="fa-solid fa-chart-column"></i> <?= $secilenYil ?> - Aylık Genel Gider</h2>
      </div>
      <div class="trend-chart-wrap">
        <canvas id="yillikChart"></canvas>
      </div>
      <p style="color:var(--text-muted); font-size:0.8rem; margin-top:10px;">
        Abonelik ve borç taksitleri, eklendikleri aydan itibaren hesaba katılır; Genel Gider her ay için gerçek kayıtlara göre hesaplanır.
      </p>
    </div>

    <div class="chart-section-wrapper reveal">
      <div class="section-head">
        <h2><i class="fa-solid fa-layer-group"></i> <?= $secilenYil ?> - Kategori Bazlı Kırılım</h2>
      </div>
      <div class="ev-category-list">
        <?php if (empty($kategoriKirilim) || array_sum(array_column($kategoriKirilim, 'toplam')) == 0) : ?>
          <p class="empty-state">Bu yıl için Genel Gider kaydı bulunamadı.</p>
        <?php endif; ?>
        <?php foreach ($kategoriKirilim as $kk) :
            if ($kk['toplam'] <= 0) continue;
            $yuzde = $yillikEvMarketToplam > 0 ? round($kk['toplam'] / $yillikEvMarketToplam * 100) : 0; ?>
          <div class="ev-category-box cat-genel">
            <span class="ev-category-icon"><i class="fa-solid <?= htmlspecialchars($kk['ikon']) ?>"></i></span>
            <div class="ev-category-info">
              <strong><?= htmlspecialchars($kk['ad']) ?></strong>
              <small>Yılın %<?= $yuzde ?>'i</small>
            </div>
            <div class="ev-category-right">
              <span class="ev-category-amount"><?= paraFormatla($kk['toplam']) ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

<?php
$ekstraJs = ["https://cdn.jsdelivr.net/npm/chart.js"];
require "includes/alt-kisim.php";
?>
<script>
  const yillikEtiketler = <?= json_encode($aylikEtiket) ?>;
  const yillikDegerler = <?= json_encode($aylikGenelGider) ?>;
  const yillikCanvas = document.getElementById("yillikChart");
  if (yillikCanvas && window.Chart) {
    const kokStil = getComputedStyle(document.documentElement);
    const temaMetinRengi = kokStil.getPropertyValue("--text-muted").trim();
    const temaCizgiRengi = document.documentElement.getAttribute("data-tema") === "karanlik"
      ? "rgba(148, 163, 184, 0.18)"
      : "rgba(148, 163, 184, 0.12)";

    new Chart(yillikCanvas, {
      type: "bar",
      data: {
        labels: yillikEtiketler,
        datasets: [{
          data: yillikDegerler,
          backgroundColor: "#4f46e5",
          borderRadius: 6,
          maxBarThickness: 40
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: ctx => ctx.parsed.y.toLocaleString("tr-TR") + " ₺" } }
        },
        scales: {
          y: { grid: { color: temaCizgiRengi }, ticks: { color: temaMetinRengi, callback: v => v.toLocaleString("tr-TR") + " ₺" } },
          x: { grid: { display: false }, ticks: { color: temaMetinRengi } }
        }
      }
    });
  }
</script>
