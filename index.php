<?php
// index.php
require_once 'helpers/response.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = trim($path, '/');
$parts = explode('/', $path);

if (!isset($parts[0]) || $parts[0] !== 'api') {
    errorResponse('Not found', 404);
}

switch (true) {

    // POST /api/reset
    case $method === 'POST' && $path === 'api/reset':
        require_once 'routes/reset.php';
        handleReset();
        break;

    // POST /api/setup
    case $method === 'POST' && $path === 'api/setup':
        require_once 'routes/setup.php';
        handleSetup();
        break;

    // POST /api/players
    case $method === 'POST' && $path === 'api/players':
        require_once 'routes/players.php';
        handleCreatePlayer();
        break;

    // GET /api/players/{id}/stats
    case $method === 'GET'
        && isset($parts[1], $parts[2], $parts[3])
        && $parts[1] === 'players'
        && $parts[3] === 'stats':
        require_once 'routes/players.php';
        handleGetStats((int)$parts[2]);
        break;

    // POST /api/games
    case $method === 'POST' && $path === 'api/games':
        require_once 'routes/games.php';
        handleCreateGame();
        break;

    // POST /api/games/{id}/join
    case $method === 'POST'
        && isset($parts[1], $parts[2], $parts[3])
        && $parts[1] === 'games'
        && $parts[3] === 'join':
        require_once 'routes/games.php';
        handleJoinGame((int)$parts[2]);
        break;

    // POST /api/games/{id}/place
    case $method === 'POST'
        && isset($parts[1], $parts[2], $parts[3])
        && $parts[1] === 'games'
        && $parts[3] === 'place':
        require_once 'routes/games.php';
        handlePlaceShips((int)$parts[2]);
        break;

    // POST /api/games/{id}/fire
    case $method === 'POST'
        && isset($parts[1], $parts[2], $parts[3])
        && $parts[1] === 'games'
        && $parts[3] === 'fire':
        require_once 'routes/games.php';
        handleFire((int)$parts[2]);
        break;

    // GET /api/games/{id}/moves
    case $method === 'GET'
        && isset($parts[1], $parts[2], $parts[3])
        && $parts[1] === 'games'
        && $parts[3] === 'moves':
        require_once 'routes/moves.php';
        handleGetMoves((int)$parts[2]);
        break;

    // GET /api/games/{id}
    case $method === 'GET'
        && isset($parts[1], $parts[2])
        && $parts[1] === 'games'
        && !isset($parts[3]):
        require_once 'routes/games.php';
        handleGetGame((int)$parts[2]);
        break;

    // TEST MODE endpoints
    case isset($parts[1]) && $parts[1] === 'test':
        require_once 'routes/test.php';
        handleTestRoute($method, $parts);
        break;

    default:
        errorResponse('Not found', 404);
}