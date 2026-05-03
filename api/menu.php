<?php
// ============================================================
// Crazy Moon POS — Menu API
// crazymoon_pos/api/menu.php
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

    // ── GET: fetch all active menu items ──────────────────
    case 'GET':
        $items = db_fetch_all($conn,
            "SELECT * FROM menu_items WHERE active = 1 ORDER BY sort_order ASC, category ASC"
        );
        respond(['success' => true, 'items' => $items]);
        break;

    // ── POST: add new menu item (Super Admin only) ────────
    case 'POST':
        $name = trim($input['name'] ?? '');
        if (!$name) err('Nombre requerido');

        $id = db_insert($conn,
            "INSERT INTO menu_items (name, category, description, price, price_300, price_500, badge, label, active, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)",
            'sssdddssi',
            [
                $name,
                trim($input['category']    ?? ''),
                trim($input['description'] ?? ''),
                $input['price']     ?? null,
                $input['price_300'] ?? null,
                $input['price_500'] ?? null,
                trim($input['badge'] ?? ''),
                trim($input['label'] ?? ''),
                intval($input['sort_order'] ?? 0),
            ]
        );
        $id ? respond(['success' => true, 'id' => $id]) : err('Error al guardar');
        break;

    // ── PUT: update menu item (Super Admin only) ──────────
    case 'PUT':
        $id = intval($input['id'] ?? 0);
        if (!$id) err('ID requerido');

        $name = trim($input['name'] ?? '');
        if (!$name) err('Nombre requerido');

        $affected = db_update($conn,
            "UPDATE menu_items SET name=?, category=?, description=?, price=?, price_300=?, price_500=?,
             badge=?, label=?, active=?, sort_order=? WHERE id=?",
            'sssdddsiii',
            [
                $name,
                trim($input['category']    ?? ''),
                trim($input['description'] ?? ''),
                $input['price']     ?? null,
                $input['price_300'] ?? null,
                $input['price_500'] ?? null,
                trim($input['badge'] ?? ''),
                trim($input['label'] ?? ''),
                intval($input['active'] ?? 1),
                intval($input['sort_order'] ?? 0),
                $id,
            ]
        );
        respond(['success' => true, 'affected' => $affected]);
        break;

    // ── DELETE: deactivate menu item (Super Admin only) ───
    case 'DELETE':
        $id = intval($input['id'] ?? $_GET['id'] ?? 0);
        if (!$id) err('ID requerido');

        $affected = db_update($conn,
            "UPDATE menu_items SET active = 0 WHERE id = ?",
            'i', [$id]
        );
        respond(['success' => true, 'affected' => $affected]);
        break;

    default:
        err('Metodo no permitido', 405);
}

mysqli_close($conn);