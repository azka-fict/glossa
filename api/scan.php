<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../includes/auth.php';

$user = current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Silakan login terlebih dahulu.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metode request tidak diizinkan.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?? $_POST;

$postToken = $data['csrf_token'] ?? '';
if (!validate_csrf() && (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $postToken))) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sesi tidak valid atau telah kedaluwarsa (CSRF). Silakan muat ulang halaman.']);
    exit;
}

$rateErr = check_rate_limit($pdo, 'scan', $user['id'], 15, 60);
if ($rateErr) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => $rateErr]);
    exit;
}
record_rate_limit_attempt($pdo, 'scan', $user['id'], 60);

$image = $data['image'] ?? null;
if (!empty($image) && strlen($image) > 5 * 1024 * 1024) {
    http_response_code(413);
    echo json_encode(['success' => false, 'error' => 'Ukuran gambar terlalu besar (maksimal 5MB).']);
    exit;
}

$skinTone = trim($data['skin_tone'] ?? 'Medium');
$dominantColor = trim($data['dominant_color'] ?? '#e2a76f');
$undertone = trim($data['undertone'] ?? '');
$faceShape = trim($data['face_shape'] ?? '');
$accuracy = isset($data['accuracy']) ? (int)$data['accuracy'] : 95;

$rfConfig = file_exists(__DIR__ . '/../config/roboflow.php') ? require __DIR__ . '/../config/roboflow.php' : [];
$apiKey = $rfConfig['api_key'] ?? '';
$undertoneModel = $rfConfig['undertone_model_id'] ?? ($rfConfig['model_id'] ?? '');
$undertoneVersion = $rfConfig['undertone_version'] ?? ($rfConfig['version'] ?? '1');
$faceShapeModel = $rfConfig['face_shape_model_id'] ?? '';
$faceShapeVersion = $rfConfig['face_shape_version'] ?? '1';

if (empty($apiKey) || (empty($undertoneModel) && empty($faceShapeModel))) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'error' => 'Koneksi AI Roboflow belum diaktifkan. Silakan masukkan ROBOFLOW_API_KEY dan MODEL_ID pada file .env terlebih dahulu.'
    ]);
    exit;
}

if (empty($image)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Data gambar kamera kosong atau tidak valid.']);
    exit;
}

$base64 = $image;
if (strpos($base64, ',') !== false) {
    $base64 = substr($base64, strpos($base64, ',') + 1);
}

$mh = curl_multi_init();
$handles = [];

if (!empty($undertoneModel)) {
    $uUrl = "https://detect.roboflow.com/" . rawurlencode($undertoneModel) . "/" . rawurlencode($undertoneVersion) . "?api_key=" . rawurlencode($apiKey);
    $chU = curl_init($uUrl);
    curl_setopt_array($chU, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $base64,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    curl_multi_add_handle($mh, $chU);
    $handles['undertone'] = $chU;
}

if (!empty($faceShapeModel)) {
    $fUrl = "https://detect.roboflow.com/" . rawurlencode($faceShapeModel) . "/" . rawurlencode($faceShapeVersion) . "?api_key=" . rawurlencode($apiKey);
    $chF = curl_init($fUrl);
    curl_setopt_array($chF, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $base64,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    curl_multi_add_handle($mh, $chF);
    $handles['face_shape'] = $chF;
}

if (!empty($handles)) {
    $running = null;
    do {
        $status = curl_multi_exec($mh, $running);
        if ($running) {
            curl_multi_select($mh, 0.5);
        }
    } while ($running > 0 && $status === CURLM_OK);

    if (isset($handles['undertone'])) {
        $ch = $handles['undertone'];
        $res = curl_multi_getcontent($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);

        if ($httpCode === 200 && $res) {
            $rf = json_decode($res, true);
            if (!empty($rf['top'])) {
                $undertone = ucfirst(strtolower($rf['top']));
                if (isset($rf['confidence'])) $accuracy = (int)round($rf['confidence'] * 100);
            } elseif (!empty($rf['predictions'][0]['class'])) {
                $undertone = ucfirst(strtolower($rf['predictions'][0]['class']));
                if (isset($rf['predictions'][0]['confidence'])) $accuracy = (int)round($rf['predictions'][0]['confidence'] * 100);
            }
        }
    }

    if (isset($handles['face_shape'])) {
        $ch = $handles['face_shape'];
        $res = curl_multi_getcontent($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);

        if ($httpCode === 200 && $res) {
            $rf = json_decode($res, true);
            if (!empty($rf['top'])) {
                $faceShape = ucfirst(strtolower($rf['top']));
            } elseif (!empty($rf['predictions'][0]['class'])) {
                $faceShape = ucfirst(strtolower($rf['predictions'][0]['class']));
            }
        }
    }

    curl_multi_close($mh);
}

if (!empty($undertoneModel) && empty($undertone)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error' => 'AI Roboflow tidak dapat mendeteksi undertone kulit. Pastikan wajah terlihat jelas dan pencahayaan terang.'
    ]);
    exit;
}

if (!empty($faceShapeModel) && empty($faceShape)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error' => 'AI Roboflow tidak dapat mendeteksi bentuk wajah. Pastikan seluruh bagian wajah dan rahang berada di dalam bingkai.'
    ]);
    exit;
}

if (empty($undertone)) {
    $undertone = 'Neutral';
}

$toneRecommendations = [
    'Warm' => 'Warna Terbaik: Terracotta, Peach, Mustard, Olive, Warm Gold. Lipstik: Coral, Brick Red, Warm Nude.',
    'Cool' => 'Warna Terbaik: Berry, Emerald, Royal Blue, Silver, Lavender. Lipstik: Rose Pink, Plum, Berry Red.',
    'Neutral' => 'Warna Terbaik: Dusty Pink, Jade Green, Soft Navy, Rose Gold. Lipstik: Soft Mauve, Peachy Nude.',
];

$shapeRecommendations = [
    'Oval' => 'Bentuk Wajah Oval: Kacamata geometris / cat-eye cocok sempurna. Gaya rambut layer panjang atau bob tekstur.',
    'Round' => 'Bentuk Wajah Bulat: Kacamata persegi (rectangular) untuk menegaskan garis wajah. Gaya rambut layer di bawah dagu.',
    'Square' => 'Bentuk Wajah Kotak: Kacamata bulat / oval untuk melembutkan rahang. Gaya rambut soft waves atau poni samping.',
    'Heart' => 'Bentuk Wajah Hati: Kacamata aviator / oval bawah lebar. Gaya rambut bob sebahu atau curtain bangs.',
    'Diamond' => 'Bentuk Wajah Diamond: Kacamata cat-eye atau oval lembut. Gaya rambut bervolume di area dagu/pipi.',
    'Oblong' => 'Bentuk Wajah Oblong: Kacamata oversized lebar. Gaya rambut berponi atau bervolume samping.',
];

$recs = [];
if (!empty($undertone) && isset($toneRecommendations[$undertone])) {
    $recs[] = $toneRecommendations[$undertone];
}
if (!empty($faceShape) && isset($shapeRecommendations[$faceShape])) {
    $recs[] = $shapeRecommendations[$faceShape];
}
$recommendations = implode(' ', $recs);

$stmt = $pdo->prepare('INSERT INTO scan_history (user_id, undertone, face_shape, skin_tone, accuracy, recommendations, dominant_color) VALUES (?, ?, ?, ?, ?, ?, ?)');
$stmt->execute([
    $user['id'],
    $undertone,
    $faceShape,
    $skinTone,
    $accuracy,
    $recommendations,
    $dominantColor
]);

echo json_encode([
    'success' => true,
    'scan' => [
        'id' => $pdo->lastInsertId(),
        'undertone' => $undertone,
        'face_shape' => $faceShape,
        'skin_tone' => $skinTone,
        'accuracy' => $accuracy,
        'recommendations' => $recommendations,
        'dominant_color' => $dominantColor,
        'created_at' => date('d M Y, H:i')
    ]
]);
