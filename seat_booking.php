```php
<?php

$route_id = $_GET['route_id'];

?>

<!DOCTYPE html>
<html>
<head>

<title>Seat Booking</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body>

<div class="container mt-5">

<h2>Select Your Seat</h2>

<form action="confirm_ticket.php" method="POST">

<input type="hidden" name="route_id" value="<?php echo $route_id; ?>">

<div class="row">

<?php

for($i=1;$i<=20;$i++){

echo "

<div class='col-md-2 mb-2'>

<label class='btn btn-outline-primary w-100'>

<input type='radio' name='seat' value='$i' required> Seat $i

</label>

</div>

";

}

?>

</div>

<br>

<button class="btn btn-primary">

Confirm Booking

</button>

</form>

</div>

</body>
</html>
```
