<?php
// config/database.php
// PostgreSQL connection using PDO

function getDB(): PDO {
    $host = 'localhost';
    $port = '5432';
    $dbname = 'battleship';
    $user = 'battleship_user';
    $password = 'battleship123';

    try {
        $pdo = new PDO(
            "pgsql:host=$host;port=$port;dbname=$dbname",
            $user,
            $password
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }
}
```

**Your files should currently look like this:**
```
config/
├── database.php  ← HAS the connection code above
└── schema.sql    ← HAS all your CREATE TABLE statements

helpers/
├── response.php  ← HAS jsonResponse and errorResponse functions
└── validation.php ← still empty for now

routes/
├── players.php   ← still empty
├── games.php     ← still empty
├── moves.php     ← still empty
├── reset.php     ← still empty
└── test.php      ← still empty

index.php         ← HAS the router code
.htaccess         ← HAS the rewrite rules