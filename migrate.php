<?php
require_once __DIR__ . '/includes/app.php';

$migrationToken = getenv('SALON_MIGRATION_TOKEN');
if (getenv('RENDER') !== 'true' || $migrationToken === false || $migrationToken === '') {
    http_response_code(404);
    exit;
}

$marker = __DIR__ . '/uploads/.database-import-complete';
$complete = is_file($marker);
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$complete) {
    verify_csrf();
    $submittedToken = $_POST['migration_token'] ?? '';
    try {
        if (!is_string($submittedToken) || !hash_equals($migrationToken, $submittedToken)) {
            throw new RuntimeException('The migration token is incorrect.');
        }

        $upload = $_FILES['database_dump'] ?? null;
        if (!$upload || $upload['error'] !== UPLOAD_ERR_OK || $upload['size'] > 5 * 1024 * 1024) {
            throw new RuntimeException('Upload a valid SQL export smaller than 5 MB.');
        }
        if (strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION)) !== 'sql') {
            throw new RuntimeException('The database export must be a .sql file.');
        }

        $dumpPath = tempnam(sys_get_temp_dir(), 'salon-db-');
        $optionsPath = tempnam(sys_get_temp_dir(), 'salon-mysql-');
        if ($dumpPath === false) {
            throw new RuntimeException('Could not allocate temporary files for the import.');
        }
        if ($optionsPath === false) {
            unlink($dumpPath);
            throw new RuntimeException('Could not allocate temporary files for the import.');
        }
        if (!chmod($dumpPath, 0600) || !chmod($optionsPath, 0600)) {
            unlink($dumpPath);
            unlink($optionsPath);
            throw new RuntimeException('Could not secure the temporary files for the import.');
        }

        try {
            if (!move_uploaded_file($upload['tmp_name'], $dumpPath)) {
                throw new RuntimeException('Could not stage the uploaded database export.');
            }

            $mysqlOptions = "[client]\n"
                . 'host="' . str_replace(['\\', '"'], ['\\\\', '\\"'], DB_HOST) . "\"\n"
                . 'port=' . DB_PORT . "\n"
                . 'user="' . str_replace(['\\', '"'], ['\\\\', '\\"'], DB_USER) . "\"\n"
                . 'password="' . str_replace(['\\', '"'], ['\\\\', '\\"'], DB_PASS) . "\"\n";
            if (file_put_contents($optionsPath, $mysqlOptions, LOCK_EX) === false) {
                throw new RuntimeException('Could not prepare the temporary database connection file.');
            }

            $command = [
                'mysql',
                '--defaults-extra-file=' . $optionsPath,
                '--binary-mode=1',
                '--default-character-set=utf8mb4',
                DB_NAME,
            ];
            $descriptors = [
                0 => ['file', $dumpPath, 'r'],
                1 => ['file', '/dev/null', 'w'],
                2 => ['pipe', 'w'],
            ];
            $process = proc_open($command, $descriptors, $pipes);
            if (!is_resource($process)) {
                throw new RuntimeException('Could not start the MySQL import client.');
            }
            stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);
            if ($exitCode !== 0) {
                error_log('Database migration failed with MySQL client exit code ' . $exitCode . '.');
                throw new RuntimeException('The database import failed. Check the Render service logs and try again.');
            }
        } finally {
            $cleanupFailed = false;
            foreach ([$dumpPath, $optionsPath] as $temporaryFile) {
                if (is_file($temporaryFile) && !unlink($temporaryFile)) {
                    $cleanupFailed = true;
                }
            }
            if ($cleanupFailed) {
                error_log('A temporary database migration file could not be removed.');
                throw new RuntimeException('The import stopped with temporary files left behind. Contact the site operator before retrying.');
            }
        }

        if (file_put_contents($marker, gmdate('c'), LOCK_EX) === false) {
            throw new RuntimeException('Import finished, but the one-time lock could not be saved. Remove SALON_MIGRATION_TOKEN in Render now.');
        }
        $success = true;
        $complete = true;
    } catch (RuntimeException $exception) {
        $error = $exception->getMessage();
    }
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Database migration</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body class="setup-page"><main class="setup-card">
  <a class="brand brand-dark" href="index.php"><span class="brand-mark">AG</span><span>Amazing Grace<small>BEAUTY SALON</small></span></a>
  <p class="eyebrow">ONE-TIME DATA TRANSFER</p><h1>Import salon data</h1>
  <?php if ($success): ?>
    <div class="notice notice-success">Database import completed. Remove <code>SALON_MIGRATION_TOKEN</code> from Render now, then continue with first-time setup.</div>
  <?php elseif ($complete): ?>
    <div class="notice notice-info">A database import has already completed. Remove <code>SALON_MIGRATION_TOKEN</code> from Render before continuing.</div>
  <?php else: ?>
    <p>This replaces matching tables in the hosted database with the uploaded SQL export. Upload only an export from your salon database.</p>
    <?php if ($error): ?><div class="notice notice-error"><?= h($error) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="form-stack">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <label>Migration token<input type="password" name="migration_token" required autocomplete="off"></label>
      <label>SQL export (up to 5 MB)<input type="file" name="database_dump" accept=".sql" required></label>
      <button class="button button-dark" type="submit">Import database export</button>
    </form>
  <?php endif; ?>
</main></body></html>
