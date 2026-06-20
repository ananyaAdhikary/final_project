<?php
require_once 'config/database.php';
header('Content-Type: application/json');
$id = intval($_GET['id'] ?? 0);
if (!$id) { echo json_encode(['error' => 'Invalid booking ID']); exit; }
$conn = getConnection();
$stmt = $conn->prepare("SELECT bk.*, r.from_city, r.to_city FROM bookings bk JOIN routes r ON bk.route_id=r.id WHERE bk.id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if (!$row) { echo json_encode(['error' => 'Booking #' . $id . ' not found']); exit; }
echo json_encode([
    'id' => $row['id'],
    'passenger_name' => $row['passenger_name'],
    'from_city' => $row['from_city'],
    'to_city' => $row['to_city'],
    'journey_date' => date('d M Y', strtotime($row['journey_date'])),
    'seat_numbers' => $row['seat_numbers'],
    'total_fare' => number_format($row['total_fare']),
    'status' => $row['status'],
]);
