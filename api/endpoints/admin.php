<?php
// Admin & System API Endpoint
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';
require_once '../models/AdminSystem.php';

$admin = new AdminSystem($con);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Simple admin check (in production, use proper JWT or session auth)
function checkAdminAuth() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    // For demo purposes - in production, implement proper auth
    return true;
}

try {
    switch ($action) {
        // =====================================================
        // AUDIT LOG
        // =====================================================
        
        case 'logAction':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            $data = json_decode(file_get_contents('php://input'), true);
            
            $result = $admin->logAction(
                $data['user_id'],
                $data['action'],
                $data['entity_type'],
                $data['entity_id'] ?? null,
                $data['details'] ?? null
            );
            
            if ($result) {
                echo json_encode(['success' => true, 'id' => $result]);
            } else {
                throw new Exception('Failed to log action');
            }
            break;
            
        case 'getAuditLog':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            if (!checkAdminAuth()) throw new Exception('Unauthorized');
            
            $filters = [
                'user_id' => $_GET['user_id'] ?? null,
                'action' => $_GET['action'] ?? null,
                'entity_type' => $_GET['entity_type'] ?? null,
                'date_from' => $_GET['date_from'] ?? null,
                'date_to' => $_GET['date_to'] ?? null
            ];
            $limit = $_GET['limit'] ?? 100;
            $offset = $_GET['offset'] ?? 0;
            
            $result = $admin->getAuditLog($filters, $limit, $offset);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'getUserActivityLog':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            $userId = $_GET['user_id'] ?? null;
            $limit = $_GET['limit'] ?? 50;
            
            if (!$userId) throw new Exception('User ID required');
            
            $result = $admin->getUserActivityLog($userId, $limit);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        // =====================================================
        // ANALYTICS
        // =====================================================
        
        case 'getAnalyticsSummary':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            if (!checkAdminAuth()) throw new Exception('Unauthorized');
            
            $result = $admin->getAnalyticsSummary();
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'getUserAnalytics':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            $userId = $_GET['user_id'] ?? null;
            
            if (!$userId) throw new Exception('User ID required');
            
            $result = $admin->getUserAnalytics($userId);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'getConditionStatistics':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            if (!checkAdminAuth()) throw new Exception('Unauthorized');
            
            $result = $admin->getConditionStatistics();
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'getAdherenceStatistics':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            if (!checkAdminAuth()) throw new Exception('Unauthorized');
            
            $result = $admin->getAdherenceStatistics();
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'getMedicineUsageStats':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            if (!checkAdminAuth()) throw new Exception('Unauthorized');
            
            $result = $admin->getMedicineUsageStats();
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        // =====================================================
        // DATA EXPORT / IMPORT
        // =====================================================
        
        case 'exportUserData':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            $userId = $_GET['user_id'] ?? null;
            $format = $_GET['format'] ?? 'json';
            
            if (!$userId) throw new Exception('User ID required');
            
            $result = $admin->exportUserData($userId, $format);
            
            if ($format === 'json') {
                echo json_encode(['success' => true, 'data' => $result]);
            } else {
                // Return as downloadable file
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="user_data_' . $userId . '.csv"');
                echo $result;
                exit;
            }
            break;
            
        case 'importUserData':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['user_id']) || !isset($data['data'])) {
                throw new Exception('User ID and data required');
            }
            
            $result = $admin->importUserData($data['user_id'], $data['data']);
            echo json_encode(['success' => true, 'result' => $result]);
            break;
            
        case 'generateBackup':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            if (!checkAdminAuth()) throw new Exception('Unauthorized');
            
            $data = json_decode(file_get_contents('php://input'), true);
            $userId = $data['user_id'] ?? null;
            
            $result = $admin->generateBackup($userId);
            echo json_encode(['success' => true, 'backup' => $result]);
            break;
            
        // =====================================================
        // OCR LEARNING / CORRECTIONS
        // =====================================================
        
        case 'recordOCRCorrection':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            $data = json_decode(file_get_contents('php://input'), true);
            
            $result = $admin->recordOCRCorrection(
                $data['report_id'] ?? null,
                $data['original_text'],
                $data['corrected_text'],
                $data['field_type']
            );
            
            if ($result) {
                echo json_encode(['success' => true, 'id' => $result]);
            } else {
                throw new Exception('Failed to record OCR correction');
            }
            break;
            
        case 'getOCRCorrections':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            $fieldType = $_GET['field_type'] ?? null;
            $limit = $_GET['limit'] ?? 100;
            
            $result = $admin->getOCRCorrections($fieldType, $limit);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'applyOCRCorrections':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['text'])) throw new Exception('Text required');
            
            $result = $admin->applyOCRCorrections($data['text']);
            echo json_encode(['success' => true, 'corrected_text' => $result]);
            break;
            
        // =====================================================
        // SYSTEM HEALTH
        // =====================================================
        
        case 'getSystemHealth':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            if (!checkAdminAuth()) throw new Exception('Unauthorized');
            
            $result = $admin->getSystemHealth();
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'getDatabaseStats':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            if (!checkAdminAuth()) throw new Exception('Unauthorized');
            
            $result = $admin->getDatabaseStats();
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        // =====================================================
        // USER MANAGEMENT (Admin)
        // =====================================================
        
        case 'getAllUsers':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            if (!checkAdminAuth()) throw new Exception('Unauthorized');
            
            $limit = $_GET['limit'] ?? 50;
            $offset = $_GET['offset'] ?? 0;
            $search = $_GET['search'] ?? null;
            
            $result = $admin->getAllUsers($limit, $offset, $search);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'getUserDetails':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            if (!checkAdminAuth()) throw new Exception('Unauthorized');
            
            $userId = $_GET['user_id'] ?? null;
            if (!$userId) throw new Exception('User ID required');
            
            $result = $admin->getUserDetails($userId);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'toggleUserStatus':
            if ($method !== 'PUT') throw new Exception('Method not allowed');
            if (!checkAdminAuth()) throw new Exception('Unauthorized');
            
            $data = json_decode(file_get_contents('php://input'), true);
            $result = $admin->toggleUserStatus($data['user_id'], $data['is_active']);
            echo json_encode(['success' => $result]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

mysqli_close($con);
