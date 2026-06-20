<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message  = "";

/* --------------------------------------------------------------------------
   Fetch current user data
-------------------------------------------------------------------------- */
$sql    = "SELECT name, email, phone, profile_photo, password FROM users WHERE id = $user_id";
$result = mysqli_query($conn, $sql);
$user   = mysqli_fetch_assoc($result);

/* Ensure uploads directory exists */
$target_dir = "uploads/";
if (!is_dir($target_dir)) {
    mkdir($target_dir, 0777, true);
}

/* --------------------------------------------------------------------------
   Update profile
-------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name  = mysqli_real_escape_string($conn, $_POST['name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);

    /* Profile photo */
    if (!empty($_FILES['profile_photo']['name'])) {
        $photo_name  = time() . '_' . basename($_FILES['profile_photo']['name']);
        $target_file = $target_dir . $photo_name;

        if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target_file)) {
            $update_photo = ", profile_photo='$photo_name'";
        } else {
            $message      = "Failed to upload profile photo.";
            $update_photo = "";
        }
    } else {
        $update_photo = "";
    }

    $update_sql = "UPDATE users SET name='$name', phone='$phone' $update_photo WHERE id=$user_id";
    if (mysqli_query($conn, $update_sql)) {
        $message         = $message ?: "Profile updated successfully!";
        $user['name']    = $name;
        $user['phone']   = $phone;
        if (isset($photo_name)) {
            $user['profile_photo'] = $photo_name;
        }
    } else {
        $message = "Error updating profile.";
    }
}

/* --------------------------------------------------------------------------
   Change password
   (Consider switching to password_hash & password_verify in production)
-------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = md5($_POST['current_password']);
    $new     = md5($_POST['new_password']);
    $confirm = md5($_POST['confirm_password']);

    if ($current !== $user['password']) {
        $message = "Current password is incorrect.";
    } elseif ($new !== $confirm) {
        $message = "New passwords do not match.";
    } else {
        $update_password = "UPDATE users SET password='$new' WHERE id=$user_id";
        $message         = mysqli_query($conn, $update_password)
                         ? "Password changed successfully!"
                         : "Failed to change password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body            { background-color:#f8f9fc; font-family: 'Inter', sans-serif; }
        .main-card      { border-radius:16px; }
        .card-header    { background:#4e73df; color:#fff; border-radius:16px 16px 0 0; }
        .profile-photo  { width:140px;height:140px;border-radius:50%;object-fit:cover;
                          display:block;margin:0 auto 1rem;border:4px solid #f6c23e; }
        .form-label     { font-weight:600; }
        .btn-primary    { background:#4e73df;border:none; }
        .btn-primary:hover{ background:#405ec9; }
    </style>
</head>
<body>
<div class="container-fluid mt-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow-lg main-card">
                <div class="card-header">
                    <h4 class="mb-0"><i class="fas fa-user-cog me-2"></i>Manage Profile</h4>
                </div>

                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
                    <?php endif; ?>

                    <!-- Two-column layout -->
                    <div class="row g-4">
                        <!-- ===== Left: profile update ===== -->
                        <div class="col-md-6">
                            <h5 class="mb-3">Profile Information</h5>
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="update_profile" value="1">

                                <div class="text-center">
                                    <?php if (!empty($user['profile_photo']) && file_exists($target_dir.$user['profile_photo'])): ?>
                                        <img src="<?= $target_dir . htmlspecialchars($user['profile_photo']) ?>"
                                             alt="Profile Photo" class="profile-photo">
                                    <?php else: ?>
                                        <img src="https://via.placeholder.com/140?text=No+Photo"
                                             alt="No Photo" class="profile-photo">
                                    <?php endif; ?>
                                    <input type="file" name="profile_photo" class="form-control mt-2">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" name="name" class="form-control"
                                           value="<?= htmlspecialchars($user['name']) ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control"
                                           value="<?= htmlspecialchars($user['email']) ?>" readonly>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="text" name="phone" class="form-control"
                                           value="<?= htmlspecialchars($user['phone']) ?>" required>
                                </div>

                                <button type="submit" class="btn btn-success w-100">Update Profile</button>
                            </form>
                        </div>

                        <!-- ===== Right: change password ===== -->
                        <div class="col-md-6">
                            <h5 class="mb-3">Change Password</h5>
                            <form method="POST">
                                <input type="hidden" name="change_password" value="1">

                                <div class="mb-3">
                                    <label class="form-label">Current Password</label>
                                    <input type="password" name="current_password" class="form-control" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">New Password</label>
                                    <input type="password" name="new_password" class="form-control" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Confirm New Password</label>
                                    <input type="password" name="confirm_password" class="form-control" required>
                                </div>

                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">Change Password</button>
                                    <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                                </div>
                            </form>
                        </div>
                    </div><!-- /row -->
                </div><!-- /card-body -->
            </div><!-- /card -->
        </div><!-- /col -->
    </div><!-- /row -->
</div><!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</body>
</html>