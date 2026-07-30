  </div><!-- /.content -->

  <footer class="site-footer">
    <p>&copy; <?= date("Y") ?> Bütçe Takip Sistemi — Gelir, gider ve bütçeni tek yerden yönet.</p>
  </footer>

  <?php if (!empty($ekstraJs)) foreach ($ekstraJs as $dosya) : ?>
  <script src="<?= $dosya ?>"></script>
  <?php endforeach; ?>
  <script>
    document.querySelectorAll(".summary-card, .chart-section-wrapper, .table-container").forEach((el, i) => {
      el.classList.add("reveal");
      el.style.animationDelay = (i * 0.1) + "s";
    });

    // Karanlık / aydınlık mod
    const temaDegistirBtn = document.getElementById("temaDegistirBtn");
    if (temaDegistirBtn) {
      const suAnKaranlikMi = document.documentElement.getAttribute("data-tema") === "karanlik";
      temaDegistirBtn.querySelector("i").className = suAnKaranlikMi ? "fa-solid fa-sun" : "fa-solid fa-moon";

      temaDegistirBtn.addEventListener("click", () => {
        const karanlikOlacak = document.documentElement.getAttribute("data-tema") !== "karanlik";
        localStorage.setItem("tema", karanlikOlacak ? "karanlik" : "aydinlik");
        // Sayfayı yeniden yükle: böylece grafikler de (Chart.js) doğru
        // temaya göre yeniden çizilir, yarı güncellenmiş görünmez.
        location.reload();
      });
    }

    // Zil ikonuna tıklayınca bildirim panelini aç/kapat
    const bildirimZilBtn = document.getElementById("bildirimZilBtn");
    const bildirimPanel = document.getElementById("bildirimPanel");
    if (bildirimZilBtn && bildirimPanel) {
      bildirimZilBtn.addEventListener("click", (e) => {
        e.stopPropagation();
        bildirimPanel.classList.toggle("acik");
      });
      document.addEventListener("click", (e) => {
        if (!bildirimPanel.contains(e.target) && !bildirimZilBtn.contains(e.target)) {
          bildirimPanel.classList.remove("acik");
        }
      });
    }

    // Mobil kenar çubuğu: hamburger ile aç/kapat
    const menuToggleBtn = document.getElementById("menuToggleBtn");
    const sidebarEl = document.getElementById("sidebarEl");
    const sidebarOverlay = document.getElementById("sidebarOverlay");

    function menuyuKapat() {
      sidebarEl.classList.remove("acik");
      sidebarOverlay.classList.remove("acik");
      document.body.classList.remove("menu-acik");
      menuToggleBtn.setAttribute("aria-expanded", "false");
    }

    if (menuToggleBtn && sidebarEl && sidebarOverlay) {
      menuToggleBtn.addEventListener("click", () => {
        const aciliyor = !sidebarEl.classList.contains("acik");
        sidebarEl.classList.toggle("acik", aciliyor);
        sidebarOverlay.classList.toggle("acik", aciliyor);
        document.body.classList.toggle("menu-acik", aciliyor);
        menuToggleBtn.setAttribute("aria-expanded", aciliyor ? "true" : "false");
      });

      sidebarOverlay.addEventListener("click", menuyuKapat);
      sidebarEl.querySelectorAll("a").forEach(link => link.addEventListener("click", menuyuKapat));

      document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") menuyuKapat();
      });

      window.addEventListener("resize", () => {
        if (window.innerWidth > 800) menuyuKapat();
      });
    }

    // PWA: service worker kaydı
    if ("serviceWorker" in navigator) {
      navigator.serviceWorker.register("service-worker.js", { updateViaCache: "none" }).then((kayit) => {
        kayit.update();
      }).catch(() => {});
    }

    // PWA: yükle butonu
    let ertelenmisYuklemeOlayi = null;
    const uygulamaYukleBtn = document.getElementById("uygulamaYukleBtn");

    window.addEventListener("beforeinstallprompt", (e) => {
      e.preventDefault();
      ertelenmisYuklemeOlayi = e;
      if (uygulamaYukleBtn) uygulamaYukleBtn.style.display = "flex";
    });

    if (uygulamaYukleBtn) {
      uygulamaYukleBtn.addEventListener("click", async () => {
        if (!ertelenmisYuklemeOlayi) return;
        ertelenmisYuklemeOlayi.prompt();
        await ertelenmisYuklemeOlayi.userChoice;
        ertelenmisYuklemeOlayi = null;
        uygulamaYukleBtn.style.display = "none";
      });
    }

    window.addEventListener("appinstalled", () => {
      if (uygulamaYukleBtn) uygulamaYukleBtn.style.display = "none";
    });
  </script>
</body>
</html>
