<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../includes/auth.php';

$currentUser = current_user();
if (!$currentUser) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'authenticated' => false,
        'error' => 'Sesi telah berakhir atau belum masuk. Silakan masuk terlebih dahulu.'
    ]);
    exit;
}

function get_api_payload(): array {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
    return $_POST;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$payload = get_api_payload();

if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($action)) {
    $stmt = $pdo->prepare('SELECT id, name, email, email_verified_at, birth_date, age, created_at FROM users WHERE id = ?');
    $stmt->execute([$currentUser['id']]);
    $user = $stmt->fetch();

    if (!$user) {
        logout_user();
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Akun tidak ditemukan.']);
        exit;
    }

    $histStmt = $pdo->prepare('SELECT id, undertone, face_shape, skin_tone, dominant_color, accuracy, recommendations, created_at FROM scan_history WHERE user_id = ? ORDER BY created_at DESC');
    $histStmt->execute([$currentUser['id']]);
    $scans = $histStmt->fetchAll();

    echo json_encode([
        'success' => true,
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'is_verified' => !empty($user['email_verified_at']),
            'email_verified_at' => $user['email_verified_at'],
            'birth_date' => $user['birth_date'],
            'age' => $user['age'],
            'created_at' => $user['created_at'],
        ],
        'scan_history' => $scans,
        'csrf_token' => csrf_token()
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metode request tidak diizinkan.']);
    exit;
}

switch ($action) {
    case 'update_name':
        $name = trim($payload['name'] ?? '');
        if (strlen($name) < 2 || strlen($name) > 100) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Nama harus antara 2 hingga 100 karakter.']);
            exit;
        }

        $stmt = $pdo->prepare('UPDATE users SET name = ? WHERE id = ?');
        $stmt->execute([$name, $currentUser['id']]);
        $_SESSION['user_name'] = $name;

        echo json_encode([
            'success' => true,
            'message' => 'Nama berhasil diperbarui.',
            'name' => $name
        ]);
        break;

    case 'update_email':
        $email = trim($payload['email'] ?? '');
        $error = update_user_email($pdo, $currentUser['id'], $email);
        if ($error) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => $error]);
        } else {
            send_email_verification_code($pdo, $currentUser['id'], $email);
            echo json_encode([
                'success' => true,
                'message' => 'Email berhasil diubah. Kode konfirmasi telah dikirimkan ke email baru Anda.'
            ]);
        }
        break;

    case 'send_verify':
        $stmt = $pdo->prepare('SELECT email, email_verified_at FROM users WHERE id = ?');
        $stmt->execute([$currentUser['id']]);
        $user = $stmt->fetch();

        if (!$user) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Pengguna tidak ditemukan.']);
            exit;
        }
        if (!empty($user['email_verified_at'])) {
            echo json_encode(['success' => true, 'message' => 'Email sudah terverifikasi.']);
            exit;
        }

        $error = send_email_verification_code($pdo, $currentUser['id'], $user['email']);
        if ($error) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => $error]);
        } else {
            echo json_encode([
                'success' => true,
                'message' => 'Kode verifikasi berhasil dikirimkan ke ' . $user['email']
            ]);
        }
        break;

    case 'verify_email':
        $code = trim($payload['code'] ?? '');
        $error = verify_email_code($pdo, $currentUser['id'], $code);
        if ($error) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => $error]);
        } else {
            echo json_encode([
                'success' => true,
                'message' => 'Email berhasil diverifikasi!'
            ]);
        }
        break;

    case 'delete_scan':
        $scanId = (int)($payload['scan_id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM scan_history WHERE id = ? AND user_id = ?');
        $stmt->execute([$scanId, $currentUser['id']]);
        echo json_encode([
            'success' => true,
            'message' => 'Riwayat analisis berhasil dihapus.'
        ]);
        break;

    case 'delete_account':
        $password = $payload['password'] ?? '';
        $error = delete_user_account($pdo, $currentUser['id'], $password);
        if ($error) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => $error]);
        } else {
            echo json_encode([
                'success' => true,
                'message' => 'Akun berhasil dihapus selamanya.'
            ]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Aksi profil tidak dikenal.']);
        break;
}
