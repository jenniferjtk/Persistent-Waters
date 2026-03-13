# Persistent Waters
### CPSC 3750 – Distributed Multiplayer Battleship System

**Team:** Persistent Waters  
**Live API:** https://persistent-waters.onrender.com  
**Repository:** https://github.com/jenniferjtk/Persistent-Waters

---

## Project Overview

Persistent Waters is a distributed multiplayer Battleship system built for CPSC 3750. The system exposes a JSON REST API that manages multiplayer game sessions, player identities, ship placement, turn-based gameplay, and persistent player statistics across a relational PostgreSQL database.

The project is developed in three phases:

| Phase | Focus | Demo |
|-------|-------|------|
| Phase 1 | Server + Database API | March 31 / April 2 |
| Phase 2 | Human Web Client | April 17 (on camera) |
| Phase 3 | Autonomous Computer Player | April 21 / 23 |

---

## Architecture

The backend is a modular PHP 8.2 application deployed via Docker on Render, backed by a PostgreSQL database.

```
Persistent-Waters/
├── index.php               # Single entry-point router
├── router.php              # PHP dev server router (local use)
├── Dockerfile              # Container config for Render deployment
├── .htaccess               # Apache URL rewriting (local XAMPP)
│
├── config/
│   ├── database.php        # PDO connection (reads DATABASE_URL env var)
│   └── schema.sql          # Full table definitions with IF NOT EXISTS
│
├── routes/
│   ├── players.php         # Player creation and stats
│   ├── games.php           # Game lifecycle, placement, fire logic
│   ├── moves.php           # Move history retrieval
│   ├── reset.php           # Server state reset
│   ├── setup.php           # One-time schema initializer
│   └── test.php            # Deterministic test mode endpoints
│
└── helpers/
    ├── response.php        # jsonResponse() and errorResponse() utilities
    └── validation.php      # Shared input validation helpers
```

### Database Schema

| Table | Purpose |
|-------|---------|
| `players` | Persistent player identity and lifetime statistics |
| `games` | Game metadata: grid size, status, turn index |
| `game_players` | Join table: player ↔ game with turn order and elimination state |
| `ships` | Ship positions per player per game |
| `moves` | Chronological shot log with timestamps |

All tables enforce relational integrity via foreign key constraints. `game_players` uses a composite primary key `(game_id, player_id)`. Player `username` is globally unique at the database level.

---

## API Reference

All endpoints accept and return JSON. Base path: `/api`

### System

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/reset` | Clear all game data and reset sequences |
| POST | `/api/setup` | Initialize database schema (run once on new deployment) |

### Players

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/players` | Create a player. Server generates `player_id` — client must not supply one |
| GET | `/api/players/{id}/stats` | Retrieve lifetime stats for a player |

**Create player request:**
```json
{ "username": "dan" }
```
**Create player response (201):**
```json
{ "player_id": 1 }
```

**Stats response:**
```json
{
  "games_played": 3,
  "wins": 1,
  "losses": 2,
  "total_shots": 24,
  "total_hits": 9,
  "accuracy": 0.375
}
```

### Games

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/games` | Create a game (`grid_size`: 5–15, `max_players` ≥ 1) |
| POST | `/api/games/{id}/join` | Join a waiting game |
| GET | `/api/games/{id}` | Get current game state |
| POST | `/api/games/{id}/place` | Place exactly 3 ships (before game starts) |
| POST | `/api/games/{id}/fire` | Fire at a coordinate (active games, correct turn only) |
| GET | `/api/games/{id}/moves` | Full chronological move history |

**Fire response (active game):**
```json
{ "result": "hit", "next_player_id": 3, "game_status": "active" }
```
**Fire response (winning shot):**
```json
{ "result": "hit", "next_player_id": null, "game_status": "finished", "winner_id": 2 }
```

### Test Mode Endpoints

Test mode endpoints require the header:
```
X-Test-Password: clemson-test-2026
```
Both `/api/test/` and `/test/` URL prefixes are supported. Both `X-Test-Password` and `X-Test-Mode` headers are accepted.

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/test/games/{id}/restart` | Reset a game's ships and moves without touching player stats |
| POST | `/api/test/games/{id}/ships` | Place ships at deterministic coordinates for grading |
| GET | `/api/test/games/{id}/board/{player_id}` | Reveal all ship positions and hit state |

Test endpoints exist to enable deterministic automated grading. They do not affect player statistics.

---

## Validation Rules

- Client must **not** supply `player_id` on player creation → 400
- Duplicate username returns the existing `player_id` → 200
- Joining the same game twice → 400
- Joining a different game with the same name reuses the same identity
- `grid_size` must be 5–15, `max_players` ≥ 1
- Exactly 3 ships required per player, no overlapping coordinates
- Firing out of bounds, out of turn, or duplicate coordinates → 400 / 403
- Invalid `player_id` → 403, valid `player_id` but wrong game → 403

---

## Deployment

The application runs in a Docker container on Render connected to a Render-managed PostgreSQL database.

**Environment variables required:**

| Variable | Description |
|----------|-------------|
| `DATABASE_URL` | Full PostgreSQL connection string (set by Render) |
| `TEST_MODE` | `true` to enable test endpoints |

**Local development:**
```bash
cd /path/to/Persistent-Waters
php -S localhost:8000 router.php
```
Requires local PostgreSQL with `battleship` database and `battleship_user` credentials as defined in `config/database.php`.

---

## Team

| Name | Role |
|------|------|
| Owen Schuyler | Backend / Architecture Lead |
| Jennifer Tk | Frontend / Database |

**Owen Schuyler — Backend / Architecture Lead**  
Responsible for overall backend architecture, API contract definition, request/response structure, core game lifecycle logic, turn rotation and elimination rules, routing, and validation logic.

**Jennifer Tk — Frontend / Database**  
Responsible for relational database schema design, PostgreSQL setup and migration, database query implementation, deployment infrastructure, and the Phase 2 web client interface and statistics display.

---

## AI Tools Used

| Tool | Usage |
|------|-------|
| Claude (claude.ai) | Architecture planning, implementation assistance, debugging, code review, deployment troubleshooting |
| ChatGPT | Architecture planning, API design validation, test scenario generation |

AI tools are used as engineering assistants. All architectural decisions, database schema design, validation logic, and testing strategies are owned and verified by the human developers. AI-generated output is always reviewed before integration.

---

## Current Status (Phase 1 — Checkpoint A)

**Completed:**
- Full API routing structure
- PostgreSQL schema with all constraints and foreign keys
- Player identity endpoints (create, stats)
- Game creation and join logic
- Ship placement validation
- Move execution with turn rotation and elimination
- Move logging with timestamps
- Player statistics (games, wins, losses, shots, hits, accuracy)
- Test mode endpoints for deterministic grading
- Docker deployment on Render

**Remaining Phase 1 work:**
- Expanded edge-case validation
- Concurrency and stress testing
- Final autograder alignment

