<?php
require_once 'config/database.php';
$conn = getConnection();

$booking_id = intval($_GET['id'] ?? 0);
$booking = null;
$message = null;
$error = null;

if ($booking_id) {
    $stmt = $conn->prepare("SELECT bk.*, b.bus_name, b.bus_number, r.from_city, r.to_city FROM bookings bk JOIN buses b ON bk.bus_id=b.id JOIN routes r ON bk.route_id=r.id WHERE bk.id=?");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $booking) {
    $reason = sanitize($_POST['cancel_reason'] ?? 'User requested cancellation');

    // Refund: 80% if > 24hrs before journey, 50% if < 24hrs
    $journey = strtotime($booking['journey_date']);
    $now = time();
    $hours_left = ($journey - $now) / 3600;
    $refund_pct = ($hours_left >= 24) ? 0.80 : 0.50;
    if ($booking['status'] === 'cancelled') {
        $error = "This booking is already cancelled.";
    } elseif ($hours_left <= 0) {
        $error = "Cannot cancel: journey date has passed.";
    } else {
        $refund = round($booking['total_fare'] * $refund_pct, 2);
        $upd = $conn->prepare("UPDATE bookings SET status='cancelled', cancel_reason=?, refund_amount=?, payment_status='refunded' WHERE id=?");
        $upd->bind_param("sdi", $reason, $refund, $booking_id);
        if ($upd->execute()) {
            $message = "Booking #$booking_id cancelled. Refund: ৳$refund (" . ($refund_pct*100) . "%)";
            $booking['status'] = 'cancelled';
            // Send cancellation email
            $email_to = $booking['passenger_email'] ?? '';
            if ($email_to) {
                sendCancelEmail($email_to, $booking['passenger_name'], $booking_id, $booking['from_city'], $booking['to_city'], $booking['journey_date'], $booking['seat_numbers'], $refund, $reason);
            }
        } else {
            $error = "Cancellation failed. Try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cancel Ticket - BD Bus Track</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-dark" style="background:#0f172a"">
    <div class="container">
        <a class="navbar-brand" href="index.html"><i class="fas fa-bus me-2"></i>BD Bus Track</a>
        <a href="<?= isRegisteredUser()?'dashboard.php':'guest_booking.php' ?>" class="btn btn-outline-light btn-sm">Back</a>
    </div>
</nav>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-7">
            <h3><i class="fas fa-times-circle text-danger me-2"></i>Cancel Ticket</h3>

            <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i><?= $message ?>
                <div class="mt-2"><small>Refund will be processed to your original payment method within 3-5 business days.</small></div>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><?= $error ?></div>
            <?php endif; ?>

            <?php if ($booking): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Booking Details #<?= $booking['id'] ?></h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6"><strong>Passenger:</strong> <?= $booking['passenger_name'] ?></div>
                        <div class="col-6"><strong>Phone:</strong> <?= $booking['passenger_phone'] ?></div>
                        <div class="col-6 mt-2"><strong>Route:</strong> <?= $booking['from_city'] ?> → <?= $booking['to_city'] ?></div>
                        <div class="col-6 mt-2"><strong>Bus:</strong> <?= $booking['bus_name'] ?></div>
                        <div class="col-6 mt-2"><strong>Journey Date:</strong> <?= date('d M Y', strtotime($booking['journey_date'])) ?></div>
                        <div class="col-6 mt-2"><strong>Seats:</strong> <?= $booking['seat_numbers'] ?></div>
                        <div class="col-6 mt-2"><strong>Total Fare:</strong> ৳<?= number_format($booking['total_fare']) ?></div>
                        <div class="col-6 mt-2"><strong>Status:</strong>
                            <span class="badge <?= $booking['status']=='confirmed'?'bg-purple" style="background:#16a34a':'bg-danger' ?>"><?= ucfirst($booking['status']) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($booking['status'] === 'confirmed'): ?>
            <?php
            $hours_left = (strtotime($booking['journey_date']) - time()) / 3600;
            $refund_pct = ($hours_left >= 24) ? 80 : 50;
            $est_refund = round($booking['total_fare'] * $refund_pct / 100, 2);
            ?>
            <div class="alert alert-warning">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Refund Policy:</strong>
                Cancellation <?= $hours_left >= 24 ? '24+ hours before journey = <strong>80% refund</strong>' : 'less than 24 hours before journey = <strong>50% refund</strong>' ?><br>
                <strong>Estimated Refund: ৳<?= $est_refund ?></strong>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Reason for Cancellation (optional)</label>
                            <select class="form-select" name="cancel_reason">
                                <option value="Change of plans">Change of plans</option>
                                <option value="Emergency">Emergency</option>
                                <option value="Wrong booking">Wrong booking details</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-danger btn-lg w-100" onclick="return confirm('Are you sure you want to cancel this booking?')">
                            <i class="fas fa-times me-2"></i>Confirm Cancellation & Get ৳<?= $est_refund ?> Refund
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
            <?php else: ?>
            <div class="alert alert-danger">Booking not found. Please check the booking ID.</div>
            <a href="<?= isRegisteredUser()?'dashboard.php':'guest_booking.php' ?>" class="btn btn-primary">Go Back</a>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
