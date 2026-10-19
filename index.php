<?php
require_once __DIR__ . '/includes/auth.php';

if (!current_user() && empty($_COOKIE['glossa_onboarded'])) {
    header('Location: onboarding.php');
    exit;
}

$pageTitle = 'Dashboard - glossa';
require_once __DIR__ . '/includes/header.php';
?>

<div class="dashboard-wrapper">
    <div class="user-greeting-banner">
        <span class="greeting-subtitle"><?= $authUser ? 'Welcome back,' : 'Welcome,' ?></span>
        <h1 class="greeting-name"><?= $authUser ? htmlspecialchars($authUser['name']) : 'Natural Beauty' ?></h1>
        <p class="greeting-tagline">Lets find what suit you best</p>
    </div>

    <div class="scan-feature-card">
        <div class="scan-icon-wrap" aria-hidden="true">
            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                <circle cx="12" cy="13" r="4"></circle>
            </svg>
        </div>
        <h2>Fitur Scan Kamera</h2>
        <p>Gunakan kamera perangkat Anda untuk memindai dokumen, teks, atau kode secara langsung.</p>
        
        <button type="button" class="btn btn-primary" id="btnScanCamera" data-auth="<?= $authUser ? '1' : '0' ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px;">
                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                <circle cx="12" cy="13" r="4"></circle>
            </svg>
            Buka Scan Kamera
        </button>
    </div>
</div>

<?php if (!$authUser): ?>
<div class="modal-overlay" id="authModal" aria-hidden="true">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Autentikasi Diperlukan</h3>
            <button type="button" class="modal-close" id="closeAuthModal" aria-label="Tutup">&times;</button>
        </div>
        <div class="modal-body">
            <p class="modal-desc">Silakan masuk atau daftar akun terlebih dahulu untuk menggunakan fitur scan kamera.</p>
            
            <div class="auth-tabs" style="margin-bottom: 1.25rem;">
                <button type="button" class="auth-tab active" id="tabLoginBtn">Masuk</button>
                <button type="button" class="auth-tab" id="tabRegisterBtn">Daftar Akun</button>
            </div>

            <div id="modalLoginPanel">
                <div class="alert alert-error" id="modalLoginAlert" style="display: none;"></div>
                <form id="modalLoginForm" class="auth-form" method="POST" action="login.php">
                    <div class="form-group">
                        <label for="modalLoginEmail">Email</label>
                        <input type="email" id="modalLoginEmail" name="email" required placeholder="nama@email.com">
                    </div>
                    <div class="form-group">
                        <div class="label-with-link">
                            <label for="modalLoginPassword">Password</label>
                            <a href="forgot_password.php" class="forgot-link">Lupa password?</a>
                        </div>
                        <input type="password" id="modalLoginPassword" name="password" required placeholder="Masukkan password">
                    </div>
                    <button type="submit" class="btn btn-primary btn-block" id="modalLoginSubmitBtn">Masuk Sekarang</button>
                </form>
            </div>

            <div id="modalRegisterPanel" style="display: none;">
                <div class="alert alert-error" id="modalRegisterAlert" style="display: none;"></div>
                <form id="modalRegisterForm" class="auth-form" method="POST" action="register.php">
                    <div class="form-group">
                        <label for="modalRegName">Nama Lengkap</label>
                        <input type="text" id="modalRegName" name="name" required placeholder="Nama Anda">
                    </div>
                    <div class="form-group">
                        <label for="modalRegBirth">Tanggal Lahir</label>
                        <input type="date" id="modalRegBirth" name="birth_date" required>
                    </div>
                    <div class="form-group">
                        <label for="modalRegEmail">Email</label>
                        <input type="email" id="modalRegEmail" name="email" required placeholder="nama@email.com">
                    </div>
                    <div class="form-group">
                        <label for="modalRegPassword">Password (min. 6 karakter)</label>
                        <input type="password" id="modalRegPassword" name="password" minlength="6" required placeholder="Buat password">
                    </div>
                    <div class="form-group">
                        <label for="modalRegConfirm">Konfirmasi Password</label>
                        <input type="password" id="modalRegConfirm" name="confirm_password" minlength="6" required placeholder="Ulangi password">
                    </div>
                    <button type="submit" class="btn btn-primary btn-block" id="modalRegisterSubmitBtn">Daftar Akun Sekarang</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="modal-overlay" id="cameraModal" aria-hidden="true">
    <div class="modal-card modal-camera-card">
        <div class="modal-header">
            <h3>Scan Undertone Wajah</h3>
            <button type="button" class="modal-close" id="closeCameraModal" aria-label="Tutup">&times;</button>
        </div>
        <div class="modal-body">
            <div class="camera-viewport">
                <video id="cameraVideo" autoplay playsinline muted></video>
                <div class="scanner-frame">
                    <div class="scanner-line"></div>
                </div>
                <div class="camera-status" id="cameraStatus">Menghubungkan ke kamera...</div>
            </div>
            <div class="camera-actions" id="cameraActions">
                <button type="button" class="btn btn-primary" id="btnCapture">Ambil Gambar & Analisis</button>
                <button type="button" class="btn btn-secondary" id="btnStopCamera">Tutup Kamera</button>
            </div>

            <div id="scanResultBox" class="scan-result-card" style="display: none; margin-top: 1.25rem;">
                <div class="scan-result-header">
                    <span class="undertone-badge" id="resUndertoneBadge">Warm Undertone</span>
                    <span class="accuracy-pill" id="resAccuracyBadge">96% Akurasi</span>
                </div>
                <p id="resRecommendation" class="scan-result-desc"></p>
                <div class="scan-result-footer" style="display: flex; gap: 0.75rem; justify-content: center; margin-top: 1rem;">
                    <a href="profile.php" class="btn btn-primary btn-sm">Lihat Riwayat di Profil</a>
                    <button type="button" class="btn btn-secondary btn-sm" id="btnScanAgain">Scan Ulang</button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
