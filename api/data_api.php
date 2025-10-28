<?php
// kstone/api/data_api.php
include '../includes/db_connect.php';
set_api_headers();

$response = ['success' => false, 'data' => []];

try {
    $tables = [
        'groups' => 'auspicious_groups',
        'days' => 'days_of_week',
        'months' => 'months',
        'animals' => 'zodiac_animals',
        'signs' => 'zodiac_signs'
    ];

    $all_data = [];

    foreach ($tables as $key => $table) {
        // เลือกคอลัมน์ที่จำเป็นเท่านั้นเพื่อความเร็ว
        $select = '*';
        if ($table === 'days_of_week') {
            $select = 'id, name, lucky_color, unlucky_color'; // ต้องใช้ข้อมูลสีด้วย
        }
        
        $sql = "SELECT {$select} FROM {$table} ORDER BY sort_order ASC, id ASC";
        $result = $conn->query($sql);
        $items = [];
        while($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        $all_data[$key] = $items;
    }

    $response['success'] = true;
    $response = array_merge($response, $all_data);

} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = 'Server Error: ' . $e->getMessage();
}

echo json_encode($response);
exit;
?>