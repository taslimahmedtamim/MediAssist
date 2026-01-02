<?php


require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/PillTracker.php';
require_once __DIR__ . '/../models/Medicine.php';

if (!$con) {
    sendError('Database connection failed', 500);
}

$tracker = new PillTracker($con);
$medicine = new Medicine($con);
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'POST':
        $data = getJsonInput();
        
        if ($action === 'record') {
            // Record pill status (taken/missed/skipped)
            $required = ['user_id', 'medicine_id', 'schedule_id', 'status'];
            $missing = validateRequired($data, $required);
            
            if (!empty($missing)) {
                sendError('Missing required fields: ' . implode(', ', $missing));
            }

            $validStatuses = ['taken', 'missed', 'skipped', 'pending'];
            if (!in_array($data['status'], $validStatuses)) {
                sendError('Invalid status. Must be: ' . implode(', ', $validStatuses));
            }

            $date = $data['date'] ?? date('Y-m-d');
            $time = $data['time'] ?? date('H:i:s');

            if ($tracker->record(
                $data['user_id'],
                $data['medicine_id'],
                $data['schedule_id'],
                $date,
                $time,
                $data['status'],
                $data['notes'] ?? null
            )) {
                sendResponse(['success' => true, 'message' => 'Pill status recorded']);
            } else {
                sendError('Failed to record pill status', 500);
            }
        }
        elseif ($action === 'generate_today') {
            // Generate tracking records for today
            if (!isset($data['user_id'])) {
                sendError('User ID required');
            }

            if ($tracker->generateTodayRecords($data['user_id'])) {
                sendResponse(['success' => true, 'message' => 'Today\'s tracking records generated']);
            } else {
                sendError('Failed to generate tracking records', 500);
            }
        }
        break;

    case 'GET':
        $userId = $_GET['user_id'] ?? null;
        
        if (!$userId) {
            sendError('User ID required');
        }

        if ($action === 'today') {
            // Generate today's records first
            $tracker->generateTodayRecords($userId);
            
            // Get today's tracking
            $tracking = $tracker->getTodayTracking($userId);
            $todaysMedicines = $medicine->getTodaysMedicines($userId);
            
            sendResponse([
                'success' => true,
                'date' => date('Y-m-d'),
                'tracking' => $tracking,
                'medicines' => $todaysMedicines
            ]);
        }
        elseif ($action === 'stats') {
            // Get adherence statistics
            $days = $_GET['days'] ?? 30;
            $stats = $tracker->getAdherenceStats($userId, $days);
            $streak = $tracker->getCurrentStreak($userId);
            
            sendResponse([
                'success' => true,
                'period_days' => $days,
                'stats' => $stats,
                'current_streak' => $streak
            ]);
        }
        elseif ($action === 'monthly') {
            // Get monthly analytics
            $month = $_GET['month'] ?? date('m');
            $year = $_GET['year'] ?? date('Y');
            
            $analytics = $tracker->getMonthlyAnalytics($userId, $month, $year);
            
            sendResponse([
                'success' => true,
                'month' => $month,
                'year' => $year,
                'analytics' => $analytics
            ]);
        }
        elseif ($action === 'alerts') {
            // Get missed pill alerts
            $alerts = $tracker->getMissedPillAlerts($userId);
            
            sendResponse([
                'success' => true,
                'alerts' => $alerts,
                'count' => count($alerts)
            ]);
        }
        elseif ($action === 'medicine_history') {
            // Get history for a specific medicine
            $medicineId = $_GET['medicine_id'] ?? null;
            if (!$medicineId) {
                sendError('Medicine ID required');
            }
            
            $days = $_GET['days'] ?? 30;
            $history = $tracker->getMedicineHistory($userId, $medicineId, $days);
            
            sendResponse([
                'success' => true,
                'medicine_id' => $medicineId,
                'history' => $history
            ]);
        }
        elseif ($action === 'dashboard') {
            // Get complete dashboard data
            $tracker->generateTodayRecords($userId);
            
            $today = $medicine->getTodaysMedicines($userId);
            $stats = $tracker->getAdherenceStats($userId, 30);
            $streak = $tracker->getCurrentStreak($userId);
            $alerts = $tracker->getMissedPillAlerts($userId);
            $monthly = $tracker->getMonthlyAnalytics($userId);
            
            // Organize today's pills by time
            $pillsByTime = [];
            foreach ($today as $pill) {
                $time = $pill['scheduled_time'];
                if (!isset($pillsByTime[$time])) {
                    $pillsByTime[$time] = [];
                }
                $pillsByTime[$time][] = $pill;
            }
            
            // Calculate summary
            $taken = 0;
            $pending = 0;
            $missed = 0;
            foreach ($today as $pill) {
                $status = $pill['status'] ?? 'pending';
                if ($status === 'taken') $taken++;
                elseif ($status === 'missed') $missed++;
                else $pending++;
            }
            
            sendResponse([
                'success' => true,
                'dashboard' => [
                    'date' => date('Y-m-d'),
                    'summary' => [
                        'total' => count($today),
                        'taken' => $taken,
                        'pending' => $pending,
                        'missed' => $missed
                    ],
                    'pills_by_time' => $pillsByTime,
                    'adherence' => [
                        'percentage' => $stats['adherence_percentage'],
                        'streak' => $streak
                    ],
                    'alerts' => $alerts,
                    'monthly_data' => $monthly
                ]
            ]);
        }
        else {
            // Default: get today's data
            $tracker->generateTodayRecords($userId);
            $tracking = $tracker->getTodayTracking($userId);
            sendResponse(['success' => true, 'tracking' => $tracking]);
        }
        break;

    default:
        sendError('Method not allowed', 405);
}
