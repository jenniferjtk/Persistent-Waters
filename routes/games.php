<?php
// routes/games.php
// All game lifecycle endpoints

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';

function handleCreateGame(): void {
    $db = getDB();
    $body = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    if (empty($body['creator_id'])) {
        errorResponse('creator_id is required', 400);
    }
    if (empty($body['grid_size'])) {
        errorResponse('grid_size is required', 400);
    }
    if (empty($body['max_players'])) {
        errorResponse('max_players is required', 400);
    }

    $creatorId = (int)$body['creator_id'];
    $gridSize = (int)$body['grid_size'];
    $maxPlayers = (int)$body['max_players'];

    // Validate ranges
    if ($gridSize < 5 || $gridSize > 15) {
        errorResponse('grid_size must be between 5 and 15', 400);
    }
    if ($maxPlayers < 1) {
        errorResponse('max_players must be at least 1', 400);
    }

    try {
        // Verify creator exists
        $stmt = $db->prepare('SELECT player_id FROM players WHERE player_id = ?');
        $stmt->execute([$creatorId]);
        if (!$stmt->fetch()) {
            errorResponse('Creator player not found', 404);
        }

        $db->beginTransaction();

        // Create the game
        $stmt = $db->prepare('
            INSERT INTO games (creator_id, grid_size, max_players, status, current_turn_index)
            VALUES (?, ?, ?, \'waiting\', 0)
            RETURNING game_id
        ');
        $stmt->execute([$creatorId, $gridSize, $maxPlayers]);
        $game = $stmt->fetch();
        $gameId = $game['game_id'];

        // Auto-add creator to game_players with turn_order 0
        $stmt = $db->prepare('
            INSERT INTO game_players (game_id, player_id, turn_order)
            VALUES (?, ?, 0)
        ');
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

    if (empty($body['player_id'])) {
        errorResponse('player_id is required', 400);
    }

    $playerId = (int)$body['player_id'];

    try {
        // Check game exists
        $stmt = $db->prepare('SELECT * FROM games WHERE game_id = ?');
        $stmt->execute([$gameId]);
        $game = $stmt->fetch();

        if (!$game) {
            errorResponse('Game not found', 404);
        }
        if ($game['status'] !== 'waiting') {
            errorResponse('Game is not in waiting status', 400);
        }

        // Check player exists
        $stmt = $db->prepare('SELECT player_id FROM players WHERE player_id = ?');
        $stmt->execute([$playerId]);
        if (!$stmt->fetch()) {
            errorResponse('Player not found', 404);
        }

        // Check if already in game - reject duplicate join
        $stmt = $db->prepare('
            SELECT player_id FROM game_players 
            WHERE game_id = ? AND player_id = ?
        ');
        $stmt->execute([$gameId, $playerId]);
        if ($stmt->fetch()) {
            errorResponse('Player already in this game', 400);
        }

        // Check if game is full
        $stmt = $db->prepare('
            SELECT COUNT(*) as count FROM game_players WHERE game_id = ?
        ');
        $stmt->execute([$gameId]);
        $count = $stmt->fetch()['count'];

        if ($count >= $game['max_players']) {
            errorResponse('Game is full', 400);
        }

        // Add player with next turn_order
        $stmt = $db->prepare('
            INSERT INTO game_players (game_id, player_id, turn_order)
            VALUES (?, ?, ?)
        ');
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

        if (!$game) {
            errorResponse('Game not found', 404);
        }

        // Count active (non-eliminated) players
        $stmt = $db->prepare('
            SELECT COUNT(*) as count FROM game_players 
            WHERE game_id = ? AND is_eliminated = FALSE
        ');
        $stmt->execute([$gameId]);
        $activePlayers = $stmt->fetch()['count'];

        jsonResponse([
            'game_id' => $game['game_id'],
            'grid_size' => $game['grid_size'],
            'status' => $game['status'],
            'current_turn_index' => $game['current_turn_index'],
            'active_players' => (int)$activePlayers
        ]);

    } catch (PDOException $e) {
        errorResponse('Failed to get game', 500);
    }
}

function handlePlaceShips(int $gameId): void {
    $db = getDB();
    $body = json_decode(file_get_contents('php://input'), true);

    if (empty($body['player_id'])) {
        errorResponse('player_id is required', 400);
    }
    if (empty($body['ships']) || !is_array($body['ships'])) {
        errorResponse('ships array is required', 400);
    }
    if (count($body['ships']) !== 3) {
        errorResponse('Exactly 3 ships required', 400);
    }

    $playerId = (int)$body['player_id'];
    $ships = $body['ships'];

    try {
        // Check game exists and is waiting
        $stmt = $db->prepare('SELECT * FROM games WHERE game_id = ?');
        $stmt->execute([$gameId]);
        $game = $stmt->fetch();

        if (!$game) errorResponse('Game not found', 404);
        if ($game['status'] === 'finished') errorResponse('Game already finished', 400);

        // Check player is in game
        $stmt = $db->prepare('
            SELECT * FROM game_players WHERE game_id = ? AND player_id = ?
        ');
        $stmt->execute([$gameId, $playerId]);
        $gp = $stmt->fetch();

        if (!$gp) errorResponse('Player not in this game', 403);
        if ($gp['ships_placed']) errorResponse('Ships already placed', 400);

        // Validate coordinates and no overlap
        $coords = [];
        foreach ($ships as $ship) {
            if (!isset($ship['row'], $ship['col'])) {
                errorResponse('Each ship needs row and col', 400);
            }
            $row = (int)$ship['row'];
            $col = (int)$ship['col'];

            if ($row < 0 || $row >= $game['grid_size'] || 
                $col < 0 || $col >= $game['grid_size']) {
                errorResponse('Ship coordinates out of bounds', 400);
            }

            $key = "$row,$col";
            if (in_array($key, $coords)) {
                errorResponse('Duplicate ship coordinates', 400);
            }
            $coords[] = $key;
        }

        $db->beginTransaction();

        // Insert ships
        $stmt = $db->prepare('
            INSERT INTO ships (game_id, player_id, row_pos, col_pos)
            VALUES (?, ?, ?, ?)
        ');
        foreach ($ships as $ship) {
            $stmt->execute([$gameId, $playerId, (int)$ship['row'], (int)$ship['col']]);
        }

        // Mark ships as placed
        $stmt = $db->prepare('
            UPDATE game_players SET ships_placed = TRUE 
            WHERE game_id = ? AND player_id = ?
        ');
        $stmt->execute([$gameId, $playerId]);

        // Check if all players have placed - if so set game to active
        $stmt = $db->prepare('
            SELECT COUNT(*) as total,
            SUM(CASE WHEN ships_placed THEN 1 ELSE 0 END) as placed
            FROM game_players WHERE game_id = ?
        ');
        $stmt->execute([$gameId]);
        $counts = $stmt->fetch();

        if ($counts['total'] == $counts['placed']) {
            $stmt = $db->prepare("UPDATE games SET status = 'active' WHERE game_id = ?");
            $stmt->execute([$gameId]);
        }

        $db->commit();

        jsonResponse(['status' => 'placed'], 200);

    } catch (PDOException $e) {
        $db->rollBack();
        errorResponse('Failed to place ships', 500);
    }
}

function handleFire(int $gameId): void {
    $db = getDB();
    $body = json_decode(file_get_contents('php://input'), true);

    if (empty($body['player_id'])) errorResponse('player_id is required', 400);
    if (!isset($body['row'])) errorResponse('row is required', 400);
    if (!isset($body['col'])) errorResponse('col is required', 400);

    $playerId = (int)$body['player_id'];
    $row = (int)$body['row'];
    $col = (int)$body['col'];

    try {
        // Get game
        $stmt = $db->prepare('SELECT * FROM games WHERE game_id = ?');
        $stmt->execute([$gameId]);
        $game = $stmt->fetch();

        if (!$game) errorResponse('Game not found', 404);
        if ($game['status'] !== 'active') errorResponse('Game is not active', 403);

        // Validate bounds
        if ($row < 0 || $row >= $game['grid_size'] || 
            $col < 0 || $col >= $game['grid_size']) {
            errorResponse('Coordinates out of bounds', 400);
        }

        // Check player is in game and not eliminated
        $stmt = $db->prepare('
            SELECT * FROM game_players 
            WHERE game_id = ? AND player_id = ? AND is_eliminated = FALSE
        ');
        $stmt->execute([$gameId, $playerId]);
        $gp = $stmt->fetch();

        if (!$gp) errorResponse('Player not in game or eliminated', 403);

        // Check it is this player's turn
        if ($game['current_turn_index'] !== (int)$gp['turn_order']) {
            errorResponse('Not your turn', 403);
        }

        // Check for duplicate move
        $stmt = $db->prepare('
            SELECT move_id FROM moves 
            WHERE game_id = ? AND player_id = ? AND row_pos = ? AND col_pos = ?
        ');
        $stmt->execute([$gameId, $playerId, $row, $col]);
        if ($stmt->fetch()) errorResponse('Duplicate move', 400);

        // Check if it hits any ship (belonging to any OTHER player)
        $stmt = $db->prepare('
            SELECT * FROM ships 
            WHERE game_id = ? AND row_pos = ? AND col_pos = ? 
            AND player_id != ? AND is_hit = FALSE
        ');
        $stmt->execute([$gameId, $row, $col, $playerId]);
        $hitShip = $stmt->fetch();

        $result = $hitShip ? 'hit' : 'miss';

        $db->beginTransaction();

        // Log the move
        $stmt = $db->prepare('
            INSERT INTO moves (game_id, player_id, row_pos, col_pos, result)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([$gameId, $playerId, $row, $col, $result]);

        // Update total_moves for player
        $stmt = $db->prepare('
            UPDATE players SET total_moves = total_moves + 1 WHERE player_id = ?
        ');
        $stmt->execute([$playerId]);

        if ($hitShip) {
            // Mark ship as hit
            $stmt = $db->prepare('
                UPDATE ships SET is_hit = TRUE 
                WHERE ship_id = ?
            ');
            $stmt->execute([$hitShip['ship_id']]);

            // Check if that player is eliminated (all ships hit)
            $stmt = $db->prepare('
                SELECT COUNT(*) as remaining FROM ships 
                WHERE game_id = ? AND player_id = ? AND is_hit = FALSE
            ');
            $stmt->execute([$gameId, $hitShip['player_id']]);
            $remaining = $stmt->fetch()['remaining'];

            if ($remaining == 0) {
                // Eliminate that player
                $stmt = $db->prepare('
                    UPDATE game_players SET is_eliminated = TRUE 
                    WHERE game_id = ? AND player_id = ?
                ');
                $stmt->execute([$gameId, $hitShip['player_id']]);

                // Update loss stat for eliminated player
                $stmt = $db->prepare('
                    UPDATE players SET total_losses = total_losses + 1,
                    total_games = total_games + 1
                    WHERE player_id = ?
                ');
                $stmt->execute([$hitShip['player_id']]);
            }
        }

        // Check if game is over (only 1 active player left)
        $stmt = $db->prepare('
            SELECT player_id FROM game_players 
            WHERE game_id = ? AND is_eliminated = FALSE
        ');
        $stmt->execute([$gameId]);
        $activePlayers = $stmt->fetchAll();

        if (count($activePlayers) === 1) {
            $winnerId = $activePlayers[0]['player_id'];

            // Update game status
            $stmt = $db->prepare("
                UPDATE games SET status = 'finished' WHERE game_id = ?
            ");
            $stmt->execute([$gameId]);

            // Update winner stats
            $stmt = $db->prepare('
                UPDATE players SET total_wins = total_wins + 1,
                total_games = total_games + 1
                WHERE player_id = ?
            ');
            $stmt->execute([$winnerId]);

            $db->commit();

            jsonResponse([
                'result' => $result,
                'next_player_id' => null,
                'game_status' => 'finished',
                'winner_id' => $winnerId
            ]);
            return;
        }

        // Advance turn to next active player
        $stmt = $db->prepare('
            SELECT turn_order FROM game_players 
            WHERE game_id = ? AND is_eliminated = FALSE 
            ORDER BY turn_order ASC
        ');
        $stmt->execute([$gameId]);
        $activeTurnOrders = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $currentIndex = array_search($game['current_turn_index'], $activeTurnOrders);
        $nextIndex = $activeTurnOrders[($currentIndex + 1) % count($activeTurnOrders)];

        $stmt = $db->prepare('
            UPDATE games SET current_turn_index = ? WHERE game_id = ?
        ');
        $stmt->execute([$nextIndex, $gameId]);

        // Get next player id
        $stmt = $db->prepare('
            SELECT player_id FROM game_players 
            WHERE game_id = ? AND turn_order = ?
        ');
        $stmt->execute([$gameId, $nextIndex]);
        $nextPlayer = $stmt->fetch();

        $db->commit();

        jsonResponse([
            'result' => $result,
            'next_player_id' => $nextPlayer['player_id'],
            'game_status' => 'active'
        ]);

    } catch (PDOException $e) {
        $db->rollBack();
        errorResponse('Failed to fire', 500);
    }
}

function handleGetMoves(int $gameId): void {
    $db = getDB();

    try {
        $stmt = $db->prepare('SELECT * FROM games WHERE game_id = ?');
        $stmt->execute([$gameId]);
        if (!$stmt->fetch()) errorResponse('Game not found', 404);

        $stmt = $db->prepare('
            SELECT player_id, row_pos AS row, col_pos AS col, result, created_at
            FROM moves 
            WHERE game_id = ? 
            ORDER BY created_at ASC
        ');
        $stmt->execute([$gameId]);
        $moves = $stmt->fetchAll();

        jsonResponse($moves);

    } catch (PDOException $e) {
        errorResponse('Failed to get moves', 500);
    }
}