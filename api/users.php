<?php
// ============================================================
// Crazy Moon POS — Users API
// crazymoon_pos/api/users.php
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

    // ── GET: fetch users ──────────────────────────────────
    case 'GET':
        $users = db_fetch_all($conn,
            "SELECT id, name, username, role, active, created_at, last_login
             FROM users
             ORDER BY FIELD(role,'superadmin','manager','staff'), name ASC"
        );
        respond(['success' => true, 'users' => $users]);
        break;

    // ── POST: create new user ─────────────────────────────
    case 'POST':
        $name      = trim($input['name']     ?? '');
        $username  = trim($input['username'] ?? '');
        $pin       = trim($input['pin']      ?? '');
        $role      = trim($input['role']     ?? 'staff');
        $created_by = intval($input['created_by'] ?? 0);

        if (!$name)     err('Nombre requerido');
        if (!$username) err('Username requerido');
        if (!$pin)      err('PIN requerido');
        if (strlen($pin) < 4) err('PIN debe tener al menos 4 digitos');

        // Role validation
        // Manager can only create staff
        // SuperAdmin can create manager or staff
        if (!in_array($role, ['manager', 'staff'])) {
            $role = 'staff';
        }

        // Check username unique
        $existing = db_fetch_one($conn,
            "SELECT id FROM users WHERE username = ?",
            's', [$username]
        );
        if ($existing) err('Username ya existe');

        $pin_hash = password_hash($pin, PASSWORD_BCRYPT);

        $id = db_insert($conn,
            "INSERT INTO users (name, username, pin_hash, role, active, created_by)
             VALUES (?, ?, ?, ?, 1, ?)",
            'ssssi',
            [$name, $username, $pin_hash, $role, $created_by ?: null]
        );
        $id ? respond(['success' => true, 'id' => $id]) : err('Error al crear usuario');
        break;

    // ── PUT: update user ──────────────────────────────────
    case 'PUT':
        $id   = intval($input['id'] ?? 0);
        $name = trim($input['name'] ?? '');
        if (!$id)   err('ID requerido');
        if (!$name) err('Nombre requerido');

        // Update name, username, active status
        $affected = db_update($conn,
            "UPDATE users SET name=?, username=?, active=? WHERE id=? AND role != 'superadmin'",
            'ssii',
            [
                $name,
                trim($input['username'] ?? ''),
                intval($input['active'] ?? 1),
                $id,
            ]
        );

        // Update PIN if provided
        if (!empty($input['pin'])) {
            if (strlen($input['pin']) < 4) err('PIN debe tener al menos 4 digitos');
            $pin_hash = password_hash($input['pin'], PASSWORD_BCRYPT);
            db_update($conn,
                "UPDATE users SET pin_hash=? WHERE id=?",
                'si', [$pin_hash, $id]
            );
        }

        respond(['success' => true, 'affected' => $affected]);
        break;

    // ── DELETE: deactivate user ───────────────────────────
    case 'DELETE':
        $id = intval($input['id'] ?? $_GET['id'] ?? 0);
        if (!$id) err('ID requerido');

        // Never deactivate superadmin
        $affected = db_update($conn,
            "UPDATE users SET active = 0 WHERE id = ? AND role != 'superadmin'",
            'i', [$id]
        );
        respond(['success' => true, 'affected' => $affected]);
        break;

    default:
        err('Metodo no permitido', 405);
}

mysqli_close($conn);