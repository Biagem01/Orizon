<?php
/**
 * Orizon Travel Agency - Travels API Routes
 * Handles CRUD operations for travels with filtering capabilities
 */

require_once __DIR__ . '/../db.php';

// Parse the request path to extract ID if present
$path = $_SERVER['REQUEST_URI'];
$path_parts = explode('/', trim($path, '/'));
$travel_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            if ($travel_id) {
                // Get specific travel
                getTravel($pdo, $travel_id);
            } else {
                // Get all travels with optional filtering
                getAllTravels($pdo);
            }
            break;
            
        case 'POST':
            // Create new travel
            createTravel($pdo);
            break;
            
        case 'PUT':
            if (!$travel_id) {
                sendError('Travel ID is required for update operation', 400);
            }
            // Update existing travel
            updateTravel($pdo, $travel_id);
            break;
            
        case 'DELETE':
            if (!$travel_id) {
                sendError('Travel ID is required for delete operation', 400);
            }
            // Delete travel
            deleteTravel($pdo, $travel_id);
            break;
            
        default:
            sendError('Method not allowed', 405, 'Method Not Allowed');
    }
} catch (Exception $e) {
    error_log("Travels API Error: " . $e->getMessage());
    sendError('An error occurred while processing your request', 500, 'Internal Server Error');
}

/**
 * Get all travels with optional filtering
 */
function getAllTravels($pdo) {
    try {
        // Build query with optional filters
        $where_conditions = [];
        $params = [];
        
        // Filter by country_id
        if (isset($_GET['country_id']) && is_numeric($_GET['country_id'])) {
            $where_conditions[] = "t.country_id = ?";
            $params[] = (int)$_GET['country_id'];
        }
        
        // Filter by seats_available
        if (isset($_GET['seats_available']) && is_numeric($_GET['seats_available'])) {
            $where_conditions[] = "t.seats_available >= ?";
            $params[] = (int)$_GET['seats_available'];
        }
        
        // Filter by minimum price
        if (isset($_GET['min_price']) && is_numeric($_GET['min_price'])) {
            $where_conditions[] = "t.price >= ?";
            $params[] = (float)$_GET['min_price'];
        }
        
        // Filter by maximum price
        if (isset($_GET['max_price']) && is_numeric($_GET['max_price'])) {
            $where_conditions[] = "t.price <= ?";
            $params[] = (float)$_GET['max_price'];
        }
        
        // Filter by start date (travels starting after specified date)
        if (isset($_GET['start_date']) && !empty($_GET['start_date'])) {
            $where_conditions[] = "t.start_date >= ?";
            $params[] = $_GET['start_date'];
        }
        
        // Build WHERE clause
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Build ORDER BY clause
        $order_by = 'ORDER BY t.start_date ASC, t.created_at DESC';
        if (isset($_GET['sort'])) {
            switch ($_GET['sort']) {
                case 'price_asc':
                    $order_by = 'ORDER BY t.price ASC';
                    break;
                case 'price_desc':
                    $order_by = 'ORDER BY t.price DESC';
                    break;
                case 'seats_asc':
                    $order_by = 'ORDER BY t.seats_available ASC';
                    break;
                case 'seats_desc':
                    $order_by = 'ORDER BY t.seats_available DESC';
                    break;
                case 'name':
                    $order_by = 'ORDER BY t.title ASC';
                    break;
            }
        }
        
        $query = "
            SELECT t.*,
                   c.name as country_name
            FROM travels t
            JOIN countries c ON t.country_id = c.id
            {$where_clause}
            {$order_by}
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $travels = $stmt->fetchAll();
        
        // Get total count without pagination for metadata
        $count_query = "
            SELECT COUNT(*) as total
            FROM travels t
            JOIN countries c ON t.country_id = c.id
            {$where_clause}
        ";
        $count_stmt = $pdo->prepare($count_query);
        $count_stmt->execute($params);
        $total_count = $count_stmt->fetch()['total'];
        
        sendResponse([
            'data' => $travels,
            'count' => count($travels),
            'total' => (int)$total_count,
            'filters' => $_GET
        ]);
        
    } catch (PDOException $e) {
        error_log("Get all travels error: " . $e->getMessage());
        sendError('Failed to retrieve travels', 500, 'Database Error');
    }
}

/**
 * Get specific travel by ID
 */
function getTravel($pdo, $travel_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT t.*,
                   c.name as country_name
            FROM travels t
            JOIN countries c ON t.country_id = c.id
            WHERE t.id = ?
        ");
        $stmt->execute([$travel_id]);
        $travel = $stmt->fetch();
        
        if (!$travel) {
            sendError('Travel not found', 404, 'Not Found');
        }
        
        sendResponse(['data' => $travel]);
        
    } catch (PDOException $e) {
        error_log("Get travel error: " . $e->getMessage());
        sendError('Failed to retrieve travel', 500, 'Database Error');
    }
}

/**
 * Create new travel
 */
function createTravel($pdo) {
    try {
        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            sendError('Invalid JSON data provided', 400);
        }
        
        // Validate input
        $required = ['country_id', 'seats_available', 'title'];
        $optional = [
            'description' => null,
            'price' => null,
            'start_date' => null,
            'end_date' => null
        ];
        
        $data = validateInput($input, $required, $optional);
        
        // Validate country exists
        $stmt = $pdo->prepare("SELECT id FROM countries WHERE id = ?");
        $stmt->execute([$data['country_id']]);
        if (!$stmt->fetch()) {
            sendError('Country not found', 404, 'Not Found');
        }
        
        // Validate seats_available is positive
        if ($data['seats_available'] < 0) {
            sendError('Seats available must be a non-negative number', 400);
        }
        
        // Validate price if provided
        if ($data['price'] !== null && $data['price'] < 0) {
            sendError('Price must be a non-negative number', 400);
        }
        
        // Validate dates if provided
        if ($data['start_date'] && $data['end_date']) {
            if (strtotime($data['start_date']) >= strtotime($data['end_date'])) {
                sendError('End date must be after start date', 400);
            }
        }
        
        // Insert new travel
        $stmt = $pdo->prepare("
            INSERT INTO travels (country_id, seats_available, title, description, price, start_date, end_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['country_id'],
            $data['seats_available'],
            $data['title'],
            $data['description'],
            $data['price'],
            $data['start_date'],
            $data['end_date']
        ]);
        
        $travel_id = $pdo->lastInsertId();
        
        // Return created travel with country information
        $stmt = $pdo->prepare("
            SELECT t.*,
                   c.name as country_name
            FROM travels t
            JOIN countries c ON t.country_id = c.id
            WHERE t.id = ?
        ");
        $stmt->execute([$travel_id]);
        $travel = $stmt->fetch();
        
        sendResponse([
            'message' => 'Travel created successfully',
            'data' => $travel
        ], 201);
        
    } catch (InvalidArgumentException $e) {
        sendError($e->getMessage(), 400);
    } catch (PDOException $e) {
        error_log("Create travel error: " . $e->getMessage());
        sendError('Failed to create travel', 500, 'Database Error');
    }
}

/**
 * Update existing travel
 */
function updateTravel($pdo, $travel_id) {
    try {
        // Check if travel exists
        $stmt = $pdo->prepare("SELECT * FROM travels WHERE id = ?");
        $stmt->execute([$travel_id]);
        $existing_travel = $stmt->fetch();
        
        if (!$existing_travel) {
            sendError('Travel not found', 404, 'Not Found');
        }
        
        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            sendError('Invalid JSON data provided', 400);
        }
        
        // Allow partial updates - only validate provided fields
        $allowed_fields = ['country_id', 'seats_available', 'title', 'description', 'price', 'start_date', 'end_date'];
        $update_data = [];
        
        foreach ($allowed_fields as $field) {
            if (isset($input[$field])) {
                $update_data[$field] = $input[$field];
            }
        }
        
        if (empty($update_data)) {
            sendError('No valid fields provided for update', 400);
        }
        
        // Validate country_id if provided
        if (isset($update_data['country_id'])) {
            $stmt = $pdo->prepare("SELECT id FROM countries WHERE id = ?");
            $stmt->execute([$update_data['country_id']]);
            if (!$stmt->fetch()) {
                sendError('Country not found', 404, 'Not Found');
            }
        }
        
        // Validate seats_available if provided
        if (isset($update_data['seats_available']) && $update_data['seats_available'] < 0) {
            sendError('Seats available must be a non-negative number', 400);
        }
        
        // Validate price if provided
        if (isset($update_data['price']) && $update_data['price'] !== null && $update_data['price'] < 0) {
            sendError('Price must be a non-negative number', 400);
        }
        
        // Build update query
        $set_clauses = [];
        $params = [];
        
        foreach ($update_data as $field => $value) {
            $set_clauses[] = "{$field} = ?";
            $params[] = $value;
        }
        
        $params[] = $travel_id;
        
        $query = "UPDATE travels SET " . implode(', ', $set_clauses) . " WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        // Return updated travel with country information
        $stmt = $pdo->prepare("
            SELECT t.*,
                   c.name as country_name
            FROM travels t
            JOIN countries c ON t.country_id = c.id
            WHERE t.id = ?
        ");
        $stmt->execute([$travel_id]);
        $travel = $stmt->fetch();
        
        sendResponse([
            'message' => 'Travel updated successfully',
            'data' => $travel
        ]);
        
    } catch (PDOException $e) {
        error_log("Update travel error: " . $e->getMessage());
        sendError('Failed to update travel', 500, 'Database Error');
    }
}

/**
 * Delete travel
 */
function deleteTravel($pdo, $travel_id) {
    try {
        // Check if travel exists
        $stmt = $pdo->prepare("SELECT * FROM travels WHERE id = ?");
        $stmt->execute([$travel_id]);
        $travel = $stmt->fetch();
        
        if (!$travel) {
            sendError('Travel not found', 404, 'Not Found');
        }
        
        // Delete travel
        $stmt = $pdo->prepare("DELETE FROM travels WHERE id = ?");
        $stmt->execute([$travel_id]);
        
        sendResponse([
            'message' => 'Travel deleted successfully'
        ], 204);
        
    } catch (PDOException $e) {
        error_log("Delete travel error: " . $e->getMessage());
        sendError('Failed to delete travel', 500, 'Database Error');
    }
}
?>
