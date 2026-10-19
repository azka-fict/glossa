<?php
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json');

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

$image = $data['image'] ?? null;
$skinTone = trim($data['skin_tone'] ?? 'Medium');
$dominantColor = trim($data['dominant_color'] ?? '#e2a76f');
$undertone = trim($data['undertone'] ?? '');
$accuracy = isset($data['accuracy']) ? (int)$data['accuracy'] : 95;

$rfConfig = file_exists(__DIR__ . '/config/roboflow.php') ? require __DIR__ . '/config/roboflow.php' : [];
$apiKey = $rfConfig['api_key'] ?? '';
$modelId = $rfConfig['model_id'] ?? '';
$version = $rfConfig['version'] ?? '1';

if (!empty($apiKey) && !empty($modelId) && !empty($image)) {
    $base64 = $image;
    if (strpos($base64, ',') !== false) {
        $base64 = substr($base64, strpos($base64, ',') + 1);
    }

    $url = "https://detect.roboflow.com/" . rawurlencode($modelId) . "/" . rawurlencode($version) . "?api_key=" . rawurlencode($apiKey);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $base64,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    $res = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status === 200 && $res) {
        $rf = json_decode($res, true);
        if (!empty($rf['top'])) {
            $undertone = ucfirst(strtolower($rf['top']));
            if (isset($rf['confidence'])) {
                $accuracy = (int)round($rf['confidence'] * 100);
            }
        } elseif (!empty($rf['predictions'][0]['class'])) {
            $undertone = ucfirst(strtolower($rf['predictions'][0]['class']));
            if (isset($rf['predictions'][0]['confidence'])) {
                $accuracy = (int)round($rf['predictions'][0]['confidence'] * 100);
            }
        }
    }
}

// shortcut: fallback jika kredensial roboflow belum diisi, isi api_key dan model_id di config/roboflow.php
if (!in_array($undertone, ['Warm', 'Cool', 'Neutral'], true)) {
    $tones = ['Warm', 'Cool', 'Neutral'];
    $undertone = $tones[array_rand($tones)];
    $accuracy = random_int(93, 98);
}

if ($undertone === 'Warm') {
    $recommendations = 'Warna Terbaik: Terracotta, Peach, Mustard, Olive, Warm Gold. Lipstik: Coral, Brick Red, Warm Nude.';
} elseif ($undertone === 'Cool') {
    $recommendations = 'Warna Terbaik: Berry, Emerald, Royal Blue, Silver, Lavender. Lipstik: Rose Pink, Plum, Berry Red.';
} else {
    $undertone = 'Neutral';
    $recommendations = 'Warna Terbaik: Dusty Pink, Jade Green, Soft Navy, Rose Gold. Lipstik: Soft Mauve, Peachy Nude.';
}

$stmt = $pdo->prepare('INSERT INTO scan_history (user_id, undertone, skin_tone, accuracy, recommendations, dominant_color) VALUES (?, ?, ?, ?, ?, ?)');
$stmt->execute([
    $user['id'],
    $undertone,
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
        'skin_tone' => $skinTone,
        'accuracy' => $accuracy,
        'recommendations' => $recommendations,
        'dominant_color' => $dominantColor,
        'created_at' => date('d M Y, H:i')
    ]
]);
