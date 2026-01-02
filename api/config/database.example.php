<?php

$servername = "localhost";
$username = "your_username";
$password = "your_password";
$dbname = "mediassist";

$con = mysqli_connect($servername, $username, $password, $dbname);

if (!$con) {
    error_log("Database Connection Error: " . mysqli_connect_error());
}

?>
