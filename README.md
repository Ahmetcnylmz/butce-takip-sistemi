# Bütçe Takip Sistemi

![PHP](https://img.shields.io/badge/PHP-777BB4?style=flat&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=flat&logo=mysql&logoColor=white)
![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=flat&logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=flat&logo=css3&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=flat-square&logo=javascript&logoColor=black)

Gelir, gider, abonelik, hatırlatıcı ve borç/alacaklarını tek bir yerden takip edebileceğin, PHP ve MySQL ile geliştirilmiş kişisel bütçe yönetim sistemi.

---

## Ekran Görüntüleri

| Genel Bakış | Karanlık Mod |
|---|---|
| ![Genel Bakış](screenshot/anasayfa.png) | ![Karanlık Mod](screenshot/darkmode.png) |

| Genel Gider | Yıllık Özet |
|---|---|
| ![Genel Gider](screenshot/genelgider.png) | ![Yıllık Özet](screenshot/yilliközet.png) |

---

## Özellikler

### Genel
- **Genel Bakış (Dashboard):** Aylık gelir/gider özeti, kategori bazlı gider dağılım grafiği, son 6 aylık genel gider trendi
- **Genel Gider (Ev/Market) Takibi:** Kategorilere göre harcama girişi, kategori bazlı limit belirleme, tekrarlayan (otomatik yenilenen) harcamalar
- **Kategori Bazlı Bütçe Önerisi:** Son 3 ayın ortalamasına göre otomatik limit önerisi
- **Abonelik Takibi:** Aylık/yıllık abonelikler, otomatik yenilenen ödeme tarihleri, iptal/tekrar aktif etme
- **Hatırlatıcılar:** Takvim görünümlü hatırlatıcı sistemi, güne tıklayarak filtreleme
- **Borç/Alacak Takibi:** Borç ve alacakların ayrı ayrı takibi, taksit ve vade yönetimi
- **Yıllık Özet:** 12 aylık gider grafiği, kategori bazlı yıllık kırılım, Excel'e (CSV) aktarma
- **Aylık Gider Hedefi:** Kendi belirlediğin bütçe hedefine göre ilerleme takibi

### Güvenlik
- Şifreler `password_hash` ile güvenli şekilde saklanır
- CSRF koruması (tüm formlarda token doğrulama)
- Bot koruması (honeypot yöntemi)
- Giriş denemesi sınırlaması (brute-force koruması)
- Oturum güvenliği (`httponly`, `samesite` çerezler, session fixation koruması)
- "Beni Hatırla" ile güvenli otomatik giriş
- E-posta ile şifre sıfırlama
- Hesap sayfasından isteğe bağlı e-posta doğrulama

### Kullanıcı Deneyimi
- Tamamen responsive tasarım (mobil uyumlu, açılır/kapanır menü)
- Karanlık / aydınlık mod
- PWA desteği (tarayıcıdan "Yükle" ile masaüstüne/ana ekrana eklenebilir)
- E-posta bildirimleri (yaklaşan hatırlatıcılar için)

### Admin Paneli
- Kullanıcı listesi, arama ve filtreleme
- Kullanıcı rolü yönetimi (kullanıcı/admin)
- Kullanıcı silme
- İşlem geçmişi (admin aktivite logu)

---

## Kullanılan Teknolojiler

* **Backend:** ![PHP](https://img.shields.io/badge/PHP-777BB4?style=flat-square&logo=php&logoColor=white)
* **Veritabanı:** ![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=flat-square&logo=mysql&logoColor=white)
* **Frontend:** ![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=flat-square&logo=html5&logoColor=white), ![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=flat-square&logo=css3&logoColor=white), ![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=flat-square&logo=javascript&logoColor=black)
* **Arayüz İkonları:** Font Awesome

---

## Kurulum

1. Bu depoyu klonla veya indir:
   ```bash
   git clone https://github.com/Ahmetcnylmz/butce-takip-sistemi.git
   ```
2. Dosyaları XAMPP/WAMP gibi bir sunucunun `htdocs` klasörüne kopyala.
3. phpMyAdmin'den boş bir `butcetakip` adında veritabanı oluştur (tablo eklemene gerek yok, otomatik kurulur).
4. `config.php` dosyasındaki veritabanı bilgilerini kendi ortamına göre düzenle:
   ```php
   $sunucu     = "localhost";
   $kullanici  = "root";
   $sifre      = "";
   $veritabani = "butcetakip";
   ```
5. Tarayıcıdan `kayit-ol.php` sayfasını aç — tüm tablolar ilk açılışta otomatik oluşturulur.

> `sql/butcetakip.sql` dosyasını da phpMyAdmin üzerinden elle içe aktarabilirsin (isteğe bağlı, otomatik kurulum zaten yeterli).

---

# Budget Tracking System

![PHP](https://img.shields.io/badge/PHP-777BB4?style=flat&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=flat&logo=mysql&logoColor=white)
![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=flat&logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=flat&logo=css3&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=flat-square&logo=javascript&logoColor=black)

A personal finance management system built with PHP and MySQL that allows you to manage your income, expenses, subscriptions, reminders, and debts in one place.

---

## Screenshots

| Dashboard | Dark Mode |
|---|---|
| ![Dashboard](screenshot/anasayfa.png) | ![Dark Mode](screenshot/darkmode.png) |

| Expense Tracking | Annual Summary |
|---|---|
| ![Expense Tracking](screenshot/genelgider.png) | ![Annual Summary](screenshot/yilliközet.png) |

---

## Features

### General
- **Dashboard:** Monthly income/expense summary, expense distribution chart by category, and 6-month expense trend
- **Expense Tracking (Home/Market):** Categorized expense management, category-based spending limits, and recurring expenses
- **Smart Budget Suggestions:** Automatically recommends spending limits based on the average of the last 3 months
- **Subscription Tracking:** Manage monthly/yearly subscriptions, renewal dates, and activation/deactivation
- **Reminders:** Calendar-based reminder system with day-by-day filtering
- **Debt & Receivables Tracking:** Manage debts, receivables, installments, and due dates
- **Annual Summary:** 12-month expense chart, yearly category breakdown, and CSV export
- **Monthly Budget Goal:** Track your progress toward your custom monthly budget target

### Security
- Passwords are securely stored using `password_hash`
- CSRF protection (token validation for all forms)
- Bot protection (Honeypot method)
- Brute-force protection (login attempt limiting)
- Secure session management (`httponly`, `samesite` cookies, session fixation protection)
- Secure "Remember Me" functionality
- Password reset via email
- Optional email verification from the account page

### User Experience
- Fully responsive design (mobile-friendly with collapsible navigation)
- Dark / Light theme support
- PWA support (installable from the browser)
- Email notifications for upcoming reminders

### Admin Panel
- User list with search and filtering
- User role management (User/Admin)
- User deletion
- Admin activity log

---

## Technologies Used

* **Backend:** ![PHP](https://img.shields.io/badge/PHP-777BB4?style=flat-square&logo=php&logoColor=white)
* **Database:** ![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=flat-square&logo=mysql&logoColor=white)
* **Frontend:** ![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=flat-square&logo=html5&logoColor=white), ![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=flat-square&logo=css3&logoColor=white), ![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=flat-square&logo=javascript&logoColor=black)
* **Icons:** Font Awesome

---

## Installation

1. Clone or download this repository:
   ```bash
   git clone https://github.com/Ahmetcnylmz/butce-takip-sistemi.git
   ```

2. Copy the project files into the `htdocs` folder of XAMPP/WAMP.

3. Create an empty database named `butcetakip` in phpMyAdmin (no tables are required—they will be created automatically).

4. Update your database settings in `config.php`:

   ```php
   $sunucu     = "localhost";
   $kullanici  = "root";
   $sifre      = "";
   $veritabani = "butcetakip";
   ```

5. Open `kayit-ol.php` in your browser. All required database tables will be created automatically on the first run.

> You can also manually import `sql/butcetakip.sql` using phpMyAdmin (optional, since automatic setup is already available).
