<?php
require "config.php";
require "includes/fonksiyonlar.php";

$aktifSayfa = "index";
$sayfaBasligi = "Genel Bakış";
$kullaniciId = $_SESSION['kullanici_id'] ?? null;

require "includes/ust-kisim.php"; // giriş kontrolünü de burada yapıyor

$gelir = kullaniciGeliriGetir($baglanti, $kullaniciId);
$giderHedefi = kullaniciGiderHedefiGetir($baglanti, $kullaniciId);

$evMarketSpent = evMarketAylikToplam($baglanti, $kullaniciId);
$evMarketLimit = kullaniciEvMarketLimitiGetir($baglanti, $kullaniciId);

$subsSpent = round(abonelikAylikToplam($baglanti, $kullaniciId));

$borcSpent = round(toplamBorc($baglanti, $kullaniciId));
$alacakSpent = round(toplamAlacak($baglanti, $kullaniciId));
$borcLimit = max($borcSpent, $borcSpent + $alacakSpent, 1);

$kategoriler = [
    ["ad" => "Genel Gider", "anahtar" => "home", "harcanan" => $evMarketSpent, "limit" => max($evMarketLimit, 1), "ikon" => "fa-house", "link" => "genel-gider.php"],
    ["ad" => "Abonelikler",         "anahtar" => "sub",  "harcanan" => $subsSpent,     "limit" => max($subsSpent + 100, 300), "ikon" => "fa-tv", "link" => "abonelikler.php"],
    ["ad" => "Borç/Alacak",         "anahtar" => "debt", "harcanan" => $borcSpent,     "limit" => $borcLimit, "ikon" => "fa-credit-card", "link" => "borc-alacak.php"],
];

$renkHaritasi = ["home" => "#3b82f6", "debt" => "#ef4444", "sub" => "#38bdf8"];

$toplamGider = $evMarketSpent + $subsSpent + $borcSpent;
$netButce = $gelir - $toplamGider;

// Son giderler: ev/market harcamaları + abonelikler birleşik
$sonHarcamalar = evMarketHarcamalariGetir($baglanti, $kullaniciId);
$sonAbonelikler = abonelikleriGetir($baglanti, $kullaniciId);

$birlesikListe = [];
foreach ($sonHarcamalar as $h) {
    $birlesikListe[] = [
        "aciklama" => $h['aciklama'],
        "kategori" => $h['kategori_ad'],
        "anahtar" => "home",
        "ikon" => $h['kategori_ikon'],
        "tutar" => $h['tutar'],
        "tarih" => $h['tarih'],
        "duzenleLink" => "genel-gider.php?duzenle=" . $h['id'],
    ];
}
foreach ($sonAbonelikler as $s) {
    $birlesikListe[] = [
        "aciklama" => $s['ad'],
        "kategori" => "Abonelikler",
        "anahtar" => "sub",
        "ikon" => "fa-tv",
        "tutar" => $s['fiyat'],
        "tarih" => $s['yenileme_tarihi'],
        "duzenleLink" => "abonelikler.php?duzenle=" . $s['id'],
    ];
}
usort($birlesikListe, fn($a, $b) => strcmp($b['tarih'], $a['tarih']));
$birlesikListe = array_slice($birlesikListe, 0, 7);

// Son 6 aylık genel gider trendi (ev/market + abonelik + borç taksiti)
$trendEtiket = [];
$trendDeger = [];
$aylikTurkce = ["","Oca","Şub","Mar","Nis","May","Haz","Tem","Ağu","Eyl","Eki","Kas","Ara"];
$aylikTaksitToplami = borcAylikTaksitToplami($baglanti, $kullaniciId);
for ($i = 5; $i >= 0; $i--) {
    $zamanDamgasi = strtotime("-$i month");
    $ayKodu = date("Y-m", $zamanDamgasi);
    $trendEtiket[] = $aylikTurkce[(int)date("n", $zamanDamgasi)];
    $trendDeger[] = evMarketAylikToplam($baglanti, $kullaniciId, $ayKodu) + $subsSpent + $aylikTaksitToplami;
}

$tumHatirlaticilar = hatirlaticilariGetir($baglanti, $kullaniciId);
$tipEtiket = ["odeme" => "Ödeme", "fatura" => "Fatura", "genel" => "Genel"];
?>

    <!-- ÖZET KARTI -->
    <div class="summary-card">
      <div class="summary-main">
        <span class="summary-label">Kalan net bütçe</span>
        <h1 class="summary-value"><?= paraFormatla($netButce) ?></h1>
        <div class="budget-usage-bar">
          <div class="budget-usage-fill" style="width: <?= $gelir > 0 ? min(round($toplamGider / $gelir * 100), 100) : 0 ?>%;"></div>
        </div>
        <span class="budget-usage-caption">
          <?= $gelir > 0 ? "Gelirinin %" . min(round($toplamGider / $gelir * 100), 100) . "'i harcandı" : "Önce hesap sayfasından aylık gelirini gir." ?>
        </span>
      </div>

      <div class="summary-side">
        <div class="summary-stat">
          <span class="stat-icon stat-icon-income"><i class="fa-solid fa-arrow-up"></i></span>
          <div>
            <span class="summary-stat-label">Toplam gelir</span>
            <p class="summary-stat-value"><?= paraFormatla($gelir) ?></p>
          </div>
        </div>
        <div class="summary-stat">
          <span class="stat-icon stat-icon-expense"><i class="fa-solid fa-arrow-down"></i></span>
          <div>
            <span class="summary-stat-label">Toplam gider</span>
            <p class="summary-stat-value"><?= paraFormatla($toplamGider) ?></p>
          </div>
        </div>
      </div>
    </div>

    <?php if ($giderHedefi > 0) :
        $hedefYuzde = min(round($toplamGider / $giderHedefi * 100), 100);
        $hedefAsildiMi = $toplamGider > $giderHedefi; ?>
    <div class="chart-section-wrapper reveal">
      <div class="section-head">
        <h2><i class="fa-solid fa-bullseye"></i> Aylık Gider Hedefin</h2>
      </div>
      <div class="budget-usage-bar">
        <div class="budget-usage-fill" style="width: <?= $hedefYuzde ?>%; <?= $hedefAsildiMi ? 'background:var(--danger);' : '' ?>"></div>
      </div>
      <span class="budget-usage-caption">
        <?= paraFormatla($toplamGider) ?> / <?= paraFormatla($giderHedefi) ?> harcandı (%<?= $hedefYuzde ?>)
        <?= $hedefAsildiMi ? ' — hedefini aştın!' : '' ?>
      </span>
    </div>
    <?php endif; ?>

    <div class="dual-grid">
      <!-- KATEGORİ DAĞILIMI -->
      <div class="chart-section-wrapper">
        <div class="section-head">
          <h2><i class="fa-solid fa-chart-pie"></i> Kategori bazlı gider dağılımı</h2>
        </div>

        <div class="chart-flex-container">
          <div class="chart-visual">
            <canvas id="pieChart"></canvas>
            <div class="chart-center-label">
              <span><?= paraFormatla($toplamGider) ?></span>
              <small>toplam gider</small>
            </div>
          </div>

          <div class="chart-details">
            <?php foreach ($kategoriler as $c) :
                $yuzde = $toplamGider > 0 ? round($c['harcanan'] / $toplamGider * 100, 1) : 0; ?>
              <a href="<?= $c['link'] ?>" class="chart-legend-item cat-<?= $c['anahtar'] ?>" style="text-decoration:none; color:inherit; cursor:pointer;">
                <span class="legend-left">
                  <span class="legend-dot tinted"></span>
                  <span class="cat-label"><?= htmlspecialchars($c['ad']) ?></span>
                </span>
                <span class="amount"><?= paraFormatla($c['harcanan']) ?> <small>(%<?= $yuzde ?>)</small></span>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- AYLIK TREND -->
      <div class="chart-section-wrapper">
        <div class="section-head">
          <h2><i class="fa-solid fa-chart-line"></i> Son 6 ay genel gider</h2>
        </div>
        <div class="trend-chart-wrap">
          <canvas id="trendChart"></canvas>
        </div>
      </div>
    </div>

    <!-- KATEGORİ LİMİTLERİ -->
    <div class="chart-section-wrapper">
      <div class="section-head">
        <h2><i class="fa-solid fa-gauge-high"></i> Kategori limitleri</h2>
      </div>
      <div class="progress-grid">
        <?php foreach ($kategoriler as $c) :
            $yuzde = $c['limit'] > 0 ? round($c['harcanan'] / $c['limit'] * 100) : 0;
            $durumSinifi = $yuzde >= 100 ? "over" : ($yuzde >= 75 ? "warn" : "ok"); ?>
          <a href="<?= $c['link'] ?>" class="progress-card cat-<?= $c['anahtar'] ?>" style="text-decoration:none; color:inherit; display:block; cursor:pointer;">
            <div class="progress-card-top">
              <span class="progress-card-name">
                <span class="progress-card-icon tinted"><i class="fa-solid <?= $c['ikon'] ?>"></i></span>
                <?= htmlspecialchars($c['ad']) ?>
              </span>
              <span class="progress-pct-badge <?= $durumSinifi ?>">%<?= $yuzde ?></span>
            </div>
            <div class="progress-track">
              <div class="progress-fill tinted" style="width: <?= min($yuzde, 100) ?>%;"></div>
            </div>
            <span class="progress-card-amount"><?= paraFormatla($c['harcanan']) ?> / <?= paraFormatla($c['limit']) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- GİDER TABLOSU -->
    <div class="table-container">
      <div class="section-head">
        <h2><i class="fa-solid fa-receipt"></i> Son giderler</h2>
      </div>
      <table>
        <thead>
          <tr><th>Kategori</th><th>Açıklama</th><th>Tutar</th><th class="ev-th-action"></th></tr>
        </thead>
        <tbody>
          <?php if (empty($birlesikListe)) : ?>
            <tr><td colspan="4" style="text-align:center; color:var(--text-muted);">Henüz gider kaydı yok.</td></tr>
          <?php endif; ?>
          <?php foreach ($birlesikListe as $e) : ?>
            <tr>
              <td data-label="Kategori"><span class="cat-badge cat-<?= $e['anahtar'] ?>"><?= htmlspecialchars($e['kategori']) ?></span></td>
              <td data-label="Açıklama"><span class="desc-cell"><i class="fa-solid <?= $e['ikon'] ?>" style="color:<?= $renkHaritasi[$e['anahtar']] ?>"></i><?= htmlspecialchars($e['aciklama']) ?></span></td>
              <td data-label="Tutar"><?= paraFormatla($e['tutar']) ?></td>
              <td class="ev-th-action" data-label=""><a href="<?= $e['duzenleLink'] ?>" class="ev-row-edit" title="Düzenle"><i class="fa-solid fa-pen"></i></a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- HATIRLATICILAR (ÖZET) -->
    <div class="chart-section-wrapper">
      <div class="section-head">
        <h2><i class="fa-solid fa-bell"></i> Yaklaşan Hatırlatıcılar</h2>
        <a href="hatirlatici.php" class="btn-kategori-ekle"><i class="fa-solid fa-calendar-days"></i> Tümünü Gör</a>
      </div>
      <div class="hatirlatma-liste">
        <?php if (empty($tumHatirlaticilar)) : ?>
          <p class="hatirlatma-bos"><i class="fa-solid fa-calendar-check"></i> Henüz hatırlatıcı eklenmedi.</p>
        <?php endif; ?>
        <?php foreach (array_slice($tumHatirlaticilar, 0, 3) as $h) :
            $fark = gunFarkiHesapla($h['tarih']);
            $durumSinif = $fark < 0 ? "gecmis" : ($fark <= 3 ? "yakin" : "");
            $durumMetni = $fark === 0 ? "Bugün" : ($fark < 0 ? "Geçti" : "$fark gün kaldı"); ?>
          <div class="hatirlatma-item <?= $durumSinif ?>">
            <span class="hatirlatma-tarih-rozet"><?= date("j M", strtotime($h['tarih'])) ?></span>
            <div class="hatirlatma-bilgi">
              <strong><?= htmlspecialchars($h['baslik']) ?></strong>
              <small><?= $tipEtiket[$h['tip']] ?? "Genel" ?> · <?= $durumMetni ?></small>
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
  // Aktif temaya göre grafik renklerini CSS değişkenlerinden oku
  const kokStil = getComputedStyle(document.documentElement);
  const temaMetinRengi = kokStil.getPropertyValue("--text-muted").trim();
  const temaKartRengi = kokStil.getPropertyValue("--card-bg").trim();
  const temaCizgiRengi = document.documentElement.getAttribute("data-tema") === "karanlik"
    ? "rgba(148, 163, 184, 0.18)"
    : "rgba(148, 163, 184, 0.12)";

  // Pasta grafik: PHP'den gelen kategori verisiyle çizilir
  const pastaVeri = <?= json_encode(array_map(fn($c) => ["ad" => $c['ad'], "harcanan" => (float)$c['harcanan'], "renk" => $renkHaritasi[$c['anahtar']], "link" => $c['link']], $kategoriler)) ?>;
  const pieCanvas = document.getElementById("pieChart");
  if (pieCanvas && window.Chart) {
    new Chart(pieCanvas, {
      type: "doughnut",
      data: {
        labels: pastaVeri.map(c => c.ad),
        datasets: [{
          data: pastaVeri.map(c => c.harcanan),
          backgroundColor: pastaVeri.map(c => c.renk),
          borderColor: temaKartRengi,
          borderWidth: 3,
          hoverOffset: 10
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        cutout: "72%",
        onClick: (e, elemanlar) => {
          if (elemanlar.length > 0) window.location.href = pastaVeri[elemanlar[0].index].link;
        },
        onHover: (e, elemanlar) => {
          e.native.target.style.cursor = elemanlar.length > 0 ? "pointer" : "default";
        },
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: ctx => ctx.label + ": " + ctx.parsed.toLocaleString("tr-TR") + " ₺" } }
        }
      }
    });
  }

  
  const trendEtiketler = <?= json_encode($trendEtiket) ?>;
  const trendDegerler = <?= json_encode($trendDeger) ?>;
  const trendCanvas = document.getElementById("trendChart");
  if (trendCanvas && window.Chart) {
    const ctx2d = trendCanvas.getContext("2d");
    const gradient = ctx2d.createLinearGradient(0, 0, 0, 210);
    gradient.addColorStop(0, "rgba(79, 70, 229, 0.18)");
    gradient.addColorStop(1, "rgba(79, 70, 229, 0)");
    new Chart(trendCanvas, {
      type: "line",
      data: {
        labels: trendEtiketler,
        datasets: [{
          data: trendDegerler,
          borderColor: "#4f46e5",
          backgroundColor: gradient,
          fill: true,
          tension: 0.35,
          pointRadius: 4,
          pointBackgroundColor: "#4f46e5",
          pointBorderColor: temaKartRengi,
          pointBorderWidth: 2
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
