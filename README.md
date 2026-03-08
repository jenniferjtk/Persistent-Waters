Persistent Waters

CPSC 3750 – Distributed Multiplayer Battleship System

Team Name: Persistent Waters

⸻

Project Overview

Persistent Waters is a distributed multiplayer Battleship system designed for the CPSC 3750 capstone project. The system provides a server-side API that manages multiplayer game sessions, player identities, ship placement, turn-based gameplay, and persistent player statistics.

The application follows a client/server architecture where the backend server exposes a JSON API that allows multiple players to create and join games, place ships, and perform moves in a turn-based environment. The system maintains persistent game state and player statistics using a relational PostgreSQL database.

The project is developed in three phases:

Phase 1 – Server and Database
Implementation of the backend API, relational database schema, and game lifecycle logic.

Phase 2 – Human Client
Development of a web-based client that interacts with the API.

Phase 3 – Computer Player
Implementation of an autonomous player that interacts with the public API without cheating.

⸻

Architecture Summary

The system follows a lightweight modular backend architecture implemented in PHP.

Core Components

Router (index.php)
All API requests are routed through a single entry point which parses the request path and dispatches to the appropriate route handler.

Route Handlers
routes/
players.php
games.php
moves.php
reset.php
test.php

Each route module handles a specific category of API functionality.

Configuration
config/
database.php
schema.sql

Database connection and schema definitions are maintained here.

Helpers
helpers/
response.php
validation.php

Shared utility functions for JSON responses and input validation.

Database Architecture

The system uses a relational PostgreSQL database with the following tables:

Players
Stores persistent player identities and lifetime statistics.

Games
Stores metadata for each game including grid size, status, and turn index.

GamePlayers
Join table representing the many-to-many relationship between players and games.
Tracks turn order, elimination status, and ship placement.

Ships
Stores ship positions for each player in a game.

Moves
Stores a chronological log of all shots fired in a game.

API Description

All API endpoints use JSON requests and responses.

Base path:
/api

Player Endpoints

Create player
POST /api/players

Response
{
  "player_id": 1
}

Get Player statistics
GET /api/players/{id}/stats

Response
{
  "games_played": 3,
  "wins": 1,
  "losses": 2,
  "total_shots": 24,
  "total_hits": 9,
  "accuracy": 0.375
}

Game Lifecycle Endpoints

Create game
POST /api/games

Join game
POST /api/games/{id}/join

Get game state
GET /api/games/{id}

Place ships
POST /api/games/{id}/place

Fire shot
POST /api/games/{id}/fire

Move history
GET /api/games/{id}/moves

⸻

System Control

Reset server state
POST /api/reset

⸻

Test Mode Endpoints

These endpoints are only accessible when test mode is enabled and require a test authentication header.

Restart game
POST /api/test/games/{id}/restart

Deterministic ship placement
POST /api/test/games/{id}/ships

Reveal board state
GET /api/test/games/{id}/board/{player_id}

Test endpoints exist to allow deterministic automated grading and should not affect player statistics.

⸻

Team Members

Owen Schuyler
Jennifer Tk

⸻

AI Tools Used

The following AI tools are used to assist development:

ChatGPT
Used for architecture planning, API design validation, debugging assistance, and generating test scenarios.

Claude Code
Used for implementation assistance, code review suggestions, and refactoring support.

AI tools are used as assistants, while all architectural decisions, validation, and testing strategies are verified by the human developers.

⸻

Roles and Responsibilities

Owen Schuyler – Backend / Architecture Lead

Primary responsibilities:

Design overall backend architecture
Define API contract and request/response structure
Design relational database schema
Implement core game lifecycle logic
Implement turn rotation and elimination rules
Maintain API routing and validation logic
Maintain project documentation and architecture descriptions

Jennifer Tk – Frontend / Database

Primary responsibilities:

Design and implement user interface for the game client
Manage frontend hosting and deployment
Assist with relational database implementation
Develop statistics display for players
Assist with integration between frontend and backend API

⸻

AI Collaboration Philosophy

AI tools are used to assist with boilerplate code generation, exploring implementation approaches, and generating test cases. However, AI-generated code is always reviewed and validated by the human developers.

Benefits of AI-assisted development include faster exploration of architectural alternatives and rapid generation of test scenarios.

Limitations include the potential for hallucinated or incorrect suggestions, which requires careful verification and disciplined testing.

⸻

Current Project Status

Phase 1 development in progress.

Completed so far:

API routing structure
Database schema design
Player identity endpoints
Game creation and join logic
Ship placement validation
Move execution logic
Move logging and retrieval
Test mode endpoints for deterministic grading

Remaining Phase 1 work includes:

Expanded validation and edge-case handling
Comprehensive testing aligned with the autograder
Deployment to a public server for grading

