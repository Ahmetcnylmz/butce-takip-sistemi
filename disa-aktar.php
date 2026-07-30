<?php
require "config.php";
require "includes/fonksiyonlar.php";
girisSartiKontrolEt();

$kullaniciId = $_SESSION['kullanici_id'];

if (isset($_GET['yil'])) {
    // Yılın tamamını dışa aktar (12 ayın hepsini birleştirip tek dosyada verir)
    $secilenYil = (int)$_GET['yil'];
    $harcamalar = [];
    for ($ay = 1; $ay <= 12; $ay++) {
        $ayKodu = $secilenYil . "-" . str_pad($ay, 2, "0", STR_PAD_LEFT);
        $harcamalar = array_merge($harcamalar, evMarketHarcamalariGetir($baglanti, $kullaniciId, $ayKodu));
    }
    $dosyaAdi = "genel-gider-" . $secilenYil . ".csv";
} else {
    $ayFiltre = $_GET['ay'] ?? date("Y-m");
    $harcamalar = evMarketHarcamalariGetir($baglanti, $kullaniciId, $ayFiltre);
    $dosyaAdi = "genel-gider-" . $ayFiltre . ".csv";
}

// Tarayıcıya bunun bir dosya indirmesi olduğunu söylüyoruz
header("Content-Type: text/csv; charset=UTF-8");
header("Content-Disposition: attachment; filename=" . $dosyaAdi);

// Excel'in Türkçe karakterleri doğru göstermesi için BOM ekliyoruz
echo "\xEF\xBB\xBF";

$cikti = fopen("php://output", "w");
fputcsv($cikti, ["Tarih", "Kategori", "Açıklama", "Tutar (₺)"], ";");

foreach ($harcamalar as $h) {
    fputcsv($cikti, [
        date("d.m.Y", strtotime($h['tarih'])),
        $h['kategori_ad'],
        $h['aciklama'],
        number_format((float)$h['tutar'], 2, ',', '.'),
    ], ";");
}

fclose($cikti);
exit;
