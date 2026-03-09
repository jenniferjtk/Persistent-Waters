<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';

function handleSetup(): void {
    $db = getDB();
    try {
        $sql = file_get_contents(__DIR__ . '/../config/schema.sql');
        $db->exec($sql);
        jsonResponse(['status' => 'setup complete']);
    } catch (PDOException $e) {
        errorResponse('Setup failed: ' . $e->getMessage(), 500);
    }
}