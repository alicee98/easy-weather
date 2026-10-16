# EasyWeather PHP

Aplikasi praktik **Informatika × Geografi**, dikembangkan oleh **Alfi, Azlan, Ridho, Kayca, dan Raudha**. Versi 2 menggunakan PHP sebagai dasar aplikasi: halaman dirender server, pemeriksaan akun, cookie sesi, akses lokasi, pencarian kota, permintaan prakiraan, dan analisis awan diproses oleh PHP. HTML tetap menjadi format keluaran halaman; CSS dan JavaScript mengatur tampilan dan interaksi browser.

## Menjalankan di XAMPP

1. Gunakan XAMPP dengan PHP **8.2 atau lebih baru** (direkomendasikan 8.3).
2. Salin proyek ke `C:\xampp\htdocs\easy-weather`. Jangan salin folder `.git` atau `node_modules`.
3. Jalankan **Apache**. MySQL tidak diperlukan.
4. Buka **http://localhost/easy-weather/index.php**. Jangan membuka berkas dengan `file://`.
5. Aktifkan ekstensi `curl` pada `php.ini` jika belum aktif, lalu restart Apache. Komputer tetap memerlukan internet untuk layanan Open-Meteo.

Pada localhost, kunci penandatangan sesi dibuat otomatis pada `var/key.php` saat halaman pertama kali dibuka. Folder ini harus dapat ditulis PHP dan tidak boleh dimasukkan ke Git. `.env.example` hanya contoh; PHP tidak otomatis membaca berkas `.env`. Untuk konfigurasi manual, set `APP_KEY` sebagai environment variable server dengan nilai acak minimal 32 karakter. Jangan bagikan kunci tersebut.

Alternatif pengembangan: `php -S localhost:8080`, kemudian buka http://localhost:8080/index.php. Server bawaan PHP hanya untuk pengembangan.

## Akun praktik

Username: `alfi1`, `azlan2`, `ridho3`, `kayca4`, `raudha5`.
Password bersama untuk demonstrasi: `easyweather`.

Daftar akun tidak ditampilkan pada dialog login dan password diperiksa memakai `password_verify` terhadap hash di PHP. Ini tetap aplikasi praktik dengan kredensial bersama, tanpa pendaftaran atau database; bukan sistem identitas produksi.

## Fitur

- Jakarta dapat dilihat tanpa login.
- Lokasi lain dan pencarian kota harus melewati pemeriksaan sesi pada endpoint PHP. Mengubah tampilan browser tidak membuka API yang dilindungi.
- Cuaca terkini, 24 jam ke depan, tujuh hari prakiraan, dan analisis tiga lapisan awan.
- Menu kiri dengan ikon garis tiga, navigasi bagian cuaca, profil, dan logout.
- Grafik 24 jam dengan pilihan suhu, peluang hujan, dan angin.
- Indeks UV, jarak pandang, tekanan udara, titik embun, hembusan dan arah angin.
- Presipitasi per jam dan total harian, waktu terbit/terbenam, dan durasi siang.
- Estimasi genus awan dengan alasan, alternatif, serta kepastian kualitatif.
- Saran aktivitas, perbandingan jendela aktivitas siang, dan catatan perhatian dari model.
- Pilihan Celsius/Fahrenheit, tema terang/gelap, serta maksimal enam lokasi favorit per akun di perangkat.
- Lokasi perangkat dipakai hanya setelah tombol pengguna dan izin browser; fitur ini memerlukan login.
- Panduan sepuluh genus awan dan identitas tim.
- Logout menghapus akses sesi di browser dan mengembalikan Jakarta.
- Pembaruan manual dan otomatis setiap 10 menit saat halaman aktif.
- Kegagalan API ditampilkan tanpa membuat angka cuaca pengganti.

## Struktur berkas

| Berkas | Tanggung jawab |
| --- | --- |
| `index.php` | Titik masuk Apache dan XAMPP |
| `api/index.php` | Controller HTTP dan JSON; mengarahkan action ke fungsi PHP |
| `auth.php` | Akun praktik, verifikasi password, cookie sesi, dan CSRF |
| `weather.php` | Validasi koordinat dan akses lokasi, Open-Meteo, serta analisis awan |
| `view.php` | Template halaman yang dirender PHP dan data awal sesi |
| `app.js` | Interaksi antarmuka dan pemanggilan endpoint PHP |
| `weather.js` | Pemetaan label/ikon cuaca dan validasi tampilan, tanpa kredensial akun |
| `style.css`, `icon.svg` | Aset antarmuka |
| `.htaccess` | Prioritas index.php, penonaktifan daftar folder, dan pembatasan berkas internal di Apache |
| `tests/` | Pengujian PHP, JavaScript, dan alur HTTP |
| `vercel.json` | Routing serta runtime PHP untuk deployment |

Lima berkas PHP aplikasi adalah `index.php`, `view.php`, `api/index.php`, `auth.php`, dan `weather.php`. Berkas pengujian serta `var/key.php` yang dibuat otomatis tidak dihitung sebagai berkas fitur. Modul `auth.php` dan `weather.php` dimuat oleh controller melalui `require_once`; keduanya tidak dapat dijalankan langsung.

## API PHP

Semua endpoint berada pada `api/index.php?action=...`.

| Action | Metode | Akses |
| --- | --- | --- |
| `session` | GET | Informasi sesi dan token CSRF |
| `login` | POST JSON | Username/password, membutuhkan header `X-CSRF-Token` |
| `logout` | POST JSON | Membutuhkan token CSRF sesi aktif |
| `weather` | GET | Jakarta publik; koordinat lain memerlukan login |
| `search` | GET | Login wajib; pencarian dibatasi negara Indonesia |

Sesi ditandatangani HMAC SHA-256 dan disimpan dalam cookie **HttpOnly**, **SameSite=Lax**, serta **Secure** pada HTTPS. Cookie berlaku selama sesi browser, dengan batas token dua jam. Tidak menggunakan sessionStorage maupun berkas sesi lokal yang tidak dapat diandalkan pada serverless. Token CSRF dirotasi saat login/logout. Karena bersifat stateless, token yang telah dicuri tidak dapat dicabut secara individual sebelum kedaluwarsa; gunakan penyimpanan sesi server jika aplikasi dikembangkan untuk penggunaan produksi. Provider API dibatasi ke dua host Open-Meteo, bukan URL bebas dari pengguna.

## Deployment Vercel

PHP memakai runtime komunitas **vercel-php@0.7.4** (PHP 8.3), bukan dukungan PHP bawaan. Preset **Other**, tanpa build command, direktori root proyek. Set environment variable **APP_KEY** dengan nilai acak minimal 32 karakter untuk Production/Preview sebelum deployment. Server menolak beroperasi di Vercel jika kunci belum diatur. Kunci tidak berada dalam repository. Perubahan pada branch produksi `main` memicu deployment otomatis.

## Pemeriksaan

```sh
php -l index.php
php -l view.php
php -l api/index.php
php -l auth.php
php -l weather.php
php tests/server_test.php
npm run check
npm test
```

Untuk pengujian alur HTTP, jalankan PHP server di terminal pertama, lalu `python3 tests/http_test.py` di terminal lain. Berkas pengujian tidak memerlukan database atau dependensi npm. Node.js diperlukan hanya untuk pengujian JavaScript, bukan untuk menjalankan aplikasi pada XAMPP.

## Sumber dan batas analisis

[Open-Meteo Forecast API](https://open-meteo.com/en/docs) dan [Geocoding API](https://open-meteo.com/en/docs/geocoding-api), dengan atribusi [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/). Lokasi berdasarkan GeoNames. Gunakan sesuai ketentuan nonkomersial layanan.

Kondisi terkini berasal dari model, bukan sensor tepat di lokasi pengguna. Analisis awan PHP memilih lapisan dominan dari tutupan rendah (hingga sekitar 3 km), menengah (3–8 km), dan tinggi (di atas 8 km). Jika semuanya di bawah 20%, hasilnya awan sedikit; ini aturan aplikasi. Kode badai petir menjadi indikasi kondisi konvektif. Contoh genus merupakan kemungkinan pembelajaran yang perlu dikonfirmasi secara visual. Lapisan dapat bertumpuk dan persentasenya tidak harus berjumlah 100%. Referensi genus: [WMO Cloud Atlas](https://cloudatlas.wmo.int/en/clouds-genera.html). Periksa [BMKG](https://www.bmkg.go.id/) untuk peringatan resmi.

## Preferensi dan interpretasi

Tema, satuan suhu, dan lokasi favorit disimpan di localStorage perangkat. Favorit dipisahkan menurut nama akun praktik. Password dan token sesi tidak disimpan di localStorage. Logout kembali ke Jakarta dan menutup akses lokasi lain. Geolokasi tidak diminta otomatis.

Estimasi genus pada `ew_cloud_prediction` adalah heuristik dari tutupan lapisan, kode cuaca, kelembapan, dan siang/malam. Kepastian Rendah/Sedang bukan persentase akurasi atau probabilitas terkalibrasi. Tutupan sedikit tetap bisa memberi kandidat lemah; jika semua lapisan nol, tidak dipilih genus.

Catatan perhatian dan rekomendasi aktivitas adalah turunan data model, bukan peringatan resmi BMKG. Jendela aktivitas membandingkan jam siang 07–18 dalam 24 jam ke depan dengan data hujan, UV, suhu, serta angin yang lengkap; bukan jaminan aman. Nilai fitur tambahan yang hilang tampil sebagai —.
