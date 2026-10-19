<?php
if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) {
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    session_start();
}

require_once __DIR__ . '/../config/database.php';

// --- CSRF Protection ---
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function validate_csrf(): bool {
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if ($sessionToken === '') {
        return false;
    }
    $postToken = $_POST['csrf_token'] ?? '';
    if ($postToken !== '' && hash_equals($sessionToken, $postToken)) {
        return true;
    }
    $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($headerToken !== '' && hash_equals($sessionToken, $headerToken)) {
        return true;
    }
    return false;
}

// --- Rate Limiting ---
function client_ip(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function check_rate_limit(PDO $pdo, string $action, string $identifier, int $maxAttempts = 5, int $decaySeconds = 300): ?string {
    $rateKey = substr($action . ':' . $identifier, 0, 100);
    $now = time();

    $stmt = $pdo->prepare('SELECT attempts, last_attempt FROM rate_limits WHERE rate_key = ?');
    $stmt->execute([$rateKey]);
    $row = $stmt->fetch();

    if ($row) {
        $elapsed = $now - (int)$row['last_attempt'];
        if ($elapsed < $decaySeconds && (int)$row['attempts'] >= $maxAttempts) {
            $retryAfter = (int)ceil(($decaySeconds - $elapsed) / 60);
            return "Terlalu banyak percobaan. Silakan coba lagi dalam {$retryAfter} menit.";
        }
        if ($elapsed >= $decaySeconds) {
            $pdo->prepare('DELETE FROM rate_limits WHERE rate_key = ?')->execute([$rateKey]);
        }
    }
    return null;
}

function record_rate_limit_attempt(PDO $pdo, string $action, string $identifier, int $decaySeconds = 300): void {
    $rateKey = substr($action . ':' . $identifier, 0, 100);
    $now = time();

    $stmt = $pdo->prepare('SELECT attempts, last_attempt FROM rate_limits WHERE rate_key = ?');
    $stmt->execute([$rateKey]);
    $row = $stmt->fetch();

    if (!$row || ($now - (int)$row['last_attempt']) >= $decaySeconds) {
        $stmt = $pdo->prepare('INSERT INTO rate_limits (rate_key, attempts, last_attempt) VALUES (?, 1, ?) ON DUPLICATE KEY UPDATE attempts = 1, last_attempt = ?');
        $stmt->execute([$rateKey, $now, $now]);
    } else {
        $stmt = $pdo->prepare('UPDATE rate_limits SET attempts = attempts + 1, last_attempt = ? WHERE rate_key = ?');
        $stmt->execute([$now, $rateKey]);
    }
}

function clear_rate_limit(PDO $pdo, string $action, string $identifier): void {
    $rateKey = substr($action . ':' . $identifier, 0, 100);
    $pdo->prepare('DELETE FROM rate_limits WHERE rate_key = ?')->execute([$rateKey]);
}

// --- Email Delivery (SMTP / Native) ---
function send_smtp_email(string $to, string $subject, string $body): bool {
    $host = getenv('SMTP_HOST') ?: '';
    if ($host === '') {
        return false;
    }
    $port = (int)(getenv('SMTP_PORT') ?: 587);
    $user = getenv('SMTP_USER') ?: '';
    $pass = getenv('SMTP_PASS') ?: '';
    $from = getenv('SMTP_FROM') ?: ($user ?: 'no-reply@glossa.local');
    $fromName = getenv('SMTP_FROM_NAME') ?: 'glossa';

    $isSsl = ($port === 465);
    $remote = ($isSsl ? 'ssl://' : 'tcp://') . $host . ':' . $port;

    $socket = @stream_socket_client($remote, $errno, $errstr, 12, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        error_log("SMTP connection failed: $errstr ($errno)");
        return false;
    }

    $read = function() use ($socket): string {
        $data = '';
        while (!feof($socket)) {
            $line = fgets($socket, 512);
            if ($line === false) break;
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $data;
    };

    $send = function(string $cmd) use ($socket, $read): string {
        fwrite($socket, $cmd . "\r\n");
        return $read();
    };

    $banner = $read();
    if (substr($banner, 0, 3) !== '220') {
        fclose($socket);
        return false;
    }

    $send('EHLO ' . gethostname());

    if (!$isSsl && $port === 587) {
        $res = $send('STARTTLS');
        if (substr($res, 0, 3) === '220') {
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                return false;
            }
            $send('EHLO ' . gethostname());
        }
    }

    if ($user !== '' && $pass !== '') {
        $send('AUTH LOGIN');
        $send(base64_encode($user));
        $res = $send(base64_encode($pass));
        if (substr($res, 0, 3) !== '235') {
            error_log("SMTP Auth failed: " . trim($res));
            fclose($socket);
            return false;
        }
    }

    $send('MAIL FROM: <' . $from . '>');
    $res = $send('RCPT TO: <' . $to . '>');
    if (substr($res, 0, 3) !== '250' && substr($res, 0, 3) !== '251') {
        error_log("SMTP RCPT failed: " . trim($res));
        fclose($socket);
        return false;
    }

    $send('DATA');

    $headers = [
        'From: ' . '=?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . '>',
        'To: <' . $to . '>',
        'Subject: ' . '=?UTF-8?B?' . base64_encode($subject) . '?=',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'Date: ' . date(DATE_RFC2822),
    ];

    $payload = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.";
    $res = $send($payload);
    $send('QUIT');
    fclose($socket);

    return substr($res, 0, 3) === '250';
}

function send_app_mail(string $to, string $subject, string $body): bool {
    if (getenv('SMTP_HOST')) {
        return send_smtp_email($to, $subject, $body);
    }
    $from = getenv('SMTP_FROM') ?: 'no-reply@glossa.local';
    $headers = "From: $from\r\nReply-To: $from\r\nX-Mailer: PHP/" . phpversion();
    return @mail($to, $subject, $body, $headers);
}


function generate_uuid(): string {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function current_user(): ?array {
    if (!empty($_SESSION['user_id'])) {
        return [
            'id'    => $_SESSION['user_id'],
            'name'  => $_SESSION['user_name'] ?? '',
            'email' => $_SESSION['user_email'] ?? '',
        ];
    }
    return null;
}

function require_auth(): void {
    if (!current_user()) {
        header('Location: login.php');
        exit;
    }
}

function require_guest(): void {
    if (current_user()) {
        header('Location: index.php');
        exit;
    }
}

function register_user(PDO $pdo, string $name, string $email, string $password, ?string $birth_date = null, string $gender = 'not_specified'): ?string {
    $name = trim($name);
    $email = trim(strtolower($email));
    $birth_date = $birth_date ? trim($birth_date) : null;

    if (strlen($name) < 2 || strlen($name) > 100) {
        return 'Nama lengkap harus antara 2 - 100 karakter.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Format email tidak valid.';
    }
    if (strlen($password) < 6) {
        return 'Password minimal 6 karakter.';
    }

    $age = 18;
    if ($birth_date) {
        $d = DateTime::createFromFormat('Y-m-d', $birth_date);
        if (!$d || $d->format('Y-m-d') !== $birth_date) {
            return 'Format tanggal lahir tidak valid.';
        }
        $now = new DateTime();
        if ($d > $now) {
            return 'Tanggal lahir tidak valid.';
        }
        $age = $now->diff($d)->y;
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return 'Email sudah terdaftar.';
    }

    $id = generate_uuid();
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare('INSERT INTO users (id, name, email, password_hash, age, birth_date, gender) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$id, $name, $email, $hash, $age, $birth_date, $gender]);

    return null;
}

function login_user(PDO $pdo, string $email, string $password): ?string {
    $email = trim(strtolower($email));
    $rateLimitKey = $email . '|' . client_ip();

    $rateErr = check_rate_limit($pdo, 'login', $rateLimitKey, 5, 300);
    if ($rateErr) {
        return $rateErr;
    }

    if ($email === '' || $password === '') {
        return 'Email dan password wajib diisi.';
    }

    $stmt = $pdo->prepare('SELECT id, name, email, password_hash FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        record_rate_limit_attempt($pdo, 'login', $rateLimitKey, 300);
        return 'Email atau password salah.';
    }

    clear_rate_limit($pdo, 'login', $rateLimitKey);

    if (!headers_sent()) {
        session_regenerate_id(true);
    }
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];

    return null;
}

function send_reset_code(PDO $pdo, string $email): ?string {
    $email = trim(strtolower($email));

    $rateErr = check_rate_limit($pdo, 'send_reset', $email, 3, 300);
    if ($rateErr) {
        return $rateErr;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Format email tidak valid.';
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if (!$stmt->fetch()) {
        return 'Email tidak terdaftar.';
    }

    record_rate_limit_attempt($pdo, 'send_reset', $email, 300);

    $code = sprintf('%06d', random_int(0, 999999));
    $expiresAt = date('Y-m-d H:i:s', time() + 900);

    $pdo->prepare('DELETE FROM password_resets WHERE email = ?')->execute([$email]);
    $stmt = $pdo->prepare('INSERT INTO password_resets (email, code, expires_at) VALUES (?, ?, ?)');
    $stmt->execute([$email, $code, $expiresAt]);

    $subject = 'Kode Reset Password - glossa';
    $message = "Halo,\n\nKode verifikasi reset password glossa Anda adalah: $code\n\nKode ini berlaku selama 15 menit.\nJika bukan Anda yang meminta, abaikan email ini.\n\nSalam,\nTim glossa";

    send_app_mail($email, $subject, $message);

    $_SESSION['reset_email'] = $email;
    return null;
}

function verify_and_reset_password(PDO $pdo, string $email, string $code, string $new_password): ?string {
    $email = trim(strtolower($email));
    $code = trim($code);

    $rateErr = check_rate_limit($pdo, 'verify_reset', $email, 5, 300);
    if ($rateErr) {
        return $rateErr;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Format email tidak valid.';
    }
    if ($code === '') {
        return 'Kode verifikasi wajib diisi.';
    }
    if (strlen($new_password) < 6) {
        return 'Password baru minimal 6 karakter.';
    }

    $stmt = $pdo->prepare('SELECT id, expires_at FROM password_resets WHERE email = ? AND code = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$email, $code]);
    $reset = $stmt->fetch();

    if (!$reset) {
        record_rate_limit_attempt($pdo, 'verify_reset', $email, 300);
        return 'Kode verifikasi salah.';
    }
    if (strtotime($reset['expires_at']) < time()) {
        return 'Kode verifikasi sudah kedaluwarsa. Silakan minta kode baru.';
    }

    clear_rate_limit($pdo, 'verify_reset', $email);

    $hash = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE email = ?');
    $stmt->execute([$hash, $email]);

    $pdo->prepare('DELETE FROM password_resets WHERE email = ?')->execute([$email]);
    unset($_SESSION['reset_email']);

    return null;
}

function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies') && !headers_sent()) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

function update_user_email(PDO $pdo, string $userId, string $newEmail): ?string {
    $newEmail = trim(strtolower($newEmail));
    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        return 'Format email baru tidak valid.';
    }

    $stmt = $pdo->prepare('SELECT id, email FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $current = $stmt->fetch();
    if (!$current) {
        return 'Pengguna tidak ditemukan.';
    }
    if ($current['email'] === $newEmail) {
        return 'Email baru sama dengan email saat ini.';
    }

    $check = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
    $check->execute([$newEmail, $userId]);
    if ($check->fetch()) {
        return 'Email sudah terdaftar pada akun lain.';
    }

    $upd = $pdo->prepare('UPDATE users SET email = ?, email_verified_at = NULL WHERE id = ?');
    $upd->execute([$newEmail, $userId]);

    $pdo->prepare('DELETE FROM email_verifications WHERE user_id = ?')->execute([$userId]);
    $_SESSION['user_email'] = $newEmail;

    return null;
}

function send_email_verification_code(PDO $pdo, string $userId, string $email): ?string {
    $email = trim(strtolower($email));

    $rateErr = check_rate_limit($pdo, 'send_verify', $userId, 3, 300);
    if ($rateErr) {
        return $rateErr;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Format email tidak valid.';
    }

    record_rate_limit_attempt($pdo, 'send_verify', $userId, 300);

    $code = sprintf('%06d', random_int(0, 999999));
    $expiresAt = date('Y-m-d H:i:s', time() + 900);

    $pdo->prepare('DELETE FROM email_verifications WHERE user_id = ?')->execute([$userId]);
    $stmt = $pdo->prepare('INSERT INTO email_verifications (user_id, email, code, expires_at) VALUES (?, ?, ?, ?)');
    $stmt->execute([$userId, $email, $code, $expiresAt]);

    $subject = 'Kode Konfirmasi Email - glossa';
    $message = "Halo,\n\nKode konfirmasi email akun glossa Anda adalah: $code\n\nKode ini berlaku selama 15 menit.\nJika bukan Anda yang meminta konfirmasi ini, abaikan email ini.\n\nSalam,\nTim glossa";

    send_app_mail($email, $subject, $message);

    return null;
}

function verify_email_code(PDO $pdo, string $userId, string $code): ?string {
    $code = trim($code);

    $rateErr = check_rate_limit($pdo, 'verify_email', $userId, 5, 300);
    if ($rateErr) {
        return $rateErr;
    }

    if ($code === '' || strlen($code) !== 6 || !ctype_digit($code)) {
        return 'Kode verifikasi harus 6 digit angka.';
    }

    $stmt = $pdo->prepare('SELECT id, email, expires_at FROM email_verifications WHERE user_id = ? AND code = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$userId, $code]);
    $item = $stmt->fetch();

    if (!$item) {
        record_rate_limit_attempt($pdo, 'verify_email', $userId, 300);
        return 'Kode verifikasi salah.';
    }

    if (strtotime($item['expires_at']) < time()) {
        return 'Kode verifikasi sudah kedaluwarsa. Silakan kirim ulang kode baru.';
    }

    clear_rate_limit($pdo, 'verify_email', $userId);

    $upd = $pdo->prepare('UPDATE users SET email = ?, email_verified_at = CURRENT_TIMESTAMP WHERE id = ?');
    $upd->execute([$item['email'], $userId]);

    $pdo->prepare('DELETE FROM email_verifications WHERE user_id = ?')->execute([$userId]);

    return null;
}

function delete_user_account(PDO $pdo, string $userId, string $password): ?string {
    if ($password === '') {
        return 'Password konfirmasi wajib diisi.';
    }

    $stmt = $pdo->prepare('SELECT id, email, password_hash FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        return 'Pengguna tidak ditemukan.';
    }

    if (!password_verify($password, $user['password_hash'])) {
        return 'Password konfirmasi salah.';
    }

    $pdo->prepare('DELETE FROM password_resets WHERE email = ?')->execute([$user['email']]);
    $pdo->prepare('DELETE FROM email_verifications WHERE user_id = ?')->execute([$userId]);
    $pdo->prepare('DELETE FROM scan_history WHERE user_id = ?')->execute([$userId]);
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);

    logout_user();
    return null;
}



