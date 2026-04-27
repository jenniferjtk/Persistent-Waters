# Persistent Waters 🏴‍☠️
### CPSC 3750 – Distributed Multiplayer Battleship System

**Team:** Persistent Waters (0x07)  
**Live API:** https://persistent-waters.onrender.com  
**Live Client:** https://jenniferjgj.com/persistentwaters  
**Repository:** https://github.com/jenniferjtk/Persistent-Waters

---

## Project Overview

Persistent Waters is a distributed multiplayer Battleship system built for CPSC 3750. The system exposes a JSON REST API that manages multiplayer game sessions, player identities, ship placement, turn-based gameplay, and persistent player statistics across a relational PostgreSQL database. A pirate-themed single-page web client connects to the API and provides a full playable game experience.

The project is developed in three phases:

| Phase | Focus | Status |
|-------|-------|--------|
| Phase 1 | Server + Database API | Complete |
| Phase 2 | Human Web Client | Complete |

## Architecture

### Backend

The backend is a modular PHP 8.2 application deployed via Docker on Render, backed by a managed PostgreSQL database.

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

### Frontend

The Phase 2 client is a vanilla JavaScript single-page application hosted on cPanel at `jenniferjgj.com/persistentwaters`. It is a single `index.html` file with no build step or framework dependencies.

**Architecture decisions:**

- **Single-file SPA** — all views (register, lobby, placement, game, stats) are stacked vertically in one HTML file. Navigation uses `scrollIntoView()` to snap to each section. Chosen over a multi-page approach to eliminate page reloads and simplify cPanel deployment.
- **State management** — all client state lives in a single `state` object and a separate `gameplay` object for in-game data. No external state library.
- **Player identity** — stored in `localStorage` so returning players are recognized across sessions without re-registering.
- **Polling** — game state updates via `setInterval` calling `GET /api/games/{id}` and `GET /api/games/{id}/moves` every 4 seconds. Chosen over WebSockets to match the stateless PHP backend architecture. 4 seconds is imperceptible in a turn-based game.
- **Turn enforcement** — compares `current_turn_index` from the server against the player's `turn_order` to enable or disable the fire button.
- **Multi-game UX** — a player is in one active game at a time. The lobby shows all waiting games, a player joins one and stays until it ends.

---

## Database Schema

| Table | Purpose |
|-------|---------|
| `players` | Persistent player identity and lifetime statistics |
| `games` | Game metadata: grid size, status, turn index |
| `game_players` | Join table: player ↔ game with turn order and elimination state |
| `ships` | Ship positions per player per game |
| `moves` | Chronological shot log with timestamps |

All tables enforce relational integrity via foreign key constraints. `game_players` uses a composite primary key `(game_id, player_id)`. Player `username` is globally unique at the database level. Sequences reset on `POST /api/reset` via `TRUNCATE ... RESTART IDENTITY`.

---

## API Reference

All endpoints accept and return JSON. Base path: `/api`

### System

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/reset` | Clear all game data and reset sequences |
| `POST` | `/api/setup` | Initialize database schema (run once on new deployment) |
| `GET` | `/api/health` | Health check — returns `{"status":"ok"}` |

### Players

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/players` | Create a player — server generates `player_id` |
| `GET` | `/api/players` | List all players |
| `GET` | `/api/players/{id}/stats` | Retrieve lifetime stats for a player |

**Create player request:**
```json
{ "username": "dan" }
```

**Create player response `201`:**
```json
{ "player_id": 1 }
```

**Stats response `200`:**
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
| `POST` | `/api/games` | Create a game (`grid_size`: 5–15, `max_players` ≥ 2) |
| `GET` | `/api/games` | List all open games |
| `POST` | `/api/games/{id}/join` | Join a waiting game |
| `GET` | `/api/games/{id}` | Get current game state including players and turn info |
| `GET` | `/api/games/{id}/players` | List all players in a game with turn order |
| `GET` | `/api/games/{id}/boards` | Get ship and hit state for all players |
| `POST` | `/api/games/{id}/place` | Place exactly 3 ships before game starts |
| `POST` | `/api/games/{id}/fire` | Fire at a coordinate — active games, correct turn only |
| `GET` | `/api/games/{id}/moves` | Full chronological move history |

**Game status lifecycle:**
```
waiting_setup → playing → finished
```

**Fire response (active game) `200`:**
```json
{ "result": "miss", "next_player_id": 2, "game_status": "playing" }
```

**Fire response (winning shot) `200`:**
```json
{ "result": "hit", "next_player_id": null, "game_status": "finished", "winner_id": 1 }
```

### Test Mode Endpoints

Test mode endpoints require the header:
```
X-Test-Password: clemson-test-2026
```

Both `/api/test/` and `/test/` URL prefixes are supported.

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/test/games/{id}/restart` | Reset a game's ships and moves without touching player stats |
| `POST` | `/api/test/games/{id}/ships` | Place ships at deterministic coordinates for grading |
| `GET` | `/api/test/games/{id}/board/{player_id}` | Reveal all ship positions and hit state |

---

## Validation Rules

| Rule | Response |
|------|----------|
| Client supplies `player_id` on creation | `400` |
| Duplicate username | `409` |
| `grid_size` outside 5–15 | `400` |
| `max_players` below 2 | `400` |
| Non-existent `creator_id` | `400` |
| Joining a non-existent game | `404` |
| Joining with non-existent `player_id` | `404` |
| Joining a full game | `400` |
| Joining the same game twice | `400` |
| Joining a game already in `playing` status | `400` |
| Placing fewer or more than 3 ships | `400` |
| Overlapping or out-of-bounds ship coordinates | `400` |
| Placing ships twice | `409` |
| Firing out of bounds | `400` |
| Firing out of turn | `403` |
| Firing at already-targeted coordinate | `409` |
| Firing into a finished game | `400` |
| Non-existent game or player | `404` |

---

## Deployment

### Production

The application runs in a Docker container on Render connected to a Render-managed PostgreSQL database in the Ohio region.

**Environment variables required:**

| Variable | Description |
|----------|-------------|
| `DATABASE_URL` | Full PostgreSQL connection string (set by Render) |
| `TEST_MODE` | `true` to enable test endpoints |

**First-time setup on a new Render deployment:**
```bash
curl -s -X POST https://persistent-waters.onrender.com/api/setup
```

**Reset database state before grading:**
```bash
curl -s -X POST https://persistent-waters.onrender.com/api/reset
```

### Local Development

```bash
cd /path/to/Persistent-Waters
php -S localhost:8000 router.php
```

Requires local PostgreSQL with credentials as defined in `config/database.php`. Alternatively, point `DATABASE_URL` at the Render database for local testing against production data.

---

## Testing Strategy

- **Phase 1** — Instructor autograder (Gradescope) across three checkpoints: Foundations, Identity & Logic, Persistence & Stress. Checkpoint A: 24.8/25. Checkpoint B: 17/18.
- **Phase 2** — Class-wide pool autograder (142 tests from 30 teams). Pool tests contain contradictory expectations across teams — documented and escalated to instructor. Instructor REF test suite scores reflect actual spec compliance.
- **Local testing** — two-browser simulation using Chrome + Chrome Incognito for multiplayer flow verification. `curl` for endpoint-level verification of status codes and response bodies.

---

## Team

| Name | Role |
|------|------|
| Jennifer Johnson | Frontend, database schema, backend API, deployment, hosting |
| Owen Schuyler | Backend architecture, game logic, gameplay view, turn flow |

**Jennifer Johnson**  
Responsible for the Phase 2 web client player registration, lobby, ship placement grid, stats view, and cPanel hosting. Also owns database schema design, PostgreSQL setup, API endpoints, and deployment infrastructure on Render.

**Owen Schuyler**  
Responsible for backend API architecture, core game lifecycle logic, turn rotation, elimination rules, routing, validation, and the Phase 2 gameplay view including firing mechanic, live board display, hit/miss rendering, and polling.

---

## AI Tools Used

| Tool | Usage |
|------|-------|
| Claude (claude.ai) | Architecture planning, implementation, debugging, deployment troubleshooting, test analysis |
| ChatGPT | API design validation, test scenario generation |

AI tools are used as engineering assistants. All architectural decisions, schema design, validation logic, and testing strategies are owned and verified by the human developers. AI output is always reviewed before integration.
