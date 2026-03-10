<?php
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
        && isset($parts[1], $parts[2], $parts[3])
        && $parts[1] === 'players'
        && $parts[3] === 'stats':
        require_once 'routes/players.php';
        handleGetStats((int)$parts[2]);
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
        && $parts[3] === 'fire':
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