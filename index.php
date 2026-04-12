<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Test-Password');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
require_once 'helpers/response.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = trim($path, '/');
$parts = explode('/', $path);

// Allow /test/ routes without /api/ prefix
if (isset($parts[0]) && $parts[0] === 'test') {
    require_once 'routes/test.php';
    handleTestRoute($method, $parts);
    exit;
}

if (!isset($parts[0]) || $parts[0] !== 'api') {
    errorResponse('Not found', 404);
}

switch (true) {

    case $method === 'POST' && $path === 'api/reset':
        require_once 'routes/reset.php';
        handleReset();
        break;

    case $method === 'POST' && $path === 'api/setup':
        require_once 'routes/setup.php';
        handleSetup();
        break;

    case $method === 'POST' && $path === 'api/players':
        require_once 'routes/players.php';
        handleCreatePlayer();
        break;

    case $method === 'GET'
        && isset($parts[1], $parts[2])
        && $parts[1] === 'players'
        && !isset($parts[3]):
        require_once 'routes/players.php';
        handleGetPlayer((int)$parts[2]);
        break;

    case $method === 'GET'
        && isset($parts[1], $parts[2], $parts[3])
        && $parts[1] === 'players'
        && $parts[3] === 'stats':
        require_once 'routes/players.php';
        handleGetStats((int)$parts[2]);
        break;

    case $method === 'GET' && $path === 'api/games':
        require_once 'routes/games.php';
        handleGetGames();
        break;

    case $method === 'POST' && $path === 'api/games':
        require_once 'routes/games.php';
        handleCreateGame();
        break;

    case $method === 'POST'
        && isset($parts[1], $parts[2], $parts[3])
        && $parts[1] === 'games'
        && $parts[3] === 'join':
        require_once 'routes/games.php';
        handleJoinGame((int)$parts[2]);
        break;

    case $method === 'POST'
        && isset($parts[1], $parts[2], $parts[3])
        && $parts[1] === 'games'
        && $parts[3] === 'place':
        require_once 'routes/games.php';
        handlePlaceShips((int)$parts[2]);
        break;

    case $method === 'POST'
        && isset($parts[1], $parts[2], $parts[3])
        && $parts[1] === 'games'
        && $parts[3] === 'ships':
        require_once 'routes/games.php';
        handlePlaceShips((int)$parts[2]);
        break;

    case $method === 'POST'
        && isset($parts[1], $parts[2], $parts[3])
        && $parts[1] === 'games'
        && $parts[3] === 'fire':
        require_once 'routes/games.php';
        handleFire((int)$parts[2]);
        break;

    case $method === 'POST'
        && isset($parts[1], $parts[2], $parts[3])
        && $parts[1] === 'games'
        && $parts[3] === 'moves':
        require_once 'routes/games.php';
        handleFire((int)$parts[2]);
        break;

    case $method === 'GET'
        && isset($parts[1], $parts[2], $parts[3])
        && $parts[1] === 'games'
        && $parts[3] === 'moves':
        require_once 'routes/moves.php';
        handleGetMoves((int)$parts[2]);
        break;

    case $method === 'GET'
        && isset($parts[1], $parts[2], $parts[3])
        && $parts[1] === 'games'
        && $parts[3] === 'ships':
        require_once 'routes/games.php';
        handleGetShips((int)$parts[2]);
        break;

    case $method === 'GET'
        && isset($parts[1], $parts[2], $parts[3])
        && $parts[1] === 'games'
        && $parts[3] === 'players':
        require_once 'routes/games.php';
        handleGetGamePlayers((int)$parts[2]);
        break;

    case $method === 'GET'
        && isset($parts[1], $parts[2], $parts[3])
        && $parts[1] === 'games'
        && $parts[3] === 'boards':
        require_once 'routes/games.php';
        handleGetGameBoards((int)$parts[2]);
        break;

    case $method === 'GET'
        && isset($parts[1], $parts[2])
        && $parts[1] === 'games'
        && !isset($parts[3]):
        require_once 'routes/games.php';
        handleGetGame((int)$parts[2]);
        break;

    case isset($parts[1]) && $parts[1] === 'test':
        require_once 'routes/test.php';
        handleTestRoute($method, $parts);
        break;

    default:
        errorResponse('Not found', 404);
}
function handleGetGamePlayers(int $gameId): void {
    $db = getDB();
    try {
        $stmt = $db->prepare('SELECT game_id FROM games WHERE game_id = ?');
        $stmt->execute([$gameId]);
        if (!$stmt->fetch()) errorResponse('Game not found', 404);

        $stmt = $db->prepare('
            SELECT gp.player_id, gp.turn_order, gp.is_eliminated, gp.ships_placed,
                   p.username
            FROM game_players gp
            JOIN players p ON p.player_id = gp.player_id
            WHERE gp.game_id = ?
            ORDER BY gp.turn_order ASC
        ');
        $stmt->execute([$gameId]);
        $players = $stmt->fetchAll();

        jsonResponse(array_map(fn($p) => [
            'player_id'    => (int)$p['player_id'],
            'username'     => $p['username'],
            'turn_order'   => (int)$p['turn_order'],
            'is_eliminated'=> (bool)$p['is_eliminated'],
            'ships_placed' => (bool)$p['ships_placed'],
        ], $players));

    } catch (PDOException $e) {
        errorResponse('Failed to get players', 500);
    }
}

function handleGetGameBoards(int $gameId): void {
    $db = getDB();
    try {
        $stmt = $db->prepare('SELECT game_id FROM games WHERE game_id = ?');
        $stmt->execute([$gameId]);
        if (!$stmt->fetch()) errorResponse('Game not found', 404);

        $stmt = $db->prepare('
            SELECT gp.player_id, gp.turn_order, gp.is_eliminated,
                   p.username
            FROM game_players gp
            JOIN players p ON p.player_id = gp.player_id
            WHERE gp.game_id = ?
            ORDER BY gp.turn_order ASC
        ');
        $stmt->execute([$gameId]);
        $players = $stmt->fetchAll();

        $boards = [];
        foreach ($players as $player) {
            $pid = (int)$player['player_id'];

            $shipStmt = $db->prepare('
                SELECT row_pos AS row, col_pos AS col, is_hit
                FROM ships WHERE game_id = ? AND player_id = ?
            ');
            $shipStmt->execute([$gameId, $pid]);
            $ships = $shipStmt->fetchAll();

            $allShips = array_map(fn($s) => ['row' => (int)$s['row'], 'col' => (int)$s['col']], $ships);
            $hits = array_values(array_map(
                fn($s) => ['row' => (int)$s['row'], 'col' => (int)$s['col']],
                array_filter($ships, fn($s) => $s['is_hit'])
            ));

            $boards[] = [
                'player_id'    => $pid,
                'username'     => $player['username'],
                'turn_order'   => (int)$player['turn_order'],
                'is_eliminated'=> (bool)$player['is_eliminated'],
                'ships'        => $allShips,
                'hits'         => $hits,
            ];
        }

        jsonResponse($boards);

    } catch (PDOException $e) {
        errorResponse('Failed to get boards', 500);
    }
}