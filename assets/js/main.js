document.addEventListener('DOMContentLoaded', () => {
    const btnScan = document.getElementById('btnScanCamera');
    const authModal = document.getElementById('authModal');
    const closeAuthModal = document.getElementById('closeAuthModal');
    const tabLoginBtn = document.getElementById('tabLoginBtn');
    const tabRegisterBtn = document.getElementById('tabRegisterBtn');
    const modalLoginPanel = document.getElementById('modalLoginPanel');
    const modalRegisterPanel = document.getElementById('modalRegisterPanel');
    const modalLoginForm = document.getElementById('modalLoginForm');
    const modalLoginAlert = document.getElementById('modalLoginAlert');
    const modalLoginSubmitBtn = document.getElementById('modalLoginSubmitBtn');
    const modalRegisterForm = document.getElementById('modalRegisterForm');
    const modalRegisterAlert = document.getElementById('modalRegisterAlert');
    const modalRegisterSubmitBtn = document.getElementById('modalRegisterSubmitBtn');

    const cameraModal = document.getElementById('cameraModal');
    const closeCameraModal = document.getElementById('closeCameraModal');
    const btnStopCamera = document.getElementById('btnStopCamera');
    const btnCapture = document.getElementById('btnCapture');
    const cameraVideo = document.getElementById('cameraVideo');
    const cameraStatus = document.getElementById('cameraStatus');
    const cameraFlash = document.getElementById('cameraFlash');
    const scannerFrame = document.querySelector('.scanner-frame');
    let mediaStream = null;

    function playShutterSound() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'triangle';
            osc.frequency.setValueAtTime(600, ctx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(80, ctx.currentTime + 0.08);
            gain.gain.setValueAtTime(0.4, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.08);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.08);
        } catch (e) {}
    }

    if (!btnScan) return;

    function openModal(modal) {
        if (!modal) return;
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
    }

    function closeModal(modal) {
        if (!modal) return;
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
    }

    async function startCamera() {
        openModal(cameraModal);
        if (scannerFrame) scannerFrame.style.display = 'block';
        if (scanResultBox) scanResultBox.style.display = 'none';
        if (cameraActions) cameraActions.style.display = 'flex';
        if (cameraStatus) {
            cameraStatus.style.display = 'block';
            cameraStatus.textContent = 'Menghubungkan ke kamera...';
        }

        try {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                throw new Error('Browser tidak mendukung akses kamera.');
            }
            mediaStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user' }
            });
            if (cameraVideo) {
                cameraVideo.srcObject = mediaStream;
                if (cameraStatus) cameraStatus.style.display = 'none';
            }
        } catch (err) {
            console.error('Camera error:', err);
            if (cameraStatus) {
                cameraStatus.style.display = 'block';
                cameraStatus.textContent = 'Gagal mengakses kamera: ' + (err.message || 'Izin ditolak.');
            }
        }
    }

    function stopCamera() {
        if (mediaStream) {
            mediaStream.getTracks().forEach(track => track.stop());
            mediaStream = null;
        }
        if (cameraVideo) {
            cameraVideo.srcObject = null;
        }
        if (scannerFrame) scannerFrame.style.display = 'block';
        if (scanResultBox) scanResultBox.style.display = 'none';
        if (cameraActions) cameraActions.style.display = 'flex';
        closeModal(cameraModal);
    }

    btnScan.addEventListener('click', (e) => {
        e.preventDefault();
        const isAuth = btnScan.getAttribute('data-auth') === '1';
        if (isAuth) {
            startCamera();
        } else {
            openModal(authModal);
        }
    });

    if (tabLoginBtn && tabRegisterBtn && modalLoginPanel && modalRegisterPanel) {
        tabLoginBtn.addEventListener('click', () => {
            tabLoginBtn.classList.add('active');
            tabRegisterBtn.classList.remove('active');
            modalLoginPanel.style.display = 'block';
            modalRegisterPanel.style.display = 'none';
        });

        tabRegisterBtn.addEventListener('click', () => {
            tabRegisterBtn.classList.add('active');
            tabLoginBtn.classList.remove('active');
            modalRegisterPanel.style.display = 'block';
            modalLoginPanel.style.display = 'none';
        });
    }

    if (closeAuthModal) {
        closeAuthModal.addEventListener('click', () => closeModal(authModal));
    }
    if (closeCameraModal) {
        closeCameraModal.addEventListener('click', stopCamera);
    }
    if (btnStopCamera) {
        btnStopCamera.addEventListener('click', stopCamera);
    }

    const cameraActions = document.getElementById('cameraActions');
    const scanResultBox = document.getElementById('scanResultBox');
    const resUndertoneBadge = document.getElementById('resUndertoneBadge');
    const resAccuracyBadge = document.getElementById('resAccuracyBadge');
    const resRecommendation = document.getElementById('resRecommendation');
    const btnScanAgain = document.getElementById('btnScanAgain');

    if (btnCapture) {
        btnCapture.addEventListener('click', async () => {
            btnCapture.disabled = true;
            btnCapture.textContent = 'Menganalisis...';

            if (cameraFlash) {
                cameraFlash.classList.add('flash');
                setTimeout(() => cameraFlash.classList.remove('flash'), 120);
            }
            playShutterSound();

            if (cameraVideo) {
                cameraVideo.pause();
            }
            if (scannerFrame) {
                scannerFrame.style.display = 'none';
            }

            let hexColor = '#e3a86c';
            let skinTone = 'Medium';
            let imageData = null;

            if (cameraVideo && cameraVideo.videoWidth > 0 && cameraVideo.videoHeight > 0) {
                try {
                    const canvas = document.createElement('canvas');
                    const maxDim = 640;
                    let w = cameraVideo.videoWidth;
                    let h = cameraVideo.videoHeight;
                    if (w > maxDim || h > maxDim) {
                        if (w > h) {
                            h = Math.round((h * maxDim) / w);
                            w = maxDim;
                        } else {
                            w = Math.round((w * maxDim) / h);
                            h = maxDim;
                        }
                    }
                    canvas.width = w;
                    canvas.height = h;
                    const ctx = canvas.getContext('2d');
                    ctx.translate(w, 0);
                    ctx.scale(-1, 1);
                    ctx.drawImage(cameraVideo, 0, 0, w, h);

                    imageData = canvas.toDataURL('image/jpeg', 0.85);

                    const cx = Math.floor(w / 2);
                    const cy = Math.floor(h / 2);
                    const pixelData = ctx.getImageData(Math.max(0, cx - 15), Math.max(0, cy - 15), 30, 30).data;

                    let r = 0, g = 0, b = 0, count = 0;
                    for (let i = 0; i < pixelData.length; i += 4) {
                        r += pixelData[i];
                        g += pixelData[i + 1];
                        b += pixelData[i + 2];
                        count++;
                    }
                    if (count > 0) {
                        r = Math.round(r / count);
                        g = Math.round(g / count);
                        b = Math.round(b / count);
                        hexColor = '#' + [r, g, b].map(x => x.toString(16).padStart(2, '0')).join('');
                        const brightness = (r * 299 + g * 587 + b * 114) / 1000;
                        if (brightness > 180) skinTone = 'Fair';
                        else if (brightness > 140) skinTone = 'Medium';
                        else if (brightness > 95) skinTone = 'Tan';
                        else skinTone = 'Deep';
                    }
                } catch (e) {
                    console.warn('Gagal membaca canvas video:', e);
                }
            }

            try {
                const response = await fetch('save_scan.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        image: imageData,
                        skin_tone: skinTone,
                        dominant_color: hexColor
                    })
                });
                const res = await response.json();
                if (!res.success) {
                    throw new Error(res.error || 'Gagal menganalisis gambar.');
                }

                const scan = res.scan;
                if (resUndertoneBadge) {
                    resUndertoneBadge.textContent = `${scan.undertone} Undertone`;
                    resUndertoneBadge.className = `undertone-badge ${scan.undertone.toLowerCase()}`;
                }
                if (resAccuracyBadge) {
                    resAccuracyBadge.textContent = `${scan.accuracy}% Akurasi`;
                }
                if (resRecommendation) {
                    resRecommendation.textContent = scan.recommendations;
                }
                if (cameraActions) cameraActions.style.display = 'none';
                if (scanResultBox) scanResultBox.style.display = 'block';
            } catch (err) {
                console.error('Error memproses scan:', err);
                alert(err.message || 'Terjadi kesalahan saat memproses scan.');
            } finally {
                btnCapture.disabled = false;
                btnCapture.textContent = 'Ambil Gambar & Analisis';
            }
        });
    }

    if (btnScanAgain) {
        btnScanAgain.addEventListener('click', () => {
            if (scanResultBox) scanResultBox.style.display = 'none';
            if (cameraActions) cameraActions.style.display = 'flex';
            if (scannerFrame) scannerFrame.style.display = 'block';
            if (cameraVideo) cameraVideo.play();
        });
    }

    [authModal, cameraModal].forEach(modal => {
        if (!modal) return;
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                if (modal === cameraModal) stopCamera();
                else closeModal(modal);
            }
        });
    });

    if (modalLoginForm) {
        modalLoginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (modalLoginAlert) modalLoginAlert.style.display = 'none';
            if (modalLoginSubmitBtn) {
                modalLoginSubmitBtn.disabled = true;
                modalLoginSubmitBtn.textContent = 'Memverifikasi...';
            }

            try {
                const response = await fetch('login.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(modalLoginForm)
                });
                const res = await response.json();
                if (res.success) {
                    closeModal(authModal);
                    window.location.href = 'index.php';
                } else {
                    if (modalLoginAlert) {
                        modalLoginAlert.textContent = res.error || 'Login gagal.';
                        modalLoginAlert.style.display = 'block';
                    }
                }
            } catch (err) {
                modalLoginForm.submit();
            } finally {
                if (modalLoginSubmitBtn) {
                    modalLoginSubmitBtn.disabled = false;
                    modalLoginSubmitBtn.textContent = 'Masuk Sekarang';
                }
            }
        });
    }

    if (modalRegisterForm) {
        modalRegisterForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (modalRegisterAlert) modalRegisterAlert.style.display = 'none';
            if (modalRegisterSubmitBtn) {
                modalRegisterSubmitBtn.disabled = true;
                modalRegisterSubmitBtn.textContent = 'Mendaftarkan...';
            }

            try {
                const response = await fetch('register.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(modalRegisterForm)
                });
                const res = await response.json();
                if (res.success) {
                    closeModal(authModal);
                    window.location.href = 'index.php';
                } else {
                    if (modalRegisterAlert) {
                        modalRegisterAlert.textContent = res.error || 'Pendaftaran gagal.';
                        modalRegisterAlert.style.display = 'block';
                    }
                }
            } catch (err) {
                modalRegisterForm.submit();
            } finally {
                if (modalRegisterSubmitBtn) {
                    modalRegisterSubmitBtn.disabled = false;
                    modalRegisterSubmitBtn.textContent = 'Daftar Akun Sekarang';
                }
            }
        });
    }
});
