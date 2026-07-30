<?php
// Giriş yapılmamışsa login sayfasına at
function girisSartiKontrolEt()
{
    if (!isset($_SESSION['kullanici_id'])) {
        header("Location: login.php");
        exit;
    }
}

// CSRF koruması: formlarla birlikte token gönderilir, POST'ta doğrulanır
function csrfTokenGetir()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfAlanYaz()
{
    echo '<input type="hidden" name="csrf_token" value="' . csrfTokenGetir() . '">';
}

function csrfDogrula()
{
    $gelenToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $gelenToken)) {
        http_response_code(403);
        die("Güvenlik doğrulaması başarısız oldu. Lütfen sayfayı yenileyip tekrar dene.");
    }
}

// Bot koruması: forma gerçek kullanıcıların göremediği gizli bir alan koyuyoruz.
// Botlar formu otomatik doldururken bu alanı da doldurur, insan doldurmaz.
function botAlaniYaz()
{
    echo '<input type="text" name="web_sitesi" class="hp-alan" tabindex="-1" autocomplete="off">';
}

function botKontrolEt()
{
    if (!empty($_POST['web_sitesi'])) {
        http_response_code(403);
        die("Hata oluştu.");
    }
}

// Tutarları "1.234 ₺" gibi göstermek için
function paraFormatla($tutar)
{
    return number_format((float)$tutar, 0, ',', '.') . " ₺";
}

function paraFormatlaKurus($tutar)
{
    return number_format((float)$tutar, 2, ',', '.') . " ₺";
}

function kullaniciGeliriGetir($baglanti, $kullaniciId)
{
    $sorgu = $baglanti->prepare("SELECT aylik_gelir FROM kullanicilar WHERE id = ?");
    $sorgu->bind_param("i", $kullaniciId);
    $sorgu->execute();
    $sonuc = $sorgu->get_result()->fetch_assoc();
    return $sonuc ? (float)$sonuc['aylik_gelir'] : 0;
}

function kullaniciGiderHedefiGetir($baglanti, $kullaniciId)
{
    $sorgu = $baglanti->prepare("SELECT aylik_gider_hedefi FROM kullanicilar WHERE id = ?");
    $sorgu->bind_param("i", $kullaniciId);
    $sorgu->execute();
    $sonuc = $sorgu->get_result()->fetch_assoc();
    return $sonuc ? (float)$sonuc['aylik_gider_hedefi'] : 0;
}

function evMarketKategorileriGetir($baglanti, $kullaniciId)
{
    $sorgu = $baglanti->prepare("SELECT * FROM ev_market_kategoriler WHERE kullanici_id = ? ORDER BY id ASC");
    $sorgu->bind_param("i", $kullaniciId);
    $sorgu->execute();
    return $sorgu->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Kullanıcının Hesap/Genel Gider sayfasından kendi belirlediği toplam limit
function kullaniciEvMarketLimitiGetir($baglanti, $kullaniciId)
{
    $sorgu = $baglanti->prepare("SELECT ev_market_limiti FROM kullanicilar WHERE id = ?");
    $sorgu->bind_param("i", $kullaniciId);
    $sorgu->execute();
    $sonuc = $sorgu->get_result()->fetch_assoc();
    return $sonuc ? (float)$sonuc['ev_market_limiti'] : 0;
}

// $ayFiltre formatı: "2026-07"; boşsa tüm harcamalar döner
function evMarketHarcamalariGetir($baglanti, $kullaniciId, $ayFiltre = null)
{
    if ($ayFiltre) {
        $sorgu = $baglanti->prepare("
            SELECT h.*, k.ad AS kategori_ad, k.ikon AS kategori_ikon
            FROM ev_market_harcamalar h
            JOIN ev_market_kategoriler k ON k.id = h.kategori_id
            WHERE h.kullanici_id = ? AND DATE_FORMAT(h.tarih, '%Y-%m') = ?
            ORDER BY h.tarih DESC, h.id DESC
        ");
        $sorgu->bind_param("is", $kullaniciId, $ayFiltre);
    } else {
        $sorgu = $baglanti->prepare("
            SELECT h.*, k.ad AS kategori_ad, k.ikon AS kategori_ikon
            FROM ev_market_harcamalar h
            JOIN ev_market_kategoriler k ON k.id = h.kategori_id
            WHERE h.kullanici_id = ?
            ORDER BY h.tarih DESC, h.id DESC
        ");
        $sorgu->bind_param("i", $kullaniciId);
    }
    $sorgu->execute();
    return $sorgu->get_result()->fetch_all(MYSQLI_ASSOC);
}

function evMarketAylikToplam($baglanti, $kullaniciId, $ay = null)
{
    $ay = $ay ?: date("Y-m");
    $sorgu = $baglanti->prepare("
        SELECT COALESCE(SUM(tutar),0) AS toplam
        FROM ev_market_harcamalar
        WHERE kullanici_id = ? AND DATE_FORMAT(tarih, '%Y-%m') = ?
    ");
    $sorgu->bind_param("is", $kullaniciId, $ay);
    $sorgu->execute();
    return (float)$sorgu->get_result()->fetch_assoc()['toplam'];
}

function abonelikleriGetir($baglanti, $kullaniciId, $durum = 'aktif')
{
    $sorgu = $baglanti->prepare("SELECT * FROM abonelikler WHERE kullanici_id = ? AND durum = ? ORDER BY yenileme_tarihi ASC");
    $sorgu->bind_param("is", $kullaniciId, $durum);
    $sorgu->execute();
    return $sorgu->get_result()->fetch_all(MYSQLI_ASSOC);
}

function abonelikAylikToplam($baglanti, $kullaniciId)
{
    $toplam = 0;
    foreach (abonelikleriGetir($baglanti, $kullaniciId) as $s) {
        $toplam += $s['donge'] === 'yearly' ? $s['fiyat'] / 12 : $s['fiyat'];
    }
    return $toplam;
}

// Aktif aboneliklerin ödeme tarihi geçmişse, otomatik olarak bir sonraki
// döneme (ay/yıl) taşır. Böylece kullanıcı hiçbir zaman "süresi geçti"
// görmez, abonelik kendini kendiliğinden yeniler.
function abonelikleriIlerlet($baglanti, $kullaniciId)
{
    $bugun = date("Y-m-d");
    $aktifler = abonelikleriGetir($baglanti, $kullaniciId, 'aktif');

    foreach ($aktifler as $a) {
        $yeniTarih = $a['yenileme_tarihi'];
        while ($yeniTarih < $bugun) {
            $yeniTarih = $a['donge'] === 'yearly'
                ? date("Y-m-d", strtotime("+1 year", strtotime($yeniTarih)))
                : date("Y-m-d", strtotime("+1 month", strtotime($yeniTarih)));
        }
        if ($yeniTarih !== $a['yenileme_tarihi']) {
            $guncelle = $baglanti->prepare("UPDATE abonelikler SET yenileme_tarihi = ? WHERE id = ?");
            $guncelle->bind_param("si", $yeniTarih, $a['id']);
            $guncelle->execute();
        }
    }
}

function hatirlaticilariGetir($baglanti, $kullaniciId)
{
    $sorgu = $baglanti->prepare("SELECT * FROM hatirlaticilar WHERE kullanici_id = ? ORDER BY tarih ASC");
    $sorgu->bind_param("i", $kullaniciId);
    $sorgu->execute();
    return $sorgu->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Bugünden itibaren $gunSayisi gün içindeki hatırlatıcılar (zil ikonu için)
function yaklasanHatirlaticilariGetir($baglanti, $kullaniciId, $gunSayisi = 5)
{
    $bugun = date("Y-m-d");
    $sonTarih = date("Y-m-d", strtotime("+$gunSayisi day"));
    $sorgu = $baglanti->prepare("SELECT * FROM hatirlaticilar WHERE kullanici_id = ? AND tarih BETWEEN ? AND ? ORDER BY tarih ASC");
    $sorgu->bind_param("iss", $kullaniciId, $bugun, $sonTarih);
    $sorgu->execute();
    return $sorgu->get_result()->fetch_all(MYSQLI_ASSOC);
}

function borclariGetir($baglanti, $kullaniciId, $tip = null)
{
    if ($tip) {
        $sorgu = $baglanti->prepare("SELECT * FROM borclar WHERE kullanici_id = ? AND tip = ? ORDER BY vade_tarihi ASC");
        $sorgu->bind_param("is", $kullaniciId, $tip);
    } else {
        $sorgu = $baglanti->prepare("SELECT * FROM borclar WHERE kullanici_id = ? ORDER BY vade_tarihi ASC");
        $sorgu->bind_param("i", $kullaniciId);
    }
    $sorgu->execute();
    return $sorgu->get_result()->fetch_all(MYSQLI_ASSOC);
}

function toplamBorc($baglanti, $kullaniciId)
{
    $sorgu = $baglanti->prepare("SELECT COALESCE(SUM(tutar),0) AS toplam FROM borclar WHERE kullanici_id = ? AND tip = 'borc' AND odendi = 0");
    $sorgu->bind_param("i", $kullaniciId);
    $sorgu->execute();
    return (float)$sorgu->get_result()->fetch_assoc()['toplam'];
}

function toplamAlacak($baglanti, $kullaniciId)
{
    $sorgu = $baglanti->prepare("SELECT COALESCE(SUM(tutar),0) AS toplam FROM borclar WHERE kullanici_id = ? AND tip = 'alacak' AND odendi = 0");
    $sorgu->bind_param("i", $kullaniciId);
    $sorgu->execute();
    return (float)$sorgu->get_result()->fetch_assoc()['toplam'];
}

function borcAylikTaksitToplami($baglanti, $kullaniciId)
{
    $sorgu = $baglanti->prepare("SELECT COALESCE(SUM(aylik_taksit),0) AS toplam FROM borclar WHERE kullanici_id = ? AND tip = 'borc' AND odendi = 0");
    $sorgu->bind_param("i", $kullaniciId);
    $sorgu->execute();
    return (float)$sorgu->get_result()->fetch_assoc()['toplam'];
}

// Admin panelinde yapılan işlemleri kaydeder (kullanıcı silme, rol değiştirme vb.)
function adminLogEkle($baglanti, $adminAdi, $islem)
{
    $ekle = $baglanti->prepare("INSERT INTO admin_loglari (admin_adi, islem) VALUES (?, ?)");
    $ekle->bind_param("ss", $adminAdi, $islem);
    $ekle->execute();
}

function adminLoglariGetir($baglanti, $adet = 20)
{
    $sorgu = $baglanti->prepare("SELECT * FROM admin_loglari ORDER BY tarih DESC LIMIT ?");
    $sorgu->bind_param("i", $adet);
    $sorgu->execute();
    return $sorgu->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Aylık gelir - abonelik - genel gider - borç taksitleri
function toplamButce($baglanti, $kullaniciId)
{
    $gelir = kullaniciGeliriGetir($baglanti, $kullaniciId);
    $abonelik = abonelikAylikToplam($baglanti, $kullaniciId);
    $evMarket = evMarketAylikToplam($baglanti, $kullaniciId);
    $taksit = borcAylikTaksitToplami($baglanti, $kullaniciId);
    return $gelir - $abonelik - $evMarket - $taksit;
}

// Yeni üyeye varsayılan Genel Gider kategorilerini ekler
function varsayilanKategorileriEkle($baglanti, $kullaniciId)
{
    $varsayilanlar = [
        ["Mutfak & Gıda",        "fa-basket-shopping",     2000],
        ["Faturalar",            "fa-file-invoice-dollar", 1500],
        ["Araç Masrafları",      "fa-car",                 1000],
        ["Alışveriş / Market",   "fa-cart-shopping",       1200],
        ["Temizlik ve Hijyen",   "fa-pump-soap",            500],
        ["Ev Düzeni & Tadilat",  "fa-screwdriver-wrench",   800],
    ];
    $sorgu = $baglanti->prepare("INSERT INTO ev_market_kategoriler (kullanici_id, ad, ikon, aylik_limit) VALUES (?, ?, ?, ?)");
    foreach ($varsayilanlar as $k) {
        $sorgu->bind_param("issd", $kullaniciId, $k[0], $k[1], $k[2]);
        $sorgu->execute();
    }
}

// Basit e-posta gönderme fonksiyonu (PHP'nin kendi mail() fonksiyonu ile).
// Not: gerçekten göndermek için sunucunda/hostinginde SMTP ayarlı olmalı.
function epostaGonder($alici, $konu, $mesaj)
{
    $basliklar = "From: Bütçe Takip <bildirim@butcetakip.local>\r\nContent-Type: text/plain; charset=UTF-8";
    $basariliMi = mail($alici, $konu, $mesaj, $basliklar);
    if (!$basariliMi) {
        error_log("E-posta gönderilemedi: $alici - $konu");
    }
    return $basariliMi;
}

function sifirlamaKoduGonder($alici, $kod)
{
    $konu = "Bütçe Takip - Şifre Sıfırlama Kodun";
    $mesaj = "Merhaba,\n\nŞifreni sıfırlamak için kodun: $kod\n\nBu kod 30 dakika geçerlidir. Bu isteği sen yapmadıysan bu e-postayı yok sayabilirsin.";
    epostaGonder($alici, $konu, $mesaj);
}

// Bir kategorinin son $aySayisi aydaki ortalama harcaması (bütçe önerisi için)
function kategoriOrtalamaHarcama($baglanti, $kullaniciId, $kategoriId, $aySayisi = 3)
{
    $baslangic = date("Y-m-01", strtotime("-" . ($aySayisi - 1) . " months"));
    $sorgu = $baglanti->prepare("
        SELECT COALESCE(SUM(tutar),0) AS toplam
        FROM ev_market_harcamalar
        WHERE kullanici_id = ? AND kategori_id = ? AND tarih >= ?
    ");
    $sorgu->bind_param("iis", $kullaniciId, $kategoriId, $baslangic);
    $sorgu->execute();
    $toplam = (float)$sorgu->get_result()->fetch_assoc()['toplam'];
    return $toplam / $aySayisi;
}

// Tekrarlayan işaretli harcamaları, ay değiştiyse otomatik olarak bu aya da ekler
function tekrarlayanHarcamalariUygula($baglanti, $kullaniciId)
{
    $simdikiAy = date("Y-m");
    $sorgu = $baglanti->prepare("
        SELECT kategori_id, aciklama, tutar, MAX(tarih) AS son_tarih
        FROM ev_market_harcamalar
        WHERE kullanici_id = ? AND tekrarlayan = 1
        GROUP BY kategori_id, aciklama, tutar
    ");
    $sorgu->bind_param("i", $kullaniciId);
    $sorgu->execute();
    $kayitlar = $sorgu->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($kayitlar as $k) {
        if (date("Y-m", strtotime($k['son_tarih'])) === $simdikiAy) {
            continue; // bu ay için zaten eklenmiş
        }
        $gun = min((int)date("j", strtotime($k['son_tarih'])), (int)date("t"));
        $yeniTarih = $simdikiAy . "-" . str_pad($gun, 2, "0", STR_PAD_LEFT);
        $ekle = $baglanti->prepare("INSERT INTO ev_market_harcamalar (kullanici_id, kategori_id, aciklama, tutar, tarih, tekrarlayan) VALUES (?, ?, ?, ?, ?, 1)");
        $ekle->bind_param("iisds", $kullaniciId, $k['kategori_id'], $k['aciklama'], $k['tutar'], $yeniTarih);
        $ekle->execute();
    }
}


function dogrulamaKoduGonder($alici, $kod)
{
    $konu = "Bütçe Takip - E-posta Doğrulama Kodun";
    $mesaj = "Merhaba,\n\nHesap sayfandan e-postanı doğrulamak için kodun: $kod\n\nDoğruladığında hatırlatıcı bildirimlerini e-postana da alabileceksin.";
    epostaGonder($alici, $konu, $mesaj);
}

function hatirlaticiMailGonder($alici, $baslik, $tarih, $gunKaldi)
{
    $konu = "Bütçe Takip - Yaklaşan Hatırlatıcı";
    $gunMetni = $gunKaldi === 0 ? "bugün" : "$gunKaldi gün sonra";
    $mesaj = "Merhaba,\n\n\"$baslik\" hatırlatıcın $gunMetni ($tarih).\n\nBütçe Takip Sistemi";
    epostaGonder($alici, $konu, $mesaj);
}

// Bugünün tarihini "Cuma, 17 Temmuz 2026" gibi Türkçe yazar
function turkceTarih()
{
    $gunler = ["Pazar","Pazartesi","Salı","Çarşamba","Perşembe","Cuma","Cumartesi"];
    $aylar  = ["","Ocak","Şubat","Mart","Nisan","Mayıs","Haziran","Temmuz","Ağustos","Eylül","Ekim","Kasım","Aralık"];
    $gun = $gunler[date("w")];
    $ay = $aylar[(int)date("n")];
    return $gun . ", " . date("j") . " " . $ay . " " . date("Y");
}

// İki tarih arasındaki gün farkı (hatırlatıcı ve borç vadeleri için)
function gunFarkiHesapla($tarih)
{
    $bugun = new DateTime(date("Y-m-d"));
    $hedef = new DateTime($tarih);
    $fark = $bugun->diff($hedef)->days;
    return $hedef < $bugun ? -$fark : $fark;
}
