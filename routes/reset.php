<?php
// routes/reset.php
// POST /api/reset - clears all game data for testing

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';

function handleReset(): void {
    $db = getDB();
    
    try {
        $db->exec('TRUNCATE TABLE moves, ships, game_players, games, players RESTART IDENTITY CASCADE');
        jsonResponse(['status' => 'reset']);
    } catch (PDOException $e) {
        errorResponse('Reset failed: ' . $e->getMessage(), 500);
    }
}
