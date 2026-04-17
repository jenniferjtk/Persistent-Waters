<?php
// routes/moves.php
// GET /api/games/{id}/moves

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';

function handleGetMoves(int $gameId): void {
    $db = getDB();

    try {
        if ($gameId <= 0) {
            errorResponse('Game not found', 404);
        }

        $stmt = $db->prepare('SELECT game_id FROM games WHERE game_id = ?');
        $stmt->execute([$gameId]);

        if (!$stmt->fetch()) {
            errorResponse('Game not found', 404);
        }

        $stmt = $db->prepare('
            SELECT
                game_id,
                player_id,
                row_pos AS row,
                col_pos AS col,
                result,
                created_at
            FROM moves
            WHERE game_id = ?
            ORDER BY created_at ASC, move_id ASC
        ');
        $stmt->execute([$gameId]);

        $moves = array_map(fn($m) => [
            'game_id'    => (int)$m['game_id'],
            'player_id'  => (int)$m['player_id'],
            'row'        => (int)$m['row'],
            'col'        => (int)$m['col'],
            'result'     => $m['result'],
            'created_at' => $m['created_at'],
        ], $stmt->fetchAll());

        jsonResponse($moves, 200);

    } catch (PDOException $e) {
        errorResponse('Failed to get moves', 500);
    }
}
