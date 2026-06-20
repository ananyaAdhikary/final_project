<?php
require_once 'config/database.php';
if (!isRegisteredUser()) redirect('login.php');
$conn = getConnection();
$id = intval($_GET['id'] ?? 0);

// Security: only owner can cancel
$stmt = $conn->prepare("SELECT bk.*, r.from_city, r.to_city, b.bus_name FROM bookings bk JOIN routes r ON bk.route_id=r.id JOIN buses b ON bk.bus_id=b.id WHERE bk.id=? AND bk.user_id=?");
$stmt->bind_param("ii", $id, $_SESSION['user_id']);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) { redirect('dashboard.php'); }

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $reason = sanitize($_POST['cancel_reason'] ?? 'User cancelled');
    $hours_left = (strtotime($booking['journey_date']) - time()) / 3600;
    $refund_pct = ($hours_left >= 24) ? 0.80 : 0.50;
    $refund = round($booking['total_fare'] * $refund_pct, 2);

    if ($hours_left <= 0) {
        $msg = "Cannot cancel: journey date passed."; $err = true;
    } else {
        $upd = $conn->prepare("UPDATE bookings SET status='cancelled', cancel_reason=?, refund_amount=?, payment_status='refunded' WHERE id=? AND user_id=?");
        $upd->bind_param("sdii", $reason, $refund, $id, $_SESSION['user_id']);
        $upd->execute();
        $msg = "Booking #$id cancelled! Refund ৳$refund will be processed in 3-5 business days."; $err = false;
        // Send cancellation email
        $email_to = $booking['passenger_email'] ?? $_SESSION['user_email'] ?? '';
        if ($email_to) {
            sendCancelEmail($email_to, $booking['passenger_name'], $id, $booking['from_city'], $booking['to_city'], $booking['journey_date'], $booking['seat_numbers'], $refund, $reason);
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Cancel Booking</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-dark" style="background:#0f172a""><div class="container">
    <a class="navbar-brand" href="index.html"><i class="fas fa-bus me-2"></i>BD Bus Track</a>
    <a href="dashboard.php" class="btn btn-outline-light btn-sm">← Dashboard</a>
</div></nav>
<div class="container mt-4"><div class="row justify-content-center"><div class="col-md-7">
    <h3><i class="fas fa-times-circle text-danger me-2"></i>Cancel Booking</h3>
    <?php if(isset($msg)): ?>
    <div class="alert alert-<?=$err?'danger':'success'?>"><?=$msg?></div>
    <a href="dashboard.php" class="btn btn-success">Back to Dashboard</a>
    <?php else: ?>
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h6>Booking #<?=$booking['id']?></h6>
            <p><b>Route:</b> <?=$booking['from_city']?> → <?=$booking['to_city']?></p>
            <p><b>Bus:</b> <?=$booking['bus_name']?></p>
            <p><b>Journey:</b> <?=date('d M Y',strtotime($booking['journey_date']))?></p>
            <p><b>Seats:</b> <?=$booking['seat_numbers']?></p>
            <p><b>Fare:</b> ৳<?=number_format($booking['total_fare'])?></p>
        </div>
    </div>
    <?php
    $hours_left=(strtotime($booking['journey_date'])-time())/3600;
    $refund_pct=($hours_left>=24)?80:50;
    $est=round($booking['total_fare']*$refund_pct/100,2);
    ?>
    <div class="alert alert-warning">Refund: <b><?=$refund_pct?>%</b> = <b>৳<?=$est?></b></div>
    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Reason</label>
            <select class="form-select" name="cancel_reason">
                <option>Change of plans</option><option>Emergency</option>
                <option>Wrong booking</option><option>Other</option>
            </select>
        </div>
        <button type="submit" class="btn btn-danger btn-lg w-100" onclick="return confirm('Confirm cancellation?')">
            <i class="fas fa-times me-2"></i>Cancel & Get ৳<?=$est?> Refund
        </button>
    </form>
    <?php endif; ?>
</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
