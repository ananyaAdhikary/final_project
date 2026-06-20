<?php
require_once 'config/database.php';
if (isRegisteredUser()) redirect('dashboard.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $identifier = sanitize($_POST['identifier']);
    $password   = md5($_POST['password']);
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT id,name,email,phone,role FROM users WHERE (email=? OR phone=?) AND password=?");
    $stmt->bind_param("sss",$identifier,$identifier,$password);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if ($user) {
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_name']  = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_phone'] = $user['phone'];
        $_SESSION['role']       = $user['role'];
        unset($_SESSION['guest']);
        redirect($user['role']==='admin' ? 'admin/dashboard.php' : 'dashboard.php');
    } else { $error = "Invalid phone/email or password."; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — BD Bus Track</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
:root{--primary:#16a34a;--primary-dark:#15803d;--accent:#f97316;--dark:#0f172a;}

*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Inter',sans-serif;min-height:100vh;background:linear-gradient(135deg,#0f172a 0%,#16a34a40 50%,#0f172a 100%);display:flex;align-items:center;justify-content:center;padding:20px;}
.card{background:white;border-radius:20px;padding:40px 36px;width:100%;max-width:420px;box-shadow:0 24px 64px rgba(0,0,0,.35);}
.logo{font-size:24px;font-weight:900;color:#16a34a;text-align:center;margin-bottom:4px;letter-spacing:-.3px;}
.logo span{color:#f97316;}
.tagline{text-align:center;color:#64748b;font-size:13px;font-weight:500;margin-bottom:28px;}
.form-label{font-size:12px;font-weight:700;color:#475569;letter-spacing:.5px;text-transform:uppercase;margin-bottom:6px;display:block;}
.input-wrap{position:relative;margin-bottom:16px;}
.input-ico{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:14px;}
.form-control{width:100%;border:1.5px solid #e2e8f0;border-radius:10px;padding:12px 14px 12px 40px;font-family:'Inter',sans-serif;font-size:14px;font-weight:500;color:#1e293b;outline:none;transition:all .2s;}
.form-control:focus{border-color:#16a34a;box-shadow:0 0 0 3px rgba(22,163,74,.1);}
.btn-login{width:100%;background:linear-gradient(135deg,#16a34a,#22c55e);color:white;border:none;border-radius:10px;padding:13px;font-family:'Inter',sans-serif;font-weight:700;font-size:15px;cursor:pointer;transition:all .2s;box-shadow:0 4px 12px rgba(22,163,74,.3);margin-top:4px;}
.btn-login:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(22,163,74,.4);}
.divider{display:flex;align-items:center;gap:12px;margin:18px 0;color:#94a3b8;font-size:12px;font-weight:600;}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:#e2e8f0;}
.btn-guest{display:block;text-align:center;border:1.5px solid #16a34a;color:#16a34a;border-radius:10px;padding:11px;font-weight:700;font-size:14px;text-decoration:none;transition:all .2s;}
.btn-guest:hover{background:#dcfce7;color:#16a34a;}
.links{text-align:center;margin-top:18px;font-size:13px;color:#64748b;}
.links a{color:#16a34a;font-weight:700;text-decoration:none;}
.alert-err{background:#fef2f2;border:1px solid #fecaca;color:#dc2626;border-radius:10px;padding:11px 14px;font-size:13px;font-weight:600;margin-bottom:18px;display:flex;align-items:center;gap:8px;}
.demo-box{margin-top:16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:10px 14px;font-size:11px;color:#64748b;font-weight:500;}
</style>
</head>
<body>
<div class="card">
  <div class="logo">BD Bus <span>Track</span></div>
  <div class="tagline">Sign in to manage your bookings</div>
  <?php if(isset($error)): ?>
  <div class="alert-err"><i class="fas fa-exclamation-circle"></i><?= $error ?></div>
  <?php endif; ?>
  <form method="POST">
    <label class="form-label">Phone Number or Email</label>
    <div class="input-wrap">
      <i class="fas fa-user input-ico"></i>
      <input class="form-control" name="identifier" placeholder="01XXXXXXXXX or email@example.com" required>
    </div>
    <label class="form-label">Password</label>
    <div class="input-wrap">
      <i class="fas fa-lock input-ico"></i>
      <input class="form-control" type="password" name="password" placeholder="Your password" required>
    </div>
    <button class="btn-login" type="submit"><i class="fas fa-sign-in-alt me-2"></i>Sign In</button>
  </form>
  <div class="divider">or</div>
  <a href="guest_booking.php" class="btn-guest"><i class="fas fa-user-clock me-2"></i>Continue as Guest</a>
  <div class="links">Don't have an account? <a href="register.php">Register here</a></div>
  <div class="demo-box">
    <strong>Demo accounts:</strong><br>
    Admin: admin@bustrack.bd / admin123<br>
    User: 01711111111 / user123
  </div>
</div>
</body>
</html>
