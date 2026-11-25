<?php
require_once __DIR__ . '/includes/app.php';

$error = '';
$done = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    try {
        if (getenv('RENDER') === 'true') {
            $setupToken = getenv('SALON_SETUP_TOKEN');
            $submittedToken = $_POST['setup_token'] ?? '';
            if ($setupToken === false || $setupToken === '') {
                throw new RuntimeException('Set SALON_SETUP_TOKEN in Render before running first-time setup.');
            }
            if (!is_string($submittedToken) || !hash_equals($setupToken, $submittedToken)) {
                throw new RuntimeException('The first-time setup token is incorrect.');
            }
        }
        $pdo = db();
        $sql = file_get_contents(__DIR__ . '/schema.sql');
        if ($sql === false) {
            throw new RuntimeException('Could not read schema.sql.');
        }
        foreach (explode(';', $sql) as $statement) {
            $statement = trim($statement);
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }
        $adminCount = (int)$pdo->query('SELECT COUNT(*) FROM administrators')->fetchColumn();
        if ($adminCount > 0) {
            throw new RuntimeException('An administrator already exists. Sign in from the admin login page.');
        }
        if (!$name || !$email || strlen($password) < 10) {
            throw new RuntimeException('Enter your name, a valid email address, and a password of at least 10 characters.');
        }
        $stmt = $pdo->prepare('INSERT INTO administrators (name, email, password_hash) VALUES (?, ?, ?)');
        $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
        $done = true;
    } catch (PDOException $e) {
        $error = 'Database setup failed. Confirm the existing database name and MySQL credentials in config/config.php. Details: ' . $e->getMessage();
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Salon setup</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body class="setup-page"><main class="setup-card">
  <a class="brand brand-dark" href="index.php"><span class="brand-mark">AG</span><span>Amazing Grace<small>BEAUTY SALON</small></span></a>
  <p class="eyebrow">FIRST-TIME SETUP</p><h1>Prepare your salon</h1>
  <p>This installs the tables inside your existing <strong><?= h(DB_NAME) ?></strong> database and creates your first administrator account. It does not create a database.</p>
  <?php if ($done): ?><div class="notice notice-success">Setup is complete. Your administrator account is ready.</div><a class="button button-dark" href="index.php?page=admin-login">Sign in to the dashboard</a>
  <?php else: ?>
    <?php if ($error): ?><div class="notice notice-error"><?= h($error) ?></div><?php endif; ?>
    <form method="post" class="form-stack">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <?php if (getenv('RENDER') === 'true'): ?>
        <label>First-time setup token<input type="password" name="setup_token" required autocomplete="off"></label>
      <?php endif; ?>
      <label>Your name<input name="name" required maxlength="120" value="<?= h($_POST['name'] ?? '') ?>"></label>
      <label>Administrator email<input type="email" name="email" required value="<?= h($_POST['email'] ?? '') ?>"></label>
      <label>Password (at least 10 characters)<input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
      <button class="button button-dark" type="submit">Initialize database &amp; create admin</button>
    </form>
  <?php endif; ?>
</main></body></html>
