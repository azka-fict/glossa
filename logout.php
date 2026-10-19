<?php
require_once __DIR__ . '/includes/auth.php';

logout_user();
setcookie('glossa_onboarded', '1', time() + (86400 * 365), '/');
header('Location: index.php');
exit;
