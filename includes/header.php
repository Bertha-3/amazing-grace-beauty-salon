<?php
$currentPage = $_GET['page'] ?? 'home';
$pageTitle = $pageTitle ?? 'Natural hair, beautifully cared for';
$flashes = take_flashes();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#201d1b">
  <title><?= h($pageTitle) ?> | <?= h(APP_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="announcement">A little more care, a lot more confidence <span>·</span> Accra, Ghana</div>
<header class="site-header">
  <div class="header-inner">
    <a class="brand" href="index.php" aria-label="Amazing Grace Beauty Salon home"><span class="brand-mark">AG</span><span>Amazing Grace<small>BEAUTY SALON</small></span></a>
    <button class="menu-toggle" type="button" aria-label="Toggle navigation" aria-expanded="false"><span></span><span></span></button>
    <nav class="main-nav" aria-label="Main navigation">
      <a class="<?= $currentPage === 'home' ? 'active' : '' ?>" href="index.php">Home</a>
      <a class="<?= $currentPage === 'about' ? 'active' : '' ?>" href="index.php?page=about">Our story</a>
      <a class="<?= $currentPage === 'styles' ? 'active' : '' ?>" href="index.php?page=styles">Styles</a>
      <a class="<?= $currentPage === 'services' ? 'active' : '' ?>" href="index.php?page=services">Services</a>
      <a class="<?= in_array($currentPage, ['shop','cart'], true) ? 'active' : '' ?>" href="index.php?page=shop">Shop</a>
      <a class="nav-book" href="index.php?page=booking">Book a visit <span>↗</span></a>
      <a class="nav-account" href="index.php?page=account"><?= customer() ? 'My account' : 'Sign in' ?></a>
      <a class="cart-link" href="index.php?page=cart" aria-label="Shopping cart">Bag <span><?= cart_count() ?></span></a>
    </nav>
  </div>
</header>
<?php if ($flashes): ?><div class="flash-wrap"><?php foreach ($flashes as $flash): ?><div class="notice notice-<?= h($flash['type']) ?>" role="status"><?= h($flash['message']) ?></div><?php endforeach; ?></div><?php endif; ?>
<main>
