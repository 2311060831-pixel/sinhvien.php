<?php
$connection = mysqli_connect("localhost", "root", "", "qlsv", 3307);
if (!$connection) {
    die('Database connection error: ' . mysqli_connect_error());
}
?>
