<?php
// Health Features API Endpoint
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';
require_once '../models/HealthFeatures.php';

$healthFeatures = new HealthFeatures($con);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        // =====================================================
        // MISSED DOSE INTELLIGENCE
        // =====================================================
        
        case 'getMissedDoses':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            $userId = $_GET['user_id'] ?? null;
            $date = $_GET['date'] ?? null;
            
            if (!$userId) throw new Exception('User ID required');
            
            $result = $healthFeatures->getMissedDoses($userId, $date);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'updateMissedDoseReason':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            $data = json_decode(file_get_contents('php://input'), true);
            
            $result = $healthFeatures->updateMissedDoseReason(
                $data['tracking_id'],
                $data['reason'],
                $data['recovery_action'] ?? null
            );
            echo json_encode(['success' => $result]);
            break;
            
        case 'getRecoverySuggestion':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            $medicineId = $_GET['medicine_id'] ?? null;
            $missedTime = $_GET['missed_time'] ?? null;
            
            if (!$medicineId || !$missedTime) throw new Exception('Medicine ID and missed time required');
            
            $result = $healthFeatures->getRecoverySuggestion($medicineId, $missedTime);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        // =====================================================
        // MEDICINE INTERACTION CHECKER
        // =====================================================
        
        case 'checkInteractions':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            $userId = $_GET['user_id'] ?? null;
            
            if (!$userId) throw new Exception('User ID required');
            
            $result = $healthFeatures->checkInteractions($userId);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        // =====================================================
        // REFILL & EXPIRY ALERTS
        // =====================================================
        
        case 'getRefillAlerts':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            $userId = $_GET['user_id'] ?? null;
            
            if (!$userId) throw new Exception('User ID required');
            
            $result = $healthFeatures->getRefillAlerts($userId);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'updateMedicinePills':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            $data = json_decode(file_get_contents('php://input'), true);
            
            $result = $healthFeatures->updateMedicinePills(
                $data['medicine_id'],
                $data['remaining_pills'],
                $data['total_pills'] ?? null,
                $data['expiry_date'] ?? null
            );
            echo json_encode(['success' => $result]);
            break;
            
        // =====================================================
        // MEDICINE HISTORY TIMELINE
        // =====================================================
        
        case 'getMedicineHistory':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            $medicineId = $_GET['medicine_id'] ?? null;
            
            if (!$medicineId) throw new Exception('Medicine ID required');
            
            $result = $healthFeatures->getMedicineHistory($medicineId);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'getUserMedicineTimeline':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            $userId = $_GET['user_id'] ?? null;
            
            if (!$userId) throw new Exception('User ID required');
            
            $result = $healthFeatures->getUserMedicineTimeline($userId);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        // =====================================================
        // SIDE EFFECTS REPORTING
        // =====================================================
        
        case 'reportSideEffect':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            $data = json_decode(file_get_contents('php://input'), true);
            
            $result = $healthFeatures->reportSideEffect(
                $data['user_id'],
                $data['medicine_id'],
                $data['effect_type'],
                $data['severity'],
                $data['description'] ?? null,
                $data['onset_date'] ?? null
            );
            
            if ($result) {
                echo json_encode(['success' => true, 'id' => $result]);
            } else {
                throw new Exception('Failed to report side effect');
            }
            break;
            
        case 'getUserSideEffects':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            $userId = $_GET['user_id'] ?? null;
            $medicineId = $_GET['medicine_id'] ?? null;
            
            if (!$userId) throw new Exception('User ID required');
            
            $result = $healthFeatures->getUserSideEffects($userId, $medicineId);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'getCommonSideEffects':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            $saltName = $_GET['salt_name'] ?? null;
            
            if (!$saltName) throw new Exception('Salt name required');
            
            $result = $healthFeatures->getCommonSideEffects($saltName);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'updateSideEffect':
            if ($method !== 'PUT') throw new Exception('Method not allowed');
            $data = json_decode(file_get_contents('php://input'), true);
            
            $result = $healthFeatures->updateSideEffect($data['id'], $data);
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
