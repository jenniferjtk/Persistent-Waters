<?php
// routes/games.php
// All game lifecycle endpoints

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';

function handleCreateGame(): void {
    $db = getDB();
    $body = json_decode(file_get_contents('php://input'), true);

    if (empty($body['creator_id']))  errorResponse('creator_id is required', 400);
    if (empty($body['grid_size']))   errorResponse('grid_size is required', 400);
    if (empty($body['max_players'])) errorResponse('max_players is required', 400);

    $creatorId  = (int)$body['creator_id'];
    $gridSize   = (int)$body['grid_size'];
    $maxPlayers = (int)$body['max_players'];

    if ($gridSize < 5 || $gridSize > 15) errorResponse('grid_size must be between 5 and 15', 400);
    if ($maxPlayers < 1)                 errorResponse('max_players must be at least 1', 400);

    try {
        $stmt = $db->prepare('SELECT player_id FROM players WHERE player_id = ?');
        $stmt->execute([$creatorId]);
        if (!$stmt->fetch()) errorResponse('Creator player not found', 404);

        $db->beginTransaction();

        $stmt = $db->prepare("
            INSERT INTO games (creator_id, grid_size, max_players, status, current_turn_index)
            VALUES (?, ?, ?, 'waiting', 0)
            RETURNING game_id
        ");
        $stmt->execute([$creatorId, $gridSize, $maxPlayers]);
        $gameId = $stmt->fetch()['game_id'];

        $stmt = $db->prepare('INSERT INTO game_players (game_id, player_id, turn_order) VALUES (?, ?, 0)');
        $stmt->execute([$gameId, $creatorId]);

        $db->commit();
        jsonResponse(['game_id' => $gameId], 201);

    } catch (PDOException $e) {
        $db->rollBack();
        errorResponse('Failed to create game', 500);
    }
}

function handleJoinGame(int $gameId): void {
    $db = getDB();
    $body = json_decode(file_get_contents('php://input'), true);

    if (empty($body['player_id'])) errorResponse('player_id is required', 400);
    $playerId = (int)$body['player_id'];

    try {
        $stmt = $db->prepare('SELECT * FROM games WHERE game_id = ?');
        $stmt->execute([$gameId]);
        $game = $stmt->fetch();

        if (!$game)                          errorResponse('Game not found', 404);
        if ($game['status'] !== 'waiting')   errorResponse('Game is not in waiting status', 400);

        $stmt = $db->prepare('SELECT player_id FROM players WHERE player_id = ?');
        $stmt->execute([$playerId]);
        if (!$stmt->fetch()) errorResponse('Player not found', 404);

        $stmt = $db->prepare('SELECT player_id FROM game_players WHERE game_id = ? AND player_id = ?');
        $stmt->execute([$gameId, $playerId]);
        if ($stmt->fetch()) errorResponse('Player already in this game', 400);

        $stmt = $db->prepare('SELECT COUNT(*) as count FROM game_players WHERE game_id = ?');
        $stmt->execute([$gameId]);
        $count = (int)$stmt->fetch()['count'];

        if ($count >= $game['max_players']) errorResponse('Game is full', 400);

        $stmt = $db->prepare('INSERT INTO game_players (game_id, player_id, turn_order) VALUES (?, ?, ?)');
        $stmt->execute([$gameId, $playerId, $count]);

        jsonResponse(['status' => 'joined', 'game_id' => $gameId], 200);

    } catch (PDOException $e) {
        errorResponse('Failed to join game', 500);
    }
}

function handleGetGame(int $gameId): void {
    $db = getDB();

    try {
        $stmt = $db->prepare('SELECT * FROM games WHERE game_id = ?');
        $stmt->execute([$gameId]);
        $game = $stmt->fetch();

        if (!$game) errorResponse('Game not found', 404);

        $stmt = $db->prepare('
            SELECT COUNT(*) as count FROM game_players
            WHERE game_id = ? AND is_eliminated = FALSE
        ');
        $stmt->execute([$gameId]);
        $activePlayers = (int)$stmt->fetch()['count'];

        jsonResponse([
            'game_id'            => (int)$game['game_id'],
            'grid_size'          => (int)$game['grid_size'],
            'status'             => $game['status'],
            'current_turn_index' => (int)$game['current_turn_index'],
            'active_players'     => $activePlayers
        ]);

    } catch (PDOException $e) {
        errorResponse('Failed to get game', 500);
    }
}

function handlePlaceShips(int $gameId): void {
    $db = getDB();
    $body = json_decode(file_get_contents('php://input'), true);

    if (empty($body['player_id']))                          errorResponse('player_id is required', 400);
    if (!isset($body['ships']) || !is_array($body['ships'])) errorResponse('ships array is required', 400);
    if (count($body['ships']) !== 3)                        errorResponse('Exactly 3 ships required', 400);

    $playerId = (int)$body['player_id'];
    $ships    = $body['ships'];

    try {
        $stmt = $db->prepare('SELECT * FROM games WHERE game_id = ?');
        $stmt->execute([$gameId]);
        $game = $stmt->fetch();

        if (!$game)                         errorResponse('Game not found', 404);
        if ($game['status'] === 'finished') errorResponse('Game already finished', 400);

        // FIX: check player exists in players table first (fake player_id → 403)
        $stmt = $db->prepare('SELECT player_id FROM players WHERE player_id = ?');
        $stmt->execute([$playerId]);
        if (!$stmt->fetch()) errorResponse('Invalid player_id', 403);

        $stmt = $db->prepare('SELECT * FROM game_players WHERE game_id = ? AND player_id = ?');
        $stmt->execute([$gameId, $playerId]);
        $gp = $stmt->fetch();

        if (!$gp)                errorResponse('Player not in this game', 403);
        if ($gp['ships_placed']) errorResponse('Ships already placed', 400);

        $coords = [];
        foreach ($ships as $ship) {
            if (!isset($ship['row'], $ship['col'])) errorResponse('Each ship needs row and col', 400);
            $row = (int)$ship['row'];
            $col = (int)$ship['col'];
            if ($row < 0 || $row >= $game['grid_size'] || $col < 0 || $col >= $game['grid_size']) {
                errorResponse('Ship coordinates out of bounds', 400);
            }
            $key = "$row,$col";
            if (in_array($key, $coords)) errorResponse('Duplicate ship coordinates', 400);
            $coords[] = $key;
        }

        $db->beginTransaction();

        $stmt = $db->prepare('INSERT INTO ships (game_id, player_id, row_pos, col_pos) VALUES (?, ?, ?, ?)');
        foreach ($ships as $ship) {
            $stmt->execute([$gameId, $playerId, (int)$ship['row'], (int)$ship['col']]);
        }

        $db->prepare('UPDATE game_players SET ships_placed = TRUE WHERE game_id = ? AND player_id = ?')
           ->execute([$gameId, $playerId]);

        $stmt = $db->prepare('
            SELECT COUNT(*) as total,
                   SUM(CASE WHEN ships_placed THEN 1 ELSE 0 END) as placed
            FROM game_players WHERE game_id = ?
        ');
        $stmt->execute([$gameId]);
        $counts = $stmt->fetch();

        if ((int)$counts['total'] === (int)$counts['placed']) {
            $db->prepare("UPDATE games SET status = 'active' WHERE game_id = ?")->execute([$gameId]);
        }

        $db->commit();
        jsonResponse(['status' => 'placed'], 200);

    } catch (PDOException $e) {
        $db->rollBack();
        errorResponse('Failed to place ships: ' . $e->getMessage(), 500);
    }
}

function handleFire(int $gameId): void {
    $db = getDB();
    $body = json_decode(file_get_contents('php://input'), true);

    if (empty($body['player_id'])) errorResponse('player_id is required', 400);
    if (!isset($body['row']))      errorResponse('row is required', 400);
    if (!isset($body['col']))      errorResponse('col is required', 400);

    $playerId = (int)$body['player_id'];
    $row      = (int)$body['row'];
    $col      = (int)$body['col'];

    try {
        $stmt = $db->prepare('SELECT * FROM games WHERE game_id = ?');
        $stmt->execute([$gameId]);
        $game = $stmt->fetch();

        if (!$game)                       errorResponse('Game not found', 404);
        if ($game['status'] !== 'active') errorResponse('Game is not active', 403);

        // Bounds check first
        if ($row < 0 || $row >= $game['grid_size'] || $col < 0 || $col >= $game['grid_size']) {
            errorResponse('Coordinates out of bounds', 400);
        }

        // FIX: Check if player_id exists at all (fake player_id → 403, not 404)
        $stmt = $db->prepare('SELECT player_id FROM players WHERE player_id = ?');
        $stmt->execute([$playerId]);
        if (!$stmt->fetch()) errorResponse('Invalid player_id', 403);

        // Check player is in THIS game and not eliminated
        $stmt = $db->prepare('
            SELECT * FROM game_players
            WHERE game_id = ? AND player_id = ? AND is_eliminated = FALSE
        ');
        $stmt->execute([$gameId, $playerId]);
        $gp = $stmt->fetch();

        // FIX: Player exists but not in this game → 403 (not 404)
        if (!$gp) errorResponse('Player not in this game or already eliminated', 403);

        // Turn enforcement
        if ((int)$game['current_turn_index'] !== (int)$gp['turn_order']) {
            errorResponse('Not your turn', 403);
        }

        // Duplicate move check (same player, same cell)
        $stmt = $db->prepare('
            SELECT move_id FROM moves
            WHERE game_id = ? AND player_id = ? AND row_pos = ? AND col_pos = ?
        ');
        $stmt->execute([$gameId, $playerId, $row, $col]);
        if ($stmt->fetch()) errorResponse('Duplicate move: already fired at this coordinate', 400);

        // Hit detection — any unhit ship belonging to another player
        $stmt = $db->prepare('
            SELECT * FROM ships
            WHERE game_id = ? AND row_pos = ? AND col_pos = ?
            AND player_id != ? AND is_hit = FALSE
        ');
        $stmt->execute([$gameId, $row, $col, $playerId]);
        $hitShip = $stmt->fetch();

        $result = $hitShip ? 'hit' : 'miss';

        $db->beginTransaction();

        // Log the move with timestamp (created_at auto-set by DB default)
        $db->prepare('INSERT INTO moves (game_id, player_id, row_pos, col_pos, result) VALUES (?, ?, ?, ?, ?)')
           ->execute([$gameId, $playerId, $row, $col, $result]);

        // FIX: Increment total_moves AND total_hits correctly
        if ($result === 'hit') {
            $db->prepare('UPDATE players SET total_moves = total_moves + 1, total_hits = total_hits + 1 WHERE player_id = ?')
               ->execute([$playerId]);
        } else {
            $db->prepare('UPDATE players SET total_moves = total_moves + 1 WHERE player_id = ?')
               ->execute([$playerId]);
        }

        if ($hitShip) {
            $db->prepare('UPDATE ships SET is_hit = TRUE WHERE ship_id = ?')
               ->execute([$hitShip['ship_id']]);

            // Check if the hit player is now eliminated (all their ships hit)
            $stmt = $db->prepare('
                SELECT COUNT(*) as remaining FROM ships
                WHERE game_id = ? AND player_id = ? AND is_hit = FALSE
            ');
            $stmt->execute([$gameId, $hitShip['player_id']]);
            $remaining = (int)$stmt->fetch()['remaining'];

            if ($remaining === 0) {
                $db->prepare('UPDATE game_players SET is_eliminated = TRUE WHERE game_id = ? AND player_id = ?')
                   ->execute([$gameId, $hitShip['player_id']]);

                // FIX: eliminated player gets total_losses + total_games
                $db->prepare('UPDATE players SET total_losses = total_losses + 1, total_games = total_games + 1 WHERE player_id = ?')
                   ->execute([$hitShip['player_id']]);
            }
        }

        // Check if game is over (1 active player left)
        $stmt = $db->prepare('SELECT player_id FROM game_players WHERE game_id = ? AND is_eliminated = FALSE');
        $stmt->execute([$gameId]);
        $activePlayers = $stmt->fetchAll();

        if (count($activePlayers) === 1) {
            $winnerId = (int)$activePlayers[0]['player_id'];

            $db->prepare("UPDATE games SET status = 'finished' WHERE game_id = ?")->execute([$gameId]);

            // FIX: winner gets total_wins + total_games (they never got eliminated so total_games not incremented yet)
            $db->prepare('UPDATE players SET total_wins = total_wins + 1, total_games = total_games + 1 WHERE player_id = ?')
               ->execute([$winnerId]);

            $db->commit();

            jsonResponse([
                'result'         => $result,
                'next_player_id' => null,
                'game_status'    => 'finished',
                'winner_id'      => $winnerId
            ]);
            return;
        }

        // FIX: Turn advancement — build sorted list of active turn_orders
        // then find the next one after current, wrapping around
        $stmt = $db->prepare('
            SELECT turn_order FROM game_players
            WHERE game_id = ? AND is_eliminated = FALSE
            ORDER BY turn_order ASC
        ');
        $stmt->execute([$gameId]);
        $activeTurnOrders = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $currentTurn = (int)$game['current_turn_index'];
        $nextTurn    = null;

        // Find the next turn_order after current in the active list
        foreach ($activeTurnOrders as $turnOrder) {
            if ((int)$turnOrder > $currentTurn) {
                $nextTurn = (int)$turnOrder;
                break;
            }
        }
        // If no higher turn_order exists, wrap to first
        if ($nextTurn === null) {
            $nextTurn = (int)$activeTurnOrders[0];
        }

        $db->prepare('UPDATE games SET current_turn_index = ? WHERE game_id = ?')
           ->execute([$nextTurn, $gameId]);

        $stmt = $db->prepare('SELECT player_id FROM game_players WHERE game_id = ? AND turn_order = ?');
        $stmt->execute([$gameId, $nextTurn]);
        $nextPlayer = $stmt->fetch();

        $db->commit();

        jsonResponse([
            'result'         => $result,
            'next_player_id' => (int)$nextPlayer['player_id'],
            'game_status'    => 'active'
        ]);

    } catch (PDOException $e) {
        $db->rollBack();
        errorResponse('Failed to fire: ' . $e->getMessage(), 500);
    }
}