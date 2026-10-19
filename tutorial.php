<?php
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Tutorial - glossa';
require_once __DIR__ . '/includes/header.php';
?>

<div class="tutorial-wrapper">
    <div class="tutorial-header">
        <h1>Panduan Scan Undertone</h1>
        <p>Ikuti 4 langkah mudah ini untuk mendapatkan hasil analisa undertone wajah dan rekomendasi gaya yang paling akurat.</p>
    </div>

    <div class="tutorial-grid">
        <div class="tutorial-card">
            <span class="tutorial-step-badge">1</span>
            <h2 class="tutorial-step-title">Pencahayaan Tepat</h2>
            <p class="tutorial-step-desc">Gunakan cahaya alami (seperti di dekat jendela) atau lampu putih yang merata. Hindari pencahayaan dari belakang (backlight) atau lampu kuning yang dapat mempengaruhi warna asli kulit.</p>
        </div>

        <div class="tutorial-card">
            <span class="tutorial-step-badge">2</span>
            <h2 class="tutorial-step-title">Bebas Penghalang</h2>
            <p class="tutorial-step-desc">Lepas kacamata, topi, atau masker. Rapikan rambut agar tidak menutupi dahi dan pipi sehingga kamera dapat membaca area kulit wajah secara menyeluruh.</p>
        </div>

        <div class="tutorial-card">
            <span class="tutorial-step-badge">3</span>
            <h2 class="tutorial-step-title">Posisikan di Lingkaran</h2>
            <p class="tutorial-step-desc">Buka fitur scan kamera dan hadapkan wajah lurus sejajar ke layar. Posisikan seluruh bentuk wajah Anda pas di dalam bingkai lingkaran pemindai.</p>
        </div>

        <div class="tutorial-card">
            <span class="tutorial-step-badge">4</span>
            <h2 class="tutorial-step-title">Ambil & Analisa</h2>
            <p class="tutorial-step-desc">Tekan tombol "Ambil Gambar & Analisis". Kamera akan menangkap gambar dan menampilkan jenis undertone (Warm, Neutral, atau Cool) serta rekomendasi warna terbaik.</p>
        </div>
    </div>

    <div class="tutorial-cta">
        <h3>Siap Menemukan Undertone Kamu?</h3>
        <p>Coba langsung fitur scan kamera dan temukan palet warna yang paling cocok untukmu.</p>
        <a href="index.php" class="btn btn-primary">Buka Scan Kamera</a>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
