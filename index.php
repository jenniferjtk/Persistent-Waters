<?php
// index.php
// Main router - all requests come here first

require_once 'helpers/response.php';

// Parse the request
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Remove leading slash and split into parts
$path = trim($path, '/');
$parts = explode('/', $path);


error_log("DEBUG - method: $method | path: $path | parts: " . json_encode($parts));


// Must start with 'api'
if ($parts[0] !== 'api') {
    errorResponse('Not found', 404);
}

// Route to correct handler
switch (true) {

    // POST /api/reset
    case $method === 'POST' && $path === 'api/reset':
        require_once 'routes/reset.php';
        handleReset();
        break;

    // POST /api/players
    case $method === 'POST' && $path === 'api/players':
        require_once 'routes/players.php';
        handleCreatePlayer();
        break;

    // GET /api/players/{id}/stats
   case $method === 'GET' && isset($parts[2]) && $parts[3] === 'stats':
    require_once 'routes/players.php';
    handleGetStats((int)$parts[2]);
    break;

    // POST /api/games
    case $method === 'POST' && $path === 'api/games':
        require_once 'routes/games.php';
        handleCreateGame();
        break;

    // POST /api/games/{id}/join
    case $method === 'POST' && isset($parts[1]) && isset($parts[3]) && $parts[3] === 'join':
        require_once 'routes/games.php';
        handleJoinGame((int)$parts[2]);
        break;

    // GET /api/games/{id}
    case $method === 'GET' && isset($parts[2]) && !isset($parts[3]):
        require_once 'routes/games.php';
        handleGetGame((int)$parts[2]);
        break;

    // POST /api/games/{id}/place
    case $method === 'POST' && isset($parts[3]) && $parts[3] === 'place':
        require_once 'routes/games.php';
        handlePlaceShips((int)$parts[2]);
        break;

    // POST /api/games/{id}/fire
    case $method === 'POST' && isset($parts[3]) && $parts[3] === 'fire':
        require_once 'routes/games.php';
        handleFire((int)$parts[2]);
        break;

    // GET /api/games/{id}/moves
    case $method === 'GET' && isset($parts[3]) && $parts[3] === 'moves':
        require_once 'routes/games.php';
        handleGetMoves((int)$parts[2]);
        break;

    // TEST MODE endpoints
    case str_starts_with($path, 'api/test/'):
        require_once 'routes/test.php';
        handleTestRoute($method, $parts);
        break;

    default:
        errorResponse('Not found', 404);
}