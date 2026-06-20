<?php
require_once '../config/database.php';
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') redirect('../login.php');

$conn = getConnection();

// Safe migrations (in case this page is opened first)
$conn->query("ALTER TABLE buses ADD COLUMN IF NOT EXISTS driver_pin VARCHAR(10) DEFAULT NULL");
$conn->query("ALTER TABLE buses ADD COLUMN IF NOT EXISTS last_updated TIMESTAMP NULL DEFAULT NULL");
$conn->query("ALTER TABLE buses ADD COLUMN IF NOT EXISTS driver_phone VARCHAR(20) DEFAULT NULL");
$noPin = $conn->query("SELECT id FROM buses WHERE driver_pin IS NULL OR driver_pin = ''");
while ($row = $noPin->fetch_assoc()) {
    $pin = (string) random_int(1000, 9999);
    $upd = $conn->prepare("UPDATE buses SET driver_pin = ? WHERE id = ?");
    $upd->bind_param("si", $pin, $row['id']);
    $upd->execute();
}

// Detect current base URL automatically (works with ngrok too, since it forwards Host header)
$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST'];
$scriptDir = str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))); // one level up from /admin
$baseUrl = $scheme . '://' . $host . $scriptDir;

$buses = $conn->query("SELECT b.*, r.from_city, r.to_city FROM buses b LEFT JOIN routes r ON b.route_id = r.id WHERE b.status='active' ORDER BY b.bus_name")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Driver Links — BD Bus Track</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<style>
:root{--primary:#16a34a;--primary-dark:#15803d;--dark:#0f172a;--bg:#f8fafc;--border:#e2e8f0;--text:#111827;--muted:#6b7280;}
*{box-sizing:border-box;}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);margin:0;}
.topnav{background:#15803d;padding:0 28px;height:62px;display:flex;align-items:center;box-shadow:0 2px 12px rgba(15,23,42,.4);}
.brand{font-size:20px;font-weight:900;color:white;text-decoration:none;letter-spacing:-.3px;}
.brand span{color:rgba(255,255,255,.45);font-weight:400;}
.nav-a{color:rgba(255,255,255,.65);text-decoration:none;font-size:13px;font-weight:600;padding:6px 12px;border-radius:7px;transition:all .15s;}
.nav-a:hover{color:white;background:rgba(255,255,255,.12);}
.page-head{max-width:1100px;margin:28px auto 18px;padding:0 20px;}
.page-title{font-size:24px;font-weight:900;letter-spacing:-.5px;}
.page-sub{font-size:13px;color:var(--muted);margin-top:2px;}
.main-wrap{max-width:1100px;margin:0 auto 50px;padding:0 20px;}
.base-url-card{background:#0f172a;border-radius:14px;padding:18px 20px;color:white;margin-bottom:24px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;}
.base-url-card input{flex:1;min-width:240px;background:rgba(255,255,255,.08);border:1.5px solid rgba(255,255,255,.15);border-radius:8px;padding:9px 12px;color:white;font-family:monospace;font-size:13px;}
.base-url-card .hint{font-size:12px;color:rgba(255,255,255,.5);flex-basis:100%;}
.bus-card{background:white;border:1.5px solid var(--border);border-radius:14px;padding:18px 20px;margin-bottom:16px;box-shadow:0 2px 8px rgba(0,0,0,.04);}
.bus-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;}
.bus-name{font-size:16px;font-weight:800;}
.bus-route{font-size:12px;color:var(--muted);margin-top:2px;}
.pin-badge{background:#0f172a;color:white;font-weight:800;letter-spacing:2px;padding:6px 14px;border-radius:8px;font-size:14px;}
.link-row{display:flex;gap:8px;align-items:center;margin-top:10px;}
.link-input{flex:1;border:1.5px solid var(--border);border-radius:8px;padding:9px 12px;font-family:monospace;font-size:12px;background:#f8fafc;}
.btn-copy{background:var(--primary);color:white;border:none;border-radius:8px;padding:9px 16px;font-weight:700;font-size:13px;white-space:nowrap;cursor:pointer;}
.btn-copy:hover{background:var(--primary-dark);}
.btn-copy.copied{background:#0f172a;}
.btn-qr{background:white;border:1.5px solid var(--primary);color:var(--primary);border-radius:8px;padding:9px 14px;font-weight:700;font-size:13px;cursor:pointer;}
.btn-qr:hover{background:#f0fdf4;}
.qr-box{margin-top:14px;text-align:center;padding:16px;background:#f8fafc;border-radius:10px;display:none;}
.qr-box img,.qr-box canvas{background:white;padding:10px;border-radius:8px;}
.qr-box p{font-size:12px;color:var(--muted);margin-top:8px;margin-bottom:0;}
.empty-state{text-align:center;padding:60px 20px;color:var(--muted);}
</style>
</head>
<body>

<nav class="topnav">
  <a href="dashboard.php" class="brand">BD Bus <span>Track</span></a>
  <div style="margin-left:auto;display:flex;align-items:center;gap:4px;">
    <a href="dashboard.php" class="nav-a"><i class="fas fa-grid-2 me-1"></i>Dashboard</a>
    <a href="manage_bus.php" class="nav-a"><i class="fas fa-bus me-1"></i>Buses</a>
    <a href="profile.php" class="nav-a"><i class="fas fa-user me-1"></i>Profile</a>
    <a href="../logout.php" class="nav-a" style="margin-left:8px;border:1px solid rgba(255,255,255,.2);border-radius:8px;">Logout</a>
  </div>
</nav>

<div class="page-head">
  <div class="page-title">Driver Links</div>
  <div class="page-sub">Generate a live-location sharing link for each bus driver — send it via SMS, WhatsApp, or QR code</div>
</div>

<div class="main-wrap">

  <!-- <div class="base-url-card">
    <i class="fas fa-globe fa-lg"></i>
    <input type="text" id="baseUrlInput" value="<?= htmlspecialchars($baseUrl) ?>">
    <button class="btn-copy" onclick="copyBaseUrl()" id="baseUrlCopyBtn"><i class="fas fa-copy me-1"></i>Copy</button>
    <div class="hint">
      <i class="fas fa-info-circle me-1"></i>
      This is auto-detected from your current URL. If you're using <strong>ngrok</strong>, open this page through your ngrok URL
      (e.g. <code>https://xxxx.ngrok-free.app/.../admin/driver_links.php</code>) so the links below use the public address — not <code>localhost</code>.
    </div>
  </div> -->

  <?php if (empty($buses)): ?>
  <div class="empty-state">
    <i class="fas fa-bus fa-3x mb-3" style="opacity:.3"></i>
    <p>No active buses found. Add a bus first from <a href="add_bus.php">Add Bus</a>.</p>
  </div>
  <?php else: ?>

  <?php foreach ($buses as $i => $bus):
      $driverUrl = rtrim($baseUrl, '/') . '/update_location.php?bus_id=' . $bus['id'];
  ?>
  <div class="bus-card">
    <div class="bus-head">
      <div>
        <div class="bus-name"><i class="fas fa-bus me-2" style="color:var(--primary)"></i><?= htmlspecialchars($bus['bus_name']) ?> <span style="color:var(--muted);font-weight:500;font-size:13px;">(<?= htmlspecialchars($bus['bus_number']) ?>)</span></div>
        <div class="bus-route"><?= htmlspecialchars(($bus['from_city'] ?? '—') . ' → ' . ($bus['to_city'] ?? '—')) ?></div>
        <?php if (!empty($bus['driver_phone'])): ?>
        <div class="bus-route"><i class="fas fa-phone-alt me-1"></i>Driver: <?= htmlspecialchars($bus['driver_phone']) ?></div>
        <?php else: ?>
        <div class="bus-route" style="color:#dc2626;"><i class="fas fa-exclamation-circle me-1"></i>No driver phone set — <a href="edit_bus.php?id=<?= $bus['id'] ?>">add one</a></div>
        <?php endif; ?>
      </div>
      <div class="pin-badge">PIN: <?= htmlspecialchars($bus['driver_pin']) ?></div>
    </div>

    <div class="link-row">
      <input type="text" class="link-input" id="link-<?= $bus['id'] ?>" value="<?= htmlspecialchars($driverUrl) ?>" readonly>
      <button class="btn-copy" onclick="copyLink(<?= $bus['id'] ?>, this)"><i class="fas fa-copy me-1"></i>Copy Link</button>
      <button class="btn-qr" onclick="toggleQr(<?= $bus['id'] ?>, '<?= htmlspecialchars($driverUrl, ENT_QUOTES) ?>')"><i class="fas fa-qrcode me-1"></i>Show QR</button>
      <?php if (!empty($bus['driver_phone'])):
          $waPhone = preg_replace('/[^0-9]/', '', $bus['driver_phone']);
          if (strlen($waPhone) === 11 && $waPhone[0] === '0') $waPhone = '88' . $waPhone; // BD number → international format
          $waMessage = rawurlencode("Hi! Please open this link and enter PIN {$bus['driver_pin']} to start sharing your bus's live location: {$driverUrl}");
          $waUrl = "https://wa.me/{$waPhone}?text={$waMessage}";
      ?>
      <a class="btn-copy" style="background:#22c55e;text-decoration:none;" href="<?= htmlspecialchars($waUrl) ?>" target="_blank"><i class="fab fa-whatsapp me-1"></i>Send via WhatsApp</a>
      <?php endif; ?>
    </div>

    <div class="qr-box" id="qrbox-<?= $bus['id'] ?>">
      <div id="qrcanvas-<?= $bus['id'] ?>"></div>
      <p>Driver scans this with their phone camera to open the location-sharing page directly.</p>
    </div>
  </div>
  <?php endforeach; ?>

  <?php endif; ?>
</div>

<script>
function copyBaseUrl() {
    const input = document.getElementById('baseUrlInput');
    input.select();
    navigator.clipboard.writeText(input.value).then(() => {
        const btn = document.getElementById('baseUrlCopyBtn');
        btn.innerHTML = '<i class="fas fa-check me-1"></i>Copied!';
        btn.classList.add('copied');
        setTimeout(() => { btn.innerHTML = '<i class="fas fa-copy me-1"></i>Copy'; btn.classList.remove('copied'); }, 1800);
    });
}

function copyLink(busId, btn) {
    const input = document.getElementById('link-' + busId);
    navigator.clipboard.writeText(input.value).then(() => {
        const original = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check me-1"></i>Copied!';
        btn.classList.add('copied');
        setTimeout(() => { btn.innerHTML = original; btn.classList.remove('copied'); }, 1800);
    });
}

const renderedQr = {};
function toggleQr(busId, url) {
    const box = document.getElementById('qrbox-' + busId);
    const isHidden = box.style.display === 'none' || !box.style.display;
    box.style.display = isHidden ? 'block' : 'none';
    if (isHidden && !renderedQr[busId]) {
        new QRCode(document.getElementById('qrcanvas-' + busId), {
            text: url,
            width: 180,
            height: 180,
            colorDark: '#0f172a',
            colorLight: '#ffffff'
        });
        renderedQr[busId] = true;
    }
}
</script>
</body>
</html>
