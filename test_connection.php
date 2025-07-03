<?php
$servername = "localhost";
$username = "root";     // default for XAMPP
$password = "";         // default is empty in XAMPP
$dbname = "club_dashboard_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "Connected successfully!";
?>
