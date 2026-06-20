<?php
include '../config/database.php';
$conn = getConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$all_buses_result = $conn->query("SELECT * FROM buses ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>All Buses</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        body {
            background-color: #f4f6f8;
            padding: 2rem 1rem;
        }

        .card {
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 0 25px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            max-width: 1200px;
            margin: auto;
        }

        h3 {
            font-size: 1.6rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .table thead {
            background: #764ba2;
            color: #fff;
        }

        .table th, .table td {
            vertical-align: middle;
        }

        .btn-back {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: #fff !important;
            font-weight: 600;
            border-radius: 50px;
            padding: 0.75rem;
            width: 100%;
            margin-top: 1.5rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-back:hover {
            background: linear-gradient(90deg, #5a67d8 0%, #6a4dbd 100%);
            box-shadow: 0 6px 20px rgba(106, 77, 189, 0.6);
            text-decoration: none;
            color: #fff !important;
        }

        .no-data {
            text-align: center;
            padding: 1rem;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="card">
        <h3>🚌 All Buses</h3>
        <div class="table-responsive">
            <table class="table table-bordered table-striped text-center">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Bus Name</th>
                        <th>Status</th>
                        <th>Capacity</th>
                        <th>Departure Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($all_buses_result && $all_buses_result->num_rows > 0): ?>
                        <?php while ($bus = $all_buses_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $bus['id']; ?></td>
                                <td><?php echo htmlspecialchars($bus['bus_name']); ?></td>
                                <td>
                                    <?php if ($bus['status'] === 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $bus['total_seats']; ?></td>
                                <td><?php echo date('h:i A', strtotime($bus['departure_time'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="no-data">No buses found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Back Button -->
        <a href="dashboard.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
    

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
