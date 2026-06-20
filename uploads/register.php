<?php
require_once 'config/database.php';

$message = "";  // Success or error message

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $password = md5($_POST['password']);  // পরবর্তীতে password_hash() ব্যবহার করো

    $conn = getConnection();

    // Check if email already exists
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $message = '<div class="alert alert-danger">Email already exists!</div>';
    } else {
        // Handle profile photo upload
        $profile_photo_name = NULL;

        if (!empty($_FILES['profile_photo']['name'])) {
            $target_dir = "uploads/";

            $file_tmp = $_FILES['profile_photo']['tmp_name'];
            $file_name = time() . '_' . basename($_FILES['profile_photo']['name']);
            $target_file = $target_dir . $file_name;

            $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];

            if (in_array($file_type, $allowed_types)) {
                if (move_uploaded_file($file_tmp, $target_file)) {
                    $profile_photo_name = $file_name;
                } else {
                    $message = '<div class="alert alert-danger">Failed to upload profile photo.</div>';
                }
            } else {
                $message = '<div class="alert alert-danger">Only JPG, JPEG, PNG & GIF files allowed for profile photo.</div>';
            }
        }

        if (empty($message)) {
            if ($profile_photo_name) {
                $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, profile_photo) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $name, $email, $phone, $password, $profile_photo_name);
            } else {
                $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $name, $email, $phone, $password);
            }

            if ($stmt->execute()) {
                $message = '<div class="alert alert-success">Registration successful! You can now <a href="login.php">login</a>.</div>';
            } else {
                $message = '<div class="alert alert-danger">Registration failed! Please try again.</div>';
            }
        }
    }

    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Register - BD Bus Track</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-success text-white text-center">
                        <h4><i class="fas fa-user-plus"></i> Register for BD Bus Track</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($message)) echo $message; ?>

                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="name" name="name" required />
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" required />
                            </div>
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" required />
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required />
                            </div>
                            <div class="mb-3">
                                <label for="profile_photo" class="form-label">Profile Photo (optional)</label>
                                <input type="file" class="form-control" id="profile_photo" name="profile_photo" accept="image/*" />
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-user-plus"></i> Register
                                </button>
                            </div>
                        </form>

                        <div class="text-center mt-3">
                            <p>Already have an account? <a href="login.php">Login here</a></p>
                            <p><a href="index.html">Back to Home</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>