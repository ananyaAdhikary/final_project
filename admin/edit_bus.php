<?php

include '../config/database.php';
$conn = getConnection();

// Safe migration: driver phone number
$conn->query("ALTER TABLE buses ADD COLUMN IF NOT EXISTS driver_phone VARCHAR(20) DEFAULT NULL");

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Get bus ID from URL
$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: manage_bus.php");
    exit();
}

// Fetch existing bus info using prepared statement
$stmt = $conn->prepare("SELECT * FROM buses WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$bus = $result->fetch_assoc();

if (!$bus) {
    echo "Bus not found.";
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['bus_name'];
    $type = $_POST['bus_type'];
    $status = $_POST['status'];
    $driver_phone = trim($_POST['driver_phone'] ?? '');

    $stmt = $conn->prepare("UPDATE buses SET bus_name = ?, bus_type = ?, status = ?, driver_phone = ? WHERE id = ?");
    $stmt->bind_param("ssssi", $name, $type, $status, $driver_phone, $id);
    $stmt->execute();

    header("Location: manage_bus.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Bus</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-5">
    <h2>Edit Bus</h2>
    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Bus Name</label>
            <input type="text" name="bus_name" class="form-control" value="<?= htmlspecialchars($bus['bus_name']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Type</label>
            <input type="text" name="bus_type" class="form-control" value="<?= htmlspecialchars($bus['bus_type']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Driver's Phone Number</label>
            <input type="text" name="driver_phone" class="form-control" value="<?= htmlspecialchars($bus['driver_phone'] ?? '') ?>" placeholder="Eg: 01712345678">
        </div>
        <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
                <option value="active" <?= $bus['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $bus['status'] == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                <option value="maintenance" <?= $bus['status'] == 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Update Bus</button>
    </form>
</div>
</body>
</html>
