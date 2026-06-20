```php
<?php

$route_id = $_POST['route_id'];
$seat = $_POST['seat'];

?>

<!DOCTYPE html>
<html>

<head>

<title>Ticket Confirmed</title>

</head>

<body>

<h2>Ticket Confirmed</h2>

<p>Route ID: <?php echo $route_id; ?></p>
<p>Seat Number: <?php echo $seat; ?></p>

<p>Your ticket has been booked successfully.</p>

</body>

</html>
```
