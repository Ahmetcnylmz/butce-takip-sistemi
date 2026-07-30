<?php
require "config.php";
require "includes/fonksiyonlar.php";
girisSartiKontrolEt();

if (($_SESSION['rol'] ?? '') !== 'admin') {
    header("Location: index.php");
    exit;
}

$kullaniciId = $_SESSION['kullanici_id'];
$hataMesaji = "";
$basariMesaji = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfDogrula();

    if (isset($_POST['kullaniciSil'])) {
        $silinecekId = (int)$_POST['hedef_id'];
        if ($silinecekId === $kullaniciId) {
            $hataMesaji = "Kendi hesabını buradan silemezsin.";
        } else {
            $sorgu = $baglanti->prepare("SELECT kullanici_adi FROM kullanicilar WHERE id = ?");
            $sorgu->bind_param("i", $silinecekId);
            $sorgu->execute();
            $hedefKullanici = $sorgu->get_result()->fetch_assoc();

            $sil = $baglanti->prepare("DELETE FROM kullanicilar WHERE id = ?");
            $sil->bind_param("i", $silinecekId);
            $sil->execute();
            adminLogEkle($baglanti, $_SESSION['kullanici_adi'], "\"" . ($hedefKullanici['kullanici_adi'] ?? $silinecekId) . "\" adlı kullanıcıyı sildi");
            $basariMesaji = "Kullanıcı silindi.";
        }
    }

    if (isset($_POST['rolDegistir'])) {
        $hedefId = (int)$_POST['hedef_id'];
        $yeniRol = $_POST['yeni_rol'] === 'admin' ? 'admin' : 'kullanici';
        if ($hedefId === $kullaniciId) {
            $hataMesaji = "Kendi rolünü buradan değiştiremezsin.";
        } else {
            $sorgu = $baglanti->prepare("SELECT kullanici_adi FROM kullanicilar WHERE id = ?");
            $sorgu->bind_param("i", $hedefId);
            $sorgu->execute();
            $hedefKullanici = $sorgu->get_result()->fetch_assoc();

            $guncelle = $baglanti->prepare("UPDATE kullanicilar SET rol = ? WHERE id = ?");
            $guncelle->bind_param("si", $yeniRol, $hedefId);
            $guncelle->execute();
            adminLogEkle($baglanti, $_SESSION['kullanici_adi'], "\"" . ($hedefKullanici['kullanici_adi'] ?? $hedefId) . "\" adlı kullanıcının rolünü \"$yeniRol\" yaptı");
            $basariMesaji = "Kullanıcının rolü güncellendi.";
        }
    }
}

// İstatistik
$toplamKullanici = $baglanti->query("SELECT COUNT(*) AS adet FROM kullanicilar")->fetch_assoc()['adet'];

// Kullanıcı listesi (her kullanıcının kaç ev/market kaydı olduğu ile birlikte)
$kullanicilar = $baglanti->query("
    SELECT k.*, COUNT(h.id) AS harcama_sayisi
    FROM kullanicilar k
    LEFT JOIN ev_market_harcamalar h ON h.kullanici_id = k.id
    GROUP BY k.id
    ORDER BY k.kayit_tarihi DESC
")->fetch_all(MYSQLI_ASSOC);

$adminLoglari = adminLoglariGetir($baglanti, 20);

$aktifSayfa = "admin";
$sayfaBasligi = "Yönetim Paneli";
require "includes/ust-kisim.php";
?>

    <?php if ($basariMesaji) : ?>
      <p style="color:var(--success); font-size:0.9rem; margin-bottom:14px;"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($basariMesaji) ?></p>
    <?php endif; ?>
    <?php if ($hataMesaji) : ?>
      <p style="color:var(--danger); font-size:0.9rem; margin-bottom:14px;"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($hataMesaji) ?></p>
    <?php endif; ?>

    <div class="section-head" style="margin-bottom:12px;">
      <h2 style="font-size:1rem;"><i class="fa-solid fa-chart-simple"></i> Genel İstatistikler</h2>
    </div>
    <div class="progress-grid">
      <div class="progress-card cat-home">
        <div class="progress-card-top">
          <span class="progress-card-name"><span class="progress-card-icon tinted"><i class="fa-solid fa-users"></i></span> Toplam Kullanıcı</span>
        </div>
        <span class="progress-card-amount" style="font-size:1.3rem; font-weight:700;"><?= $toplamKullanici ?></span>
      </div>
    </div>

    <div class="table-container">
      <div class="section-head">
        <h2><i class="fa-solid fa-users"></i> Kayıtlı Kullanıcılar (<?= count($kullanicilar) ?>)</h2>
        <div class="section-head-actions">
          <input type="text" id="kullaniciAra" placeholder="Ad, kullanıcı adı veya e-posta ara..." class="ay-filtre-select" style="width:240px;">
        </div>
      </div>
      <table id="kullaniciTablosu">
        <thead>
          <tr>
            <th>Ad Soyad</th>
            <th>Kullanıcı Adı</th>
            <th>E-posta</th>
            <th>Aylık Gelir</th>
            <th>Genel Gider Kaydı</th>
            <th>Rol</th>
            <th>Kayıt Tarihi</th>
            <th class="ev-th-action"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($kullanicilar as $k) : ?>
            <tr>
              <td data-label="Ad Soyad"><?= htmlspecialchars($k['ad_soyad']) ?></td>
              <td data-label="Kullanıcı Adı"><?= htmlspecialchars($k['kullanici_adi']) ?></td>
              <td data-label="E-posta"><?= htmlspecialchars($k['email']) ?></td>
              <td data-label="Aylık Gelir"><?= paraFormatla($k['aylik_gelir']) ?></td>
              <td data-label="Genel Gider Kaydı"><?= $k['harcama_sayisi'] ?></td>
              <td data-label="Rol">
                <?php if ((int)$k['id'] === $kullaniciId) : ?>
                  <span class="cat-badge cat-home"><?= $k['rol'] === 'admin' ? 'Admin' : 'Kullanıcı' ?></span>
                <?php else : ?>
                  <form method="post" action="admin.php" style="display:inline;">
                    <?php csrfAlanYaz(); ?>
                    <input type="hidden" name="hedef_id" value="<?= $k['id'] ?>">
                    <select name="yeni_rol" onchange="this.form.submit()" class="ay-filtre-select">
                      <option value="kullanici" <?= $k['rol'] === 'kullanici' ? 'selected' : '' ?>>Kullanıcı</option>
                      <option value="admin" <?= $k['rol'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                    </select>
                    <input type="hidden" name="rolDegistir" value="1">
                  </form>
                <?php endif; ?>
              </td>
              <td data-label="Kayıt Tarihi"><?= date("d.m.Y", strtotime($k['kayit_tarihi'])) ?></td>
              <td class="ev-th-action">
                <?php if ((int)$k['id'] !== $kullaniciId) : ?>
                  <form method="post" action="admin.php" onsubmit="return confirm('Bu kullanıcıyı ve tüm verilerini silmek istediğine emin misin?');">
                    <?php csrfAlanYaz(); ?>
                    <input type="hidden" name="hedef_id" value="<?= $k['id'] ?>">
                    <button type="submit" name="kullaniciSil" class="ev-row-delete" title="Kullanıcıyı sil"><i class="fa-solid fa-trash"></i></button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php if (empty($kullanicilar)) : ?>
        <p class="empty-state">Henüz kayıtlı kullanıcı yok.</p>
      <?php endif; ?>
    </div>

    <div class="table-container">
      <div class="section-head">
        <h2><i class="fa-solid fa-clock-rotate-left"></i> İşlem Geçmişi</h2>
      </div>
      <table>
        <thead>
          <tr><th>Admin</th><th>İşlem</th><th>Tarih</th></tr>
        </thead>
        <tbody>
          <?php if (empty($adminLoglari)) : ?>
            <tr><td colspan="3" style="text-align:center; color:var(--text-muted);">Henüz bir işlem yapılmadı.</td></tr>
          <?php endif; ?>
          <?php foreach ($adminLoglari as $log) : ?>
            <tr>
              <td data-label="Admin"><?= htmlspecialchars($log['admin_adi']) ?></td>
              <td data-label="İşlem"><?= htmlspecialchars($log['islem']) ?></td>
              <td data-label="Tarih"><?= date("d.m.Y H:i", strtotime($log['tarih'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

<?php require "includes/alt-kisim.php"; ?>
<script>
  // Kullanıcı listesini isim/kullanıcı adı/e-postaya göre anlık filtrele
  const kullaniciAraInput = document.getElementById("kullaniciAra");
  if (kullaniciAraInput) {
    kullaniciAraInput.addEventListener("input", () => {
      const aranan = kullaniciAraInput.value.toLocaleLowerCase("tr-TR");
      document.querySelectorAll("#kullaniciTablosu tbody tr").forEach(satir => {
        satir.style.display = satir.textContent.toLocaleLowerCase("tr-TR").includes(aranan) ? "" : "none";
      });
    });
  }
</script>

