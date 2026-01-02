<?php
// Notifications & Engagement Model - Smart notifications and weekly summaries

class NotificationEngine {
    private $con;
    
    public function __construct($con) {
        $this->con = $con;
    }
    
    // =====================================================
    // SMART NOTIFICATION ENGINE
    // =====================================================
    
    public function createNotification($userId, $type, $title, $message, $data = null) {
        // Check user preferences first
        if (!$this->shouldSendNotification($userId, $type)) {
            return false;
        }
        
        // Check quiet hours
        if ($this->isQuietHours($userId)) {
            // Queue for later
            return $this->queueNotification($userId, $type, $title, $message, $data);
        }
        
        $query = "INSERT INTO notifications (user_id, type, title, message) VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "isss", $userId, $type, $title, $message);
        
        if (mysqli_stmt_execute($stmt)) {
            return mysqli_insert_id($this->con);
        }
        return false;
    }
    
    public function getNotifications($userId, $limit = 20, $unreadOnly = false) {
        $query = "SELECT * FROM notifications WHERE user_id = ?";
        
        if ($unreadOnly) {
            $query .= " AND is_read = 0";
        }
        
        $query .= " ORDER BY created_at DESC LIMIT ?";
        
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "ii", $userId, $limit);
        mysqli_stmt_execute($stmt);
        return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    }
    
    public function getUnreadCount($userId) {
        $query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        return (int)$result['count'];
    }
    
    public function markAsRead($notificationId, $userId) {
        $query = "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "ii", $notificationId, $userId);
        return mysqli_stmt_execute($stmt);
    }
    
    public function markAllAsRead($userId) {
        $query = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        return mysqli_stmt_execute($stmt);
    }
    
    public function deleteNotification($notificationId, $userId) {
        $query = "DELETE FROM notifications WHERE id = ? AND user_id = ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "ii", $notificationId, $userId);
        return mysqli_stmt_execute($stmt);
    }
    
    public function clearOldNotifications($userId, $days = 30) {
        $query = "DELETE FROM notifications WHERE user_id = ? AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "ii", $userId, $days);
        return mysqli_stmt_execute($stmt);
    }
    
    // =====================================================
    // NOTIFICATION PREFERENCES
    // =====================================================
    
    public function getPreferences($userId) {
        $query = "SELECT * FROM notification_preferences WHERE user_id = ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $prefs = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        
        if (!$prefs) {
            // Create default preferences
            $this->createDefaultPreferences($userId);
            return $this->getPreferences($userId);
        }
        
        return $prefs;
    }
    
    public function updatePreferences($userId, $preferences) {
        $allowedFields = [
            'medicine_reminders', 'missed_dose_alerts', 'refill_alerts', 'expiry_alerts',
            'interaction_warnings', 'lab_alerts', 'diet_reminders', 'water_reminders',
            'weekly_summary', 'quiet_hours_start', 'quiet_hours_end',
            'email_notifications', 'push_notifications'
        ];
        
        $updates = [];
        $params = [];
        $types = "";
        
        foreach ($allowedFields as $field) {
            if (isset($preferences[$field])) {
                $updates[] = "$field = ?";
                $params[] = $preferences[$field];
                $types .= is_int($preferences[$field]) || is_bool($preferences[$field]) ? "i" : "s";
            }
        }
        
        if (empty($updates)) return false;
        
        $query = "INSERT INTO notification_preferences (user_id, " . implode(", ", array_keys(array_intersect_key($preferences, array_flip($allowedFields)))) . ")
                  VALUES (?, " . str_repeat("?, ", count($updates) - 1) . "?)
                  ON DUPLICATE KEY UPDATE " . implode(", ", $updates);
        
        // Simpler approach - just update
        $query = "UPDATE notification_preferences SET " . implode(", ", $updates) . " WHERE user_id = ?";
        $params[] = $userId;
        $types .= "i";
        
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        return mysqli_stmt_execute($stmt);
    }
    
    private function createDefaultPreferences($userId) {
        $query = "INSERT INTO notification_preferences (user_id) VALUES (?) 
                  ON DUPLICATE KEY UPDATE user_id = user_id";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
    }
    
    private function shouldSendNotification($userId, $type) {
        $prefs = $this->getPreferences($userId);
        
        $typeMapping = [
            'medicine_reminder' => 'medicine_reminders',
            'missed_pill' => 'missed_dose_alerts',
            'refill_alert' => 'refill_alerts',
            'expiry_alert' => 'expiry_alerts',
            'interaction_warning' => 'interaction_warnings',
            'abnormal_value' => 'lab_alerts',
            'diet_reminder' => 'diet_reminders',
            'water_reminder' => 'water_reminders',
            'weekly_summary' => 'weekly_summary'
        ];
        
        $prefField = $typeMapping[$type] ?? null;
        
        if ($prefField && isset($prefs[$prefField])) {
            return (bool)$prefs[$prefField];
        }
        
        return true; // Default to sending
    }
    
    private function isQuietHours($userId) {
        $prefs = $this->getPreferences($userId);
        
        if (!$prefs['quiet_hours_start'] || !$prefs['quiet_hours_end']) {
            return false;
        }
        
        $now = date('H:i:s');
        $start = $prefs['quiet_hours_start'];
        $end = $prefs['quiet_hours_end'];
        
        if ($start <= $end) {
            return ($now >= $start && $now <= $end);
        } else {
            // Crosses midnight
            return ($now >= $start || $now <= $end);
        }
    }
    
    private function queueNotification($userId, $type, $title, $message, $data) {
        // For now, just save it anyway - in production, you'd use a queue system
        $query = "INSERT INTO notifications (user_id, type, title, message) VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "isss", $userId, $type, $title, $message);
        
        if (mysqli_stmt_execute($stmt)) {
            return mysqli_insert_id($this->con);
        }
        return false;
    }
    
    // =====================================================
    // ADAPTIVE REMINDER LOGIC
    // =====================================================
    
    public function getAdaptiveReminderStrength($userId, $medicineId) {
        // Check recent missed doses
        $query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'missed' THEN 1 ELSE 0 END) as missed
                  FROM pill_tracking
                  WHERE user_id = ? AND medicine_id = ? 
                  AND scheduled_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "ii", $userId, $medicineId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        
        $missRate = $result['total'] > 0 ? ($result['missed'] / $result['total']) : 0;
        
        if ($missRate >= 0.5) {
            return 'strong'; // More than 50% missed
        } elseif ($missRate >= 0.2) {
            return 'moderate'; // 20-50% missed
        }
        return 'normal';
    }
    
    // =====================================================
    // WEEKLY HEALTH SUMMARY
    // =====================================================
    
    public function generateWeeklySummary($userId) {
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $weekEnd = date('Y-m-d', strtotime('sunday this week'));
        
        // Check if already generated
        $existingQuery = "SELECT * FROM weekly_summaries WHERE user_id = ? AND week_start_date = ?";
        $stmt = mysqli_prepare($this->con, $existingQuery);
        mysqli_stmt_bind_param($stmt, "is", $userId, $weekStart);
        mysqli_stmt_execute($stmt);
        $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        
        if ($existing) {
            return $existing;
        }
        
        // Calculate adherence
        $adherenceQuery = "SELECT 
                            COUNT(*) as total_doses,
                            SUM(CASE WHEN status = 'taken' THEN 1 ELSE 0 END) as taken_doses,
                            SUM(CASE WHEN status = 'missed' THEN 1 ELSE 0 END) as missed_doses
                          FROM pill_tracking
                          WHERE user_id = ? AND scheduled_date BETWEEN ? AND ?";
        $stmt = mysqli_prepare($this->con, $adherenceQuery);
        mysqli_stmt_bind_param($stmt, "iss", $userId, $weekStart, $weekEnd);
        mysqli_stmt_execute($stmt);
        $adherence = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        
        $adherencePercentage = $adherence['total_doses'] > 0 
            ? round(($adherence['taken_doses'] / $adherence['total_doses']) * 100, 2) 
            : 0;
        
        // Calculate water intake
        $waterQuery = "SELECT SUM(amount_ml) as total_water FROM water_intake
                       WHERE user_id = ? AND date BETWEEN ? AND ?";
        $stmt = mysqli_prepare($this->con, $waterQuery);
        mysqli_stmt_bind_param($stmt, "iss", $userId, $weekStart, $weekEnd);
        mysqli_stmt_execute($stmt);
        $water = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        $totalWater = (int)($water['total_water'] ?? 0);
        
        // Calculate activity
        $activityQuery = "SELECT 
                            SUM(duration_minutes) as total_minutes,
                            SUM(calories_burned) as total_burned
                          FROM activity_log
                          WHERE user_id = ? AND date BETWEEN ? AND ?";
        $stmt = mysqli_prepare($this->con, $activityQuery);
        mysqli_stmt_bind_param($stmt, "iss", $userId, $weekStart, $weekEnd);
        mysqli_stmt_execute($stmt);
        $activity = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        
        // Count new reports
        $reportsQuery = "SELECT COUNT(*) as count FROM medical_reports
                         WHERE user_id = ? AND created_at BETWEEN ? AND ?";
        $stmt = mysqli_prepare($this->con, $reportsQuery);
        mysqli_stmt_bind_param($stmt, "iss", $userId, $weekStart, $weekEnd);
        mysqli_stmt_execute($stmt);
        $reports = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        
        // Count abnormal values
        $abnormalQuery = "SELECT COUNT(*) as count FROM report_values rv
                          JOIN medical_reports mr ON rv.report_id = mr.id
                          WHERE mr.user_id = ? AND rv.is_abnormal = 1
                          AND mr.created_at BETWEEN ? AND ?";
        $stmt = mysqli_prepare($this->con, $abnormalQuery);
        mysqli_stmt_bind_param($stmt, "iss", $userId, $weekStart, $weekEnd);
        mysqli_stmt_execute($stmt);
        $abnormal = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        
        // Generate insights
        $insights = [];
        
        if ($adherencePercentage >= 90) {
            $insights[] = ['type' => 'success', 'message' => 'Excellent medication adherence this week!'];
        } elseif ($adherencePercentage < 70) {
            $insights[] = ['type' => 'warning', 'message' => 'Medication adherence needs improvement.'];
        }
        
        if ($totalWater < 14000) { // Less than 2L average per day
            $insights[] = ['type' => 'info', 'message' => 'Try to drink more water next week.'];
        }
        
        if (($activity['total_minutes'] ?? 0) >= 150) {
            $insights[] = ['type' => 'success', 'message' => 'Great job meeting the WHO recommended activity level!'];
        }
        
        // Save summary
        $insertQuery = "INSERT INTO weekly_summaries (
                          user_id, week_start_date, week_end_date,
                          total_doses, taken_doses, missed_doses, adherence_percentage,
                          total_water_ml, avg_water_ml,
                          total_activity_minutes, calories_burned,
                          new_reports, abnormal_values, health_insights
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $avgWater = $totalWater / 7;
        $insightsJson = json_encode($insights);
        
        $stmt = mysqli_prepare($this->con, $insertQuery);
        mysqli_stmt_bind_param($stmt, "issiiiiiiiiiis",
            $userId, $weekStart, $weekEnd,
            $adherence['total_doses'], $adherence['taken_doses'], $adherence['missed_doses'], $adherencePercentage,
            $totalWater, $avgWater,
            $activity['total_minutes'] ?? 0, $activity['total_burned'] ?? 0,
            $reports['count'], $abnormal['count'], $insightsJson
        );
        
        mysqli_stmt_execute($stmt);
        
        // Return the summary
        return [
            'week_start_date' => $weekStart,
            'week_end_date' => $weekEnd,
            'adherence' => [
                'total_doses' => (int)$adherence['total_doses'],
                'taken_doses' => (int)$adherence['taken_doses'],
                'missed_doses' => (int)$adherence['missed_doses'],
                'percentage' => $adherencePercentage
            ],
            'hydration' => [
                'total_ml' => $totalWater,
                'avg_daily_ml' => round($avgWater),
                'glasses_per_day' => round($avgWater / 250, 1)
            ],
            'activity' => [
                'total_minutes' => (int)($activity['total_minutes'] ?? 0),
                'calories_burned' => (int)($activity['total_burned'] ?? 0)
            ],
            'labs' => [
                'new_reports' => (int)$reports['count'],
                'abnormal_values' => (int)$abnormal['count']
            ],
            'insights' => $insights
        ];
    }
    
    public function getWeeklySummaryHistory($userId, $weeks = 4) {
        $query = "SELECT * FROM weekly_summaries 
                  WHERE user_id = ?
                  ORDER BY week_start_date DESC
                  LIMIT ?";
        
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "ii", $userId, $weeks);
        mysqli_stmt_execute($stmt);
        return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    }
    
    // =====================================================
    // TRIGGER NOTIFICATIONS
    // =====================================================
    
    public function triggerMissedDoseAlert($userId, $medicineName, $scheduledTime) {
        $title = "Missed Dose Alert";
        $message = "You missed your $medicineName dose scheduled for $scheduledTime. Would you like to take it now or skip?";
        
        return $this->createNotification($userId, 'missed_pill', $title, $message);
    }
    
    public function triggerRefillAlert($userId, $medicineName, $remainingPills) {
        $title = "Refill Reminder";
        $message = "Only $remainingPills pills left of $medicineName. Time to refill your prescription!";
        
        return $this->createNotification($userId, 'refill_alert', $title, $message);
    }
    
    public function triggerExpiryAlert($userId, $medicineName, $daysToExpiry) {
        $title = $daysToExpiry <= 0 ? "Medicine Expired!" : "Expiry Warning";
        $message = $daysToExpiry <= 0 
            ? "$medicineName has expired. Please dispose safely and get a new supply."
            : "$medicineName expires in $daysToExpiry days. Please use or replace soon.";
        
        return $this->createNotification($userId, 'expiry_alert', $title, $message);
    }
    
    public function triggerInteractionWarning($userId, $medicine1, $medicine2, $severity) {
        $title = "Drug Interaction Warning";
        $message = "Potential $severity interaction detected between $medicine1 and $medicine2. Please consult your doctor.";
        
        return $this->createNotification($userId, 'interaction_warning', $title, $message);
    }
    
    public function triggerAbnormalLabAlert($userId, $parameter, $value, $status) {
        $title = "Abnormal Lab Value";
        $message = "Your $parameter result ($value) is $status normal range. Review recommended.";
        
        return $this->createNotification($userId, 'abnormal_value', $title, $message);
    }
    
    // Alias for compatibility
    public function triggerInteractionAlert($userId, $medicine1, $medicine2, $severity) {
        return $this->triggerInteractionWarning($userId, $medicine1, $medicine2, $severity);
    }
    
    // Get weekly summary by date
    public function getWeeklySummary($userId, $weekStartDate = null) {
        if (!$weekStartDate) {
            // Get most recent Monday
            $weekStartDate = date('Y-m-d', strtotime('last monday'));
        }
        
        $query = "SELECT * FROM weekly_summaries 
                  WHERE user_id = ? AND week_start_date = ?";
        
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "is", $userId, $weekStartDate);
        mysqli_stmt_execute($stmt);
        $result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        
        if (!$result) {
            // Generate if not exists
            return $this->generateWeeklySummary($userId);
        }
        
        // Decode JSON fields
        if (isset($result['health_insights'])) {
            $result['health_insights'] = json_decode($result['health_insights'], true);
        }
        
        return $result;
    }
    
    // =====================================================
    // PUSH TOKEN MANAGEMENT
    // =====================================================
    
    public function registerPushToken($userId, $token, $platform = 'web') {
        // Check if token already exists
        $checkQuery = "SELECT id FROM push_tokens WHERE user_id = ? AND token = ?";
        $stmt = mysqli_prepare($this->con, $checkQuery);
        mysqli_stmt_bind_param($stmt, "is", $userId, $token);
        mysqli_stmt_execute($stmt);
        
        if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) {
            // Token exists, update timestamp
            $updateQuery = "UPDATE push_tokens SET updated_at = NOW() WHERE user_id = ? AND token = ?";
            $stmt = mysqli_prepare($this->con, $updateQuery);
            mysqli_stmt_bind_param($stmt, "is", $userId, $token);
            return mysqli_stmt_execute($stmt);
        }
        
        // Insert new token
        $query = "INSERT INTO push_tokens (user_id, token, platform) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "iss", $userId, $token, $platform);
        return mysqli_stmt_execute($stmt);
    }
    
    public function unregisterPushToken($userId, $token) {
        $query = "DELETE FROM push_tokens WHERE user_id = ? AND token = ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "is", $userId, $token);
        return mysqli_stmt_execute($stmt);
    }
    
    public function getUserPushTokens($userId) {
        $query = "SELECT token, platform FROM push_tokens WHERE user_id = ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    }
}
