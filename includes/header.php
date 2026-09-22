<?php
require_once __DIR__.'/functions.php';
$isAdmin = !empty($_SESSION['admin']);
$isUser = !empty($_SESSION['user']);
$inAdmin = (strpos($_SERVER['REQUEST_URI'] ?? '', '/admin/') !== false) || (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') !== false);
$rootRel = $inAdmin ? '../' : '';
$adminRel = $inAdmin ? '' : 'admin/';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#123d3a">
    <meta name="description" content="Haven Hotel — thoughtful comfort, warm hospitality and effortless stays.">
    <meta name="user-role" content="<?= $isAdmin ? 'admin' : ($isUser ? 'customer' : 'guest') ?>">
    <meta name="user-id" content="<?= $isAdmin ? (int)($_SESSION['admin']['id'] ?? 0) : ($isUser ? (int)($_SESSION['user']['id'] ?? 0) : 0) ?>">
    <meta name="base-url" content="<?= $rootRel ?>">
    <title><?= e($pageTitle ?? 'Haven Hotel') ?></title>
    <link rel="stylesheet" href="<?= e($cssPath ?? ($rootRel . 'css/style.css')) ?>">
</head>
<body class="<?= $isAdmin ? 'admin-page' : '' ?>">
<a class="skip-link" href="#main-content">Skip to content</a>
<header class="site-header <?= $isAdmin ? 'admin-nav' : '' ?>">
    <div class="container nav">
        <div class="nav-left-wrap">
            <button class="mobile-menu-toggle" id="mobile-menu-toggle" type="button" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="primary-nav">
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
            </button>
            <a class="brand <?= $isAdmin ? 'light' : '' ?>" href="<?= e($homePath ?? ($rootRel . 'index.php')) ?>" aria-label="Haven Hotel home">
                <span class="brand-mark">H</span>
                <span>Haven Hotel</span>
            </a>
        </div>

        <nav id="primary-nav" class="nav-menu" aria-label="Primary navigation">
            <?php if ($isAdmin): ?>
                <div class="mobile-drawer-header">
                    <span class="mobile-role-badge">Admin Portal</span>
                    <span class="mobile-user-name"><?= e($_SESSION['admin']['name']) ?></span>
                </div>
                <a href="<?= e($homePath ?? ($rootRel . 'index.php')) ?>">Home</a>
                <a href="<?= e($adminRel . 'dashboard.php') ?>">Dashboard</a>
                <a href="<?= e($adminRel . 'bookings.php') ?>">Bookings</a>
                <a href="<?= e($adminRel . 'rooms.php') ?>">Rooms</a>
                <a href="<?= e($adminRel . 'customers.php') ?>">Customers</a>
                <a href="<?= e($rootRel . 'contact.php') ?>">Contact</a>
                <div class="nav-status-item">
                    <span class="live-pill" id="live-indicator" title="Real-time live updates active"><span class="dot"></span> Live</span>
                </div>
                <a class="nav-button" href="<?= e($adminRel . 'logout.php') ?>">Log out</a>
            <?php elseif ($isUser): ?>
                <div class="mobile-drawer-header">
                    <span class="mobile-role-badge">Guest Account</span>
                    <span class="mobile-user-name"><?= e($_SESSION['user']['name']) ?></span>
                </div>
                <a href="<?= e($homePath ?? ($rootRel . 'index.php')) ?>">Home</a>
                <a href="<?= e($rootRel . 'rooms.php') ?>">Rooms</a>
                <a href="<?= e($rootRel . 'contact.php') ?>">Contact</a>
                <a href="<?= e($rootRel . 'my_bookings.php') ?>">My bookings</a>
                <div class="nav-status-item">
                    <span class="live-pill" id="live-indicator" title="Real-time live updates active"><span class="dot"></span> Live</span>
                </div>
                <a class="nav-button" href="<?= e($rootRel . 'logout.php') ?>">Log out</a>
            <?php else: ?>
                <a href="<?= e($homePath ?? ($rootRel . 'index.php')) ?>">Home</a>
                <a href="<?= e($rootRel . 'rooms.php') ?>">Rooms</a>
                <a href="<?= e($rootRel . 'contact.php') ?>">Contact</a>
                <div class="nav-status-item">
                    <span class="live-pill" id="live-indicator" title="Real-time live updates active"><span class="dot"></span> Live</span>
                </div>
                <a class="nav-button" href="<?= e($rootRel . 'login.php') ?>">Log in</a>
            <?php endif; ?>
        </nav>

        <div class="nav-mobile-right">
            <span class="live-pill mobile-quick-live" title="Real-time live sync active"><span class="dot"></span></span>
        </div>
    </div>
</header>
<div class="nav-overlay" id="nav-overlay" aria-hidden="true"></div>
<main id="main-content">
