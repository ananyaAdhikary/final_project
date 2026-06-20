<?php
require_once 'config/database.php';
if (!isRegisteredUser()) redirect('login.php');
$conn = getConnection();

// Auto-add missing columns (safe migration)
$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS passenger_email VARCHAR(100) DEFAULT NULL");
$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS passenger_count INT DEFAULT 1");
$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS bus_type VARCHAR(10) DEFAULT 'Non-AC'");
$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS payment_method VARCHAR(20) DEFAULT 'cash'");
$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS payment_status VARCHAR(20) DEFAULT 'pending'");
$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS cancel_reason VARCHAR(255) DEFAULT NULL");
$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS refund_amount DECIMAL(10,2) DEFAULT 0");
$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS transaction_id VARCHAR(100) DEFAULT NULL");

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['book_ticket'])) {
    $bus_id         = intval($_POST['bus_id']);
    $route_id       = intval($_POST['route_id']);
    $p_name         = sanitize($_POST['passenger_name']);
    $p_phone        = sanitize($_POST['passenger_phone']);
    $p_email        = sanitize($_POST['passenger_email'] ?? '');
    $p_count        = intval($_POST['passenger_count'] ?? 1);
    $seat_numbers   = sanitize($_POST['seat_numbers']);
    $journey_date   = $_POST['journey_date'];
    $bus_type       = sanitize($_POST['bus_type'] ?? 'Non-AC');
    $payment_method  = sanitize($_POST['payment_method'] ?? 'cash');
    $transaction_id  = sanitize($_POST['transaction_id'] ?? '');

    // Validate required fields
    if (!$bus_id || !$route_id || !$p_name || !$p_phone || !$seat_numbers || !$journey_date) {
        $error = "❌ Please fill in all required fields.";
    } else {
        // Check seat conflicts
        $seat_array = array_map('trim', explode(',', $seat_numbers));
        $conflict = [];
        foreach ($seat_array as $seat) {
            if (!$seat) continue;
            $chk = $conn->prepare("SELECT passenger_name FROM bookings WHERE bus_id=? AND journey_date=? AND status='confirmed' AND FIND_IN_SET(?, REPLACE(seat_numbers,' ','')) > 0");
            if ($chk) {
                $chk->bind_param("iss", $bus_id, $journey_date, $seat);
                $chk->execute();
                if ($chk->get_result()->num_rows > 0) $conflict[] = $seat;
            }
        }

        if ($conflict) {
            $error = "❌ Seats already booked: " . implode(', ', $conflict) . ". Please choose different seats.";
        } else {
            // Get fare
            $fare_stmt = $conn->prepare("SELECT r.fare FROM routes r JOIN buses b ON r.id = b.route_id WHERE b.id = ?");
            if (!$fare_stmt) {
                $error = "❌ Database error: " . $conn->error;
            } else {
                $fare_stmt->bind_param("i", $bus_id);
                $fare_stmt->execute();
                $fare_row = $fare_stmt->get_result()->fetch_assoc();

                if (!$fare_row) {
                    $error = "❌ Could not find bus fare. Please re-select the bus.";
                } else {
                    $total_fare = floatval($fare_row['fare']) * $p_count;

                    // Insert booking
                    $ins = $conn->prepare(
                        "INSERT INTO bookings 
                        (user_id, bus_id, route_id, passenger_name, passenger_phone, passenger_email, passenger_count, seat_numbers, bus_type, total_fare, payment_method, payment_status, booking_date, journey_date, transaction_id) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', CURDATE(), ?, ?)"
                    );

                    if (!$ins) {
                        $error = "❌ Prepare error: " . $conn->error;
                    } else {
                        // 12 params: i i i s s s i s s d s s
                        $ins->bind_param(
                            "iiisssissdsss",
                            $_SESSION['user_id'],
                            $bus_id,
                            $route_id,
                            $p_name,
                            $p_phone,
                            $p_email,
                            $p_count,
                            $seat_numbers,
                            $bus_type,
                            $total_fare,
                            $payment_method,
                            $journey_date,
                            $transaction_id
                        );

                        if ($ins->execute()) {
                            $booking_id = $conn->insert_id;
                            // Send email ticket if email provided
                            if ($p_email) {
                                $info = $conn->query("SELECT b.bus_name, r.from_city, r.to_city FROM buses b JOIN routes r ON b.route_id=r.id WHERE b.id=$bus_id")->fetch_assoc();
                                if ($info) sendTicketEmail($p_email, $p_name, $booking_id, $info['from_city'], $info['to_city'], $journey_date, $seat_numbers, $total_fare, $info['bus_name']);
                            }
                            header("Location: ticket.php?id=" . $booking_id);
                            exit();
                        } else {
                            $error = "❌ Booking failed: " . $ins->error;
                        }
                    }
                }
            }
        }
    }
}

// Get buses for selected route + type filter
$buses_result = null;
if (isset($_GET['route_id'])) {
    $r_id   = intval($_GET['route_id']);
    $b_type = (isset($_GET['bus_type']) && $_GET['bus_type']) ? sanitize($_GET['bus_type']) : null;

    if ($b_type) {
        $bq = $conn->prepare("SELECT b.*, r.route_name, r.from_city, r.to_city, r.fare, r.id as route_id FROM buses b JOIN routes r ON b.route_id = r.id WHERE b.route_id = ? AND b.bus_type = ? AND b.status = 'active'");
        $bq->bind_param("is", $r_id, $b_type);
    } else {
        $bq = $conn->prepare("SELECT b.*, r.route_name, r.from_city, r.to_city, r.fare, r.id as route_id FROM buses b JOIN routes r ON b.route_id = r.id WHERE b.route_id = ? AND b.status = 'active'");
        $bq->bind_param("i", $r_id);
    }
    $bq->execute();
    $buses_result = $bq->get_result();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Book Ticket — BD Bus Track</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
:root{--primary:#16a34a;--primary-dark:#15803d;--accent:#f97316;--dark:#0f172a;--bk:#16a34a;--dk:#15803d;--bd:#e2e8f0;--bg:#f8fafc;--tx:#1e293b;--mt:#64748b;}
*{box-sizing:border-box;}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--tx);}
.step-badge{background:#16a34a;color:white;border-radius:50%;width:28px;height:28px;display:inline-flex;align-items:center;justify-content:center;font-weight:800;margin-right:8px;font-size:13px;}
.bus-card{cursor:pointer;transition:all .2s;border:1.5px solid var(--bd)!important;}
.bus-card:hover{border-color:#16a34a!important;box-shadow:0 4px 16px rgba(0,0,0,.1);}
/* Override Bootstrap */
.card{border-radius:12px!important;border:1.5px solid var(--bd)!important;}
.card-header{background:white!important;border-bottom:1px solid var(--bd)!important;padding:14px 20px!important;}
.card-header h5{font-weight:800;font-size:15px;color:var(--tx);}
.form-label{font-size:11px;font-weight:700;color:#374151;letter-spacing:.5px;text-transform:uppercase;}
.form-control,.form-select{border:1.5px solid var(--bd)!important;border-radius:8px!important;font-family:'Inter',sans-serif!important;font-size:14px!important;font-weight:500!important;}
.form-control:focus,.form-select:focus{border-color:#16a34a!important;box-shadow:0 0 0 3px rgba(0,0,0,.07)!important;}
.btn-primary{background:#16a34a!important;border-color:#16a34a!important;font-family:'Inter',sans-serif!important;font-weight:700!important;border-radius:8px!important;}
.btn-primary:hover{background:#15803d!important;}
.btn-success{background:#16a34a!important;border-color:#16a34a!important;font-family:'Inter',sans-serif!important;font-weight:700!important;border-radius:8px!important;}
.btn-success:hover{background:#15803d!important;}
.badge.bg-primary{background:#16a34a!important;}
.badge.bg-secondary{background:#4b5563!important;}
.alert-primary{background:#f3f4f6!important;border-color:var(--bd)!important;color:var(--tx)!important;border-radius:9px!important;}
.alert-success{background:#f0fdf4!important;border-color:#bbf7d0!important;color:#15803d!important;}
.text-success{color:#16a34a!important;font-weight:800!important;}
.bus-card:hover { border-color:#16a34a !important; box-shadow: 0 4px 16px rgba(0,0,0,.1); }
/* Seat Map */
.seat-grid { display: flex; flex-direction: column; gap: 6px; max-width: 260px; }
.seat-row { display: grid; grid-template-columns: 44px 20px 44px 44px; gap: 6px; align-items: center; }
.driver-row { grid-template-columns: 44px 44px 44px 20px; }
.seat {
    width: 44px; height: 44px; border-radius: 8px 8px 4px 4px;
    border: 2px solid #dee2e6; font-size: 11px; font-weight: 800;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; transition: all .15s; background: white; color: #333;
    position: relative; user-select: none;
}
.seat::before { content:''; position:absolute; bottom:-4px; left:4px; right:4px; height:4px; background:inherit; border-radius:0 0 3px 3px; filter:brightness(.85); }
.seat:hover:not(.sold) { border-color: #0d6efd; color: #0d6efd; background: #e7f1ff; transform: translateY(-1px); }
.seat.selected-seat { background: #198754 !important; border-color: #198754 !important; color: white !important; }
.seat.sold { background: #e9ecef; border-color: #ced4da; color: #adb5bd; cursor: not-allowed; }
.seat-aisle { width: 20px; }
.driver-seat { background: #fff3cd; border-color: #ffc107; cursor: default; font-size: 18px; }
.driver-seat:hover { transform: none; }
.seat-demo { display: inline-block; width: 16px; height: 16px; border-radius: 3px; margin-right: 4px; vertical-align: middle; }
.seat-demo.available { border: 2px solid #dee2e6; background: white; }
.seat-demo.sold { background: #e9ecef; border: 2px solid #ced4da; }
.seat-demo.selected-demo { background: #198754; border: 2px solid #198754; }
</style>
</head>
<body class="bg-light">

<nav style="background:#16a34a;padding:0 28px;height:60px;display:flex;align-items:center;position:sticky;top:0;z-index:100;box-shadow:0 1px 0 rgba(255,255,255,.07);">
    <a href="index.html" style="font-size:18px;font-weight:900;color:white;text-decoration:none;letter-spacing:-.3px;">BD Bus <span style="color:#9ca3af;font-weight:400">Track</span></a>
    <div style="margin-left:auto;display:flex;align-items:center;gap:20px;">
        <span style="color:rgba(255,255,255,.55);font-size:13px;font-weight:600;">Welcome, <?= htmlspecialchars($_SESSION['user_name']) ?></span>
        <a href="dashboard.php" style="color:rgba(255,255,255,.7);font-size:13px;font-weight:600;text-decoration:none;padding:6px 12px;border-radius:7px;transition:all .15s;" onmouseover="this.style.color='white';this.style.background='rgba(255,255,255,.08)'" onmouseout="this.style.color='rgba(255,255,255,.7)';this.style.background='transparent'">Dashboard</a>
        <a href="logout.php" style="color:rgba(255,255,255,.7);font-size:13px;font-weight:600;text-decoration:none;padding:6px 12px;border-radius:7px;" onmouseover="this.style.color='white'" onmouseout="this.style.color='rgba(255,255,255,.7)'">Logout</a>
    </div>
</nav>

<div class="container mt-4">
    <h2 class="mb-3" style="font-weight:900;font-size:26px;letter-spacing:-.5px;"><i class="fas fa-ticket-alt me-2" style="color:#16a34a"></i>Book Your Ticket</h2>

    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible">
        <i class="fas fa-check-circle me-2"></i><?= $success ?>
        <a href="dashboard.php" class="btn btn-sm btn-success ms-3">View in Dashboard</a>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible">
        <i class="fas fa-exclamation-triangle me-2"></i><?= $error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- STEP 1: Select Route -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0"><span class="step-badge">1</span>Select Route & Bus Type</h5>
        </div>
        <div class="card-body">
            <form method="GET">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">From City</label>
                        <select name="from_city" class="form-select" id="fromCity" onchange="updateTo()" required>
                            <option value="">Select City</option>
                            <?php foreach (['Dhaka','Chittagong','Sylhet','Rajshahi'] as $c): ?>
                            <option value="<?= $c ?>" <?= (isset($_GET['from_city']) && $_GET['from_city']==$c) ? 'selected' : '' ?>><?= $c ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">To City</label>
                        <select name="to_city" class="form-select" id="toCity" required>
                            <option value="">Select Destination</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Bus Type</label>
                        <select name="bus_type" class="form-select">
                            <option value="">Any (AC / Non-AC)</option>
                            <option value="AC" <?= (isset($_GET['bus_type']) && $_GET['bus_type']=='AC') ? 'selected' : '' ?>>AC</option>
                            <option value="Non-AC" <?= (isset($_GET['bus_type']) && $_GET['bus_type']=='Non-AC') ? 'selected' : '' ?>>Non-AC</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Journey Date *</label>
                        <input type="date" name="journey_date" class="form-control"
                               min="<?= date('Y-m-d') ?>"
                               value="<?= htmlspecialchars($_GET['journey_date'] ?? date('Y-m-d')) ?>" required>
                    </div>
                    <?php
                    // Resolve route_id from from_city + to_city
                    $resolved_route_id = null;
                    if (isset($_GET['from_city'], $_GET['to_city'])) {
                        $fc = sanitize($_GET['from_city']); $tc = sanitize($_GET['to_city']);
                        $rq = $conn->query("SELECT id FROM routes WHERE from_city='$fc' AND to_city='$tc' LIMIT 1");
                        if ($rq) { $rrow = $rq->fetch_assoc(); if ($rrow) $resolved_route_id = $rrow['id']; }
                    } elseif (isset($_GET['route_id'])) {
                        $resolved_route_id = intval($_GET['route_id']);
                    }
                    if ($resolved_route_id) echo "<input type='hidden' name='route_id' value='$resolved_route_id'>";
                    ?>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-1"></i>Search Buses
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- STEP 2: Available Buses -->
    <?php if ($buses_result): ?>
    <?php if ($buses_result->num_rows > 0): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0"><span class="step-badge">2</span>Available Buses</h5>
        </div>
        <div class="card-body">
            <?php while ($bus = $buses_result->fetch_assoc()): ?>
            <div class="card bus-card mb-2 border" onclick="selectBus(<?= $bus['id'] ?>, '<?= addslashes($bus['bus_name']) ?>', <?= $bus['fare'] ?>, '<?= $bus['bus_type'] ?>', <?= $resolved_route_id ?>)">
                <div class="card-body py-2">
                    <div class="row align-items-center">
                        <div class="col-md-3">
                            <h6 class="mb-0 fw-bold"><?= $bus['bus_name'] ?></h6>
                            <small class="text-muted"><?= $bus['bus_number'] ?></small>
                        </div>
                        <div class="col-md-2">
                            <span class="badge <?= $bus['bus_type']=='AC' ? 'bg-primary' : 'bg-secondary' ?> fs-6"><?= $bus['bus_type'] ?></span>
                        </div>
                        <div class="col-md-2">
                            <small class="text-muted">Departs</small>
                            <div><?= date('h:i A', strtotime($bus['departure_time'])) ?></div>
                        </div>
                        <div class="col-md-2">
                            <small class="text-muted">Arrives</small>
                            <div><?= date('h:i A', strtotime($bus['arrival_time'])) ?></div>
                        </div>
                        <div class="col-md-2">
                            <span class="text-success fw-bold fs-5">৳<?= $bus['fare'] ?></span>
                            <small class="text-muted d-block">/seat</small>
                        </div>
                        <div class="col-md-1 text-end">
                            <button class="btn btn-success btn-sm" onclick="event.stopPropagation(); selectBus(<?= $bus['id'] ?>, '<?= addslashes($bus['bus_name']) ?>', <?= $bus['fare'] ?>, '<?= $bus['bus_type'] ?>', <?= $resolved_route_id ?>)">Select</button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>No buses found for this route/type. Try a different filter.</div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- STEP 3: Passenger Details Form -->
    <div class="card shadow-sm" id="bookingForm" style="display:none;">
        <div class="card-header bg-white">
            <h5 class="mb-0"><span class="step-badge">3</span>Passenger Details</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-primary" id="selBusInfo"></div>
            <form method="POST">
                <input type="hidden" id="bid" name="bus_id">
                <input type="hidden" id="rid" name="route_id">
                <input type="hidden" id="btype" name="bus_type">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Passenger Name *</label>
                        <input type="text" class="form-control" name="passenger_name"
                               value="<?= htmlspecialchars($_SESSION['user_name']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Phone Number *</label>
                        <input type="tel" class="form-control" name="passenger_phone"
                               value="<?= htmlspecialchars($_SESSION['user_phone'] ?? '') ?>"
                               placeholder="01XXXXXXXXX" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Email <small class="text-muted">(optional — get ticket by email)</small></label>
                        <input type="email" class="form-control" name="passenger_email"
                               value="<?= htmlspecialchars($_SESSION['user_email'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Number of Passengers *</label>
                        <select class="form-select" name="passenger_count" id="pCount" onchange="updateFare()">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <option value="<?= $i ?>"><?= $i ?> Passenger<?= $i > 1 ? 's' : '' ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <input type="hidden" name="journey_date" id="journeyDateHidden" value="<?= htmlspecialchars($_GET['journey_date'] ?? date('Y-m-d')) ?>">
                    <div class="col-12">
                        <label class="form-label fw-semibold">Select Seats *</label>
                        <div id="seatMapContainer" class="mb-2" style="display:none">
                            <div class="card border-0 bg-light p-3 mb-2">
                                <div class="d-flex gap-3 mb-3 flex-wrap">
                                    <span><span class="seat-demo available"></span> Available</span>
                                    <span><span class="seat-demo sold"></span> Sold</span>
                                    <span><span class="seat-demo selected-demo"></span> Selected</span>
                                </div>
                                <div class="text-center py-3" id="seatLoadingMsg">
                                    <i class="fas fa-spinner fa-spin me-2"></i>Loading seats...
                                </div>
                                <div class="seat-grid" id="seatGrid" style="display:none"></div>
                                <div class="mt-3 p-2 bg-white rounded border">
                                    <strong>Selected:</strong>
                                    <span id="selectedSeatsDisplay" class="text-success fw-bold">None</span>
                                    <span class="text-muted ms-2" id="seatCount">(0 seats)</span>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="seat_numbers" id="seatNumbersInput" required>
                        <div id="seatMapHint" class="text-muted small">
                            <i class="fas fa-info-circle me-1"></i>Select a bus and journey date above to see available seats
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Payment Method</label>
                        <select class="form-select" name="payment_method">
                            <option value="cash">Cash on Board</option>
                            <option value="bkash">bKash</option>
                            <option value="nagad">Nagad</option>
                            <option value="card">Card</option>
                        </select>
                    </div>
                    <div class="col-md-6" id="transactionRow" style="display:none">
                        <label class="form-label fw-semibold">Transaction ID *</label>
                        <input type="text" class="form-control" name="transaction_id" id="transactionId"
                               placeholder="e.g. 8N7TXK2P1Q">
                        <small class="text-muted">bKash/Nagad/Card transaction reference number</small>
                    </div>
                    <div class="col-md-6 d-flex align-items-center">
                        <div class="alert alert-success mb-0 w-100">
                            <strong>Total Fare: </strong>
                            <span id="fareDisplay" class="fs-4 fw-bold">৳0</span>
                        </div>
                    </div>
                    <div class="col-12 mt-2">
                        <button type="submit" name="book_ticket" class="btn btn-success btn-lg px-5">
                            <i class="fas fa-ticket-alt me-2"></i>Confirm Booking
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let selectedFare = 0;

const routeMap = {
    'Dhaka': ['Chittagong', 'Sylhet', 'Rajshahi'],
    'Chittagong': ["Cox's Bazar", 'Dhaka'],
    'Sylhet': ['Dhaka'],
    'Rajshahi': ['Dhaka'],
    "Cox's Bazar": ['Chittagong']
};

function updateTo() {
    const from = document.getElementById('fromCity').value;
    const to = document.getElementById('toCity');
    const prevVal = to.value;
    to.innerHTML = '<option value="">Select Destination</option>';
    if (from && routeMap[from]) {
        routeMap[from].forEach(c => {
            const opt = document.createElement('option');
            opt.value = c;
            opt.textContent = c;
            to.appendChild(opt);
        });
        // Restore previous value if still valid
        if (prevVal && routeMap[from].includes(prevVal)) {
            to.value = prevVal;
        }
    }
}

// Seat map variables
let selectedSeats = [];
let selectedSeats2 = [];
let currentBusId = 0;

function selectBus(busId, name, fare, type, routeId) {
    selectedFare = parseFloat(fare);
    currentBusId = busId;
    document.getElementById('bid').value = busId;
    document.getElementById('rid').value = routeId;
    document.getElementById('btype').value = type;
    document.getElementById('selBusInfo').innerHTML =
        `<i class="fas fa-bus me-2"></i><strong>${name}</strong> &nbsp;|&nbsp;
         <span class="badge ${type==='AC'?'bg-primary':'bg-secondary'}">${type}</span> &nbsp;|&nbsp;
         ৳${fare} per seat`;
    updateFare();

    // Show seat map, fetch sold seats via AJAX
    document.getElementById('seatMapContainer').style.display = 'block';
    document.getElementById('seatMapHint').style.display = 'none';
    document.getElementById('seatLoadingMsg').style.display = 'block';
    document.getElementById('seatGrid').style.display = 'none';
    selectedSeats = [];
    updateSeatDisplay();

    const journeyDate = document.getElementById('journeyDateHidden')?.value || document.querySelector('input[name="journey_date"]')?.value || '<?= date('Y-m-d') ?>';
    fetch(`check_seats.php?bus_id=${busId}&journey_date=${journeyDate}`)
        .then(r => r.json())
        .then(data => {
            buildSeatGrid(data.booked_seats || []);
        })
        .catch(() => buildSeatGrid([]));

    const form = document.getElementById('bookingForm');
    form.style.display = 'block';
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function buildSeatGrid(soldSeats) {
    const rows = ['A','B','C','D','E','F','G','H','I','J'];
    const grid = document.getElementById('seatGrid');
    let html = '';
    // Driver row
    html += `<div class="seat-row driver-row">
        <div class="seat-aisle"></div><div class="seat-aisle"></div>
        <div class="seat driver-seat" style="font-size:18px">🚗</div>
        <div class="seat-aisle"></div>
    </div>`;
    rows.forEach(row => {
        html += `<div class="seat-row">`;
        [1, null, 2, 3].forEach(col => {
            if (col === null) {
                html += `<div class="seat-aisle"></div>`;
            } else {
                const s = row + col;
                const isSold = soldSeats.includes(s);
                html += `<div class="seat${isSold?' sold':''}" data-seat="${s}" ${isSold?'':'onclick="toggleSeat(this)"'}>${s}</div>`;
            }
        });
        html += `</div>`;
    });
    grid.innerHTML = html;
    document.getElementById('seatLoadingMsg').style.display = 'none';
    grid.style.display = 'flex';
}

// Re-fetch if journey date changes
document.addEventListener('DOMContentLoaded', () => {
    const dateInput = document.querySelector('input[name="journey_date"]');
    if (dateInput) {
        dateInput.addEventListener('change', () => {
            if (currentBusId) {
                document.getElementById('seatLoadingMsg').style.display = 'block';
                document.getElementById('seatGrid').style.display = 'none';
                selectedSeats = [];
                updateSeatDisplay();
                fetch(`check_seats.php?bus_id=${currentBusId}&journey_date=${dateInput.value}`)
                    .then(r => r.json())
                    .then(data => buildSeatGrid(data.booked_seats || []))
                    .catch(() => buildSeatGrid([]));
            }
        });
    }
});

function toggleSeat(el) {
    if (el.classList.contains('sold')) return;
    const seat = el.dataset.seat;
    const max = parseInt(document.getElementById('pCount').value) || 1;
    if (el.classList.contains('selected-seat')) {
        el.classList.remove('selected-seat');
        selectedSeats = selectedSeats.filter(s => s !== seat);
    } else {
        if (selectedSeats.length >= max) {
            const first = selectedSeats.shift();
            document.querySelector(`.seat[data-seat="${first}"]`)?.classList.remove('selected-seat');
        }
        el.classList.add('selected-seat');
        selectedSeats.push(seat);
    }
    updateSeatDisplay();
}

function updateSeatDisplay() {
    const disp  = document.getElementById('selectedSeatsDisplay');
    const cnt   = document.getElementById('seatCount');
    const input = document.getElementById('seatNumbersInput');
    if (selectedSeats.length === 0) {
        disp.textContent = 'None'; cnt.textContent = '(0 seats)'; input.value = '';
    } else {
        disp.textContent = selectedSeats.join(', ');
        cnt.textContent = `(${selectedSeats.length} seat${selectedSeats.length>1?'s':''})`;
        input.value = selectedSeats.join(', ');
    }
    updateFare();
}

function updateFare() {
    const count = parseInt(document.getElementById('pCount').value) || 1;
    document.getElementById('fareDisplay').textContent = '৳' + (selectedFare * count).toLocaleString();
}

// Show/hide Transaction ID field
document.addEventListener('DOMContentLoaded', function() {
    const paySelect = document.querySelector('select[name="payment_method"]');
    const txRow = document.getElementById('transactionRow');
    const txInput = document.getElementById('transactionId');
    if (paySelect && txRow) {
        paySelect.addEventListener('change', function() {
            const needsTx = ['bkash','nagad','card'].includes(this.value);
            txRow.style.display = needsTx ? 'block' : 'none';
            txInput.required = needsTx;
        });
    }
});

// Populate To dropdown on page load - always run
document.addEventListener('DOMContentLoaded', function() {
    // Populate toCity whenever fromCity has a value
    const fromEl = document.getElementById('fromCity');
    const toEl   = document.getElementById('toCity');
    if (fromEl.value) {
        updateTo();
        // Restore previously selected To value if available
        <?php if (!empty($_GET['to_city'])): ?>
        toEl.value = "<?= htmlspecialchars($_GET['to_city']) ?>";
        <?php endif; ?>
    }
    // Also listen for changes to fromCity
    fromEl.addEventListener('change', updateTo);
});

// Show/hide Transaction ID field based on payment method
document.addEventListener('DOMContentLoaded', function() {
    const paySelect = document.querySelector('select[name="payment_method"]');
    const txRow     = document.getElementById('transactionRow');
    const txInput   = document.getElementById('transactionId');
    if (paySelect && txRow) {
        function checkTx() {
            const needs = ['bkash','nagad','card'].includes(paySelect.value);
            txRow.style.display = needs ? '' : 'none';
            if (txInput) txInput.required = needs;
        }
        paySelect.addEventListener('change', checkTx);
        checkTx(); // run on load
    }
});
</script>
</body>
</html>
