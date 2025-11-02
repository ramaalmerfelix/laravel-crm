# AGENTS.md

> **Tujuan dokumen**: Panduan operasional untuk _coding agent_ yang mengerjakan Tugas Akhir: **Rancang Bangun Sistem Informasi Analytical CRM untuk Pemesanan Custom Engineering (PT Famindo Teknik Karya Utama)** berbasis **Krayin (Laravel CRM)** dengan metode **Market Basket Analysis (Apriori)** — _upgrade‑safe_ tanpa memodifikasi core Krayin.

---

## 1) Gambaran Proyek
- **Goal utama**: menambah modul **Analytical CRM** pada Krayin untuk:
  - Mengambil data transaksi dari Quotes & Quote Items yang terkait Leads berstatus "won" (alias: Custom Engineering Order).
  - Menjalankan **Apriori** untuk menemukan _association rules_ (X ⇒ Y) beserta **support**, **confidence**, **lift**.
  - Menyajikan **UI admin** untuk analisis & ekspor.
  - Memberi **rekomendasi item** di alur Lead/Quote/Order (cross-sell/upsell).
- **Platform**: Windows 11, **Laragon** + PHPStorm (lokal). Docker tersedia sebagai alternatif.
- **Prinsip**: **Jangan ubah folder `vendor/…` (core Krayin)**. Seluruh kerja dilakukan via **package/module kustom** + event/listener/override view & config.

---

## 2) Stack & Versi yang Disarankan
- **Krayin**: `v2.1.5` (stabil).
- **PHP**: 8.1/8.2 (Laragon default 8.3 juga OK jika dependensi terpenuhi). Pastikan ekstensi aktif: `zip`, `intl`, `gd`, `fileinfo`, `exif`.
- **Composer**: ≥ 2.5 (Laragon Full sudah include; atau install global).
- **Node**: ≥ 16.16 LTS (untuk asset build jika diperlukan).
- **DB**: MySQL ≥ 8.0 / MariaDB ≥ 10.3.

---

## 3) Setup Lingkungan Lokal
### 3.1 Laragon
1. Pastikan **Composer** tersedia (Terminal Laragon → `composer -V`).
2. Aktifkan ekstensi (Laragon → PHP → Extensions): **zip**, **intl**, **gd**, **fileinfo**, **exif**.
3. Restart All.

### 3.2 Membuat Proyek (dua pola)
- **Pola A – Clone yang sudah ada**
  - Jalankan: `composer install -o`
  - Jika pertama kali: `php artisan optimize:clear` dan `php artisan storage:link`
- **Pola B – Buat proyek baru dari template**
  ```bash
  composer create-project krayin/laravel-crm krayin-app
  cd krayin-app
  php artisan krayin-crm:install
  ```

### 3.3 `.env` minimal (dev)
```env
APP_NAME="Krayin CRM"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000   # atau http://krayin.test jika pakai virtual host Laragon
APP_TIMEZONE=Asia/Jakarta
APP_LOCALE=en
APP_CURRENCY=IDR

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel-crm
DB_USERNAME=root
DB_PASSWORD=            # kosong default Laragon kecuali diubah

CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=database   # gunakan database queue agar analisis berjalan di background

# Email dev (pilih salah satu)
MAIL_MAILER=log         # paling simpel; tidak kirim email sungguhan
# atau MailHog (kalau dijalankan)
# MAIL_MAILER=smtp
# MAIL_HOST=127.0.0.1
# MAIL_PORT=1025
# MAIL_USERNAME=null
# MAIL_PASSWORD=null
# MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS=laravel@krayincrm.com
MAIL_FROM_NAME="${APP_NAME}"

FILESYSTEM_DISK=public
```
Lalu:
```bash
php artisan storage:link
php artisan optimize:clear
```

### 3.4 Menjalankan Aplikasi
```bash
php artisan serve   # buka http://localhost:8000
```

> **Catatan**: Jika memakai DB di Docker, sesuaikan `DB_PORT` (mis. 3307) dan kredensial.

### 3.5 Worker Antrian (Dev)
- Jalankan worker di terminal/runner terpisah agar job analisis berjalan di background:
  - `php artisan queue:work --queue=analytics,default --timeout=3600 --tries=1`
- PHPStorm/IntelliJ: tersedia konfigurasi run `.run/Queue Worker.run.xml` (jalankan bersamaan dengan "Artisan Serve").
- Setelah mengubah kode job/service, jalankan `php artisan queue:restart` agar worker memuat ulang.

---

## 4) Arsitektur _Upgrade‑Safe_
- **Jangan** modifikasi `vendor/` (core Krayin & dependensi).
- Tambahan fitur dibungkus di **package kustom**: `packages/Famindo/AnalyticalCRM`.
- Gunakan **event/listener**, **service container binding**, **override view** (publish bila perlu), **policy/ACL**.

### 4.1 Struktur Package Kustom
```
packages/
  Famindo/AnalyticalCRM/
    src/
      Providers/ServiceProvider.php
      Http/Controllers/
      Models/
      Services/
      Console/
    routes/admin.php
    database/migrations/
    Resources/views/
    composer.json (opsional, jika dijadikan package standalone)
```
- Daftarkan `ServiceProvider` di app (config/komposer autoload PSR‑4).
- Tambahkan **menu Admin**: “Analytics → Market Basket (Apriori)”.
- Tambahkan **permissions/ACL** untuk modul ini.

---

## 5) Sumber Data Transaksi (Quotes/Leads)
Untuk analisis Apriori, kita memanfaatkan entitas core Krayin tanpa menambah tabel order kustom:

- `leads` — hanya yang berada pada pipeline stage "won" (default pipeline)
- `quotes` — terkait ke lead di atas
- `quote_items` — daftar produk per quote

Alias: istilah "Custom Engineering Order" dipetakan ke kombinasi `quotes` + `quote_items` milik `leads` yang sudah "won". Tidak diperlukan tabel `engineering_orders`/`engineering_order_items`.

---

## 6) Pipeline Apriori
### 6.1 ETL Transaksi (Sumber Quotes/Leads)
- Sumber: `quote_items` yang bergabung ke `quotes` dan `leads`.
- Filter utama:
  - Hanya `leads` pada stage "won" (default pipeline).
  - Rentang tanggal berdasarkan `quotes.expire_at` atau `leads.closed_at` (standar modul menggunakan `quotes.expire_at`).
  - Filter segmen opsional: organization/person, industri, nilai quote.
- Bentuk transaksi: kelompokkan item per `quote_id`  `[[skuA, skuB], [skuC, skuD, ], ]`.

Contoh sketsa query (pseudocode):
```
SELECT qi.quote_id, p.sku
FROM quote_items qi
JOIN quotes q ON q.id = qi.quote_id
JOIN leads  l ON l.id = q.lead_id
JOIN products p ON p.id = qi.product_id
WHERE l.lead_pipeline_stage_id = <WON_STAGE_ID>
  AND q.expire_at BETWEEN :from AND :to;
```
 
### 6.2 Algoritma
- Tambah dependensi (di root proyek):
  ```bash
  composer require php-ai/php-ml
  ```
- Contoh pemakaian ringkas:
  ```php
  use Phpml\Association\Apriori;

  $assoc = new Apriori($minSupport, $minConfidence);
  $assoc->train($transactions, []);
  $rules = $assoc->getRules();
  ```

### 6.3 Persistensi & Snapshot
- Snapshot run:
  - Tabel `apriori_runs`: `id`, `label`, `period_start`, `period_end`, `params_json`, `created_by`, `created_at`.
- Rules:
  - Tabel `apriori_rules`: `id`, `run_id` (FK ke `apriori_runs`), `lhs` (JSON), `rhs` (JSON), `support`, `confidence`, `lift`, `period_start`, `period_end`, `params_json`, `created_by`, `created_at`.
- (Opsional) transaksi:
  - Tabel `apriori_transactions`: `id`, `run_id` (FK), `quote_id` (nullable), `items_json`, `created_at`.

Catatan: migrasi lama yang mereferensikan `engineering_orders` sudah tidak digunakan; hapus/rollback agar `migrate:fresh --seed` tidak gagal.

### 6.4 Scheduler & CLI
- Console command: `analytics:apriori` (param: `--from`, `--to`, `--support`, `--confidence`, `--min-items`, `--persist-transactions`, `--label="Q1 2025"`).
- Tambah jadwal di `app/Console/Kernel.php` (harian/mingguan).

### 6.4a Eksekusi Background (Queue)
- Analisis Apriori dieksekusi via job terpisah: `Famindo\\AnalyticalCRM\\Jobs\\RunAprioriJob`.
- Controller admin akan `dispatch()` job ini ke queue `analytics` sehingga request web tidak terblokir.
- Pastikan worker aktif (lihat §3.5) agar job diproses.

### 6.5 UI Admin
- Halaman Market Basket (Apriori):
  - Form parameter: `from`, `to`, `min_support`, `min_confidence`, `min_items`, opsi "Persist transactions".
  - Snapshot selector: pilih `apriori_runs` mana yang ingin ditampilkan/diaktifkan.
  - Tabel hasil: LHS, RHS, Support, Confidence, Lift, Period Start/End, Created At (berdasarkan snapshot terpilih).
  - Aksi: Export CSV; (opsional) Set active snapshot untuk dipakai rekomendasi.

### 6.6 Integrasi ke Alur CRM
- **Widget rekomendasi** di halaman Lead/Quote/Order: ketika item dipilih → tampilkan saran item tambahan dari rules (X⇒Y).
- Tombol “Tambahkan” untuk push item ke quote/order.
- Logging **acceptance rate** rekomendasi.

---

## 7) Alur Kerja (Roadmap Tugas)
1. **Fondasi**: project jalan, ekstensi PHP aktif, `.env` beres.
2. Data: seed `leads` (stage "won"), `quotes`, `quote_items` sesuai distribusi produk; mapping Persons/Organizations konsisten.
3. **Package**: siapkan `Famindo/AnalyticalCRM` (provider, routes, menu, ACL).
4. **ETL & Apriori**: service ETL → training → simpan `apriori_rules`.
5. **CLI & Scheduler**: command + jadwal rutin.
6. **UI Admin**: parameter, tabel hasil, ekspor.
7. **Integrasi UI CRM**: widget rekomendasi di Lead/Quote/Order.
8. **Testing & Evaluasi**: unit test ETL, validasi metrik, uji performa dasar.
9. **Dokumentasi**: arsitektur, ERD, flow, eksperimen & hasil, batasan & saran.
10. **Packaging/Deploy** (opsional): env prod, backup, docker-compose.

---

## 8) Perintah & Cheat‑Sheet
```bash
# dependency & util
composer install -o
composer dump-autoload
php artisan optimize:clear
php artisan storage:link

# migrasi & seeding
php artisan migrate --seed

# membuat artefak inti modul
php artisan make:controller Admin/AprioriController --invokable
php artisan make:command AnalyticsApriori
# (Jika perlu) migrasi snapshot & rules
php artisan make:migration create_apriori_runs_table
php artisan make:migration create_apriori_rules_table
# (Opsional) transaksi untuk audit
php artisan make:migration create_apriori_transactions_table

# server dev
php artisan serve

# worker antrian (jalan di terminal/runner terpisah)
php artisan queue:work --queue=analytics,default --timeout=3600 --tries=1
# restart worker setelah update kode job/service
php artisan queue:restart

# analitik (contoh CLI)
php artisan analytics:apriori --from=2025-01-01 --to=2025-06-30 --support=0.05 --confidence=0.6 --min-items=2 --label="H1 2025" --persist-transactions
```

---

## 9) Standar Kode & Guardrails
- **Jangan** modif `vendor/`.
- Business logic di `Services/` (mudah di‑unit test), controller tipis.
- Validasi parameter (periode, support/confidence), _empty dataset_ harus ditangani elegan.
- Pastikan query ETL **efisien** (index pada `quote_items.quote_id`, `quote_items.product_id`, `quotes.expire_at`/`leads.closed_at`).
- Data sensitif: hindari log isi _credentials_; gunakan `.env` & config.

### 9.1 Seeding Entitas Core Krayin (EAV + LogsActivity)
- Banyak entitas core (persons, organizations, warehouses, products, dst.) memakai EAV (`attributes`/`attribute_values`) dan trait `LogsActivity` untuk timeline.
- Agar data hasil seeding muncul dengan benar di UI (form/lookup) dan menulis timeline “Created/Updated”, ikuti pola dua langkah berikut:
  1) Simpan kolom inti via Eloquent Model entitas terkait (contoh: `Webkul\Warehouse\Models\Warehouse::create([...])` atau update lalu `save()`). Ini akan memicu event `created/updated` dari `LogsActivity` sehingga timeline mencatat “Created/Updated”.
  2) Simpan nilai EAV via `Webkul\Attribute\Repositories\AttributeValueRepository->save([...])` dengan payload minimal:
     - `entity_type` = kode entitas (mis. `persons`, `organizations`, `warehouses`).
     - `entity_id` = ID record yang baru dibuat/diperbarui.
     - Pasang nilai atribut yang dipakai UI (mis. persons: `name`, `emails`, `contact_numbers`, `job_title`, `user_id`, `organization_id`; organizations: `name`, `address`, `user_id`; warehouses: `name`, `description`, `contact_name`, `contact_emails`, `contact_numbers`, `contact_address`).
- Hindari `DB::table(...)->insert` langsung untuk entitas EAV, karena UI membaca dari `attribute_values` dan timeline tidak akan menulis “Created”.
- Jika memilih lewat Repository core (mis. `WarehouseRepository->create($data)`), jangan sisipkan `entity_type` ke payload model (akan error kolom tidak ada). Simpan EAV terpisah memakai `AttributeValueRepository->save()` seperti di langkah (2).
- Persons/Organizations: set juga `user_id` (owner) pada record inti dan EAV agar lookup/ACL bekerja mulus di UI.

### 9.2 Catatan Khusus (Short‑Term Project)
- Untuk tugas ini bersifat jangka pendek/eksperimental dan tidak ditujukan ke produksi, maka diperbolehkan melakukan perubahan pada kode di `packages/Webkul/**` bila diperlukan untuk mempercepat implementasi/bugfix.
- Tetap dilarang mengubah kode di `vendor/**` karena:
  - File di `vendor/` dikelola Composer dan tidak masuk kendali versi kita (rawan ter‑overwrite),
  - Tidak aman untuk upgrade/deploy.
- Usahakan perubahan di `packages/Webkul/**` tetap minimal, terdokumentasi, dan mudah di‑rollback. Jika nantinya dibutuhkan upgrade‑safe, pindahkan ke package kustom (override routes/controller/datagrid/binding) sesuai pola di dokumen ini.

---

## 10) Testing & Evaluasi
- **Unit test**: ETL builder (jumlah transaksi, konsistensi item), perhitungan metrik.
- **Sanity check**: sample rules ditinjau domain expert.
- **Metrik**: distribusi support/confidence/lift; di integrasi UI ukur **acceptance rate** rekomendasi.

---

## 11) Deliverables
- Kode modul `packages/Famindo/AnalyticalCRM` + migration & seeder.
- Console command & scheduler aktif.
- Job queue untuk analisis (`RunAprioriJob`) + konfigurasi runner PHPStorm `.run/Queue Worker.run.xml`.
- UI Admin Analytics + ekspor CSV.
- Widget rekomendasi di Lead/Quote/Order.
- Dokumentasi: ERD, arsitektur, flow ETL/Apriori, panduan instal, hasil uji & analisis.

---

## 12) Known Issues & Tips
- Jika Composer mengeluh `ext-zip` → aktifkan `zip` di Laragon (PHP → Extensions). Terminal baru setelah perubahan.
- `.env` untuk email di dev: gunakan `MAIL_MAILER=log` atau MailHog; jangan pakai host `mailhog` kecuali via docker-compose.
- App URL harus sesuai cara run (artisan serve vs virtual host Laragon).
 - Jika `migrate:fresh --seed` gagal karena referensi `engineering_orders`, hapus/rollback migrasi legacy tersebut dan gunakan skema `apriori_runs`/`apriori_rules`/`apriori_transactions` sesuai dokumen ini.
- Jika worker queue tidak dijalankan, analisis yang dikirim dari UI akan berstatus `queued` dan tidak berjalan. Jalankan worker: `php artisan queue:work --queue=analytics,default --timeout=3600 --tries=1`.
- Untuk job berat, pastikan `--timeout` worker besar (mis. 3600) dan `retry_after` di `config/queue.php` (untuk koneksi `database`/`redis`) lebih besar dari timeout.

---

## 12a) Branding Overrides (Logo & Powered By)
- Ganti Logo Admin (upgrade‑safe, via seeder):
  - Letakkan file logo di `database/seeders/assets/admin-logo.(png|jpg|jpeg|webp|svg)`.
  - (Opsional) Favicon di `database/seeders/assets/favicon.(png|ico|svg|jpg|jpeg|webp)`.
  - Seeder: `database/seeders/AppLogoSeeder.php`.
    - Menyalin logo ke disk `public` (`storage/app/public/configuration/...`).
    - Menulis `core_config` keys:
      - `general.general.admin_logo.logo_image` (utama)
      - `general.design.admin_logo.logo_image` (kompatibilitas view lama)
      - `general.design.admin_logo.favicon` (jika favicon ada)
  - Jalankan: `php artisan storage:link` (sekali) lalu `php artisan db:seed --class=AppLogoSeeder`.
  - Alternatif manual via UI: Admin → Configuration → General → General → Admin Logo.

- Ganti Footer (teks bawah aplikasi):
  - Seeder: `database/seeders/AppFooterSeeder.php`.
    - Ubah variabel `$footerHtml` sesuai kebutuhan.
    - Menulis `core_config` key `general.settings.footer.label` (HTML diperbolehkan).
  - Jalankan: `php artisan db:seed --class=AppFooterSeeder`.
  - Lokasi UI: Admin → Configuration → General → Settings → Footer.

- Ganti “Powered by …” di halaman Login (tanpa ubah core):
  - Override translasi: `resources/lang/vendor/admin/en/app.php`.
    - Key: `admin::app.components.layouts.powered-by.description`.
    - Contoh isi saat ini: `PT Famindo Teknik Karya Utama — Market Basket Analysis (Apriori) for sales recommendations.`
  - Setelah ubah, jalankan `php artisan optimize:clear` lalu refresh halaman login.

- Catatan teknis:
  - View yang membaca logo mengambil dari `core()->getConfigData('general.general.admin_logo.logo_image')` dan sebagian lama `general.design.admin_logo.logo_image`.
  - Storage URL menggunakan disk `public` → pastikan `APP_URL` benar dan symlink `public/storage` tersedia.
  - Jika ingin “tahun berjalan” di footer tanpa reseed tahunan, pertimbangkan override view untuk render dinamis; saat ini seeder menempelkan tahun saat dieksekusi.

---

## 13) Kontak & Eskalasi
- **Teknis**: masalah dependency/ekstensi PHP/Composer.
- **Data**: seeding & konsistensi mapping Leads/Quotes/Quote Items.
- **UX**: kebutuhan tampilan & metrik di UI admin.

> Selesai. Ikuti urutan di §7 sebagai _sprint plan_. Jika ada perubahan requirement, update dokumen ini terlebih dahulu sebelum implementasi.
