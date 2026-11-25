<?php
/**
 * YATIYA COUTURE - Luxury Administrator Sign-In Console
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-functions.php';

// If already authenticated as administrator, redirect to dashboard
if (is_admin_logged_in()) {
    redirect(ADMIN_URL . '/index.php');
}

$error = null;
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $username = clean_input($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Please provide both username/email and password.';
        } else {
            $authRes = authenticate_admin($username, $password);
            if ($authRes['success']) {
                $targetUrl = $_SESSION['admin_intended_url'] ?? (ADMIN_URL . '/index.php');
                unset($_SESSION['admin_intended_url']);
                set_flash('success', 'Welcome to Yatiya Couture Atelier Operations.');
                redirect($targetUrl);
            } else {
                $error = $authRes['message'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrator Sign In | <?= SITE_NAME ?> Atelier</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600;700&family=Inter:wght@300;400;500;600&family=Playfair+Display:ital,wght@0,600;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= ADMIN_URL ?>/assets/css/admin.css">
    <style>
        .login-page {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #1C1A17 0%, #292622 100%);
            padding: 24px;
        }
        .login-card {
            background-color: var(--bg-surface);
            border: 1px solid var(--gold-border);
            border-radius: var(--radius-md);
            width: 100%;
            max-width: 440px;
            padding: 40px;
            box-shadow: 0 16px 40px rgba(0,0,0,0.3);
        }
        .login-brand {
            text-align: center;
            margin-bottom: 28px;
        }
        .login-brand-title {
            font-family: var(--font-heading);
            font-size: 24px;
            letter-spacing: 3px;
            color: #1C1A17;
            font-weight: 700;
            text-transform: uppercase;
        }
        .login-brand-subtitle {
            font-size: 11px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--gold);
            font-weight: 600;
            margin-top: 4px;
        }
    </style>
</head>
<body class="login-page">

<div class="login-card">
    <div class="login-brand">
        <div class="login-brand-title">YATIYA COUTURE</div>
        <div class="login-brand-subtitle">Atelier Operations Console</div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger" style="margin-bottom: 20px;">
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <form action="<?= ADMIN_URL ?>/login.php" method="POST" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <div class="form-group">
            <label class="form-label" for="username">Username or Email</label>
            <input 
                type="text" 
                id="username" 
                name="username" 
                class="form-control" 
                placeholder="admin or admin@yatiyacouture.com" 
                value="<?= e($username) ?>" 
                required 
                autofocus
            >
        </div>

        <div class="form-group" style="margin-bottom: 28px;">
            <label class="form-label" for="password">Administrator Password</label>
            <input 
                type="password" 
                id="password" 
                name="password" 
                class="form-control" 
                placeholder="••••••••••••" 
                required
            >
        </div>

        <button type="submit" class="btn btn-gold" style="width: 100%; padding: 12px; font-size: 14px;">
            Enter Atelier Console
        </button>
    </form>

    <div style="margin-top: 24px; padding-top: 18px; border-top: 1px solid var(--border-color); text-align: center;">
        <a href="<?= BASE_URL ?>/index.php" style="color: var(--text-muted); font-size: 12px; text-decoration: none;">
            &larr; Return to Public Boutique
        </a>
    </div>
</div>

</body>
</html>

