<?php
// ============================================================
// Crazy Moon POS — Expenses API
// crazymoon_pos/api/expenses.php
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

    // ── GET: fetch expenses ───────────────────────────────
    case 'GET':
        $shift_id = intval($_GET['shift_id'] ?? 0);

        if ($shift_id) {
            $expenses = db_fetch_all($conn,
                "SELECT e.*, u.name as recorded_by_name
                 FROM expenses e
                 LEFT JOIN users u ON u.id = e.recorded_by
                 WHERE e.shift_id = ?
                 ORDER BY e.created_at DESC",
                'i', [$shift_id]
            );
        } else {
            $expenses = db_fetch_all($conn,
                "SELECT e.*, u.name as recorded_by_name
                 FROM expenses e
                 LEFT JOIN users u ON u.id = e.recorded_by
                 ORDER BY e.created_at DESC
                 LIMIT 100"
            );
        }
        respond(['success' => true, 'expenses' => $expenses]);
        break;

    // ── POST: record new expense ──────────────────────────
    case 'POST':
        $type   = trim($input['type']   ?? '');
        $amount = floatval($input['amount'] ?? 0);
        $notes  = trim($input['notes']  ?? '');

        if (!$type)   err('Tipo requerido');
        if (!$amount) err('Monto requerido');
        if (!$notes)  err('Notas requeridas');

        if (!in_array($type, ['cogs', 'salary', 'opex'])) {
            err('Tipo invalido. Usa: cogs, salary, opex');
        }

        $id = db_insert($conn,
            "INSERT INTO expenses (shift_id, type, amount, notes, recorded_by)
             VALUES (?, ?, ?, ?, ?)",
            'isdsi',
            [
                $input['shift_id']   ?? null,
                $type,
                $amount,
                $notes,
                $input['user_id']    ?? null,
            ]
        );
        $id ? respond(['success' => true, 'id' => $id]) : err('Error al guardar gasto');
        break;

    // ── PUT: update expense ───────────────────────────────
    case 'PUT':
        $id     = intval($input['id'] ?? 0);
        $amount = floatval($input['amount'] ?? 0);
        $notes  = trim($input['notes'] ?? '');

        if (!$id)     err('ID requerido');
        if (!$amount) err('Monto requerido');
        if (!$notes)  err('Notas requeridas');

        $affected = db_update($conn,
            "UPDATE expenses SET type=?, amount=?, notes=? WHERE id=?",
            'sdsi',
            [
                trim($input['type'] ?? ''),
                $amount,
                $notes,
                $id,
            ]
        );
        respond(['success' => true, 'affected' => $affected]);
        break;

    // ── DELETE: remove expense ────────────────────────────
    case 'DELETE':
        $id = intval($input['id'] ?? $_GET['id'] ?? 0);
        if (!$id) err('ID requerido');

        $affected = db_update($conn, "DELETE FROM expenses WHERE id = ?", 'i', [$id]);
        respond(['success' => true, 'affected' => $affected]);
        break;

    default:
        err('Metodo no permitido', 405);
}

mysqli_close($conn);