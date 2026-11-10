# Alur Lengkap Sistem & Arsitektur Glossa (Project Flow Guide)

Dokumen ini disusun sebagai panduan arsitektur komprehensif bagi **AI Agent** dan pengembang untuk memahami cara kerja, struktur berkas, alur data (data flow), integrasi kecerdasan buatan (AI), serta kontrak antarmuka di proyek **Glossa**.

---

## 1. Ringkasan Proyek (Overview)

**Glossa** adalah aplikasi web asisten kecantikan berbasis AI yang menganalisis fitur wajah pengguna secara *real-time* melalui kamera:
1. **Deteksi Skin Undertone** (*Warm, Cool, Neutral*).
2. **Klasifikasi Bentuk Wajah / Face Shape** (*Oval, Round, Square, Heart, Diamond, Oblong*).
3. **Sistem Rekomendasi Personal**: Memberikan rekomendasi palet warna pakaian, makeup/lipstik, bentuk kacamata, dan gaya rambut berdasarkan hasil deteksi.
4. **Riwayat & Profil Pengguna**: Menyimpan riwayat scan pengguna yang terdaftar dan manajemen akun lengkap dengan verifikasi email OTP.

---

## 2. Arsitektur Sistem (System Architecture)

Aplikasi ini menggunakan pola arsitektur **Decoupled Client-Server**:

```
+-------------------------------------------------------------+
|                     FRONTEND (Client)                       |
|   Vanilla HTML5 + Vanilla CSS3 + Modern JavaScript (ES6)     |
|   - Browser Camera API (MediaDevices.getUserMedia)          |
|   - GlossaApp Helper (assets/js/app.js)                     |
|   - Scanner Controller (assets/js/main.js)                  |
+------------------------------+------------------------------+
                               |
                    Fetch API (JSON over HTTP)
                    Session Cookie + CSRF Header
                               v
+-------------------------------------------------------------+
|            WEB SERVER & RUNTIME (Debian WSL)                |
|   Nginx Engine (Port 8000) -> PHP 8.4-FPM FastCGI Socket   |
|   - Native security rules: Blokir .env, .sql, config, dll   |
|   - api/auth.php     : Sesi, Login, Register, Reset Pass    |
|   - api/profile.php  : Data Profil, Edit, Verifikasi, History|
|   - api/scan.php     : Pemrosesan Gambar & Proxy AI Model   |
+--------------+-------------------------------+--------------+
               |                               |
       PDO Prepared Stmt                cURL Multi (Parallel)
               v                               v
+------------------------------+  +---------------------------+
|  DATABASE (MariaDB WSL)      |  |    EXTERNAL AI SERVICE    |
|   - Host: 127.0.0.1:3306     |  |   Roboflow Computer Vision|
|   - User: glossa_user        |  |   1. Undertone Model      |
|   - users, scan_history      |  |   2. Face Shape Model     |
|   - email_verifications      |  +---------------------------+
|   - password_resets          |
|   - rate_limits              |
+------------------------------+
```

### Karakteristik Utama Arsitektur:
- **Zero Frontend Build Tooling**: Tidak menggunakan webpack, vite, atau node build step. File HTML langsung disajikan oleh web server Nginx/Apache.
- **Production-Grade Linux Stack**: Berjalan di atas **Debian Linux via WSL2** dengan Nginx dan PHP 8.4-FPM yang diotomasi oleh [start-server.bat](file:///c:/laragon/www/glossav1/start-server.bat).
- **Public Tunneling**: Dihubungkan ke internet secara aman melalui Cloudflare Tunnel (`glossa.aoka.my.id`).
- **Stateless/Session-Based API**: Backend mengekspos endpoint JSON di folder `api/`. Sesi login dijaga dengan PHP native session cookie (`HttpOnly`, `SameSite=Lax`).
- **Security First**: Dilengkapi proteksi Nginx & Apache native terhadap file rahasia (`.env`, `.sql`, `config/`), database MariaDB terisolasi dengan user ber-password, validasi CSRF token (`X-CSRF-TOKEN`), rate limiting anti brute-force, dan PDO Prepared Statements.

---

## 3. Peta Berkas & Direktori (File Map)

```
glossav1/
│
├── index.html              # Dashboard utama, pemicu kamera, modal kamera AI, kartu hasil scan
├── login.html              # Halaman autentikasi masuk (email + password)
├── register.html           # Halaman pendaftaran akun baru
├── forgot_password.html    # Halaman lupa password (alur 2 tahap: request OTP & verify)
├── profile.html            # Halaman profil akun, status verifikasi, riwayat scan, modal edit
├── onboarding.html         # Carousel interaktif pengenalan aplikasi bagi pengguna baru
│
├── api/                    # REST API Endpoints (Menerima & mengembalikan JSON)
│   ├── auth.php            # Endpoint autentikasi (login, register, logout, csrf, me, reset)
│   ├── profile.php         # Endpoint manajemen data user & riwayat scan
│   └── scan.php            # Endpoint penerima gambar webcam, proxy Roboflow, simpan scan
│
├── config/                 # Konfigurasi Sistem
│   ├── database.php        # Loader file .env, koneksi PDO MySQL, auto-healing schema
│   └── roboflow.php        # Konfigurasi API key dan Model ID Roboflow AI
│
├── includes/               # Logika Bisnis & Utilitas Inti
│   └── auth.php            # Core functions: session, csrf, rate_limit, smtp socket mail, hashing
│
├── assets/                 # Aset Statis
│   ├── css/style.css       # Style antarmuka visual utama
│   ├── js/app.js           # Helper global GlossaApp (navbar sync, user session, csrf helper)
│   ├── js/main.js          # Controller kamera browser, laser HUD scanner, snapshot, fetch scan
│   └── images/             # Icon, banner, ilustrasi
│
├── schema.sql              # Definisi DDL tabel MySQL / MariaDB
├── start-server.bat        # Skrip otomatisasi runtime: start service Debian WSL (Nginx, MariaDB, PHP-FPM) & Cloudflare Tunnel
├── .env.example            # Template variabel lingkungan (DB, Roboflow, SMTP)
├── .env                    # Variabel lingkungan lokal (Sensitif, diabaikan oleh git)
├── .htaccess               # Routing rewrite legacy, proteksi file sensitif untuk Apache
└── AGENTS.md               # Pedoman agen pengembang (Ponytail lazy senior dev rules)
```
```

---

## 4. Alur Kerja Sistem (System Flows)

### Flow 1: Autentikasi & Manajemen Sesi (Auth Flow)
1. **Pemeriksaan Sesi (`api/auth.php?action=me`)**:
   - Saat halaman dibuka, `assets/js/app.js` memanggil `action=me`.
   - Mengembalikan status login (`authenticated: true/false`), data user, dan token CSRF baru.
   - Navbar secara otomatis menampilkan link "Profile" & "Keluar" jika login, atau "Masuk" jika tamu.
2. **Registrasi (`api/auth.php?action=register`)**:
   - Menerima `name`, `email`, `password`, `birth_date`, `gender`.
   - Backend memvalidasi panjang nama, format email, panjang minimal password (>= 6).
   - Menghitung umur otomatis dari `birth_date`.
   - Password dienkripsi dengan `password_hash($password, PASSWORD_DEFAULT)`.
   - Setelah sukses insert ke tabel `users`, sistem langsung mengaktifkan sesi login (auto-login).
3. **Login (`api/auth.php?action=login`)**:
   - Memeriksa batas percobaan login di tabel `rate_limits` (maksimal 5x dalam 5 menit per kombinasi email + IP).
   - Memverifikasi hash password dengan `password_verify()`.
   - Jika berhasil, meregenerasi session ID (`session_regenerate_id(true)`) dan menyimpan user di `$_SESSION`.
4. **Logout (`api/auth.php?action=logout`)**:
   - Mengosongkan `$_SESSION`, menghapus cookie sesi, dan memanggil `session_destroy()`.

---

### Flow 2: Reset Password & Verifikasi Email (OTP via SMTP)
1. **Lupa Password**:
   - User memasukkan email di `forgot_password.html`.
   - Frontend memanggil `POST api/auth.php?action=forgot_request`.
   - Backend menghasilkan kode OTP acak 6 digit, disimpan di `password_resets` (berlaku 15 menit).
   - Backend mengirim email berisi kode OTP via fungsi `send_smtp_email()` (koneksi direct socket TLS/SSL port 587/465).
   - User memasukkan OTP & password baru -> `POST api/auth.php?action=forgot_verify`.
   - Backend memverifikasi kecocokan OTP & masa berlaku, memperbarui hash password, lalu menghapus entri OTP.
2. **Verifikasi & Ganti Email**:
   - Di `profile.html`, pengguna dapat meminta kode verifikasi akun (`action=send_verify`) atau mengubah alamat email (`action=update_email`).
   - Kode 6 digit disimpan di tabel `email_verifications` dan dikirimkan via SMTP.
   - Verifikasi melalui `action=verify_email` akan mencatat stempel waktu pada kolom `users.email_verified_at`.

---

### Flow 3: Pemindaian Kamera & Analisis AI (Core Scan Flow)

Alur ini adalah inti fungsi aplikasi Glossa:

```
[User Klik "Mulai Scan Wajah"]
         |
         v
[Cek Status Login via GlossaApp]
  ├── Belum Login: Tampilkan Modal Login/Register Cepat
  └── Sudah Login:
         |
         v
[Buka Modal Kamera & Minta Izin getUserMedia()]
         |
         v
[Kamera Streaming Aktif di <video id="cameraVideo">]
         |
         v
[User Tekan "Ambil Foto" (btnCapture)]
         |
         +---> Putar Suara Shutter (Web Audio API)
         +---> Efek Blitz Putih (cameraFlash)
         +---> Freeze Frame & Ekstrak Base64 JPEG dari Video via Canvas
         +---> Tampilkan Animasi Laser Scanning HUD (analyzingOverlay)
         |
         v
[Kirim HTTP POST ke api/scan.php]
Payload: {
  image: "data:image/jpeg;base64,...",
  csrf_token: "...",
  skin_tone: "Medium",
  dominant_color: "#e2a76f"
}
         |
         v
[api/scan.php Processing]
  1. Validasi Autentikasi Sesi (Wajib Login).
  2. Validasi CSRF Token.
  3. Cek Rate Limit (Maksimal 15 scan / 60 detik per user).
  4. Validasi Ukuran Gambar (Maks 5 MB).
  5. Eksekusi Paralel cURL Multi ke 2 Endpoint Roboflow:
       - Undertone Model: https://detect.roboflow.com/{undertone_model}/{version}
       - Face Shape Model: https://detect.roboflow.com/{face_shape_model}/{version}
  6. Parsing Output Prediksi (Class & Confidence).
  7. Engine Rekomendasi Berbasis Aturan:
       - Undertone (Warm / Cool / Neutral) -> Rekomendasi pakaian & lipstik.
       - Face Shape (Oval / Round / Square / Heart / Diamond / Oblong) -> Kacamata & rambut.
  8. Simpan Hasil ke MySQL (Tabel scan_history).
  9. Response JSON ke Frontend.
         |
         v
[Frontend Menampilkan Hasil di #scanResultBox]
  - Badge Undertone (#resUndertoneBadge)
  - Badge Bentuk Wajah (#resFaceShapeBadge)
  - Badge Akurasi (#resAccuracyBadge)
  - Paragraf Rekomendasi (#resRecommendation)
  - Tombol Scan Ulang (#btnScanAgain)
```

---

### Flow 4: Pengelolaan Profil & Riwayat (Profile Flow)
1. **Load Profil (`GET api/profile.php`)**:
   - Mengambil data identitas pengguna (`name`, `email`, `is_verified`, `birth_date`, `age`).
   - Mengambil seluruh daftar riwayat scan dari tabel `scan_history` urut dari yang paling baru (`ORDER BY created_at DESC`).
2. **Update Nama (`action=update_name`)**: Mengubah nama di database dan session.
3. **Hapus Riwayat Scan (`action=delete_scan`)**: Menghapus satu item scan berdasarkan `scan_id` milik user yang bersangkutan.
4. **Hapus Akun Permanen (`action=delete_account`)**:
   - Meminta konfirmasi password akun.
   - Jika password benar, menghapus data di `password_resets`, `email_verifications`, `scan_history`, dan `users`.
   - Menghancurkan sesi pengguna dan redirect ke halaman login.

---

## 5. Struktur Database (Database Schema)

Database menggunakan **MariaDB 11+** di lingkungan **Debian WSL** (database name: `glossa`), dengan port 3306 yang diteruskan ke Windows sehingga dapat dikelola melalui HeidiSQL Laragon atau DBeaver. User terisolasi: `glossa_user`. Seluruh skrip ada pada [schema.sql](file:///c:/laragon/www/glossav1/schema.sql):

| Nama Tabel | Deskripsi | Kunci Utama & Relasi |
|---|---|---|
| `users` | Menyimpan kredensial akun pengguna, profil usia, tanggal lahir, dan status verifikasi email. | `id` (UUID VARCHAR(36)). Unique: `email`. |
| `scan_history` | Riwayat deteksi AI per scan: undertone, bentuk wajah, skintone, warna dominan, akurasi, dan teks rekomendasi. | `id` (AUTO_INCREMENT). Foreign Key: `user_id` -> `users(id)` ON DELETE CASCADE. |
| `email_verifications` | Kode OTP 6 digit untuk verifikasi alamat email pengguna terdaftar. | `id` (AUTO_INCREMENT). Foreign Key: `user_id` -> `users(id)` ON DELETE CASCADE. |
| `password_resets` | Kode OTP 6 digit untuk alur pemulihan kata sandi (lupa password). | `id` (AUTO_INCREMENT). Index: `(email, code)`. |
| `rate_limits` | Pencatatan brute-force & pembatas kuota request (action:identifier, attempts, last_attempt). | `rate_key` (VARCHAR(100)). |

---

## 6. Referensi Endpoint API Lengkap

Semua request POST menerima payload dalam format JSON (`Content-Type: application/json`) atau data form biasa, dan selalu mengembalikan JSON.

| Endpoint | Method | Parameter Utama | Respon Sukses Utama |
|---|---|---|---|
| `api/auth.php?action=csrf` | GET | - | `{"success": true, "csrf_token": "..."}` |
| `api/auth.php?action=me` | GET | - | `{"success": true, "authenticated": true, "user": {...}, "csrf_token": "..."}` |
| `api/auth.php?action=login` | POST | `email`, `password` | `{"success": true, "message": "...", "user": {...}}` |
| `api/auth.php?action=register` | POST | `name`, `email`, `password`, `birth_date`, `gender` | `{"success": true, "message": "...", "user": {...}}` |
| `api/auth.php?action=logout` | POST | - | `{"success": true, "message": "Berhasil keluar."}` |
| `api/auth.php?action=forgot_request` | POST | `email` | `{"success": true, "message": "Kode verifikasi..."}` |
| `api/auth.php?action=forgot_verify` | POST | `email`, `code`, `password` | `{"success": true, "message": "Password berhasil direset."}` |
| `api/profile.php` | GET | *(Sesi Login Aktif)* | `{"success": true, "user": {...}, "scan_history": [...]}` |
| `api/profile.php?action=update_name` | POST | `name` | `{"success": true, "message": "...", "name": "..."}` |
| `api/profile.php?action=update_email` | POST | `email` | `{"success": true, "message": "..."}` |
| `api/profile.php?action=send_verify` | POST | - | `{"success": true, "message": "..."}` |
| `api/profile.php?action=verify_email` | POST | `code` | `{"success": true, "message": "..."}` |
| `api/profile.php?action=delete_scan` | POST | `scan_id` | `{"success": true, "message": "..."}` |
| `api/profile.php?action=delete_account` | POST | `password` | `{"success": true, "message": "..."}` |
| `api/scan.php` | POST | `image` (base64), `skin_tone`, `dominant_color`, `csrf_token` | `{"success": true, "scan": {"id", "undertone", "face_shape", "accuracy", "recommendations", ...}}` |

---

## 7. Kontrak DOM Frontend (Penting bagi Pengembang UI & AI)

File [assets/js/main.js](file:///c:/laragon/www/glossav1/assets/js/main.js) mengikat logika interaktif ke elemen DOM berdasarkan atribut `id`. Saat merombak tampilan HTML, **ID berikut tidak boleh diubah**:

1. **Pemicu & Modal Kamera**:
   - `#btnScanCamera`: Tombol pemicu proses scan.
   - `#cameraModal`: Kontainer modal popup kamera.
   - `#closeCameraModal`: Tombol penutup kamera & penghenti stream webcam.
   - `#cameraVideo`: Elemen `<video>` viewport streaming webcam.
   - `#btnCapture`: Tombol penjepret gambar (snapshot).
   - `#cameraStatus`: Kontainer pesan status loading / error kamera.
   - `#cameraFlash`: Elemen efek kilatan cahaya animasi.
   - `#analyzingOverlay`: Overlay efek animasi laser HUD saat AI memproses gambar.
2. **Tampilan Hasil Analisis**:
   - `#scanResultBox`: Kontainer kartu hasil scan.
   - `#resUndertoneBadge`: Badge teks undertone (*Warm/Cool/Neutral*).
   - `#resFaceShapeBadge`: Badge teks bentuk wajah (*Oval/Round/dll*).
   - `#resAccuracyBadge`: Badge teks akurasi persentase (*contoh: 96%*).
   - `#resRecommendation`: Paragraf deskripsi panduan rekomendasi.
   - `#btnScanAgain`: Tombol untuk mengulang pemindaian.
3. **Modal Autentikasi Cepat (Quick Auth Modal)**:
   - `#authModal`, `#tabLoginBtn`, `#tabRegisterBtn`, `#modalLoginForm`, `#modalRegisterForm`.

---

## 8. Variabel Lingkungan & Konfigurasi (.env)

Pengaturan disimpan pada file `.env` di root direktori (dibaca via parser manual di [config/database.php](file:///c:/laragon/www/glossav1/config/database.php)):

```ini
# Database (MariaDB Debian WSL)
DB_HOST=127.0.0.1
DB_NAME=glossa
DB_USER=glossa_user
DB_PASS=GlossaSecure2026!

# Roboflow AI (2 Model Terpisah)
ROBOFLOW_API_KEY=your_roboflow_key
ROBOFLOW_UNDERTONE_MODEL_ID=skin-undertone-model
ROBOFLOW_UNDERTONE_VERSION=1
ROBOFLOW_FACE_SHAPE_MODEL_ID=face-shape-detector
ROBOFLOW_FACE_SHAPE_VERSION=1

# SMTP Email (Gmail / Brevo / cPanel)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=youremail@gmail.com
SMTP_PASS=app_password_16_digit
SMTP_FROM=youremail@gmail.com
SMTP_FROM_NAME=glossa
```

---

## 9. Infrastruktur, Keamanan & Runtime (Debian WSL)

Aplikasi Glossa telah dimigrasikan ke stack server berbasis **Debian WSL2** untuk standar keamanan dan performa produksi:

1. **Web Server Nginx**:
   - Berjalan pada port `8000` di Debian WSL.
   - Mengalihkan eksekusi file `.php` ke soket FastCGI PHP 8.4-FPM (`/run/php/php-fpm.sock`).
   - Memblokir langsung secara native (`404 Not Found`) untuk file sensitif: `.env`, `.sql`, `.bat`, `.log`, `.md`, serta folder internal `/config/` dan `/includes/`.
2. **Database MariaDB**:
   - Berjalan terisolasi di dalam kernel Debian WSL, terikat pada `127.0.0.1:3306`.
   - Menggunakan autentikasi password khusus `glossa_user` (bukan user `root` tanpa password).
   - Dapat diakses dari aplikasi visual Windows (HeidiSQL Laragon) pada `127.0.0.1:3306`.
3. **Keep-Alive & Startup Otomatis**:
   - Skrip [start-server.bat](file:///c:/laragon/www/glossav1/start-server.bat) menyalakan service systemd, menjalankan proses `sleep infinity` sebagai penjaga agar WSL2 tidak melakukan auto-sleep/hibernate, lalu menghubungkan **Cloudflare Tunnel** (`glossa.aoka.my.id`).

---

## 10. Aturan Modifikasi bagi AI Agent (Developer Guidelines)

Jika Anda adalah AI Agent yang bertugas memodifikasi proyek ini, ikuti pedoman berikut:

1. **Jaga Pemisahan Frontend & Backend**:
   - Jangan menambahkan tag `<?php ... ?>` ke file visual HTML (`index.html`, `profile.html`, dll).
   - Semua komunikasi wajib lewat REST API JSON di `api/`.
2. **Gunakan Pattern yang Ada**:
   - Gunakan fungsi pembantu di [includes/auth.php](file:///c:/laragon/www/glossav1/includes/auth.php) untuk urusan sesi, validasi CSRF, dan rate limiting.
   - Gunakan helper [assets/js/app.js](file:///c:/laragon/www/glossav1/assets/js/app.js) (`GlossaApp`) untuk mengambil CSRF token dan status sesi pengguna di frontend.
3. **Pertahankan Keamanan**:
   - Selalu sertakan validasi CSRF pada request mutasi (POST).
   - Jangan pernah menyimpan password mentah (gunakan `password_hash`).
   - Lindungi endpoint yang memerlukan hak akses dengan `current_user()`.
4. **Respon Ringkas & Minim Dependensi**:
   - Jangan memasang dependency library pihak ketiga / composer / npm jika tugas dapat diselesaikan dengan kode native yang sudah ada.
