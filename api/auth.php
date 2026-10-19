<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../includes/auth.php';

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

switch ($action) {
    case 'csrf':
        echo json_encode([
            'success' => true,
            'csrf_token' => csrf_token()
        ]);
        break;

    case 'me':
        $user = current_user();
        echo json_encode([
            'success' => true,
            'authenticated' => ($user !== null),
            'user' => $user,
            'csrf_token' => csrf_token()
        ]);
        break;

    case 'login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Metode request tidak diizinkan.']);
            exit;
        }

        $email = $payload['email'] ?? '';
        $password = $payload['password'] ?? '';

        $error = login_user($pdo, $email, $password);
        if ($error) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => $error]);
        } else {
            echo json_encode([
                'success' => true,
                'message' => 'Login berhasil.',
                'user' => current_user(),
                'csrf_token' => csrf_token()
            ]);
        }
        break;

    case 'register':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Metode request tidak diizinkan.']);
            exit;
        }

        $name = $payload['name'] ?? '';
        $email = $payload['email'] ?? '';
        $password = $payload['password'] ?? '';
        $birthDate = $payload['birth_date'] ?? null;
        $gender = $payload['gender'] ?? 'not_specified';

        $error = register_user($pdo, $name, $email, $password, $birthDate, $gender);
        if ($error) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => $error]);
        } else {
            login_user($pdo, $email, $password);
            echo json_encode([
                'success' => true,
                'message' => 'Registrasi berhasil.',
                'user' => current_user(),
                'csrf_token' => csrf_token()
            ]);
        }
        break;

    case 'logout':
        logout_user();
        echo json_encode(['success' => true, 'message' => 'Berhasil keluar.']);
        break;

    case 'forgot_request':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Metode request tidak diizinkan.']);
            exit;
        }

        $email = $payload['email'] ?? '';
        $error = send_reset_code($pdo, $email);
        if ($error) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => $error]);
        } else {
            echo json_encode([
                'success' => true,
                'message' => 'Kode verifikasi reset password telah dikirim ke email Anda.'
            ]);
        }
        break;

    case 'forgot_verify':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Metode request tidak diizinkan.']);
            exit;
        }

        $email = $payload['email'] ?? ($_SESSION['reset_email'] ?? '');
        $code = $payload['code'] ?? '';
        $newPassword = $payload['password'] ?? ($payload['new_password'] ?? '');

        $error = verify_and_reset_password($pdo, $email, $code, $newPassword);
        if ($error) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => $error]);
        } else {
            echo json_encode([
                'success' => true,
                'message' => 'Password berhasil direset. Silakan masuk dengan password baru.'
            ]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Aksi tidak dikenal.']);
        break;
}
