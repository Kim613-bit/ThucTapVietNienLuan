<?php
$host = "dpg-d24qm3ili9vc73ej472g-a";
$port = "5432";
$dbname = "db_quanlythuchi";
$user = "db_quanlythuchi_user";
$password = "lMMkElD7noEARgTdAdH7l1nj8EictGsi";

// Kết nối PostgreSQL
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");

// Kiểm tra kết nối
if (!$conn) {
    die("Kết nối đến PostgreSQL thất bại.");
}

echo "Kết nối thành công đến PostgreSQL!";
?>
