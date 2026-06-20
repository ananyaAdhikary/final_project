<?php
require_once 'config/database.php';
if (!isLoggedIn()) redirect('login.php');

$conn = getConnection();
$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$success = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $name  = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);

        if (empty($name) || empty($email) || empty($phone)) {
            $error = "Name, email and phone are required.";
        } else {
            $ck = $conn->prepare("SELECT id FROM users WHERE email=? AND id!=?");
            $ck->bind_param("si", $email, $user_id);
            $ck->execute();
            if ($ck->get_result()->num_rows > 0) {
                $error = "Email already used by another account.";
            } else {
                $upd = $conn->prepare("UPDATE users SET name=?, email=?, phone=? WHERE id=?");
                $upd->bind_param("sssi", $name, $email, $phone, $user_id);
                if ($upd->execute()) {
                    $_SESSION['user_name']  = $name;
                    $_SESSION['user_email'] = $email;
                    $_SESSION['user_phone'] = $phone;
                    $success = "Profile updated successfully!";
                    // Refresh
                    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $user = $stmt->get_result()->fetch_assoc();
                } else { $error = "Update failed. Try again."; }
            }
        }
    } elseif (isset($_POST['change_password'])) {
        $current = $_POST['current_password'];
        $new     = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];

        if (empty($current) || empty($new) || empty($confirm)) {
            $error = "All password fields are required.";
        } elseif ($new !== $confirm) {
            $error = "New passwords do not match.";
        } elseif (strlen($new) < 6) {
            $error = "Password must be at least 6 characters.";
        } else {
            $ps = $conn->prepare("SELECT password FROM users WHERE id=?");
            $ps->bind_param("i", $user_id);
            $ps->execute();
            $pw = $ps->get_result()->fetch_assoc();
            // Support both MD5 (old) and bcrypt
            $valid = (md5($current) === $pw['password']) || password_verify($current, $pw['password']);
            if ($valid) {
                $hashed = md5($new); // keep MD5 consistent with login
                $upd = $conn->prepare("UPDATE users SET password=? WHERE id=?");
                $upd->bind_param("si", $hashed, $user_id);
                if ($upd->execute()) $success = "Password changed successfully!";
                else $error = "Failed to change password.";
            } else { $error = "Current password is incorrect."; }
        }
    }
}

// Get booking stats
$stats = $conn->query("SELECT 
    COUNT(*) as total,
    SUM(status='confirmed') as confirmed,
    SUM(status='cancelled') as cancelled,
    SUM(CASE WHEN status='confirmed' THEN total_fare ELSE 0 END) as spent
    FROM bookings WHERE user_id=$user_id")->fetch_assoc();

$initials = strtoupper(substr($user['name'], 0, 2));
$joined   = date('M Y', strtotime($user['created_at'] ?? 'now'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile — BD Bus Track</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
:root {
  --purple: #16a34a;
  --purple-dark: #15803d;
  --purple-mid: #16a34a;
  --primary-light: #dcfce7;
  --white: #ffffff;
  --bg: #f8fafc;
  --border: #e5e7eb;
  --text: #111827;
  --muted: #6b7280;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); }

/* ── NAVBAR ── */
.topnav {
  background: #15803d;
  padding: 0 28px;
  height: 62px;
  display: flex;
  align-items: center;
  position: sticky;
  top: 0;
  z-index: 100;
  box-shadow: 0 2px 12px rgba(15,23,42,.4);
}
.brand { font-size: 20px; font-weight: 900; color: white; text-decoration: none; letter-spacing: -.3px; }
.brand span { color: rgba(255,255,255,.45); font-weight: 400; }
.nav-a { color: rgba(255,255,255,.65); text-decoration: none; font-size: 13px; font-weight: 600; padding: 6px 12px; border-radius: 7px; transition: all .15s; }
.nav-a:hover { color: white; background: rgba(255,255,255,.12); }
.nav-a.active { color: white; }

/* ── HERO BANNER ── */
.profile-hero {
  background: linear-gradient(135deg, #15803d 0%, #16a34a 60%, #16a34a 100%);
  padding: 36px 0 80px;
  position: relative;
  overflow: hidden;
}
.profile-hero::before {
  content: '';
  position: absolute;
  inset: 0;
  background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Ccircle cx='30' cy='30' r='15'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
}
.hero-inner { position: relative; z-index: 1; max-width: 900px; margin: 0 auto; padding: 0 20px; display: flex; align-items: center; gap: 24px; }
.avatar {
  width: 90px; height: 90px;
  border-radius: 50%;
  background: rgba(255,255,255,.15);
  border: 3px solid rgba(255,255,255,.4);
  display: flex; align-items: center; justify-content: center;
  font-size: 32px; font-weight: 900; color: white;
  flex-shrink: 0;
  backdrop-filter: blur(10px);
}
.hero-name { font-size: 26px; font-weight: 900; color: white; letter-spacing: -.5px; }
.hero-email { font-size: 14px; color: rgba(255,255,255,.65); font-weight: 500; margin-top: 3px; }
.hero-badge {
  display: inline-flex; align-items: center; gap: 6px;
  background: rgba(255,255,255,.12);
  border: 1px solid rgba(255,255,255,.2);
  color: rgba(255,255,255,.85);
  padding: 4px 12px; border-radius: 20px;
  font-size: 12px; font-weight: 600; margin-top: 8px;
}

/* ── STAT PILLS ── */
.stats-row {
  max-width: 900px; margin: -36px auto 0; padding: 0 20px;
  position: relative; z-index: 2;
}
.stat-pill {
  background: white;
  border-radius: 12px;
  padding: 16px 18px;
  border: 1.5px solid var(--border);
  box-shadow: 0 4px 16px rgba(59,7,100,.08);
  text-align: center;
}
.stat-pill .num { font-size: 22px; font-weight: 900; color: #16a34a; }
.stat-pill .lbl { font-size: 11px; color: var(--muted); font-weight: 600; margin-top: 2px; text-transform: uppercase; letter-spacing: .4px; }

/* ── MAIN CONTENT ── */
.main { max-width: 900px; margin: 24px auto 40px; padding: 0 20px; }
.section-card {
  background: white;
  border-radius: 14px;
  border: 1.5px solid var(--border);
  overflow: hidden;
  margin-bottom: 20px;
  box-shadow: 0 2px 8px rgba(0,0,0,.04);
}
.section-head {
  background: #16a34a;
  padding: 14px 20px;
  display: flex; align-items: center; gap: 10px;
}
.section-head h5 { color: white; font-size: 14px; font-weight: 800; margin: 0; letter-spacing: .2px; }
.section-head i { color: rgba(255,255,255,.8); font-size: 15px; }
.section-body { padding: 22px 20px; }

/* ── FORM STYLES ── */
.form-label-c { font-size: 11px; font-weight: 700; color: #374151; text-transform: uppercase; letter-spacing: .6px; margin-bottom: 6px; display: block; }
.form-ctrl {
  width: 100%; border: 1.5px solid var(--border); border-radius: 9px;
  padding: 11px 14px; font-family: 'Inter', sans-serif; font-size: 14px; font-weight: 500;
  color: var(--text); outline: none; transition: all .15s; background: white;
}
.form-ctrl:focus { border-color: #16a34a; box-shadow: 0 0 0 3px rgba(22,163,74,.08); }
.icon-input { position: relative; }
.icon-input i { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 13px; }
.icon-input .form-ctrl { padding-left: 38px; }

/* ── BUTTONS ── */
.btn-green-custom {
  background: #16a34a;
  color: white; border: none; border-radius: 9px;
  padding: 11px 22px; font-family: 'Inter', sans-serif; font-weight: 700; font-size: 14px;
  cursor: pointer; transition: all .15s;
}
.btn-green-custom:hover { background: #15803d; box-shadow: 0 4px 12px rgba(59,7,100,.3); }
.btn-outline-green {
  background: white; color: #16a34a;
  border: 1.5px solid #16a34a; border-radius: 9px;
  padding: 10px 20px; font-family: 'Inter', sans-serif; font-weight: 700; font-size: 14px;
  cursor: pointer; transition: all .15s;
}
.btn-outline-green:hover { background: #dcfce7; }

/* ── ALERTS ── */
.alert-s { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; border-radius: 10px; padding: 12px 16px; font-size: 13px; font-weight: 600; margin-bottom: 18px; display: flex; align-items: center; gap: 8px; }
.alert-e { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; border-radius: 10px; padding: 12px 16px; font-size: 13px; font-weight: 600; margin-bottom: 18px; display: flex; align-items: center; gap: 8px; }

/* ── TOGGLE SWITCH ── */
.switch-row { display: flex; align-items: center; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f3f4f6; }
.switch-row:last-child { border: none; }
.switch-label { font-size: 14px; font-weight: 600; color: var(--text); }
.switch-sub { font-size: 12px; color: var(--muted); margin-top: 1px; }
.toggle { position: relative; width: 44px; height: 24px; flex-shrink: 0; }
.toggle input { opacity: 0; width: 0; height: 0; }
.slider { position: absolute; inset: 0; background: #e5e7eb; border-radius: 24px; cursor: pointer; transition: .2s; }
.slider::before { content: ''; position: absolute; height: 18px; width: 18px; left: 3px; bottom: 3px; background: white; border-radius: 50%; transition: .2s; }
input:checked + .slider { background: #16a34a; }
input:checked + .slider::before { transform: translateX(20px); }

/* ── PAYMENT CARD ── */
.pay-card { display: flex; align-items: center; justify-content: space-between; padding: 14px; border: 1.5px solid var(--border); border-radius: 10px; margin-bottom: 10px; }
.pay-card:hover { border-color: #16a34a; }
</style>
</head>
<body>

<!-- Navbar -->
<nav class="topnav">
  <a href="index.html" class="brand">BD Bus <span>Track</span></a>
  <div style="margin-left:auto;display:flex;align-items:center;gap:4px;">
    <a href="dashboard.php" class="nav-a"><i class="fas fa-grid-2 me-1"></i>Dashboard</a>
    <a href="booking.php" class="nav-a"><i class="fas fa-ticket-alt me-1"></i>Book Ticket</a>
    <a href="tracking.php" class="nav-a"><i class="fas fa-satellite-dish me-1"></i>Live Tracking</a>
    <a href="profile.php" class="nav-a active"><i class="fas fa-user me-1"></i>Profile</a>
    <a href="logout.php" class="nav-a" style="margin-left:8px;border:1px solid rgba(255,255,255,.2);border-radius:8px;">Logout</a>
  </div>
</nav>

<!-- Hero Banner -->
<div class="profile-hero">
  <div class="hero-inner">
    <div class="avatar"><?= $initials ?></div>
    <div>
      <div class="hero-name"><?= htmlspecialchars($user['name']) ?></div>
      <div class="hero-email"><i class="fas fa-envelope me-1"></i><?= htmlspecialchars($user['email']) ?></div>
      <div class="hero-badge"><i class="fas fa-bus me-1"></i>Member since <?= $joined ?></div>
    </div>
    <div style="margin-left:auto;text-align:right">
      <div style="color:rgba(255,255,255,.5);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">Phone</div>
      <div style="color:white;font-weight:700;font-size:15px;margin-top:3px;"><?= htmlspecialchars($user['phone'] ?: '—') ?></div>
    </div>
  </div>
</div>

<!-- Stats Pills -->
<div class="stats-row">
  <div class="row g-3">
    <div class="col-3">
      <div class="stat-pill">
        <div class="num"><?= $stats['total'] ?? 0 ?></div>
        <div class="lbl">Total</div>
      </div>
    </div>
    <div class="col-3">
      <div class="stat-pill">
        <div class="num" style="color:#16a34a"><?= $stats['confirmed'] ?? 0 ?></div>
        <div class="lbl">Confirmed</div>
      </div>
    </div>
    <div class="col-3">
      <div class="stat-pill">
        <div class="num" style="color:#dc2626"><?= $stats['cancelled'] ?? 0 ?></div>
        <div class="lbl">Cancelled</div>
      </div>
    </div>
    <div class="col-3">
      <div class="stat-pill">
        <div class="num" style="font-size:18px">৳<?= number_format($stats['spent'] ?? 0) ?></div>
        <div class="lbl">Spent</div>
      </div>
    </div>
  </div>
</div>

<!-- Main Content -->
<div class="main">
  <?php if($success): ?>
  <div class="alert-s"><i class="fas fa-check-circle"></i><?= $success ?></div>
  <?php endif; ?>
  <?php if($error): ?>
  <div class="alert-e"><i class="fas fa-exclamation-circle"></i><?= $error ?></div>
  <?php endif; ?>

  <div class="row g-4">

    <!-- Edit Profile -->
    <div class="col-md-6">
      <div class="section-card">
        <div class="section-head">
          <i class="fas fa-user-edit"></i>
          <h5>Edit Profile</h5>
        </div>
        <div class="section-body">
          <form method="POST">
            <div class="mb-3">
              <label class="form-label-c">Full Name</label>
              <div class="icon-input">
                <i class="fas fa-user"></i>
                <input class="form-ctrl" type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label-c">Email Address</label>
              <div class="icon-input">
                <i class="fas fa-envelope"></i>
                <input class="form-ctrl" type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
              </div>
            </div>
            <div class="mb-4">
              <label class="form-label-c">Phone Number</label>
              <div class="icon-input">
                <i class="fas fa-phone"></i>
                <input class="form-ctrl" type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" required placeholder="01XXXXXXXXX">
              </div>
            </div>
            <button class="btn-green-main" type="submit" name="update_profile">
              <i class="fas fa-save me-2"></i>Save Changes
            </button>
          </form>
        </div>
      </div>
    </div>

    <!-- Change Password -->
    <div class="col-md-6">
      <div class="section-card">
        <div class="section-head">
          <i class="fas fa-lock"></i>
          <h5>Change Password</h5>
        </div>
        <div class="section-body">
          <form method="POST">
            <div class="mb-3">
              <label class="form-label-c">Current Password</label>
              <div class="icon-input">
                <i class="fas fa-lock"></i>
                <input class="form-ctrl" type="password" name="current_password" required>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label-c">New Password</label>
              <div class="icon-input">
                <i class="fas fa-key"></i>
                <input class="form-ctrl" type="password" name="new_password" id="newPwd" required minlength="6">
              </div>
            </div>
            <div class="mb-4">
              <label class="form-label-c">Confirm New Password</label>
              <div class="icon-input">
                <i class="fas fa-key"></i>
                <input class="form-ctrl" type="password" name="confirm_password" id="confPwd" required>
              </div>
              <div id="pwdMatch" style="font-size:12px;margin-top:5px;font-weight:600;display:none"></div>
            </div>
            <button class="btn-green-main" type="submit" name="change_password">
              <i class="fas fa-shield-alt me-2"></i>Change Password
            </button>
          </form>
        </div>
      </div>
    </div>

    <!-- Notifications -->
    <div class="col-md-6">
      <div class="section-card">
        <div class="section-head">
          <i class="fas fa-bell"></i>
          <h5>Notification Settings</h5>
        </div>
        <div class="section-body">
          <div class="switch-row">
            <div>
              <div class="switch-label">Booking Confirmations</div>
              <div class="switch-sub">Email when ticket is confirmed</div>
            </div>
            <label class="toggle"><input type="checkbox" checked><span class="slider"></span></label>
          </div>
          <div class="switch-row">
            <div>
              <div class="switch-label">Cancellation Alerts</div>
              <div class="switch-sub">Email when ticket is cancelled</div>
            </div>
            <label class="toggle"><input type="checkbox" checked><span class="slider"></span></label>
          </div>
          <div class="switch-row">
            <div>
              <div class="switch-label">Promotions & Offers</div>
              <div class="switch-sub">Deals and discounts</div>
            </div>
            <label class="toggle"><input type="checkbox"><span class="slider"></span></label>
          </div>
        </div>
      </div>
    </div>

    <!-- Payment Methods -->
    <div class="col-md-6">
      <div class="section-card">
        <div class="section-head">
          <i class="fas fa-credit-card"></i>
          <h5>Payment Methods</h5>
        </div>
        <div class="section-body">
          <div class="pay-card">
            <div style="display:flex;align-items:center;gap:12px">
              <div style="width:44px;height:30px;background:linear-gradient(135deg,#e0107a,#ff5f00);border-radius:5px;display:flex;align-items:center;justify-content:center;color:white;font-size:10px;font-weight:900">bKash</div>
              <div>
                <div style="font-weight:700;font-size:14px">bKash</div>
                <div style="font-size:12px;color:#6b7280">Mobile Banking</div>
              </div>
            </div>
            <span style="background:#f0fdf4;color:#16a34a;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;">Active</span>
          </div>
          <div class="pay-card">
            <div style="display:flex;align-items:center;gap:12px">
              <div style="width:44px;height:30px;background:linear-gradient(135deg,#f97316,#ef4444);border-radius:5px;display:flex;align-items:center;justify-content:center;color:white;font-size:10px;font-weight:900">Nagad</div>
              <div>
                <div style="font-weight:700;font-size:14px">Nagad</div>
                <div style="font-size:12px;color:#6b7280">Mobile Banking</div>
              </div>
            </div>
            <span style="background:#f0fdf4;color:#16a34a;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;">Active</span>
          </div>
          <div class="pay-card">
            <div style="display:flex;align-items:center;gap:12px">
              <i class="fas fa-money-bill-wave" style="font-size:24px;color:#6b7280;width:44px;text-align:center"></i>
              <div>
                <div style="font-weight:700;font-size:14px">Cash on Board</div>
                <div style="font-size:12px;color:#6b7280">Pay during journey</div>
              </div>
            </div>
            <span style="background:#f0fdf4;color:#16a34a;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;">Active</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Links -->
    <div class="col-12">
      <div class="section-card">
        <div class="section-head">
          <i class="fas fa-bolt"></i>
          <h5>Quick Actions</h5>
        </div>
        <div class="section-body">
          <div class="row g-3">
            <div class="col-md-3">
              <a href="booking.php" style="display:block;text-align:center;padding:18px;border:1.5px solid var(--border);border-radius:12px;text-decoration:none;color:var(--text);transition:all .2s;" onmouseover="this.style.borderColor='#16a34a';this.style.background='#f5f3ff'" onmouseout="this.style.borderColor='var(--border)';this.style.background='white'">
                <i class="fas fa-ticket-alt" style="font-size:24px;color:#16a34a;margin-bottom:8px;display:block"></i>
                <div style="font-weight:700;font-size:13px">Book Ticket</div>
              </a>
            </div>
            <div class="col-md-3">
              <a href="dashboard.php" style="display:block;text-align:center;padding:18px;border:1.5px solid var(--border);border-radius:12px;text-decoration:none;color:var(--text);transition:all .2s;" onmouseover="this.style.borderColor='#16a34a';this.style.background='#f5f3ff'" onmouseout="this.style.borderColor='var(--border)';this.style.background='white'">
                <i class="fas fa-list" style="font-size:24px;color:#16a34a;margin-bottom:8px;display:block"></i>
                <div style="font-weight:700;font-size:13px">My Bookings</div>
              </a>
            </div>
            <div class="col-md-3">
              <a href="tracking.php" style="display:block;text-align:center;padding:18px;border:1.5px solid var(--border);border-radius:12px;text-decoration:none;color:var(--text);transition:all .2s;" onmouseover="this.style.borderColor='#16a34a';this.style.background='#f5f3ff'" onmouseout="this.style.borderColor='var(--border)';this.style.background='white'">
                <i class="fas fa-satellite-dish" style="font-size:24px;color:#16a34a;margin-bottom:8px;display:block"></i>
                <div style="font-weight:700;font-size:13px">Live Tracking</div>
              </a>
            </div>
            <div class="col-md-3">
              <a href="logout.php" style="display:block;text-align:center;padding:18px;border:1.5px solid #fecaca;border-radius:12px;text-decoration:none;color:#dc2626;transition:all .2s;" onmouseover="this.style.background='#fef2f2'" onmouseout="this.style.background='white'">
                <i class="fas fa-sign-out-alt" style="font-size:24px;margin-bottom:8px;display:block"></i>
                <div style="font-weight:700;font-size:13px">Sign Out</div>
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Password match indicator
const newPwd  = document.getElementById('newPwd');
const confPwd = document.getElementById('confPwd');
const pwdMsg  = document.getElementById('pwdMatch');

function checkMatch() {
    if (!confPwd.value) { pwdMsg.style.display='none'; return; }
    if (newPwd.value === confPwd.value) {
        pwdMsg.style.display='block'; pwdMsg.textContent='✓ Passwords match'; pwdMsg.style.color='#16a34a';
        confPwd.setCustomValidity('');
    } else {
        pwdMsg.style.display='block'; pwdMsg.textContent='✗ Passwords do not match'; pwdMsg.style.color='#dc2626';
        confPwd.setCustomValidity('no match');
    }
}
confPwd.addEventListener('input', checkMatch);
newPwd.addEventListener('input', checkMatch);
</script>
</body>
</html>
