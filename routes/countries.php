<?php
/**
 * Orizon Travel Agency - Countries API Routes
 * Handles CRUD operations for countries
 */

require_once __DIR__ . '/../db.php';

// Parse the request path to extract ID if present
$path = $_SERVER['REQUEST_URI'];
$path_parts = explode('/', trim($path, '/'));
$country_id = isset($_GET['id']) ? (int)$_GET['id'] : null;


// Get request method
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            if ($country_id) {
                // Get specific country
                getCountry($pdo, $country_id);
            } else {
                // Get all countries
                getAllCountries($pdo);
            }
            break;
            
        case 'POST':
            // Create new country
            createCountry($pdo);
            break;
            
        case 'PUT':
            if (!$country_id) {
                sendError('Country ID is required for update operation', 400);
            }
            // Update existing country
            updateCountry($pdo, $country_id);
            break;
            
        case 'DELETE':
            if (!$country_id) {
                sendError('Country ID is required for delete operation', 400);
            }
            // Delete country
            deleteCountry($pdo, $country_id);
            break;
            
        default:
            sendError('Method not allowed', 405, 'Method Not Allowed');
    }
} catch (Exception $e) {
    error_log("Countries API Error: " . $e->getMessage());
    sendError('An error occurred while processing your request', 500, 'Internal Server Error');
}

/**
 * Get all countries
 */
function getAllCountries($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT c.*, 
                   COUNT(t.id) as travel_count 
            FROM countries c 
            LEFT JOIN travels t ON c.id = t.country_id 
            GROUP BY c.id 
            ORDER BY c.name ASC
        ");
        $stmt->execute();
        $countries = $stmt->fetchAll();
        
        sendResponse([
            'data' => $countries,
            'count' => count($countries)
        ]);
    } catch (PDOException $e) {
        error_log("Get all countries error: " . $e->getMessage());
        sendError('Failed to retrieve countries', 500, 'Database Error');
    }
}

/**
 * Get specific country by ID
 */
function getCountry($pdo, $country_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT c.*, 
                   COUNT(t.id) as travel_count 
            FROM countries c 
            LEFT JOIN travels t ON c.id = t.country_id 
            WHERE c.id = ? 
            GROUP BY c.id
        ");
        $stmt->execute([$country_id]);
        $country = $stmt->fetch();
        
        if (!$country) {
            sendError('Country not found', 404, 'Not Found');
        }
        
        // Also get related travels
        $stmt = $pdo->prepare("
            SELECT id, title, seats_available, price, start_date, end_date 
            FROM travels 
            WHERE country_id = ? 
            ORDER BY start_date ASC
        ");
        $stmt->execute([$country_id]);
        $travels = $stmt->fetchAll();
        
        $country['travels'] = $travels;
        
        sendResponse(['data' => $country]);
    } catch (PDOException $e) {
        error_log("Get country error: " . $e->getMessage());
        sendError('Failed to retrieve country', 500, 'Database Error');
    }
}

/**
 * Create new country
 */
function createCountry($pdo) {
    try {
        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            sendError('Invalid JSON data provided', 400);
        }
        
        // Validate input
        $data = validateInput($input, ['name']);
        
        // Check if country already exists
        $stmt = $pdo->prepare("SELECT id FROM countries WHERE name = ?");
        $stmt->execute([$data['name']]);
        if ($stmt->fetch()) {
            sendError('Country already exists', 409, 'Conflict');
        }
        
        // Insert new country
        $stmt = $pdo->prepare("INSERT INTO countries (name) VALUES (?)");
        $stmt->execute([$data['name']]);
        
        $country_id = $pdo->lastInsertId();
        
        // Return created country
        $stmt = $pdo->prepare("SELECT * FROM countries WHERE id = ?");
        $stmt->execute([$country_id]);
        $country = $stmt->fetch();
        
        sendResponse([
            'message' => 'Country created successfully',
            'data' => $country
        ], 201);
        
    } catch (InvalidArgumentException $e) {
        sendError($e->getMessage(), 400);
    } catch (PDOException $e) {
        error_log("Create country error: " . $e->getMessage());
        if ($e->getCode() == 23000) {
            sendError('Country name must be unique', 409, 'Conflict');
        }
        sendError('Failed to create country', 500, 'Database Error');
    }
}

/**
 * Update existing country
 */
function updateCountry($pdo, $country_id) {
    try {
        // Check if country exists
        $stmt = $pdo->prepare("SELECT * FROM countries WHERE id = ?");
        $stmt->execute([$country_id]);
        $existing_country = $stmt->fetch();
        
        if (!$existing_country) {
            sendError('Country not found', 404, 'Not Found');
        }
        
        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            sendError('Invalid JSON data provided', 400);
        }
        
        // Validate input
        $data = validateInput($input, ['name']);
        
        // Check if new name conflicts with existing country (excluding current one)
        $stmt = $pdo->prepare("SELECT id FROM countries WHERE name = ? AND id != ?");
        $stmt->execute([$data['name'], $country_id]);
        if ($stmt->fetch()) {
            sendError('Country name already exists', 409, 'Conflict');
        }
        
        // Update country
        $stmt = $pdo->prepare("UPDATE countries SET name = ? WHERE id = ?");
        $stmt->execute([$data['name'], $country_id]);
        
        // Return updated country
        $stmt = $pdo->prepare("SELECT * FROM countries WHERE id = ?");
        $stmt->execute([$country_id]);
        $country = $stmt->fetch();
        
        sendResponse([
            'message' => 'Country updated successfully',
            'data' => $country
        ]);
        
    } catch (InvalidArgumentException $e) {
        sendError($e->getMessage(), 400);
    } catch (PDOException $e) {
        error_log("Update country error: " . $e->getMessage());
        if ($e->getCode() == 23000) {
            sendError('Country name must be unique', 409, 'Conflict');
        }
        sendError('Failed to update country', 500, 'Database Error');
    }
}

/**
 * Delete country
 */
function deleteCountry($pdo, $country_id) {
    try {
        // Check if country exists
        $stmt = $pdo->prepare("SELECT * FROM countries WHERE id = ?");
        $stmt->execute([$country_id]);
        $country = $stmt->fetch();
        
        if (!$country) {
            sendError('Country not found', 404, 'Not Found');
        }

        // Check if country has associated travels
        $stmt = $pdo->prepare("SELECT COUNT(*) as travel_count FROM travels WHERE country_id = ?");
        $stmt->execute([$country_id]);
        $result = $stmt->fetch();

        if ($result['travel_count'] > 0) {
            sendError(
                'Cannot delete country with associated travels. Please delete or reassign travels first.',
                409,
                'Conflict'
            );
        }

        // Delete country
        $stmt = $pdo->prepare("DELETE FROM countries WHERE id = ?");
        $stmt->execute([$country_id]);

        // 204 No Content, quindi non inviamo nulla nel body
        http_response_code(204);
        exit;

    } catch (PDOException $e) {
        error_log("Delete country error: " . $e->getMessage());
        sendError('Failed to delete country', 500, 'Database Error');
    }
}

?>
