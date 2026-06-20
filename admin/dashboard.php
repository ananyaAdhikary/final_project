<?php
include '../config/database.php';
$conn = getConnection();


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$stats = [
    'total_buses'      => 0,
    'available_buses'  => 0,
    'total_routes'     => 0,
    'available_routes' => 0,
    'today_bookings'   => 0,
    'total_bookings'   => 0,
    'today_revenue'    => 0,
    'total_revenue'    => 0,
];

date_default_timezone_set('Asia/Dhaka'); 
$today = date('Y-m-d');

$result = $conn->query("SELECT COUNT(*) as total FROM buses");
$stats['total_buses'] = $result ? $result->fetch_assoc()['total'] : 0;

$result = $conn->query("SELECT COUNT(*) as available FROM buses WHERE status = 'active'");
$stats['available_buses'] = $result ? $result->fetch_assoc()['available'] : 0;

$result = $conn->query("SELECT COUNT(*) as total FROM routes");
$stats['total_routes'] = $result ? $result->fetch_assoc()['total'] : 0;

$result = $conn->query("SELECT COUNT(*) as available FROM routes");
$stats['available_routes'] = $result ? $result->fetch_assoc()['available'] : 0;

$result = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE booking_date = '$today'");
$stats['today_bookings'] = $result ? $result->fetch_assoc()['count'] : 0;

$result = $conn->query("SELECT COUNT(*) as total FROM bookings");
$stats['total_bookings'] = $result ? $result->fetch_assoc()['total'] : 0;

$result = $conn->query("SELECT SUM(total_fare) as revenue FROM bookings WHERE DATE(booking_date) = '$today'");
$stats['today_revenue'] = $result ? ($result->fetch_assoc()['revenue'] ?: 0) : 0;

$result = $conn->query("SELECT SUM(total_fare) as revenue FROM bookings");
$stats['total_revenue'] = $result ? ($result->fetch_assoc()['revenue'] ?: 0) : 0;

// Fetch recent bookings
$recent_bookings_query = "
    SELECT b.*,
           COALESCE(u.name, b.passenger_name, 'Guest') as user_name,
           bus.bus_name, r.route_name, r.from_city, r.to_city
    FROM bookings b
    LEFT JOIN users u   ON b.user_id = u.id
    JOIN buses bus      ON b.bus_id = bus.id
    JOIN routes r       ON b.route_id = r.id
    ORDER BY b.created_at DESC
    LIMIT 10
";
$recent_bookings_result = $conn->query($recent_bookings_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
:root {
  --primary: #16a34a;
  --primary-dark: #15803d;
  --primary-light: #dcfce7;
  --dark: #0f172a;
  --bg: #f8fafc;
  --border: #e5e7eb;
  --text: #111827;
  --muted: #6b7280;
}
* { box-sizing: border-box; }
body { font-family: 'Inter', sans-serif !important; background: var(--bg); color: var(--text); margin: 0; }

/* Navbar */
.topnav {
  background: #15803d;
  padding: 0 28px; height: 62px;
  display: flex; align-items: center;
  box-shadow: 0 2px 12px rgba(15,23,42,.4);
}
.brand { font-size: 20px; font-weight: 900; color: white; text-decoration: none; letter-spacing: -.3px; }
.brand span { color: rgba(255,255,255,.45); font-weight: 400; }
.nav-a { color: rgba(255,255,255,.65); text-decoration: none; font-size: 13px; font-weight: 600; padding: 6px 12px; border-radius: 7px; transition: all .15s; }
.nav-a:hover { color: white; background: rgba(255,255,255,.12); }

/* Page heading */
.page-head { max-width: 1100px; margin: 28px auto 18px; padding: 0 20px; }
.page-title { font-size: 24px; font-weight: 900; color: var(--text); letter-spacing: -.5px; }
.page-sub { font-size: 13px; color: var(--muted); margin-top: 2px; }

.main-wrap { max-width: 1100px; margin: 0 auto 50px; padding: 0 20px; min-height: 50vh; }

/* Stat cards */
.stat-card-wrap { display: flex; flex-direction: column; height: 100%; }
.row.align-items-stretch > .col { display: flex; }
.stat-card {
  border-radius: 14px 14px 0 0; border: none; padding: 18px 20px;
  display: flex; align-items: center; justify-content: space-between;
  color: white; position: relative; overflow: hidden;
  flex: 1; min-height: 92px;
}
.stat-card.standalone { border-radius: 14px; flex: 1; min-height: 100%; }
.stat-card .stat-icon { font-size: 26px; opacity: .85; }
.stat-card h4 { font-size: 24px; font-weight: 900; margin: 0 0 2px; }
.stat-card p { font-size: 12px; font-weight: 600; margin: 0; opacity: .9; text-transform: uppercase; letter-spacing: .4px; }
.stat-card small { font-size: 11px; opacity: .75; }
.stat-card-footer {
  background: rgba(255,255,255,.12);
  text-align: center; padding: 8px;
  font-size: 12px; font-weight: 700; color: white; text-decoration: none; display: block;
  transition: background .15s;
}
.stat-card-footer:hover { background: rgba(255,255,255,.22); color: white; }

/* Section cards */
.section-card {
  background: white; border-radius: 14px; border: 1.5px solid var(--border);
  margin-bottom: 24px; box-shadow: 0 2px 8px rgba(0,0,0,.04);
}
.section-head { border-radius: 14px 14px 0 0; }
.section-card .table-responsive { border-radius: 0 0 14px 14px; overflow: hidden; }
.section-head { background: var(--primary); padding: 14px 20px; display: flex; align-items: center; gap: 10px; }
.section-head h5 { color: white; font-size: 14px; font-weight: 800; margin: 0; letter-spacing: .2px; }
.section-head i { color: rgba(255,255,255,.85); font-size: 15px; }
.section-body { padding: 20px; }

/* Quick action buttons */
.qa-btn {
  background: var(--primary); color: white; border: none; border-radius: 10px;
  padding: 13px; font-family: 'Inter', sans-serif; font-weight: 700; font-size: 14px;
  width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px;
  transition: all .15s; cursor: pointer;
}
.qa-btn:hover { background: var(--primary-dark); }
.qa-btn.outline { background: white; color: var(--primary); border: 1.5px solid var(--primary); }
.qa-btn.outline:hover { background: var(--primary-light); }
.qa-btn.dark { background: var(--dark); }
.qa-btn.dark:hover { background: #1e293b; }
.dropdown-menu { border-radius: 10px; border: 1.5px solid var(--border); padding: 6px; z-index: 1050; }
.dropdown { position: relative; }
.dropdown-item { border-radius: 7px; font-weight: 600; font-size: 13px; padding: 9px 12px; }
.dropdown-item:hover { background: var(--primary-light); color: var(--primary-dark); }

/* Table */
.table thead th {
  background: var(--bg); color: var(--muted); font-size: 11px; font-weight: 700;
  text-transform: uppercase; letter-spacing: .5px; border-color: var(--border);
  padding: 12px 14px;
}
.table tbody td { padding: 12px 14px; font-size: 13px; vertical-align: middle; border-color: #f1f5f9; }
.table tbody tr:hover td { background: #f0fdf4; }
.badge { border-radius: 20px !important; font-weight: 700 !important; font-size: 11px !important; padding: 5px 11px !important; }
.btn-outline-primary { color: var(--primary) !important; border-color: var(--primary) !important; border-radius: 8px !important; font-weight: 700 !important; }
.btn-outline-primary:hover { background: var(--primary) !important; color: white !important; }
.btn-outline-danger { border-radius: 8px !important; font-weight: 700 !important; }

footer { background: var(--dark) !important; }
</style>
</head>
<body>
<nav class="topnav">
    <a href="dashboard.php" class="brand">BD Bus <span>Track</span></a>
    <div style="margin-left:auto;display:flex;align-items:center;gap:6px;">
        <span style="color:rgba(255,255,255,.65);font-size:13px;font-weight:600;margin-right:6px;">
            <i class="fas fa-user-shield me-1"></i><?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'Admin'; ?>
        </span>
        <a class="nav-a" href="../tracking.php"><i class="fas fa-map-marker-alt me-1"></i>Live Tracking</a>
        <a class="nav-a" href="profile.php"><i class="fas fa-user me-1"></i>Profile</a>
        <a class="nav-a" href="../logout.php" style="border:1px solid rgba(255,255,255,.2);border-radius:8px;">Logout</a>
    </div>
</nav>

<div class="page-head">
    <div class="page-title">Admin Dashboard</div>
    <div class="page-sub">Overview of buses, routes, bookings and revenue</div>
</div>
<div class="main-wrap">
    <!-- Dashboard Stats -->
    <div class="row row-cols-2 row-cols-lg-6 g-3 mb-4 align-items-stretch">
        <!-- Total Buses -->
        <div class="col">
            <div class="stat-card-wrap">
                <div class="stat-card" style="background:linear-gradient(135deg,#16a34a,#22c55e);">
                    <div>
                        <h4><?php echo $stats['total_buses']; ?></h4>
                        <p>Total Buses</p>
                        <small>Available: <?php echo $stats['available_buses']; ?></small>
                    </div>
                    <i class="fas fa-bus stat-icon"></i>
                </div>
                <a href="all_buses.php" class="stat-card-footer" style="background:#15803d;border-radius:0 0 14px 14px;">Show All Buses</a>
            </div>
        </div>

        <!-- Total Routes -->
        <div class="col">
            <div class="stat-card-wrap">
                <div class="stat-card" style="background:linear-gradient(135deg,#0f172a,#1e293b);">
                    <div>
                        <h4><?php echo $stats['total_routes']; ?></h4>
                        <p>Total Routes</p>
                        <small>Available: <?php echo $stats['available_routes']; ?></small>
                    </div>
                    <i class="fas fa-route stat-icon"></i>
                </div>
                <a href="all_routes.php" class="stat-card-footer" style="background:#1e293b;border-radius:0 0 14px 14px;">Show All Routes</a>
            </div>
        </div>

        <!-- Today's Bookings -->
        <div class="col">
            <div class="stat-card-wrap">
                <div class="stat-card" style="background:linear-gradient(135deg,#0ea5e9,#38bdf8);">
                    <div>
                        <h4><?php echo $stats['today_bookings']; ?></h4>
                        <p>Today's Bookings</p>
                    </div>
                    <i class="fas fa-ticket-alt stat-icon"></i>
                </div>
                <a href="todays_bookings.php" class="stat-card-footer" style="background:#0284c7;border-radius:0 0 14px 14px;">Show Today's Bookings</a>
            </div>
        </div>

        <!-- Total Bookings -->
        <div class="col">
            <div class="stat-card-wrap">
                <div class="stat-card" style="background:linear-gradient(135deg,#7c3aed,#a78bfa);">
                    <div>
                        <h4><?php echo $stats['total_bookings']; ?></h4>
                        <p>Total Bookings</p>
                    </div>
                    <i class="fas fa-list stat-icon"></i>
                </div>
                <a href="total_bookings.php" class="stat-card-footer" style="background:#6d28d9;border-radius:0 0 14px 14px;">Show Total Bookings</a>
            </div>
        </div>

        <!-- Today's Revenue -->
        <div class="col">
            <div class="stat-card-wrap">
                <div class="stat-card standalone" style="background:linear-gradient(135deg,#f97316,#fb923c);">
                    <div>
                        <h4>৳<?php echo number_format($stats['today_revenue']); ?></h4>
                        <p>Today's Revenue</p>
                    </div>
                    <i class="fas fa-money-bill-wave stat-icon"></i>
                </div>
            </div>
        </div>

        <!-- Total Revenue -->
        <div class="col">
            <div class="stat-card-wrap">
                <div class="stat-card standalone" style="background:linear-gradient(135deg,#15803d,#16a34a);">
                    <div>
                        <h4>৳<?php echo number_format($stats['total_revenue']); ?></h4>
                        <p>Total Revenue</p>
                    </div>
                    <i class="fas fa-coins stat-icon"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="section-card">
        <div class="section-head">
            <i class="fas fa-bolt"></i>
            <h5>Quick Actions</h5>
        </div>
        <div class="section-body">
            <div class="row g-3">
                <!-- Bus Dropdown -->
                <div class="col-md-4">
                    <div class="dropdown">
                        <button class="qa-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-bus"></i> Bus
                        </button>
                        <ul class="dropdown-menu w-100">
                            <li><a class="dropdown-item" href="add_bus.php"><i class="fas fa-plus me-2" style="color:#16a34a"></i>Add Bus</a></li>
                            <li><a class="dropdown-item" href="manage_bus.php"><i class="fas fa-cog me-2" style="color:#16a34a"></i>Manage Buses</a></li>
                            <li><a class="dropdown-item" href="driver_links.php"><i class="fas fa-qrcode me-2" style="color:#16a34a"></i>Driver Links</a></li>
                        </ul>
                    </div>
                </div>

                <!-- Route Dropdown -->
                <div class="col-md-4">
                    <div class="dropdown">
                        <button class="qa-btn dark dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-route"></i> Route
                        </button>
                        <ul class="dropdown-menu w-100">
                            <li><a class="dropdown-item" href="add_route.php"><i class="fas fa-plus me-2" style="color:#16a34a"></i>Add Route</a></li>
                            <li><a class="dropdown-item" href="manage_route.php"><i class="fas fa-cog me-2" style="color:#16a34a"></i>Manage Routes</a></li>
                        </ul>
                    </div>
                </div>

                <!-- Manage Profile -->
                <div class="col-md-4">
                    <a href="profile.php" class="qa-btn outline">
                        <i class="fas fa-user"></i> Manage Profile
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Bookings Table -->
    <div class="section-card">
        <div class="section-head">
            <i class="fas fa-clock-rotate-left"></i>
            <h5>Recent Bookings</h5>
        </div>
        <div class="table-responsive">
        <table class="table align-middle mb-0">
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
                <?php if ($recent_bookings_result && $recent_bookings_result->num_rows > 0): ?>
                    <?php while ($row = $recent_bookings_result->fetch_assoc()): ?>
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
                                <a href="../ticket.php?id=<?php echo $row['id']; ?>&from=admin" target="_blank" class="btn btn-sm btn-outline-primary mb-1" title="View Ticket"><i class="fas fa-eye"></i></a>
                                <?php if ($row['status']==='confirmed'): ?>
                                <a href="cancel_booking.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-danger mb-1" title="Cancel" onclick="return confirm('Cancel booking #<?php echo $row['id']; ?>?')"><i class="fas fa-times"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="text-center">No recent bookings found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
<!-- Footer -->
<footer class="text-white py-5 mt-4">
    <div class="container" style="max-width:1100px;">
        <div class="row text-center text-md-start justify-content-between">
            <!-- Left Side -->
            <div class="col-md-6 mb-4 mb-md-0">
                <h5 style="font-weight:800;margin-bottom:10px;">Bus Management System</h5>
                <p class="mb-1" style="opacity:.65;font-size:13px;">Admin Dashboard v1.0</p>
                <p class="mb-0" style="opacity:.5;font-size:12px;">&copy; <?php echo date("Y"); ?> All rights reserved.</p>
            </div>

            <!-- Right Side -->
            <div class="col-md-6">
                <h5 style="font-weight:800;margin-bottom:10px;">Contact</h5>
                <p class="mb-1" style="opacity:.65;font-size:13px;">Email: admin@busapp.com</p>
                <p class="mb-0" style="opacity:.65;font-size:13px;">Phone: +880 1234 567890</p>
            </div>
        </div>
    </div>
</footer>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
