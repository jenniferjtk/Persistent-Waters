<?php
// routes/players.php
// POST /api/players - create a new player
// GET /api/players/{id}/stats - get player lifetime stats

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';

function handleCreatePlayer(): void {
    $db = getDB();
    $body = json_decode(file_get_contents('php://input'), true);

    // Must have username, must NOT have player_id
    if (isset($body['player_id'])) {
        errorResponse('player_id must not be supplied by client', 400);
    }
    if (empty($body['username'])) {
        errorResponse('username is required', 400);
    }

    $username = trim($body['username']);

    try {
        // Check if username already exists - reuse same player_id
        $stmt = $db->prepare('SELECT player_id FROM players WHERE username = ?');
        $stmt->execute([$username]);
        $existing = $stmt->fetch();

        if ($existing) {
            jsonResponse(['player_id' => $existing['player_id']], 200);
            return;
        }

        // Create new player
        $stmt = $db->prepare('
            INSERT INTO players (username) 
            VALUES (?) 
            RETURNING player_id
        ');
        $stmt->execute([$username]);
        $player = $stmt->fetch();

        jsonResponse(['player_id' => $player['player_id']], 201);

    } catch (PDOException $e) {
        errorResponse('Failed to create player', 500);
    }
}

function handleGetStats(int $playerId): void {
    $db = getDB();

    try {
        $stmt = $db->prepare('
            SELECT 
                total_games AS games_played,
                total_wins AS wins,
                total_losses AS losses,
                total_moves AS total_shots,
                total_hits
            FROM players 
            WHERE player_id = ?
        ');
        $stmt->execute([$playerId]);
        $stats = $stmt->fetch();

        if (!$stats) {
            errorResponse('Player not found', 404);
        }

        // Cast to correct types
        $stats['games_played'] = (int)$stats['games_played'];
        $stats['wins'] = (int)$stats['wins'];
        $stats['losses'] = (int)$stats['losses'];
        $stats['total_shots'] = (int)$stats['total_shots'];
        $stats['total_hits'] = (int)$stats['total_hits'];

        // Calculate accuracy
        if ($stats['total_shots'] > 0) {
            $stats['accuracy'] = (float)round($stats['total_hits'] / $stats['total_shots'], 3);
        } else {
            $stats['accuracy'] = 0.0;
        }

        jsonResponse($stats);

    } catch (PDOException $e) {
        errorResponse('Failed to get stats', 500);
    }
}