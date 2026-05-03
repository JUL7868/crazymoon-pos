<?php
// ============================================================
// Crazy Moon POS — Auth API
// crazymoon_pos/api/auth.php
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

session_start_secure();

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

$conn = db_connect();

switch ($action) {

    // ── Get all active users for login screen ─────────────
    case 'get_users':
        $users = db_fetch_all($conn,
            "SELECT id, name, role FROM users WHERE active = 1 ORDER BY role DESC, name ASC"
        );
        respond(['success' => true, 'users' => $users]);
        break;

    // ── Verify PIN and create session ─────────────────────
    case 'login':
        $user_id = intval($input['user_id'] ?? 0);
        $pin     = trim($input['pin'] ?? '');

        if (!$user_id || !$pin) err('Datos incompletos');

        $user = db_fetch_one($conn,
            "SELECT id, name, username, pin_hash, role FROM users WHERE id = ? AND active = 1",
            'i', [$user_id]
        );

        if (!$user) err('Usuario no encontrado');

        if (!password_verify($pin, $user['pin_hash'])) {
            err('PIN incorrecto');
        }

        // Create session
        $_SESSION['user_id']       = $user['id'];
        $_SESSION['user_name']     = $user['name'];
        $_SESSION['role']          = $user['role'];
        $_SESSION['last_activity'] = time();

        // Update last login
        db_update($conn,
            "UPDATE users SET last_login = NOW() WHERE id = ?",
            'i', [$user['id']]
        );

        log_activity($conn, 'login', $user['name'] . ' logged in');

        respond([
            'success'   => true,
            'user_id'   => $user['id'],
            'user_name' => $user['name'],
            'role'      => $user['role'],
        ]);
        break;

    // ── Check current session ─────────────────────────────
    case 'check':
        if (is_logged_in()) {
            respond([
                'success'   => true,
                'logged_in' => true,
                'user_id'   => $_SESSION['user_id'],
                'user_name' => $_SESSION['user_name'],
                'role'      => $_SESSION['role'],
            ]);
        } else {
            respond(['success' => true, 'logged_in' => false]);
        }
        break;

    // ── Logout ────────────────────────────────────────────
    case 'logout':
        log_activity($conn, 'logout', ($_SESSION['user_name'] ?? 'Unknown') . ' logged out');
        session_unset();
        session_destroy();
        respond(['success' => true]);
        break;

    default:
        err('Accion no reconocida', 405);
}

mysqli_close($conn);