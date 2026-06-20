<?php
require_once 'config/database.php';

$conn = getConnection();

// Get all routes
$routes_query = "SELECT * FROM routes ORDER BY route_name";
$routes_result = $conn->query($routes_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Routes - BD Bus Track</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.html">
                <i class="fas fa-bus"></i> BD Bus Track
            </a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.html">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="booking.php">Book Ticket</a>
                    </li>
                   
                    <li class="nav-item">
                        <a class="nav-link active" href="routes.php">Routes</a>
                    </li>
                </ul>
                <div class="navbar-nav">
                    <?php if (isLoggedIn()): ?>
                        <span class="navbar-text me-3">Welcome, <?php echo $_SESSION['user_name']; ?></span>
                        <a class="nav-link" href="logout.php">Logout</a>
                    <?php else: ?>
                        <a class="nav-link" href="login.php">Login</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2><i class="fas fa-route"></i> Available Routes</h2>
        <p class="text-muted">Explore all available bus routes across Bangladesh</p>

        <div class="row">
            <?php while ($route = $routes_result->fetch_assoc()): ?>
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><?php echo $route['route_name']; ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <h6><i class="fas fa-map-marker-alt text-success"></i> From</h6>
                                <p class="mb-2"><?php echo $route['from_city']; ?></p>
                            </div>
                            <div class="col-6">
                                <h6><i class="fas fa-map-marker-alt text-danger"></i> To</h6>
                                <p class="mb-2"><?php echo $route['to_city']; ?></p>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="row">
                            <div class="col-4">
                                <small class="text-muted">Distance</small>
                                <p><i class="fas fa-road"></i> <?php echo $route['distance']; ?> km</p>
                            </div>
                            <div class="col-4">
                                <small class="text-muted">Duration</small>
                                <p><i class="fas fa-clock"></i> <?php echo $route['duration']; ?></p>
                            </div>
                            <div class="col-4">
                                <small class="text-muted">Fare</small>
                                <p><i class="fas fa-money-bill"></i> ৳<?php echo number_format($route['fare']); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="booking.php?route_id=<?php echo $route['id']; ?>" class="btn btn-primary w-100">
                            <i class="fas fa-ticket-alt"></i> Book This Route
                        </a>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>