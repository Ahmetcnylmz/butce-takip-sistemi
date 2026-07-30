<?php
require "config.php";

// Beni Hatırla token'ı varsa veritabanından ve çerezden temizle
if (isset($_SESSION['kullanici_id'])) {
    $temizle = $baglanti->prepare("UPDATE kullanicilar SET hatirla_token = NULL WHERE id = ?");
    $temizle->bind_param("i", $_SESSION['kullanici_id']);
    $temizle->execute();
}
setcookie("hatirla_token", "", time() - 3600, "/");

// Oturumdaki tüm bilgileri temizle ve session'ı bitir
$_SESSION = [];
session_destroy();

header("Location: login.php");
exit;
