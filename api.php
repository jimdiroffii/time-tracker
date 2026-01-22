<?php
// api.php
/** @var PDO $db */
require 'db.php'; // PhpStorm now knows $db comes from here

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

switch ($action) {
    case 'start':
        $now = time();
        // FIXED: Removed the duplicate line referencing 'created_at'
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

        // Dynamically build the SQL based on what was sent
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $input)) {
                $updates[] = "$field = :$field";
                $params[":$field"] = $input[$field];
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
        // Default to last 30 days if no range provided
        $to = $input['to'] ?? time();
        $from = $input['from'] ?? (time() - (30 * 24 * 60 * 60));

        $stmt = $db->prepare("SELECT * FROM time_entries WHERE start_time BETWEEN :from AND :to ORDER BY start_time DESC");
        $stmt->execute([':from' => $from, ':to' => $to]);

        $entries = $stmt->fetchAll();
        echo json_encode(['status' => 'success', 'data' => $entries]);
        break;
}