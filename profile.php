<?php
require_once __DIR__ . '/includes/auth.php';
require_auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_scan_id'])) {
    $scanId = (int)$_POST['delete_scan_id'];
    if ($scanId > 0) {
        $delStmt = $pdo->prepare('DELETE FROM scan_history WHERE id = ? AND user_id = ?');
        $delStmt->execute([$scanId, $_SESSION['user_id']]);
    }
    header('Location: profile.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id, name, email, birth_date, age, gender, created_at FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    logout_user();
    header('Location: login.php');
    exit;
}

$scanStmt = $pdo->prepare('SELECT id, undertone, skin_tone, accuracy, recommendations, dominant_color, created_at FROM scan_history WHERE user_id = ? ORDER BY created_at DESC');
$scanStmt->execute([$_SESSION['user_id']]);
$scanHistory = $scanStmt->fetchAll();

$pageTitle = 'Dashboard Profil - glossa';
require_once __DIR__ . '/includes/header.php';
?>

<div class="profile-wrapper">
    <div class="profile-card">
        <div class="profile-header">
            <div class="profile-avatar">
                <?= strtoupper(mb_substr($user['name'], 0, 1)) ?>
            </div>
            <div class="profile-titles">
                <h1><?= htmlspecialchars($user['name']) ?></h1>
                <p class="profile-email"><?= htmlspecialchars($user['email']) ?></p>
            </div>
        </div>

        <div class="profile-details">
            <div class="detail-row">
                <span class="detail-label">Nama Lengkap</span>
                <span class="detail-value"><?= htmlspecialchars($user['name']) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Email</span>
                <span class="detail-value"><?= htmlspecialchars($user['email']) ?></span>
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
                ?>
                    <div class="history-card">
                        <div class="history-card-header">
                            <div class="undertone-tag-group">
                                <span class="undertone-badge <?= htmlspecialchars($undertoneClass) ?>">
                                    <?= htmlspecialchars($scan['undertone']) ?> Undertone
                                </span>
                                <span class="accuracy-pill"><?= (int)$scan['accuracy'] ?>% Akurasi</span>
                            </div>
                            <div class="history-header-right">
                                <time class="history-date"><?= date('d M Y, H:i', strtotime($scan['created_at'])) ?></time>
                                <form method="POST" action="profile.php" onsubmit="return confirm('Hapus riwayat scan ini?');" class="delete-history-form">
                                    <input type="hidden" name="delete_scan_id" value="<?= (int)$scan['id'] ?>">
                                    <button type="submit" class="btn-delete-history" title="Hapus riwayat" aria-label="Hapus riwayat">
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
                                    <span class="color-indicator" style="background-color: <?= htmlspecialchars($scan['dominant_color']) ?>;" title="Sampel Warna"></span>
                                <?php endif; ?>
                                <span class="skin-tone-label">Tone: <strong><?= htmlspecialchars($scan['skin_tone'] ?? 'Medium') ?></strong></span>
                            </div>
                            <p class="history-rec"><?= htmlspecialchars($scan['recommendations']) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
