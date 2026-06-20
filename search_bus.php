<?php
require_once 'config/database.php';
$conn = getConnection();

$from = isset($_GET['from']) ? sanitize($_GET['from']) : '';
$to   = isset($_GET['to'])   ? sanitize($_GET['to'])   : '';
$date = isset($_GET['journey_date']) ? $_GET['journey_date'] : date('Y-m-d');
$bus_type = isset($_GET['bus_type']) ? sanitize($_GET['bus_type']) : '';
$passengers = intval($_GET['passengers'] ?? 1);

if (!$from || !$to) { redirect('index.html'); }

$sql = "SELECT b.*, r.route_name, r.from_city, r.to_city, r.fare, r.id as route_id FROM buses b JOIN routes r ON b.route_id=r.id WHERE r.from_city=? AND r.to_city=? AND b.status='active'";
$params = [$from, $to]; $types = "ss";
if ($bus_type) { $sql .= " AND b.bus_type=?"; $params[] = $bus_type; $types .= "s"; }

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Available Buses - BD Bus Track</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-dark" style="background:linear-gradient(135deg,#0d6efd,#0a58ca)">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.html"><i class="fas fa-bus me-2"></i>BD Bus Track</a>
        <div class="navbar-nav ms-auto d-flex flex-row gap-2">
            <?php if(isRegisteredUser()): ?>
            <a href="dashboard.php" class="nav-link text-white">Dashboard</a>
            <?php else: ?>
            <a href="guest_booking.php" class="nav-link text-white">Guest Booking</a>
            <a href="login.php" class="nav-link text-white">Login</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <div class="d-flex align-items-center mb-3">
        <a href="index.html" class="btn btn-outline-secondary btn-sm me-3">← Back</a>
        <h4 class="mb-0">
            <i class="fas fa-route text-primary me-2"></i>
            <?= $from ?> → <?= $to ?>
            <?php if($bus_type): ?><span class="badge bg-primary ms-2"><?=$bus_type?></span><?php endif; ?>
        </h4>
    </div>

    <p class="text-muted">Journey: <?= date('d M Y', strtotime($date)) ?> | <?= $passengers ?> Passenger(s)</p>

    <?php if($result->num_rows > 0): ?>
    <div class="row">
    <?php while($bus = $result->fetch_assoc()): ?>
    <div class="col-12 mb-3">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-3">
                        <h5 class="fw-bold mb-0"><?= $bus['bus_name'] ?></h5>
                        <small class="text-muted"><?= $bus['bus_number'] ?></small>
                    </div>
                    <div class="col-md-2">
                        <span class="badge <?= $bus['bus_type']=='AC'?'bg-primary':'bg-secondary' ?> fs-6"><?= $bus['bus_type'] ?></span>
                    </div>
                    <div class="col-md-2">
                        <small class="text-muted">Departs</small>
                        <div class="fw-semibold"><?= date('h:i A', strtotime($bus['departure_time'])) ?></div>
                    </div>
                    <div class="col-md-2">
                        <small class="text-muted">Arrives</small>
                        <div class="fw-semibold"><?= date('h:i A', strtotime($bus['arrival_time'])) ?></div>
                    </div>
                    <div class="col-md-2">
                        <small class="text-muted">Total Fare</small>
                        <div class="text-success fw-bold fs-5">৳<?= $bus['fare'] * $passengers ?></div>
                        <small class="text-muted">৳<?= $bus['fare'] ?>/seat</small>
                    </div>
                    <div class="col-md-1">
                        <?php
                        $book_url = isRegisteredUser()
                            ? "booking.php?route_id={$bus['route_id']}&bus_type={$bus['bus_type']}"
                            : "guest_booking.php?route_id={$bus['route_id']}&bus_type={$bus['bus_type']}&from_city=$from&to_city=$to";
                        ?>
                        <a href="<?= $book_url ?>" class="btn btn-success">Book</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endwhile; ?>
    </div>
    <?php else: ?>
    <div class="alert alert-warning text-center">
        <i class="fas fa-search fa-2x mb-2 d-block"></i>
        <strong>No buses found</strong> for <?= $from ?> → <?= $to ?>
        <?= $bus_type ? "($bus_type)" : "" ?>
        <br><a href="index.html" class="btn btn-primary mt-2">Search Again</a>
    </div>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
