touch config/schema.sql
```
cd /Applications/XAMPP/xamppfiles/htdocs/Persistent-Waters-1

```
Your Mac
├── XAMPP (runs PHP)
├── PostgreSQL (runs locally via Homebrew)
│   └── battleship database
│       └── your 5 tables
└── VS Code (your code)

Render (later)
├── PHP server (not set up yet)
└── PostgreSQL database (not set up yet)
    └── will need your tables created here too

    '''
    CREATE TABLE players (
    player_id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_games INTEGER DEFAULT 0,
    total_wins INTEGER DEFAULT 0,
    total_losses INTEGER DEFAULT 0,
    total_moves INTEGER DEFAULT 0
);

CREATE TABLE games (
    game_id SERIAL PRIMARY KEY,
    creator_id INTEGER NOT NULL REFERENCES players(player_id),
    grid_size INTEGER NOT NULL CHECK (grid_size >= 5 AND grid_size <= 15),
    max_players INTEGER NOT NULL CHECK (max_players >= 1),
    status VARCHAR(20) DEFAULT 'waiting',
    current_turn_index INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE game_players (
    game_id INTEGER NOT NULL REFERENCES games(game_id),
    player_id INTEGER NOT NULL REFERENCES players(player_id),
    turn_order INTEGER NOT NULL,
    is_eliminated BOOLEAN DEFAULT FALSE,
    ships_placed BOOLEAN DEFAULT FALSE,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (game_id, player_id)
);

CREATE TABLE ships (
    ship_id SERIAL PRIMARY KEY,
    game_id INTEGER NOT NULL REFERENCES games(game_id),
    player_id INTEGER NOT NULL REFERENCES players(player_id),
    row_pos INTEGER NOT NULL,
    col_pos INTEGER NOT NULL,
    is_hit BOOLEAN DEFAULT FALSE,
    UNIQUE (game_id, player_id, row_pos, col_pos)
);

CREATE TABLE moves (
    move_id SERIAL PRIMARY KEY,
    game_id INTEGER NOT NULL REFERENCES games(game_id),
    player_id INTEGER NOT NULL REFERENCES players(player_id),
    row_pos INTEGER NOT NULL,
    col_pos INTEGER NOT NULL,
    result VARCHAR(10) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);'''