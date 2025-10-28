<?php
// C:\AppServ\www\kstone\db_connect.php

$servername = "localhost";
$username = "root"; // เปลี่ยนเป็นชื่อผู้ใช้ MySQL ของคุณ
$password = "Hmanhman37#"; // เปลี่ยนเป็นรหัสผ่าน MySQL ของคุณ
$dbname = "auspicious_stones"; // ชื่อฐานข้อมูล

// สร้างการเชื่อมต่อ
$conn = new mysqli($servername, $username, $password, $dbname);

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// กำหนด charset เป็น utf8mb4 สำหรับรองรับภาษาไทย
$conn->set_charset("utf8mb4");

// ฟังก์ชันสำหรับปิดการเชื่อมต่อ
function close_db_connection($conn) {
    $conn->close();
}

// ตั้งค่า Header สำหรับ API (ส่วนใหญ่มักจะใช้ JSON)
header('Content-Type: application/json; charset=utf-8');
?>