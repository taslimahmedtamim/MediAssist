<?php


require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Medicine.php';

if (!$con) {
    sendError('Database connection failed', 500);
}

$medicine = new Medicine($con);
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'POST':
        $data = getJsonInput();
        
        if ($action === 'create') {
            // Add new medicine
            $required = ['user_id', 'name', 'start_date'];
            $missing = validateRequired($data, $required);
            
            if (!empty($missing)) {
                sendError('Missing required fields: ' . implode(', ', $missing));
            }

            $medicine->user_id = $data['user_id'];
            $medicine->name = sanitize($data['name']);
            $medicine->dosage = sanitize($data['dosage'] ?? '');
            $medicine->dose_type = $data['dose_type'] ?? 'tablet';
            $medicine->frequency = $data['frequency'] ?? 'once';
            $medicine->duration_days = $data['duration_days'] ?? null;
            $medicine->start_date = $data['start_date'];
            $medicine->end_date = $data['end_date'] ?? null;
            $medicine->instructions = sanitize($data['instructions'] ?? '');

            if ($medicine->create()) {
                // Add schedules if provided
                if (isset($data['schedules']) && is_array($data['schedules'])) {
                    foreach ($data['schedules'] as $schedule) {
                        $medicine->addSchedule(
                            $medicine->id,
                            $schedule['time'],
                            $schedule['dose_amount'] ?? $medicine->dosage,
                            $schedule['meal_relation'] ?? 'anytime'
                        );
                    }
                }

                sendResponse([
                    'success' => true,
                    'message' => 'Medicine added successfully',
                    'medicine_id' => $medicine->id
                ], 201);
            } else {
                sendError('Failed to add medicine', 500);
            }
        }
        elseif ($action === 'add_schedule') {
            // Add schedule to existing medicine
            $required = ['medicine_id', 'time'];
            $missing = validateRequired($data, $required);
            
            if (!empty($missing)) {
                sendError('Missing required fields: ' . implode(', ', $missing));
            }

            if ($medicine->addSchedule(
                $data['medicine_id'],
                $data['time'],
                $data['dose_amount'] ?? '',
                $data['meal_relation'] ?? 'anytime'
            )) {
                sendResponse(['success' => true, 'message' => 'Schedule added']);
            } else {
                sendError('Failed to add schedule', 500);
            }
        }
        break;

    case 'GET':
        $userId = $_GET['user_id'] ?? null;
        
        if ($action === 'today') {
            // Get today's medicines
            if (!$userId) {
                sendError('User ID required');
            }
            
            $medicines = $medicine->getTodaysMedicines($userId);
            sendResponse(['success' => true, 'medicines' => $medicines]);
        }
        elseif ($action === 'schedules') {
            // Get schedules for a medicine
            $medicineId = $_GET['medicine_id'] ?? null;
            if (!$medicineId) {
                sendError('Medicine ID required');
            }
            
            $schedules = $medicine->getSchedules($medicineId);
            sendResponse(['success' => true, 'schedules' => $schedules]);
        }
        elseif ($action === 'single') {
            // Get single medicine
            $medicineId = $_GET['medicine_id'] ?? null;
            if (!$medicineId) {
                sendError('Medicine ID required');
            }
            
            $med = $medicine->getById($medicineId);
            if ($med) {
                $med['schedules'] = $medicine->getSchedules($medicineId);
                sendResponse(['success' => true, 'medicine' => $med]);
            } else {
                sendError('Medicine not found', 404);
            }
        }
        else {
            // Get all medicines for user
            if (!$userId) {
                sendError('User ID required');
            }
            
            $activeOnly = ($_GET['active_only'] ?? 'true') === 'true';
            $medicines = $medicine->getByUser($userId, $activeOnly);
            
            // Get schedules for each medicine
            foreach ($medicines as &$med) {
                $med['schedules'] = $medicine->getSchedules($med['id']);
            }
            
            sendResponse(['success' => true, 'medicines' => $medicines]);
        }
        break;

    case 'PUT':
        $data = getJsonInput();
        
        if (!isset($data['medicine_id']) || !isset($data['user_id'])) {
            sendError('Medicine ID and User ID required');
        }

        $medicine->id = $data['medicine_id'];
        $medicine->user_id = $data['user_id'];
        $medicine->name = sanitize($data['name'] ?? '');
        $medicine->dosage = sanitize($data['dosage'] ?? '');
        $medicine->dose_type = $data['dose_type'] ?? 'tablet';
        $medicine->frequency = $data['frequency'] ?? 'once';
        $medicine->duration_days = $data['duration_days'] ?? null;
        $medicine->end_date = $data['end_date'] ?? null;
        $medicine->instructions = sanitize($data['instructions'] ?? '');
        $medicine->is_active = $data['is_active'] ?? true;

        if ($medicine->update()) {
            // Update schedules if provided
            if (isset($data['schedules']) && is_array($data['schedules'])) {
                $medicine->deleteSchedules($medicine->id);
                foreach ($data['schedules'] as $schedule) {
                    $medicine->addSchedule(
                        $medicine->id,
                        $schedule['time'],
                        $schedule['dose_amount'] ?? $medicine->dosage,
                        $schedule['meal_relation'] ?? 'anytime'
                    );
                }
            }

            sendResponse(['success' => true, 'message' => 'Medicine updated successfully']);
        } else {
            sendError('Update failed', 500);
        }
        break;

    case 'DELETE':
        $medicineId = $_GET['medicine_id'] ?? null;
        $userId = $_GET['user_id'] ?? null;
        
        if (!$medicineId || !$userId) {
            sendError('Medicine ID and User ID required');
        }

        if ($medicine->delete($medicineId, $userId)) {
            sendResponse(['success' => true, 'message' => 'Medicine deleted successfully']);
        } else {
            sendError('Delete failed', 500);
        }
        break;

    default:
        sendError('Method not allowed', 405);
}
