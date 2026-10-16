<?php
declare(strict_types=1);
define('EW_TESTING', true);
require dirname(__DIR__) . '/api/index.php';
function check(bool $result, string $name): void { if (!$result) throw new RuntimeException('FAIL: ' . $name); echo "PASS: $name\n"; }
foreach (array_keys(EW_USERS) as $name) {
    check(ew_login_valid($name, 'easyweather'), 'akun ' . $name);
    check(!ew_login_valid($name, 'wrong'), 'password salah ' . $name);
}
check(!ew_login_valid('demo', 'cuaca123'), 'akun lama ditolak');
check(!ew_login_valid('admin', 'easyweather'), 'akun asing ditolak');
check(ew_login_valid(' ALFI1 ', 'easyweather'), 'normalisasi username');
$key = str_repeat('a', 64); $s = ew_new_session('alfi1'); $token = ew_sign($s, $key);
check(ew_decode($token, $key) === $s, 'cookie valid dapat diverifikasi');
check(ew_decode($token . 'a', $key) === null, 'cookie yang dimodifikasi ditolak');
check(ew_decode($token, str_repeat('b', 64)) === null, 'kunci salah ditolak');
$s['expires'] = time() - 1; check(ew_decode(ew_sign($s, $key), $key) === null, 'cookie kedaluwarsa ditolak');
check(ew_csrf(ew_new_session(), $key) !== ew_csrf(ew_new_session(), $key), 'CSRF dirotasi per sesi');
check(ew_can_access(-6.2, 106.8167, false), 'Jakarta publik');
check(!ew_can_access(-6.9175, 107.6191, false), 'Bandung dibatasi server');
check(ew_can_access(-6.9175, 107.6191, true), 'Bandung setelah login');
try { ew_coordinates(['latitude' => 'abc', 'longitude' => 200]); check(false, 'koordinat invalid'); } catch (InvalidArgumentException $e) { check(true, 'koordinat invalid ditolak'); }
check(ew_cloud_analysis(['cloud_cover_low'=>6,'cloud_cover_mid'=>0,'cloud_cover_high'=>97,'weather_code'=>3])['title'] === 'Lapisan tinggi dominan', 'analisis awan PHP');
check(ew_cloud_analysis(['cloud_cover_low'=>80,'cloud_cover_mid'=>80,'cloud_cover_high'=>0])['title'] === 'Lapisan rendah & menengah dominan', 'nilai dominan seri');
check(ew_cloud_analysis(['cloud_cover_low'=>0,'cloud_cover_mid'=>0,'cloud_cover_high'=>2])['title'] === 'Awan sedikit di semua lapisan', 'awan sedikit');
check(ew_cloud_analysis([])['title'] === 'Data lapisan awan belum tersedia', 'lapisan kosong');
check(ew_cloud_prediction(['cloud_cover_low'=>2,'cloud_cover_mid'=>0,'cloud_cover_high'=>3,'weather_code'=>0])['name'] === 'Cirrus', 'awan tipis tetap diberi kandidat rendah');
check(ew_cloud_prediction(['cloud_cover_low'=>0,'cloud_cover_mid'=>0,'cloud_cover_high'=>0])['name'] === 'Awan belum terindikasi', 'tanpa awan tidak mengarang genus');
check(ew_cloud_prediction(['cloud_cover_low'=>80,'cloud_cover_mid'=>60,'cloud_cover_high'=>90,'weather_code'=>95])['name'] === 'Cumulonimbus', 'petir mengindikasikan Cumulonimbus');
check(ew_cloud_prediction(['cloud_cover_low'=>75,'cloud_cover_mid'=>80,'cloud_cover_high'=>0,'weather_code'=>63])['name'] === 'Nimbostratus', 'hujan berlapis mengindikasikan Nimbostratus');
check(ew_cloud_prediction([])['confidence'] === 'Belum tersedia', 'data kosong tidak diberi kepastian');
check(ew_cloud_prediction(['cloud_cover_low'=>-1,'cloud_cover_mid'=>1,'cloud_cover_high'=>2])['name'] === 'Data belum cukup', 'nilai lapisan tidak valid ditolak');
