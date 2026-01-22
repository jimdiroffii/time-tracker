<?php
// api.php
/** @var PDO $db */
require 'db.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

switch ($action) {
    case 'start':
        // FIX 1: Prevent multiple timers.
        // If a timer is already running, stop it first.
        $now = time();
        $check = $db->query("SELECT id FROM time_entries WHERE end_time IS NULL LIMIT 1");
        if ($check->fetch()) {
            $stopStmt = $db->prepare("UPDATE time_entries SET end_time = :end WHERE end_time IS NULL");
            $stopStmt->execute([':end' => $now]);
        }

        // Start the new timer
        $stmt = $db->prepare("INSERT INTO time_entries (start_time) VALUES (:start)");
        $stmt->execute([':start' => $now]);
        echo json_encode(['status' => 'success']);
        break;

    case 'stop':
        $now = time();
        $stmt = $db->prepare("UPDATE time_entries SET end_time = :end WHERE end_time IS NULL");
        $stmt->execute([':end' => $now]);
        echo json_encode(['status' => 'success']);
        break;

    case 'update':
        $id = $input['id'];
        $allowedFields = ['project', 'start_time', 'end_time'];
        $updates = [];
        $params = [':id' => $id];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $input)) {
                $updates[] = "$field = :$field";
                $value = $input[$field];

                // FIX 2: SERVER-SIDE XSS PROTECTION
                // Sanitize text fields before saving to the DB.
                // This neutralizes <script> tags into &lt;script&gt;
                if ($field === 'project' && is_string($value)) {
                    $value = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                }

                $params[":$field"] = $value;
            }
        }

        if (!empty($updates)) {
            $sql = "UPDATE time_entries SET " . implode(', ', $updates) . " WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
        }

        echo json_encode(['status' => 'success']);
        break;

    case 'list':
        // UPDATED LOGIC:
        // If inputs are null (cleared by user), default to the full range of time.
        // 2147483647 is the max 32-bit integer (Year 2038), effectively "forever" for this MVP.
        $to = $input['to'] ?? 2147483647;
        $from = $input['from'] ?? 0;      // Jan 1, 1970

        $stmt = $db->prepare("SELECT * FROM time_entries WHERE start_time BETWEEN :from AND :to ORDER BY start_time DESC");
        $stmt->execute([':from' => $from, ':to' => $to]);

        $entries = $stmt->fetchAll();
        echo json_encode(['status' => 'success', 'data' => $entries]);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        break;
}