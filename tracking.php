<?php
require_once 'config/database.php';

$conn = getConnection();

// Safe migration: ensure columns exist (in case this page loads before update_location.php/manage_bus.php has run)
$conn->query("ALTER TABLE buses ADD COLUMN IF NOT EXISTS driver_pin VARCHAR(10) DEFAULT NULL");
$conn->query("ALTER TABLE buses ADD COLUMN IF NOT EXISTS last_updated TIMESTAMP NULL DEFAULT NULL");
$conn->query("ALTER TABLE buses ADD COLUMN IF NOT EXISTS driver_phone VARCHAR(20) DEFAULT NULL");

// Get all active buses with their current locations
$buses_query = "SELECT b.*, r.route_name, r.from_city, r.to_city,
                TIMESTAMPDIFF(SECOND, b.last_updated, NOW()) as seconds_ago
                FROM buses b 
                JOIN routes r ON b.route_id = r.id 
                WHERE b.status = 'active' 
                ORDER BY r.route_name";
$buses_result = $conn->query($buses_query);
if (!$buses_result) {
    // Fallback in case TIMESTAMPDIFF column isn't ready yet for any reason
    $buses_query = "SELECT b.*, r.route_name, r.from_city, r.to_city
                    FROM buses b
                    JOIN routes r ON b.route_id = r.id
                    WHERE b.status = 'active'
                    ORDER BY r.route_name";
    $buses_result = $conn->query($buses_query);
}

// Handle AJAX request for bus location update
if (isset($_GET['ajax']) && $_GET['ajax'] == 'get_location' && isset($_GET['bus_id'])) {
    $bus_id = $_GET['bus_id'];
    $location_query = "SELECT current_lat, current_lng, bus_name, last_updated FROM buses WHERE id = ?";
    $stmt = $conn->prepare($location_query);
    $stmt->bind_param("i", $bus_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $location = $result->fetch_assoc();

    if ($location) {
        $location['is_live'] = false;
        if (!empty($location['last_updated'])) {
            $secondsAgo = time() - strtotime($location['last_updated']);
            $location['is_live'] = $secondsAgo <= 60; // considered live if updated within last 60s
            $location['seconds_ago'] = $secondsAgo;
        }
    }

    header('Content-Type: application/json');
    echo json_encode($location);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Bus - BD Bus Track</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <style>
        #map {
            height: 500px;
            width: 100%;
        }
        .bus-card {
            cursor: pointer;
            transition: all 0.3s;
        }
        .bus-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .bus-card.selected {
            border: 2px solid #007bff;
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.html">
                <i class="fas fa-bus"></i> BD Bus Track
            </a>
            <div class="navbar-nav ms-auto">
                <?php if (isLoggedIn()): ?>
                    <span class="navbar-text me-3">Welcome, <?php echo $_SESSION['user_name']; ?></span>
                    <a class="nav-link" href="logout.php">Logout</a>
                <?php else: ?>
                    <a class="nav-link" href="login.php">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Bus List -->
            <div class="col-md-4">
                <h4><i class="fas fa-list"></i> Active Buses</h4>
                <div class="mb-3">
                    <input type="text" id="searchBus" class="form-control" placeholder="Search buses...">
                </div>
                
                <div id="busList">
                    <?php while ($bus = $buses_result->fetch_assoc()): ?>
                    <div class="card bus-card mb-2" data-bus-id="<?php echo $bus['id']; ?>" onclick="trackBus(<?php echo $bus['id']; ?>, '<?php echo $bus['bus_name']; ?>', <?php echo $bus['current_lat'] ?: 'null'; ?>, <?php echo $bus['current_lng'] ?: 'null'; ?>)">
                        <div class="card-body p-3">
                            <h6 class="card-title mb-1"><?php echo $bus['bus_name']; ?></h6>
                            <small class="text-muted"><?php echo $bus['bus_number']; ?></small>
                            <p class="card-text mb-1">
                                <small><i class="fas fa-route"></i> <?php echo $bus['from_city'] . ' → ' . $bus['to_city']; ?></small>
                            </p>
                            <?php
                                $isLive = isset($bus['seconds_ago']) && $bus['seconds_ago'] !== null && $bus['seconds_ago'] <= 60;
                            ?>
                            <span class="badge <?php echo $isLive ? 'bg-success' : 'bg-secondary'; ?>">
                                <?php if ($isLive): ?><i class="fas fa-circle" style="font-size:6px;vertical-align:middle;"></i> Live<?php else: ?>Offline<?php endif; ?>
                            </span>
                            <span class="badge bg-info"><?php echo $bus['bus_type']; ?></span>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
            
            <!-- Map -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-map-marker-alt"></i> Live Bus Tracking</h5>
                        <small class="text-muted">Click on a bus from the list to track its location</small>
                    </div>
                    <div class="card-body p-0">
                        <div id="map"></div>
                    </div>
                </div>
                
                <!-- Bus Info Panel -->
                <div id="busInfo" class="card mt-3" style="display: none;">
                    <div class="card-header">
                        <h6><i class="fas fa-info-circle"></i> Bus Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Bus Name:</strong> <span id="infoBusName"></span></p>
                                <p><strong>Bus Number:</strong> <span id="infoBusNumber"></span></p>
                                <p><strong>Route:</strong> <span id="infoBusRoute"></span></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Current Location:</strong> <span id="infoBusLocation"></span></p>
                                <p><strong>Last Updated:</strong> <span id="infoLastUpdate"></span></p>
                                <p><strong>Status:</strong> <span class="badge bg-success">Active</span></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script>
        // Initialize map
        var map = L.map('map').setView([23.8103, 90.4125], 7); // Center on Dhaka
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        var currentMarker = null;
        var selectedBusId = null;

        // Track specific bus
        function trackBus(busId, busName, lat, lng) {
            // Remove previous marker
            if (currentMarker) {
                map.removeLayer(currentMarker);
            }
            
            // Highlight selected bus card
            document.querySelectorAll('.bus-card').forEach(card => {
                card.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
            
            selectedBusId = busId;
            
            // Add new marker
            if (lat && lng) {
                currentMarker = L.marker([lat, lng]).addTo(map);
                currentMarker.bindPopup(`<b>${busName}</b><br>Current Location`).openPopup();
                map.setView([lat, lng], 12);
                
                // Update bus info panel
                updateBusInfo(busId, busName);
            } else {
                alert('Location not available for this bus');
            }
        }

        // Update bus information panel
        function updateBusInfo(busId, busName) {
            // This would typically fetch more detailed info from the server
            document.getElementById('infoBusName').textContent = busName;
            document.getElementById('infoLastUpdate').textContent = new Date().toLocaleString();
            document.getElementById('busInfo').style.display = 'block';
        }

        // Auto-select bus if ?bus_id= is present in the URL (used by QR codes on tickets)
        document.addEventListener('DOMContentLoaded', function() {
            const params = new URLSearchParams(window.location.search);
            const targetBusId = params.get('bus_id');
            if (targetBusId) {
                const targetCard = document.querySelector(`.bus-card[data-bus-id="${targetBusId}"]`);
                if (targetCard) {
                    targetCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    targetCard.click();
                }
            }
        });

        // Search functionality
        document.getElementById('searchBus').addEventListener('input', function() {
            var searchTerm = this.value.toLowerCase();
            var busCards = document.querySelectorAll('.bus-card');
            
            busCards.forEach(function(card) {
                var busName = card.querySelector('.card-title').textContent.toLowerCase();
                var busNumber = card.querySelector('.text-muted').textContent.toLowerCase();
                
                if (busName.includes(searchTerm) || busNumber.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });

        // Auto-refresh bus location every 10 seconds
        setInterval(function() {
            if (selectedBusId) {
                fetch(`tracking.php?ajax=get_location&bus_id=${selectedBusId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.current_lat && data.current_lng) {
                            if (currentMarker) {
                                map.removeLayer(currentMarker);
                            }
                            currentMarker = L.marker([data.current_lat, data.current_lng]).addTo(map);
                            currentMarker.bindPopup(`<b>${data.bus_name}</b><br>${data.is_live ? 'Live now' : 'Last seen ' + Math.round(data.seconds_ago/60) + ' min ago'}`).openPopup();
                            map.panTo([data.current_lat, data.current_lng]);
                            document.getElementById('infoLastUpdate').textContent = new Date().toLocaleString();
                            document.getElementById('infoBusLocation').textContent = data.current_lat.toFixed(5) + ', ' + data.current_lng.toFixed(5);

                            const statusBadge = document.querySelector('#busInfo .badge');
                            if (statusBadge) {
                                if (data.is_live) {
                                    statusBadge.className = 'badge bg-success';
                                    statusBadge.innerHTML = '<i class="fas fa-circle" style="font-size:6px;"></i> Live';
                                } else {
                                    statusBadge.className = 'badge bg-secondary';
                                    statusBadge.textContent = 'Offline';
                                }
                            }
                        }
                    })
                    .catch(error => console.error('Error:', error));
            }
        }, 10000);
    </script>
</body>
</html>