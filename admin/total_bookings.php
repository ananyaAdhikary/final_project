<?php
include '../config/database.php';
$conn = getConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$query = "SELECT b.*, 
           COALESCE(u.name, b.passenger_name, 'Guest') as user_name,
           bus.bus_name, r.route_name, r.from_city, r.to_city
           FROM bookings b
           LEFT JOIN users u ON b.user_id = u.id
           JOIN buses bus ON b.bus_id = bus.id
           JOIN routes r ON b.route_id = r.id
           ORDER BY b.created_at DESC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>All Bookings</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        body {
            background-color: #f4f6f8;
            font-family: 'Inter', Tahoma, Geneva, Verdana, sans-serif;
            padding: 2rem 1rem;
        }
        .card {
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 0 25px rgba(0, 0, 0, 0.15);
            padding: 1.5rem;
            max-width: 1200px;
            margin: auto;
        }
        .card h2 {
            font-size: 1.8rem;
            font-weight: bold;
            color: #333;
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .table th, .table td {
            vertical-align: middle;
        }
        .table thead {
            background: #16a34a;
            color: white;
        }
        .btn-back {
            background: linear-gradient(90deg, #16a34a 0%, #15803d 100%);
            border: none;
            color: #fff !important;
            font-weight: 600;
            border-radius: 50px;
            padding: 0.75rem;
            transition: background-color 0.3s ease, box-shadow 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            width: 100%;
        }
        .btn-back:hover {
            background: linear-gradient(90deg, #15803d 0%, #166534 100%);
            box-shadow: 0 6px 20px rgba(106, 77, 189, 0.6);
            color: #fff !important;
            text-decoration: none;
        }
        .no-bookings {
            text-align: center;
            font-weight: 500;
            padding: 1rem;
        }
    </style>
</head>
<body>
<div class="card">
    <h2>📋 All Bookings</h2>
    <div class="table-responsive">
        <table class="table table-bordered table-striped text-center">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Passenger</th>
                    <th>Bus</th>
                    <th>Route</th>
                    <th>Seats</th>
                    <th>Fare</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td>
                                <?php echo htmlspecialchars($row['user_name']); ?>
                                <?php if (!$row['user_id']): ?><br><span class="badge bg-secondary">Guest</span><?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['bus_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['from_city'].' → '.$row['to_city']); ?></td>
                            <td><?php echo htmlspecialchars($row['seat_numbers']); ?></td>
                            <td>৳<?php echo number_format($row['total_fare']); ?></td>
                            <td>
                                <span class="badge <?php echo $row['status']==='confirmed'?'bg-success':($row['status']==='cancelled'?'bg-danger':'bg-secondary'); ?>">
                                    <?php echo ucfirst($row['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('d M Y', strtotime($row['booking_date'])); ?></td>
                            <td>
                                <a href="../ticket.php?id=<?php echo $row['id']; ?>&from=admin" target="_blank" class="btn btn-sm btn-outline-primary mb-1"><i class="fas fa-eye"></i></a>
                                <?php if ($row['status']==='confirmed'): ?>
                                <a href="cancel_booking.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-danger mb-1" onclick="return confirm('Cancel booking #<?php echo $row['id']; ?>?')"><i class="fas fa-times"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="no-bookings">No bookings found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Back Button -->
    <a href="dashboard.php" class="btn-back mt-3">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
        Back to Dashboard
    </a>
</div>
</body>
</html>
