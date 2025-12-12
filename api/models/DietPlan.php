<?php
/**
 * Diet Plan Model
 */

class DietPlan {
    private $conn;
    private $table = 'diet_plans';

    public $id;
    public $user_id;
    public $plan_name;
    public $target_calories;
    public $target_protein_g;
    public $target_carbs_g;
    public $target_fat_g;
    public $condition_focus;
    public $start_date;
    public $end_date;
    public $is_active;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Create new diet plan
     */
    public function create() {
        // Deactivate other plans for this user
        $this->deactivateAll($this->user_id);

        $query = "INSERT INTO " . $this->table . " 
                  (user_id, plan_name, target_calories, target_protein_g, target_carbs_g, target_fat_g, 
                   condition_focus, start_date, end_date, is_active) 
                  VALUES (:user_id, :plan_name, :calories, :protein, :carbs, :fat, 
                          :condition, :start_date, :end_date, 1)";

        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':user_id', $this->user_id);
        $stmt->bindParam(':plan_name', $this->plan_name);
        $stmt->bindParam(':calories', $this->target_calories);
        $stmt->bindParam(':protein', $this->target_protein_g);
        $stmt->bindParam(':carbs', $this->target_carbs_g);
        $stmt->bindParam(':fat', $this->target_fat_g);
        $stmt->bindParam(':condition', $this->condition_focus);
        $stmt->bindParam(':start_date', $this->start_date);
        $stmt->bindParam(':end_date', $this->end_date);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    /**
     * Get active diet plan for user
     */
    public function getActivePlan($userId) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE user_id = :user_id AND is_active = 1 LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();

        return $stmt->fetch();
    }

    /**
     * Get all diet plans for user
     */
    public function getByUser($userId) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE user_id = :user_id ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Get diet plan by ID
     */
    public function getById($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetch();
    }

    /**
     * Update diet plan
     */
    public function update() {
        $query = "UPDATE " . $this->table . " 
                  SET plan_name = :plan_name, target_calories = :calories, 
                      target_protein_g = :protein, target_carbs_g = :carbs, target_fat_g = :fat,
                      condition_focus = :condition, end_date = :end_date 
                  WHERE id = :id AND user_id = :user_id";

        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':plan_name', $this->plan_name);
        $stmt->bindParam(':calories', $this->target_calories);
        $stmt->bindParam(':protein', $this->target_protein_g);
        $stmt->bindParam(':carbs', $this->target_carbs_g);
        $stmt->bindParam(':fat', $this->target_fat_g);
        $stmt->bindParam(':condition', $this->condition_focus);
        $stmt->bindParam(':end_date', $this->end_date);
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':user_id', $this->user_id);

        return $stmt->execute();
    }

    /**
     * Delete diet plan
     */
    public function delete($id, $userId) {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id AND user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':user_id', $userId);

        return $stmt->execute();
    }

    /**
     * Deactivate all plans for user
     */
    public function deactivateAll($userId) {
        $query = "UPDATE " . $this->table . " SET is_active = 0 WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);

        return $stmt->execute();
    }

    /**
     * Add meal to diet plan
     */
    public function addMeal($planId, $mealData) {
        $query = "INSERT INTO meals 
                  (diet_plan_id, meal_type, day_of_week, meal_name, description, 
                   calories, protein_g, carbs_g, fat_g, fiber_g, sodium_mg) 
                  VALUES (:plan_id, :meal_type, :day, :name, :description, 
                          :calories, :protein, :carbs, :fat, :fiber, :sodium)";

        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':plan_id', $planId);
        $stmt->bindParam(':meal_type', $mealData['meal_type']);
        $stmt->bindParam(':day', $mealData['day_of_week']);
        $stmt->bindParam(':name', $mealData['meal_name']);
        $stmt->bindParam(':description', $mealData['description']);
        $stmt->bindParam(':calories', $mealData['calories']);
        $stmt->bindParam(':protein', $mealData['protein_g']);
        $stmt->bindParam(':carbs', $mealData['carbs_g']);
        $stmt->bindParam(':fat', $mealData['fat_g']);
        $stmt->bindParam(':fiber', $mealData['fiber_g']);
        $stmt->bindParam(':sodium', $mealData['sodium_mg']);

        return $stmt->execute();
    }

    /**
     * Get meals for a diet plan
     */
    public function getMeals($planId, $dayOfWeek = null) {
        $query = "SELECT * FROM meals WHERE diet_plan_id = :plan_id";
        
        if ($dayOfWeek !== null) {
            $query .= " AND day_of_week = :day";
        }
        
        $query .= " ORDER BY day_of_week, FIELD(meal_type, 'breakfast', 'morning_snack', 'lunch', 'afternoon_snack', 'dinner', 'evening_snack')";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':plan_id', $planId);
        
        if ($dayOfWeek !== null) {
            $stmt->bindParam(':day', $dayOfWeek);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Delete meal
     */
    public function deleteMeal($mealId, $planId) {
        $query = "DELETE FROM meals WHERE id = :meal_id AND diet_plan_id = :plan_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':meal_id', $mealId);
        $stmt->bindParam(':plan_id', $planId);

        return $stmt->execute();
    }

    /**
     * Get restricted foods for user
     */
    public function getRestrictedFoods($userId) {
        $query = "SELECT * FROM restricted_foods WHERE user_id = :user_id ORDER BY severity, food_name";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Add restricted food
     */
    public function addRestrictedFood($userId, $foodName, $reason, $severity = 'avoid') {
        $query = "INSERT INTO restricted_foods (user_id, food_name, reason, severity) 
                  VALUES (:user_id, :food_name, :reason, :severity)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':food_name', $foodName);
        $stmt->bindParam(':reason', $reason);
        $stmt->bindParam(':severity', $severity);

        return $stmt->execute();
    }

    /**
     * Remove restricted food
     */
    public function removeRestrictedFood($id, $userId) {
        $query = "DELETE FROM restricted_foods WHERE id = :id AND user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':user_id', $userId);

        return $stmt->execute();
    }

    /**
     * Get foods from database suitable for condition
     */
    public function getFoodsForCondition($condition) {
        $query = "SELECT * FROM foods WHERE JSON_CONTAINS(suitable_for, :condition)";
        $stmt = $this->conn->prepare($query);
        $conditionJson = '"' . $condition . '"';
        $stmt->bindParam(':condition', $conditionJson);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Search foods
     */
    public function searchFoods($keyword) {
        $query = "SELECT * FROM foods WHERE name LIKE :keyword OR category LIKE :keyword";
        $stmt = $this->conn->prepare($query);
        $searchTerm = '%' . $keyword . '%';
        $stmt->bindParam(':keyword', $searchTerm);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Get all health conditions
     */
    public function getHealthConditions() {
        $query = "SELECT * FROM health_conditions ORDER BY name";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Generate meal plan based on condition
     */
    public function generateMealPlan($userId, $condition, $targetCalories) {
        // Get suitable foods for the condition
        $foods = $this->getFoodsForCondition(strtolower(str_replace(' ', '_', $condition)));
        
        // Get user's restricted foods
        $restricted = $this->getRestrictedFoods($userId);
        $restrictedNames = array_column($restricted, 'food_name');

        // Filter out restricted foods
        $availableFoods = array_filter($foods, function($food) use ($restrictedNames) {
            return !in_array(strtolower($food['name']), array_map('strtolower', $restrictedNames));
        });

        // Simple meal plan generation (can be enhanced with AI/ML)
        $mealPlan = [];
        $mealTypes = ['breakfast', 'morning_snack', 'lunch', 'afternoon_snack', 'dinner'];
        $caloriesPerMeal = [
            'breakfast' => $targetCalories * 0.25,
            'morning_snack' => $targetCalories * 0.10,
            'lunch' => $targetCalories * 0.30,
            'afternoon_snack' => $targetCalories * 0.10,
            'dinner' => $targetCalories * 0.25
        ];

        foreach ($mealTypes as $mealType) {
            $targetCal = $caloriesPerMeal[$mealType];
            $mealPlan[$mealType] = $this->selectFoodsForMeal($availableFoods, $targetCal);
        }

        return $mealPlan;
    }

    /**
     * Select foods for a meal based on target calories
     */
    private function selectFoodsForMeal($foods, $targetCalories) {
        $selected = [];
        $currentCalories = 0;
        $shuffledFoods = $foods;
        shuffle($shuffledFoods);

        foreach ($shuffledFoods as $food) {
            if ($currentCalories + $food['calories_per_100g'] <= $targetCalories * 1.1) {
                $selected[] = $food;
                $currentCalories += $food['calories_per_100g'];
                
                if ($currentCalories >= $targetCalories * 0.9) {
                    break;
                }
            }
        }

        return $selected;
    }
}
