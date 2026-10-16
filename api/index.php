<?php
/** EasyWeather PHP controller and JSON API (PHP 8.2+). No database required. */
declare(strict_types=1);

define('EW_BOOTSTRAPPED', true);
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/weather.php';

function ew_json(array $data, int $status = 200): never { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); exit; }
// Unit tests load only pure PHP functions; no cookies, headers or HTTP dispatch.
if (defined('EW_TESTING')) return;

header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Frame-Options: DENY');
try {
    $key = ew_key();
    $session = ew_decode($_COOKIE[EW_COOKIE] ?? '', $key);
    if ($session === null) { $session = ew_new_session(); ew_cookie($session, $key); }
    $username = $session['username'] ?? null; $loggedIn = $username !== null;
    $action = $_GET['action'] ?? '';
    if (!is_string($action)) ew_json(['error' => 'Permintaan tidak valid.'], 400);
    $csrf = ew_csrf($session, $key);
    if ($action === '') {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') ew_json(['error' => 'Metode tidak diizinkan.'], 405);
        $boot = ['authenticated' => $loggedIn, 'name' => $loggedIn ? EW_USERS[$username] : null, 'csrf' => $csrf];
        require dirname(__DIR__) . '/view.php'; exit;
    }
    if (in_array($action, ['login', 'logout'], true)) {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { header('Allow: POST'); ew_json(['error' => 'Gunakan POST.'], 405); }
        if (!hash_equals($csrf, $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) ew_json(['error' => 'Sesi formulir berakhir. Muat ulang halaman.'], 403);
        if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 4096) ew_json(['error' => 'Permintaan terlalu besar.'], 413);
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) ew_json(['error' => 'Format permintaan tidak valid.'], 400);
        if ($action === 'login') {
            if (!is_string($input['username'] ?? null) || !is_string($input['password'] ?? null) || strlen($input['username']) > 40 || strlen($input['password']) > 80 || !ew_login_valid($input['username'], $input['password'])) ew_json(['error' => 'Username atau password salah. Silakan coba lagi.'], 401);
            $username = strtolower(trim($input['username']));
        } else $username = null;
        $session = ew_new_session($username); ew_cookie($session, $key);
        ew_json(['authenticated' => $username !== null, 'name' => $username !== null ? EW_USERS[$username] : null, 'csrf' => ew_csrf($session, $key)]);
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') { header('Allow: GET'); ew_json(['error' => 'Gunakan GET.'], 405); }
    if ($action === 'session') ew_json(['authenticated' => $loggedIn, 'name' => $loggedIn ? EW_USERS[$username] : null, 'csrf' => $csrf]);
    if ($action === 'weather') {
        [$lat, $lon] = ew_coordinates($_GET);
        if (!ew_can_access($lat, $lon, $loggedIn)) ew_json(['error' => 'Silakan login untuk mengakses lokasi lain.'], 401);
        $data = ew_upstream('api.open-meteo.com', '/v1/forecast', ['latitude' => $lat, 'longitude' => $lon, 'current' => 'temperature_2m,relative_humidity_2m,apparent_temperature,is_day,precipitation,weather_code,cloud_cover,cloud_cover_low,cloud_cover_mid,cloud_cover_high,wind_speed_10m,wind_direction_10m,wind_gusts_10m,pressure_msl', 'hourly' => 'temperature_2m,precipitation_probability,weather_code,is_day,precipitation,visibility,uv_index,dew_point_2m,relative_humidity_2m,wind_speed_10m,wind_direction_10m', 'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max,sunrise,sunset,daylight_duration,uv_index_max,precipitation_sum,wind_speed_10m_max,wind_gusts_10m_max', 'timezone' => 'auto', 'forecast_days' => 7]);
        if (!is_array($data['current'] ?? null) || !is_array($data['hourly'] ?? null) || !is_array($data['daily'] ?? null)) throw new RuntimeException('Data prakiraan belum lengkap.');
        $data['cloud_analysis'] = ew_cloud_analysis($data['current']);
        $data['cloud_analysis']['prediction'] = ew_cloud_prediction($data['current']); ew_json($data);
    }
    if ($action === 'search') {
        if (!$loggedIn) ew_json(['error' => 'Silakan login untuk mencari kota.'], 401);
        $name = $_GET['name'] ?? ''; if (!is_string($name) || strlen(trim($name)) < 2 || strlen($name) > 160) ew_json(['error' => 'Masukkan nama kota antara 2 dan 80 karakter.'], 400);
        $data = ew_upstream('geocoding-api.open-meteo.com', '/v1/search', ['name' => trim($name), 'count' => 10, 'language' => 'id', 'format' => 'json', 'countryCode' => 'ID']);
        $results = array_values(array_filter($data['results'] ?? [], fn($city) => ($city['country_code'] ?? '') === 'ID' && is_numeric($city['latitude'] ?? null) && is_numeric($city['longitude'] ?? null)));
        ew_json(['results' => $results]);
    }
    ew_json(['error' => 'Endpoint tidak ditemukan.'], 404);
} catch (InvalidArgumentException $e) { ew_json(['error' => $e->getMessage()], 400); }
catch (Throwable $e) { error_log('EasyWeather: ' . $e->getMessage()); ew_json(['error' => 'Layanan sementara tidak tersedia. Periksa konfigurasi PHP dan koneksi internet.'], 503); }
