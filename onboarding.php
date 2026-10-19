<?php
require_once __DIR__ . '/includes/auth.php';

if (current_user()) {
    header('Location: index.php');
    exit;
}

if (isset($_GET['action'])) {
    setcookie('glossa_onboarded', '1', [
        'expires' => time() + (86400 * 365),
        'path' => '/',
        'httponly' => false,
        'samesite' => 'Lax'
    ]);
    header('Location: index.php?skip_onboarding=1');
    exit;
}

$pageTitle = 'Glossa - Know your Natural Beauty';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="beauty-onboarding-body">

<div class="beauty-onboarding-container" id="onboardingContainer">
    <header class="beauty-top-bar">
        <a href="index.php" class="beauty-brand">glossa</a>
        <a href="onboarding.php?action=skip" class="beauty-skip" onclick="setOnboardingCookie()">Skip</a>
    </header>

    <div class="beauty-slider" id="slider">
        <div class="beauty-slide active" data-index="0">
            <div class="beauty-slide-content">
                <span class="beauty-slide-tag">Personal Analysis</span>
                <h1 class="beauty-title">Know your<br><span>Natural Beauty</span></h1>
                <p class="beauty-desc">Scan your face and get personalized recommendations for makeup, outfit, and more.</p>
                <div class="beauty-action-group">
                    <button type="button" class="beauty-btn-next" onclick="handleNext()">Next</button>
                    <div class="beauty-dots">
                        <button type="button" class="b-dot active" aria-label="Slide 1" onclick="goToSlide(0)"></button>
                        <button type="button" class="b-dot" aria-label="Slide 2" onclick="goToSlide(1)"></button>
                        <button type="button" class="b-dot" aria-label="Slide 3" onclick="goToSlide(2)"></button>
                    </div>
                </div>
            </div>
            <div class="beauty-visual-col">
                <div class="beauty-blob-backdrop"></div>
                <svg class="beauty-orbit-ring ring-1" viewBox="0 0 460 260" fill="none">
                    <ellipse cx="230" cy="130" rx="210" ry="75" stroke="#be8d8d" stroke-width="1.2" transform="rotate(-15 230 130)" />
                </svg>
                <svg class="beauty-orbit-ring ring-2" viewBox="0 0 460 260" fill="none">
                    <ellipse cx="230" cy="130" rx="190" ry="55" stroke="#deb5b5" stroke-width="1" stroke-dasharray="6 6" transform="rotate(22 230 130)" />
                </svg>
                <div class="beauty-photo-frame">
                    <img src="assets/images/slide1.jpg" alt="Natural Beauty Portrait" class="beauty-photo">
                </div>
            </div>
        </div>

        <div class="beauty-slide" data-index="1">
            <div class="beauty-slide-content">
                <span class="beauty-slide-tag">Smart Detection</span>
                <h1 class="beauty-title">Instant Smart<br><span>Camera Scan</span></h1>
                <p class="beauty-desc">Detect skin undertones, facial contours, and features accurately through your device camera.</p>
                <div class="beauty-action-group">
                    <button type="button" class="beauty-btn-next" onclick="handleNext()">Next</button>
                    <div class="beauty-dots">
                        <button type="button" class="b-dot" aria-label="Slide 1" onclick="goToSlide(0)"></button>
                        <button type="button" class="b-dot active" aria-label="Slide 2" onclick="goToSlide(1)"></button>
                        <button type="button" class="b-dot" aria-label="Slide 3" onclick="goToSlide(2)"></button>
                    </div>
                </div>
            </div>
            <div class="beauty-visual-col">
                <div class="beauty-blob-backdrop blob-alt"></div>
                <svg class="beauty-orbit-ring ring-1" viewBox="0 0 460 260" fill="none">
                    <ellipse cx="230" cy="130" rx="210" ry="75" stroke="#be8d8d" stroke-width="1.2" transform="rotate(-8 230 130)" />
                </svg>
                <svg class="beauty-orbit-ring ring-2" viewBox="0 0 460 260" fill="none">
                    <ellipse cx="230" cy="130" rx="190" ry="55" stroke="#deb5b5" stroke-width="1" stroke-dasharray="6 6" transform="rotate(30 230 130)" />
                </svg>
                <div class="beauty-photo-frame">
                    <img src="assets/images/slide2.jpg" alt="Smart Camera Scan" class="beauty-photo">
                </div>
            </div>
        </div>

        <div class="beauty-slide" data-index="2">
            <div class="beauty-slide-content">
                <span class="beauty-slide-tag">Personalized Style</span>
                <h1 class="beauty-title">Curated Beauty<br><span>Just For You</span></h1>
                <p class="beauty-desc">Receive tailored cosmetic shades, color palettes, and daily guides crafted exclusively for your glow.</p>
                <div class="beauty-action-group">
                    <button type="button" class="beauty-btn-next" onclick="handleNext()">Get Started</button>
                    <div class="beauty-dots">
                        <button type="button" class="b-dot" aria-label="Slide 1" onclick="goToSlide(0)"></button>
                        <button type="button" class="b-dot" aria-label="Slide 2" onclick="goToSlide(1)"></button>
                        <button type="button" class="b-dot active" aria-label="Slide 3" onclick="goToSlide(2)"></button>
                    </div>
                </div>
            </div>
            <div class="beauty-visual-col">
                <div class="beauty-blob-backdrop blob-alt-2"></div>
                <svg class="beauty-orbit-ring ring-1" viewBox="0 0 460 260" fill="none">
                    <ellipse cx="230" cy="130" rx="210" ry="75" stroke="#be8d8d" stroke-width="1.2" transform="rotate(-20 230 130)" />
                </svg>
                <svg class="beauty-orbit-ring ring-2" viewBox="0 0 460 260" fill="none">
                    <ellipse cx="230" cy="130" rx="190" ry="55" stroke="#deb5b5" stroke-width="1" stroke-dasharray="6 6" transform="rotate(18 230 130)" />
                </svg>
                <div class="beauty-photo-frame">
                    <img src="assets/images/slide3.jpg" alt="Curated Style Recommendations" class="beauty-photo">
                </div>
            </div>
        </div>
    </div>

    <button type="button" class="beauty-nav-arrow arrow-prev" id="arrowPrev" aria-label="Previous Slide" onclick="prevSlide()">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
    </button>
    <button type="button" class="beauty-nav-arrow arrow-next" id="arrowNext" aria-label="Next Slide" onclick="handleNext()">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="9 18 15 12 9 6"></polyline>
        </svg>
    </button>
</div>

<script>
function setOnboardingCookie() {
    document.cookie = "glossa_onboarded=1; path=/; max-age=" + (365 * 24 * 60 * 60) + "; SameSite=Lax";
}

let currentIndex = 0;
const slides = document.querySelectorAll('.beauty-slide');
const arrowPrev = document.getElementById('arrowPrev');
const arrowNext = document.getElementById('arrowNext');
const totalSlides = slides.length;

function updateSlides(index) {
    currentIndex = index;
    slides.forEach((slide, i) => {
        slide.classList.toggle('active', i === currentIndex);
    });

    if (arrowPrev) {
        arrowPrev.style.opacity = (currentIndex === 0) ? '0.35' : '1';
        arrowPrev.style.pointerEvents = (currentIndex === 0) ? 'none' : 'auto';
    }
}

function handleNext() {
    if (currentIndex < totalSlides - 1) {
        updateSlides(currentIndex + 1);
    } else {
        finishOnboarding();
    }
}

function prevSlide() {
    if (currentIndex > 0) {
        updateSlides(currentIndex - 1);
    }
}

function goToSlide(index) {
    if (index >= 0 && index < totalSlides) {
        updateSlides(index);
    }
}

function finishOnboarding() {
    setOnboardingCookie();
    window.location.href = 'onboarding.php?action=finish';
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowRight') handleNext();
    if (e.key === 'ArrowLeft') prevSlide();
});

let startX = 0;
let isDragging = false;
const sliderContainer = document.getElementById('onboardingContainer');

sliderContainer.addEventListener('touchstart', (e) => {
    startX = e.touches[0].clientX;
}, { passive: true });

sliderContainer.addEventListener('touchend', (e) => {
    const endX = e.changedTouches[0].clientX;
    const diff = startX - endX;
    if (Math.abs(diff) > 40) {
        if (diff > 0) handleNext();
        else prevSlide();
    }
}, { passive: true });

sliderContainer.addEventListener('mousedown', (e) => {
    isDragging = true;
    startX = e.clientX;
});

sliderContainer.addEventListener('mouseup', (e) => {
    if (!isDragging) return;
    isDragging = false;
    const diff = startX - e.clientX;
    if (Math.abs(diff) > 50) {
        if (diff > 0) handleNext();
        else prevSlide();
    }
});

sliderContainer.addEventListener('mouseleave', () => {
    isDragging = false;
});
</script>

</body>
</html>
