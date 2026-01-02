<?php
/**
 * Diet Plan Model
 */

class DietPlan {
    private $con;
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

    public function __construct($con) {
        $this->con = $con;
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
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";

        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "isidddsss", 
            $this->user_id, $this->plan_name, $this->target_calories, 
            $this->target_protein_g, $this->target_carbs_g, $this->target_fat_g,
            $this->condition_focus, $this->start_date, $this->end_date);

        if (mysqli_stmt_execute($stmt)) {
            $this->id = mysqli_insert_id($this->con);
            mysqli_stmt_close($stmt);
            return true;
        }
        mysqli_stmt_close($stmt);
        return false;
    }

    /**
     * Get active diet plan for user
     */
    public function getActivePlan($userId) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE user_id = ? AND is_active = 1 LIMIT 1";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $row;
    }

    /**
     * Get all diet plans for user
     */
    public function getByUser($userId) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE user_id = ? ORDER BY created_at DESC";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        return $rows;
    }

    /**
     * Get diet plan by ID
     */
    public function getById($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = ? LIMIT 1";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $row;
    }

    /**
     * Update diet plan
     */
    public function update() {
        $query = "UPDATE " . $this->table . " 
                  SET plan_name = ?, target_calories = ?, 
                      target_protein_g = ?, target_carbs_g = ?, target_fat_g = ?,
                      condition_focus = ?, end_date = ? 
                  WHERE id = ? AND user_id = ?";

        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "sidddssii", 
            $this->plan_name, $this->target_calories, 
            $this->target_protein_g, $this->target_carbs_g, $this->target_fat_g,
            $this->condition_focus, $this->end_date, $this->id, $this->user_id);

        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $success;
    }

    /**
     * Delete diet plan
     */
    public function delete($id, $userId) {
        $query = "DELETE FROM " . $this->table . " WHERE id = ? AND user_id = ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "ii", $id, $userId);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $success;
    }

    /**
     * Deactivate all plans for user
     */
    public function deactivateAll($userId) {
        $query = "UPDATE " . $this->table . " SET is_active = 0 WHERE user_id = ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $success;
    }

    /**
     * Add meal to diet plan
     */
    public function addMeal($planId, $mealData) {
        $query = "INSERT INTO meals 
                  (diet_plan_id, meal_type, day_of_week, meal_name, description, 
                   calories, protein_g, carbs_g, fat_g, fiber_g, sodium_mg) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "isissidddd", 
            $planId, $mealData['meal_type'], $mealData['day_of_week'], 
            $mealData['meal_name'], $mealData['description'],
            $mealData['calories'], $mealData['protein_g'], $mealData['carbs_g'], 
            $mealData['fat_g'], $mealData['fiber_g'], $mealData['sodium_mg']);

        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $success;
    }

    /**
     * Get meals for a diet plan
     */
    public function getMeals($planId, $dayOfWeek = null) {
        if ($dayOfWeek !== null) {
            $query = "SELECT * FROM meals WHERE diet_plan_id = ? AND day_of_week = ?
                      ORDER BY day_of_week, FIELD(meal_type, 'breakfast', 'morning_snack', 'lunch', 'afternoon_snack', 'dinner', 'evening_snack')";
            $stmt = mysqli_prepare($this->con, $query);
            mysqli_stmt_bind_param($stmt, "ii", $planId, $dayOfWeek);
        } else {
            $query = "SELECT * FROM meals WHERE diet_plan_id = ?
                      ORDER BY day_of_week, FIELD(meal_type, 'breakfast', 'morning_snack', 'lunch', 'afternoon_snack', 'dinner', 'evening_snack')";
            $stmt = mysqli_prepare($this->con, $query);
            mysqli_stmt_bind_param($stmt, "i", $planId);
        }
        
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        return $rows;
    }

    /**
     * Delete meal
     */
    public function deleteMeal($mealId, $planId) {
        $query = "DELETE FROM meals WHERE id = ? AND diet_plan_id = ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "ii", $mealId, $planId);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $success;
    }

    /**
     * Get restricted foods for user
     */
    public function getRestrictedFoods($userId) {
        $query = "SELECT * FROM restricted_foods WHERE user_id = ? ORDER BY severity, food_name";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        return $rows;
    }

    /**
     * Add restricted food
     */
    public function addRestrictedFood($userId, $foodName, $reason, $severity = 'avoid') {
        $query = "INSERT INTO restricted_foods (user_id, food_name, reason, severity) 
                  VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "isss", $userId, $foodName, $reason, $severity);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $success;
    }

    /**
     * Remove restricted food
     */
    public function removeRestrictedFood($id, $userId) {
        $query = "DELETE FROM restricted_foods WHERE id = ? AND user_id = ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "ii", $id, $userId);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $success;
    }

    /**
     * Get foods from database suitable for condition
     */
    public function getFoodsForCondition($condition) {
        $conditionJson = '"' . $condition . '"';
        $query = "SELECT * FROM foods WHERE JSON_CONTAINS(suitable_for, ?)";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "s", $conditionJson);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        return $rows;
    }

    /**
     * Search foods
     */
    public function searchFoods($keyword) {
        $searchTerm = '%' . $keyword . '%';
        $query = "SELECT * FROM foods WHERE name LIKE ? OR category LIKE ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "ss", $searchTerm, $searchTerm);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        return $rows;
    }

    /**
     * Get all health conditions
     */
    public function getHealthConditions() {
        $query = "SELECT * FROM health_conditions ORDER BY name";
        $result = mysqli_query($this->con, $query);
        $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
        return $rows;
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

    /**
     * Create diet plan with doctor assignment
     */
    public function createWithDoctor($doctorId, $doctorNotes = null) {
        // Deactivate other plans for this user
        $this->deactivateAll($this->user_id);

        $query = "INSERT INTO " . $this->table . " 
                  (user_id, assigned_by, plan_name, target_calories, target_protein_g, target_carbs_g, target_fat_g, 
                   condition_focus, start_date, end_date, is_active, doctor_notes) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)";

        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "iisidddssss", 
            $this->user_id, $doctorId, $this->plan_name, $this->target_calories, 
            $this->target_protein_g, $this->target_carbs_g, $this->target_fat_g,
            $this->condition_focus, $this->start_date, $this->end_date, $doctorNotes);

        if (mysqli_stmt_execute($stmt)) {
            $this->id = mysqli_insert_id($this->con);
            mysqli_stmt_close($stmt);
            return true;
        }
        mysqli_stmt_close($stmt);
        return false;
    }

    /**
     * Get diet plans assigned by a specific doctor
     */
    public function getByDoctor($doctorId, $patientId = null) {
        $query = "SELECT dp.*, u.full_name as patient_name 
                  FROM " . $this->table . " dp
                  JOIN users u ON dp.user_id = u.id
                  WHERE dp.assigned_by = ?";
        
        if ($patientId) {
            $query .= " AND dp.user_id = ?";
        }
        
        $query .= " ORDER BY dp.created_at DESC";

        $stmt = mysqli_prepare($this->con, $query);
        if ($patientId) {
            mysqli_stmt_bind_param($stmt, "ii", $doctorId, $patientId);
        } else {
            mysqli_stmt_bind_param($stmt, "i", $doctorId);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $plans = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        return $plans;
    }

    /**
     * Get active plan with doctor info
     */
    public function getActivePlanWithDoctor($userId) {
        $query = "SELECT dp.*, u.full_name as assigned_by_name, u.specialization as doctor_specialization
                  FROM " . $this->table . " dp
                  LEFT JOIN users u ON dp.assigned_by = u.id
                  WHERE dp.user_id = ? AND dp.is_active = 1 LIMIT 1";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $row;
    }
}
