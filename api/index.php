<?php
/** EasyWeather PHP controller and JSON API (PHP 8.2+). No database required. */
declare(strict_types=1);

const EW_USERS = ['alfi1' => 'Alfi', 'azlan2' => 'Azlan', 'ridho3' => 'Ridho', 'kayca4' => 'Kayca', 'raudha5' => 'Raudha'];
// Password hashes are private server configuration; never sent to the browser.
const EW_PASSWORD_HASH = '$2y$10$ui0wRidDW/1jxHMUG8zM0eciuLhPoM1kFHJcJjc30B2TuxELvJwZW';
const EW_JAKARTA = ['name' => 'Jakarta', 'country' => 'Indonesia', 'latitude' => -6.2, 'longitude' => 106.8167];
const EW_COOKIE = 'easyweather_session';

function ew_key(): string {
    $key = getenv('APP_KEY') ?: '';
    if (strlen($key) >= 32) return $key;
    if (getenv('VERCEL')) throw new RuntimeException('Konfigurasi server APP_KEY belum tersedia.');
    // Local XAMPP only. A PHP file is used so Apache cannot disclose its contents.
    $file = dirname(__DIR__) . '/var/key.php';
    if (!is_file($file)) {
        $dir = dirname($file);
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) throw new RuntimeException('Folder var tidak dapat dibuat.');
        $handle = fopen($file, 'x');
        if ($handle !== false) { fwrite($handle, '<?php return ' . var_export(bin2hex(random_bytes(32)), true) . ';'); fclose($handle); chmod($file, 0600); }
    }
    $key = is_file($file) ? require $file : '';
    if (!is_string($key) || strlen($key) < 32) throw new RuntimeException('Kunci sesi lokal belum dapat dibuat.');
    return $key;
}
function ew_sign(array $session, string $key): string {
    $body = rtrim(strtr(base64_encode(json_encode($session, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    return $body . '.' . hash_hmac('sha256', $body, $key);
}
function ew_decode(string $token, string $key): ?array {
    if (strlen($token) > 2048 || substr_count($token, '.') !== 1) return null;
    [$body, $signature] = explode('.', $token, 2);
    if (!hash_equals(hash_hmac('sha256', $body, $key), $signature)) return null;
    $decoded = base64_decode(strtr($body, '-_', '+/'), true);
    $data = $decoded === false ? null : json_decode($decoded, true);
    if (!is_array($data) || !is_int($data['expires'] ?? null) || $data['expires'] <= time() || !is_string($data['nonce'] ?? null)) return null;
    if (($data['username'] ?? null) !== null && (!is_string($data['username']) || !array_key_exists($data['username'], EW_USERS))) return null;
    return $data;
}
function ew_secure(): bool { return getenv('VERCEL') || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'); }
function ew_cookie(array $session, string $key): void {
    setcookie(EW_COOKIE, ew_sign($session, $key), ['expires' => 0, 'path' => '/', 'secure' => ew_secure(), 'httponly' => true, 'samesite' => 'Lax']);
}
function ew_new_session(?string $username = null): array { return ['username' => $username, 'expires' => time() + 7200, 'nonce' => bin2hex(random_bytes(24))]; }
function ew_csrf(array $session, string $key): string { return hash_hmac('sha256', 'csrf:' . $session['nonce'], $key); }
function ew_json(array $data, int $status = 200): never { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); exit; }
function ew_login_valid(string $username, string $password): bool { return array_key_exists(strtolower(trim($username)), EW_USERS) && password_verify($password, EW_PASSWORD_HASH); }
function ew_coordinates(array $input): array {
    $lat = filter_var($input['latitude'] ?? '', FILTER_VALIDATE_FLOAT);
    $lon = filter_var($input['longitude'] ?? '', FILTER_VALIDATE_FLOAT);
    if ($lat === false || $lon === false || !is_finite($lat) || !is_finite($lon) || $lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) throw new InvalidArgumentException('Koordinat lokasi tidak valid.');
    return [$lat, $lon];
}
function ew_can_access(float $lat, float $lon, bool $loggedIn): bool { return $loggedIn || (abs($lat - EW_JAKARTA['latitude']) < 0.000001 && abs($lon - EW_JAKARTA['longitude']) < 0.000001); }
function ew_cloud_analysis(array $current): array {
    $values = [$current['cloud_cover_low'] ?? null, $current['cloud_cover_mid'] ?? null, $current['cloud_cover_high'] ?? null];
    foreach ($values as $v) if (!is_int($v) && !is_float($v)) return ['title' => 'Data lapisan awan belum tersedia', 'text' => 'Jenis awan tidak dapat diperkirakan tanpa data lapisan yang lengkap.'];
    if (in_array($current['weather_code'] ?? null, [95, 96, 99], true)) return ['title' => 'Indikasi awan konvektif', 'text' => 'Model memprakirakan badai petir. Kondisi ini berkaitan dengan awan Cumulonimbus, tetapi bentuk awan perlu dikonfirmasi melalui pengamatan.'];
    $max = max($values);
    if ($max < 20) return ['title' => 'Awan sedikit di semua lapisan', 'text' => 'Tutupan tiap lapisan di bawah 20%. Belum ada lapisan awan yang dominan kuat; jenis awan tidak dapat ditentukan dari persentase saja.'];
    $names = ['rendah', 'menengah', 'tinggi']; $examples = ['Stratus atau Stratocumulus', 'Altostratus atau Altocumulus', 'Cirrus, Cirrostratus, atau Cirrocumulus']; $top = []; $possible = [];
    foreach ($values as $i => $v) if ($v == $max) { $top[] = $names[$i]; $possible[] = $examples[$i]; }
    return ['title' => 'Lapisan ' . implode(' & ', $top) . ' dominan', 'text' => 'Tutupan terbesar ' . $max . '% pada lapisan ' . implode(' dan ', $top) . '. Contoh jenis yang mungkin dijumpai: ' . implode('; ', $possible) . '. Bentuk dan jenis pastinya memerlukan pengamatan visual.'];
}
function ew_upstream(string $host, string $path, array $query): array {
    // Only fixed, trusted providers are reachable; user input cannot become a URL.
    if (!in_array($host, ['api.open-meteo.com', 'geocoding-api.open-meteo.com'], true)) throw new RuntimeException('Penyedia tidak diizinkan.');
    $url = 'https://' . $host . $path . '?' . http_build_query($query);
    if (function_exists('curl_init')) {
        $ch = curl_init($url); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 22, CURLOPT_FOLLOWLOCATION => false, CURLOPT_USERAGENT => 'EasyWeather/2.0', CURLOPT_HTTPHEADER => ['Accept: application/json']]);
        $body = curl_exec($ch); $status = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($body === false || $status !== 200) throw new RuntimeException('Layanan data cuaca belum dapat dihubungi. Silakan coba lagi.');
    } else {
        $context = stream_context_create(['http' => ['timeout' => 22, 'follow_location' => 0, 'header' => "Accept: application/json\r\nUser-Agent: EasyWeather/2.0\r\n"]]);
        $body = @file_get_contents($url, false, $context);
        if ($body === false) throw new RuntimeException('Layanan data cuaca belum dapat dihubungi. Aktifkan cURL pada PHP dan periksa koneksi.');
    }
    $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data) || ($data['error'] ?? false)) throw new RuntimeException('Respons penyedia data tidak valid.');
    return $data;
}
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
        $data = ew_upstream('api.open-meteo.com', '/v1/forecast', ['latitude' => $lat, 'longitude' => $lon, 'current' => 'temperature_2m,relative_humidity_2m,apparent_temperature,is_day,precipitation,weather_code,cloud_cover,cloud_cover_low,cloud_cover_mid,cloud_cover_high,wind_speed_10m', 'hourly' => 'temperature_2m,precipitation_probability,weather_code,is_day', 'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max', 'timezone' => 'auto', 'forecast_days' => 5]);
        if (!is_array($data['current'] ?? null) || !is_array($data['hourly'] ?? null) || !is_array($data['daily'] ?? null)) throw new RuntimeException('Data prakiraan belum lengkap.');
        $data['cloud_analysis'] = ew_cloud_analysis($data['current']); ew_json($data);
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
