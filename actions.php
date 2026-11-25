<?php
require_once __DIR__ . '/includes/app.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
verify_csrf();
$action = $_POST['action'] ?? '';

function action_failure_url(string $action): string
{
    if ($action === 'admin_login') {
        return 'index.php?page=admin-login';
    }
    if ($action === 'register') {
        return 'index.php?page=register';
    }
    if ($action === 'login') {
        return 'index.php?page=login';
    }
    if ($action === 'book') {
        return 'index.php?page=booking';
    }
    if ($action === 'checkout') {
        return 'index.php?page=checkout';
    }
    if (in_array($action, ['cart_add','cart_update'], true)) {
        return 'index.php?page=cart';
    }
    if ($action === 'contact') {
        return 'index.php?page=contact';
    }
    if (in_array($action, ['profile','review'], true)) {
        return 'index.php?page=account';
    }
    if ($action === 'change_password') {
        return ($_POST['user_type'] ?? '') === 'admin' ? 'admin.php?page=profile' : 'index.php?page=account';
    }
    if ($action === 'forgot') {
        return 'index.php?page=forgot&type=' . (($_POST['user_type'] ?? '') === 'admin' ? 'admin' : 'customer');
    }
    if ($action === 'reset') {
        $type = ($_POST['user_type'] ?? '') === 'admin' ? 'admin' : 'customer';
        return 'index.php?page=reset&token=' . rawurlencode($_POST['token'] ?? '') . '&type=' . $type;
    }
    return 'index.php';
}

try {
    switch ($action) {
        case 'register':
            $name = trim($_POST['name'] ?? '');
            $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
            $phone = trim($_POST['phone'] ?? '');
            $password = $_POST['password'] ?? '';
            if (!$name || !$email || strlen($password) < 10) {
                throw new RuntimeException('Enter your name, a valid email address, and a password of at least 10 characters.');
            }
            $stmt = db()->prepare('INSERT INTO customers (name,email,phone,password_hash) VALUES (?,?,?,?)');
            $stmt->execute([$name, $email, $phone, password_hash($password, PASSWORD_DEFAULT)]);
            session_regenerate_id(true);
            $_SESSION['customer'] = ['id' => (int)db()->lastInsertId(), 'name' => $name, 'email' => $email];
            flash('success', 'Welcome to Amazing Grace. Your account is ready.');
            redirect('index.php?page=account');

        case 'login':
        case 'admin_login':
            $isAdmin = $action === 'admin_login';
            $table = $isAdmin ? 'administrators' : 'customers';
            $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
            $stmt = db()->prepare("SELECT id,name,email,password_hash FROM $table WHERE email = ?");
            $stmt->execute([$email ?: '']);
            $user = $stmt->fetch();
            if (!$user || !password_verify($_POST['password'] ?? '', $user['password_hash'])) {
                throw new RuntimeException('Email or password is incorrect.');
            }
            session_regenerate_id(true);
            unset($user['password_hash']);
            $_SESSION[$isAdmin ? 'admin' : 'customer'] = $user;
            flash('success', 'You are signed in.');
            redirect($isAdmin ? 'admin.php' : 'index.php?page=account');

        case 'logout':
            unset($_SESSION['customer'], $_SESSION['admin']);
            session_regenerate_id(true);
            flash('success', 'You have been signed out.');
            redirect('index.php');

        case 'profile':
            require_customer();
            $name = trim($_POST['name'] ?? '');
            $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
            $phone = trim($_POST['phone'] ?? '');
            if (!$name || !$email) {
                throw new RuntimeException('Enter your name and a valid email address.');
            }
            db()->prepare('UPDATE customers SET name=?,email=?,phone=? WHERE id=?')->execute([$name, $email, $phone, customer()['id']]);
            $_SESSION['customer']['name'] = $name;
            $_SESSION['customer']['email'] = $email;
            flash('success', 'Your profile has been updated.');
            redirect('index.php?page=account');

        case 'change_password':
            $isAdmin = ($_POST['user_type'] ?? '') === 'admin';
            $user = $isAdmin ? admin() : customer();
            if (!$user) {
                throw new RuntimeException('Sign in before changing your password.');
            }
            $table = $isAdmin ? 'administrators' : 'customers';
            $stmt = db()->prepare("SELECT password_hash FROM $table WHERE id=?");
            $stmt->execute([$user['id']]);
            $hash = $stmt->fetchColumn();
            $newPassword = $_POST['new_password'] ?? '';
            if (!$hash || !password_verify($_POST['current_password'] ?? '', $hash)) {
                throw new RuntimeException('Your current password is incorrect.');
            }
            if (strlen($newPassword) < 10) {
                throw new RuntimeException('Your new password must be at least 10 characters.');
            }
            db()->prepare("UPDATE $table SET password_hash=? WHERE id=?")->execute([password_hash($newPassword, PASSWORD_DEFAULT), $user['id']]);
            flash('success', 'Your password has been changed.');
            redirect($isAdmin ? 'admin.php?page=profile' : 'index.php?page=account');

        case 'book':
            require_customer();
            $date = $_POST['appointment_date'] ?? '';
            $time = $_POST['appointment_time'] ?? '';
            $serviceId = (int)($_POST['service_id'] ?? 0);
            $styleId = (int)($_POST['hairstyle_id'] ?? 0);
            $notes = trim($_POST['notes'] ?? '');
            $slots = ['09:00:00','10:30:00','12:00:00','13:30:00','15:00:00','16:30:00'];
            $parsedDate = DateTime::createFromFormat('Y-m-d', $date);
            if (!$parsedDate || $parsedDate->format('Y-m-d') !== $date || $date < date('Y-m-d') ||
                $date > date('Y-m-d', strtotime('+6 months')) || (int)$parsedDate->format('N') < 2 ||
                (int)$parsedDate->format('N') > 6 || !in_array($time, $slots, true) ||
                ($date === date('Y-m-d') && $time <= date('H:i:s')) ||
                (!$serviceId && !$styleId)) {
                throw new RuntimeException('Choose a valid Tuesday–Saturday date, an available time, and a service or style.');
            }
            $pdo = db();
            if ($serviceId) {
                $check = $pdo->prepare('SELECT id FROM services WHERE id=? AND is_active=1');
                $check->execute([$serviceId]);
                if (!$check->fetchColumn()) {
                    throw new RuntimeException('That service is no longer available.');
                }
            }
            if ($styleId) {
                $check = $pdo->prepare('SELECT id FROM hairstyles WHERE id=? AND is_active=1');
                $check->execute([$styleId]);
                if (!$check->fetchColumn()) {
                    throw new RuntimeException('That hairstyle is no longer available.');
                }
            }
            $check = $pdo->prepare("SELECT id FROM appointments WHERE appointment_date=? AND appointment_time=? AND status <> 'cancelled'");
            $check->execute([$date, $time]);
            if ($check->fetchColumn()) {
                throw new RuntimeException('That time was just booked. Please choose another available slot.');
            }
            $stmt = $pdo->prepare('INSERT INTO appointments (customer_id,service_id,hairstyle_id,appointment_date,appointment_time,notes) VALUES (?,?,?,?,?,?)');
            $stmt->execute([customer()['id'], $serviceId ?: null, $styleId ?: null, $date, $time, $notes ?: null]);
            flash('success', 'Your appointment request is in. We will confirm it shortly.');
            redirect('index.php?page=account');

        case 'cart_add':
            $id = (int)($_POST['product_id'] ?? 0);
            $quantity = max(1, min(20, (int)($_POST['quantity'] ?? 1)));
            $stmt = db()->prepare('SELECT stock FROM products WHERE id=? AND is_active=1');
            $stmt->execute([$id]);
            $stock = $stmt->fetchColumn();
            if ($stock === false || (int)$stock < 1) {
                throw new RuntimeException('That product is currently unavailable.');
            }
            $_SESSION['cart'][$id] = min((int)$stock, (int)($_SESSION['cart'][$id] ?? 0) + $quantity);
            flash('success', 'Added to your bag.');
            redirect('index.php?page=shop');

        case 'cart_update':
            foreach (($_POST['quantity'] ?? []) as $id => $quantity) {
                $id = (int)$id;
                $quantity = (int)$quantity;
                if ($quantity < 1) {
                    unset($_SESSION['cart'][$id]);
                    continue;
                }
                $stmt = db()->prepare('SELECT stock FROM products WHERE id=? AND is_active=1');
                $stmt->execute([$id]);
                $stock = $stmt->fetchColumn();
                if ($stock === false) {
                    unset($_SESSION['cart'][$id]);
                } else {
                    $_SESSION['cart'][$id] = min((int)$stock, $quantity);
                }
            }
            flash('success', 'Your bag has been updated.');
            redirect('index.php?page=cart');

        case 'checkout':
            require_customer();
            $items = cart_items();
            if (!$items) {
                throw new RuntimeException('Your bag is empty.');
            }
            $address = trim($_POST['delivery_address'] ?? '');
            if (strlen($address) < 8) {
                throw new RuntimeException('Enter a complete delivery address.');
            }
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $total = 0;
                $locked = [];
                foreach ($items as $item) {
                    $stmt = $pdo->prepare('SELECT id,name,price,stock FROM products WHERE id=? AND is_active=1 FOR UPDATE');
                    $stmt->execute([$item['id']]);
                    $product = $stmt->fetch();
                    if (!$product || (int)$product['stock'] < $item['quantity']) {
                        throw new RuntimeException($item['name'] . ' no longer has enough stock. Please update your bag.');
                    }
                    $product['quantity'] = $item['quantity'];
                    $locked[] = $product;
                    $total += (float)$product['price'] * $item['quantity'];
                }
                $number = 'AG' . date('ymd') . strtoupper(bin2hex(random_bytes(3)));
                $pdo->prepare("INSERT INTO orders (customer_id,order_number,total,delivery_address) VALUES (?,?,?,?)")
                    ->execute([customer()['id'], $number, $total, $address]);
                $orderId = (int)$pdo->lastInsertId();
                foreach ($locked as $product) {
                    $pdo->prepare('INSERT INTO order_items (order_id,product_id,product_name,unit_price,quantity) VALUES (?,?,?,?,?)')
                        ->execute([$orderId, $product['id'], $product['name'], $product['price'], $product['quantity']]);
                    $pdo->prepare('UPDATE products SET stock=stock-? WHERE id=?')->execute([$product['quantity'], $product['id']]);
                }
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
            unset($_SESSION['cart']);
            flash('success', 'Order ' . $number . ' placed successfully. We will be in touch to arrange delivery.');
            redirect('index.php?page=account');

        case 'review':
            require_customer();
            $rating = (int)($_POST['rating'] ?? 0);
            $comment = trim($_POST['comment'] ?? '');
            if ($rating < 1 || $rating > 5 || strlen($comment) < 5) {
                throw new RuntimeException('Choose a 1–5 star rating and write a short review.');
            }
            db()->prepare('INSERT INTO reviews (customer_id,rating,comment) VALUES (?,?,?)')->execute([customer()['id'], $rating, $comment]);
            flash('success', 'Thank you. Your review will appear after approval.');
            redirect('index.php?page=account');

        case 'contact':
            $name = trim($_POST['name'] ?? '');
            $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
            $subject = trim($_POST['subject'] ?? '');
            $message = trim($_POST['message'] ?? '');
            if (!$name || !$email || !$subject || strlen($message) < 10) {
                throw new RuntimeException('Please complete each field and provide a message of at least 10 characters.');
            }
            db()->prepare('INSERT INTO contact_messages (name,email,subject,message) VALUES (?,?,?,?)')->execute([$name, $email, $subject, $message]);
            flash('success', 'Your message has been received. Thank you for getting in touch.');
            redirect('index.php?page=contact');

        case 'forgot':
            $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
            $type = ($_POST['user_type'] ?? '') === 'admin' ? 'admin' : 'customer';
            if (!$email) {
                throw new RuntimeException('Enter a valid email address.');
            }
            $table = $type === 'admin' ? 'administrators' : 'customers';
            $stmt = db()->prepare("SELECT id FROM $table WHERE email=?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn()) {
                $token = bin2hex(random_bytes(32));
                $hash = hash('sha256', $token);
                db()->prepare('DELETE FROM password_resets WHERE email=? AND user_type=?')->execute([$email, $type]);
                db()->prepare('INSERT INTO password_resets (email,user_type,token_hash,expires_at) VALUES (?,?,?,DATE_ADD(NOW(), INTERVAL 1 HOUR))')->execute([$email, $type, $hash]);
                $link = base_url('index.php?page=reset&token=' . urlencode($token) . '&type=' . urlencode($type));
                $sent = mail($email, 'Reset your Amazing Grace password', "Use this one-time link within one hour:\n\n$link\n\nIf you did not request this, ignore this email.");
                if (!$sent) {
                    throw new RuntimeException('Password reset email could not be sent. Configure PHP mail on this server and try again.');
                }
            }
            flash('success', 'If an account matches that email, a password reset link has been sent.');
            redirect('index.php?page=forgot&type=' . $type);

        case 'reset':
            $type = ($_POST['user_type'] ?? '') === 'admin' ? 'admin' : 'customer';
            $token = $_POST['token'] ?? '';
            $password = $_POST['password'] ?? '';
            if (strlen($password) < 10 || !preg_match('/^[a-f0-9]{64}$/', $token)) {
                throw new RuntimeException('Use a valid reset link and a password of at least 10 characters.');
            }
            $stmt = db()->prepare('SELECT id,email FROM password_resets WHERE token_hash=? AND user_type=? AND expires_at>NOW()');
            $stmt->execute([hash('sha256', $token), $type]);
            $reset = $stmt->fetch();
            if (!$reset) {
                throw new RuntimeException('This reset link is invalid or expired. Request a new one.');
            }
            $table = $type === 'admin' ? 'administrators' : 'customers';
            db()->prepare("UPDATE $table SET password_hash=? WHERE email=?")->execute([password_hash($password, PASSWORD_DEFAULT), $reset['email']]);
            db()->prepare('DELETE FROM password_resets WHERE email=? AND user_type=?')->execute([$reset['email'], $type]);
            flash('success', 'Your password has been reset. You can now sign in.');
            redirect($type === 'admin' ? 'index.php?page=admin-login' : 'index.php?page=login');

        default:
            http_response_code(400);
            exit('Unknown action.');
    }
} catch (PDOException $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($action === 'register' && (string)$e->getCode() === '23000') {
        flash('error', 'An account already uses that email address.');
    } elseif ($action === 'book' && (string)$e->getCode() === '23000') {
        flash('error', 'That appointment time was just booked. Please choose another slot.');
    } else {
        error_log('Salon application database error: ' . $e->getMessage());
        flash('error', 'We could not complete that request. Please try again or contact the salon.');
    }
    redirect(action_failure_url($action));
} catch (RuntimeException $e) {
    flash('error', $e->getMessage());
    redirect(action_failure_url($action));
}
