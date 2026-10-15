<?php
/** Coordinate validation, forecast and geocoding providers, and cloud analysis. */
declare(strict_types=1);

if (!defined('EW_BOOTSTRAPPED')) { http_response_code(404); exit; }

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
