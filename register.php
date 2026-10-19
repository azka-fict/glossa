<?php
require_once __DIR__ . '/includes/auth.php';
require_guest();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $error = 'Sesi tidak valid atau telah kedaluwarsa. Silakan muat ulang halaman.';
    } else {
        $name = $_POST['name'] ?? '';
        $birthDate = $_POST['birth_date'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($password !== $confirmPassword) {
            $error = 'Konfirmasi password tidak cocok.';
        } else {
            $regError = register_user($pdo, $name, $email, $password, $birthDate);
            if ($regError) {
                $error = $regError;
            } else {
                login_user($pdo, $email, $password);
                setcookie('glossa_onboarded', '1', time() + (86400 * 365), '/');
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true]);
                    exit;
                }
                header('Location: index.php');
                exit;
            }
        }
    }

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $error]);
        exit;
    }
}

$pageTitle = 'Daftar - glossa';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-card">
    <div class="auth-tabs">
        <a href="login.php<?= !empty($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>" class="auth-tab">Masuk</a>
        <a href="register.php<?= !empty($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>" class="auth-tab active">Daftar Akun</a>
    </div>

    <h2>Daftar Akun glossa</h2>
    <p class="subtitle">Buat akun baru untuk mulai</p>

    <?php if (isset($_GET['redirect']) && $_GET['redirect'] === 'scan'): ?>
        <div class="alert alert-info">Autentikasi Diperlukan: Silakan daftar akun atau masuk terlebih dahulu untuk menggunakan fitur scan kamera.</div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="register.php<?= !empty($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>" class="auth-form">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="name">Nama Lengkap</label>
            <input type="text" id="name" name="name" required autofocus value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label for="birth_date">Tanggal Lahir</label>
            <input type="date" id="birth_date" name="birth_date" required value="<?= htmlspecialchars($_POST['birth_date'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label for="password">Password (min. 6 karakter)</label>
            <div class="password-input-wrapper">
                <input type="password" id="password" name="password" minlength="6" required>
                <button type="button" class="btn-toggle-password" aria-label="Lihat password" title="Lihat password">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                </button>
            </div>
        </div>

        <div class="form-group">
            <label for="confirm_password">Konfirmasi Password</label>
            <div class="password-input-wrapper">
                <input type="password" id="confirm_password" name="confirm_password" minlength="6" required>
                <button type="button" class="btn-toggle-password" aria-label="Lihat password" title="Lihat password">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                </button>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Daftar</button>
    </form>

    <div class="auth-footer">
        Sudah punya akun? <a href="login.php<?= !empty($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>">Masuk di sini</a>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
