<?php
$conn = mysqli_connect("localhost", "root", "", "bus_tracking_bd");

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
?>