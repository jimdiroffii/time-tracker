<?php
// db.php

// 1. Connect to the SQLite database file
// If the file doesn't exist, SQLite3 will create it automatically.
try {
    $db = new PDO('sqlite:tracker.sqlite');
    // Enable exceptions for errors
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Fetch results as associative arrays by default
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// 2. Define the Schema
// We use INTEGER for start/end times to store Unix Timestamps.
// This makes math (end - start = duration) trivial.
$schema = "
CREATE TABLE IF NOT EXISTS time_entries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project TEXT DEFAULT NULL,
    description TEXT DEFAULT NULL,
    start_time INTEGER NOT NULL,
    end_time INTEGER DEFAULT NULL
);
";

// 3. Execute Schema Creation
$db->exec($schema);
