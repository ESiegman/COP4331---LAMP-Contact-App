<?php

header('Content-Type: application/json');

if (isset($_GET['ping'])) {
    echo json_encode(['status' => 'ok', 'service' => 'contacts-app-api']);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Not found']);
