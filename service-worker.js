// Statik dosyalar (css, logo) için basit bir service worker.
// PWA yükleme özelliğinin çalışması için gerekli.

const CACHE_ADI = "butcetakip-cache-v2";
const ONBELLEGE_ALINACAKLAR = [
  "css/index.css",
  "css/ortak.css",
  "logo/icon.svg",
  "logo/favicon.svg",
  "logo/pwa/icon-192.png",
  "logo/pwa/icon-512.png",
];

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE_ADI).then((cache) => cache.addAll(ONBELLEGE_ALINACAKLAR))
  );
  self.skipWaiting();
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys().then((isimler) =>
      Promise.all(
        isimler.filter((isim) => isim !== CACHE_ADI).map((isim) => caches.delete(isim))
      )
    )
  );
  self.clients.claim();
});

self.addEventListener("fetch", (event) => {
  const url = new URL(event.request.url);

  // .php sayfaları her zaman doğrudan sunucudan gelsin
  if (url.pathname.endsWith(".php") || event.request.method !== "GET") {
    return;
  }

  // Statik dosyalar: önce ağdan dene, olmazsa önbellekten göster
  event.respondWith(
    fetch(event.request)
      .then((agYaniti) => {
        const kopya = agYaniti.clone();
        caches.open(CACHE_ADI).then((cache) => cache.put(event.request, kopya));
        return agYaniti;
      })
      .catch(() => caches.match(event.request))
  );
});
