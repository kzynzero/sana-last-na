<?php
declare(strict_types=1);

session_start();
header("Content-Type: application/json; charset=utf-8");

require __DIR__ . "/db.php";

function jsonOut(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function inputData(): array
{
    $raw = file_get_contents("php://input");
    $decoded = $raw ? json_decode($raw, true) : null;
    return is_array($decoded) ? $decoded : ($_POST ?: []);
}

$action = $_GET["action"] ?? "";
$conn   = db();

switch ($action) {

    // ── CHECK SESSION ──────────────────────────────────────────────────────────
    case "check":
        if (!empty($_SESSION["user_id"])) {
            $stmt = $conn->prepare(
                "SELECT users_id, username, full_name, role, email, contact,
                        (profile_photo IS NOT NULL) AS has_photo
                 FROM users WHERE users_id = ? AND is_active = 1"
            );
            $stmt->bind_param("i", $_SESSION["user_id"]);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            if ($user) {
                jsonOut(["success" => true, "user" => [
                    "dbId"     => (int) $user["users_id"],
                    "username" => $user["username"],
                    "fullName" => $user["full_name"],
                    "role"     => $user["role"],
                    "position" => ROLES[$user["role"]] ?? ucfirst($user["role"]),
                    "email"    => $user["email"],
                    "contact"  => $user["contact"],
                    "hasPhoto" => (bool) $user["has_photo"],
                ]]);
            }
        }
        jsonOut(["success" => false, "error" => "Not logged in."]);

    // ── LOGIN ──────────────────────────────────────────────────────────────────
    case "login":
        $data = inputData();
        $username = trim((string) ($data["username"] ?? ""));
        $password = (string) ($data["password"] ?? "");

        if (!$username || !$password) {
            jsonOut(["success" => false, "error" => "Username and password are required."], 422);
        }

        $stmt = $conn->prepare(
            "SELECT users_id, username, password_hash, full_name, role, email, contact,
                    (profile_photo IS NOT NULL) AS has_photo, is_active, is_online
             FROM users WHERE username = ?"
        );
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user || !password_verify($password, $user["password_hash"])) {
            jsonOut(["success" => false, "error" => "Invalid username or password."], 401);
        }

        if (!$user["is_active"]) {
            jsonOut(["success" => false, "error" => "This account has been deactivated."], 403);
        }

        // Role uniqueness check: only one active session per role
        $role = $user["role"];
        $uid  = (int) $user["users_id"];

        $onlineCheck = $conn->prepare(
            "SELECT users_id, username FROM users
             WHERE role = ? AND is_online = 1 AND users_id != ?"
        );
        $onlineCheck->bind_param("si", $role, $uid);
        $onlineCheck->execute();
        $conflict = $onlineCheck->get_result()->fetch_assoc();

        if ($conflict) {
            $roleName = ROLES[$role] ?? ucfirst($role);
            jsonOut([
                "success" => false,
                "error"   => "Access denied. The {$roleName} role is already occupied by \"{$conflict["username"]}\". Only one user per role may be logged in at a time.",
            ], 403);
        }

        // Mark online, set session
        $conn->query("UPDATE users SET is_online = 1 WHERE users_id = {$uid}");
        $_SESSION["user_id"] = $uid;

        jsonOut(["success" => true, "user" => [
            "dbId"     => $uid,
            "username" => $user["username"],
            "fullName" => $user["full_name"],
            "role"     => $user["role"],
            "position" => ROLES[$user["role"]] ?? ucfirst($user["role"]),
            "email"    => $user["email"],
            "contact"  => $user["contact"],
            "hasPhoto" => (bool) $user["has_photo"],
        ]]);

    // ── LOGOUT ─────────────────────────────────────────────────────────────────
    case "logout":
        if (!empty($_SESSION["user_id"])) {
            $uid = (int) $_SESSION["user_id"];
            $conn->query("UPDATE users SET is_online = 0 WHERE users_id = {$uid}");
        }
        session_destroy();
        jsonOut(["success" => true]);

    // ── SIGNUP (register new user) ─────────────────────────────────────────────
    case "signup":
        $data     = inputData();
        $username = trim((string) ($data["username"] ?? ""));
        $password = (string) ($data["password"] ?? "");
        $fullName = trim((string) ($data["fullName"] ?? ""));
        $role     = trim((string) ($data["role"] ?? ""));
        $email    = trim((string) ($data["email"] ?? ""));
        $contact  = trim((string) ($data["contact"] ?? ""));

        if (!$username || !$password || !$fullName || !$role) {
            jsonOut(["success" => false, "error" => "Username, password, full name, and role are required."], 422);
        }

        if (!array_key_exists($role, ROLES)) {
            jsonOut(["success" => false, "error" => "Invalid role selected."], 422);
        }

        // One user per role allowed (total, not just online) — prevent duplicate roles in the system
        $roleCount = $conn->prepare("SELECT COUNT(*) AS total FROM users WHERE role = ?");
        $roleCount->bind_param("s", $role);
        $roleCount->execute();
        $count = (int) $roleCount->get_result()->fetch_assoc()["total"];

        if ($count > 0) {
            $roleName = ROLES[$role] ?? ucfirst($role);
            jsonOut(["success" => false, "error" => "A user with the role \"{$roleName}\" already exists. Each role can only have one account."], 409);
        }

        // Check username uniqueness
        $userCheck = $conn->prepare("SELECT users_id FROM users WHERE username = ?");
        $userCheck->bind_param("s", $username);
        $userCheck->execute();
        if ($userCheck->get_result()->fetch_assoc()) {
            jsonOut(["success" => false, "error" => "Username \"{$username}\" is already taken."], 409);
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare(
            "INSERT INTO users (username, password_hash, full_name, role, email, contact, is_active, is_online)
             VALUES (?, ?, ?, ?, ?, ?, 1, 0)"
        );
        $stmt->bind_param("ssssss", $username, $hash, $fullName, $role, $email, $contact);
        $stmt->execute();

        jsonOut(["success" => true, "message" => "Account created successfully. You may now log in."]);

    // ── CHECK TAKEN ROLES (for signup page) ────────────────────────────────────
    case "check-roles":
        $result = $conn->query("SELECT role FROM users WHERE is_active = 1");
        $taken = [];
        while ($row = $result->fetch_assoc()) {
            $taken[] = $row["role"];
        }
        jsonOut(["success" => true, "takenRoles" => $taken, "allRoles" => ROLES]);

    default:
        jsonOut(["success" => false, "error" => "Unknown action."], 404);
}
