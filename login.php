<?php
require_once __DIR__ . '/includes/auth.php';
require_guest();

if (empty($_COOKIE['glossa_onboarded']) && !isset($_GET['skip_onboarding']) && !isset($_GET['reset'])) {
    header('Location: onboarding.php');
    exit;
}

$error = '';
$success = '';

if (isset($_GET['reset']) && $_GET['reset'] === 'success') {
    $success = 'Password berhasil diatur ulang! Silakan masuk dengan password baru Anda.';
} elseif (isset($_GET['msg']) && $_GET['msg'] === 'account_deleted') {
    $success = 'Akun Anda dan seluruh data riwayat telah berhasil dihapus.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $error = 'Sesi tidak valid atau telah kedaluwarsa. Silakan muat ulang halaman.';
    } else {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        $error = login_user($pdo, $email, $password) ?? '';
    }

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        if ($error === '') {
            setcookie('glossa_onboarded', '1', time() + (86400 * 365), '/');
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $error]);
        }
        exit;
    }

    if ($error === '') {
        setcookie('glossa_onboarded', '1', time() + (86400 * 365), '/');
        header('Location: index.php');
        exit;
    }
}

$pageTitle = 'Masuk - glossa';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-card">
    <div class="auth-tabs">
        <a href="login.php<?= !empty($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>" class="auth-tab active">Masuk</a>
        <a href="register.php<?= !empty($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>" class="auth-tab">Daftar Akun</a>
    </div>

    <h2>Masuk ke glossa</h2>
    <p class="subtitle">Silakan masukkan email dan password Anda</p>

    <?php if (isset($_GET['redirect']) && $_GET['redirect'] === 'scan'): ?>
        <div class="alert alert-info">Autentikasi Diperlukan: Silakan masuk atau daftar akun terlebih dahulu untuk menggunakan fitur scan kamera.</div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php<?= !empty($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>" class="auth-form">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required autofocus value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>

        <div class="form-group">
            <div class="label-with-link">
                <label for="password">Password</label>
                <a href="forgot_password.php" class="forgot-link">Lupa password?</a>
            </div>
            <div class="password-input-wrapper">
                <input type="password" id="password" name="password" required>
                <button type="button" class="btn-toggle-password" aria-label="Lihat password" title="Lihat password">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                </button>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Masuk</button>
    </form>

    <div class="auth-footer">
        Belum punya akun? <a href="register.php<?= !empty($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>">Daftar sekarang</a>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
