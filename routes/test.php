<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';

define('TEST_PASSWORD', 'clemson-test-2026');

function checkTestAuth(): void {
    $headers = getallheaders();
    // Accept both header names
    $password = $headers['X-Test-Password'] 
        ?? $headers['X-Test-Mode'] 
        ?? '';
    if ($password !== TEST_PASSWORD) {
        errorResponse('Forbidden', 403);
    }
}

function handleTestRoute(string $method, array $parts): void {
    checkTestAuth();

    // Work out game ID - handle both /test/games/{id}/... and /api/test/games/{id}/...
    // /test/games/{id}/reset  -> parts[0]=test, parts[1]=games, parts[2]=id, parts[3]=action
    // /api/test/games/{id}/reset -> parts[0]=api, parts[1]=test, parts[2]=games, parts[3]=id, parts[4]=action

    if ($parts[0] === 'test') {
        // /test/games/{id}/action
        $gameId = (int)($parts[2] ?? 0);
        $action = $parts[3] ?? '';
        $extra = $parts[4] ?? '';
    } else {
        // /api/test/games/{id}/action
        $gameId = (int)($parts[3] ?? 0);
        $action = $parts[4] ?? '';
        $extra = $parts[5] ?? '';
    }

    // POST .../reset or .../restart
    if ($method === 'POST' && ($action === 'reset' || $action === 'restart')) {
        handleTestRestart($gameId);
        return;
    }

    // POST .../ships
    if ($method === 'POST' && $action === 'ships') {
        handleTestPlaceShips($gameId);
        return;
    }

    // GET .../board (query param or URL segment for player_id)
    if ($method === 'GET' && $action === 'board') {
        $playerId = isset($_GET['playerId']) 
            ? (int)$_GET['playerId'] 
            : (int)$extra;
        handleTestGetBoard($gameId, $playerId);
        return;
    }

    // POST .../set-turn
    if ($method === 'POST' && $action === 'set-turn') {
        handleTestSetTurn($gameId);
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
        $db->prepare('DELETE FROM moves WHERE game_id = ?')->execute([$gameId]);
        $db->prepare('DELETE FROM ships WHERE game_id = ?')->execute([$gameId]);
        $db->prepare("UPDATE games SET status = 'waiting', current_turn_index = 0 WHERE game_id = ?")->execute([$gameId]);
        $db->prepare("UPDATE game_players SET is_eliminated = FALSE, ships_placed = FALSE WHERE game_id = ?")->execute([$gameId]);
        $db->commit();

        jsonResponse(['status' => 'reset']);
    } catch (PDOException $e) {
        $db->rollBack();
        errorResponse('Failed to reset game', 500);
    }
}

function handleTestPlaceShips(int $gameId): void {
    $db = getDB();
    $body = json_decode(file_get_contents('php://input'), true);

    // Accept both playerId and player_id
    $playerId = (int)($body['playerId'] ?? $body['player_id'] ?? 0);
    if (!$playerId) errorResponse('playerId is required', 400);

    $ships = $body['ships'] ?? [];
    if (empty($ships)) errorResponse('ships are required', 400);

    try {
        $stmt = $db->prepare('SELECT * FROM games WHERE game_id = ?');
        $stmt->execute([$gameId]);
        $game = $stmt->fetch();
        if (!$game) errorResponse('Game not found', 404);

        $db->beginTransaction();
        $db->prepare('DELETE FROM ships WHERE game_id = ? AND player_id = ?')->execute([$gameId, $playerId]);

        $stmt = $db->prepare('INSERT INTO ships (game_id, player_id, row_pos, col_pos) VALUES (?, ?, ?, ?)');

        foreach ($ships as $ship) {
            // Handle both coordinate formats
            // New format: {"type": "destroyer", "coordinates": [[0,0],[0,1]]}
            // Old format: {"row": 0, "col": 0}
            if (isset($ship['coordinates'])) {
                foreach ($ship['coordinates'] as $coord) {
                    $stmt->execute([$gameId, $playerId, (int)$coord[0], (int)$coord[1]]);
                }
            } else {
                $stmt->execute([$gameId, $playerId, (int)$ship['row'], (int)$ship['col']]);
            }
        }

        $db->prepare('UPDATE game_players SET ships_placed = TRUE WHERE game_id = ? AND player_id = ?')->execute([$gameId, $playerId]);

        $countStmt = $db->prepare('SELECT COUNT(*) as total, SUM(CASE WHEN ships_placed THEN 1 ELSE 0 END) as placed FROM game_players WHERE game_id = ?');
        $countStmt->execute([$gameId]);
        $counts = $countStmt->fetch();
        if ($counts['total'] == $counts['placed']) {
            $db->prepare("UPDATE games SET status = 'active' WHERE game_id = ?")->execute([$gameId]);
        }

        $db->commit();
        jsonResponse(['status' => 'ships placed']);
    } catch (PDOException $e) {
        $db->rollBack();
        errorResponse('Failed to place ships: ' . $e->getMessage(), 500);
    }
}

function handleTestGetBoard(int $gameId, int $playerId): void {
    $db = getDB();
    try {
        $stmt = $db->prepare('SELECT row_pos AS row, col_pos AS col, is_hit FROM ships WHERE game_id = ? AND player_id = ?');
        $stmt->execute([$gameId, $playerId]);
        $ships = $stmt->fetchAll();

        $allShips = array_map(fn($s) => ['row' => $s['row'], 'col' => $s['col']], $ships);
        $hits = array_values(array_map(
            fn($s) => ['row' => $s['row'], 'col' => $s['col']],
            array_filter($ships, fn($s) => $s['is_hit'])
        ));

        jsonResponse(['ships' => $allShips, 'hits' => $hits]);
    } catch (PDOException $e) {
        errorResponse('Failed to get board', 500);
    }
}

function handleTestSetTurn(int $gameId): void {
    $db = getDB();
    $body = json_decode(file_get_contents('php://input'), true);
    $playerId = (int)($body['playerId'] ?? $body['player_id'] ?? 0);
    if (!$playerId) errorResponse('playerId is required', 400);

    try {
        $stmt = $db->prepare('SELECT turn_order FROM game_players WHERE game_id = ? AND player_id = ?');
        $stmt->execute([$gameId, $playerId]);
        $player = $stmt->fetch();
        if (!$player) errorResponse('Player not in this game', 404);

        $db->prepare('UPDATE games SET current_turn_index = ? WHERE game_id = ?')->execute([$player['turn_order'], $gameId]);
        jsonResponse(['status' => 'turn set']);
    } catch (PDOException $e) {
        errorResponse('Failed to set turn', 500);
    }
}