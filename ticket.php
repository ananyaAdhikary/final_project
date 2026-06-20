<?php
require_once 'config/database.php';
$conn = getConnection();

// Safe migration: in case this is the first page to run
$conn->query("ALTER TABLE buses ADD COLUMN IF NOT EXISTS driver_pin VARCHAR(10) DEFAULT NULL");

$id = intval($_GET['id'] ?? 0);
if (!$id) redirect('index.html');

$stmt = $conn->prepare(
    "SELECT bk.*, b.bus_name, b.bus_number, b.departure_time, b.arrival_time, b.driver_pin,
            r.from_city, r.to_city, r.duration, r.fare
     FROM bookings bk
     JOIN buses b ON bk.bus_id = b.id
     JOIN routes r ON bk.route_id = r.id
     WHERE bk.id = ?"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$t = $stmt->get_result()->fetch_assoc();

if (!$t) {
    echo "<div style='text-align:center;padding:60px;font-family:Arial'><h3>Ticket #$id not found.</h3><a href='index.html'>Go Home</a></div>";
    exit();
}

// Build the live-tracking URL for this ticket's bus (works with ngrok too, since it reads the current Host header)
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'];
$dir    = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$trackingUrl = $scheme . '://' . $host . $dir . '/tracking.php?bus_id=' . $t['bus_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ticket #<?= $t['id'] ?> | BD Bus Track</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<style>
:root{--primary:#16a34a;--primary-dark:#15803d;--primary-light:#dcfce7;--accent:#f97316;--dark:#0f172a;--text:#1e293b;--muted:#64748b;--border:#e2e8f0;--bg:#f8fafc;}
body { background: var(--bg); font-family: 'Inter', sans-serif; color: var(--text); margin: 0; }

/* ── Action Bar ── */
.action-bar {
    background: white;
    border-bottom: 1px solid #dee2e6;
    padding: 12px 20px;
    display: flex;
    gap: 10px;
    align-items: center;
    position: sticky;
    top: 0;
    z-index: 10;
    box-shadow: 0 2px 6px rgba(0,0,0,.08);
}

/* ── Ticket wrapper ── */
.ticket-wrap { max-width: 680px; margin: 30px auto; padding: 0 16px 50px; }

/* ── Top banner ── */
.ticket-banner {
    background: linear-gradient(135deg, #1a8a4a, #16a34a);
    color: white;
    border-radius: 14px 14px 0 0;
    padding: 28px 32px;
    text-align: center;
}
.ticket-banner .check-icon { font-size: 50px; margin-bottom: 8px; }
.ticket-banner h2 { font-size: 24px; font-weight: 800; margin: 0 0 4px; }
.ticket-banner p  { font-size: 13px; opacity: .85; margin: 0; }

/* ── Main ticket card ── */
.ticket-card {
    background: white;
    border-radius: 0 0 14px 14px;
    box-shadow: 0 6px 28px rgba(0,0,0,.12);
    overflow: hidden;
    position: relative;
}
<?php if($t['status']==='cancelled'): ?>
.ticket-card::after {
    content: 'CANCELLED';
    position: absolute;
    font-size: 72px;
    font-weight: 900;
    color: rgba(220,53,69,.07);
    transform: translate(-50%,-50%) rotate(-25deg);
    top: 50%; left: 50%;
    pointer-events: none;
    white-space: nowrap;
}
<?php endif; ?>

/* ── Booking ID row ── */
.id-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 18px 28px;
    border-bottom: 2px dashed #dee2e6;
    background: #f8f9fa;
}
.bid-box { text-align: center; }
.bid-label { font-size: 10px; font-weight: 800; letter-spacing: 1px; color: #6c757d; text-transform: uppercase; }
.bid-num   { font-size: 36px; font-weight: 900; color: #16a34a; line-height: 1; }
.status-pill {
    padding: 6px 18px;
    border-radius: 20px;
    font-weight: 800;
    font-size: 13px;
    color: white;
    background: <?= $t['status']==='confirmed' ? '#16a34a' : ($t['status']==='cancelled' ? '#dc3545' : '#0dcaf0') ?>;
}

/* ── Route section ── */
.route-row {
    display: flex;
    align-items: center;
    padding: 22px 28px;
    border-bottom: 1px solid #f0f0f0;
    gap: 10px;
}
.city-col { flex: 1; }
.city-name { font-size: 26px; font-weight: 900; color: #1a2332; }
.city-time { font-size: 18px; font-weight: 800; margin-top: 3px; }
.city-date { font-size: 12px; color: #f97316; font-weight: 700; margin-top: 2px; }
.city-col.right { text-align: right; }
.mid-col   { flex: 1; text-align: center; }
.duration-pill {
    display: inline-block;
    background: #e9f5ff;
    color: #16a34a;
    border-radius: 20px;
    padding: 4px 14px;
    font-size: 12px;
    font-weight: 700;
    margin-bottom: 8px;
}
.arrow-line {
    height: 2px;
    background: linear-gradient(to right, #16a34a, #f97316, #16a34a);
    border-radius: 2px;
    margin: 4px 10px;
    position: relative;
}
.arrow-dot {
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%,-50%);
    width: 10px; height: 10px;
    background: #f97316;
    border-radius: 50%;
    border: 2px solid white;
    box-shadow: 0 0 0 2px #f97316;
}

/* ── Details grid ── */
.details-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 0;
    border-bottom: 1px solid #f0f0f0;
}
.detail-cell {
    padding: 14px 18px;
    border-right: 1px solid #f0f0f0;
    border-bottom: 1px solid #f0f0f0;
}
.detail-cell:nth-child(3n) { border-right: none; }
.detail-label { font-size: 10px; font-weight: 800; letter-spacing: .8px; color: #6c757d; text-transform: uppercase; margin-bottom: 4px; }
.detail-val   { font-size: 14px; font-weight: 800; color: #1a2332; }

/* ── Bottom fare row ── */
.fare-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 18px 28px;
    background: #f8fffe;
}
.fare-total { font-size: 32px; font-weight: 900; color: #16a34a; }
.fare-lbl   { font-size: 11px; font-weight: 700; color: #6c757d; text-transform: uppercase; letter-spacing: .8px; }
.ref-box {
    text-align: center;
    background: #f8f9fa;
    border: 2px solid #dee2e6;
    border-radius: 10px;
    padding: 12px 20px;
    min-width: 130px;
}
.ref-num    { font-size: 13px; font-weight: 900; color: #16a34a; letter-spacing: 2px; }
.ref-label  { font-size: 10px; font-weight: 700; color: #6c757d; margin-top: 3px; }

/* ── Live tracking QR ── */
.qr-row {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 18px 28px;
    border-top: 1px dashed #dee2e6;
    background: white;
}
.qr-box-ticket { flex-shrink: 0; background: white; padding: 8px; border: 2px solid #dee2e6; border-radius: 10px; }
.qr-box-ticket canvas, .qr-box-ticket img { display: block; }
.qr-text-title { font-size: 14px; font-weight: 800; color: #1a2332; margin-bottom: 3px; }
.qr-text-sub   { font-size: 12px; color: #6c757d; font-weight: 600; }
.qr-text-sub i { color: #16a34a; }

/* ── Refund info ── */
.refund-row {
    padding: 12px 28px;
    background: #fff5f5;
    border-top: 1px dashed #f5c6cb;
    font-size: 13px;
    font-weight: 700;
    color: #dc3545;
}

/* ── Policy card ── */
.policy-card {
    background: white;
    border-radius: 12px;
    border: 1px solid #dee2e6;
    padding: 18px 22px;
    margin-top: 14px;
}
.policy-title { font-size: 14px; font-weight: 800; margin-bottom: 10px; color: #1a2332; }
.policy-item  { display: flex; gap: 10px; font-size: 13px; font-weight: 600; color: #6c757d; padding: 4px 0; }

/* ── PRINT ── */
@media print {
    body          { background: white; }
    .action-bar,
    .policy-card,
    .no-print     { display: none !important; }
    .ticket-wrap  { max-width: 100%; margin: 0; padding: 0; }
    .ticket-banner{ border-radius: 0; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
    .ticket-card  { box-shadow: none; border-radius: 0; }
    @page { margin: 8mm; size: A4; }
}
</style>
</head>
<body>

<!-- Action Bar (hidden on print) -->
<div class="action-bar no-print">
    <?php
    $from = $_GET['from'] ?? '';
    if ($from === 'admin' || isAdmin()) {
        $back_url   = 'admin/total_bookings.php';
        $back_label = 'Admin Bookings';
    } elseif (isRegisteredUser()) {
        $back_url   = 'dashboard.php';
        $back_label = 'My Dashboard';
    } else {
        $back_url   = 'index.html';
        $back_label = 'Home';
    }
    ?>
    <a href="<?= $back_url ?>" class="btn btn-outline-success btn-sm">
        <i class="fas fa-arrow-left me-1"></i><?= $back_label ?>
    </a>
    <button onclick="window.print()" class="btn btn-primary btn-sm">
        <i class="fas fa-print me-1"></i>Print Ticket
    </button>
    <?php if($t['status'] === 'confirmed'): ?>
    <a href="cancel_ticket.php?id=<?= $t['id'] ?>" class="btn btn-outline-danger btn-sm ms-auto">
        <i class="fas fa-times me-1"></i>Cancel Ticket
    </a>
    <?php endif; ?>
</div>

<!-- Ticket -->
<div class="ticket-wrap" id="ticketContent">

    <!-- Banner -->
    <div class="ticket-banner">
        <div class="check-icon">
            <?= $t['status']==='confirmed' ? '✅' : ($t['status']==='cancelled' ? '❌' : '🎟️') ?>
        </div>
        <h2><?= $t['status']==='confirmed' ? 'Booking Confirmed!' : ucfirst($t['status']) ?></h2>
        <p>
            <?= $t['status']==='confirmed'
                ? 'Your e-ticket is ready. Have a safe journey! 🙏'
                : 'This ticket has been ' . $t['status'] ?>
        </p>
    </div>

    <div class="ticket-card">

        <!-- Booking ID + Status -->
        <div class="id-row">
            <div style="font-size:13px;color:#6c757d;font-weight:700">
                <i class="fas fa-bus me-2 text-primary"></i>BD Bus Track
            </div>
            <div class="bid-box">
                <div class="bid-label">Booking ID</div>
                <div class="bid-num">#<?= $t['id'] ?></div>
            </div>
            <div class="status-pill"><?= strtoupper($t['status']) ?></div>
        </div>

        <!-- Route -->
        <div class="route-row">
            <div class="city-col">
                <div class="city-name"><?= $t['from_city'] ?></div>
                <div class="city-time"><?= date('h:i A', strtotime($t['departure_time'])) ?></div>
                <div class="city-date"><?= date('D, d M Y', strtotime($t['journey_date'])) ?></div>
            </div>
            <div class="mid-col">
                <div class="duration-pill">
                    <i class="fas fa-clock me-1"></i><?= $t['duration'] ?? '—' ?>
                </div>
                <div class="arrow-line"><div class="arrow-dot"></div></div>
                <div style="font-size:11px;font-weight:700;color:#6c757d;margin-top:4px">DIRECT</div>
            </div>
            <div class="city-col right">
                <div class="city-name"><?= $t['to_city'] ?></div>
                <div class="city-time"><?= date('h:i A', strtotime($t['arrival_time'])) ?></div>
                <div class="city-date"><?= date('D, d M Y', strtotime($t['journey_date'])) ?></div>
            </div>
        </div>

        <!-- Details Grid -->
        <div class="details-grid">
            <div class="detail-cell">
                <div class="detail-label">Passenger</div>
                <div class="detail-val"><?= htmlspecialchars($t['passenger_name']) ?></div>
            </div>
            <div class="detail-cell">
                <div class="detail-label">Phone</div>
                <div class="detail-val"><?= htmlspecialchars($t['passenger_phone']) ?></div>
            </div>
            <div class="detail-cell">
                <div class="detail-label">Email</div>
                <div class="detail-val" style="font-size:12px"><?= htmlspecialchars($t['passenger_email'] ?? '—') ?></div>
            </div>
            <div class="detail-cell">
                <div class="detail-label">Bus</div>
                <div class="detail-val"><?= htmlspecialchars($t['bus_name']) ?></div>
            </div>
            <div class="detail-cell">
                <div class="detail-label">Bus No.</div>
                <div class="detail-val"><?= htmlspecialchars($t['bus_number']) ?></div>
            </div>
            <div class="detail-cell">
                <div class="detail-label">Bus Type</div>
                <div class="detail-val">
                    <span class="badge <?= $t['bus_type']==='AC'?'bg-primary':'bg-secondary' ?>">
                        <?= $t['bus_type'] ?? 'N/A' ?>
                    </span>
                </div>
            </div>
            <div class="detail-cell">
                <div class="detail-label">Seat(s)</div>
                <div class="detail-val" style="color:#16a34a"><?= htmlspecialchars($t['seat_numbers']) ?></div>
            </div>
            <div class="detail-cell">
                <div class="detail-label">Passengers</div>
                <div class="detail-val"><?= $t['passenger_count'] ?? 1 ?></div>
            </div>
            <div class="detail-cell">
                <div class="detail-label">Payment</div>
                <div class="detail-val"><?= ucfirst($t['payment_method'] ?? 'Cash') ?></div>
            </div>
            <?php if (!empty($t['transaction_id'])): ?>
            <div class="detail-cell">
                <div class="detail-label">Transaction ID</div>
                <div class="detail-val" style="font-family:monospace;font-size:13px;letter-spacing:.5px;"><?= htmlspecialchars($t['transaction_id']) ?></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Fare + Reference -->
        <div class="fare-row">
            <div>
                <div class="fare-lbl">Total Fare Paid</div>
                <div class="fare-total">৳<?= number_format($t['total_fare']) ?></div>
            </div>
            <div class="ref-box">
                <div style="font-size:26px">🎟️</div>
                <div class="ref-num"><?= sprintf('%08d', $t['id']) ?></div>
                <div class="ref-label">Reference No.</div>
            </div>
        </div>

        <?php if($t['status']==='confirmed'): ?>
        <div class="qr-row">
            <div class="qr-box-ticket" id="qrTicketBox"></div>
            <div>
                <div class="qr-text-title"><i class="fas fa-route me-1" style="color:#16a34a"></i>Track Your Bus Live</div>
                <div class="qr-text-sub"><i class="fas fa-mobile-alt me-1"></i>Scan with your phone camera to see <?= htmlspecialchars($t['bus_name']) ?>'s live location</div>
                <?php if (!empty($t['driver_pin'])): ?>
                <div class="qr-text-sub" style="margin-top:6px;">
                    <i class="fas fa-key me-1"></i>Bus Tracking PIN:
                    <span style="font-family:monospace;font-weight:900;letter-spacing:3px;color:#0f172a;background:#f1f5f9;padding:2px 8px;border-radius:6px;"><?= htmlspecialchars($t['driver_pin']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if($t['status']==='cancelled' && floatval($t['refund_amount'] ?? 0) > 0): ?>
        <div class="refund-row">
            <i class="fas fa-undo me-2"></i>
            Refund Amount: <strong>৳<?= number_format($t['refund_amount']) ?></strong>
            — Will be processed within 3–5 business days
        </div>
        <?php endif; ?>

    </div><!-- .ticket-card -->

    <!-- Policy (hidden on print) -->
    <div class="policy-card no-print">
        <div class="policy-title"><i class="fas fa-info-circle me-2 text-warning"></i>Cancellation & Refund Policy</div>
        <div class="policy-item"><i class="fas fa-check-circle text-success mt-1"></i> Cancel 24+ hours before journey → <strong>80% refund</strong></div>
        <div class="policy-item"><i class="fas fa-check-circle text-warning mt-1"></i> Cancel within 24 hours → <strong>50% refund</strong></div>
        <div class="policy-item"><i class="fas fa-times-circle text-danger mt-1"></i> No refund after journey date has passed</div>
    </div>

</div><!-- .ticket-wrap -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if($t['status']==='confirmed'): ?>
<script>
new QRCode(document.getElementById('qrTicketBox'), {
    text: <?= json_encode($trackingUrl) ?>,
    width: 90,
    height: 90,
    colorDark: '#0f172a',
    colorLight: '#ffffff'
});
</script>
<?php endif; ?>
</body>
</html>
