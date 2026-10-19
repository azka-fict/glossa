<?php
require_once __DIR__ . '/includes/auth.php';
require_auth();

$nameError = '';
$emailError = '';
$verifyError = '';
$deleteAccountError = '';

$stmt = $pdo->prepare('SELECT id, name, email, email_verified_at, birth_date, age, gender, created_at FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    logout_user();
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $verifyError = 'Sesi tidak valid atau telah kedaluwarsa. Silakan muat ulang halaman.';
    } else {
        if (isset($_POST['update_name'])) {
            $newName = trim($_POST['new_name'] ?? '');
        if (strlen($newName) >= 2 && strlen($newName) <= 100) {
            $upd = $pdo->prepare('UPDATE users SET name = ? WHERE id = ?');
            $upd->execute([$newName, $_SESSION['user_id']]);
            $_SESSION['user_name'] = $newName;
            header('Location: profile.php?success=name_updated');
            exit;
        } else {
            $nameError = 'Nama harus antara 2 - 100 karakter.';
        }
    } elseif (isset($_POST['update_email'])) {
        $newEmail = trim($_POST['new_email'] ?? '');
        $emailError = update_user_email($pdo, $_SESSION['user_id'], $newEmail);
        if (!$emailError) {
            header('Location: profile.php?success=email_updated');
            exit;
        }
    } elseif (isset($_POST['send_verification'])) {
        $sendErr = send_email_verification_code($pdo, $_SESSION['user_id'], $user['email']);
        if ($sendErr) {
            $verifyError = $sendErr;
        } else {
            header('Location: profile.php?action=verify&success=code_sent');
            exit;
        }
    } elseif (isset($_POST['verify_email_code'])) {
        $code = trim($_POST['verification_code'] ?? '');
        $verifyError = verify_email_code($pdo, $_SESSION['user_id'], $code);
        if (!$verifyError) {
            header('Location: profile.php?success=email_verified');
            exit;
        }
    } elseif (isset($_POST['delete_scan_id'])) {
        $scanId = (int)$_POST['delete_scan_id'];
        if ($scanId > 0) {
            $delStmt = $pdo->prepare('DELETE FROM scan_history WHERE id = ? AND user_id = ?');
            $delStmt->execute([$scanId, $_SESSION['user_id']]);
        }
        header('Location: profile.php');
        exit;
    } elseif (isset($_POST['delete_account'])) {
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');
        $delErr = delete_user_account($pdo, $_SESSION['user_id'], $confirmPassword);
        if (!$delErr) {
            header('Location: login.php?msg=account_deleted');
            exit;
        }
        $deleteAccountError = $delErr;
    }
}
}

$scanStmt = $pdo->prepare('SELECT id, undertone, face_shape, skin_tone, accuracy, recommendations, dominant_color, created_at FROM scan_history WHERE user_id = ? ORDER BY created_at DESC');
$scanStmt->execute([$_SESSION['user_id']]);
$scanHistory = $scanStmt->fetchAll();

$pageTitle = 'Dashboard Profil - glossa';
require_once __DIR__ . '/includes/header.php';
?>

<div class="profile-wrapper">
    <div class="profile-card">
        <?php if (isset($_GET['success']) && $_GET['success'] === 'name_updated'): ?>
            <div class="alert alert-success">Nama berhasil diperbarui!</div>
        <?php elseif (isset($_GET['success']) && $_GET['success'] === 'email_updated'): ?>
            <div class="alert alert-success">Alamat email berhasil diperbarui!</div>
        <?php elseif (isset($_GET['success']) && $_GET['success'] === 'email_verified'): ?>
            <div class="alert alert-success">Alamat email berhasil dikonfirmasi dan terverifikasi!</div>
        <?php endif; ?>
        <?php if (!empty($nameError)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($nameError) ?></div>
        <?php endif; ?>
        <?php if (!empty($emailError)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($emailError) ?></div>
        <?php endif; ?>
        <?php if (!empty($verifyError)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($verifyError) ?></div>
        <?php endif; ?>

        <div class="profile-header">
            <div class="profile-titles">
                <h1><?= htmlspecialchars($user['name']) ?></h1>
                <p class="profile-email"><?= htmlspecialchars($user['email']) ?></p>
            </div>
        </div>

        <?php if (empty($user['email_verified_at'])): ?>
            <div class="email-verify-banner">
                <div class="banner-top">
                    <div class="banner-icon-badge" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                    </div>
                    <div class="banner-body">
                        <div class="banner-title">Email Belum Terkonfirmasi</div>
                        <div class="banner-text">Konfirmasi untuk mengamankan akun & riwayat analisis Anda.</div>
                    </div>
                </div>
                <button type="button" class="btn-banner-action" onclick="openVerifyEmailModal()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    Konfirmasi Sekarang
                </button>
            </div>
        <?php endif; ?>

        <div class="profile-details">
            <div class="detail-row">
                <span class="detail-label">Nama Lengkap</span>
                <div class="detail-value-group">
                    <span class="detail-value"><?= htmlspecialchars($user['name']) ?></span>
                    <button type="button" class="btn-edit-inline" onclick="openEditNameModal()">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                        Ubah
                    </button>
                </div>
            </div>
            <div class="detail-row detail-row-email">
                <div class="email-header-line">
                    <span class="detail-label">Email</span>
                    <div class="badge-group">
                        <?php if (!empty($user['email_verified_at'])): ?>
                            <span class="status-badge verified" title="Terverifikasi pada <?= date('d M Y, H:i', strtotime($user['email_verified_at'])) ?>">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                </svg>
                                Terverifikasi
                            </span>
                        <?php else: ?>
                            <span class="status-badge unverified clickable" onclick="openVerifyEmailModal()" title="Klik untuk konfirmasi email">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="8" x2="12" y2="12"></line>
                                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                </svg>
                                Belum Terverifikasi
                            </span>
                        <?php endif; ?>
                        <button type="button" class="btn-edit-inline" onclick="openEditEmailModal()" title="Ubah email jika salah ketik / typo">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                            Ubah
                        </button>
                    </div>
                </div>
                <div class="email-content-line">
                    <span class="email-value-text"><?= htmlspecialchars($user['email']) ?></span>
                </div>
            </div>
            <div class="detail-row">
                <span class="detail-label">Tanggal Lahir</span>
                <span class="detail-value"><?= !empty($user['birth_date']) ? date('d F Y', strtotime($user['birth_date'])) : '-' ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Usia</span>
                <span class="detail-value"><?= !empty($user['age']) ? htmlspecialchars($user['age']) . ' tahun' : '-' ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Terdaftar Sejak</span>
                <span class="detail-value"><?= !empty($user['created_at']) ? date('d M Y, H:i', strtotime($user['created_at'])) : '-' ?></span>
            </div>
        </div>

        <div class="account-danger-zone">
            <div class="danger-zone-header">
                <div class="danger-zone-text">
                    <span class="danger-title">Hapus Akun</span>
                    <p class="danger-desc">Hapus akun secara permanen beserta seluruh data riwayat analisis Anda.</p>
                </div>
                <button type="button" class="btn btn-danger-outline" onclick="openDeleteAccountModal()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                    Hapus Akun
                </button>
            </div>
        </div>
    </div>

    <div class="history-section">
        <div class="section-header">
            <h2>Riwayat Scan Undertone Wajah</h2>
            <span class="history-count"><?= count($scanHistory) ?> Hasil</span>
        </div>

        <?php if (empty($scanHistory)): ?>
            <div class="history-empty-card">
                <div class="empty-icon-wrap" aria-hidden="true">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                        <circle cx="12" cy="13" r="4"></circle>
                    </svg>
                </div>
                <h3>Belum Ada Riwayat Scan</h3>
                <p>Gunakan kamera untuk mendeteksi apakah undertone kulit wajah Anda Warm, Cool, atau Neutral beserta rekomendasi warna terbaik.</p>
                <a href="index.php" class="btn btn-primary btn-sm">Mulai Scan Sekarang</a>
            </div>
        <?php else: ?>
            <div class="history-list">
                <?php foreach ($scanHistory as $scan): 
                    $undertoneClass = strtolower($scan['undertone']);
                    $faceShapeVal = $scan['face_shape'] ?? '';
                    if (empty($faceShapeVal) && !empty($scan['recommendations'])) {
                        if (preg_match('/Bentuk Wajah\s+([A-Za-z]+):/i', $scan['recommendations'], $m)) {
                            $faceShapeVal = ucfirst(strtolower($m[1]));
                        }
                    }
                    $scan['face_shape'] = $faceShapeVal;
                    $scanJson = htmlspecialchars(json_encode($scan), ENT_QUOTES, 'UTF-8');
                ?>
                    <div class="history-card clickable-history-card" onclick="openScanDetailModal(this)" data-scan='<?= $scanJson ?>' tabindex="0" role="button" aria-label="Lihat detail hasil scan">
                        <div class="history-card-header">
                            <div class="undertone-tag-group">
                                <span class="undertone-badge <?= htmlspecialchars($undertoneClass) ?>">
                                    <?= htmlspecialchars($scan['undertone']) ?> Undertone
                                </span>
                                <?php if (!empty($faceShapeVal)): ?>
                                    <span class="shape-badge">
                                        <?= htmlspecialchars($faceShapeVal) ?> Face
                                    </span>
                                <?php endif; ?>
                                <span class="accuracy-pill"><?= (int)$scan['accuracy'] ?>% Akurasi</span>
                            </div>
                            <div class="history-header-right">
                                <time class="history-date"><?= date('d M Y, H:i', strtotime($scan['created_at'])) ?></time>
                                <form method="POST" action="profile.php" onsubmit="return confirm('Hapus riwayat scan ini?');" class="delete-history-form" onclick="event.stopPropagation();">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="delete_scan_id" value="<?= (int)$scan['id'] ?>">
                                    <button type="submit" class="btn-delete-history" title="Hapus riwayat" aria-label="Hapus riwayat" onclick="event.stopPropagation();">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="3 6 5 6 21 6"></polyline>
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="history-card-body">
                            <div class="history-meta">
                                <?php if (!empty($scan['dominant_color'])): ?>
                                    <span class="color-indicator" style="background-color: <?= htmlspecialchars($scan['dominant_color']) ?>;" title="Sampel Warna: <?= htmlspecialchars($scan['dominant_color']) ?>"></span>
                                <?php endif; ?>
                                <span class="skin-tone-label">Tone: <strong><?= htmlspecialchars($scan['skin_tone'] ?? 'Medium') ?></strong></span>
                                <?php if (!empty($faceShapeVal)): ?>
                                    <span class="face-shape-meta">&bull; Wajah: <strong><?= htmlspecialchars($faceShapeVal) ?></strong></span>
                                <?php endif; ?>
                                <span class="view-detail-hint">
                                    Lihat Detail
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="9 18 15 12 9 6"></polyline>
                                    </svg>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal-overlay" id="editNameModal" aria-hidden="true">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Ubah Nama Lengkap</h3>
            <button type="button" class="modal-close" onclick="closeEditNameModal()" aria-label="Tutup">&times;</button>
        </div>
        <form method="POST" action="profile.php" class="auth-form">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="new_name">Nama Lengkap</label>
                <input type="text" id="new_name" name="new_name" value="<?= htmlspecialchars($user['name']) ?>" required minlength="2" maxlength="100">
            </div>
            <div class="actions-row actions-right" style="margin-top: 0.75rem;">
                <button type="button" class="btn btn-secondary" onclick="closeEditNameModal()">Batal</button>
                <button type="submit" name="update_name" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="editEmailModal" aria-hidden="true">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Ubah Alamat Email</h3>
            <button type="button" class="modal-close" onclick="closeEditEmailModal()" aria-label="Tutup">&times;</button>
        </div>
        <p class="modal-desc">Perbaiki alamat email Anda jika terjadi kesalahan ketik (typo). Status verifikasi email akan di-reset setelah email diubah.</p>
        <form method="POST" action="profile.php" class="auth-form">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="new_email">Alamat Email Baru</label>
                <input type="email" id="new_email" name="new_email" value="<?= htmlspecialchars($user['email']) ?>" required>
            </div>
            <div class="actions-row actions-right" style="margin-top: 0.75rem;">
                <button type="button" class="btn btn-secondary" onclick="closeEditEmailModal()">Batal</button>
                <button type="submit" name="update_email" class="btn btn-primary">Simpan Email</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="verifyEmailModal" aria-hidden="true">
    <div class="modal-card modal-sm">
        <div class="modal-close-row">
            <button type="button" class="modal-close" onclick="closeVerifyEmailModal()" aria-label="Tutup">&times;</button>
        </div>

        <div class="otp-modal-header">
            <div class="otp-icon-badge" aria-hidden="true">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                    <polyline points="22,6 12,13 2,6"></polyline>
                </svg>
            </div>
            <h3>Konfirmasi Email Akun</h3>
            <p class="otp-modal-subtitle">
                Kode 6 digit OTP dikirimkan ke alamat email:<br>
                <span class="email-pill"><?= htmlspecialchars($user['email']) ?></span>
            </p>
        </div>

        <?php if (isset($_GET['success']) && $_GET['success'] === 'code_sent'): ?>
            <div class="alert alert-success text-center">
                Kode verifikasi 6 digit telah dikirim! Silakan periksa inbox atau spam email Anda.
            </div>
        <?php endif; ?>

        <?php if (!empty($verifyError)): ?>
            <div class="alert alert-error text-center">
                <?= htmlspecialchars($verifyError) ?>
            </div>
        <?php endif; ?>


        <form method="POST" action="profile.php" class="auth-form" style="margin-bottom: 0.5rem;">
            <?= csrf_field() ?>
            <input type="hidden" name="verify_email_code" value="1">
            <div class="form-group" style="margin-bottom: 1.15rem;">
                <label for="verification_code" style="text-align: center; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.5rem;">Masukkan 6 Digit Kode Konfirmasi</label>
                <div class="otp-input-wrapper">
                    <input type="text" id="verification_code" name="verification_code" 
                           maxlength="6" pattern="[0-9]{6}" inputmode="numeric" autocomplete="one-time-code"
                           placeholder="······" required class="otp-field">
                </div>
                <p class="otp-hint">Masa aktif kode verifikasi: 15 menit</p>
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                Konfirmasi Sekarang
            </button>
        </form>

        <div class="resend-box">
            <span style="font-size: 0.8rem; color: var(--text-muted);">Belum menerima kode?</span>
            <form method="POST" action="profile.php" style="display: inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="send_verification" value="1">
                <button type="submit" class="btn-link-resend">
                    Kirim Ulang Kode Verifikasi
                </button>
            </form>
        </div>
    </div>
</div>

<div class="modal-overlay" id="scanDetailModal" aria-hidden="true">
    <div class="modal-card modal-scan-detail">
        <div class="modal-header">
            <div>
                <span class="modal-pretitle" id="modalScanDate">Tanggal Scan</span>
                <h3 id="modalScanTitle">Detail Hasil Analisis Undertone</h3>
            </div>
            <button type="button" class="modal-close" onclick="closeScanDetailModal()" aria-label="Tutup">&times;</button>
        </div>
        <div class="modal-body">
            <div class="scan-detail-hero">
                <div class="hero-left">
                    <span class="undertone-badge-lg" id="modalUndertoneBadge">Warm Undertone</span>
                    <span class="shape-badge-lg" id="modalFaceShapeBadge" style="display: none;">Oval Face</span>
                    <span class="accuracy-pill-lg" id="modalAccuracy">96% Akurasi</span>
                </div>
                <div class="hero-right">
                    <div class="color-sample-box">
                        <span class="color-swatch-lg" id="modalColorSwatch"></span>
                        <div class="color-sample-info">
                            <span class="sample-label">Tone Kulit</span>
                            <strong id="modalSkinTone">Medium</strong>
                            <span class="hex-label" id="modalHexColor">#E2A76F</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="detail-section">
                <h4 class="detail-section-title">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="16" x2="12" y2="12"></line>
                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                    Karakteristik & Ciri Alami
                </h4>
                <p class="detail-desc" id="modalCharacteristics"></p>
            </div>

            <div class="detail-section">
                <h4 class="detail-section-title">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20.38 3.46 16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23z"></path>
                    </svg>
                    Palet Busana & Pakaian Terbaik
                </h4>
                <div class="swatches-grid" id="modalClothingSwatches"></div>
            </div>

            <div class="detail-section">
                <h4 class="detail-section-title">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
                    </svg>
                    Rekomendasi Lipstik & Makeup
                </h4>
                <div class="swatches-grid" id="modalLipstickSwatches"></div>
            </div>

            <div class="detail-section" id="modalFaceShapeSection" style="display: none;">
                <h4 class="detail-section-title">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M8 14s1.5 2 4 2 4-2 4-2"></path>
                        <line x1="9" y1="9" x2="9.01" y2="9"></line>
                        <line x1="15" y1="9" x2="15.01" y2="9"></line>
                    </svg>
                    Analisis Bentuk Wajah (<span id="modalFaceShapeTitle">Oval</span>)
                </h4>
                <p class="detail-desc" id="modalFaceShapeDesc"></p>
                <div class="face-shape-tips-grid">
                    <div class="face-shape-tip-card">
                        <div class="tip-card-header">
                            <span class="tip-icon">👓</span>
                            <strong>Model Kacamata Terbaik</strong>
                        </div>
                        <p id="modalEyewearTip" class="tip-card-text">-</p>
                    </div>
                    <div class="face-shape-tip-card">
                        <div class="tip-card-header">
                            <span class="tip-icon">💇</span>
                            <strong>Gaya Rambut & Hijab</strong>
                        </div>
                        <p id="modalHairstyleTip" class="tip-card-text">-</p>
                    </div>
                </div>
            </div>

            <div class="modal-detail-footer">
                <form method="POST" action="profile.php" onsubmit="return confirm('Hapus riwayat scan ini?');" id="modalDeleteForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="delete_scan_id" id="modalDeleteId" value="">
                    <button type="submit" class="btn-danger-outline">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        </svg>
                        Hapus Riwayat
                    </button>
                </form>
                <button type="button" class="btn btn-secondary btn-sm" onclick="closeScanDetailModal()">Tutup</button>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="deleteAccountModal" aria-hidden="true">
    <div class="modal-card modal-sm">
        <div class="modal-header">
            <h3 class="danger-title" style="font-size: 1.15rem; margin: 0;">Hapus Akun Permanen</h3>
            <button type="button" class="modal-close" onclick="closeDeleteAccountModal()" aria-label="Tutup">&times;</button>
        </div>
        <div class="modal-body">
            <div class="alert alert-error" style="line-height: 1.45;">
                <strong>Peringatan:</strong> Tindakan ini bersifat permanen. Seluruh profil dan riwayat analisis undertone Anda akan dihapus dari sistem.
            </div>

            <?php if (!empty($deleteAccountError)): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($deleteAccountError) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="profile.php" class="auth-form">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="delete_confirm_password">Masukkan Password Anda untuk Konfirmasi</label>
                    <div class="password-input-wrapper">
                        <input type="password" id="delete_confirm_password" name="confirm_password" required placeholder="Password akun Anda">
                        <button type="button" class="btn-toggle-password" aria-label="Lihat password" title="Lihat password">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>
                </div>
                <div class="actions-row actions-right" style="margin-top: 0.75rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteAccountModal()">Batal</button>
                    <button type="submit" name="delete_account" class="btn btn-danger">
                        Ya, Hapus Akun
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>



<script>
function openEditNameModal() {
    const modal = document.getElementById('editNameModal');
    if (modal) {
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        setTimeout(() => document.getElementById('new_name')?.focus(), 50);
    }
}
function closeEditNameModal() {
    const modal = document.getElementById('editNameModal');
    if (modal) {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
    }
}

function openEditEmailModal() {
    const modal = document.getElementById('editEmailModal');
    if (modal) {
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        setTimeout(() => document.getElementById('new_email')?.focus(), 50);
    }
}
function closeEditEmailModal() {
    const modal = document.getElementById('editEmailModal');
    if (modal) {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
    }
}

function openVerifyEmailModal() {
    const modal = document.getElementById('verifyEmailModal');
    if (modal) {
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        setTimeout(() => document.getElementById('verification_code')?.focus(), 50);
    }
}
function closeVerifyEmailModal() {
    const modal = document.getElementById('verifyEmailModal');
    if (modal) {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
    }
}

['editNameModal', 'editEmailModal', 'verifyEmailModal', 'scanDetailModal', 'deleteAccountModal'].forEach(id => {
    document.getElementById(id)?.addEventListener('click', (e) => {
        if (e.target.id === id) {
            e.target.classList.remove('open');
            e.target.setAttribute('aria-hidden', 'true');
        }
    });
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeScanDetailModal();
        closeEditNameModal();
        closeEditEmailModal();
        closeVerifyEmailModal();
        closeDeleteAccountModal();
    }
});

document.querySelectorAll('.clickable-history-card').forEach(card => {
    card.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            openScanDetailModal(card);
        }
    });
});

const undertoneDetailsMap = {
    'Warm': {
        characteristics: 'Kulit Anda memiliki rona dasar hangat (keemasan, persik, atau kuning) di bawah permukaan kulit. Pembuluh darah di pergelangan tangan umumnya cenderung kehijauan. Warna kulit Anda tampak semakin bersinar cerah saat mengenakan busana berelemen hangat dan perhiasan emas.',
        clothing: [
            { name: 'Terracotta', hex: '#D95D39' },
            { name: 'Peach', hex: '#F4A261' },
            { name: 'Mustard', hex: '#E9C46A' },
            { name: 'Olive Green', hex: '#606C38' },
            { name: 'Warm Gold', hex: '#D4AF37' }
        ],
        lipstick: [
            { name: 'Coral', hex: '#E76F51' },
            { name: 'Brick Red', hex: '#A44234' },
            { name: 'Warm Nude', hex: '#C88A68' }
        ]
    },
    'Cool': {
        characteristics: 'Kulit Anda memiliki rona dasar sejuk (merah muda, kebiruan, atau kemerahan) di bawah permukaan kulit. Pembuluh darah di pergelangan tangan umumnya cenderung berwarna biru atau ungu. Sangat memikat dan selaras dengan warna-warna sejuk dan perhiasan perak/silver.',
        clothing: [
            { name: 'Berry', hex: '#800E34' },
            { name: 'Emerald', hex: '#007F5F' },
            { name: 'Royal Blue', hex: '#1D3557' },
            { name: 'Ice Silver', hex: '#94A3B8' },
            { name: 'Lavender', hex: '#BDB2FF' }
        ],
        lipstick: [
            { name: 'Rose Pink', hex: '#D94676' },
            { name: 'Plum', hex: '#6F1D46' },
            { name: 'Berry Red', hex: '#990F3D' }
        ]
    },
    'Neutral': {
        characteristics: 'Kulit Anda memiliki keseimbangan harmonis antara rona hangat dan sejuk. Pembuluh darah di pergelangan tangan terlihat kombinasi antara biru dan hijau. Anda sangat fleksibel dan serasi mengenakan perhiasan emas maupun perak.',
        clothing: [
            { name: 'Dusty Pink', hex: '#D48C8C' },
            { name: 'Jade Green', hex: '#81B29A' },
            { name: 'Soft Navy', hex: '#3D5A80' },
            { name: 'Rose Gold', hex: '#B76E79' },
            { name: 'Soft Sand', hex: '#D2B48C' }
        ],
        lipstick: [
            { name: 'Soft Mauve', hex: '#9E5A63' },
            { name: 'Peachy Nude', hex: '#D88E75' },
            { name: 'Rose Nude', hex: '#C47B7B' }
        ]
    }
};

const faceShapeDetailsMap = {
    'Oval': {
        desc: 'Bentuk wajah oval memiliki proporsi alami yang seimbang, dahi sedikit lebih lebar dari dagu, dengan garis rahang melengkung lembut.',
        eyewear: 'Sangat fleksibel; hampir semua model bingkai cocok, terutama kacamata geometris, rectangular, atau cat-eye seimbang.',
        hair: 'Potongan layer panjang, bob sebahu bertekstur, atau belah samping natural.'
    },
    'Round': {
        desc: 'Bentuk wajah bulat memiliki panjang dan lebar wajah relatif sama dengan pipi penuh serta kontur rahang membulat.',
        eyewear: 'Bingkai bersudut tegas seperti rectangular (persegi panjang), wayfarer, atau cat-eye tinggi untuk memberi siluet tirus.',
        hair: 'Layer bertingkat panjang di bawah dagu, volume pada mahkota kepala, atau poni samping membingkai pipi.'
    },
    'Square': {
        desc: 'Bentuk wajah kotak memiliki garis rahang tegas dan lebar dahi, pipi, serta rahang yang simetris horizontal.',
        eyewear: 'Bingkai bulat (round), oval, atau kurva lembut untuk melembutkan ketegasan garis rahang.',
        hair: 'Gaya soft waves (ombak lembut), poni tirai (curtain bangs), atau layer bertingkat.'
    },
    'Heart': {
        desc: 'Bentuk wajah hati memiliki dahi yang relatif lebar, tulang pipi menonjol, dan dagu yang meruncing manis.',
        eyewear: 'Bingkai aviator, bulat tipis, atau kacamata dengan bagian bawah lebih lebar untuk menyeimbangkan area dagu.',
        hair: 'Bob sebahu dengan volume di dekat dagu atau curtain bangs membingkai dahi.'
    },
    'Diamond': {
        desc: 'Bentuk wajah diamond memiliki tulang pipi tinggi dan menonjol, dengan dahi serta dagu yang lebih ramping.',
        eyewear: 'Bingkai cat-eye melengkung halus, kacamata oval tanpa bingkai tebal (rimless), atau model browline.',
        hair: 'Potongan bob bervolume di dagu, poni samping lembut, atau layer sebahu.'
    },
    'Oblong': {
        desc: 'Bentuk wajah oblong (panjang) memiliki panjang wajah lebih dominan dengan garis pipi cenderung lurus vertikal.',
        eyewear: 'Bingkai lebar / oversized atau bingkai dengan ornamen dekoratif pada pelipis untuk menambah kesan proporsional.',
        hair: 'Poni penuh (blunt bangs) di dahi atau rambut bergelombang mekar di sisi samping.'
    }
};

function openScanDetailModal(cardEl) {
    const raw = cardEl.getAttribute('data-scan');
    if (!raw) return;
    try {
        const scan = JSON.parse(raw);
        const modal = document.getElementById('scanDetailModal');
        if (!modal) return;

        const dateObj = new Date(scan.created_at.replace(/-/g, '/'));
        const dateStr = isNaN(dateObj) ? scan.created_at : dateObj.toLocaleDateString('id-ID', {
            day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit'
        }) + ' WIB';
        document.getElementById('modalScanDate').textContent = dateStr;

        const undertone = scan.undertone || 'Neutral';
        const uBadge = document.getElementById('modalUndertoneBadge');
        uBadge.textContent = `${undertone} Undertone`;
        uBadge.className = `undertone-badge-lg ${undertone.toLowerCase()}`;

        // Face Shape handling
        const faceShape = scan.face_shape || '';
        const fsBadge = document.getElementById('modalFaceShapeBadge');
        const fsSection = document.getElementById('modalFaceShapeSection');
        if (faceShape) {
            if (fsBadge) {
                fsBadge.textContent = `${faceShape} Face`;
                fsBadge.style.display = 'inline-flex';
            }
            if (fsSection) {
                fsSection.style.display = 'block';
                const fsInfo = faceShapeDetailsMap[faceShape] || faceShapeDetailsMap['Oval'];
                document.getElementById('modalFaceShapeTitle').textContent = `${faceShape} Face`;
                document.getElementById('modalFaceShapeDesc').textContent = fsInfo.desc;
                document.getElementById('modalEyewearTip').textContent = fsInfo.eyewear;
                document.getElementById('modalHairstyleTip').textContent = fsInfo.hair;
            }
        } else {
            if (fsBadge) fsBadge.style.display = 'none';
            if (fsSection) fsSection.style.display = 'none';
        }

        document.getElementById('modalAccuracy').textContent = `${scan.accuracy || 95}% Akurasi`;
        document.getElementById('modalSkinTone').textContent = scan.skin_tone || 'Medium';

        const hex = scan.dominant_color || '#e2a76f';
        document.getElementById('modalHexColor').textContent = hex.toUpperCase();
        document.getElementById('modalColorSwatch').style.backgroundColor = hex;

        document.getElementById('modalDeleteId').value = scan.id;

        const info = undertoneDetailsMap[undertone] || undertoneDetailsMap['Neutral'];
        document.getElementById('modalCharacteristics').textContent = info.characteristics;

        const clothingWrap = document.getElementById('modalClothingSwatches');
        clothingWrap.innerHTML = info.clothing.map(item => `
            <div class="swatch-card">
                <span class="swatch-color" style="background-color: ${item.hex};"></span>
                <span class="swatch-name">${item.name}</span>
            </div>
        `).join('');

        const lipstickWrap = document.getElementById('modalLipstickSwatches');
        lipstickWrap.innerHTML = info.lipstick.map(item => `
            <div class="swatch-card">
                <span class="swatch-color" style="background-color: ${item.hex};"></span>
                <span class="swatch-name">${item.name}</span>
            </div>
        `).join('');

        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
    } catch (e) {
        console.error('Gagal membuka detail scan:', e);
    }
}

function closeScanDetailModal() {
    const modal = document.getElementById('scanDetailModal');
    if (modal) {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
    }
}

function openDeleteAccountModal() {
    const modal = document.getElementById('deleteAccountModal');
    if (modal) {
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        setTimeout(() => document.getElementById('delete_confirm_password')?.focus(), 50);
    }
}

function closeDeleteAccountModal() {
    const modal = document.getElementById('deleteAccountModal');
    if (modal) {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
    }
}

<?php if (!empty($emailError)): ?>
openEditEmailModal();
<?php elseif (!empty($verifyError) || (isset($_GET['action']) && $_GET['action'] === 'verify')): ?>
openVerifyEmailModal();
<?php elseif (!empty($deleteAccountError)): ?>
openDeleteAccountModal();
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
