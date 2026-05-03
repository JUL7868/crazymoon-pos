<?php
// ============================================================
// Crazy Moon POS — Reports API
// crazymoon_pos/api/reports.php
// ============================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/config.php';

$conn   = db_connect();
$action = $_GET['action'] ?? '';

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function err($msg, $code = 400) {
    respond(['success' => false, 'error' => $msg], $code);
}

switch ($action) {

    // ── Sales summary for a date range ────────────────────
    case 'summary':
        $date_from = $_GET['from'] ?? date('Y-m-d');
        $date_to   = $_GET['to']   ?? date('Y-m-d');

        $sales = db_fetch_one($conn,
            "SELECT
                COUNT(*)                                              as order_count,
                SUM(total)                                           as total_sales,
                SUM(CASE WHEN payment_method='cash'  THEN total ELSE 0 END) as total_cash,
                SUM(CASE WHEN payment_method='card'  THEN total ELSE 0 END) as total_card,
                SUM(CASE WHEN payment_method='split' THEN total ELSE 0 END) as total_split
             FROM orders
             WHERE status = 'paid'
             AND DATE(paid_at) BETWEEN ? AND ?",
            'ss', [$date_from, $date_to]
        );

        $expenses = db_fetch_one($conn,
            "SELECT SUM(amount) as total FROM expenses
             WHERE DATE(created_at) BETWEEN ? AND ?",
            'ss', [$date_from, $date_to]
        );

        $by_category = db_fetch_all($conn,
            "SELECT oi.category,
                SUM(oi.subtotal) as total,
                SUM(oi.qty)      as qty
             FROM order_items oi
             JOIN orders o ON o.id = oi.order_id
             WHERE o.status = 'paid'
             AND DATE(o.paid_at) BETWEEN ? AND ?
             GROUP BY oi.category
             ORDER BY total DESC",
            'ss', [$date_from, $date_to]
        );

        $by_staff = db_fetch_all($conn,
            "SELECT u.name,
                COUNT(o.id)  as orders,
                SUM(o.total) as total
             FROM orders o
             LEFT JOIN users u ON u.id = o.paid_by
             WHERE o.status = 'paid'
             AND DATE(o.paid_at) BETWEEN ? AND ?
             GROUP BY o.paid_by
             ORDER BY total DESC",
            'ss', [$date_from, $date_to]
        );

        $top_items = db_fetch_all($conn,
            "SELECT oi.item_name,
                SUM(oi.qty)      as qty,
                SUM(oi.subtotal) as total
             FROM order_items oi
             JOIN orders o ON o.id = oi.order_id
             WHERE o.status = 'paid'
             AND DATE(o.paid_at) BETWEEN ? AND ?
             GROUP BY oi.item_name
             ORDER BY qty DESC
             LIMIT 10",
            'ss', [$date_from, $date_to]
        );

        respond([
            'success'        => true,
            'date_from'      => $date_from,
            'date_to'        => $date_to,
            'sales'          => $sales,
            'total_expenses' => floatval($expenses['total'] ?? 0),
            'net_cash'       => floatval($sales['total_cash'] ?? 0) - floatval($expenses['total'] ?? 0),
            'by_category'    => $by_category,
            'by_staff'       => $by_staff,
            'top_items'      => $top_items,
        ]);
        break;

    // ── Sales by day for a date range ─────────────────────
    case 'by_day':
        $date_from = $_GET['from'] ?? date('Y-m-01');
        $date_to   = $_GET['to']   ?? date('Y-m-d');

        $by_day = db_fetch_all($conn,
            "SELECT
                DATE(paid_at)    as date,
                COUNT(*)         as orders,
                SUM(total)       as total,
                SUM(CASE WHEN payment_method='cash'  THEN total ELSE 0 END) as cash,
                SUM(CASE WHEN payment_method='card'  THEN total ELSE 0 END) as card,
                SUM(CASE WHEN payment_method='split' THEN total ELSE 0 END) as split
             FROM orders
             WHERE status = 'paid'
             AND DATE(paid_at) BETWEEN ? AND ?
             GROUP BY DATE(paid_at)
             ORDER BY date ASC",
            'ss', [$date_from, $date_to]
        );

        respond(['success' => true, 'by_day' => $by_day]);
        break;

    // ── All shifts summary ────────────────────────────────
    case 'shifts':
        $shifts = db_fetch_all($conn,
            "SELECT s.*,
                u1.name as opened_by_name,
                u2.name as closed_by_name
             FROM shifts s
             LEFT JOIN users u1 ON u1.id = s.opened_by
             LEFT JOIN users u2 ON u2.id = s.closed_by
             ORDER BY s.opened_at DESC
             LIMIT 50"
        );
        respond(['success' => true, 'shifts' => $shifts]);
        break;

    // ── Expense breakdown ─────────────────────────────────
    case 'expenses':
        $date_from = $_GET['from'] ?? date('Y-m-01');
        $date_to   = $_GET['to']   ?? date('Y-m-d');

        $expenses = db_fetch_all($conn,
            "SELECT e.*, u.name as recorded_by_name
             FROM expenses e
             LEFT JOIN users u ON u.id = e.recorded_by
             WHERE DATE(e.created_at) BETWEEN ? AND ?
             ORDER BY e.created_at DESC",
            'ss', [$date_from, $date_to]
        );

        $totals = db_fetch_all($conn,
            "SELECT type, SUM(amount) as total
             FROM expenses
             WHERE DATE(created_at) BETWEEN ? AND ?
             GROUP BY type",
            'ss', [$date_from, $date_to]
        );

        respond([
            'success'  => true,
            'expenses' => $expenses,
            'totals'   => $totals,
        ]);
        break;

    default:
        err('Accion no reconocida. Usa: summary, by_day, shifts, expenses');
}

mysqli_close($conn);