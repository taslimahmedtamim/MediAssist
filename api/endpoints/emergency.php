<?php
// Emergency & Safety API Endpoint
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';
require_once '../models/EmergencySafety.php';

$emergency = new EmergencySafety($con);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        // =====================================================
        // EMERGENCY INFORMATION CARD
        // =====================================================
        
        case 'getEmergencyInfo':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            $userId = $_GET['user_id'] ?? null;
            
            if (!$userId) throw new Exception('User ID required');
            
            $result = $emergency->getEmergencyInfo($userId);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'getEmergencyInfoByCode':
            // Public endpoint - no user_id required, just the access code
            if ($method !== 'GET') throw new Exception('Method not allowed');
            $accessCode = $_GET['code'] ?? null;
            
            if (!$accessCode) throw new Exception('Access code required');
            
            $result = $emergency->getEmergencyInfoByCode($accessCode);
            if ($result) {
                echo json_encode(['success' => true, 'data' => $result]);
            } else {
                throw new Exception('Invalid or expired access code');
            }
            break;
            
        case 'saveEmergencyInfo':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['user_id'])) throw new Exception('User ID required');
            
            $result = $emergency->saveEmergencyInfo($data['user_id'], $data);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'generateQRCode':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            $userId = $_GET['user_id'] ?? null;
            
            if (!$userId) throw new Exception('User ID required');
            
            $result = $emergency->generateQRCodeData($userId);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'regenerateAccessCode':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['user_id'])) throw new Exception('User ID required');
            
            $result = $emergency->regenerateAccessCode($data['user_id']);
            echo json_encode(['success' => true, 'access_code' => $result]);
            break;
            
        // =====================================================
        // CAREGIVER ACCESS MODE
        // =====================================================
        
        case 'addCaregiver':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            $data = json_decode(file_get_contents('php://input'), true);
            
            $result = $emergency->addCaregiver(
                $data['patient_user_id'],
                $data['caregiver_email'],
                $data['caregiver_name'],
                $data['relationship'],
                $data['permissions'] ?? []
            );
            
            if ($result) {
                echo json_encode(['success' => true, 'id' => $result['id'], 'access_code' => $result['access_code']]);
            } else {
                throw new Exception('Failed to add caregiver');
            }
            break;
            
        case 'getCaregivers':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            $userId = $_GET['user_id'] ?? null;
            
            if (!$userId) throw new Exception('User ID required');
            
            $result = $emergency->getCaregivers($userId);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'updateCaregiver':
            if ($method !== 'PUT') throw new Exception('Method not allowed');
            $data = json_decode(file_get_contents('php://input'), true);
            
            $result = $emergency->updateCaregiver(
                $data['id'],
                $data['patient_user_id'],
                $data['permissions'] ?? null,
                $data['is_active'] ?? null
            );
            echo json_encode(['success' => $result]);
            break;
            
        case 'removeCaregiver':
            if ($method !== 'DELETE') throw new Exception('Method not allowed');
            $caregiverId = $_GET['id'] ?? null;
            $userId = $_GET['user_id'] ?? null;
            
            if (!$caregiverId || !$userId) throw new Exception('Caregiver ID and User ID required');
            
            $result = $emergency->removeCaregiver($caregiverId, $userId);
            echo json_encode(['success' => $result]);
            break;
            
        case 'verifyCaregiverAccess':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            $data = json_decode(file_get_contents('php://input'), true);
            
            $result = $emergency->verifyCaregiverAccess($data['access_code']);
            if ($result) {
                echo json_encode(['success' => true, 'data' => $result]);
            } else {
                throw new Exception('Invalid or inactive caregiver access code');
            }
            break;
            
        case 'getCaregiverPatientData':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            $accessCode = $_GET['access_code'] ?? null;
            
            if (!$accessCode) throw new Exception('Access code required');
            
            $result = $emergency->getCaregiverPatientDataByAccessCode($accessCode);
            if ($result) {
                echo json_encode(['success' => true, 'data' => $result]);
            } else {
                throw new Exception('Invalid access or insufficient permissions');
            }
            break;
            
        // =====================================================
        // EMERGENCY CONTACTS
        // =====================================================
        
        case 'getEmergencyContacts':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            $userId = $_GET['user_id'] ?? null;
            
            if (!$userId) throw new Exception('User ID required');
            
            $result = $emergency->getEmergencyContacts($userId);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'addEmergencyContact':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            $data = json_decode(file_get_contents('php://input'), true);
            
            $result = $emergency->addEmergencyContact(
                $data['user_id'],
                $data['name'],
                $data['phone'],
                $data['relationship'],
                $data['is_primary'] ?? false
            );
            
            if ($result) {
                echo json_encode(['success' => true, 'id' => $result]);
            } else {
                throw new Exception('Failed to add emergency contact');
            }
            break;
            
        case 'updateEmergencyContact':
            if ($method !== 'PUT') throw new Exception('Method not allowed');
            $data = json_decode(file_get_contents('php://input'), true);
            
            $result = $emergency->updateEmergencyContact(
                $data['id'],
                $data['user_id'],
                $data
            );
            echo json_encode(['success' => $result]);
            break;
            
        case 'deleteEmergencyContact':
            if ($method !== 'DELETE') throw new Exception('Method not allowed');
            $contactId = $_GET['id'] ?? null;
            $userId = $_GET['user_id'] ?? null;
            
            if (!$contactId || !$userId) throw new Exception('Contact ID and User ID required');
            
            $result = $emergency->deleteEmergencyContact($contactId, $userId);
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
