<?php
// kstone/api/stone_api.php
include '../includes/db_connect.php';
set_api_headers();

$method = $_SERVER['REQUEST_METHOD'];
$response = ['success' => false, 'data' => [], 'message' => 'Invalid Request'];

// สำหรับคำขอ OPTIONS (CORS Preflight)
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    switch ($method) {
        case 'GET':
            // ----------------------------------------------------
            // 1. READ (ดึงข้อมูลหินทั้งหมด/ค้นหา)
            // ----------------------------------------------------
            $search = $_GET['search'] ?? '';
            $stone_id = $_GET['id'] ?? null;
            
            $sql = "SELECT id, thai_name, english_name, description, color_ids FROM stones";
            $params = [];
            $types = '';

            if ($stone_id) {
                // ดึงข้อมูลหินเฉพาะ ID
                $sql .= " WHERE id = ?";
                $params[] = $stone_id;
                $types .= 'i';
            } elseif (!empty($search)) {
                // ค้นหา
                $sql .= " WHERE thai_name LIKE ? OR english_name LIKE ? OR other_names LIKE ?";
                $search_term = "%{$search}%";
                $params = [$search_term, $search_term, $search_term];
                $types = 'sss';
            }

            $sql .= " ORDER BY id ASC";
            
            $stmt = $conn->prepare($sql);
            if ($params) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $stones = [];
                while($row = $result->fetch_assoc()) {
                    $stones[] = $row;
                }
                $response['success'] = true;
                $response['data'] = $stones;
                $response['message'] = $stone_id ? 'ดึงข้อมูลหินสำเร็จ' : 'ดึงข้อมูลรายการหินสำเร็จ';
            } else {
                $response['message'] = "ไม่พบข้อมูลหิน";
            }
            break;

        case 'POST':
            // ----------------------------------------------------
            // 2. CREATE (เพิ่มหินใหม่)
            // ----------------------------------------------------
            $input = json_decode(file_get_contents('php://input'), true);
            if (empty($input['thai_name']) || empty($input['english_name'])) {
                $response['message'] = 'กรุณาระบุชื่อหินภาษาไทยและอังกฤษ';
                break;
            }

            $sql = "INSERT INTO stones (english_name, thai_name, description) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('sss', $input['english_name'], $input['thai_name'], $input['description']);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = "เพิ่มหิน '{$input['thai_name']}' สำเร็จ";
                $response['data']['id'] = $conn->insert_id;
            } else {
                $response['message'] = 'เพิ่มหินไม่สำเร็จ: ' . $stmt->error;
            }
            break;
            
        case 'PUT':
            // ----------------------------------------------------
            // 3. UPDATE (แก้ไขข้อมูลหิน)
            // ----------------------------------------------------
            $input = json_decode(file_get_contents('php://input'), true);
            $stone_id = $input['id'] ?? null;
            
            if (!$stone_id) {
                $response['message'] = 'ไม่พบ ID หินที่ต้องการแก้ไข';
                break;
            }

            $sql = "UPDATE stones SET thai_name = ?, english_name = ?, description = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('sssi', $input['thai_name'], $input['english_name'], $input['description'], $stone_id);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = "แก้ไขหิน ID: {$stone_id} สำเร็จ";
            } else {
                $response['message'] = 'แก้ไขหินไม่สำเร็จ: ' . $stmt->error;
            }
            break;
            
        case 'DELETE':
            // ----------------------------------------------------
            // 4. DELETE (ลบข้อมูลหิน)
            // ----------------------------------------------------
            $input = json_decode(file_get_contents('php://input'), true);
            $stone_id = $input['id'] ?? null;

            if (!$stone_id) {
                $response['message'] = 'ไม่พบ ID หินที่ต้องการลบ';
                break;
            }

            $sql = "DELETE FROM stones WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $stone_id);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = "ลบหิน ID: {$stone_id} สำเร็จ";
            } else {
                $response['message'] = 'ลบหินไม่สำเร็จ: ' . $stmt->error;
            }
            break;

        default:
            // คำขออื่น ๆ ที่ไม่รองรับ
            http_response_code(405);
            $response['message'] = "Method Not Allowed";
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = 'Server Error: ' . $e->getMessage();
}

echo json_encode($response);
exit;
?>