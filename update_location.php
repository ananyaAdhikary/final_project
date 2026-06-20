<?php
require_once 'config/database.php';
$conn = getConnection();

// Safe migration: each bus gets a 4-digit driver PIN, auto-generated if missing
$conn->query("ALTER TABLE buses ADD COLUMN IF NOT EXISTS driver_pin VARCHAR(10) DEFAULT NULL");
$conn->query("ALTER TABLE buses ADD COLUMN IF NOT EXISTS last_updated TIMESTAMP NULL DEFAULT NULL");
$conn->query("ALTER TABLE buses ADD COLUMN IF NOT EXISTS driver_phone VARCHAR(20) DEFAULT NULL");

// Auto-assign a PIN to any bus that doesn't have one yet
$noPin = $conn->query("SELECT id FROM buses WHERE driver_pin IS NULL OR driver_pin = ''");
while ($row = $noPin->fetch_assoc()) {
    $pin = (string) random_int(1000, 9999);
    $upd = $conn->prepare("UPDATE buses SET driver_pin = ? WHERE id = ?");
    $upd->bind_param("si", $pin, $row['id']);
    $upd->execute();
}

// ── AJAX: Driver app submits GPS coordinates ──
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bus_id'], $_POST['latitude'], $_POST['longitude'], $_POST['pin'])) {
    $bus_id    = intval($_POST['bus_id']);
    $latitude  = (float) $_POST['latitude'];
    $longitude = (float) $_POST['longitude'];
    $pin       = sanitize($_POST['pin']);

    header('Content-Type: application/json');

    // Verify the PIN matches this bus
    $check = $conn->prepare("SELECT id, bus_name FROM buses WHERE id = ? AND driver_pin = ?");
    $check->bind_param("is", $bus_id, $pin);
    $check->execute();
    $bus = $check->get_result()->fetch_assoc();

    if (!$bus) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid PIN for this bus.']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE buses SET current_lat = ?, current_lng = ?, last_updated = NOW() WHERE id = ?");
    $stmt->bind_param("ddi", $latitude, $longitude, $bus_id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Location updated', 'bus_name' => $bus['bus_name'], 'time' => date('h:i:s A')]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update location']);
    }
    exit;
}

$buses_query = "SELECT id, bus_name, bus_number, driver_pin FROM buses WHERE status = 'active' ORDER BY bus_name";
$buses_result = $conn->query($buses_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Driver — Share Live Location | BD Bus Track</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
:root{--primary:#16a34a;--primary-dark:#15803d;--dark:#0f172a;--bg:#f8fafc;--border:#e2e8f0;}
*{box-sizing:border-box;}
body{font-family:'Inter',sans-serif;background:var(--dark);min-height:100vh;margin:0;color:#1e293b;}
.wrap{max-width:480px;margin:0 auto;padding:24px 16px 60px;}
.brand{text-align:center;color:white;font-weight:900;font-size:20px;margin-bottom:4px;}
.brand span{color:#4ade80;}
.sub{text-align:center;color:rgba(255,255,255,.5);font-size:13px;margin-bottom:24px;}
.card{background:white;border-radius:16px;padding:24px 20px;box-shadow:0 10px 30px rgba(0,0,0,.3);}
.form-label{font-size:11px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;display:block;}
.form-control,.form-select{border:1.5px solid var(--border)!important;border-radius:9px!important;padding:11px 14px!important;font-family:'Inter',sans-serif!important;font-weight:600!important;font-size:14px!important;}
.form-control:focus,.form-select:focus{border-color:var(--primary)!important;box-shadow:0 0 0 3px rgba(22,163,74,.12)!important;}
.btn-share{background:var(--primary);color:white;border:none;border-radius:10px;padding:14px;font-weight:800;font-size:16px;width:100%;display:flex;align-items:center;justify-content:center;gap:8px;}
.btn-share:active{background:var(--primary-dark);}
.btn-share:disabled{opacity:.6;}
.status-box{border-radius:12px;padding:14px 16px;font-size:13px;font-weight:600;margin-top:16px;display:none;}
.status-ok{background:#f0fdf4;border:1.5px solid #bbf7d0;color:#15803d;}
.status-err{background:#fef2f2;border:1.5px solid #fecaca;color:#dc2626;}
.pulse{display:inline-block;width:10px;height:10px;background:#22c55e;border-radius:50%;margin-right:6px;animation:pulse 1.4s infinite;}
@keyframes pulse{0%{box-shadow:0 0 0 0 rgba(34,197,94,.6);}70%{box-shadow:0 0 0 8px rgba(34,197,94,0);}100%{box-shadow:0 0 0 0 rgba(34,197,94,0);}}
.live-badge{display:none;align-items:center;justify-content:center;gap:6px;background:#0f172a;color:white;border-radius:10px;padding:10px;font-size:13px;font-weight:700;margin-top:14px;}
.coords{font-size:11px;color:#6b7280;text-align:center;margin-top:8px;font-family:monospace;}
.pin-info{background:#fffbeb;border:1px solid #fde68a;border-radius:9px;padding:10px 12px;font-size:12px;color:#92400e;margin-top:14px;}
</style>
</head>
<body>
<div class="wrap">
  <div class="brand">BD Bus <span>Track</span></div>
  <div class="sub">Driver Live Location Sharing</div>

  <div class="card">
    <form id="locationForm">
      <div class="mb-3">
        <label class="form-label">Select Your Bus</label>
        <select class="form-select" id="bus_id" name="bus_id" required onchange="showPinHint()">
          <option value="">Choose a bus</option>
          <?php while ($bus = $buses_result->fetch_assoc()): ?>
          <option value="<?= $bus['id'] ?>" data-pin="<?= $bus['driver_pin'] ?>" <?= (isset($_GET['bus_id']) && intval($_GET['bus_id']) === (int)$bus['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($bus['bus_name'] . ' (' . $bus['bus_number'] . ')') ?>
          </option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label">Driver PIN</label>
        <input type="text" class="form-control" id="pin" name="pin" placeholder="4-digit PIN" maxlength="6" required inputmode="numeric">
      </div>
      <button type="submit" class="btn-share" id="shareBtn">
        <i class="fas fa-location-arrow"></i> Start Sharing Live Location
      </button>
    </form>

    <div class="live-badge" id="liveBadge">
      <span class="pulse"></span> Sharing live location...
    </div>
    <div class="coords" id="coordsDisplay"></div>

    <div class="status-box" id="statusBox"></div>

    <div class="pin-info">
      <i class="fas fa-info-circle me-1"></i>
      Don't know the PIN? Ask the admin — each bus has its own PIN, visible in <strong>Manage Buses</strong>.
    </div>
  </div>
</div>

<script>
let watchId = null;
let busSelect = document.getElementById('bus_id');
let pinInput  = document.getElementById('pin');
let form      = document.getElementById('locationForm');
let shareBtn  = document.getElementById('shareBtn');
let liveBadge = document.getElementById('liveBadge');
let statusBox = document.getElementById('statusBox');
let coordsDisplay = document.getElementById('coordsDisplay');

function showPinHint() {
    // Just a UX nicety; PIN is still required & verified server-side
}

// If a bus was pre-selected via ?bus_id= in the URL, jump focus to PIN field
document.addEventListener('DOMContentLoaded', function() {
    if (busSelect.value) {
        pinInput.focus();
    }
});

function showStatus(msg, ok) {
    statusBox.textContent = msg;
    statusBox.className = 'status-box ' + (ok ? 'status-ok' : 'status-err');
    statusBox.style.display = 'block';
}

function sendLocation(lat, lng) {
    const formData = new FormData();
    formData.append('bus_id', busSelect.value);
    formData.append('pin', pinInput.value);
    formData.append('latitude', lat);
    formData.append('longitude', lng);

    fetch('update_location.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                coordsDisplay.textContent = `${lat.toFixed(5)}, ${lng.toFixed(5)} · updated ${data.time}`;
                statusBox.style.display = 'none';
            } else {
                showStatus(data.message, false);
                stopSharing();
            }
        })
        .catch(() => showStatus('Network error — could not reach server.', false));
}

function startSharing() {
    if (!navigator.geolocation) {
        showStatus('Geolocation is not supported by this browser.', false);
        return;
    }
    watchId = navigator.geolocation.watchPosition(
        (pos) => sendLocation(pos.coords.latitude, pos.coords.longitude),
        (err) => showStatus('Location error: ' + err.message, false),
        { enableHighAccuracy: true, maximumAge: 5000, timeout: 15000 }
    );
    liveBadge.style.display = 'flex';
    shareBtn.innerHTML = '<i class="fas fa-stop-circle"></i> Stop Sharing';
    shareBtn.style.background = '#dc2626';
    busSelect.disabled = true;
    pinInput.disabled = true;
}

function stopSharing() {
    if (watchId !== null) navigator.geolocation.clearWatch(watchId);
    watchId = null;
    liveBadge.style.display = 'none';
    shareBtn.innerHTML = '<i class="fas fa-location-arrow"></i> Start Sharing Live Location';
    shareBtn.style.background = '#16a34a';
    busSelect.disabled = false;
    pinInput.disabled = false;
}

form.addEventListener('submit', function(e) {
    e.preventDefault();
    if (watchId === null) {
        startSharing();
    } else {
        stopSharing();
    }
});
</script>
</body>
</html>
