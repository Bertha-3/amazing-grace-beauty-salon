<?php
require_once __DIR__ . '/includes/app.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
verify_csrf();
$entity = $_POST['entity'] ?? '';
$page = $_POST['return_page'] ?? $entity;
$redirect = 'admin.php?page=' . rawurlencode($page);
$definitions = [
    'styles'=>['table'=>'hairstyles','fields'=>['name','description','price','category_id','is_featured','is_active'],'image'=>true],
    'services'=>['table'=>'services','fields'=>['name','description','price','duration_minutes','is_active']],
    'products'=>['table'=>'products','fields'=>['name','description','price','stock','category_id','is_active'],'image'=>true],
    'categories'=>['table'=>'categories','fields'=>['name','kind']],
];
try {
    $action = $_POST['action'] ?? '';
    if ($action === 'save' && isset($definitions[$entity])) {
        $definition = $definitions[$entity];
        $id = (int)($_POST['id'] ?? 0);
        $values = [];
        foreach ($definition['fields'] as $field) {
            $value = $_POST[$field] ?? '';
            if (in_array($field, ['price','duration_minutes','stock','category_id'], true)) {
                if ($value !== '' && !is_numeric($value)) {
                    throw new RuntimeException('Enter a valid number for ' . str_replace('_', ' ', $field) . '.');
                }
                $value = $value === '' ? null : $value;
            } elseif (in_array($field, ['is_active','is_featured'], true)) {
                $value = isset($_POST[$field]) ? 1 : 0;
            } else {
                $value = trim((string)$value);
            }
            $values[$field] = $value;
        }
        foreach (['name','description'] as $required) {
            if (in_array($required, $definition['fields'], true) && $values[$required] === '') {
                throw new RuntimeException(ucfirst($required) . ' is required.');
            }
        }
        if (isset($values['price']) && (float)$values['price'] < 0) {
            throw new RuntimeException('Price cannot be negative.');
        }
        if ($entity === 'categories' && !in_array($values['kind'], ['product','style'], true)) {
            throw new RuntimeException('Choose a valid category type.');
        }
        if ($entity === 'services' && ((int)$values['duration_minutes'] < 1 || (int)$values['duration_minutes'] > 1440)) {
            throw new RuntimeException('Service duration must be between 1 and 1440 minutes.');
        }
        if (in_array($entity, ['products','styles'], true) && $values['category_id'] !== null) {
            $kind = $entity === 'products' ? 'product' : 'style';
            $check = db()->prepare('SELECT id FROM categories WHERE id=? AND kind=?');
            $check->execute([$values['category_id'], $kind]);
            if (!$check->fetchColumn()) {
                throw new RuntimeException('Choose a category of the correct type.');
            }
        }
        if (!empty($definition['image'])) {
            $current = null;
            if ($id) {
                $stmt = db()->prepare('SELECT image FROM ' . $definition['table'] . ' WHERE id=?');
                $stmt->execute([$id]);
                $current = $stmt->fetchColumn() ?: null;
                if ($current === null && $stmt->rowCount() === 0) {
                    throw new RuntimeException('That record could not be found.');
                }
            }
            $values['image'] = upload_image('image', $current);
        }
        if ($id) {
            $assignments = implode(',', array_map(static fn($field) => "`$field`=?", array_keys($values)));
            db()->prepare('UPDATE ' . $definition['table'] . " SET $assignments WHERE id=?")->execute([...array_values($values), $id]);
            flash('success', ucfirst(rtrim($entity, 's')) . ' updated.');
        } else {
            $columns = implode(',', array_map(static fn($field) => "`$field`", array_keys($values)));
            $marks = implode(',', array_fill(0, count($values), '?'));
            db()->prepare('INSERT INTO ' . $definition['table'] . " ($columns) VALUES ($marks)")->execute(array_values($values));
            flash('success', ucfirst(rtrim($entity, 's')) . ' added.');
        }
    } elseif ($action === 'delete' && in_array($entity, ['styles','services','products','categories'], true)) {
        $table = $definitions[$entity]['table'];
        db()->prepare("DELETE FROM $table WHERE id=?")->execute([(int)($_POST['id'] ?? 0)]);
        flash('success', ucfirst(rtrim($entity, 's')) . ' deleted.');
    } elseif ($action === 'appointment_status') {
        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['pending','confirmed','completed','cancelled'], true)) {
            throw new RuntimeException('Choose a valid appointment status.');
        }
        db()->prepare('UPDATE appointments SET status=? WHERE id=?')->execute([$status, (int)($_POST['id'] ?? 0)]);
        flash('success', 'Appointment status updated.');
    } elseif ($action === 'order_status') {
        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['pending','processing','completed','cancelled'], true)) {
            throw new RuntimeException('Choose a valid order status.');
        }
        $pdo = db();
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT status FROM orders WHERE id=? FOR UPDATE');
        $stmt->execute([(int)($_POST['id'] ?? 0)]);
        $oldStatus = $stmt->fetchColumn();
        if ($oldStatus === false) {
            throw new RuntimeException('Order not found.');
        }
        if ($oldStatus === 'cancelled' && $status !== 'cancelled') {
            throw new RuntimeException('A cancelled order cannot be reopened. Contact the customer to place a new order.');
        }
        if ($status === 'cancelled' && $oldStatus !== 'cancelled') {
            $stmt = $pdo->prepare('SELECT product_id,quantity FROM order_items WHERE order_id=?');
            $stmt->execute([(int)$_POST['id']]);
            foreach ($stmt->fetchAll() as $item) {
                if ($item['product_id']) {
                    $pdo->prepare('UPDATE products SET stock=stock+? WHERE id=?')->execute([$item['quantity'], $item['product_id']]);
                }
            }
        }
        $pdo->prepare('UPDATE orders SET status=? WHERE id=?')->execute([$status, (int)$_POST['id']]);
        $pdo->commit();
        flash('success', 'Order status updated' . ($status === 'cancelled' ? ' and stock returned.' : '.'));
    } elseif ($action === 'review_status') {
        db()->prepare('UPDATE reviews SET is_approved=? WHERE id=?')->execute([(int)($_POST['approved'] ?? 0) ? 1 : 0, (int)($_POST['id'] ?? 0)]);
        flash('success', 'Review visibility updated.');
    } elseif ($action === 'settings') {
        foreach (['salon_phone','salon_email','salon_address','salon_hours'] as $key) {
            $value = trim($_POST[$key] ?? '');
            if ($key === 'salon_email' && $value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Enter a valid salon email address.');
            }
            db()->prepare('INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute([$key, $value]);
        }
        flash('success', 'Salon contact details saved.');
    } else {
        throw new RuntimeException('That management action is not available.');
    }
} catch (RuntimeException $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash('error', $e->getMessage());
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($e instanceof PDOException) {
        error_log('Salon admin database error: ' . $e->getMessage());
        flash('error', 'Could not save your changes. Check for duplicate values or linked records and try again.');
    } else {
        flash('error', $e->getMessage());
    }
}
redirect($redirect);
