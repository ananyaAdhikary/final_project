<?php
session_start();
require 'db.php';

// যদি ইউজার লগইন না করে থাকে তাহলে লগইন পেজে পাঠিয়ে দাও
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// রুট ডেটা ডাটাবেজ থেকে আনা
$result = $conn->query("SELECT * FROM routes ORDER BY id DESC");

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Total Routes - Admin Panel</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h2 class="mb-4">All Routes</h2>

        <a href="dashboard.php" class="btn btn-secondary mb-3">← Back to Dashboard</a>

        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Route Name</th>
                        <th>Start Point</th>
                        <th>End Point</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($route = $result->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $route['id']; ?></td>
                                <td><?php echo htmlspecialchars($route['route_name']); ?></td>
                                <td><?php echo htmlspecialchars($route['start_point']); ?></td>
                                <td><?php echo htmlspecialchars($route['end_point']); ?></td>
                                <td>
                                    <?php if ($route['status'] === 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center">No routes found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
