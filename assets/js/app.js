const GlossaApp = {
    csrfToken: '',

    async getCsrfToken() {
        if (this.csrfToken) return this.csrfToken;
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        if (metaTag && metaTag.content) {
            this.csrfToken = metaTag.content;
            return this.csrfToken;
        }
        try {
            const res = await fetch('api/auth.php?action=csrf');
            const data = await res.json();
            if (data.csrf_token) {
                this.csrfToken = data.csrf_token;
                return this.csrfToken;
            }
        } catch (e) {
            console.warn('Gagal mengambil CSRF token:', e);
        }
        return '';
    },

    async getCurrentUser() {
        try {
            const res = await fetch('api/auth.php?action=me');
            const data = await res.json();
            if (data.csrf_token) {
                this.csrfToken = data.csrf_token;
            }
            return data.authenticated ? data.user : null;
        } catch (e) {
            console.warn('Gagal mengecek sesi login:', e);
            return null;
        }
    },

    async logout() {
        try {
            await fetch('api/auth.php?action=logout', { method: 'POST' });
        } catch (e) {}
        window.location.href = 'login.html';
    },

    async initNavbar() {
        const navLinks = document.querySelector('.nav-links');
        const user = await this.getCurrentUser();
        const currentPath = window.location.pathname.split('/').pop() || 'index.html';

        if (navLinks) {
            if (user) {
                navLinks.innerHTML = `
                    <a href="index.html" class="nav-link ${currentPath === 'index.html' || currentPath === '' ? 'active' : ''}">Dashboard</a>
                    <a href="profile.html" class="nav-link ${currentPath === 'profile.html' ? 'active' : ''}">Profile</a>
                    <a href="#" id="navLogoutBtn" class="nav-link">Keluar</a>
                `;
                const logoutBtn = document.getElementById('navLogoutBtn');
                if (logoutBtn) {
                    logoutBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        if (confirm('Apakah Anda yakin ingin keluar?')) {
                            GlossaApp.logout();
                        }
                    });
                }
            } else {
                navLinks.innerHTML = `
                    <a href="index.html" class="nav-link ${currentPath === 'index.html' || currentPath === '' ? 'active' : ''}">Dashboard</a>
                    <a href="login.html" class="nav-link ${currentPath === 'login.html' ? 'active' : ''}">Masuk</a>
                `;
            }
        }

        const greetingName = document.querySelector('.greeting-name');
        const greetingSubtitle = document.querySelector('.greeting-subtitle');
        const btnScan = document.getElementById('btnScanCamera');

        if (greetingName && user) {
            greetingName.textContent = user.name;
        }
        if (greetingSubtitle && user) {
            greetingSubtitle.textContent = 'Welcome back,';
        }
        if (btnScan) {
            btnScan.dataset.auth = user ? '1' : '0';
        }

        return user;
    }
};

document.addEventListener('DOMContentLoaded', () => {
    GlossaApp.initNavbar();
});
