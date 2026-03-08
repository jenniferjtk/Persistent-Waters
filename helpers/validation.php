<?php
// helpers/validation.php

require_once __DIR__ . '/response.php';

/**
 * Read and decode JSON request body.
 */
function getJsonBody(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        errorResponse('Invalid JSON body', 400);
    }

    return is_array($data) ? $data : [];
}

/**
 * Require a field to exist in an array.
 */
function requireField(array $body, string $field): void {
    if (!array_key_exists($field, $body)) {
        errorResponse("$field is required", 400);
    }
}

/**
 * Require a field to be a non-empty string.
 */
function requireNonEmptyString(array $body, string $field): string {
    requireField($body, $field);

    $value = trim((string)$body[$field]);
    if ($value === '') {
        errorResponse("$field is required", 400);
    }

    return $value;
}

/**
 * Require a field to be an integer-like value.
 */
function requireInt(array $body, string $field): int {
    requireField($body, $field);

    if (!is_numeric($body[$field])) {
        errorResponse("$field must be an integer", 400);
    }

    return (int)$body[$field];
}

/**
 * Validate integer is within a range.
 */
function validateRange(int $value, string $field, int $min, int $max): void {
    if ($value < $min || $value > $max) {
        errorResponse("$field must be between $min and $max", 400);
    }
}

/**
 * Validate row/col are inside the board.
 */
function validateCoordinates(int $row, int $col, int $gridSize): void {
    if ($row < 0 || $row >= $gridSize || $col < 0 || $col >= $gridSize) {
        errorResponse('Coordinates out of bounds', 400);
    }
}

/**
 * Validate ships array:
 * - must be an array
 * - exactly 3 entries
 * - each entry has row and col
 * - no duplicate coordinates
 * - all coordinates inside grid
 */
function validateShips(array $ships, int $gridSize): void {
    if (count($ships) !== 3) {
        errorResponse('Exactly 3 ships required', 400);
    }

    $seen = [];

    foreach ($ships as $ship) {
        if (!is_array($ship) || !array_key_exists('row', $ship) || !array_key_exists('col', $ship)) {
            errorResponse('Each ship must include row and col', 400);
        }

        if (!is_numeric($ship['row']) || !is_numeric($ship['col'])) {
            errorResponse('Ship row and col must be integers', 400);
        }

        $row = (int)$ship['row'];
        $col = (int)$ship['col'];

        validateCoordinates($row, $col, $gridSize);

        $key = $row . ',' . $col;
        if (isset($seen[$key])) {
            errorResponse('Duplicate ship coordinates', 400);
        }

        $seen[$key] = true;
    }
}