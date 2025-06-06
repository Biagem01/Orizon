<?php
/**
 * Orizon Travel Agency - Database Connection (MySQL - MAMP)
 * Connessione sicura con uso di variabili d'ambiente da file .env
 */

require_once __DIR__ . '/vendor/autoload.php'; // Carica Composer autoload

use Dotenv\Dotenv;

// Carica il file .env dalla root del progetto
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Recupera le variabili dal file .env
$port = $_ENV['DB_PORT'];
$host = $_ENV['DB_HOST'];
$dbname = $_ENV['DB_NAME'] ;
$username = $_ENV['DB_USER'] ;
$password = $_ENV['DB_PASS'] ;

// Connessione PDO
try {
    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ];

    $pdo = new PDO($dsn, $username, $password, $options);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database connection failed',
        'message' => 'Unable to connect to the database. Please check your configuration.'
    ]);
    error_log("Database connection error: " . $e->getMessage());
    exit;
}

// Funzioni ausiliarie

function validateInput($data, $required = [], $optional = []) {
    $validated = [];

    foreach ($required as $field) {
        if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
            throw new InvalidArgumentException("Field '{$field}' is required and cannot be empty");
        }
        $validated[$field] = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
    }

    foreach ($optional as $field => $default) {
        $validated[$field] = isset($data[$field])
            ? (is_string($data[$field]) ? trim($data[$field]) : $data[$field])
            : $default;
    }

    return $validated;
}

function sendResponse($data, $status_code = 200) {
    http_response_code($status_code);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

function sendError($message, $status_code = 400, $error_type = 'Bad Request') {
    sendResponse([
        'error' => $error_type,
        'message' => $message
    ], $status_code);
}
?>
