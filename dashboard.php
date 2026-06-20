<?php
require_once 'config/database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$conn = getConnection();

// Get user's bookings
$bookings_query = "SELECT b.*, bus.bus_name, bus.bus_number, r.route_name, r.from_city, r.to_city 
                   FROM bookings b 
                   JOIN buses bus ON b.bus_id = bus.id 
                   JOIN routes r ON b.route_id = r.id 
                   WHERE b.user_id = ? 
                   ORDER BY b.created_at DESC";
$stmt = $conn->prepare($bookings_query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$bookings_result = $stmt->get_result();

// Get booking statistics
$stats_query = "SELECT 
                    COUNT(*) as total_bookings,
                    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
                    SUM(total_fare) as total_spent
                FROM bookings 
                WHERE user_id = ?";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("i", $_SESSION['user_id']);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - BD Bus Track</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background:#0f172a"">
        <div class="container">
            <a class="navbar-brand" href="index.html">
                <i class="fas fa-bus"></i> BD Bus Track
            </a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="booking.php">Book Ticket</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="tracking.php">
    <i class="fas fa-satellite-dish"></i> Live Tracking
</a>
                    </li>
                </ul>
                <div class="navbar-nav">
                    <span class="navbar-text me-3">Welcome, <?php echo $_SESSION['user_name']; ?></span>
                    <a class="nav-link" href="logout.php">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2><i class="fas fa-tachometer-alt"></i> Dashboard</h2>
        
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $stats['total_bookings'] ?: 0; ?></h4>
                                <p>Total Bookings</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-ticket-alt fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $stats['confirmed_bookings'] ?: 0; ?></h4>
                                <p>Confirmed Trips</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-check-circle fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4>৳<?php echo number_format($stats['total_spent'] ?: 0); ?></h4>
                                <p>Total Spent</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-money-bill fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
     <!-- Enhanced Feature Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <i class="fas fa-ticket-alt fa-3x text-primary mb-3"></i>
                <h5 class="card-title">Book Ticket</h5>
                <p class="card-text">Book your bus tickets online</p>
                <a href="booking.php" class="btn btn-success">
                    <i class="fas fa-plus"></i> Book Now
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <i class="fas fa-satellite-dish fa-3x text-success mb-3"></i>
                <h5 class="card-title">Real-Time Tracking</h5>
                <p class="card-text">Track buses with live GPS locations</p>
                 <a href="tracking.php" class="btn btn-success">
                       <i class="fas fa-map-marker-alt"></i> Start Tracking
                      </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <i class="fas fa-route fa-3x text-info mb-3"></i>
                <h5 class="card-title">View Routes</h5>
                <p class="card-text">Explore available bus routes</p>
                <a href="routes.php" class="btn btn-info">
                    <i class="fas fa-eye"></i> View Routes
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <i class="fas fa-user fa-3x text-warning mb-3"></i>
                <h5 class="card-title">My Profile</h5>
                <p class="card-text">Manage your account settings</p>
                <a href="profile.php" class="btn btn-warning">
                    <i class="fas fa-cog"></i> Manage Profile
                </a>
            </div>
        </div>
    </div>
</div>

        <!-- Recent Bookings -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-history"></i> Recent Bookings</h5>
            </div>
            <div class="card-body">
                <?php if ($bookings_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Booking ID</th>
                                <th>Route</th>
                                <th>Bus</th>
                                <th>Journey Date</th>
                                <th>Seats</th>
                                <th>Fare</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($booking = $bookings_result->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $booking['id']; ?></td>
                                <td><?php echo $booking['from_city'] . ' → ' . $booking['to_city']; ?></td>
                                <td>
                                    <?php echo $booking['bus_name']; ?><br>
                                    <small class="text-muted"><?php echo $booking['bus_number']; ?></small>
                                </td>
                                <td><?php echo date('d M Y', strtotime($booking['journey_date'])); ?></td>
                                <td><?php echo $booking['seat_numbers']; ?></td>
                                <td>৳<?php echo number_format($booking['total_fare']); ?></td>
                                <td>
                                    <?php
                                    $status_class = '';
                                    switch($booking['status']) {
                                        case 'confirmed': $status_class = 'bg-success'; break;
                                        case 'cancelled': $status_class = 'bg-danger'; break;
                                        case 'completed': $status_class = 'bg-info'; break;
                                    }
                                    ?>
                                    <span class="badge <?php echo $status_class; ?>">
                                        <?php echo ucfirst($booking['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="ticket.php?id=<?php echo $booking['id']; ?>" class="btn btn-sm btn-outline-success" title="View Ticket">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="#" class="btn btn-sm btn-outline-secondary" title="Print Ticket"
                                       onclick="var w=window.open('ticket.php?id=<?php echo $booking['id']; ?>','_blank');w.addEventListener('load',function(){w.print();});return false;">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    <?php if ($booking['status'] == 'confirmed' && strtotime($booking['journey_date']) > time()): ?>
                                    <button class="btn btn-sm btn-outline-danger" onclick="cancelBooking(<?php echo $booking['id']; ?>)">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-ticket-alt fa-3x text-muted mb-3"></i>
                    <h5>No bookings yet</h5>
                    <p class="text-muted">Start by booking your first ticket!</p>
                    <a href="booking.php" class="btn btn-success">Book Now</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewBooking(bookingId) {
            window.location.href = 'ticket.php?id=' + bookingId;
        }

        function cancelBooking(bookingId) {
            if (confirm('Are you sure you want to cancel this booking?')) {
                // Implement booking cancellation
                window.location.href = 'cancel_booking.php?id=' + bookingId;
            }
        }
    </script>
</body>
</html>