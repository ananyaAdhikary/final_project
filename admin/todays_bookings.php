<?php

include '../config/database.php';

date_default_timezone_set('Asia/Dhaka');

$conn = getConnection();

// শুধুমাত্র অ্যাডমিন প্রবেশ করতে পারবে
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$today = date('Y-m-d');

// আজকের বুকিং ডেটা (booking_date দিয়ে ফিল্টার)
$query = "SELECT b.*,
           COALESCE(u.name, b.passenger_name, 'Guest') as user_name,
           bus.bus_name, r.route_name, r.from_city, r.to_city
           FROM bookings b
           LEFT JOIN users u ON b.user_id = u.id
           JOIN buses bus ON b.bus_id = bus.id
           JOIN routes r ON b.route_id = r.id
           WHERE b.booking_date = ?
           ORDER BY b.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param('s', $today);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Today's Bookings</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        body {
            background: #f4f6f8;
            padding: 2rem 1rem;
        }

        .card {
            border-radius: 1rem;
            box-shadow: 0 0 25px rgba(0, 0, 0, 0.1);
            background-color: #fff;
            max-width: 1200px;
            margin: auto;
            padding: 1.5rem;
        }

        .card-title {
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .table thead {
            background: #16a34a;
            color: #fff;
        }

        .table th, .table td {
            vertical-align: middle;
            text-align: center;
        }

        .btn-back {
            background: linear-gradient(90deg, #16a34a 0%, #15803d 100%);
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
            background: linear-gradient(90deg, #15803d 0%, #166534 100%);
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
        <div class="card-title">
            📅 Today's Bookings (<?php echo date('d M Y'); ?>)
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-bordered align-middle">
                <thead>
                    <tr>
                        <th>Booking ID</th>
                        <th>User</th>
                        <th>Bus</th>
                        <th>Route</th>
                        <th>Seats</th>
                        <th>Total Fare</th>
                        <th>Booking Date</th>
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
                                <td><span class="badge bg-success">৳<?php echo number_format($row['total_fare']); ?></span></td>
                                <td>
                                    <span class="badge <?php echo $row['status']==='confirmed'?'bg-success':($row['status']==='cancelled'?'bg-danger':'bg-secondary'); ?>">
                                        <?php echo ucfirst($row['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d M Y', strtotime($row['booking_date'])); ?></td>
                                <td>
                                    <a href="../ticket.php?id=<?php echo $row['id']; ?>&from=admin" target="_blank" class="btn btn-sm btn-outline-primary mb-1"><i class="fas fa-eye"></i></a>
                                    <?php if ($row['status']==='confirmed'): ?>
                                    <a href="cancel_booking.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-danger mb-1" onclick="return confirm('Cancel this booking?')"><i class="fas fa-times"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="no-data text-muted">No bookings found for today.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <a href="dashboard.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

</body>
</html>
