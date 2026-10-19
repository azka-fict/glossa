<?php
require_once __DIR__ . '/includes/auth.php';
require_guest();

$step = $_GET['step'] ?? 'request';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'send_code') {
        $email = $_POST['email'] ?? '';
        $sendError = send_reset_code($pdo, $email);
        if ($sendError) {
            $error = $sendError;
        } else {
            header('Location: forgot_password.php?step=verify');
            exit;
        }
    } elseif ($action === 'verify_reset') {
        $email = $_SESSION['reset_email'] ?? ($_POST['email'] ?? '');
        $code = $_POST['code'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($newPassword !== $confirmPassword) {
            $error = 'Konfirmasi password baru tidak cocok.';
            $step = 'verify';
        } else {
            $resetError = verify_and_reset_password($pdo, $email, $code, $newPassword);
            if ($resetError) {
                $error = $resetError;
                $step = 'verify';
            } else {
                header('Location: login.php?reset=success');
                exit;
            }
        }
    }
}

$pageTitle = 'Lupa Password - glossa';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-card">
    <h2>Lupa Password</h2>

    <?php if ($step === 'verify'): ?>
        <p class="subtitle">
            Kode verifikasi telah dikirim ke <strong><?= htmlspecialchars($_SESSION['reset_email'] ?? '') ?></strong>.
        </p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="forgot_password.php?step=verify" class="auth-form">
            <input type="hidden" name="action" value="verify_reset">
            <?php if (empty($_SESSION['reset_email'])): ?>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="code">Kode Verifikasi (6 digit dari email)</label>
                <input type="text" id="code" name="code" maxlength="6" pattern="[0-9]{6}" placeholder="Contoh: 123456" required autofocus>
            </div>

            <div class="form-group">
                <label for="new_password">Password Baru (min. 6 karakter)</label>
                <input type="password" id="new_password" name="new_password" minlength="6" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">Konfirmasi Password Baru</label>
                <input type="password" id="confirm_password" name="confirm_password" minlength="6" required>
            </div>

            <button type="submit" class="btn btn-primary">Simpan Password Baru</button>
        </form>

        <div class="auth-footer">
            Tidak menerima email? <a href="forgot_password.php">Kirim ulang kode</a>
        </div>
    <?php else: ?>
        <p class="subtitle">Masukkan email akun Anda untuk menerima 6 digit kode verifikasi.</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="forgot_password.php" class="auth-form">
            <input type="hidden" name="action" value="send_code">
            <div class="form-group">
                <label for="email">Email Terdaftar</label>
                <input type="email" id="email" name="email" required autofocus value="<?= htmlspecialchars($_POST['email'] ?? ($_SESSION['reset_email'] ?? '')) ?>">
            </div>

            <button type="submit" class="btn btn-primary">Kirim Kode ke Email</button>
        </form>

        <div class="auth-footer">
            Ingat password Anda? <a href="login.php">Masuk kembali</a>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
