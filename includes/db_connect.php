<?php
// kstone/includes/db_connect.php

$servername = "localhost";
$username = "root"; // ตรวจสอบและเปลี่ยนเป็นชื่อผู้ใช้ MySQL ของคุณ
$password = "Hmanhman37#"; // ตรวจสอบและเปลี่ยนเป็นรหัสผ่าน MySQL ของคุณ
$dbname = "auspicious_stones";

// สร้างการเชื่อมต่อ
$conn = new mysqli($servername, $username, $password, $dbname);

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    // ส่ง HTTP 500 Error Code ในกรณีที่เชื่อมต่อฐานข้อมูลไม่ได้
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]));
}

// กำหนด charset เป็น utf8mb4
$conn->set_charset("utf8mb4");

// ตั้งค่า Header สำหรับ API (ส่วนใหญ่มักจะใช้ JSON)
// ฟังก์ชันนี้จะถูกเรียกใช้ในไฟล์ API ทุกตัว
function set_api_headers() {
    header('Content-Type: application/json; charset=utf-8');
    // ตั้งค่า CORS สำหรับการทดสอบ Localhost
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
}
?>