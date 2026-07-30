<?php
require "config.php";
require "includes/fonksiyonlar.php";
girisSartiKontrolEt();

$kullaniciId = $_SESSION['kullanici_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hatirlatici_id'])) {
    csrfDogrula();
    $id = (int)$_POST['hatirlatici_id'];
    $sil = $baglanti->prepare("DELETE FROM hatirlaticilar WHERE id = ? AND kullanici_id = ?");
    $sil->bind_param("ii", $id, $kullaniciId);
    $sil->execute();
}

// Silme işleminden sonra hangi sayfadaysak oraya geri dön
$donus = $_POST['donus'] ?? 'index.php';
// Basit bir güvenlik kontrolü: sadece sitenin kendi sayfalarına dönebilsin
if (!preg_match('/^[a-z0-9_\-]+\.php(\?[a-zA-Z0-9=&%.\-#]*)?$/', $donus)) {
    $donus = 'index.php';
}

header("Location: $donus");
exit;
