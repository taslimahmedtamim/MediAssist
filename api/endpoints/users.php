<?php
/**
 * User API Endpoints
 * Handles user registration, login, and profile management
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/User.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    sendError('Database connection failed', 500);
}

$user = new User($db);
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'POST':
        $data = getJsonInput();
        
        if ($action === 'register') {
            // Register new user
            $required = ['email', 'password', 'full_name'];
            $missing = validateRequired($data, $required);
            
            if (!empty($missing)) {
                sendError('Missing required fields: ' . implode(', ', $missing));
            }

            $user->email = sanitize($data['email']);
            $user->password = $data['password'];
            $user->full_name = sanitize($data['full_name']);
            $user->phone = sanitize($data['phone'] ?? '');
            $user->date_of_birth = $data['date_of_birth'] ?? null;
            $user->gender = $data['gender'] ?? null;
            $user->height_cm = $data['height_cm'] ?? null;
            $user->weight_kg = $data['weight_kg'] ?? null;

            // Check if email exists
            if ($user->emailExists()) {
                sendError('Email already registered');
            }

            if ($user->register()) {
                sendResponse([
                    'success' => true,
                    'message' => 'User registered successfully',
                    'user_id' => $user->id
                ], 201);
            } else {
                sendError('Registration failed', 500);
            }
        }
        elseif ($action === 'login') {
            // Login user
            $required = ['email', 'password'];
            $missing = validateRequired($data, $required);
            
            if (!empty($missing)) {
                sendError('Missing required fields: ' . implode(', ', $missing));
            }

            $user->email = sanitize($data['email']);
            $user->password = $data['password'];

            if ($user->login()) {
                // In production, generate JWT token here
                sendResponse([
                    'success' => true,
                    'message' => 'Login successful',
                    'user' => [
                        'id' => $user->id,
                        'email' => $user->email,
                        'full_name' => $user->full_name
                    ]
                ]);
            } else {
                sendError('Invalid email or password', 401);
            }
        }
        elseif ($action === 'add_condition') {
            // Add health condition to user
            $required = ['user_id', 'condition_id'];
            $missing = validateRequired($data, $required);
            
            if (!empty($missing)) {
                sendError('Missing required fields: ' . implode(', ', $missing));
            }

            if ($user->addHealthCondition(
                $data['user_id'],
                $data['condition_id'],
                $data['diagnosed_date'] ?? null,
                $data['notes'] ?? null
            )) {
                sendResponse(['success' => true, 'message' => 'Health condition added']);
            } else {
                sendError('Failed to add health condition', 500);
            }
        }
        break;

    case 'GET':
        $userId = $_GET['user_id'] ?? null;
        
        if (!$userId) {
            sendError('User ID required');
        }

        if ($action === 'profile') {
            $profile = $user->getById($userId);
            if ($profile) {
                sendResponse(['success' => true, 'user' => $profile]);
            } else {
                sendError('User not found', 404);
            }
        }
        elseif ($action === 'conditions') {
            $conditions = $user->getHealthConditions($userId);
            sendResponse(['success' => true, 'conditions' => $conditions]);
        }
        else {
            $profile = $user->getById($userId);
            $conditions = $user->getHealthConditions($userId);
            sendResponse([
                'success' => true,
                'user' => $profile,
                'health_conditions' => $conditions
            ]);
        }
        break;

    case 'PUT':
        $data = getJsonInput();
        
        if (!isset($data['user_id'])) {
            sendError('User ID required');
        }

        $user->id = $data['user_id'];
        $user->full_name = sanitize($data['full_name'] ?? '');
        $user->phone = sanitize($data['phone'] ?? '');
        $user->date_of_birth = $data['date_of_birth'] ?? null;
        $user->gender = $data['gender'] ?? null;
        $user->height_cm = $data['height_cm'] ?? null;
        $user->weight_kg = $data['weight_kg'] ?? null;

        if ($user->update()) {
            sendResponse(['success' => true, 'message' => 'Profile updated successfully']);
        } else {
            sendError('Update failed', 500);
        }
        break;

    case 'DELETE':
        $userId = $_GET['user_id'] ?? null;
        $conditionId = $_GET['condition_id'] ?? null;
        
        if ($action === 'remove_condition' && $userId && $conditionId) {
            if ($user->removeHealthCondition($userId, $conditionId)) {
                sendResponse(['success' => true, 'message' => 'Health condition removed']);
            } else {
                sendError('Failed to remove health condition', 500);
            }
        } else {
            sendError('Invalid request');
        }
        break;

    default:
        sendError('Method not allowed', 405);
}
