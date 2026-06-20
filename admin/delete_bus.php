<?php
include '../config/database.php';
$conn = getConnection();

if (!isset($_SESSION)) session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

if (isset($_GET['id'])) {
    $bus_id = intval($_GET['id']);

    // Check if this bus has any bookings
    $check = $conn->query("SELECT * FROM bookings WHERE bus_id = $bus_id");
    if ($check->num_rows > 0) {
        header("Location: manage_bus.php?error=booking_exists");
        exit();
    }

    // Proceed with delete
    if ($conn->query("DELETE FROM buses WHERE id = $bus_id")) {
        header("Location: manage_bus.php?success=deleted");
    } else {
        header("Location: manage_bus.php?error=delete_failed");
    }
}
?>
