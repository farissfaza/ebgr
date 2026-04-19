<?php
// koneksi.php

// Detail koneksi database
$servername = "localhost"; // Biasanya "localhost"
$username = "root";        // Username default XAMPP
$password = "";            // Password default XAMPP (kosong)
$dbname = "ebgr";          // Nama database Anda

// Membuat koneksi
$conn = new mysqli($servername, $username, $password, $dbname);

// Memeriksa koneksi
if ($conn->connect_error) {
  die("Koneksi gagal: " . $conn->connect_error);
}

// Set charset untuk memastikan kompatibilitas karakter
$conn->set_charset("utf8mb4");

?>