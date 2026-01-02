<?php


require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/DietPlan.php';

if (!$con) {
    sendError('Database connection failed', 500);
}

$dietPlan = new DietPlan($con);
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'POST':
        $data = getJsonInput();
        
        if ($action === 'create') {
            
            $required = ['user_id', 'plan_name', 'target_calories'];
            $missing = validateRequired($data, $required);
            
            if (!empty($missing)) {
                sendError('Missing required fields: ' . implode(', ', $missing));
            }

            $dietPlan->user_id = $data['user_id'];
            $dietPlan->plan_name = sanitize($data['plan_name']);
            $dietPlan->target_calories = $data['target_calories'];
            $dietPlan->target_protein_g = $data['target_protein_g'] ?? null;
            $dietPlan->target_carbs_g = $data['target_carbs_g'] ?? null;
            $dietPlan->target_fat_g = $data['target_fat_g'] ?? null;
            $dietPlan->condition_focus = $data['condition_focus'] ?? null;
            $dietPlan->start_date = $data['start_date'] ?? date('Y-m-d');
            $dietPlan->end_date = $data['end_date'] ?? null;

            if ($dietPlan->create()) {
                sendResponse([
                    'success' => true,
                    'message' => 'Diet plan created successfully',
                    'plan_id' => $dietPlan->id
                ], 201);
            } else {
                sendError('Failed to create diet plan', 500);
            }
        }
        elseif ($action === 'add_meal') {
            
            $required = ['plan_id', 'meal_type', 'meal_name'];
            $missing = validateRequired($data, $required);
            
            if (!empty($missing)) {
                sendError('Missing required fields: ' . implode(', ', $missing));
            }

            $mealData = [
                'meal_type' => $data['meal_type'],
                'day_of_week' => $data['day_of_week'] ?? null,
                'meal_name' => sanitize($data['meal_name']),
                'description' => sanitize($data['description'] ?? ''),
                'calories' => $data['calories'] ?? 0,
                'protein_g' => $data['protein_g'] ?? 0,
                'carbs_g' => $data['carbs_g'] ?? 0,
                'fat_g' => $data['fat_g'] ?? 0,
                'fiber_g' => $data['fiber_g'] ?? 0,
                'sodium_mg' => $data['sodium_mg'] ?? 0
            ];

            if ($dietPlan->addMeal($data['plan_id'], $mealData)) {
                sendResponse(['success' => true, 'message' => 'Meal added successfully']);
            } else {
                sendError('Failed to add meal', 500);
            }
        }
        elseif ($action === 'add_restricted') {
            // Add restricted food
            $required = ['user_id', 'food_name'];
            $missing = validateRequired($data, $required);
            
            if (!empty($missing)) {
                sendError('Missing required fields: ' . implode(', ', $missing));
            }

            if ($dietPlan->addRestrictedFood(
                $data['user_id'],
                sanitize($data['food_name']),
                sanitize($data['reason'] ?? ''),
                $data['severity'] ?? 'avoid'
            )) {
                sendResponse(['success' => true, 'message' => 'Restricted food added']);
            } else {
                sendError('Failed to add restricted food', 500);
            }
        }
        elseif ($action === 'generate') {
            // Generate meal plan based on condition
            $required = ['user_id', 'condition', 'target_calories'];
            $missing = validateRequired($data, $required);
            
            if (!empty($missing)) {
                sendError('Missing required fields: ' . implode(', ', $missing));
            }

            $generatedPlan = $dietPlan->generateMealPlan(
                $data['user_id'],
                $data['condition'],
                $data['target_calories']
            );

            sendResponse([
                'success' => true,
                'message' => 'Meal plan generated',
                'meal_plan' => $generatedPlan
            ]);
        }
        break;

    case 'GET':
        $userId = $_GET['user_id'] ?? null;
        
        if ($action === 'active') {
            // Get active diet plan with doctor info
            if (!$userId) {
                sendError('User ID required');
            }
            
            $plan = $dietPlan->getActivePlanWithDoctor($userId);
            if ($plan) {
                $plan['meals'] = $dietPlan->getMeals($plan['id']);
            }
            sendResponse(['success' => true, 'plan' => $plan]);
        }
        elseif ($action === 'meals') {
            // Get meals for a plan
            $planId = $_GET['plan_id'] ?? null;
            if (!$planId) {
                sendError('Plan ID required');
            }
            
            $dayOfWeek = $_GET['day'] ?? null;
            $meals = $dietPlan->getMeals($planId, $dayOfWeek);
            sendResponse(['success' => true, 'meals' => $meals]);
        }
        elseif ($action === 'restricted') {
            // Get restricted foods for user
            if (!$userId) {
                sendError('User ID required');
            }
            
            $restricted = $dietPlan->getRestrictedFoods($userId);
            sendResponse(['success' => true, 'restricted_foods' => $restricted]);
        }
        elseif ($action === 'foods') {
            // Search foods
            $keyword = $_GET['keyword'] ?? '';
            $condition = $_GET['condition'] ?? null;
            
            if ($condition) {
                $foods = $dietPlan->getFoodsForCondition($condition);
            } elseif ($keyword) {
                $foods = $dietPlan->searchFoods($keyword);
            } else {
                $foods = [];
            }
            
            sendResponse(['success' => true, 'foods' => $foods]);
        }
        elseif ($action === 'conditions') {
            // Get all health conditions
            $conditions = $dietPlan->getHealthConditions();
            sendResponse(['success' => true, 'conditions' => $conditions]);
        }
        elseif ($action === 'single') {
            // Get single diet plan
            $planId = $_GET['plan_id'] ?? null;
            if (!$planId) {
                sendError('Plan ID required');
            }
            
            $plan = $dietPlan->getById($planId);
            if ($plan) {
                $plan['meals'] = $dietPlan->getMeals($planId);
                sendResponse(['success' => true, 'plan' => $plan]);
            } else {
                sendError('Diet plan not found', 404);
            }
        }
        else {
            // Get all diet plans for user
            if (!$userId) {
                sendError('User ID required');
            }
            
            $plans = $dietPlan->getByUser($userId);
            sendResponse(['success' => true, 'plans' => $plans]);
        }
        break;

    case 'PUT':
        $data = getJsonInput();
        
        if (!isset($data['plan_id']) || !isset($data['user_id'])) {
            sendError('Plan ID and User ID required');
        }

        $dietPlan->id = $data['plan_id'];
        $dietPlan->user_id = $data['user_id'];
        $dietPlan->plan_name = sanitize($data['plan_name'] ?? '');
        $dietPlan->target_calories = $data['target_calories'] ?? null;
        $dietPlan->target_protein_g = $data['target_protein_g'] ?? null;
        $dietPlan->target_carbs_g = $data['target_carbs_g'] ?? null;
        $dietPlan->target_fat_g = $data['target_fat_g'] ?? null;
        $dietPlan->condition_focus = $data['condition_focus'] ?? null;
        $dietPlan->end_date = $data['end_date'] ?? null;

        if ($dietPlan->update()) {
            sendResponse(['success' => true, 'message' => 'Diet plan updated successfully']);
        } else {
            sendError('Update failed', 500);
        }
        break;

    case 'DELETE':
        if ($action === 'meal') {
            $mealId = $_GET['meal_id'] ?? null;
            $planId = $_GET['plan_id'] ?? null;
            
            if (!$mealId || !$planId) {
                sendError('Meal ID and Plan ID required');
            }

            if ($dietPlan->deleteMeal($mealId, $planId)) {
                sendResponse(['success' => true, 'message' => 'Meal deleted successfully']);
            } else {
                sendError('Delete failed', 500);
            }
        }
        elseif ($action === 'restricted') {
            $restrictedId = $_GET['restricted_id'] ?? null;
            $userId = $_GET['user_id'] ?? null;
            
            if (!$restrictedId || !$userId) {
                sendError('Restricted food ID and User ID required');
            }

            if ($dietPlan->removeRestrictedFood($restrictedId, $userId)) {
                sendResponse(['success' => true, 'message' => 'Restricted food removed']);
            } else {
                sendError('Delete failed', 500);
            }
        }
        else {
            $planId = $_GET['plan_id'] ?? null;
            $userId = $_GET['user_id'] ?? null;
            
            if (!$planId || !$userId) {
                sendError('Plan ID and User ID required');
            }

            if ($dietPlan->delete($planId, $userId)) {
                sendResponse(['success' => true, 'message' => 'Diet plan deleted successfully']);
            } else {
                sendError('Delete failed', 500);
            }
        }
        break;

    default:
        sendError('Method not allowed', 405);
}
