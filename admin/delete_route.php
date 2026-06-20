<?php

include '../config/database.php';
$conn = getConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : null;

if ($id) {
    // Check if there are bookings for this route
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM bookings WHERE route_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($booking_count);
    $stmt->fetch();
    $stmt->close();

    // Check if any buses are assigned to this route
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM buses WHERE route_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($buses_count);
    $stmt->fetch();
    $stmt->close();

    if ($booking_count > 0) {
        $_SESSION['alert'] = ['type' => 'danger', 'message' => '❌ Cannot delete: Route has existing bookings.'];
    } elseif ($buses_count > 0) {
        $_SESSION['alert'] = ['type' => 'danger', 'message' => '❌ Cannot delete: Route has assigned buses.'];
    } else {
        $stmt = $conn->prepare("DELETE FROM routes WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $_SESSION['alert'] = ['type' => 'success', 'message' => '✅ Route deleted successfully.'];
        } else {
            $_SESSION['alert'] = ['type' => 'danger', 'message' => '❌ Error deleting route: ' . $stmt->error];
        }
        $stmt->close();
    }
} else {
    $_SESSION['alert'] = ['type' => 'danger', 'message' => 'Invalid route ID.'];
}

// ✅ Redirect to manage_route.php 
header("Location: manage_route.php");
exit();
