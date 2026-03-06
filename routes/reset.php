<?php
// routes/reset.php
// POST /api/reset - clears all game data for testing

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';

function handleReset(): void {
    $db = getDB();
    
    try {
        $db->exec('DELETE FROM moves');
        $db->exec('DELETE FROM ships');
        $db->exec('DELETE FROM game_players');
        $db->exec('DELETE FROM games');
        $db->exec('DELETE FROM players');
        
        $db->exec('ALTER SEQUENCE players_player_id_seq RESTART WITH 1');
        $db->exec('ALTER SEQUENCE games_game_id_seq RESTART WITH 1');
        $db->exec('ALTER SEQUENCE ships_ship_id_seq RESTART WITH 1');
        $db->exec('ALTER SEQUENCE moves_move_id_seq RESTART WITH 1');
        
        jsonResponse(['status' => 'reset']);
    } catch (PDOException $e) {
        errorResponse('Reset failed: ' . $e->getMessage(), 500);
    }
}