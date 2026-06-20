<?php
require_once 'config/database.php';

if (isset($_GET['bus_id']) && isset($_GET['journey_date'])) {
    $bus_id = $_GET['bus_id'];
    $journey_date = $_GET['journey_date'];
    
    $conn = getConnection();
    
    // Get all booked seats for this bus and date
    $query = "SELECT seat_numbers FROM bookings 
              WHERE bus_id = ? AND journey_date = ? AND status = 'confirmed'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $bus_id, $journey_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $booked_seats = [];
    while ($row = $result->fetch_assoc()) {
        $seats = explode(',', $row['seat_numbers']);
        foreach ($seats as $seat) {
            $booked_seats[] = trim($seat);
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode(['booked_seats' => array_unique($booked_seats)]);
    
    $conn->close();
}
?>