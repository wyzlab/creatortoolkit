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

  <link rel="stylesheet" href="/css/style.css">
  <link rel="stylesheet" href="/css/components.css">
  <link rel="icon" type="image/png" href="/images/favicon.png">
</head>
<body class="<?= e($bodyClass) ?>">
  <a class="skip-link" href="#main">Skip to content</a>

  <header class="site-header">
    <div class="wrap site-header__inner">
      <a href="<?= $loggedIn ? '/dashboard.php' : '/index.php' ?>" class="site-header__brand">
        <span class="badge badge--studio">Studio Original</span>
        <span class="site-header__title">DIY Creator Starter Toolkit</span>
      </a>
      <?php if ($loggedIn): ?>
        <nav class="site-header__nav" aria-label="Account">
          <a href="/dashboard.php">Dashboard</a>
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
