# Easy Weather

Aplikasi praktik **Informatika × Geografi** untuk melihat prakiraan cuaca dan mempelajari awan. Dibuat oleh **Alfi, Azlan, Ridho, Kayca, dan Raudha**.

## Fitur

- Cuaca Jakarta langsung terlihat tanpa login.
- Login demo membuka pencarian kota/kabupaten Indonesia dan pilihan kota cepat.
- Kondisi terkini: suhu, suhu terasa, kelembapan, angin, tutupan awan, dan peluang hujan jam berjalan.
- Prakiraan 6 slot per jam dan 5 hari, mengikuti zona waktu lokasi.
- Analisis lapisan awan rendah, menengah, dan tinggi; panduan 10 genus awan.
- Pembaruan manual dan pemeriksaan otomatis setiap 10 menit saat halaman aktif.
- Pesan kegagalan koneksi, pembatalan permintaan lama saat mengganti lokasi, dan logout yang mengembalikan tampilan ke Jakarta.
- Tampilan responsif untuk ponsel dan komputer.

## Menjalankan melalui XAMPP

1. Salin berkas proyek ke `C:\xampp\htdocs\easy-weather`.
2. Jalankan **Apache** pada XAMPP Control Panel. MySQL tidak diperlukan.
3. Buka **http://localhost/easy-weather/** di browser modern.
4. Sambungan internet diperlukan untuk API cuaca dan pencarian lokasi. Jangan membuka `index.html` dengan `file://` karena aplikasi menggunakan JavaScript modules.

Alternatif: jalankan `python -m http.server 8080` di direktori proyek, lalu buka http://localhost:8080. Aplikasi ini berupa HTML, CSS, dan JavaScript tanpa dependensi build. Dapat dihosting pada layanan static hosting, termasuk Vercel (preset Other, tanpa build command, output directory `.`).

## Akun praktik

- Username: `demo`
- Password: `cuaca123`

Login ini **simulasi di sisi browser**, bukan autentikasi server atau perlindungan data produksi. Penanda login disimpan di `sessionStorage` untuk tab tersebut dan dihapus saat logout. Tidak ada database atau akun pribadi; jangan gunakan kredensial pribadi. Pembatasan lokasi merupakan demonstrasi alur login, bukan kontrol akses API yang aman.

## Sumber data dan batas analisis

- [Open-Meteo Weather Forecast API](https://open-meteo.com/en/docs): kondisi model terkini, prakiraan per jam dan harian. Tidak memerlukan API key untuk penggunaan nonkomersial sesuai ketentuan layanan.
- [Open-Meteo Geocoding API](https://open-meteo.com/en/docs/geocoding-api): pencarian lokasi berdasarkan GeoNames.
- [WMO International Cloud Atlas](https://cloudatlas.wmo.int/en/clouds-genera.html): referensi genus awan.
- Data Open-Meteo diatribusikan sesuai [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/). Perhatikan batas penggunaan dan [ketentuan API](https://open-meteo.com/en/terms) sebelum penggunaan berskala besar/komersial.

Kondisi terkini adalah hasil model, bukan pengamatan sensor tepat di lokasi. Waktu data model dan waktu pengambilan ditampilkan. Angka cuaca tidak dibuat secara acak dan tidak ada fallback cuaca palsu. Jika pembaruan gagal, data lama ditandai; saat lokasi berubah, data lama dibersihkan agar tidak tertukar.

API tidak memberikan genus awan secara pasti. Lapisan model: rendah hingga sekitar 3 km, menengah 3–8 km, tinggi di atas 8 km. Aplikasi menjelaskan lapisan dengan persentase tertinggi (termasuk jika seri), dan menyebut contoh genus sebagai kemungkinan pembelajaran. Jika setiap lapisan di bawah 20%, aplikasi menampilkan awan sedikit; ambang ini merupakan aturan aplikasi. Kode WMO 95/96/99 digunakan untuk indikasi kondisi konvektif. Genus perlu dikonfirmasi melalui pengamatan visual. Lapisan bisa bertumpuk dan persentasenya tidak perlu berjumlah 100%.

Peluang hujan berasal dari prakiraan **per jam**, bukan persentase tutupan awan. Prakiraan dapat berubah; peringatan resmi Indonesia tersedia di [BMKG](https://www.bmkg.go.id/).

## Struktur

- `index.html`: halaman cuaca, panduan awan, tentang tim, dialog login dan pencarian.
- `style.css`: desain responsif.
- `app.js`: tampilan, pengambilan API, login demo dan pemilihan lokasi.
- `weather.js`: pemetaan cuaca, analisis awan, validasi data dan fungsi API.
- `tests/weather.test.js`: pengujian logika utama dan kegagalan API.

## Pemeriksaan

Dengan Node.js 22 atau lebih baru:

```sh
npm run check
npm test
```

Tidak diperlukan `npm install`. Browser modern diperlukan untuk `dialog`, ES modules, `fetch`, `AbortSignal.timeout`, dan `AbortSignal.any`.
