
<?php
include "config/database.php";

$conn = getConnection();

$query = "SELECT * FROM routes";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html>
<head>

<title>Bus Routes</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body>

<div class="container mt-5">

<h2>All Bus Routes</h2>

<table class="table table-bordered">

<tr>
<th>Route Name</th>
<th>From</th>
<th>To</th>
<th>Distance</th>
<th>Fare</th>
</tr>

<?php

if($result->num_rows > 0){

while($row = $result->fetch_assoc()){

?>

<tr>

<td><?php echo $row['route_name']; ?></td>
<td><?php echo $row['from_city']; ?></td>
<td><?php echo $row['to_city']; ?></td>
<td><?php echo $row['distance']; ?> km</td>
<td><?php echo $row['fare']; ?> BDT</td>

</tr>

<?php
}

}else{

?>

<tr>
<td colspan="5" class="text-center">No Routes Found</td>
</tr>

<?php } ?>

</table>

</div>

</body>
</html>
```
