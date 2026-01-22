<?php

$dbPath = getenv('DB_PATH') ?: 'tracker.sqlite';

try {
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$schema = "
CREATE TABLE IF NOT EXISTS time_entries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project TEXT DEFAULT NULL,
    description TEXT DEFAULT NULL,
    start_time INTEGER NOT NULL,
    end_time INTEGER DEFAULT NULL
);
";

$db->exec($schema);
