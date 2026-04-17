<?php
// routes/players.php
// POST /api/players  - create a player by username
// GET  /api/players/{id}/stats - get player lifetime stats

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';

function handleGetAllPlayers(): void {
    $db = getDB();
    try {
        $stmt = $db->query('SELECT player_id, username FROM players ORDER BY player_id ASC');
        $players = [];
        foreach ($stmt->fetchAll() as $row) {
            $players[] = [
                'player_id' => (int)$row['player_id'],
                'username'  => $row['username'],
            ];
        }
        jsonResponse(['players' => $players]);
    } catch (PDOException $e) {
        errorResponse('Failed to get players', 500);
    }
}

function handleGetPlayer(int $playerId): void {
    $db = getDB();

    try {
        if ($playerId <= 0) {
            errorResponse('Player not found', 404);
        }

        $stmt = $db->prepare('SELECT player_id, username FROM players WHERE player_id = ?');
        $stmt->execute([$playerId]);
        $player = $stmt->fetch();

        if (!$player) {
            errorResponse('Player not found', 404);
        }

        jsonResponse([
            'player_id' => (int)$player['player_id'],
            'username'  => $player['username'],
        ]);

    } catch (PDOException $e) {
        errorResponse('Failed to get player', 500);
    }
}

function handleCreatePlayer(): void {
    $db = getDB();
    $body = json_decode(file_get_contents('php://input'), true);

    // Client must NOT supply player_id
    if (isset($body['player_id'])) {
        errorResponse('player_id must not be supplied by client', 400);
    }
    if (empty($body['username']) || !is_string($body['username'])) {
        errorResponse('username is required', 400);
    }

    $username = trim($body['username']);

    // Only alphanumeric + underscore, max 30 chars
    if (!preg_match('/^[a-zA-Z0-9_]{1,30}$/', $username)) {
        errorResponse('username may only contain letters, numbers, and underscores (max 30 chars)', 400);
    }

    try {
        $stmt = $db->prepare('SELECT player_id FROM players WHERE username = ?');
        $stmt->execute([$username]);
        $existing = $stmt->fetch();

        if ($existing) {
            errorResponse('Username already taken', 409);
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
        if ($playerId <= 0) {
            errorResponse('Player not found', 404);
        }

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
                                ? (float)round($totalHits / $totalShots, 3)
                                : 0.0
        ]);

    } catch (PDOException $e) {
        errorResponse('Failed to get stats', 500);
    }
}
