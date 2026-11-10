document.addEventListener('DOMContentLoaded', () => {
    const btnScan = document.getElementById('btnScanCamera');
    const authModal = document.getElementById('authModal');
    const closeAuthModal = document.getElementById('closeAuthModal');
    const toRegisterLink = document.getElementById('toRegisterLink');
    const toLoginLink = document.getElementById('toLoginLink');
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

    const eyeSvg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
    const eyeOffSvg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-toggle-password');
        if (!btn) return;
        e.preventDefault();
        const wrapper = btn.closest('.password-input-wrapper');
        const input = wrapper ? wrapper.querySelector('input') : null;
        if (!input) return;

        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        btn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
        btn.setAttribute('title', isPassword ? 'Hide password' : 'Show password');
        btn.innerHTML = isPassword ? eyeOffSvg : eyeSvg;
        input.focus();
    });

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
            cameraStatus.textContent = 'Connecting to camera...';
        }

        try {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                throw new Error('Browser does not support camera access.');
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
                cameraStatus.textContent = 'Failed to access camera: ' + (err.message || 'Permission denied.');
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
        stopAnalyzing();
        if (btnCapture) {
            btnCapture.disabled = false;
            btnCapture.classList.remove('is-analyzing');
        }
        if (shutterLabel) shutterLabel.textContent = 'Capture Photo & Analyze';
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
            showModalLogin();
            openModal(authModal);
        }
    });

        function showModalLogin(e) {
        if (e && e.preventDefault) e.preventDefault();
        if (modalLoginPanel) modalLoginPanel.style.display = 'block';
        if (modalRegisterPanel) modalRegisterPanel.style.display = 'none';
        if (modalLoginAlert) modalLoginAlert.style.display = 'none';
        if (modalRegisterAlert) modalRegisterAlert.style.display = 'none';
        openModal(authModal);
    }

    function showModalRegister(e) {
        if (e && e.preventDefault) e.preventDefault();
        if (modalRegisterPanel) modalRegisterPanel.style.display = 'block';
        if (modalLoginPanel) modalLoginPanel.style.display = 'none';
        if (modalLoginAlert) modalLoginAlert.style.display = 'none';
        if (modalRegisterAlert) modalRegisterAlert.style.display = 'none';
        openModal(authModal);
    }

    window.showModalLogin = showModalLogin;
    window.showModalRegister = showModalRegister;

    if (toRegisterLink) {
        toRegisterLink.addEventListener('click', (e) => {
            e.preventDefault();
            showModalRegister();
        });
    }

    if (toLoginLink) {
        toLoginLink.addEventListener('click', (e) => {
            e.preventDefault();
            showModalLogin();
        });
    }

    document.addEventListener('click', (e) => {
        const navLogin = e.target.closest('.nav-links a[href*="login"], #navLoginBtn');
        if (!navLogin) return;
        const modal = document.getElementById('authModal');
        if (modal) {
            e.preventDefault();
            showModalLogin();
        }
    });

    if (closeAuthModal) {
        closeAuthModal.addEventListener('click', () => closeModal(authModal));
    }
    if (closeCameraModal) {
        closeCameraModal.addEventListener('click', stopCamera);
    }

    const cameraActions = document.getElementById('cameraActions');
    const shutterLabel = document.getElementById('shutterLabel');
    const scanResultBox = document.getElementById('scanResultBox');
    const resUndertoneBadge = document.getElementById('resUndertoneBadge');
    const resFaceShapeBadge = document.getElementById('resFaceShapeBadge');
    const resAccuracyBadge = document.getElementById('resAccuracyBadge');
    const resRecommendation = document.getElementById('resRecommendation');
    const btnScanAgain = document.getElementById('btnScanAgain');

    const resRecGrid = document.getElementById('resRecGrid');
    const resRecUndertoneTitle = document.getElementById('resRecUndertoneTitle');
    const resRecClothing = document.getElementById('resRecClothing');
    const resRecLipstick = document.getElementById('resRecLipstick');
    const resRecJewelry = document.getElementById('resRecJewelry');
    const resRecFaceShapeCard = document.getElementById('resRecFaceShapeCard');
    const resRecFaceShapeTitle = document.getElementById('resRecFaceShapeTitle');
    const resRecEyewear = document.getElementById('resRecEyewear');
    const resRecHair = document.getElementById('resRecHair');

    const defaultToneRecs = {
        'Warm': {
            clothing: 'Terracotta, Peach, Mustard, Olive, Warm Gold',
            lipstick: 'Coral, Brick Red, Warm Nude',
            jewelry: 'Emas (Gold) & Tembaga hangat'
        },
        'Cool': {
            clothing: 'Berry, Emerald, Royal Blue, Ice Silver, Lavender',
            lipstick: 'Rose Pink, Plum, Berry Red',
            jewelry: 'Perak (Silver) & Platinum'
        },
        'Neutral': {
            clothing: 'Dusty Pink, Jade Green, Soft Navy, Rose Gold',
            lipstick: 'Soft Mauve, Peachy Nude, Rose Nude',
            jewelry: 'Rose Gold, Emas & Perak'
        }
    };

    const defaultShapeRecs = {
        'Oval': {
            eyewear: 'Geometris, rectangular, atau cat-eye seimbang',
            hair: 'Layer panjang, bob bertekstur, belah samping'
        },
        'Round': {
            eyewear: 'Persegi panjang (rectangular), wayfarer, sudut tegas',
            hair: 'Layer di bawah dagu, volume atas, poni samping'
        },
        'Square': {
            eyewear: 'Bulat (round), oval, bingkai kurva lembut',
            hair: 'Soft waves, curtain bangs, layer bertingkat'
        },
        'Heart': {
            eyewear: 'Aviator, bulat tipis, bingkai bawah lebar',
            hair: 'Bob sebahu bervolume dagu, curtain bangs'
        },
        'Diamond': {
            eyewear: 'Cat-eye melengkung halus, oval rimless, browline',
            hair: 'Bob bervolume di dagu, layer sebahu'
        },
        'Oblong': {
            eyewear: 'Oversized lebar, bingkai tebal samping',
            hair: 'Poni depan penuh, rambut bervolume samping'
        }
    };

    const analyzingOverlay = document.getElementById('analyzingOverlay');
    const analyzingText = document.getElementById('analyzingText');
    let analyzingInterval = null;

    function startAnalyzing() {
        if (!analyzingOverlay) return;
        analyzingOverlay.style.display = 'flex';
        const steps = [
            'Analyzing with AI...',
            'Detecting face contours & shape...',
            'Analyzing skin color spectrum...',
            'Calculating undertone accuracy...'
        ];
        let idx = 0;
        if (analyzingText) analyzingText.textContent = steps[0];
        clearInterval(analyzingInterval);
        analyzingInterval = setInterval(() => {
            idx = (idx + 1) % steps.length;
            if (analyzingText) analyzingText.textContent = steps[idx];
        }, 750);
    }

    function stopAnalyzing() {
        clearInterval(analyzingInterval);
        analyzingInterval = null;
        if (analyzingOverlay) analyzingOverlay.style.display = 'none';
    }

    if (btnCapture) {
        btnCapture.addEventListener('click', async () => {
            btnCapture.disabled = true;
            btnCapture.classList.add('is-analyzing');
            if (shutterLabel) shutterLabel.textContent = 'Analyzing...';
            startAnalyzing();

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
                    console.warn('Failed to read video canvas:', e);
                }
            }

            try {
                let csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                if (!csrfToken && window.GlossaApp && typeof window.GlossaApp.getCsrfToken === 'function') {
                    csrfToken = await window.GlossaApp.getCsrfToken();
                }
                const response = await fetch('api/scan.php', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({
                        image: imageData,
                        skin_tone: skinTone,
                        dominant_color: hexColor,
                        csrf_token: csrfToken
                    })
                });
                const res = await response.json();
                if (!res.success) {
                    throw new Error(res.error || 'Failed to analyze photo.');
                }

                const scan = res.scan;
                if (resUndertoneBadge) {
                    resUndertoneBadge.textContent = `${scan.undertone} Undertone`;
                    resUndertoneBadge.className = `undertone-badge ${scan.undertone.toLowerCase()}`;
                }
                if (resFaceShapeBadge) {
                    if (scan.face_shape) {
                        resFaceShapeBadge.textContent = `${scan.face_shape} Face`;
                        resFaceShapeBadge.style.display = 'inline-flex';
                    } else {
                        resFaceShapeBadge.style.display = 'none';
                    }
                }
                if (resAccuracyBadge) {
                    resAccuracyBadge.textContent = `${scan.accuracy}% Akurasi`;
                }
                if (resRecommendation) {
                    const safeSkin = (scan.skin_tone || 'Medium').replace(/[^\w\s-]/g, '');
                    resRecommendation.innerHTML = `Skin Tone: <strong>${safeSkin}</strong> &bull; Panduan personal rekomendasi gaya Anda:`;
                }

                // Render detail rekomendasi Undertone & Face Shape
                const uDetails = (scan.recommendation_details && scan.recommendation_details.undertone)
                    || defaultToneRecs[scan.undertone]
                    || defaultToneRecs['Neutral'];

                if (resRecClothing) resRecClothing.textContent = uDetails.clothing || '-';
                if (resRecLipstick) resRecLipstick.textContent = uDetails.lipstick || '-';
                if (resRecJewelry) resRecJewelry.textContent = uDetails.jewelry || '-';
                if (resRecUndertoneTitle) resRecUndertoneTitle.textContent = `Rekomendasi Undertone (${scan.undertone})`;

                const fShape = scan.face_shape;
                if (fShape) {
                    const fDetails = (scan.recommendation_details && scan.recommendation_details.face_shape)
                        || defaultShapeRecs[fShape]
                        || defaultShapeRecs['Oval'];
                    if (resRecEyewear) resRecEyewear.textContent = fDetails.eyewear || '-';
                    if (resRecHair) resRecHair.textContent = fDetails.hair || '-';
                    if (resRecFaceShapeTitle) resRecFaceShapeTitle.textContent = `Rekomendasi Wajah (${fShape})`;
                    if (resRecFaceShapeCard) resRecFaceShapeCard.style.display = 'flex';
                } else {
                    if (resRecFaceShapeCard) resRecFaceShapeCard.style.display = 'none';
                }

                if (resRecGrid) resRecGrid.style.display = 'grid';
                if (cameraActions) cameraActions.style.display = 'none';
                if (scanResultBox) scanResultBox.style.display = 'block';
            } catch (err) {
                console.error('Error memproses scan:', err);
                alert(err.message || 'An error occurred while processing scan.');
            } finally {
                stopAnalyzing();
                btnCapture.disabled = false;
                btnCapture.classList.remove('is-analyzing');
                if (shutterLabel) shutterLabel.textContent = 'Capture Photo & Analyze';
            }
        });
    }

    if (btnScanAgain) {
        btnScanAgain.addEventListener('click', () => {
            stopAnalyzing();
            if (scanResultBox) scanResultBox.style.display = 'none';
            if (resRecGrid) resRecGrid.style.display = 'none';
            if (resFaceShapeBadge) resFaceShapeBadge.style.display = 'none';
            if (cameraActions) cameraActions.style.display = 'flex';
            if (scannerFrame) scannerFrame.style.display = 'block';
            if (btnCapture) {
                btnCapture.disabled = false;
                btnCapture.classList.remove('is-analyzing');
            }
            if (shutterLabel) shutterLabel.textContent = 'Capture Photo & Analyze';
            if (cameraVideo) cameraVideo.play();
        });
    }

    if (shutterLabel && btnCapture) {
        shutterLabel.addEventListener('click', () => {
            if (!btnCapture.disabled) btnCapture.click();
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

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (cameraModal && cameraModal.classList.contains('open')) stopCamera();
            else if (authModal && authModal.classList.contains('open')) closeModal(authModal);
        }
    });

    if (modalLoginForm) {
        modalLoginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (modalLoginAlert) modalLoginAlert.style.display = 'none';
            if (modalLoginSubmitBtn) {
                modalLoginSubmitBtn.disabled = true;
                modalLoginSubmitBtn.textContent = 'Signing in...';
            }

            try {
                const response = await fetch('api/auth.php?action=login', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(modalLoginForm)
                });
                const res = await response.json();
                if (res.success) {
                    if (res.csrf_token && window.GlossaApp) {
                        window.GlossaApp.setMetaToken(res.csrf_token);
                    }
                    closeModal(authModal);
                    btnScan.dataset.auth = '1';
                    if (window.GlossaApp && typeof window.GlossaApp.initNavbar === 'function') {
                        await window.GlossaApp.initNavbar();
                    }
                } else {
                    if (modalLoginAlert) {
                        modalLoginAlert.textContent = res.error || 'Sign in failed.';
                        modalLoginAlert.style.display = 'block';
                    }
                }
            } catch (err) {
                if (modalLoginAlert) {
                    modalLoginAlert.textContent = 'Failed to connect to server.';
                    modalLoginAlert.style.display = 'block';
                }
            } finally {
                if (modalLoginSubmitBtn) {
                    modalLoginSubmitBtn.disabled = false;
                    modalLoginSubmitBtn.textContent = 'Sign In';
                }
            }
        });
    }

    if (modalRegisterForm) {
        modalRegisterForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (modalRegisterAlert) modalRegisterAlert.style.display = 'none';

            const p1 = document.getElementById('modalRegPassword')?.value || '';
            const p2 = document.getElementById('modalRegConfirm')?.value || '';
            if (p1 && p2 && p1 !== p2) {
                if (modalRegisterAlert) {
                    modalRegisterAlert.textContent = 'Password confirmation does not match.';
                    modalRegisterAlert.style.display = 'block';
                }
                return;
            }
            if (modalRegisterSubmitBtn) {
                modalRegisterSubmitBtn.disabled = true;
                modalRegisterSubmitBtn.textContent = 'Creating account...';
            }

            try {
                const response = await fetch('api/auth.php?action=register', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(modalRegisterForm)
                });
                const res = await response.json();
                if (res.success) {
                    if (res.csrf_token && window.GlossaApp) {
                        window.GlossaApp.setMetaToken(res.csrf_token);
                    }
                    closeModal(authModal);
                    btnScan.dataset.auth = '1';
                    if (window.GlossaApp && typeof window.GlossaApp.initNavbar === 'function') {
                        await window.GlossaApp.initNavbar();
                    }
                } else {
                    if (modalRegisterAlert) {
                        modalRegisterAlert.textContent = res.error || 'Registration failed.';
                        modalRegisterAlert.style.display = 'block';
                    }
                }
            } catch (err) {
                if (modalRegisterAlert) {
                    modalRegisterAlert.textContent = 'Failed to connect to server.';
                    modalRegisterAlert.style.display = 'block';
                }
            } finally {
                if (modalRegisterSubmitBtn) {
                    modalRegisterSubmitBtn.disabled = false;
                    modalRegisterSubmitBtn.textContent = 'Create Account';
                }
            }
        });
    }
});
