<?php
include '../config/database.php';
$conn = getConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php"); exit();
}

$id = intval($_GET['id'] ?? 0);
if (!$id) { header("Location: total_bookings.php"); exit(); }

// Fetch booking — admin can see ALL bookings (no user_id restriction)
$stmt = $conn->prepare(
    "SELECT bk.*, b.bus_name, b.bus_number, r.from_city, r.to_city
     FROM bookings bk
     JOIN buses b  ON bk.bus_id  = b.id
     JOIN routes r ON bk.route_id = r.id
     WHERE bk.id = ?"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) { header("Location: total_bookings.php"); exit(); }

$msg = null; $err = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason     = sanitize($_POST['cancel_reason'] ?? 'Cancelled by admin');
    $hours_left = (strtotime($booking['journey_date']) - time()) / 3600;
    $refund_pct = ($hours_left >= 24) ? 0.80 : 0.50;
    $refund     = round($booking['total_fare'] * $refund_pct, 2);

    if ($booking['status'] === 'cancelled') {
        $msg = "This booking is already cancelled."; $err = true;
    } else {
        $upd = $conn->prepare(
            "UPDATE bookings SET status='cancelled', cancel_reason=?, refund_amount=?, payment_status='refunded' WHERE id=?"
        );
        $upd->bind_param("sdi", $reason, $refund, $id);

        if ($upd->execute()) {
            $msg = "Booking #$id cancelled successfully. Refund: ৳$refund";
            $booking['status'] = 'cancelled';

            // Send email to passenger
            $email_to = $booking['passenger_email'] ?? '';
            if ($email_to) {
                sendCancelEmail(
                    $email_to,
                    $booking['passenger_name'],
                    $id,
                    $booking['from_city'],
                    $booking['to_city'],
                    $booking['journey_date'],
                    $booking['seat_numbers'],
                    $refund,
                    $reason
                );
                $msg .= " — Email sent to $email_to";
            } else {
                $msg .= " — No email on file for this passenger.";
            }
        } else {
            $msg = "Cancellation failed: " . $conn->error; $err = true;
        }
    }
}

$hours_left = (strtotime($booking['journey_date']) - time()) / 3600;
$refund_pct = ($hours_left >= 24) ? 80 : 50;
$est_refund = round($booking['total_fare'] * $refund_pct / 100, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cancel Booking #<?= $id ?> | Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark" style="background:#0f172a"">
    <div class="container">
        <a class="navbar-brand fw-900" style="font-weight:900;color:#16a34a !important;" href="dashboard.php"><i class="fas fa-bus me-2"></i>Bus Admin Panel</a>
        <a href="total_bookings.php" class="btn btn-outline-light btn-sm">← All Bookings</a>
    </div>
</nav>

<div class="container mt-4">
<div class="row justify-content-center">
<div class="col-md-7">

    <h3 class="mb-4"><i class="fas fa-times-circle text-danger me-2"></i>Cancel Booking — Admin</h3>

    <?php if ($msg): ?>
    <div class="alert alert-<?= $err ? 'danger' : 'success' ?>">
        <i class="fas fa-<?= $err ? 'exclamation-triangle' : 'check-circle' ?> me-2"></i><?= $msg ?>
    </div>
    <a href="total_bookings.php" class="btn btn-success">← Back to All Bookings</a>
    <?php else: ?>

    <!-- Booking Details -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-dark text-white">
            <h6 class="mb-0">Booking #<?= $booking['id'] ?> Details</h6>
        </div>
        <div class="card-body">
            <div class="row g-2">
                <div class="col-6"><strong>Passenger:</strong><br><?= htmlspecialchars($booking['passenger_name']) ?></div>
                <div class="col-6"><strong>Phone:</strong><br><?= htmlspecialchars($booking['passenger_phone']) ?></div>
                <div class="col-6 mt-2"><strong>Email:</strong><br>
                    <?= $booking['passenger_email'] ? htmlspecialchars($booking['passenger_email']) : '<span class="text-muted">Not provided</span>' ?>
                </div>
                <div class="col-6 mt-2"><strong>User Type:</strong><br>
                    <?= $booking['user_id'] ? '<span class="badge bg-primary">Registered</span>' : '<span class="badge bg-secondary">Guest</span>' ?>
                </div>
                <div class="col-6 mt-2"><strong>Route:</strong><br><?= $booking['from_city'] ?> → <?= $booking['to_city'] ?></div>
                <div class="col-6 mt-2"><strong>Bus:</strong><br><?= htmlspecialchars($booking['bus_name']) ?></div>
                <div class="col-6 mt-2"><strong>Journey Date:</strong><br><?= date('d M Y', strtotime($booking['journey_date'])) ?></div>
                <div class="col-6 mt-2"><strong>Seats:</strong><br><?= htmlspecialchars($booking['seat_numbers']) ?></div>
                <div class="col-6 mt-2"><strong>Total Fare:</strong><br>৳<?= number_format($booking['total_fare']) ?></div>
                <div class="col-6 mt-2"><strong>Status:</strong><br>
                    <span class="badge <?= $booking['status']==='confirmed'?'bg-success':'bg-danger' ?>">
                        <?= ucfirst($booking['status']) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <?php if ($booking['status'] === 'confirmed'): ?>

    <div class="alert alert-warning">
        <i class="fas fa-info-circle me-2"></i>
        <strong>Refund:</strong>
        <?= $hours_left >= 24 ? '24+ hours before → <strong>80% refund</strong>' : 'Less than 24 hours → <strong>50% refund</strong>' ?><br>
        <strong>Estimated Refund: ৳<?= $est_refund ?></strong>
        <?php if (!($booking['passenger_email'] ?? '')): ?>
        <hr class="my-2">
        <i class="fas fa-exclamation-triangle text-warning me-1"></i>
        <strong>Note:</strong> No email on file — cancellation email will NOT be sent.
        <?php endif; ?>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Reason for Cancellation</label>
                    <select class="form-select" name="cancel_reason">
                        <option value="Cancelled by admin">Cancelled by admin</option>
                        <option value="Bus breakdown">Bus breakdown</option>
                        <option value="Route cancelled">Route cancelled</option>
                        <option value="Passenger request">Passenger request</option>
                        <option value="Duplicate booking">Duplicate booking</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-danger btn-lg w-100"
                    onclick="return confirm('Cancel booking #<?= $id ?> and send refund email?')">
                    <i class="fas fa-times me-2"></i>
                    Confirm Cancel & Issue ৳<?= $est_refund ?> Refund
                    <?php if ($booking['passenger_email']): ?>
                    + Send Email
                    <?php endif; ?>
                </button>
            </form>
        </div>
    </div>

    <?php else: ?>
    <div class="alert alert-danger">This booking is already cancelled.</div>
    <a href="total_bookings.php" class="btn btn-success">← Back</a>
    <?php endif; ?>

    <?php endif; ?>

</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
