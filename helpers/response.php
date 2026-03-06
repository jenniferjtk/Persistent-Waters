<?php
// helpers/response.php

function jsonResponse(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function errorResponse(string $message, int $status): void {
    jsonResponse(['error' => $message], $status);
}