<?php
$servername = "localhost";
$username = "root";     
$password = "fb2e5aa9";     
$database = "magazin_haine";  

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Conexiune eșuată: " . $conn->connect_error);
}
// else echo "Conectat cu succes la MySQL!";
?>