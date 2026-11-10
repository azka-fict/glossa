const GlossaApp = {
    csrfToken: '',

    setMetaToken(token) {
        if (!token) return;
        this.csrfToken = token;
        let meta = document.querySelector('meta[name="csrf-token"]');
        if (!meta) {
            meta = document.createElement('meta');
            meta.name = 'csrf-token';
            document.head.appendChild(meta);
        }
        meta.content = token;
    },

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
                this.setMetaToken(data.csrf_token);
                return this.csrfToken;
            }
        } catch (e) {
            console.warn('Failed to fetch CSRF token:', e);
        }
        return '';
    },

    async getCurrentUser() {
        try {
            const res = await fetch('api/auth.php?action=me');
            const data = await res.json();
            if (data.csrf_token) {
                this.setMetaToken(data.csrf_token);
            }
            return data.authenticated ? data.user : null;
        } catch (e) {
            console.warn('Failed to check login session:', e);
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
                const userName = user.name || 'Profile';
                navLinks.innerHTML = `
                    <a href="index.html" class="nav-link ${currentPath === 'index.html' || currentPath === '' ? 'active' : ''}">Dashboard</a>
                    <a href="profile.html" class="icon-btn ${currentPath === 'profile.html' ? 'active' : ''}" aria-label="Profile" title="${userName}">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                    </a>
                    <a href="#" id="navLogoutBtn" class="icon-btn" aria-label="Sign Out" title="Sign Out">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    </a>
                `;
                const logoutBtn = document.getElementById('navLogoutBtn');
                if (logoutBtn) {
                    logoutBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        if (confirm('Are you sure you want to sign out?')) {
                            GlossaApp.logout();
                        }
                    });
                }
            } else {
                navLinks.innerHTML = `
                    <a href="index.html" class="nav-link ${currentPath === 'index.html' || currentPath === '' ? 'active' : ''}">Dashboard</a>
                    <a href="login.html" id="navLoginBtn" class="nav-link ${currentPath === 'login.html' ? 'active' : ''}">Sign In</a>
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

window.GlossaApp = GlossaApp;

document.addEventListener('DOMContentLoaded', () => {
    GlossaApp.initNavbar();
});
