<?php
require_once __DIR__ . '/auth.php';
$authUser = current_user();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'glossa') ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>
<header class="navbar">
    <div class="nav-container">
        <a href="index.php" class="brand">glossa</a>
        <nav class="nav-links">
            <?php if ($authUser): ?>
                <a href="profile.php" class="nav-profile-btn" title="Dashboard Profil">
                    <span class="nav-avatar"><?= strtoupper(mb_substr($authUser['name'], 0, 1)) ?></span>
                    <span class="nav-name"><?= htmlspecialchars($authUser['name']) ?></span>
                </a>
                <a href="logout.php" class="nav-link">Keluar</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="container">
