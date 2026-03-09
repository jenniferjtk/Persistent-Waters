<?php
// config/database.php
// PostgreSQL connection using PDO

function getDB(): PDO {
    $url = getenv('DATABASE_URL');

    if ($url) {
        $url = preg_replace('/^postgresql:\/\//', 'postgres://', $url);
        $parts = parse_url($url);
        $host = $parts['host'];
        $port = $parts['port'] ?? 5432;
        $dbname = ltrim($parts['path'], '/');
        $user = $parts['user'];
        $password = $parts['pass'];
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    } else {
        $dsn = "pgsql:host=localhost;port=5432;dbname=battleship";
        $user = 'battleship_user';
        $password = 'battleship123';
    }

    try {
        $pdo = new PDO($dsn, $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
        exit;
    }
}