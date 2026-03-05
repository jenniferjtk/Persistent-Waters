<?php
// routes/test.php
// Test mode endpoints - only available when TEST_MODE = true

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';

define('TEST_MODE', true); // Set to false in production
define('TEST_PASSWORD', 'clemson-test-2026');

function checkTestAuth(): void {
    if (!TEST_MODE) {
        errorResponse('Test mode is disabled', 403);
    }
    $headers = getallheaders();
    $password = $headers['X-Test-Password'] ?? '';
    if ($password !== TEST_PASSWORD) {
        errorResponse('Forbidden', 403);
    }
}

function handleTestRoute(string $method, array $parts): void {
    checkTestAuth();

    // POST /api/test/games/{id}/restart
    if ($method === 'POST' && isset($parts[4]) && $parts[4] === 'restart') {
        handleTestRestart((int)$parts[3]);
        return;
    }

    // POST /api/test/games/{id}/ships
    if ($method === 'POST' && isset($parts[4]) && $parts[4] === 'ships') {
        handleTestPlaceShips((int)$parts[3]);
        return;
    }

    // GET /api/test/games/{id}/board/{player_id}
    if ($method === 'GET' && isset($parts[4]) && $parts[4] === 'board') {
        handleTestGetBoard((int)$parts[3], (int)$parts[5]);
        return;
    }

    errorResponse('Test endpoint not found', 404);
}

function handleTestRestart(int $gameId): void {
    $db = getDB();

    try {
        $stmt = $db->prepare('SELECT game_id FROM games WHERE game_id = ?');
        $stmt->execute([$gameId]);
        if (!$stmt->fetch()) errorResponse('Game not found', 404);

        $db->beginTransaction();

        // Reset ships and moves
        $db->prepare('DELETE FROM moves WHERE game_id = ?')->execute([$gameId]);
        $db->prepare('DELETE FROM ships WHERE game_id = ?')->execute([$gameId]);

        // Reset game state
        $db->prepare("
            UPDATE games SET status = 'waiting', current_turn_index = 0 
            WHERE game_id = ?
        ")->execute([$gameId]);

        // Reset player flags but keep stats
        $db->prepare("
            UPDATE game_players 
            SET is_eliminated = FALSE, ships_placed = FALSE 
            WHERE game_id = ?
        ")->execute([$gameId]);

        $db->commit();

        jsonResponse(['status' => 'restarted']);

    } catch (PDOException $e) {
        $db->rollBack();
        errorResponse('Failed to restart game', 500);
    }
}

function handleTestPlaceShips(int $gameId): void {
    $db = getDB();
    $body = json_decode(file_get_contents('php://input'), true);

    if (empty($body['player_id'])) errorResponse('player_id is required', 400);
    if (empty($body['ships']) || count($body['ships']) !== 3) {
        errorResponse('Exactly 3 ships required', 400);
    }

    $playerId = (int)$body['player_id'];

    try {
        $stmt = $db->prepare('SELECT * FROM games WHERE game_id = ?');
        $stmt->execute([$gameId]);
        $game = $stmt->fetch();
        if (!$game) errorResponse('Game not found', 404);

        $db->beginTransaction();

        // Remove existing ships for this player in this game
        $db->prepare('DELETE FROM ships WHERE game_id = ? AND player_id = ?')
           ->execute([$gameId, $playerId]);

        // Insert new ships
        $stmt = $db->prepare('
            INSERT INTO ships (game_id, player_id, row_pos, col_pos) 
            VALUES (?, ?, ?, ?)
        ');
        foreach ($body['ships'] as $ship) {
            $stmt->execute([$gameId, $playerId, (int)$ship['row'], (int)$ship['col']]);
        }

        // Mark ships placed
        $db->prepare('
            UPDATE game_players SET ships_placed = TRUE 
            WHERE game_id = ? AND player_id = ?
        ')->execute([$gameId, $playerId]);

        // Check if all players placed
        $stmt = $db->prepare('
            SELECT COUNT(*) as total,
            SUM(CASE WHEN ships_placed THEN 1 ELSE 0 END) as placed
            FROM game_players WHERE game_id = ?
        ');
        $stmt->execute([$gameId]);
        $counts = $stmt->fetch();

        if ($counts['total'] == $counts['placed']) {
            $db->prepare("UPDATE games SET status = 'active' WHERE game_id = ?")
               ->execute([$gameId]);
        }

        $db->commit();

        jsonResponse(['status' => 'ships placed']);

    } catch (PDOException $e) {
        $db->rollBack();
        errorResponse('Failed to place ships', 500);
    }
}

function handleTestGetBoard(int $gameId, int $playerId): void {
    $db = getDB();

    try {
        $stmt = $db->prepare('
            SELECT row_pos AS row, col_pos AS col, is_hit 
            FROM ships WHERE game_id = ? AND player_id = ?
        ');
        $stmt->execute([$gameId, $playerId]);
        $ships = $stmt->fetchAll();

        $allShips = array_map(fn($s) => ['row' => $s['row'], 'col' => $s['col']], $ships);
        $hits = array_map(
            fn($s) => ['row' => $s['row'], 'col' => $s['col']], 
            array_filter($ships, fn($s) => $s['is_hit'])
        );

        jsonResponse([
            'ships' => $allShips,
            'hits' => array_values($hits)
        ]);

    } catch (PDOException $e) {
        errorResponse('Failed to get board', 500);
    }
}