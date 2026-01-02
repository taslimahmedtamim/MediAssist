<?php
// Lifestyle API Endpoint - Water, Activity, Grocery
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';
require_once '../models/Lifestyle.php';

$lifestyle = new Lifestyle($con);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        // =====================================================
        // WATER INTAKE TRACKING
        // =====================================================
        
        case 'logWater':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            $data = json_decode(file_get_contents('php://input'), true);
            
            $result = $lifestyle->logWaterIntake(
                $data['user_id'],
                $data['amount_ml'],
                $data['date'] ?? null
            );
            
            if ($result) {
                echo json_encode(['success' => true, 'id' => $result]);
            } else {
                throw new Exception('Failed to log water intake');
            }
            break;
            
        case 'getDailyWater':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            $userId = $_GET['user_id'] ?? null;
            $date = $_GET['date'] ?? null;
            
            if (!$userId) throw new Exception('User ID required');
            
            $result = $lifestyle->getDailyWaterIntake($userId, $date);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'getWaterHistory':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            $userId = $_GET['user_id'] ?? null;
            $days = $_GET['days'] ?? 7;
            
            if (!$userId) throw new Exception('User ID required');
            
            $result = $lifestyle->getWaterIntakeHistory($userId, $days);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'getWaterLogs':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            $userId = $_GET['user_id'] ?? null;
            $date = $_GET['date'] ?? null;
            
            if (!$userId) throw new Exception('User ID required');
            
            $result = $lifestyle->getWaterLogs($userId, $date);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'deleteWaterLog':
            if ($method !== 'DELETE') throw new Exception('Method not allowed');
            $logId = $_GET['id'] ?? null;
            $userId = $_GET['user_id'] ?? null;
            
            if (!$logId || !$userId) throw new Exception('Log ID and User ID required');
            
            $result = $lifestyle->deleteWaterLog($logId, $userId);
            echo json_encode(['success' => $result]);
            break;
            
        // =====================================================
        // ACTIVITY / EXERCISE TRACKING
        // =====================================================
        
        case 'logActivity':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            $data = json_decode(file_get_contents('php://input'), true);
            
            $result = $lifestyle->logActivity(
                $data['user_id'],
                $data['activity_type'],
                $data['duration_minutes'],
                $data['intensity'] ?? 'moderate',
                $data['calories_burned'] ?? null,
                $data['notes'] ?? null,
                $data['date'] ?? null
            );
            
            if ($result) {
                echo json_encode(['success' => true, 'id' => $result]);
            } else {
                throw new Exception('Failed to log activity');
            }
            break;
            
        case 'getDailyActivity':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            $userId = $_GET['user_id'] ?? null;
            $date = $_GET['date'] ?? null;
            
            if (!$userId) throw new Exception('User ID required');
            
            $result = $lifestyle->getDailyActivity($userId, $date);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'getActivityHistory':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            $userId = $_GET['user_id'] ?? null;
            $days = $_GET['days'] ?? 7;
            
            if (!$userId) throw new Exception('User ID required');
            
            $result = $lifestyle->getActivityHistory($userId, $days);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'getActivityTypes':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            
            $result = $lifestyle->getActivityTypes();
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'deleteActivity':
            if ($method !== 'DELETE') throw new Exception('Method not allowed');
            $activityId = $_GET['id'] ?? null;
            $userId = $_GET['user_id'] ?? null;
            
            if (!$activityId || !$userId) throw new Exception('Activity ID and User ID required');
            
            $result = $lifestyle->deleteActivity($activityId, $userId);
            echo json_encode(['success' => $result]);
            break;
            
        // =====================================================
        // CALORIE BALANCE
        // =====================================================
        
        case 'getCalorieBalance':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            $userId = $_GET['user_id'] ?? null;
            $date = $_GET['date'] ?? null;
            
            if (!$userId) throw new Exception('User ID required');
            
            $result = $lifestyle->getDailyCalorieBalance($userId, $date);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        // =====================================================
        // GROCERY LIST
        // =====================================================
        
        case 'generateGroceryList':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            $data = json_decode(file_get_contents('php://input'), true);
            
            $result = $lifestyle->generateGroceryList(
                $data['user_id'],
                $data['week_start_date'] ?? null
            );
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'getGroceryList':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            $userId = $_GET['user_id'] ?? null;
            $weekStartDate = $_GET['week_start_date'] ?? null;
            
            if (!$userId) throw new Exception('User ID required');
            
            $result = $lifestyle->getGroceryList($userId, $weekStartDate);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'addGroceryItem':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            $data = json_decode(file_get_contents('php://input'), true);
            
            $result = $lifestyle->addGroceryItem(
                $data['user_id'],
                $data['item_name'],
                $data['quantity'] ?? null,
                $data['category'] ?? 'Other',
                $data['week_start_date'] ?? null
            );
            
            if ($result) {
                echo json_encode(['success' => true, 'id' => $result]);
            } else {
                throw new Exception('Failed to add grocery item');
            }
            break;
            
        case 'updateGroceryItem':
            if ($method !== 'PUT') throw new Exception('Method not allowed');
            $data = json_decode(file_get_contents('php://input'), true);
            
            $result = $lifestyle->updateGroceryItem(
                $data['id'],
                $data['user_id'],
                $data['is_purchased']
            );
            echo json_encode(['success' => $result]);
            break;
            
        case 'deleteGroceryItem':
            if ($method !== 'DELETE') throw new Exception('Method not allowed');
            $itemId = $_GET['id'] ?? null;
            $userId = $_GET['user_id'] ?? null;
            
            if (!$itemId || !$userId) throw new Exception('Item ID and User ID required');
            
            $result = $lifestyle->deleteGroceryItem($itemId, $userId);
            echo json_encode(['success' => $result]);
            break;
            
        // =====================================================
        // DIET SUGGESTIONS
        // =====================================================
        
        case 'getFoodRestrictions':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            $userId = $_GET['user_id'] ?? null;
            
            if (!$userId) throw new Exception('User ID required');
            
            $result = $lifestyle->getFoodRestrictions($userId);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'getDietSuggestions':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            $userId = $_GET['user_id'] ?? null;
            
            if (!$userId) throw new Exception('User ID required');
            
            $result = $lifestyle->getDietSuggestions($userId);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

mysqli_close($con);
