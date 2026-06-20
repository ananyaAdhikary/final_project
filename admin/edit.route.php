<?php

include '../config/database.php';
$conn = getConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: manage_route.php");
    exit();
}

// রুটের ডেটা নিয়ে আসা
$result = $conn->query("SELECT * FROM routes WHERE id = $id");
$route = $result->fetch_assoc();

if (!$route) {
    echo "Route not found!";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $route_name = $_POST['route_name'] ?? '';
    $from_city = $_POST['from_city'] ?? '';
    $to_city = $_POST['to_city'] ?? '';
    $distance = intval($_POST['distance'] ?? 0);
    $duration = $_POST['duration'] ?? '';
    $fare = floatval($_POST['fare'] ?? 0);

    $stmt = $conn->prepare("UPDATE routes SET route_name=?, from_city=?, to_city=?, distance=?, duration=?, fare=? WHERE id=?");
    $stmt->bind_param("sssissi", $route_name, $from_city, $to_city, $distance, $duration, $fare, $id);
    $stmt->execute();

    header("Location: manage_route.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Route</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-5">
    <h2>Edit Route</h2>
    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Route Name</label>
            <input type="text" name="route_name" class="form-control" value="<?= htmlspecialchars($route['route_name']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">From City</label>
            <input type="text" name="from_city" class="form-control" value="<?= htmlspecialchars($route['from_city']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">To City</label>
            <input type="text" name="to_city" class="form-control" value="<?= htmlspecialchars($route['to_city']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Distance (km)</label>
            <input type="number" name="distance" class="form-control" value="<?= htmlspecialchars($route['distance']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Duration (e.g. 3h 30m)</label>
            <input type="text" name="duration" class="form-control" value="<?= htmlspecialchars($route['duration']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Fare</label>
            <input type="number" step="0.01" name="fare" class="form-control" value="<?= htmlspecialchars($route['fare']) ?>" required>
        </div>
        <button type="submit" class="btn btn-primary">Update Route</button>
    </form>
</div>
</body>
</html>
