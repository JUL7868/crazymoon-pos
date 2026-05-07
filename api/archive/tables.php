<?php
// ============================================================
// Crazy Moon POS — Tables API
// crazymoon_pos/api/tables.php
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

switch ($method) {

    // ── GET: fetch all active tables with open order info ─
    case 'GET':
        $tables = db_fetch_all($conn,
            "SELECT t.*, 
                o.id as order_id,
                o.total as order_total,
                o.status as order_status,
                o.created_at as order_opened_at
             FROM pos_tables t
             LEFT JOIN orders o ON o.pos_table_id = t.id AND o.status = 'open'
             WHERE t.active = 1
             ORDER BY t.sort_order ASC"
        );
        respond(['success' => true, 'tables' => $tables]);
        break;

    // ── POST: add new table (Super Admin only) ────────────
    case 'POST':
        $name = trim($input['name'] ?? '');
        if (!$name) err('Nombre requerido');

        $id = db_insert($conn,
            "INSERT INTO pos_tables (name, type, sort_order, active) VALUES (?, ?, ?, 1)",
            'ssi',
            [
                $name,
                $input['type']       ?? 'mesa',
                intval($input['sort_order'] ?? 0),
            ]
        );
        $id ? respond(['success' => true, 'id' => $id]) : err('Error al guardar');
        break;

    // ── PUT: update table (Super Admin only) ──────────────
    case 'PUT':
        $id = intval($input['id'] ?? 0);
        if (!$id) err('ID requerido');

        $affected = db_update($conn,
            "UPDATE pos_tables SET name=?, type=?, sort_order=?, active=? WHERE id=?",
            'ssiii',
            [
                trim($input['name'] ?? ''),
                $input['type']       ?? 'mesa',
                intval($input['sort_order'] ?? 0),
                intval($input['active']     ?? 1),
                $id,
            ]
        );
        respond(['success' => true, 'affected' => $affected]);
        break;

    // ── DELETE: deactivate table (Super Admin only) ───────
    case 'DELETE':
        $id = intval($input['id'] ?? $_GET['id'] ?? 0);
        if (!$id) err('ID requerido');

        $affected = db_update($conn,
            "UPDATE pos_tables SET active = 0 WHERE id = ?",
            'i', [$id]
        );
        respond(['success' => true, 'affected' => $affected]);
        break;

    default:
        err('Metodo no permitido', 405);
}

mysqli_close($conn);