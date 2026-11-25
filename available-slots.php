<?php
require_once __DIR__ . '/includes/app.php';
header('Content-Type: application/json; charset=utf-8');
$date = $_GET['date'] ?? '';
$parsed = DateTime::createFromFormat('Y-m-d', $date);
if (!$parsed || $parsed->format('Y-m-d') !== $date || $date < date('Y-m-d') || $date > date('Y-m-d', strtotime('+6 months'))) {
    http_response_code(422);
    echo json_encode(['error' => 'Choose a date within the next six months.']);
    exit;
}
if ((int)$parsed->format('N') < 2 || (int)$parsed->format('N') > 6) {
    echo json_encode(['closed' => true, 'booked' => []]);
    exit;
}
$stmt = db()->prepare("SELECT TIME_FORMAT(appointment_time,'%H:%i') FROM appointments WHERE appointment_date=? AND status <> 'cancelled'");
$stmt->execute([$date]);
$booked = $stmt->fetchAll(PDO::FETCH_COLUMN);
if ($date === date('Y-m-d')) {
    foreach (['09:00','10:30','12:00','13:30','15:00','16:30'] as $time) {
        if ($time . ':00' <= date('H:i:s')) {
            $booked[] = $time;
        }
    }
}
echo json_encode(['closed' => false, 'booked' => $booked]);
