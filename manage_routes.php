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
    $route_id = $_GET['delete'];
    
    // Check if route has buses assigned
    $check_buses = $conn->prepare("SELECT COUNT(*) as count FROM buses WHERE route_id = ?");
    $check_buses->bind_param("i", $route_id);
    $check_buses->execute();
    $result = $check_buses->get_result();
    $bus_count = $result->fetch_assoc()['count'];
    
    if ($bus_count > 0) {
        $message = "<div class='alert alert-warning'>Cannot delete route. There are {$bus_count} buses assigned to this route.</div>";
    } else {
        $delete_stmt = $conn->prepare("DELETE FROM routes WHERE id = ?");
        $delete_stmt->bind_param("i", $route_id);
        
        if ($delete_stmt->execute()) {
            $message = "<div class='alert alert-success'>Route deleted successfully!</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error deleting route.</div>";
        }
        $delete_stmt->close();
    }
    $check_buses->close();
}

// Fetch all routes with bus count
$routes_query = "SELECT r.*, COUNT(b.id) as bus_count 
                 FROM routes r 
                 LEFT JOIN buses b ON r.id = b.route_id 
                 GROUP BY r.id 
                 ORDER BY r.id DESC";
$routes_result = $conn->query($routes_query);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Routes - BD Bus Track</title>
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
    </style>
</head>
<body>
    <div class="container-fluid">
        <a href="dashboard.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-route"></i> Manage Routes</h2>
            <a href="add_route.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add New Route
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
                                <th>Route Name</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Distance (km)</th>
                                <th>Duration</th>
                                <th>Fare (৳)</th>
                                <th>Buses</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($routes_result && $routes_result->num_rows > 0): ?>
                                <?php while ($route = $routes_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $route['id'] ?></td>
                                    <td><?= htmlspecialchars($route['route_name']) ?></td>
                                    <td><?= htmlspecialchars($route['from_city']) ?></td>
                                    <td><?= htmlspecialchars($route['to_city']) ?></td>
                                    <td><?= $route['distance'] ?></td>
                                    <td><?= htmlspecialchars($route['duration']) ?></td>
                                    <td>৳<?= number_format($route['fare'], 2) ?></td>
                                    <td>
                                        <span class="badge bg-info"><?= $route['bus_count'] ?> buses</span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="edit_route.php?id=<?= $route['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?delete=<?= $route['id'] ?>" class="btn btn-sm btn-outline-danger" 
                                               onclick="return confirm('Are you sure you want to delete this route?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center">No routes found.</td>
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
