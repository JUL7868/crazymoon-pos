<?php
// ============================================================
// Crazy Moon POS — Shifts API
// crazymoon_pos/api/shifts.php
// ============================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

// Build shift summary data
function buildShiftData($conn, $shift_id) {
    $shift = db_fetch_one($conn, "SELECT * FROM shifts WHERE id = ?", 'i', [$shift_id]);
    if (!$shift) return null;

    // Sales totals
    $sales = db_fetch_one($conn,
        "SELECT 
            COUNT(*) as order_count,
            SUM(total) as total_sales,
            SUM(CASE WHEN payment_method = 'cash'  THEN total ELSE 0 END) as total_cash,
            SUM(CASE WHEN payment_method = 'card'  THEN total ELSE 0 END) as total_card,
            SUM(CASE WHEN payment_method = 'split' THEN total ELSE 0 END) as total_split
         FROM orders WHERE shift_id = ? AND status = 'paid'",
        'i', [$shift_id]
    );

    // Sales by category
    $by_category = db_fetch_all($conn,
        "SELECT oi.category, SUM(oi.subtotal) as total, SUM(oi.qty) as qty
         FROM order_items oi
         JOIN orders o ON o.id = oi.order_id
         WHERE o.shift_id = ? AND o.status = 'paid'
         GROUP BY oi.category ORDER BY total DESC",
        'i', [$shift_id]
    );

    // Expenses
    $expenses = db_fetch_all($conn,
        "SELECT type, SUM(amount) as total FROM expenses WHERE shift_id = ? GROUP BY type",
        'i', [$shift_id]
    );
    $total_expenses = array_sum(array_column($expenses, 'total'));

    // Sales by staff
    $by_staff = db_fetch_all($conn,
        "SELECT u.name, COUNT(o.id) as orders, SUM(o.total) as total
         FROM orders o
         LEFT JOIN users u ON u.id = o.paid_by
         WHERE o.shift_id = ? AND o.status = 'paid'
         GROUP BY o.paid_by",
        'i', [$shift_id]
    );

    return [
        'shift'          => $shift,
        'sales'          => $sales,
        'by_category'    => $by_category,
        'by_staff'       => $by_staff,
        'expenses'       => $expenses,
        'total_expenses' => $total_expenses,
        'net_cash'       => floatval($sales['total_cash'] ?? 0) - $total_expenses,
    ];
}

switch ($method) {

    case 'GET':
        // ── Get current open shift ────────────────────────
        if ($action === 'current') {
            try {
                $sql = "SELECT *
                        FROM shifts
                        WHERE status = 'open'
                        ORDER BY opened_at DESC LIMIT 1";

                $result = mysqli_query($conn, $sql);

                if (!$result) {
                    respond([
                        'success' => false,
                        'error'   => 'No se pudo consultar el turno actual',
                    ], 500);
                }

                $shift = mysqli_fetch_assoc($result) ?: null;

                respond([
                    'success' => true,
                    'shift'   => $shift,
                ]);

            } catch (Throwable $e) {
                respond([
                    'success' => false,
                    'error'   => 'No se pudo consultar el turno actual',
                ], 500);
            }

        // ── X Report — live snapshot ──────────────────────
        } elseif ($action === 'x_report') {
            $shift_id = intval($_GET['shift_id'] ?? 0);
            if (!$shift_id) err('shift_id requerido');
            $data = buildShiftData($conn, $shift_id);
            respond(['success' => true, 'report' => $data, 'type' => 'x']);

        // ── Get shift history ─────────────────────────────
        } elseif ($action === 'history') {
            $shifts = db_fetch_all($conn,
                "SELECT s.*, 
                    u1.name as opened_by_name,
                    u2.name as closed_by_name
                 FROM shifts s
                 LEFT JOIN users u1 ON u1.id = s.opened_by
                 LEFT JOIN users u2 ON u2.id = s.closed_by
                 WHERE s.status = 'closed'
                 ORDER BY s.closed_at DESC
                 LIMIT 30"
            );
            respond(['success' => true, 'shifts' => $shifts]);

        // ── Get single closed shift Z report ──────────────
        } else {
            $shift_id = intval($_GET['shift_id'] ?? 0);
            if (!$shift_id) err('shift_id requerido');
            $shift = db_fetch_one($conn, "SELECT * FROM shifts WHERE id = ?", 'i', [$shift_id]);
            if (!$shift) err('Turno no encontrado', 404);
            $report = json_decode($shift['z_report_data'], true);
            respond(['success' => true, 'shift' => $shift, 'report' => $report]);
        }
        break;

    case 'POST':
        // ── Open new shift ────────────────────────────────
        if ($action === 'open') {
            $existing = db_fetch_one($conn,
                "SELECT id FROM shifts WHERE status = 'open' LIMIT 1"
            );
            if ($existing) err('Ya hay un turno abierto. Cierra el turno actual primero.');

            $user_id  = intval($input['user_id'] ?? 0);
            $shift_id = db_insert($conn,
                "INSERT INTO shifts (opened_by, status) VALUES (?, 'open')",
                'i', [$user_id]
            );
            $shift_id ? respond(['success' => true, 'shift_id' => $shift_id]) : err('Error al abrir turno');

        // ── Close shift — Z Report ────────────────────────
        } elseif ($action === 'close') {
            $shift_id = intval($input['shift_id'] ?? 0);
            $user_id  = intval($input['user_id']  ?? 0);
            if (!$shift_id) err('shift_id requerido');

            $shift = db_fetch_one($conn, "SELECT * FROM shifts WHERE id = ? AND status = 'open'", 'i', [$shift_id]);
            if (!$shift) err('Turno no encontrado o ya cerrado');

            // Check no open orders remain
            $open_orders = db_fetch_one($conn,
                "SELECT COUNT(*) as cnt FROM orders WHERE shift_id = ? AND status = 'open'",
                'i', [$shift_id]
            );
            if (intval($open_orders['cnt']) > 0) {
                err('Hay ordenes abiertas. Cierralas antes de cerrar el turno.');
            }

            // Build final report data
            $data = buildShiftData($conn, $shift_id);

            $payment_totals = db_fetch_one($conn,
                "SELECT
                    COALESCE(SUM(tip_cash), 0) as tip_cash,
                    COALESCE(SUM(tip_card), 0) as tip_card,
                    COALESCE(SUM(tip_total), 0) as tip_total,
                    COALESCE(SUM(payment_total), 0) as payment_total
                 FROM orders
                 WHERE shift_id = ? AND status = 'paid'",
                'i', [$shift_id]
            );

            $data['payment_totals'] = [
                'tip_cash'      => floatval($payment_totals['tip_cash'] ?? 0),
                'tip_card'      => floatval($payment_totals['tip_card'] ?? 0),
                'tip_total'     => floatval($payment_totals['tip_total'] ?? 0),
                'payment_total' => floatval($payment_totals['payment_total'] ?? 0),
            ];

            $gross_food_drink_sales = floatval($data['sales']['total_sales'] ?? 0);
            $iva_rate = 0.16;
            $net_food_drink_sales = $gross_food_drink_sales / 1.16;
            $iva_total = $gross_food_drink_sales - $net_food_drink_sales;

            $data['tax_totals'] = [
                'gross_food_drink_sales' => round($gross_food_drink_sales, 2),
                'net_food_drink_sales'   => round($net_food_drink_sales, 2),
                'iva_total'              => round($iva_total, 2),
                'iva_rate'               => $iva_rate,
            ];

            $total_sales    = floatval($data['sales']['total_sales']  ?? 0);
            $total_cash     = floatval($data['sales']['total_cash']   ?? 0);
            $total_card     = floatval($data['sales']['total_card']   ?? 0);
            $total_split    = floatval($data['sales']['total_split']  ?? 0);
            $total_expenses = floatval($data['total_expenses']        ?? 0);
            $net_cash       = $total_cash - $total_expenses;

            db_update($conn,
                "UPDATE shifts SET 
                    status = 'closed',
                    closed_by = ?,
                    closed_at = NOW(),
                    total_sales = ?,
                    total_cash = ?,
                    total_card = ?,
                    total_split = ?,
                    total_expenses = ?,
                    net_cash = ?,
                    z_report_data = ?
                 WHERE id = ?",
                'iddddddsi',
                [
                    $user_id,
                    $total_sales,
                    $total_cash,
                    $total_card,
                    $total_split,
                    $total_expenses,
                    $net_cash,
                    json_encode($data),
                    $shift_id,
                ]
            );

            respond([
                'success' => true,
                'report'  => $data,
                'type'    => 'z',
            ]);
        }
        break;

    default:
        err('Metodo no permitido', 405);
}

mysqli_close($conn);
