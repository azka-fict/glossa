<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

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

    if ($email === '' || $password === '') {
        return 'Email dan password wajib diisi.';
    }

    $stmt = $pdo->prepare('SELECT id, name, email, password_hash FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return 'Email atau password salah.';
    }

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

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Format email tidak valid.';
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if (!$stmt->fetch()) {
        return 'Email tidak terdaftar.';
    }

    $code = sprintf('%06d', random_int(0, 999999));
    $expiresAt = date('Y-m-d H:i:s', time() + 900);

    $pdo->prepare('DELETE FROM password_resets WHERE email = ?')->execute([$email]);
    $stmt = $pdo->prepare('INSERT INTO password_resets (email, code, expires_at) VALUES (?, ?, ?)');
    $stmt->execute([$email, $code, $expiresAt]);

    $subject = 'Kode Reset Password - glossa';
    $message = "Halo,\n\nKode verifikasi reset password glossa Anda adalah: $code\n\nKode ini berlaku selama 15 menit.\nJika bukan Anda yang meminta, abaikan email ini.\n\nSalam,\nTim glossa";
    $headers = "From: no-reply@glossa.local\r\nReply-To: no-reply@glossa.local\r\nX-Mailer: PHP/" . phpversion();

    @mail($email, $subject, $message, $headers);

    $_SESSION['reset_email'] = $email;
    return null;
}

function verify_and_reset_password(PDO $pdo, string $email, string $code, string $new_password): ?string {
    $email = trim(strtolower($email));
    $code = trim($code);

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
        return 'Kode verifikasi salah.';
    }
    if (strtotime($reset['expires_at']) < time()) {
        return 'Kode verifikasi sudah kedaluwarsa. Silakan minta kode baru.';
    }

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
