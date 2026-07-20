<?php
session_start();
header('Content-Type: application/json');
require 'db_user.php';

if (!isset($_SESSION['role']) || strtolower((string) $_SESSION['role']) !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak.']);
    exit;
}

function umRespond($payload) {
    echo json_encode($payload);
    exit;
}

function umColumns($pdo, $table) {
    $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
    $columns = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $columns[$row['Field']] = true;
    }
    return $columns;
}

function umRequireValue($value, $label) {
    $value = trim((string) $value);
    if ($value === '') {
        umRespond(['status' => 'error', 'message' => "{$label} wajib diisi."]);
    }
    return $value;
}

function umJsonPayload() {
    $payload = json_decode(file_get_contents('php://input'), true);
    return is_array($payload) ? $payload : [];
}

$allowedUserRoles = ['Admin', 'Project Manager', 'Site Engineer'];
$allowedAssignmentRoles = ['Project Manager', 'Site Engineer'];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestPayload = $method === 'POST' ? umJsonPayload() : [];
$action = $method === 'GET' ? ($_GET['action'] ?? 'list') : ($requestPayload['action'] ?? '');

try {
    $assignmentColumns = umColumns($pdo, 'project_assignments');
    $assignmentHasId = isset($assignmentColumns['id']);

    if ($action === 'list') {
        $users = $pdo
            ->query("SELECT id, username, full_name, role FROM users ORDER BY role ASC, full_name ASC, username ASC")
            ->fetchAll(PDO::FETCH_ASSOC);

        $projects = $pdo
            ->query("SELECT project_id, project_name, status FROM projects ORDER BY project_name ASC")
            ->fetchAll(PDO::FETCH_ASSOC);

        $assignmentIdSelect = $assignmentHasId ? 'pa.id' : 'NULL AS id';
        $assignments = $pdo
            ->query("
                SELECT
                    {$assignmentIdSelect},
                    pa.user_id,
                    pa.project_id,
                    pa.role_assigned,
                    u.username,
                    u.full_name,
                    p.project_name
                FROM project_assignments pa
                LEFT JOIN users u ON u.id = pa.user_id
                LEFT JOIN projects p ON p.project_id = pa.project_id
                ORDER BY p.project_name ASC, pa.role_assigned ASC, u.full_name ASC
            ")
            ->fetchAll(PDO::FETCH_ASSOC);

        umRespond([
            'status' => 'success',
            'users' => $users,
            'projects' => $projects,
            'assignments' => $assignments,
        ]);
    }

    if ($method !== 'POST') {
        umRespond(['status' => 'error', 'message' => 'Metode request tidak valid.']);
    }

    $data = $requestPayload;

    if (($data['action'] ?? '') === 'save_user') {
        $id = trim((string) ($data['id'] ?? ''));
        $username = umRequireValue($data['username'] ?? '', 'Username');
        $fullName = umRequireValue($data['full_name'] ?? '', 'Nama lengkap');
        $role = umRequireValue($data['role'] ?? '', 'Role');
        $password = (string) ($data['password'] ?? '');

        if (!in_array($role, $allowedUserRoles, true)) {
            umRespond(['status' => 'error', 'message' => 'Role user tidak valid.']);
        }

        if ($id === '' && trim($password) === '') {
            umRespond(['status' => 'error', 'message' => 'Password wajib diisi untuk user baru.']);
        }

        $uniqueStmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND (? = '' OR id <> ?) LIMIT 1");
        $uniqueStmt->execute([$username, $id, $id]);
        if ($uniqueStmt->fetch(PDO::FETCH_ASSOC)) {
            umRespond(['status' => 'error', 'message' => 'Username sudah digunakan.']);
        }

        if ($id !== '') {
            $fields = ['username = ?', 'full_name = ?', 'role = ?'];
            $params = [$username, $fullName, $role];
            if (trim($password) !== '') {
                $fields[] = 'password = ?';
                $params[] = password_hash($password, PASSWORD_DEFAULT);
            }
            $params[] = $id;
            $stmt = $pdo->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?');
            $stmt->execute($params);
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $fullName, $role]);
        }

        umRespond(['status' => 'success', 'message' => 'Data user berhasil disimpan.']);
    }

    if (($data['action'] ?? '') === 'delete_user') {
        $id = umRequireValue($data['id'] ?? '', 'User ID');
        if (isset($_SESSION['user_id']) && (string) $_SESSION['user_id'] === (string) $id) {
            umRespond(['status' => 'error', 'message' => 'User yang sedang login tidak dapat dihapus.']);
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("DELETE FROM project_assignments WHERE user_id = ?");
        $stmt->execute([$id]);
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $pdo->commit();

        umRespond(['status' => 'success', 'message' => 'User berhasil dihapus.']);
    }

    if (($data['action'] ?? '') === 'save_assignment') {
        $id = trim((string) ($data['id'] ?? ''));
        $userId = umRequireValue($data['user_id'] ?? '', 'User');
        $projectId = umRequireValue($data['project_id'] ?? '', 'Project');
        $roleAssigned = umRequireValue($data['role_assigned'] ?? '', 'Role assignment');

        if (!in_array($roleAssigned, $allowedAssignmentRoles, true)) {
            umRespond(['status' => 'error', 'message' => 'Role assignment tidak valid.']);
        }

        $stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            umRespond(['status' => 'error', 'message' => 'User tidak ditemukan.']);
        }
        if ($user['role'] !== $roleAssigned) {
            umRespond(['status' => 'error', 'message' => 'Role assignment harus sama dengan role user.']);
        }

        $stmt = $pdo->prepare("SELECT project_id FROM projects WHERE project_id = ? LIMIT 1");
        $stmt->execute([$projectId]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            umRespond(['status' => 'error', 'message' => 'Project tidak ditemukan.']);
        }

        $originalUserId = trim((string) ($data['original_user_id'] ?? ''));
        $originalProjectId = trim((string) ($data['original_project_id'] ?? ''));
        $originalRole = trim((string) ($data['original_role_assigned'] ?? ''));

        $duplicateSql = "SELECT " . ($assignmentHasId ? "id" : "user_id") . " FROM project_assignments WHERE user_id = ? AND project_id = ? AND role_assigned = ?";
        $duplicateParams = [$userId, $projectId, $roleAssigned];
        if ($assignmentHasId && $id !== '') {
            $duplicateSql .= " AND id <> ?";
            $duplicateParams[] = $id;
        } elseif (!$assignmentHasId && $originalUserId !== '' && $originalProjectId !== '' && $originalRole !== '') {
            $duplicateSql .= " AND NOT (user_id = ? AND project_id = ? AND role_assigned = ?)";
            array_push($duplicateParams, $originalUserId, $originalProjectId, $originalRole);
        }
        $duplicateSql .= " LIMIT 1";
        $stmt = $pdo->prepare($duplicateSql);
        $stmt->execute($duplicateParams);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            umRespond(['status' => 'error', 'message' => 'Assignment tersebut sudah ada.']);
        }

        $pdo->beginTransaction();
        if ($assignmentHasId && $id !== '') {
            $stmt = $pdo->prepare("UPDATE project_assignments SET user_id = ?, project_id = ?, role_assigned = ? WHERE id = ?");
            $stmt->execute([$userId, $projectId, $roleAssigned, $id]);
        } else {
            if ($originalUserId !== '' && $originalProjectId !== '' && $originalRole !== '') {
                $stmt = $pdo->prepare("DELETE FROM project_assignments WHERE user_id = ? AND project_id = ? AND role_assigned = ?");
                $stmt->execute([$originalUserId, $originalProjectId, $originalRole]);
            }
            $stmt = $pdo->prepare("INSERT INTO project_assignments (project_id, user_id, role_assigned) VALUES (?, ?, ?)");
            $stmt->execute([$projectId, $userId, $roleAssigned]);
        }
        $pdo->commit();

        umRespond(['status' => 'success', 'message' => 'Project assignment berhasil disimpan.']);
    }

    if (($data['action'] ?? '') === 'delete_assignment') {
        $id = trim((string) ($data['id'] ?? ''));
        if ($assignmentHasId && $id !== '') {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("DELETE FROM project_assignments WHERE id = ?");
            $stmt->execute([$id]);
            $pdo->commit();
        } else {
            $userId = umRequireValue($data['user_id'] ?? '', 'User');
            $projectId = umRequireValue($data['project_id'] ?? '', 'Project');
            $roleAssigned = umRequireValue($data['role_assigned'] ?? '', 'Role assignment');
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("DELETE FROM project_assignments WHERE user_id = ? AND project_id = ? AND role_assigned = ?");
            $stmt->execute([$userId, $projectId, $roleAssigned]);
            $pdo->commit();
        }

        umRespond(['status' => 'success', 'message' => 'Project assignment berhasil dihapus.']);
    }

    umRespond(['status' => 'error', 'message' => 'Action tidak dikenali.']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    umRespond(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
