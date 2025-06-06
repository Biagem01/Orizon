<?php
/**
 * Orizon Travel Agency - Main API Router
 * Routes incoming requests to appropriate endpoint handlers
 */

// Set JSON content type for all responses
header("Content-Type: application/json");

// Enable CORS for development
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get the request URI and parse it
$request_uri = $_SERVER['REQUEST_URI'];
$parsed_url = parse_url($request_uri);
$path = $parsed_url['path'];

// Remove any base path if running in subdirectory
$path = preg_replace('/^\/[^\/]*\.php/', '', $path);
if (empty($path)) {
    $path = '/';
}

try {
    // Route to appropriate handler based on path
    if ($path === '/' || $path === '') {
        // Serve the frontend interface
        header("Content-Type: text/html");
        readfile(__DIR__ . '/frontend.html');
    } elseif ($path === '/api' || $path === '/api/') {
        // API information endpoint
        echo json_encode([
            "name" => "Orizon Travel Agency API",
            "version" => "1.0.0",
            "description" => "RESTful API for managing countries and travels",
            "endpoints" => [
                "GET /countries" => "Get all countries",
                "GET /countries/{id}" => "Get specific country",
                "POST /countries" => "Create new country",
                "PUT /countries/{id}" => "Update country",
                "DELETE /countries/{id}" => "Delete country",
                "GET /travels" => "Get all travels (supports ?country_id and ?seats_available filters)",
                "GET /travels/{id}" => "Get specific travel",
                "POST /travels" => "Create new travel",
                "PUT /travels/{id}" => "Update travel",
                "DELETE /travels/{id}" => "Delete travel"
            ]
        ]);
    } elseif (strpos($path, '/countries') === 0) {
        require __DIR__ . '/routes/countries.php';
    } elseif (strpos($path, '/travels') === 0) {
        require __DIR__ . '/routes/travels.php';
    } else {
        http_response_code(404);
        echo json_encode([
            "error" => "Endpoint not found",
            "message" => "The requested endpoint does not exist",
            "path" => $path
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "error" => "Internal server error",
        "message" => "An unexpected error occurred while processing your request"
    ]);
    error_log("API Error: " . $e->getMessage());
}
?>
