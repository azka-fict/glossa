<?php
require_once __DIR__ . '/auth.php';
$authUser = current_user();
$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
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
            <a href="index.php" class="nav-link <?= $currentScript === 'index.php' ? 'active' : '' ?>">Dashboard</a>
            <a href="tutorial.php" class="nav-link <?= $currentScript === 'tutorial.php' ? 'active' : '' ?>">Tutorial</a>
            <?php if ($authUser): ?>
                <a href="profile.php" class="nav-link <?= $currentScript === 'profile.php' ? 'active' : '' ?>">Profile</a>
                <a href="logout.php" class="nav-link">Keluar</a>
            <?php else: ?>
                <a href="login.php" class="nav-link <?= $currentScript === 'login.php' ? 'active' : '' ?>">Masuk</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="container">
