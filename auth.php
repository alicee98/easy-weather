<?php
/** Practice accounts, signed session cookies, password validation and CSRF. */
declare(strict_types=1);

if (!defined('EW_BOOTSTRAPPED')) { http_response_code(404); exit; }

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
    $file = __DIR__ . '/var/key.php';
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

function ew_login_valid(string $username, string $password): bool { return array_key_exists(strtolower(trim($username)), EW_USERS) && password_verify($password, EW_PASSWORD_HASH); }
