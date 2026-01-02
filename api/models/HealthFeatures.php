<?php
// Health Features Model - Handles all new health-related features

class HealthFeatures {
    private $con;
    
    public function __construct($con) {
        $this->con = $con;
    }
    
    // =====================================================
    // MISSED DOSE INTELLIGENCE
    // =====================================================
    
    public function getMissedDoses($userId, $date = null) {
        $date = $date ?? date('Y-m-d');
        $query = "SELECT pt.*, m.name as medicine_name, m.dosage, m.instructions,
                         ms.scheduled_time, ms.meal_relation
                  FROM pill_tracking pt
                  JOIN medicines m ON pt.medicine_id = m.id
                  JOIN medicine_schedules ms ON pt.schedule_id = ms.id
                  WHERE pt.user_id = ? AND pt.scheduled_date = ? AND pt.status = 'missed'
                  ORDER BY pt.scheduled_time";
        
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "is", $userId, $date);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        return mysqli_fetch_all($result, MYSQLI_ASSOC);
    }
    
    public function updateMissedDoseReason($trackingId, $reason, $recoveryAction = null) {
        $query = "UPDATE pill_tracking SET missed_reason = ?, recovery_action = ? WHERE id = ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "ssi", $reason, $recoveryAction, $trackingId);
        return mysqli_stmt_execute($stmt);
    }
    
    public function getRecoverySuggestion($medicineId, $missedTime) {
        // Get medicine details
        $query = "SELECT m.*, ms.scheduled_time, ms.meal_relation 
                  FROM medicines m
                  JOIN medicine_schedules ms ON m.id = ms.medicine_id
                  WHERE m.id = ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $medicineId);
        mysqli_stmt_execute($stmt);
        $medicine = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        
        if (!$medicine) return null;
        
        $hoursSinceMissed = (strtotime('now') - strtotime($missedTime)) / 3600;
        
        // Get next scheduled dose
        $nextDoseQuery = "SELECT scheduled_time FROM medicine_schedules 
                          WHERE medicine_id = ? AND scheduled_time > ?
                          ORDER BY scheduled_time LIMIT 1";
        $stmt = mysqli_prepare($this->con, $nextDoseQuery);
        $currentTime = date('H:i:s');
        mysqli_stmt_bind_param($stmt, "is", $medicineId, $currentTime);
        mysqli_stmt_execute($stmt);
        $nextDose = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        
        $suggestion = [
            'medicine' => $medicine['name'],
            'missed_time' => $missedTime,
            'hours_since_missed' => round($hoursSinceMissed, 1),
            'can_take_now' => false,
            'should_skip' => false,
            'recommendation' => '',
            'warning' => ''
        ];
        
        if ($hoursSinceMissed < 4 && (!$nextDose || strtotime($nextDose['scheduled_time']) - strtotime('now') > 7200)) {
            $suggestion['can_take_now'] = true;
            $suggestion['recommendation'] = 'You can take this dose now. It\'s been less than 4 hours since the scheduled time.';
        } elseif ($nextDose && strtotime($nextDose['scheduled_time']) - strtotime('now') < 7200) {
            $suggestion['should_skip'] = true;
            $suggestion['recommendation'] = 'Skip this dose. Your next dose is coming up soon. Do NOT double up.';
            $suggestion['warning'] = 'Taking two doses close together can cause side effects.';
        } else {
            $suggestion['should_skip'] = true;
            $suggestion['recommendation'] = 'It\'s been too long. Skip this dose and continue with your regular schedule.';
        }
        
        return $suggestion;
    }
    
    // =====================================================
    // MEDICINE INTERACTION CHECKER
    // =====================================================
    
    public function checkInteractions($userId) {
        // Get all active medicines for user
        $query = "SELECT id, name, salt_composition FROM medicines 
                  WHERE user_id = ? AND is_active = 1";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $medicines = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
        
        $interactions = [];
        
        for ($i = 0; $i < count($medicines); $i++) {
            for ($j = $i + 1; $j < count($medicines); $j++) {
                $salt1 = strtolower($medicines[$i]['salt_composition'] ?? $medicines[$i]['name']);
                $salt2 = strtolower($medicines[$j]['salt_composition'] ?? $medicines[$j]['name']);
                
                // Check for interaction
                $interactionQuery = "SELECT * FROM medicine_interactions 
                                    WHERE (LOWER(medicine1_salt) LIKE ? AND LOWER(medicine2_salt) LIKE ?)
                                    OR (LOWER(medicine1_salt) LIKE ? AND LOWER(medicine2_salt) LIKE ?)";
                $stmt = mysqli_prepare($this->con, $interactionQuery);
                $salt1Like = "%$salt1%";
                $salt2Like = "%$salt2%";
                mysqli_stmt_bind_param($stmt, "ssss", $salt1Like, $salt2Like, $salt2Like, $salt1Like);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                while ($interaction = mysqli_fetch_assoc($result)) {
                    $interactions[] = [
                        'medicine1' => $medicines[$i]['name'],
                        'medicine2' => $medicines[$j]['name'],
                        'severity' => $interaction['severity'],
                        'description' => $interaction['description'],
                        'recommendation' => $interaction['recommendation']
                    ];
                }
            }
        }
        
        // Check for duplicate salts
        $saltCounts = [];
        foreach ($medicines as $med) {
            $salt = strtolower($med['salt_composition'] ?? $med['name']);
            if (!isset($saltCounts[$salt])) {
                $saltCounts[$salt] = [];
            }
            $saltCounts[$salt][] = $med['name'];
        }
        
        $duplicates = [];
        foreach ($saltCounts as $salt => $names) {
            if (count($names) > 1) {
                $duplicates[] = [
                    'salt' => $salt,
                    'medicines' => $names,
                    'warning' => 'These medicines contain the same active ingredient. Taking both may lead to overdose.'
                ];
            }
        }
        
        return [
            'interactions' => $interactions,
            'duplicates' => $duplicates
        ];
    }
    
    // =====================================================
    // REFILL & EXPIRY ALERTS
    // =====================================================
    
    public function getRefillAlerts($userId) {
        $query = "SELECT id, name, dosage, remaining_pills, total_pills, refill_reminder_days,
                         expiry_date, 
                         DATEDIFF(expiry_date, CURDATE()) as days_to_expiry
                  FROM medicines 
                  WHERE user_id = ? AND is_active = 1
                  AND (remaining_pills <= refill_reminder_days 
                       OR DATEDIFF(expiry_date, CURDATE()) <= 30)";
        
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $medicines = mysqli_fetch_all($result, MYSQLI_ASSOC);
        
        $alerts = [];
        foreach ($medicines as $med) {
            if ($med['remaining_pills'] <= $med['refill_reminder_days']) {
                $alerts[] = [
                    'type' => 'refill',
                    'medicine_id' => $med['id'],
                    'medicine_name' => $med['name'],
                    'remaining_pills' => $med['remaining_pills'],
                    'message' => "Only {$med['remaining_pills']} pills left. Time to refill!",
                    'urgency' => $med['remaining_pills'] <= 3 ? 'high' : 'medium'
                ];
            }
            
            if ($med['days_to_expiry'] !== null && $med['days_to_expiry'] <= 30) {
                $urgency = 'low';
                if ($med['days_to_expiry'] <= 0) $urgency = 'critical';
                elseif ($med['days_to_expiry'] <= 7) $urgency = 'high';
                elseif ($med['days_to_expiry'] <= 14) $urgency = 'medium';
                
                $alerts[] = [
                    'type' => 'expiry',
                    'medicine_id' => $med['id'],
                    'medicine_name' => $med['name'],
                    'expiry_date' => $med['expiry_date'],
                    'days_to_expiry' => $med['days_to_expiry'],
                    'message' => $med['days_to_expiry'] <= 0 
                        ? "EXPIRED! Do not use." 
                        : "Expires in {$med['days_to_expiry']} days",
                    'urgency' => $urgency
                ];
            }
        }
        
        return $alerts;
    }
    
    public function updateMedicinePills($medicineId, $remainingPills, $totalPills = null, $expiryDate = null) {
        $query = "UPDATE medicines SET remaining_pills = ?";
        $params = [$remainingPills];
        $types = "i";
        
        if ($totalPills !== null) {
            $query .= ", total_pills = ?";
            $params[] = $totalPills;
            $types .= "i";
        }
        
        if ($expiryDate !== null) {
            $query .= ", expiry_date = ?";
            $params[] = $expiryDate;
            $types .= "s";
        }
        
        $query .= " WHERE id = ?";
        $params[] = $medicineId;
        $types .= "i";
        
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        return mysqli_stmt_execute($stmt);
    }
    
    public function decrementPillCount($medicineId) {
        $query = "UPDATE medicines SET remaining_pills = GREATEST(0, remaining_pills - 1) WHERE id = ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $medicineId);
        return mysqli_stmt_execute($stmt);
    }
    
    // =====================================================
    // MEDICINE HISTORY TIMELINE
    // =====================================================
    
    public function getMedicineHistory($medicineId) {
        $query = "SELECT mh.*, u.full_name as user_name 
                  FROM medicine_history mh
                  JOIN users u ON mh.user_id = u.id
                  WHERE mh.medicine_id = ?
                  ORDER BY mh.created_at DESC";
        
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $medicineId);
        mysqli_stmt_execute($stmt);
        return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    }
    
    public function logMedicineHistory($medicineId, $userId, $action, $oldValue = null, $newValue = null, $notes = null) {
        $query = "INSERT INTO medicine_history (medicine_id, user_id, action, old_value, new_value, notes) 
                  VALUES (?, ?, ?, ?, ?, ?)";
        
        $oldJson = $oldValue ? json_encode($oldValue) : null;
        $newJson = $newValue ? json_encode($newValue) : null;
        
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "iissss", $medicineId, $userId, $action, $oldJson, $newJson, $notes);
        return mysqli_stmt_execute($stmt);
    }
    
    public function getUserMedicineTimeline($userId) {
        $query = "SELECT mh.*, m.name as medicine_name 
                  FROM medicine_history mh
                  JOIN medicines m ON mh.medicine_id = m.id
                  WHERE mh.user_id = ?
                  ORDER BY mh.created_at DESC
                  LIMIT 100";
        
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    }
    
    // =====================================================
    // SIDE EFFECTS REPORTING
    // =====================================================
    
    public function reportSideEffect($userId, $medicineId, $effectType, $severity, $description = null, $onsetDate = null) {
        $query = "INSERT INTO side_effects (user_id, medicine_id, effect_type, severity, description, onset_date) 
                  VALUES (?, ?, ?, ?, ?, ?)";
        
        $onsetDate = $onsetDate ?? date('Y-m-d');
        
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "iissss", $userId, $medicineId, $effectType, $severity, $description, $onsetDate);
        
        if (mysqli_stmt_execute($stmt)) {
            return mysqli_insert_id($this->con);
        }
        return false;
    }
    
    public function getUserSideEffects($userId, $medicineId = null) {
        $query = "SELECT se.*, m.name as medicine_name 
                  FROM side_effects se
                  JOIN medicines m ON se.medicine_id = m.id
                  WHERE se.user_id = ?";
        
        $params = [$userId];
        $types = "i";
        
        if ($medicineId) {
            $query .= " AND se.medicine_id = ?";
            $params[] = $medicineId;
            $types .= "i";
        }
        
        $query .= " ORDER BY se.created_at DESC";
        
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    }
    
    public function getCommonSideEffects($saltName) {
        $query = "SELECT * FROM common_side_effects WHERE LOWER(salt_name) LIKE ?";
        $saltLike = "%" . strtolower($saltName) . "%";
        
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "s", $saltLike);
        mysqli_stmt_execute($stmt);
        return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    }
    
    public function updateSideEffect($sideEffectId, $data) {
        $allowedFields = ['action_taken', 'is_resolved', 'duration_days'];
        $updates = [];
        $params = [];
        $types = "";
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
                $types .= is_int($data[$field]) ? "i" : "s";
            }
        }
        
        if (empty($updates)) return false;
        
        $query = "UPDATE side_effects SET " . implode(", ", $updates) . " WHERE id = ?";
        $params[] = $sideEffectId;
        $types .= "i";
        
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        return mysqli_stmt_execute($stmt);
    }
}
