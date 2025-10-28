<?php
// kstone/api/product_api.php
include '../includes/db_connect.php';
set_api_headers();

$method = $_SERVER['REQUEST_METHOD'];
$response = ['success' => false, 'data' => [], 'message' => 'Invalid Request'];

if ($method === 'OPTIONS') { http_response_code(200); exit(); }

try {
    $input = json_decode(file_get_contents('php://input'), true);

    // ฟังก์ชันช่วยในการจัดการความสัมพันธ์ Many-to-Many
    function save_stone_relationships($conn, $product_id, $stones) {
        $conn->begin_transaction();
        try {
            // 1. ลบความสัมพันธ์เก่าทั้งหมดก่อน
            $stmt = $conn->prepare("DELETE FROM stone_product WHERE product_id = ?");
            $stmt->bind_param('i', $product_id);
            $stmt->execute();
            $stmt->close();

            // 2. เพิ่มความสัมพันธ์ใหม่
            if (!empty($stones)) {
                $sql = "INSERT INTO stone_product (product_id, stone_id, quantity) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                foreach ($stones as $stone) {
                    $stmt->bind_param('iii', $product_id, $stone['stone_id'], $stone['quantity']);
                    $stmt->execute();
                }
                $stmt->close();
            }
            $conn->commit();
            return true;
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    }

    switch ($method) {
        case 'GET':
            // ----------------------------------------------------
            // 1. READ (ดึงข้อมูลสินค้าทั้งหมด)
            // ----------------------------------------------------
            $product_id = $_GET['id'] ?? null;
            $search = $_GET['search'] ?? '';

            $sql = "SELECT p.id, p.thai_name, p.sku, p.price, p.stock_qty, p.image_url, pm.name_th AS model_name
                    FROM products p
                    JOIN product_models pm ON p.model_id = pm.id";
            
            $where = []; $params = []; $types = '';

            if ($product_id) {
                $where[] = "p.id = ?";
                $params[] = $product_id;
                $types .= 'i';
            } elseif (!empty($search)) {
                $where[] = "(p.thai_name LIKE ? OR p.sku LIKE ?)";
                $search_term = "%{$search}%";
                $params = array_merge($params, [$search_term, $search_term]);
                $types .= 'ss';
            }

            if (!empty($where)) { $sql .= " WHERE " . implode(' AND ', $where); }
            $sql .= " ORDER BY p.id DESC";

            $stmt = $conn->prepare($sql);
            if ($params) { $stmt->bind_param($types, ...$params); }
            $stmt->execute();
            $result = $stmt->get_result();
            $products = [];

            while($row = $result->fetch_assoc()) {
                // ถ้าดึงเฉพาะ ID ให้ดึงข้อมูลความสัมพันธ์ของหินมาด้วย
                if ($product_id) {
                    $stone_sql = "SELECT sp.stone_id, sp.quantity, s.thai_name 
                                  FROM stone_product sp 
                                  JOIN stones s ON sp.stone_id = s.id 
                                  WHERE sp.product_id = ?";
                    $stone_stmt = $conn->prepare($stone_sql);
                    $stone_stmt->bind_param('i', $product_id);
                    $stone_stmt->execute();
                    $stone_result = $stone_stmt->get_result();
                    $row['stones'] = $stone_result->fetch_all(MYSQLI_ASSOC);
                    $stone_stmt->close();
                }
                $products[] = $row;
            }

            $response['success'] = true;
            $response['data'] = $products;
            $response['message'] = $product_id ? 'ดึงข้อมูลสินค้าสำเร็จ' : 'ดึงรายการสินค้าสำเร็จ';
            break;

        case 'POST':
        case 'PUT':
            // ----------------------------------------------------
            // 2. CREATE & 3. UPDATE (เพิ่ม/แก้ไขสินค้า)
            // ----------------------------------------------------
            $product_id = $input['id'] ?? null;
            $action_type = $method === 'POST' ? 'เพิ่ม' : 'แก้ไข';

            if (empty($input['thai_name']) || empty($input['model_id']) || empty($input['sku']) || !isset($input['price'])) {
                $response['message'] = 'กรุณากรอกข้อมูลสินค้าให้ครบถ้วน'; break;
            }

            $conn->begin_transaction();
            try {
                if ($method === 'POST') {
                    $sql = "INSERT INTO products (model_id, thai_name, sku, price, stock_qty, image_url, description) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('issdiss', $input['model_id'], $input['thai_name'], $input['sku'], $input['price'], $input['stock_qty'], $input['image_url'], $input['description']);
                    $stmt->execute();
                    $product_id = $conn->insert_id;
                    $stmt->close();
                } else { // PUT
                    $sql = "UPDATE products SET model_id=?, thai_name=?, sku=?, price=?, stock_qty=?, image_url=?, description=? WHERE id=?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('issdissi', $input['model_id'], $input['thai_name'], $input['sku'], $input['price'], $input['stock_qty'], $input['image_url'], $input['description'], $product_id);
                    $stmt->execute();
                    $stmt->close();
                }

                // บันทึกความสัมพันธ์ของหิน
                if (!save_stone_relationships($conn, $product_id, $input['stones'] ?? [])) {
                    throw new Exception("บันทึกความสัมพันธ์หินไม่สำเร็จ");
                }

                $conn->commit();
                $response['success'] = true;
                $response['message'] = "{$action_type}สินค้า '{$input['thai_name']}' สำเร็จ";
                $response['data']['id'] = $product_id;

            } catch (Exception $e) {
                $conn->rollback();
                $response['message'] = "{$action_type}สินค้าไม่สำเร็จ: " . $e->getMessage();
            }
            break;

        case 'DELETE':
            // ----------------------------------------------------
            // 4. DELETE (ลบข้อมูลสินค้า)
            // ----------------------------------------------------
            $product_id = $input['id'] ?? null;
            if (!$product_id) { $response['message'] = 'ไม่พบ ID สินค้า'; break; }

            $conn->begin_transaction();
            try {
                // เนื่องจากเราตั้ง ON DELETE CASCADE ในตาราง stone_product
                // เมื่อลบ product จะลบความสัมพันธ์ใน stone_product อัตโนมัติ
                $sql = "DELETE FROM products WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('i', $product_id);
                $stmt->execute();
                $stmt->close();
                
                $conn->commit();
                $response['success'] = true;
                $response['message'] = "ลบสินค้า ID: {$product_id} สำเร็จ";

            } catch (Exception $e) {
                $conn->rollback();
                $response['message'] = 'ลบสินค้าไม่สำเร็จ: ' . $e->getMessage();
            }
            break;

        default:
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