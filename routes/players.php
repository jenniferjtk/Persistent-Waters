<?php
// routes/players.php
// POST /api/players  - create or retrieve a player by username
// GET  /api/players/{id}/stats - get player lifetime stats

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';

function handleCreatePlayer(): void {
    $db = getDB();
    $body = json_decode(file_get_contents('php://input'), true);

    // Client must NOT supply player_id
    if (isset($body['player_id'])) {
        errorResponse('player_id must not be supplied by client', 400);
    }
    if (empty($body['username'])) {
        errorResponse('username is required', 400);
    }

    $username = trim($body['username']);

    try {
        // Identity reuse: same username → same player_id across games
        $stmt = $db->prepare('SELECT player_id FROM players WHERE username = ?');
        $stmt->execute([$username]);
        $existing = $stmt->fetch();

        if ($existing) {
            jsonResponse(['player_id' => (int)$existing['player_id']], 409);
            return;
        }

        $stmt = $db->prepare('INSERT INTO players (username) VALUES (?) RETURNING player_id');
        $stmt->execute([$username]);
        $player = $stmt->fetch();

        jsonResponse(['player_id' => (int)$player['player_id']], 201);

    } catch (PDOException $e) {
        errorResponse('Failed to create player', 500);
    }
}

function handleGetStats(int $playerId): void {
    $db = getDB();

    try {
        $stmt = $db->prepare('
            SELECT
                total_games  AS games_played,
                total_wins   AS wins,
                total_losses AS losses,
                total_moves  AS total_shots,
                total_hits
            FROM players
            WHERE player_id = ?
        ');
        $stmt->execute([$playerId]);
        $stats = $stmt->fetch();

        if (!$stats) {
            errorResponse('Player not found', 404);
        }

        $totalShots = (int)$stats['total_shots'];
        $totalHits  = (int)$stats['total_hits'];

        jsonResponse([
            'games_played' => (int)$stats['games_played'],
            'wins'         => (int)$stats['wins'],
            'losses'       => (int)$stats['losses'],
            'total_shots'  => $totalShots,
            'total_hits'   => $totalHits,
            'accuracy'     => $totalShots > 0
                                ? round($totalHits / $totalShots, 3)
                                : 0.0
        ]);

    } catch (PDOException $e) {
        errorResponse('Failed to get stats', 500);
    }
}