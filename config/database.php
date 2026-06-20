<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'bus_tracking_bd');

function getConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);
    return $conn;
}

session_start();

function isLoggedIn()      { return isset($_SESSION['user_id']) || isset($_SESSION['guest']); }
function isRegisteredUser(){ return isset($_SESSION['user_id']); }
function isGuest()         { return isset($_SESSION['guest']) && !isset($_SESSION['user_id']); }
function isAdmin()         { return isset($_SESSION['role']) && $_SESSION['role'] === 'admin'; }
function redirect($url)    { header("Location: $url"); exit(); }
function sanitize($data)   { return htmlspecialchars(strip_tags(trim($data))); }

// Use SMTP mailer — Ticket Confirmed
function sendTicketEmail($to_email, $passenger_name, $booking_id, $from_city, $to_city, $journey_date, $seats, $fare, $bus_name, $payment_method = 'cash') {
    require_once __DIR__ . '/mailer.php';
    return sendTicketEmailSMTP($to_email, $passenger_name, $booking_id, $from_city, $to_city, $journey_date, $seats, $fare, $bus_name, $payment_method);
}

// Use SMTP mailer — Ticket Cancelled
function sendCancelEmail($to_email, $passenger_name, $booking_id, $from_city, $to_city, $journey_date, $seats, $refund_amount, $cancel_reason = '') {
    require_once __DIR__ . '/mailer.php';
    return sendCancelEmailSMTP($to_email, $passenger_name, $booking_id, $from_city, $to_city, $journey_date, $seats, $refund_amount, $cancel_reason);
}
?>
