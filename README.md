### Requirements

-   **SERVER**: Apache 2 or NGINX.
-   **RAM**: 3 GB or higher.
-   **PHP**: 8.1 or higher
-   **For MySQL users**: 5.7.23 or higher.
-   **For MariaDB users**: 10.2.7 or Higher.
-   **Node**: 8.11.3 LTS or higher.
-   **Composer**: 2.5 or higher

### Installation and Configuration

##### Execute these commands below, in order

```
composer create-project
```

-   Find **.env** file in root directory and change the **APP_URL** param to your **domain**.

-   Also, Configure the **Mail** and **Database** parameters inside **.env** file.

```
php artisan krayin-crm:install
```

**To execute Krayin**:

##### On server:

Warning: Before going into production mode we recommend you uninstall developer dependencies.
In order to do that, run the command below:

> composer install --no-dev

```
Open the specified entry point in your hosts file in your browser or make an entry in hosts file if not done.
```

##### On local:

```
php artisan route:clear
php artisan serve
```


**How to log in as admin:**

> _http(s)://example.com/admin/login_

```
email:admin@example.com
password:admin123
```

### Seeding Data dengan Custom Attributes (EAV) – Penting

Krayin menggunakan pola EAV (Entity–Attribute–Value) untuk banyak field di UI. Pada form, nilai atribut dibaca dari tabel `attribute_values` dan dapat menimpa nilai kolom inti (mis. `persons.organization_id`, `organizations.address`, dll). Akibatnya, data hasil seeding yang hanya mengisi kolom tabel bisa terlihat di listing, tetapi kosong/tidak muncul di komponen lookup sampai `attribute_values` ikut diisi.

- Gejala umum jika `attribute_values` tidak diisi:
  - Field lookup (mis. Organization pada Person, Person pada Quote) tampak kosong di halaman edit, baru muncul setelah Anda klik Save sekali.
  - Error filter di lookup: `item.name is null` saat pencarian.

- Guardrails saat membuat seeder untuk entitas yang punya custom attributes:
  - Isi tabel utama (mis. `persons`, `organizations`) seperti biasa menggunakan `insert/upsert`.
  - Lalu isi juga tabel EAV `attribute_values` untuk atribut yang dipakai di form/lookup.
  - Set kolom `user_id` (owner) pada record utama DAN simpan juga sebagai attribute (`user_id` → tipe lookup/integer) agar lolos batasan ACL (authorized user ids) di pencarian/lookup.
  - Komponen lookup mulai mencari setelah ≥ 3 karakter; ini normal.

- Checklist atribut yang umum:
  - Persons (`entity_type = persons`): `name` (text), `emails` (json), `contact_numbers` (json), `job_title` (text), `user_id` (integer/lookup), `organization_id` (integer/lookup).
  - Organizations (`entity_type = organizations`): `name` (text), `address` (json), `user_id` (integer/lookup).

- Contoh pola seeding ringkas (pseudo):

```
// 1) Upsert tabel utama
DB::table('persons')->upsert($rows, ['unique_id'], ['name', 'emails', '...']);

// 2) Ambil id atribut yang relevan
$attrIds = DB::table('attributes')
  ->where('entity_type', 'persons')
  ->whereIn('code', ['name','emails','contact_numbers','job_title','user_id','organization_id'])
  ->pluck('id','code');

// 3) Susun baris EAV sesuai tipe field (text/json/integer) dan upsert
DB::table('attribute_values')->upsert($attributeValues,
  ['entity_type','entity_id','attribute_id'],
  ['text_value','boolean_value','integer_value','float_value','datetime_value','date_value','json_value']);

// Catatan: set juga owner `user_id` (mis. admin@example.com) pada record utama dan EAV.
```

- Jika sudah terlanjur seeding tanpa EAV: jalankan `php artisan migrate:fresh --seed` (atau seed ulang seeder terkait) lalu `php artisan optimize:clear`.
