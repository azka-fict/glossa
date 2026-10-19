# Panduan Resmi Tim Frontend Glossa

Dokumen ini adalah panduan integrasi resmi untuk tim Frontend agar dapat merancang, mengubah tampilan, atau memoles antarmuka visual secara leluasa tanpa risiko merusak logika backend.

---

## 1. Arsitektur Terpisah

Proyek ini telah dipisahkan secara penuh:
* **Frontend:** 100% menggunakan file `.html`, `.css`, dan `.js`. Tidak ada kode PHP di dalam file tampilan. Bisa langsung dibuka di browser atau menggunakan VS Code Live Server.
* **Backend:** Menggunakan file PHP sebagai REST API di folder `api/` (`api/auth.php`, `api/profile.php`, `api/scan.php`) yang menangani database MySQL, enkripsi password, otentikasi sesi, dan AI Roboflow. Semua data dikirim dan diterima dalam format JSON.

---

## 2. Struktur File Tampilan Frontend

Semua file visual berada langsung di root project:

| File Frontend | Fungsi Halaman |
|---|---|
| `index.html` | Dashboard utama, kartu pemicu scan kamera, modal kamera AI, laser HUD scanner, kartu hasil analisis, dan modal login instan jika tamu ingin scan. |
| `login.html` | Halaman Masuk akun (email & password). |
| `register.html` | Halaman Pendaftaran akun baru (nama lengkap, tanggal lahir, email, password). |
| `forgot_password.html` | Halaman Lupa Password (tahap 1: minta OTP email, tahap 2: verifikasi OTP & password baru). |
| `profile.html` | Halaman Profil Pengguna, status verifikasi email, daftar riwayat scan, serta modal ubah nama, ubah email, OTP, detail scan, dan hapus akun. |
| `onboarding.html` | Slider interaktif perkenalan fitur aplikasi untuk pengguna baru. |
| `assets/css/style.css` | File styling CSS utama. Bebas diubah dan ditambahkan. |
| `assets/js/app.js` | Helper JavaScript bersama untuk sinkronisasi navbar (menampilkan status login/logout & nama user). |
| `assets/js/main.js` | Logika kamera webcam, capture snapshot, HUD laser animasi, dan pengiriman gambar ke model AI. |

---

## 3. Daftar Endpoint REST API Backend

Frontend berkomunikasi dengan backend menggunakan `fetch()` dengan mengirim atau menerima data JSON.

### A. Otentikasi (`api/auth.php`)

| Endpoint & Action | Method | Parameter Request (JSON) | Respon Sukses |
|---|---|---|---|
| `api/auth.php?action=me` | `GET` | *(Tanpa parameter)* | `{ "success": true, "authenticated": true, "user": { "id", "name", "email" } }` |
| `api/auth.php?action=login` | `POST` | `{ "email": "...", "password": "..." }` | `{ "success": true, "user": {...} }` |
| `api/auth.php?action=register` | `POST` | `{ "name": "...", "birth_date": "YYYY-MM-DD", "email": "...", "password": "..." }` | `{ "success": true, "user": {...} }` |
| `api/auth.php?action=logout` | `POST` | *(Tanpa parameter)* | `{ "success": true }` |
| `api/auth.php?action=forgot_request` | `POST` | `{ "email": "..." }` | `{ "success": true, "message": "Kode telah dikirim..." }` |
| `api/auth.php?action=forgot_verify` | `POST` | `{ "email": "...", "code": "123456", "password": "..." }` | `{ "success": true, "message": "Password berhasil direset." }` |

### B. Profil & Riwayat (`api/profile.php`)

| Endpoint & Action | Method | Parameter Request (JSON) | Respon Sukses |
|---|---|---|---|
| `api/profile.php` | `GET` | *(Wajib login)* | `{ "success": true, "user": { "name", "email", "is_verified", "birth_date", "age", "created_at" }, "scan_history": [...] }` |
| `api/profile.php?action=update_name` | `POST` | `{ "name": "Nama Baru" }` | `{ "success": true, "message": "Nama berhasil diperbarui." }` |
| `api/profile.php?action=update_email` | `POST` | `{ "email": "baru@email.com" }` | `{ "success": true, "message": "Kode konfirmasi dikirim ke email baru." }` |
| `api/profile.php?action=send_verify` | `POST` | *(Kirim ulang OTP)* | `{ "success": true, "message": "Kode verifikasi berhasil dikirim." }` |
| `api/profile.php?action=verify_email` | `POST` | `{ "code": "123456" }` | `{ "success": true, "message": "Email berhasil diverifikasi!" }` |
| `api/profile.php?action=delete_scan` | `POST` | `{ "scan_id": 12 }` | `{ "success": true, "message": "Riwayat analisis berhasil dihapus." }` |
| `api/profile.php?action=delete_account` | `POST` | `{ "password": "..." }` | `{ "success": true, "message": "Akun berhasil dihapus selamanya." }` |

### C. Kamera & Analisis AI (`api/scan.php`)
* **Method:** `POST`
* **Content-Type:** `application/json`
* **Body:**
  ```json
  {
    "image": "data:image/jpeg;base64,...",
    "skin_tone": "Medium",
    "dominant_color": "#e2a76f"
  }
  ```
* **Respon:** Mengembalikan hasil deteksi Undertone, Bentuk Wajah (Face Shape), persentase akurasi, dan rekomendasi lengkap.

---

## 4. Kontrak ID Selector untuk Kamera & AI Scan

Frontend bebas mendesain ulang layout, class CSS, posisi, maupun warna elemen, asalkan atribut `id` HTML berikut tetap dipertahankan:

| ID Selector | Tipe Elemen | Fungsi |
|---|---|---|
| `btnScanCamera` | `<button>` | Membuka jendela kamera scan. |
| `cameraModal` | `<div>` | Container modal popup kamera. |
| `closeCameraModal` | `<button>` | Menutup modal dan mematikan video stream perangkat. |
| `cameraVideo` | `<video>` | Viewport pemutar video langsung dari webcam/HP. |
| `btnCapture` | `<button>` | Mengambil foto snapshot dan mengirim ke AI. |
| `cameraStatus` | `<div>` | Pesan status koneksi webcam. |
| `cameraFlash` | `<div>` | Efek animasi blitz saat snapshot. |
| `analyzingOverlay` | `<div>` | Overlay animasi HUD laser AI saat proses analisis berlangsung. |
| `analyzingText` | `<span>` | Teks status langkah analisis AI yang berjalan. |
| `scanResultBox` | `<div>` | Kartu yang menampung hasil scan AI. |
| `resUndertoneBadge` | `<span>` | Label Undertone (Warm / Cool / Neutral). |
| `resFaceShapeBadge` | `<span>` | Label Bentuk Wajah (Oval, Round, Oblong, dll). |
| `resAccuracyBadge` | `<span>` | Persentase akurasi model AI. |
| `resRecommendation` | `<p>` | Teks ringkasan tone kulit & rekomendasi. |
| `btnScanAgain` | `<button>` | Mengulang pemindaian kamera. |
