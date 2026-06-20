<?php
include '../config/database.php';
$conn = getConnection();


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conn->query("ALTER TABLE buses ADD COLUMN IF NOT EXISTS driver_pin VARCHAR(10) DEFAULT NULL");
$conn->query("ALTER TABLE buses ADD COLUMN IF NOT EXISTS last_updated TIMESTAMP NULL DEFAULT NULL");
$conn->query("ALTER TABLE buses ADD COLUMN IF NOT EXISTS driver_phone VARCHAR(20) DEFAULT NULL");
$noPin = $conn->query("SELECT id FROM buses WHERE driver_pin IS NULL OR driver_pin = ''");
while ($row = $noPin->fetch_assoc()) {
    $pin = (string) random_int(1000, 9999);
    $upd = $conn->prepare("UPDATE buses SET driver_pin = ? WHERE id = ?");
    $upd->bind_param("si", $pin, $row['id']);
    $upd->execute();
}
$result = $conn->query("SELECT * FROM buses");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Manage Buses</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

    <style>
        body {
            background: #f4f6f8;
            min-height: 100vh;
            padding: 2rem 1rem;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .card {
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 0 25px rgba(0,0,0,0.15);
            max-width: 900px;
            width: 100%;
            padding: 2rem 2.5rem;
        }
        h2 {
            font-weight: 700;
            color: #4a3f7d;
            margin-bottom: 1.5rem;
            font-size: 1.8rem;
        }
        .btn-primary, .btn-outline-primary, .btn-secondary, .btn-danger {
            border-radius: 50px;
            font-weight: 600;
        }
        .btn-primary {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .btn-outline-primary {
            color: #764ba2;
            border-color: #764ba2;
        }
        .btn-outline-primary:hover {
            background-color: #764ba2;
            color: #fff;
        }
        .btn-secondary {
            background-color: #6c757d;
            border: none;
            color: #fff;
        }
        .btn-danger {
            background-color: #d63031;
            border: none;
            color: #fff;
        }
        .btn i {
            margin-right: 6px;
        }
        .table thead {
            background-color: #764ba2;
            color: white;
            font-weight: 600;
        }
        .table-hover tbody tr:hover {
            background-color: #f2e9ff;
        }
    </style>
</head>
<body>

<div class="card">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4">
        <h2>Manage Buses</h2>
        <div>
            <a href="dashboard.php" class="btn btn-outline-primary me-2 mb-2 mb-md-0">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <a href="add_bus.php" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Add Bus
            </a>
            <a href="driver_links.php" class="btn btn-success ms-2">
                <i class="fas fa-qrcode"></i> Driver Links
            </a>
        </div>
    </div>

    <!-- Show alert if deletion fails due to existing bookings -->
    <?php if (isset($_GET['error']) && $_GET['error'] == 'booking_exists'): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>❌ Cannot delete!</strong> This bus has bookings and cannot be deleted.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif (isset($_GET['success']) && $_GET['success'] == 'deleted'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            ✅ Bus deleted successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-bordered table-hover text-center mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Bus Name</th>
                    <th>Type</th>
                    <th>Driver PIN</th>
                    <th>Driver Phone</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($bus = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $bus['id'] ?></td>
                            <td><?= htmlspecialchars($bus['bus_name']) ?></td>
                            <td><?= htmlspecialchars($bus['bus_type']) ?></td>
                            <td><span class="badge bg-dark" style="font-size:13px;letter-spacing:1px;"><?= htmlspecialchars($bus['driver_pin'] ?? '----') ?></span></td>
                            <td><?= !empty($bus['driver_phone']) ? htmlspecialchars($bus['driver_phone']) : '<span class="text-muted">Not set</span>' ?></td>
                            <td>
                                <a href="edit_bus.php?id=<?= $bus['id'] ?>" class="btn btn-sm btn-secondary me-2 mb-1 mb-md-0">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="delete_bus.php?id=<?= $bus['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure to delete this bus?');">
                                    <i class="fas fa-trash-alt"></i> Delete
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted">No buses found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
