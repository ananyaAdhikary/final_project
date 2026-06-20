<?php
// Database connection
$host = "localhost";
$user = "root";
$password = "";
$database = "bus_tracking_bd";

$conn = new mysqli($host, $user, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch all bookings joined with bus info
$sql = "
    SELECT 
        b.id AS booking_id,
        b.bus_id,
        b.seat_numbers,
        b.passenger_name,
        b.passenger_phone,
        b.booking_date,
        b.journey_date,
        b.status,
        buses.bus_name,
        buses.total_seats
    FROM bookings b
    INNER JOIN buses ON b.bus_id = buses.id
    ORDER BY b.created_at DESC
";

$result = $conn->query($sql);

// Calculate booked seats count per bus
$bus_booked_seats = [];
$sqlBooked = "SELECT bus_id, seat_numbers FROM bookings";
$resBooked = $conn->query($sqlBooked);
if ($resBooked) {
    while ($row = $resBooked->fetch_assoc()) {
        $busId = $row['bus_id'];
        // Count seats booked in this booking by exploding seat_numbers (comma separated)
        $seatsArray = array_filter(array_map('trim', explode(',', $row['seat_numbers'])));
        $countSeats = count($seatsArray);
        if (!isset($bus_booked_seats[$busId])) {
            $bus_booked_seats[$busId] = 0;
        }
        $bus_booked_seats[$busId] += $countSeats;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Bookings List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
        body {
            background: #f4f6f8;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 2rem;
        }
        h1 {
            color: #4a4a4a;
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: 700;
        }
        table {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        th {
            background-color: #667eea;
            color: white;
        }
    </style>
</head>
<body>
    <h1>All Bookings</h1>

    <?php if ($result && $result->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="table table-striped table-bordered align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Booking ID</th>
                        <th>Bus Name</th>
                        <th>Passenger Name</th>
                        <th>Passenger Phone</th>
                        <th>Seats Booked</th>
                        <th>Booking Date</th>
                        <th>Journey Date</th>
                        <th>Status</th>
                        <th>Total Seats</th>
                        <th>Booked Seats</th>
                        <th>Available Seats</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $count = 1;
                    foreach ($result as $booking):
                        $busId = $booking['bus_id'];
                        $totalSeats = (int)$booking['total_seats'];
                        $bookedSeats = isset($bus_booked_seats[$busId]) ? (int)$bus_booked_seats[$busId] : 0;
                        $availableSeats = $totalSeats - $bookedSeats;
                    ?>
                    <tr>
                        <td><?= $count++ ?></td>
                        <td><?= htmlspecialchars($booking['booking_id']) ?></td>
                        <td><?= htmlspecialchars($booking['bus_name']) ?></td>
                        <td><?= htmlspecialchars($booking['passenger_name']) ?></td>
                        <td><?= htmlspecialchars($booking['passenger_phone']) ?></td>
                        <td><?= htmlspecialchars($booking['seat_numbers']) ?></td>
                        <td><?= htmlspecialchars($booking['booking_date']) ?></td>
                        <td><?= htmlspecialchars($booking['journey_date']) ?></td>
                        <td><?= htmlspecialchars(ucfirst($booking['status'])) ?></td>
                        <td><?= $totalSeats ?></td>
                        <td><?= $bookedSeats ?></td>
                        <td><?= $availableSeats ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info text-center">No bookings found.</div>
    <?php endif; ?>
</body>
</html>