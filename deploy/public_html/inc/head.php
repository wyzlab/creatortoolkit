<?php
/**
 * head.php — top of every rendered page. Opens the document, loads fonts and
 * CSS, exposes the CSRF token to JavaScript, and renders the header bar.
 *
 * Set $pageTitle and (optionally) $pageDesc and $bodyClass before including.
 */

declare(strict_types=1);

$pageTitle = $pageTitle ?? 'DIY Creator Starter Toolkit';
$pageDesc  = $pageDesc  ?? 'A guided toolkit for Filipino coaches, consultants, and creators.';
$bodyClass = $bodyClass ?? '';
$loggedIn  = function_exists('is_logged_in') ? is_logged_in() : false;

// Home for a logged-in learner: My Offers once they have one, else the gates.
$homeHref = '/index.php';
if ($loggedIn && function_exists('current_user')) {
    require_once __DIR__ . '/offers.php';
    $homeHref = user_offer_count(db(), (int)current_user()['id']) > 0 ? '/offers/' : '/dashboard.php';
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= e($pageDesc) ?>">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <meta name="robots" content="noindex, nofollow">
  <title><?= e($pageTitle) ?> | WyzCore Academy</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="<?= e(asset('/css/style.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('/css/components.css')) ?>">
  <link rel="icon" type="image/png" href="/images/favicon.png">
</head>
<body class="<?= e($bodyClass) ?>">
  <a class="skip-link" href="#main">Skip to content</a>

  <header class="site-header">
    <div class="wrap site-header__inner">
      <a href="<?= e($homeHref) ?>" class="site-header__brand">
        <span class="badge badge--studio">Studio Original</span>
        <span class="site-header__title">DIY Creator Starter Toolkit</span>
      </a>
      <?php if ($loggedIn): ?>
        <button type="button" class="nav-toggle" data-nav-toggle aria-controls="site-nav" aria-expanded="false" aria-label="Menu">
          <span class="nav-toggle__bars" aria-hidden="true"></span>
        </button>
        <nav class="site-header__nav" id="site-nav" aria-label="Account">
          <a href="/dashboard.php">Dashboard</a>
          <a href="/offers/">My Offers</a>
          <?php if (function_exists('current_user') && (current_user()['role'] ?? '') === 'admin'): ?>
            <a href="/admin/">Admin</a>
          <?php endif; ?>
          <button type="button" class="btn btn--ghost btn--sm" data-action="logout">Log out</button>
        </nav>
      <?php else: ?>
        <span class="site-header__academy"><?= e(APP['academy_line']) ?></span>
      <?php endif; ?>
    </div>
  </header>

  <main id="main" class="site-main">
