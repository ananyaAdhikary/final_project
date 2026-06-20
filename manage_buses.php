<?php
// Database connection
$host = "localhost";
$user = "root";
$password = "";
$database = "bus_tracking_bd";

$conn = new mysqli($host, $user, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = "";

// Handle delete request
if (isset($_GET['delete'])) {
    $bus_id = $_GET['delete'];
    $delete_stmt = $conn->prepare("DELETE FROM buses WHERE id = ?");
    $delete_stmt->bind_param("i", $bus_id);
    
    if ($delete_stmt->execute()) {
        $message = "<div class='alert alert-success'>Bus deleted successfully!</div>";
    } else {
        $message = "<div class='alert alert-danger'>Error deleting bus.</div>";
    }
    $delete_stmt->close();
}

// Handle status update
if (isset($_POST['update_status'])) {
    $bus_id = $_POST['bus_id'];
    $new_status = $_POST['status'];
    
    $update_stmt = $conn->prepare("UPDATE buses SET status = ? WHERE id = ?");
    $update_stmt->bind_param("si", $new_status, $bus_id);
    
    if ($update_stmt->execute()) {
        $message = "<div class='alert alert-success'>Bus status updated successfully!</div>";
    } else {
        $message = "<div class='alert alert-danger'>Error updating bus status.</div>";
    }
    $update_stmt->close();
}

// Fetch all buses with route information
$buses_query = "SELECT b.*, r.route_name FROM buses b LEFT JOIN routes r ON b.route_id = r.id ORDER BY b.id DESC";
$buses_result = $conn->query($buses_query);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Buses - BD Bus Track</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f4f6f8;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 2rem;
        }
        .back-btn {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            text-decoration: none;
            margin-bottom: 20px;
            display: inline-block;
        }
        .back-btn:hover {
            background-color: #5a6268;
            color: white;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        .table th {
            background-color: #667eea;
            color: white;
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <a href="dashboard.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-bus"></i> Manage Buses</h2>
            <a href="add_bus.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add New Bus
            </a>
        </div>

        <?= $message ?>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Bus Name</th>
                                <th>Bus Number</th>
                                <th>Route</th>
                                <th>Total Seats</th>
                                <th>Type</th>
                                <th>Departure</th>
                                <th>Arrival</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($buses_result && $buses_result->num_rows > 0): ?>
                                <?php while ($bus = $buses_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $bus['id'] ?></td>
                                    <td><?= htmlspecialchars($bus['bus_name']) ?></td>
                                    <td><?= htmlspecialchars($bus['bus_number']) ?></td>
                                    <td><?= htmlspecialchars($bus['route_name'] ?: 'No Route') ?></td>
                                    <td><?= $bus['total_seats'] ?></td>
                                    <td><?= htmlspecialchars($bus['bus_type']) ?></td>
                                    <td><?= htmlspecialchars($bus['departure_time']) ?></td>
                                    <td><?= htmlspecialchars($bus['arrival_time']) ?></td>
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="bus_id" value="<?= $bus['id'] ?>">
                                            <select name="status" class="form-select form-select-sm status-badge 
                                                <?= $bus['status'] == 'active' ? 'bg-success' : ($bus['status'] == 'inactive' ? 'bg-danger' : 'bg-warning') ?>" 
                                                onchange="this.form.submit()">
                                                <option value="active" <?= $bus['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                                                <option value="inactive" <?= $bus['status'] == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                                <option value="maintenance" <?= $bus['status'] == 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                                            </select>
                                            <input type="hidden" name="update_status" value="1">
                                        </form>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="edit_bus.php?id=<?= $bus['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?delete=<?= $bus['id'] ?>" class="btn btn-sm btn-outline-danger" 
                                               onclick="return confirm('Are you sure you want to delete this bus?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center">No buses found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
