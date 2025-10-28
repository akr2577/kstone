<?php
// kstone/api/stone_api.php
include '../includes/db_connect.php';
set_api_headers();

$method = $_SERVER['REQUEST_METHOD'];
$response = ['success' => false, 'data' => [], 'message' => 'Invalid Request'];

if ($method === 'OPTIONS') { http_response_code(200); exit(); }

try {
    if ($method === 'GET') {
        // ดึงเงื่อนไขการค้นหา
        $search = $_GET['search'] ?? '';
        $group_id = $_GET['group_id'] ?? null;
        $day_id = $_GET['day_id'] ?? null;
        $month_id = $_GET['month_id'] ?? null;
        $zodiac_animal_id = $_GET['zodiac_animal_id'] ?? null;
        $zodiac_sign_id = $_GET['zodiac_sign_id'] ?? null;

        $where_clauses = [];
        $params = [];
        $types = '';
        $day_data = null; // สำหรับเก็บข้อมูลสีมงคล/อัปมงคล

        // --- 1. ค้นหาจากชื่อหิน ---
        if (!empty($search)) {
            $where_clauses[] = "(stones.thai_name LIKE ? OR stones.english_name LIKE ? OR stones.other_names LIKE ?)";
            $search_term = "%{$search}%";
            $params = array_merge($params, [$search_term, $search_term, $search_term]);
            $types .= 'sss';
        }

        // --- 2. ค้นหาจากกลุ่มมงคล ---
        if (!empty($group_id)) {
            $where_clauses[] = "FIND_IN_SET(?, REPLACE(stones.group_ids, ' ', ','))";
            $params[] = $group_id;
            $types .= 'i';
        }

        // --- 3. ค้นหาจากเดือนเกิด ---
        if (!empty($month_id)) {
            $where_clauses[] = "FIND_IN_SET(?, REPLACE(stones.good_months, ' ', ','))";
            $params[] = $month_id;
            $types .= 'i';
        }

        // --- 4. ค้นหาจากปีนักษัตร ---
        if (!empty($zodiac_animal_id)) {
            $where_clauses[] = "FIND_IN_SET(?, REPLACE(stones.good_zodiac_animals, ' ', ','))";
            $params[] = $zodiac_animal_id;
            $types .= 'i';
        }

        // --- 5. ค้นหาจากราศี ---
        if (!empty($zodiac_sign_id)) {
            $where_clauses[] = "FIND_IN_SET(?, REPLACE(stones.good_zodiac_signs, ' ', ','))";
            $params[] = $zodiac_sign_id;
            $types .= 'i';
        }

        // --- 6. ค้นหาจากวันเกิด (Pre-Filtering) ---
        if (!empty($day_id)) {
            // A. กรองวันเกิดใน SQL
            $where_clauses[] = "FIND_IN_SET(?, REPLACE(stones.good_days, ' ', ','))";
            $params[] = $day_id;
            $types .= 'i';

            // B. ดึงข้อมูลสีมงคล/อัปมงคลของวันนั้น (สำหรับ Post-Filtering)
            $day_sql = "SELECT lucky_color, unlucky_color FROM days_of_week WHERE id = ?";
            $day_stmt = $conn->prepare($day_sql);
            $day_stmt->bind_param('i', $day_id);
            $day_stmt->execute();
            $day_result = $day_stmt->get_result();
            $day_data = $day_result->fetch_assoc();
            $day_stmt->close();
        }

        // ----------------------------------------------------
        // สร้างและรัน SQL Query
        // ----------------------------------------------------
        $sql = "SELECT id, thai_name, english_name, description, color_ids, good_days, group_ids FROM stones";
        if (!empty($where_clauses)) {
            $sql .= " WHERE " . implode(' AND ', $where_clauses);
        }
        $sql .= " ORDER BY id ASC";
        
        $stmt = $conn->prepare($sql);
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $stones = [];

        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $stones[] = $row;
            }
        } 
        
        // ----------------------------------------------------
        // Post-Filtering: กรองสีอัปมงคล (ใช้ PHP)
        // ----------------------------------------------------
        $final_stones = $stones;
        
        if (!empty($day_data) && !empty($stones)) {
            $unlucky_color_names = array_map('trim', explode(',', $day_data['unlucky_color']));
            $unlucky_color_ids = [];
            
            // ดึง ID สีอัปมงคลจากตาราง stone_colors
            if (!empty($unlucky_color_names)) {
                $name_placeholders = implode(',', array_fill(0, count($unlucky_color_names), '?'));
                $color_sql = "SELECT id FROM stone_colors WHERE name IN ({$name_placeholders})";
                $color_stmt = $conn->prepare($color_sql);
                
                // PHP 7.3 Compatibility: ใช้ call_user_func_array
                $color_types = str_repeat('s', count($unlucky_color_names));
                call_user_func_array([$color_stmt, 'bind_param'], array_merge([$color_types], $unlucky_color_names));
                
                $color_stmt->execute();
                $color_result = $color_stmt->get_result();
                while($row = $color_result->fetch_assoc()) {
                    $unlucky_color_ids[] = (string)$row['id'];
                }
                $color_stmt->close();
            }
            
            // กรองหินที่มีสีอัปมงคล
            if (!empty($unlucky_color_ids)) {
                $final_stones = array_filter($final_stones, function($stone) use ($unlucky_color_ids) {
                    $stone_color_ids = explode(' ', $stone['color_ids']);
                    foreach ($stone_color_ids as $stone_color_id) {
                        if (in_array($stone_color_id, $unlucky_color_ids)) {
                            return false; // กรองหินนี้ออก
                        }
                    }
                    return true; // เก็บหินนี้ไว้
                });
            }
        }
        
        $response['success'] = true;
        $response['data'] = array_values($final_stones); // Reset array keys
        $response['message'] = "ค้นหาหินสำเร็จ พบ " . count($final_stones) . " รายการ";

    } 
    // ... (เพิ่ม case 'POST', 'PUT', 'DELETE' CRUD เดิมที่นี่)
    
    else {
        http_response_code(405);
        $response['message'] = "Method Not Allowed";
    }

} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = 'Server Error: ' . $e->getMessage();
}

echo json_encode($response);
exit;
?>