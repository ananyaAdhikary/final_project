<?php
require_once 'config/database.php';

// Start guest session
if (!isset($_SESSION['guest'])) {
    $_SESSION['guest'] = true;
}

$conn = getConnection();

// Safe column migration
$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS passenger_email VARCHAR(100) DEFAULT NULL");
$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS passenger_count INT DEFAULT 1");
$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS bus_type VARCHAR(10) DEFAULT 'Non-AC'");
$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS payment_method VARCHAR(20) DEFAULT 'cash'");
$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS payment_status VARCHAR(20) DEFAULT 'pending'");
$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS cancel_reason VARCHAR(255) DEFAULT NULL");
$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS refund_amount DECIMAL(10,2) DEFAULT 0");
$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS transaction_id VARCHAR(100) DEFAULT NULL");

// Get distinct cities for dropdowns
$cities_from = $conn->query("SELECT DISTINCT from_city FROM routes ORDER BY from_city");
$cities_to = $conn->query("SELECT DISTINCT to_city FROM routes ORDER BY to_city");

// Handle booking submission
$booking_success = false;
$booking_id = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['book_ticket'])) {
    $bus_id        = intval($_POST['bus_id']);
    $route_id      = intval($_POST['route_id']);
    $p_name        = sanitize($_POST['passenger_name']);
    $p_phone       = sanitize($_POST['passenger_phone']);
    $p_email       = sanitize($_POST['passenger_email'] ?? '');
    $p_count       = intval($_POST['passenger_count'] ?? 1);
    $seat_numbers  = sanitize($_POST['seat_numbers']);
    $journey_date  = $_POST['journey_date'];
    $bus_type      = sanitize($_POST['bus_type'] ?? 'Non-AC');
    $payment_method  = sanitize($_POST['payment_method'] ?? 'cash');
    $transaction_id  = sanitize($_POST['transaction_id'] ?? '');

    // Get fare
    $fare_stmt = $conn->prepare("SELECT r.fare FROM routes r JOIN buses b ON r.id = b.route_id WHERE b.id = ?");
    $fare_stmt->bind_param("i", $bus_id);
    $fare_stmt->execute();
    $fare_row = $fare_stmt->get_result()->fetch_assoc();
    $total_fare = $fare_row['fare'] * $p_count;

    $ins = $conn->prepare("INSERT INTO bookings (bus_id, route_id, passenger_name, passenger_phone, passenger_email, passenger_count, seat_numbers, bus_type, total_fare, payment_method, payment_status, booking_date, journey_date, transaction_id) VALUES (?,?,?,?,?,?,?,?,?,?,'pending',CURDATE(),?,?)");
    $ins->bind_param("iisssissdsss", $bus_id, $route_id, $p_name, $p_phone, $p_email, $p_count, $seat_numbers, $bus_type, $total_fare, $payment_method, $journey_date, $transaction_id);

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
        $error = "Booking failed. Please try again.";
    }
}

// Get buses for route+type filter
$buses_result = null;
if (isset($_GET['route_id'])) {
    $r_id = intval($_GET['route_id']);
    $b_type = isset($_GET['bus_type']) && $_GET['bus_type'] ? sanitize($_GET['bus_type']) : null;
    if ($b_type) {
        $bq = $conn->prepare("SELECT b.*, r.route_name, r.from_city, r.to_city, r.fare FROM buses b JOIN routes r ON b.route_id=r.id WHERE b.route_id=? AND b.bus_type=? AND b.status='active'");
        $bq->bind_param("is", $r_id, $b_type);
    } else {
        $bq = $conn->prepare("SELECT b.*, r.route_name, r.from_city, r.to_city, r.fare FROM buses b JOIN routes r ON b.route_id=r.id WHERE b.route_id=? AND b.status='active'");
        $bq->bind_param("i", $r_id);
    }
    $bq->execute();
    $buses_result = $bq->get_result();
}

// All routes for dropdown
$routes_result = $conn->query("SELECT * FROM routes ORDER BY route_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Guest Booking - BD Bus Track</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
body { background: #f8f9fa; }
.step-badge { background:#16a34a; color:white; border-radius:50%; width:28px; height:28px; display:inline-flex; align-items:center; justify-content:center; font-weight:bold; margin-right:8px; }
.bus-card:hover { border-color:#16a34a; box-shadow:0 2px 10px rgba(13,110,253,.2); }
.ticket-preview { background:linear-gradient(135deg,#16a34a,#0a58ca); color:white; border-radius:14px; padding:25px; }
/* Seat Map */
.seat-grid-g { display:flex; flex-direction:column; gap:6px; max-width:260px; }
.g-seat-row { display:grid; grid-template-columns:44px 20px 44px 44px; gap:6px; align-items:center; }
.g-seat {
    width:44px; height:44px; border-radius:8px 8px 4px 4px;
    border:2px solid #dee2e6; font-size:11px; font-weight:800;
    display:flex; align-items:center; justify-content:center;
    cursor:pointer; transition:all .15s; background:white; color:#333; user-select:none;
}
.g-seat:hover:not(.g-sold) { border-color:#16a34a; color:#16a34a; background:#dcfce7; transform:translateY(-1px); }
.g-seat.g-selected { background:#15803d !important; border-color:#198754 !important; color:white !important; }
.g-seat.g-sold { background:#e9ecef; border-color:#ced4da; color:#adb5bd; cursor:not-allowed; }
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark" style="background:#0f172a"">
    <div class="container">
        <a class="navbar-brand" href="index.html"><i class="fas fa-bus"></i> BD Bus Track</a>
        <div class="navbar-nav ms-auto">
            <span class="navbar-text text-warning me-3"><i class="fas fa-user-clock me-1"></i>Guest Mode</span>
            <a class="nav-link" href="login.php">Login for Full Access</a>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i>
        <strong>Guest Booking:</strong> You can book without an account. Provide your phone/email to receive ticket details and manage your booking.
    </div>

    <?php if ($booking_success): ?>
    <div class="ticket-preview text-center mb-4">
        <i class="fas fa-check-circle fa-3x mb-3"></i>
        <h3>Ticket Confirmed! 🎉</h3>
        <h1 class="fw-bold">#<?= $booking_id ?></h1>
        <p class="fs-5">Save this Booking ID to cancel or check your ticket</p>
        <div class="row justify-content-center mt-3">
            <div class="col-md-6">
                <div class="bg-white text-dark rounded p-3">
                    <p class="mb-1"><strong>Use this ID to cancel:</strong></p>
                    <h2 class="text-primary fw-bold">#<?= $booking_id ?></h2>
                    <a href="cancel_ticket.php?id=<?= $booking_id ?>" class="btn btn-danger btn-sm">Cancel This Ticket</a>
                </div>
            </div>
        </div>
        <?php if (isset($p_email) && $p_email): ?>
        <p class="mt-3 small">📧 Ticket details sent to your email!</p>
        <?php endif; ?>
        <a href="guest_booking.php" class="btn btn-light mt-3">Book Another Ticket</a>
    </div>
    <?php else: ?>

    <?php if ($error): ?>
    <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <!-- Step 1: Select Route -->
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-header bg-white border-0 pb-0">
            <h5><span class="step-badge">1</span>Select Route</h5>
        </div>
        <div class="card-body">
            <form method="GET">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">From City</label>
                        <select name="from_city" class="form-select" id="fromCity" onchange="updateToCities()" required>
                            <option value="">Select Origin</option>
                            <?php
                            $all_from = ['Dhaka','Chittagong','Sylhet','Rajshahi'];
                            foreach($all_from as $city): ?>
                            <option value="<?= $city ?>" <?= (isset($_GET['from_city']) && $_GET['from_city']==$city)?'selected':'' ?>><?= $city ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">To City</label>
                        <select name="to_city" class="form-select" id="toCity" required>
                            <option value="">Select Destination</option>

                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Bus Type</label>
                        <select name="bus_type" class="form-select">
                            <option value="">Any Type</option>
                            <option value="AC" <?= (isset($_GET['bus_type'])&&$_GET['bus_type']=='AC')?'selected':'' ?>>AC</option>
                            <option value="Non-AC" <?= (isset($_GET['bus_type'])&&$_GET['bus_type']=='Non-AC')?'selected':'' ?>>Non-AC</option>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <?php
                        // Find route_id for selected from+to
                        if(isset($_GET['from_city']) && isset($_GET['to_city'])) {
                            $fc = sanitize($_GET['from_city']); $tc = sanitize($_GET['to_city']);
                            $rq = $conn->query("SELECT id FROM routes WHERE from_city='$fc' AND to_city='$tc' LIMIT 1");
                            $rrow = $rq ? $rq->fetch_assoc() : null;
                            if($rrow) echo "<input type='hidden' name='route_id' value='{$rrow['id']}'>";
                        }
                        ?>
                        <div class="row g-2 mt-1">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" style="font-size:11px;text-transform:uppercase;letter-spacing:.5px">Journey Date *</label>
                                <input type="date" name="journey_date" class="form-control"
                                       min="<?= date('Y-m-d') ?>"
                                       value="<?= htmlspecialchars($_GET['journey_date'] ?? date('Y-m-d')) ?>" required>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>Find Buses</button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Step 2: Available Buses -->
    <?php if ($buses_result && $buses_result->num_rows > 0): ?>
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-header bg-white border-0 pb-0">
            <h5><span class="step-badge">2</span>Select Bus</h5>
        </div>
        <div class="card-body">
            <?php while($bus = $buses_result->fetch_assoc()): ?>
            <div class="card bus-card mb-3 border cursor-pointer" onclick="selectBus(<?= $bus['id'] ?>, '<?= $bus['bus_name'] ?>', <?= $bus['fare'] ?>, '<?= $bus['bus_type'] ?>', <?= isset($_GET['route_id'])?$_GET['route_id']:0 ?>)">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-3">
                            <h6 class="mb-0 fw-bold"><?= $bus['bus_name'] ?></h6>
                            <small class="text-muted"><?= $bus['bus_number'] ?></small>
                        </div>
                        <div class="col-md-2">
                            <span class="badge <?= $bus['bus_type']=='AC'?'bg-primary':'bg-secondary' ?>"><?= $bus['bus_type'] ?></span>
                        </div>
                        <div class="col-md-2">
                            <small><i class="fas fa-clock text-muted me-1"></i><?= date('h:i A',strtotime($bus['departure_time'])) ?></small>
                        </div>
                        <div class="col-md-2">
                            <small><?= $bus['from_city'] ?> → <?= $bus['to_city'] ?></small>
                        </div>
                        <div class="col-md-2">
                            <strong class="text-success fs-5">৳<?= $bus['fare'] ?></strong>
                            <small class="text-muted d-block">per seat</small>
                        </div>
                        <div class="col-md-1">
                            <button class="btn btn-sm btn-outline-primary">Select</button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
    <?php elseif(isset($_GET['route_id'])): ?>
    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>No buses found for this route/type combination.</div>
    <?php endif; ?>

    <!-- Step 3: Passenger Details -->
    <div class="card border-0 shadow-sm" id="bookingForm" style="display:none;">
        <div class="card-header bg-white border-0 pb-0">
            <h5><span class="step-badge">3</span>Passenger Details</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" id="hidden_bus_id" name="bus_id">
                <input type="hidden" id="hidden_route_id" name="route_id">
                <input type="hidden" id="hidden_bus_type" name="bus_type">

                <div class="alert alert-primary" id="selectedBusInfo"></div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Passenger Name *</label>
                        <input type="text" class="form-control" name="passenger_name" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Phone Number *</label>
                        <input type="tel" class="form-control" name="passenger_phone" placeholder="01XXXXXXXXX" required>
                        <small class="text-muted">Required for ticket confirmation</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Email (optional)</label>
                        <input type="email" class="form-control" name="passenger_email" placeholder="you@example.com">
                        <small class="text-muted">Get your ticket via email</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Number of Passengers *</label>
                        <select class="form-select" name="passenger_count" id="passengerCount" onchange="updateFare()">
                            <option value="1">1 Passenger</option>
                            <option value="2">2 Passengers</option>
                            <option value="3">3 Passengers</option>
                            <option value="4">4 Passengers</option>
                            <option value="5">5 Passengers</option>
                        </select>
                    </div>
                    <input type="hidden" name="journey_date" id="gJourneyDateHidden" value="<?= htmlspecialchars($_GET['journey_date'] ?? date('Y-m-d')) ?>">
                    <div class="col-12">
                        <label class="form-label fw-semibold">Select Seats *</label>
                        <div id="seatMapContainerG" class="mb-2" style="display:none">
                            <div class="card border-0 bg-light p-3 mb-2">
                                <div class="d-flex gap-3 mb-3 flex-wrap">
                                    <span><span style="display:inline-block;width:16px;height:16px;border:2px solid #dee2e6;border-radius:3px;vertical-align:middle;margin-right:4px"></span>Available</span>
                                    <span><span style="display:inline-block;width:16px;height:16px;background:#e9ecef;border:2px solid #ced4da;border-radius:3px;vertical-align:middle;margin-right:4px"></span>Sold</span>
                                    <span><span style="display:inline-block;width:16px;height:16px;background:#15803d;border-radius:3px;vertical-align:middle;margin-right:4px"></span>Selected</span>
                                </div>
                                <div class="seat-grid-g" id="seatGridG"></div>
                                <div class="mt-3 p-2 bg-white rounded border">
                                    <strong>Selected:</strong> <span id="gSelectedDisplay" class="text-success fw-bold">None</span>
                                    <span class="text-muted ms-2" id="gSeatCount">(0 seats)</span>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="seat_numbers" id="gSeatInput" required>
                        <div id="gSeatHint" class="text-muted small"><i class="fas fa-info-circle me-1"></i>Select a bus above to see available seats</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Payment Method</label>
                        <select class="form-select" name="payment_method" id="gPayMethod">
                            <option value="cash">Cash on Board</option>
                            <option value="bkash">bKash</option>
                            <option value="nagad">Nagad</option>
                            <option value="card">Card</option>
                        </select>
                    </div>
                    <div class="col-md-6" id="gTxRow" style="display:none">
                        <label class="form-label fw-semibold">Transaction ID *</label>
                        <input type="text" class="form-control" name="transaction_id" id="gTxInput"
                               placeholder="e.g. 8N7TXK2P1Q">
                        <small class="text-muted">bKash/Nagad/Card transaction reference number</small>
                    </div>
                    <div class="col-md-6">
                        <div class="alert alert-success mb-0">
                            <strong>Total Fare:</strong> <span id="totalFareDisplay" class="fs-5 fw-bold">৳0</span>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" name="book_ticket" class="btn btn-success btn-lg px-5">
                            <i class="fas fa-ticket-alt me-2"></i>Confirm Booking
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php endif; ?>

    <!-- Check existing booking section -->
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-body">
            <h6><i class="fas fa-search me-2 text-primary"></i>Check or Cancel Existing Booking</h6>
            <div class="row g-2">
                <div class="col-md-8">
                    <input type="number" class="form-control" id="checkBookingId" placeholder="Enter Booking ID (e.g., 12)">
                </div>
                <div class="col-md-4">
                    <button class="btn btn-outline-primary w-100" onclick="checkBooking()">
                        <i class="fas fa-eye me-1"></i>Check Booking
                    </button>
                </div>
            </div>
            <div id="bookingCheckResult" class="mt-3"></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let selectedFare = 0;

const routeMap = {
  'Dhaka': ['Chittagong','Sylhet','Rajshahi'],
  'Chittagong': ["Cox's Bazar",'Dhaka'],
  'Sylhet': ['Dhaka'],
  'Rajshahi': ['Dhaka'],
  "Cox's Bazar": ['Chittagong']
};

function updateToCities() {
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
        if (prevVal && routeMap[from].includes(prevVal)) to.value = prevVal;
    }
}

// Sold seats per bus from PHP
const gSoldSeats = {};

function selectBus(busId, busName, fare, busType, routeId) {
    selectedFare = fare;
    document.getElementById('hidden_bus_id').value = busId;
    document.getElementById('hidden_route_id').value = routeId;
    document.getElementById('hidden_bus_type').value = busType;
    document.getElementById('selectedBusInfo').innerHTML = 
        `<i class="fas fa-bus me-2"></i><strong>Selected:</strong> ${busName} | Type: ${busType} | ৳${fare}/seat`;
    updateFare();
    // Show seat map
    document.getElementById('seatMapContainerG').style.display = 'block';
    document.getElementById('gSeatHint').style.display = 'none';
    gSelectedSeats = [];
    buildGuestSeatGrid(busId);
    const form = document.getElementById('bookingForm');
    form.style.display = 'block';
    form.scrollIntoView({behavior:'smooth'});
}

let gSelectedSeats = [];

function buildGuestSeatGrid(busId) {
    const journeyDate = document.querySelector('input[name="journey_date"]').value || '<?= date('Y-m-d') ?>';
    fetch(`check_seats.php?bus_id=${busId}&journey_date=${journeyDate}`)
        .then(r => r.json())
        .then(data => renderGuestGrid(data.booked_seats || []))
        .catch(() => renderGuestGrid([]));
}

function renderGuestGrid(soldSeats) {
    const rows = ['A','B','C','D','E','F','G','H','I','J'];
    const grid = document.getElementById('seatGridG');
    let html = '';
    // driver
    html += `<div class="g-seat-row"><div style="width:44px"></div><div style="width:20px"></div><div class="g-seat" style="font-size:18px;cursor:default;background:#fff3cd;border-color:#ffc107">🚗</div><div style="width:20px"></div></div>`;
    rows.forEach(row => {
        html += `<div class="g-seat-row">`;
        [1, null, 2, 3].forEach(col => {
            if (col === null) { html += `<div style="width:20px"></div>`; }
            else {
                const s = row + col;
                const isSold = soldSeats.includes(s);
                html += `<div class="g-seat${isSold?' g-sold':''}" data-seat="${s}" ${isSold?'':'onclick="toggleGSeat(this)"'}>${s}</div>`;
            }
        });
        html += `</div>`;
    });
    grid.innerHTML = html;
    updateGSeatDisplay();
}

function toggleGSeat(el) {
    if (el.classList.contains('g-sold')) return;
    const seat = el.dataset.seat;
    const max = parseInt(document.getElementById('passengerCount').value) || 1;
    if (el.classList.contains('g-selected')) {
        el.classList.remove('g-selected');
        gSelectedSeats = gSelectedSeats.filter(s => s !== seat);
    } else {
        if (gSelectedSeats.length >= max) {
            const first = gSelectedSeats.shift();
            document.querySelector(`.g-seat[data-seat="${first}"]`)?.classList.remove('g-selected');
        }
        el.classList.add('g-selected');
        gSelectedSeats.push(seat);
    }
    updateGSeatDisplay();
}

function updateGSeatDisplay() {
    const disp = document.getElementById('gSelectedDisplay');
    const cnt  = document.getElementById('gSeatCount');
    const inp  = document.getElementById('gSeatInput');
    if (gSelectedSeats.length === 0) {
        disp.textContent = 'None'; cnt.textContent = '(0 seats)'; inp.value = '';
    } else {
        disp.textContent = gSelectedSeats.join(', ');
        cnt.textContent = `(${gSelectedSeats.length} seat${gSelectedSeats.length>1?'s':''})`;
        inp.value = gSelectedSeats.join(', ');
    }
    updateFare();
}

function updateFare() {
    const count = gSelectedSeats.length || parseInt(document.getElementById('passengerCount').value) || 1;
    const total = selectedFare * count;
    document.getElementById('totalFareDisplay').textContent = '৳' + total.toLocaleString();
}

function checkBooking() {
    const id = document.getElementById('checkBookingId').value;
    if (!id) return;
    fetch(`get_booking.php?id=${id}`)
        .then(r => r.json())
        .then(data => {
            const el = document.getElementById('bookingCheckResult');
            if (data.error) {
                el.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
            } else {
                const canCancel = data.status === 'confirmed';
                el.innerHTML = `
                    <div class="card border-success">
                        <div class="card-body">
                            <h6>Booking #${data.id}</h6>
                            <p class="mb-1"><strong>Passenger:</strong> ${data.passenger_name}</p>
                            <p class="mb-1"><strong>Route:</strong> ${data.from_city} → ${data.to_city}</p>
                            <p class="mb-1"><strong>Journey:</strong> ${data.journey_date}</p>
                            <p class="mb-1"><strong>Seats:</strong> ${data.seat_numbers}</p>
                            <p class="mb-1"><strong>Fare:</strong> ৳${data.total_fare}</p>
                            <p class="mb-2"><strong>Status:</strong> <span class="badge ${data.status==='confirmed'?'bg-success':'bg-danger'}">${data.status}</span></p>
                            ${canCancel ? `<a href="cancel_ticket.php?id=${data.id}" class="btn btn-danger btn-sm"><i class="fas fa-times me-1"></i>Cancel Ticket</a>` : ''}
                        </div>
                    </div>`;
            }
        });
}

// Initialize dropdowns if values exist
document.addEventListener('DOMContentLoaded', function() {
    const fromEl = document.getElementById('fromCity');
    const toEl   = document.getElementById('toCity');
    if (fromEl && fromEl.value) {
        updateToCities();
        <?php if (!empty($_GET['to_city'])): ?>
        toEl.value = "<?= htmlspecialchars($_GET['to_city']) ?>";
        <?php endif; ?>
    }
    if (fromEl) fromEl.addEventListener('change', updateToCities);
});

// Transaction ID show/hide for guest
document.addEventListener('DOMContentLoaded', function() {
    const gPay = document.getElementById('gPayMethod');
    const gTxRow = document.getElementById('gTxRow');
    const gTxIn  = document.getElementById('gTxInput');
    if (gPay && gTxRow) {
        function checkGTx() {
            const needs = ['bkash','nagad','card'].includes(gPay.value);
            gTxRow.style.display = needs ? '' : 'none';
            if (gTxIn) gTxIn.required = needs;
        }
        gPay.addEventListener('change', checkGTx);
        checkGTx();
    }
});
</script>
</body>
</html>
