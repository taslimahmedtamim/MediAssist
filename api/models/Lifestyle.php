<?php
// Lifestyle Model - Handles water intake, activity tracking, and grocery list

class Lifestyle {
    private $con;
    
    public function __construct($con) {
        $this->con = $con;
    }
    
    // =====================================================
    // WATER INTAKE TRACKING
    // =====================================================
    
    public function logWaterIntake($userId, $amountMl, $date = null) {
        $date = $date ?? date('Y-m-d');
        
        $query = "INSERT INTO water_intake (user_id, date, amount_ml) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "isi", $userId, $date, $amountMl);
        
        if (mysqli_stmt_execute($stmt)) {
            return mysqli_insert_id($this->con);
        }
        return false;
    }
    
    public function getDailyWaterIntake($userId, $date = null) {
        $date = $date ?? date('Y-m-d');
        
        $query = "SELECT SUM(amount_ml) as total_ml, COUNT(*) as entries
                  FROM water_intake
                  WHERE user_id = ? AND date = ?";
        
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "is", $userId, $date);
        mysqli_stmt_execute($stmt);
        $result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        
        // Get user's target (default 2500ml)
        $target = $this->getWaterTarget($userId);
        
        return [
            'date' => $date,
            'total_ml' => (int)($result['total_ml'] ?? 0),
            'entries' => (int)($result['entries'] ?? 0),
            'target_ml' => $target,
            'percentage' => $target > 0 ? min(100, round(($result['total_ml'] ?? 0) / $target * 100)) : 0,
            'glasses' => round(($result['total_ml'] ?? 0) / 250), // Assuming 250ml per glass
            'remaining_ml' => max(0, $target - ($result['total_ml'] ?? 0))
        ];
    }
    
    public function getWaterIntakeHistory($userId, $days = 7) {
        $query = "SELECT date, SUM(amount_ml) as total_ml
                  FROM water_intake
                  WHERE user_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                  GROUP BY date
                  ORDER BY date DESC";
        
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "ii", $userId, $days);
        mysqli_stmt_execute($stmt);
        return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    }
    
    public function getWaterLogs($userId, $date = null) {
        $date = $date ?? date('Y-m-d');
        
        $query = "SELECT * FROM water_intake WHERE user_id = ? AND date = ? ORDER BY logged_at DESC";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "is", $userId, $date);
        mysqli_stmt_execute($stmt);
        return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    }
    
    public function deleteWaterLog($logId, $userId) {
        $query = "DELETE FROM water_intake WHERE id = ? AND user_id = ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "ii", $logId, $userId);
        return mysqli_stmt_execute($stmt);
    }
    
    private function getWaterTarget($userId) {
        // Get user weight to calculate target
        $query = "SELECT weight_kg FROM users WHERE id = ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        
        if ($user && $user['weight_kg']) {
            // ~35ml per kg body weight
            return round($user['weight_kg'] * 35);
        }
        
        return 2500; // Default target
    }
    
    // =====================================================
    // ACTIVITY / EXERCISE TRACKING
    // =====================================================
    
    public function logActivity($userId, $activityType, $durationMinutes, $intensity = 'moderate', $caloriesBurned = null, $notes = null, $date = null) {
        $date = $date ?? date('Y-m-d');
        
        // If calories not provided, calculate from reference
        if ($caloriesBurned === null) {
            $caloriesBurned = $this->calculateCaloriesBurned($userId, $activityType, $durationMinutes, $intensity);
        }
        
        $query = "INSERT INTO activity_log (user_id, date, activity_type, duration_minutes, calories_burned, intensity, notes) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "issiiss", $userId, $date, $activityType, $durationMinutes, $caloriesBurned, $intensity, $notes);
        
        if (mysqli_stmt_execute($stmt)) {
            return mysqli_insert_id($this->con);
        }
        return false;
    }
    
    public function getDailyActivity($userId, $date = null) {
        $date = $date ?? date('Y-m-d');
        
        $query = "SELECT * FROM activity_log WHERE user_id = ? AND date = ? ORDER BY logged_at DESC";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "is", $userId, $date);
        mysqli_stmt_execute($stmt);
        $activities = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
        
        $totalMinutes = 0;
        $totalCalories = 0;
        
        foreach ($activities as $activity) {
            $totalMinutes += $activity['duration_minutes'];
            $totalCalories += $activity['calories_burned'];
        }
        
        return [
            'date' => $date,
            'activities' => $activities,
            'total_minutes' => $totalMinutes,
            'total_calories_burned' => $totalCalories,
            'goal_minutes' => 30, // WHO recommends 30 min/day
            'goal_met' => $totalMinutes >= 30
        ];
    }
    
    public function getActivityHistory($userId, $days = 7) {
        $query = "SELECT date, 
                         SUM(duration_minutes) as total_minutes,
                         SUM(calories_burned) as total_calories,
                         COUNT(*) as activities
                  FROM activity_log
                  WHERE user_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                  GROUP BY date
                  ORDER BY date DESC";
        
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "ii", $userId, $days);
        mysqli_stmt_execute($stmt);
        return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    }
    
    public function getActivityTypes() {
        $query = "SELECT * FROM activity_reference ORDER BY category, activity_name";
        $result = mysqli_query($this->con, $query);
        return mysqli_fetch_all($result, MYSQLI_ASSOC);
    }
    
    public function deleteActivity($activityId, $userId) {
        $query = "DELETE FROM activity_log WHERE id = ? AND user_id = ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "ii", $activityId, $userId);
        return mysqli_stmt_execute($stmt);
    }
    
    private function calculateCaloriesBurned($userId, $activityType, $durationMinutes, $intensity) {
        // Get activity reference
        $query = "SELECT * FROM activity_reference WHERE LOWER(activity_name) = LOWER(?)";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "s", $activityType);
        mysqli_stmt_execute($stmt);
        $activity = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        
        if (!$activity) {
            // Default estimate: 5 calories per minute for moderate activity
            $caloriesPerMinute = 5;
        } else {
            switch ($intensity) {
                case 'light':
                    $caloriesPerMinute = $activity['calories_per_minute_light'];
                    break;
                case 'vigorous':
                    $caloriesPerMinute = $activity['calories_per_minute_vigorous'];
                    break;
                default:
                    $caloriesPerMinute = $activity['calories_per_minute_moderate'];
            }
        }
        
        // Adjust for user weight if available
        $userQuery = "SELECT weight_kg FROM users WHERE id = ?";
        $stmt = mysqli_prepare($this->con, $userQuery);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        
        if ($user && $user['weight_kg']) {
            // Adjust calories for weight (base is 70kg)
            $weightMultiplier = $user['weight_kg'] / 70;
            $caloriesPerMinute *= $weightMultiplier;
        }
        
        return round($caloriesPerMinute * $durationMinutes);
    }
    
    // =====================================================
    // CALORIE BALANCE
    // =====================================================
    
    public function getDailyCalorieBalance($userId, $date = null) {
        $date = $date ?? date('Y-m-d');
        
        // Get calories consumed from meals (would need meal logging feature)
        // For now, get from diet plan
        $dietQuery = "SELECT SUM(m.calories) as total_intake
                      FROM meals m
                      JOIN diet_plans dp ON m.diet_plan_id = dp.id
                      WHERE dp.user_id = ? AND dp.is_active = 1";
        $stmt = mysqli_prepare($this->con, $dietQuery);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $dietResult = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        $caloriesConsumed = (int)($dietResult['total_intake'] ?? 0);
        
        // Get calories burned from activities
        $activityQuery = "SELECT SUM(calories_burned) as total_burned
                          FROM activity_log
                          WHERE user_id = ? AND date = ?";
        $stmt = mysqli_prepare($this->con, $activityQuery);
        mysqli_stmt_bind_param($stmt, "is", $userId, $date);
        mysqli_stmt_execute($stmt);
        $activityResult = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        $caloriesBurned = (int)($activityResult['total_burned'] ?? 0);
        
        // Get BMR (Basal Metabolic Rate) estimate
        $bmr = $this->calculateBMR($userId);
        
        return [
            'date' => $date,
            'calories_consumed' => $caloriesConsumed,
            'calories_burned_activity' => $caloriesBurned,
            'bmr' => $bmr,
            'total_burned' => $bmr + $caloriesBurned,
            'net_calories' => $caloriesConsumed - ($bmr + $caloriesBurned),
            'is_deficit' => $caloriesConsumed < ($bmr + $caloriesBurned)
        ];
    }
    
    private function calculateBMR($userId) {
        $query = "SELECT weight_kg, height_cm, date_of_birth, gender FROM users WHERE id = ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        
        if (!$user || !$user['weight_kg'] || !$user['height_cm'] || !$user['date_of_birth']) {
            return 1500; // Default BMR
        }
        
        $age = date_diff(date_create($user['date_of_birth']), date_create('today'))->y;
        
        // Mifflin-St Jeor Equation
        if ($user['gender'] === 'male') {
            $bmr = (10 * $user['weight_kg']) + (6.25 * $user['height_cm']) - (5 * $age) + 5;
        } else {
            $bmr = (10 * $user['weight_kg']) + (6.25 * $user['height_cm']) - (5 * $age) - 161;
        }
        
        return round($bmr);
    }
    
    // =====================================================
    // GROCERY LIST GENERATOR
    // =====================================================
    
    public function generateGroceryList($userId, $weekStartDate = null) {
        $weekStartDate = $weekStartDate ?? date('Y-m-d', strtotime('monday this week'));
        
        // Get meals from active diet plan
        $query = "SELECT m.meal_name, m.description
                  FROM meals m
                  JOIN diet_plans dp ON m.diet_plan_id = dp.id
                  WHERE dp.user_id = ? AND dp.is_active = 1";
        
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $meals = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
        
        // Extract ingredients from meal descriptions
        $groceryItems = [];
        
        foreach ($meals as $meal) {
            // Simple extraction - in production, you'd use NLP
            $description = strtolower($meal['description'] ?? $meal['meal_name']);
            
            // Common food items to look for
            $commonItems = [
                'vegetables' => ['spinach', 'broccoli', 'carrot', 'tomato', 'onion', 'garlic', 'lettuce', 'cucumber', 'bell pepper', 'potato', 'cabbage'],
                'fruits' => ['apple', 'banana', 'orange', 'berries', 'mango', 'grapes', 'watermelon'],
                'proteins' => ['chicken', 'fish', 'egg', 'tofu', 'paneer', 'lentils', 'beans', 'dal'],
                'dairy' => ['milk', 'yogurt', 'cheese', 'butter', 'curd'],
                'grains' => ['rice', 'bread', 'oats', 'wheat', 'quinoa', 'pasta'],
                'others' => ['oil', 'salt', 'pepper', 'honey', 'nuts', 'seeds']
            ];
            
            foreach ($commonItems as $category => $items) {
                foreach ($items as $item) {
                    if (strpos($description, $item) !== false) {
                        $key = ucfirst($item);
                        if (!isset($groceryItems[$key])) {
                            $groceryItems[$key] = [
                                'name' => $key,
                                'category' => ucfirst($category),
                                'quantity' => 'As needed'
                            ];
                        }
                    }
                }
            }
        }
        
        // Save to grocery_list table
        foreach ($groceryItems as $item) {
            $insertQuery = "INSERT INTO grocery_list (user_id, item_name, quantity, category, week_start_date)
                            VALUES (?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)";
            $stmt = mysqli_prepare($this->con, $insertQuery);
            mysqli_stmt_bind_param($stmt, "issss", $userId, $item['name'], $item['quantity'], $item['category'], $weekStartDate);
            mysqli_stmt_execute($stmt);
        }
        
        return array_values($groceryItems);
    }
    
    public function getGroceryList($userId, $weekStartDate = null) {
        $weekStartDate = $weekStartDate ?? date('Y-m-d', strtotime('monday this week'));
        
        $query = "SELECT * FROM grocery_list 
                  WHERE user_id = ? AND week_start_date = ?
                  ORDER BY category, item_name";
        
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "is", $userId, $weekStartDate);
        mysqli_stmt_execute($stmt);
        return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    }
    
    public function addGroceryItem($userId, $itemName, $quantity = null, $category = 'Other', $weekStartDate = null) {
        $weekStartDate = $weekStartDate ?? date('Y-m-d', strtotime('monday this week'));
        
        $query = "INSERT INTO grocery_list (user_id, item_name, quantity, category, week_start_date)
                  VALUES (?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "issss", $userId, $itemName, $quantity, $category, $weekStartDate);
        
        if (mysqli_stmt_execute($stmt)) {
            return mysqli_insert_id($this->con);
        }
        return false;
    }
    
    public function updateGroceryItem($itemId, $userId, $isPurchased) {
        $query = "UPDATE grocery_list SET is_purchased = ? WHERE id = ? AND user_id = ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "iii", $isPurchased, $itemId, $userId);
        return mysqli_stmt_execute($stmt);
    }
    
    public function deleteGroceryItem($itemId, $userId) {
        $query = "DELETE FROM grocery_list WHERE id = ? AND user_id = ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "ii", $itemId, $userId);
        return mysqli_stmt_execute($stmt);
    }
    
    // =====================================================
    // CONDITION-BASED DIET SUGGESTIONS
    // =====================================================
    
    public function getFoodRestrictions($userId) {
        // Get user's health conditions
        $condQuery = "SELECT hc.name FROM user_health_conditions uhc
                      JOIN health_conditions hc ON uhc.condition_id = hc.id
                      WHERE uhc.user_id = ?";
        $stmt = mysqli_prepare($this->con, $condQuery);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $conditions = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
        
        if (empty($conditions)) {
            return [];
        }
        
        $conditionNames = array_column($conditions, 'name');
        $placeholders = str_repeat('?,', count($conditionNames) - 1) . '?';
        
        $query = "SELECT * FROM condition_food_restrictions 
                  WHERE condition_name IN ($placeholders)
                  ORDER BY restriction_type, food_category";
        
        $stmt = mysqli_prepare($this->con, $query);
        $types = str_repeat('s', count($conditionNames));
        mysqli_stmt_bind_param($stmt, $types, ...$conditionNames);
        mysqli_stmt_execute($stmt);
        
        return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    }
    
    public function getDietSuggestions($userId) {
        $restrictions = $this->getFoodRestrictions($userId);
        
        $suggestions = [
            'avoid' => [],
            'limit' => [],
            'prefer' => []
        ];
        
        foreach ($restrictions as $restriction) {
            $suggestions[$restriction['restriction_type']][] = [
                'food' => $restriction['food_category'],
                'condition' => $restriction['condition_name'],
                'reason' => $restriction['reason'],
                'alternatives' => $restriction['alternatives']
            ];
        }
        
        return $suggestions;
    }
}
