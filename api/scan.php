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

$postToken = $data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!validate_csrf($postToken)) {
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

    $rfUndertoneError = '';
    $rfFaceShapeError = '';

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
        } elseif ($res) {
            $errObj = json_decode($res, true);
            $rfUndertoneError = $errObj['message'] ?? ($errObj['error']['message'] ?? ($errObj['error'] ?? ''));
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
        } elseif ($res) {
            $errObj = json_decode($res, true);
            $rfFaceShapeError = $errObj['message'] ?? ($errObj['error']['message'] ?? ($errObj['error'] ?? ''));
        }
    }

    curl_multi_close($mh);
}

if (!empty($undertoneModel) && empty($undertone)) {
    http_response_code(422);
    $errMsg = 'AI Roboflow tidak dapat mendeteksi undertone kulit. Pastikan wajah terlihat jelas dan pencahayaan terang.';
    if (!empty($rfUndertoneError)) {
        $errMsg = 'Roboflow Undertone (' . $rfUndertoneError . '). Silakan train model di dashboard Roboflow terlebih dahulu.';
    }
    echo json_encode([
        'success' => false,
        'error' => $errMsg
    ]);
    exit;
}

if (!empty($faceShapeModel) && empty($faceShape)) {
    http_response_code(422);
    $errMsg = 'AI Roboflow tidak dapat mendeteksi bentuk wajah. Pastikan seluruh bagian wajah dan rahang berada di dalam bingkai.';
    if (!empty($rfFaceShapeError)) {
        $errMsg = 'Roboflow Face Shape (' . $rfFaceShapeError . '). Silakan train model di dashboard Roboflow terlebih dahulu.';
    }
    echo json_encode([
        'success' => false,
        'error' => $errMsg
    ]);
    exit;
}

if (empty($undertone)) {
    $undertone = 'Neutral';
}

$toneDetails = [
    'Warm' => [
        'clothing' => 'Terracotta, Peach, Mustard, Olive, Warm Gold',
        'lipstick' => 'Coral, Brick Red, Warm Nude',
        'jewelry'  => 'Emas (Gold) & Tembaga hangat'
    ],
    'Cool' => [
        'clothing' => 'Berry, Emerald, Royal Blue, Ice Silver, Lavender',
        'lipstick' => 'Rose Pink, Plum, Berry Red',
        'jewelry'  => 'Perak (Silver) & Platinum'
    ],
    'Neutral' => [
        'clothing' => 'Dusty Pink, Jade Green, Soft Navy, Rose Gold',
        'lipstick' => 'Soft Mauve, Peachy Nude, Rose Nude',
        'jewelry'  => 'Rose Gold, Emas & Perak'
    ],
];

$shapeDetails = [
    'Oval' => [
        'eyewear' => 'Geometris, rectangular, atau cat-eye seimbang',
        'hair'    => 'Layer panjang, bob bertekstur, belah samping'
    ],
    'Round' => [
        'eyewear' => 'Persegi panjang (rectangular), wayfarer, sudut tegas',
        'hair'    => 'Layer di bawah dagu, volume atas, poni samping'
    ],
    'Square' => [
        'eyewear' => 'Bulat (round), oval, bingkai kurva lembut',
        'hair'    => 'Soft waves, curtain bangs, layer bertingkat'
    ],
    'Heart' => [
        'eyewear' => 'Aviator, bulat tipis, bingkai bawah lebar',
        'hair'    => 'Bob sebahu bervolume dagu, curtain bangs'
    ],
    'Diamond' => [
        'eyewear' => 'Cat-eye melengkung halus, oval rimless, browline',
        'hair'    => 'Bob bervolume di dagu, layer sebahu'
    ],
    'Oblong' => [
        'eyewear' => 'Oversized lebar, bingkai tebal samping',
        'hair'    => 'Poni depan penuh, rambut bervolume samping'
    ],
];

$uRec = $toneDetails[$undertone] ?? $toneDetails['Neutral'];
$fRec = (!empty($faceShape) && isset($shapeDetails[$faceShape])) ? $shapeDetails[$faceShape] : null;

$recs = [
    "Undertone {$undertone} - Pakaian: {$uRec['clothing']}. Lipstik: {$uRec['lipstick']}. Perhiasan: {$uRec['jewelry']}."
];
if ($fRec) {
    $recs[] = "Wajah {$faceShape} - Kacamata: {$fRec['eyewear']}. Rambut: {$fRec['hair']}.";
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
        'recommendation_details' => [
            'undertone' => $uRec,
            'face_shape' => $fRec
        ],
        'dominant_color' => $dominantColor,
        'created_at' => date('d M Y, H:i')
    ]
]);
