<?php
// ============================================================
// Crazy Moon POS — Orders API
// crazymoon_pos/api/orders.php
// ============================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/config.php';

$conn   = db_connect();
$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function err($msg, $code = 400) {
    respond(['success' => false, 'error' => $msg], $code);
}

// Recalculate and update order total
function recalcTotal($conn, $order_id) {
    $result = db_fetch_one($conn,
        "SELECT SUM(subtotal) as total FROM order_items WHERE order_id = ?",
        'i', [$order_id]
    );
    $total = floatval($result['total'] ?? 0);
    db_update($conn,
        "UPDATE orders SET total = ? WHERE id = ?",
        'di', [$total, $order_id]
    );
    return $total;
}

switch ($method) {

    case 'GET':
        // ── Get open order for a table ────────────────────
        if ($action === 'get_order') {
            $table_id = intval($_GET['table_id'] ?? 0);
            if (!$table_id) err('table_id requerido');

            $order = db_fetch_one($conn,
                "SELECT * FROM orders WHERE pos_table_id = ? AND status = 'open' LIMIT 1",
                'i', [$table_id]
            );

            if (!$order) {
                respond(['success' => true, 'order' => null, 'items' => []]);
            }

            $items = db_fetch_all($conn,
                "SELECT * FROM order_items WHERE order_id = ? ORDER BY added_at ASC",
                'i', [$order['id']]
            );

            respond(['success' => true, 'order' => $order, 'items' => $items]);

        // ── Get all open orders ───────────────────────────
        } elseif ($action === 'get_open') {
            $orders = db_fetch_all($conn,
                "SELECT o.*, t.name as table_name 
                 FROM orders o 
                 JOIN pos_tables t ON t.id = o.pos_table_id
                 WHERE o.status = 'open'
                 ORDER BY o.created_at ASC"
            );
            respond(['success' => true, 'orders' => $orders]);

        // ── Get single order with items ───────────────────
        } else {
            $order_id = intval($_GET['order_id'] ?? 0);
            if (!$order_id) err('order_id requerido');

            $order = db_fetch_one($conn,
                "SELECT * FROM orders WHERE id = ?",
                'i', [$order_id]
            );
            if (!$order) err('Orden no encontrada', 404);

            $items = db_fetch_all($conn,
                "SELECT * FROM order_items WHERE order_id = ? ORDER BY added_at ASC",
                'i', [$order_id]
            );
            respond(['success' => true, 'order' => $order, 'items' => $items]);
        }
        break;

    case 'POST':
        // ── Open new order for a table ────────────────────
        if ($action === 'open') {
            $table_id = intval($input['table_id'] ?? 0);
            $guest_count = intval($input['guest_count'] ?? 1);

            if (!$table_id) err('table_id requerido');
            if ($guest_count < 1) $guest_count = 1;
            if ($guest_count > 30) $guest_count = 30;

            // Check no open order exists
            $existing = db_fetch_one($conn,
                "SELECT id, guest_count FROM orders WHERE pos_table_id = ? AND status = 'open'",
                'i', [$table_id]
            );
            if ($existing) {
                respond([
                    'success' => true,
                    'order_id' => $existing['id'],
                    'guest_count' => intval($existing['guest_count'] ?? 1),
                    'existing' => true
                ]);
            }

            $current_shift = db_fetch_one($conn,
                "SELECT id FROM shifts WHERE status = 'open' ORDER BY opened_at DESC LIMIT 1"
            );
            if (!$current_shift) err('No hay turno abierto');

            $order_id = db_insert($conn,
                "INSERT INTO orders (pos_table_id, shift_id, created_by, guest_count, status) VALUES (?, ?, ?, ?, 'open')",
                'iiii',
                [$table_id, intval($current_shift['id']), $input['user_id'] ?? null, $guest_count]
            );
            $order_id ? respond(['success' => true, 'order_id' => $order_id, 'shift_id' => intval($current_shift['id']), 'guest_count' => $guest_count]) : err('Error al abrir orden');

        // ── Add item to order ─────────────────────────────
        } elseif ($action === 'add_item') {
            $order_id  = intval($input['order_id'] ?? 0);
            $item_name = trim($input['item_name'] ?? '');
            $unit_price = floatval($input['unit_price'] ?? 0);
            $qty        = intval($input['qty'] ?? 1);

            if (!$order_id || !$item_name || !$unit_price) err('Datos incompletos');

            $subtotal = $unit_price * $qty;

            $item_id = db_insert($conn,
                "INSERT INTO order_items (order_id, menu_item_id, item_name, category, size, unit_price, qty, subtotal, added_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                'iisssddii',
                [
                    $order_id,
                    $input['menu_item_id'] ?? null,
                    $item_name,
                    $input['category']     ?? '',
                    $input['size']         ?? '',
                    $unit_price,
                    $qty,
                    $subtotal,
                    $input['user_id']      ?? null,
                ]
            );

            if (!$item_id) err('Error al agregar articulo');

            $total = recalcTotal($conn, $order_id);
            respond(['success' => true, 'item_id' => $item_id, 'total' => $total]);

        // ── Remove item from order ────────────────────────
        } elseif ($action === 'remove_item') {
            $item_id  = intval($input['item_id'] ?? 0);
            $order_id = intval($input['order_id'] ?? 0);
            if (!$item_id || !$order_id) err('Datos incompletos');

            db_update($conn, "DELETE FROM order_items WHERE id = ?", 'i', [$item_id]);
            $total = recalcTotal($conn, $order_id);
            respond(['success' => true, 'total' => $total]);

        // ── Update item quantity ──────────────────────────
        } elseif ($action === 'update_qty') {
            $item_id  = intval($input['item_id'] ?? 0);
            $order_id = intval($input['order_id'] ?? 0);
            $qty      = intval($input['qty'] ?? 1);
            if (!$item_id || !$order_id) err('Datos incompletos');

            if ($qty <= 0) {
                db_update($conn, "DELETE FROM order_items WHERE id = ?", 'i', [$item_id]);
            } else {
                $item = db_fetch_one($conn, "SELECT unit_price FROM order_items WHERE id = ?", 'i', [$item_id]);
                $subtotal = floatval($item['unit_price']) * $qty;
                db_update($conn,
                    "UPDATE order_items SET qty = ?, subtotal = ? WHERE id = ?",
                    'idi', [$qty, $subtotal, $item_id]
                );
            }
            $total = recalcTotal($conn, $order_id);
            respond(['success' => true, 'total' => $total]);

        // ── Process payment and close order ───────────────
        } elseif ($action === 'pay') {
            $order_id       = intval($input['order_id'] ?? 0);
            $payment_method = $input['payment_method'] ?? '';
            $cash_tendered  = floatval($input['cash_tendered'] ?? 0);

            if (!$order_id || !$payment_method) err('Datos incompletos');

            $order = db_fetch_one($conn, "SELECT * FROM orders WHERE id = ? AND status = 'open'", 'i', [$order_id]);
            if (!$order) err('Orden no encontrada o ya cerrada');

            $total      = floatval($order['total']);
            $cash_change = ($payment_method === 'cash' || $payment_method === 'split')
                ? max(0, $cash_tendered - $total)
                : 0;

            db_update($conn,
                "UPDATE orders SET status='paid', payment_method=?, cash_tendered=?, cash_change=?, paid_at=NOW(), paid_by=? WHERE id=?",
                'sddii',
                [$payment_method, $cash_tendered, $cash_change, $input['user_id'] ?? null, $order_id]
            );

            respond([
                'success'     => true,
                'order_id'    => $order_id,
                'total'       => $total,
                'cash_change' => $cash_change,
            ]);

        // ── Void order ────────────────────────────────────
        } elseif ($action === 'void') {
            $order_id = intval($input['order_id'] ?? 0);
            if (!$order_id) err('order_id requerido');

            db_update($conn, "UPDATE orders SET status='voided' WHERE id=?", 'i', [$order_id]);
            respond(['success' => true]);
        }
        break;

    default:
        err('Metodo no permitido', 405);
}

mysqli_close($conn);
