<?php
// Notifications API Endpoint
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';
require_once '../models/NotificationEngine.php';

$notifications = new NotificationEngine($con);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        // =====================================================
        // NOTIFICATIONS
        // =====================================================
        
        case 'getNotifications':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            $userId = $_GET['user_id'] ?? null;
            $unreadOnly = isset($_GET['unread_only']) ? filter_var($_GET['unread_only'], FILTER_VALIDATE_BOOLEAN) : false;
            $limit = $_GET['limit'] ?? 50;
            
            if (!$userId) throw new Exception('User ID required');
            
            $result = $notifications->getNotifications($userId, $unreadOnly, $limit);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'getUnreadCount':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            $userId = $_GET['user_id'] ?? null;
            
            if (!$userId) throw new Exception('User ID required');
            
            $result = $notifications->getUnreadCount($userId);
            echo json_encode(['success' => true, 'count' => $result]);
            break;
            
        case 'markAsRead':
            if ($method !== 'PUT') throw new Exception('Method not allowed');
            $data = json_decode(file_get_contents('php://input'), true);
            
            $result = $notifications->markAsRead($data['id'], $data['user_id']);
            echo json_encode(['success' => $result]);
            break;
            
        case 'markAllAsRead':
            if ($method !== 'PUT') throw new Exception('Method not allowed');
            $data = json_decode(file_get_contents('php://input'), true);
            
            $result = $notifications->markAllAsRead($data['user_id']);
            echo json_encode(['success' => $result]);
            break;
            
        case 'deleteNotification':
            if ($method !== 'DELETE') throw new Exception('Method not allowed');
            $notificationId = $_GET['id'] ?? null;
            $userId = $_GET['user_id'] ?? null;
            
            if (!$notificationId || !$userId) throw new Exception('Notification ID and User ID required');
            
            $result = $notifications->deleteNotification($notificationId, $userId);
            echo json_encode(['success' => $result]);
            break;
            
        case 'clearOldNotifications':
            if ($method !== 'DELETE') throw new Exception('Method not allowed');
            $userId = $_GET['user_id'] ?? null;
            $daysOld = $_GET['days'] ?? 30;
            
            if (!$userId) throw new Exception('User ID required');
            
            $result = $notifications->clearOldNotifications($userId, $daysOld);
            echo json_encode(['success' => true, 'deleted' => $result]);
            break;
            
        // =====================================================
        // NOTIFICATION PREFERENCES
        // =====================================================
        
        case 'getPreferences':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            $userId = $_GET['user_id'] ?? null;
            
            if (!$userId) throw new Exception('User ID required');
            
            $result = $notifications->getPreferences($userId);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'updatePreferences':
            if ($method !== 'PUT') throw new Exception('Method not allowed');
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['user_id'])) throw new Exception('User ID required');
            
            $userId = $data['user_id'];
            unset($data['user_id']);
            
            $result = $notifications->updatePreferences($userId, $data);
            echo json_encode(['success' => $result]);
            break;
            
        // =====================================================
        // TRIGGER NOTIFICATIONS
        // =====================================================
        
        case 'triggerMissedDoseAlert':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            $data = json_decode(file_get_contents('php://input'), true);
            
            $result = $notifications->triggerMissedDoseAlert(
                $data['user_id'],
                $data['medicine_name'],
                $data['scheduled_time']
            );
            echo json_encode(['success' => true, 'notification_id' => $result]);
            break;
            
        case 'triggerRefillAlert':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            $data = json_decode(file_get_contents('php://input'), true);
            
            $result = $notifications->triggerRefillAlert(
                $data['user_id'],
                $data['medicine_name'],
                $data['remaining_pills']
            );
            echo json_encode(['success' => true, 'notification_id' => $result]);
            break;
            
        case 'triggerExpiryAlert':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            $data = json_decode(file_get_contents('php://input'), true);
            
            $result = $notifications->triggerExpiryAlert(
                $data['user_id'],
                $data['medicine_name'],
                $data['expiry_date']
            );
            echo json_encode(['success' => true, 'notification_id' => $result]);
            break;
            
        case 'triggerInteractionAlert':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            $data = json_decode(file_get_contents('php://input'), true);
            
            $result = $notifications->triggerInteractionAlert(
                $data['user_id'],
                $data['medicine1'],
                $data['medicine2'],
                $data['severity']
            );
            echo json_encode(['success' => true, 'notification_id' => $result]);
            break;
            
        case 'triggerAbnormalLabAlert':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            $data = json_decode(file_get_contents('php://input'), true);
            
            $result = $notifications->triggerAbnormalLabAlert(
                $data['user_id'],
                $data['parameter_name'],
                $data['value'],
                $data['status']
            );
            echo json_encode(['success' => true, 'notification_id' => $result]);
            break;
            
        // =====================================================
        // WEEKLY SUMMARY
        // =====================================================
        
        case 'generateWeeklySummary':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['user_id'])) throw new Exception('User ID required');
            
            $result = $notifications->generateWeeklySummary($data['user_id']);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'getWeeklySummary':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            $userId = $_GET['user_id'] ?? null;
            $weekStartDate = $_GET['week_start_date'] ?? null;
            
            if (!$userId) throw new Exception('User ID required');
            
            $result = $notifications->getWeeklySummary($userId, $weekStartDate);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'getWeeklySummaryHistory':
            if ($method !== 'GET') throw new Exception('Method not allowed');
            $userId = $_GET['user_id'] ?? null;
            $limit = $_GET['limit'] ?? 10;
            
            if (!$userId) throw new Exception('User ID required');
            
            $result = $notifications->getWeeklySummaryHistory($userId, $limit);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        // =====================================================
        // PUSH NOTIFICATIONS (if enabled)
        // =====================================================
        
        case 'registerPushToken':
            if ($method !== 'POST') throw new Exception('Method not allowed');
            $data = json_decode(file_get_contents('php://input'), true);
            
            $result = $notifications->registerPushToken(
                $data['user_id'],
                $data['token'],
                $data['platform'] ?? 'web'
            );
            echo json_encode(['success' => $result]);
            break;
            
        case 'unregisterPushToken':
            if ($method !== 'DELETE') throw new Exception('Method not allowed');
            $userId = $_GET['user_id'] ?? null;
            $token = $_GET['token'] ?? null;
            
            if (!$userId || !$token) throw new Exception('User ID and token required');
            
            $result = $notifications->unregisterPushToken($userId, $token);
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
