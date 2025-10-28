<?php
// C:\AppServ\www\kstone\api.php

include 'db_connect.php';

// ตั้งค่าสำหรับ response
$response = ['success' => false, 'data' => [], 'message' => ''];

$action = $_GET['action'] ?? ''; // กำหนด action เพื่อระบุว่าต้องการทำอะไร

try {
    if ($action === 'get_stones') {
        // ดึงข้อมูลหินทั้งหมด หรือตามคำค้นหา
        $search = $_GET['search'] ?? '';
        $sql = "SELECT id, thai_name, english_name, description FROM stones";

        if (!empty($search)) {
            // ป้องกัน SQL Injection โดยใช้ Prepared Statements
            $search_term = "%" . $conn->real_escape_string($search) . "%";
            $sql .= " WHERE thai_name LIKE '$search_term' OR english_name LIKE '$search_term' OR other_names LIKE '$search_term'";
        }
        
        $sql .= " ORDER BY id ASC";

        $result = $conn->query($sql);
        $stones = [];

        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $stones[] = $row;
            }
            $response['success'] = true;
            $response['data'] = $stones;
        } else {
            $response['message'] = "ไม่พบข้อมูลหิน";
        }

    } 
    // **TODO: เพิ่มส่วนสำหรับ 'add_stone', 'update_stone', 'delete_stone' ในอนาคต**
    
    else {
        $response['message'] = 'Action ไม่ถูกต้อง';
    }

} catch (Exception $e) {
    $response['message'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
}

close_db_connection($conn);
echo json_encode($response);
?>