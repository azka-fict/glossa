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

$undertone = trim($data['undertone'] ?? '');
$skinTone = trim($data['skin_tone'] ?? 'Medium');
$accuracy = (int)($data['accuracy'] ?? 95);
$recommendations = trim($data['recommendations'] ?? '');
$dominantColor = trim($data['dominant_color'] ?? '#e2a76f');

$validUndertones = ['Warm', 'Cool', 'Neutral'];
if (!in_array($undertone, $validUndertones, true)) {
    $undertone = 'Warm';
}

if ($recommendations === '') {
    if ($undertone === 'Warm') {
        $recommendations = 'Warna Terbaik: Terracotta, Peach, Mustard, Olive, Warm Gold. Lipstik: Coral, Brick Red, Warm Nude.';
    } elseif ($undertone === 'Cool') {
        $recommendations = 'Warna Terbaik: Berry, Emerald, Royal Blue, Silver, Lavender. Lipstik: Rose Pink, Plum, Berry Red.';
    } else {
        $recommendations = 'Warna Terbaik: Dusty Pink, Jade Green, Soft Navy, Rose Gold. Lipstik: Soft Mauve, Peachy Nude.';
    }
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
