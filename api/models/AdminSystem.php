<?php
// Admin & System Features Model - Analytics, audit log, data export/import

class AdminSystem {
    private $con;
    
    public function __construct($con) {
        $this->con = $con;
    }
    
    // =====================================================
    // AUDIT LOG SYSTEM
    // =====================================================
    
    public function logAction($userId, $actionType, $entityType, $entityId = null, $oldValues = null, $newValues = null) {
        $query = "INSERT INTO audit_log (user_id, action_type, entity_type, entity_id, old_values, new_values, ip_address, user_agent) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $oldJson = $oldValues ? json_encode($oldValues) : null;
        $newJson = $newValues ? json_encode($newValues) : null;
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "ississss", 
            $userId, $actionType, $entityType, $entityId, $oldJson, $newJson, $ipAddress, $userAgent
        );
        
        return mysqli_stmt_execute($stmt);
    }
    
    public function getAuditLog($userId = null, $limit = 100, $entityType = null) {
        $query = "SELECT al.*, u.full_name as user_name 
                  FROM audit_log al
                  LEFT JOIN users u ON al.user_id = u.id
                  WHERE 1=1";
        
        $params = [];
        $types = "";
        
        if ($userId) {
            $query .= " AND al.user_id = ?";
            $params[] = $userId;
            $types .= "i";
        }
        
        if ($entityType) {
            $query .= " AND al.entity_type = ?";
            $params[] = $entityType;
            $types .= "s";
        }
        
        $query .= " ORDER BY al.created_at DESC LIMIT ?";
        $params[] = $limit;
        $types .= "i";
        
        $stmt = mysqli_prepare($this->con, $query);
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        
        return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    }
    
    public function getUserActivityLog($userId, $limit = 50) {
        $query = "SELECT * FROM audit_log WHERE user_id = ? ORDER BY created_at DESC LIMIT ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "ii", $userId, $limit);
        mysqli_stmt_execute($stmt);
        return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    }
    
    // =====================================================
    // ADMIN ANALYTICS PANEL
    // =====================================================
    
    public function getAnalyticsSummary() {
        $analytics = [];
        
        // Total users
        $result = mysqli_query($this->con, "SELECT COUNT(*) as count FROM users");
        $analytics['total_users'] = mysqli_fetch_assoc($result)['count'];
        
        // Active users (logged in last 7 days - approximated by pill tracking activity)
        $result = mysqli_query($this->con, 
            "SELECT COUNT(DISTINCT user_id) as count FROM pill_tracking 
             WHERE scheduled_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"
        );
        $analytics['active_users_7d'] = mysqli_fetch_assoc($result)['count'];
        
        // Total medicines tracked
        $result = mysqli_query($this->con, "SELECT COUNT(*) as count FROM medicines WHERE is_active = 1");
        $analytics['active_medicines'] = mysqli_fetch_assoc($result)['count'];
        
        // Total reports uploaded
        $result = mysqli_query($this->con, "SELECT COUNT(*) as count FROM medical_reports");
        $analytics['total_reports'] = mysqli_fetch_assoc($result)['count'];
        
        // Overall adherence rate
        $result = mysqli_query($this->con, 
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'taken' THEN 1 ELSE 0 END) as taken
             FROM pill_tracking
             WHERE scheduled_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"
        );
        $adherence = mysqli_fetch_assoc($result);
        $analytics['adherence_rate_30d'] = $adherence['total'] > 0 
            ? round(($adherence['taken'] / $adherence['total']) * 100, 1) 
            : 0;
        
        // Most common conditions
        $result = mysqli_query($this->con, 
            "SELECT hc.name, COUNT(*) as count 
             FROM user_health_conditions uhc
             JOIN health_conditions hc ON uhc.condition_id = hc.id
             GROUP BY hc.name
             ORDER BY count DESC
             LIMIT 5"
        );
        $analytics['top_conditions'] = mysqli_fetch_all($result, MYSQLI_ASSOC);
        
        // Most prescribed medicines (anonymized)
        $result = mysqli_query($this->con, 
            "SELECT name, COUNT(*) as count 
             FROM medicines
             WHERE is_active = 1
             GROUP BY LOWER(name)
             ORDER BY count DESC
             LIMIT 10"
        );
        $analytics['top_medicines'] = mysqli_fetch_all($result, MYSQLI_ASSOC);
        
        // Users by registration date (last 30 days)
        $result = mysqli_query($this->con, 
            "SELECT DATE(created_at) as date, COUNT(*) as count 
             FROM users
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
             GROUP BY DATE(created_at)
             ORDER BY date"
        );
        $analytics['registrations_30d'] = mysqli_fetch_all($result, MYSQLI_ASSOC);
        
        return $analytics;
    }
    
    public function getConditionStatistics() {
        $query = "SELECT 
                    hc.name as condition_name,
                    COUNT(DISTINCT uhc.user_id) as user_count,
                    (SELECT COUNT(*) FROM medicines m 
                     JOIN user_health_conditions uhc2 ON m.user_id = uhc2.user_id
                     WHERE uhc2.condition_id = hc.id AND m.is_active = 1) as medicines_count
                  FROM health_conditions hc
                  LEFT JOIN user_health_conditions uhc ON hc.id = uhc.condition_id
                  GROUP BY hc.id
                  ORDER BY user_count DESC";
        
        $result = mysqli_query($this->con, $query);
        return mysqli_fetch_all($result, MYSQLI_ASSOC);
    }
    
    public function getAdherenceStatistics($days = 30) {
        $query = "SELECT 
                    DATE(scheduled_date) as date,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'taken' THEN 1 ELSE 0 END) as taken,
                    SUM(CASE WHEN status = 'missed' THEN 1 ELSE 0 END) as missed,
                    SUM(CASE WHEN status = 'skipped' THEN 1 ELSE 0 END) as skipped
                  FROM pill_tracking
                  WHERE scheduled_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                  GROUP BY DATE(scheduled_date)
                  ORDER BY date";
        
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $days);
        mysqli_stmt_execute($stmt);
        return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    }
    
    // =====================================================
    // DATA EXPORT
    // =====================================================
    
    public function exportUserData($userId, $format = 'json') {
        $data = [
            'exported_at' => date('Y-m-d H:i:s'),
            'user' => $this->getUserProfile($userId),
            'health_conditions' => $this->getUserHealthConditions($userId),
            'medicines' => $this->getUserMedicines($userId),
            'medicine_schedules' => $this->getUserMedicineSchedules($userId),
            'pill_tracking' => $this->getUserPillTracking($userId),
            'medical_reports' => $this->getUserMedicalReports($userId),
            'diet_plans' => $this->getUserDietPlans($userId),
            'emergency_info' => $this->getUserEmergencyInfo($userId),
            'notifications' => $this->getUserNotifications($userId),
            'water_intake' => $this->getUserWaterIntake($userId),
            'activity_log' => $this->getUserActivityLogData($userId)
        ];
        
        if ($format === 'csv') {
            return $this->convertToCSV($data);
        }
        
        return $data;
    }
    
    private function getUserProfile($userId) {
        $query = "SELECT id, email, full_name, phone, date_of_birth, gender, height_cm, weight_kg, created_at 
                  FROM users WHERE id = ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    }
    
    private function getUserHealthConditions($userId) {
        $query = "SELECT hc.name, uhc.diagnosed_date, uhc.notes 
                  FROM user_health_conditions uhc
                  JOIN health_conditions hc ON uhc.condition_id = hc.id
                  WHERE uhc.user_id = ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    }
    
    private function getUserMedicines($userId) {
        $query = "SELECT * FROM medicines WHERE user_id = ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    }
    
    private function getUserMedicineSchedules($userId) {
        $query = "SELECT ms.* FROM medicine_schedules ms
                  JOIN medicines m ON ms.medicine_id = m.id
                  WHERE m.user_id = ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    }
    
    private function getUserPillTracking($userId) {
        $query = "SELECT * FROM pill_tracking WHERE user_id = ? ORDER BY scheduled_date DESC LIMIT 1000";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    }
    
    private function getUserMedicalReports($userId) {
        $query = "SELECT id, report_type, report_date, ocr_text, parsed_data, recommendations, created_at 
                  FROM medical_reports WHERE user_id = ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    }
    
    private function getUserDietPlans($userId) {
        $query = "SELECT * FROM diet_plans WHERE user_id = ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    }
    
    private function getUserEmergencyInfo($userId) {
        $query = "SELECT * FROM emergency_info WHERE user_id = ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    }
    
    private function getUserNotifications($userId) {
        $query = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 100";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    }
    
    private function getUserWaterIntake($userId) {
        $query = "SELECT * FROM water_intake WHERE user_id = ? ORDER BY date DESC LIMIT 365";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    }
    
    private function getUserActivityLogData($userId) {
        $query = "SELECT * FROM activity_log WHERE user_id = ? ORDER BY date DESC LIMIT 365";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    }
    
    private function convertToCSV($data) {
        $csvData = [];
        
        foreach ($data as $section => $rows) {
            if (is_array($rows) && !empty($rows)) {
                if (isset($rows[0]) && is_array($rows[0])) {
                    // Multiple rows
                    $csvData[$section] = [];
                    $csvData[$section][] = implode(',', array_keys($rows[0])); // Header
                    foreach ($rows as $row) {
                        $csvData[$section][] = implode(',', array_map(function($v) {
                            return '"' . str_replace('"', '""', $v ?? '') . '"';
                        }, array_values($row)));
                    }
                } else {
                    // Single row
                    $csvData[$section][] = implode(',', array_keys($rows));
                    $csvData[$section][] = implode(',', array_map(function($v) {
                        return '"' . str_replace('"', '""', $v ?? '') . '"';
                    }, array_values($rows)));
                }
            }
        }
        
        return $csvData;
    }
    
    // =====================================================
    // DATA IMPORT
    // =====================================================
    
    public function importUserData($userId, $data) {
        $results = [
            'success' => [],
            'errors' => []
        ];
        
        // Start transaction
        mysqli_begin_transaction($this->con);
        
        try {
            // Import medicines
            if (isset($data['medicines']) && is_array($data['medicines'])) {
                foreach ($data['medicines'] as $medicine) {
                    $medicine['user_id'] = $userId;
                    unset($medicine['id']); // Remove old ID
                    $this->importMedicine($medicine);
                }
                $results['success'][] = 'Medicines imported';
            }
            
            // Import health conditions
            if (isset($data['health_conditions']) && is_array($data['health_conditions'])) {
                foreach ($data['health_conditions'] as $condition) {
                    $this->importHealthCondition($userId, $condition);
                }
                $results['success'][] = 'Health conditions imported';
            }
            
            mysqli_commit($this->con);
            
        } catch (Exception $e) {
            mysqli_rollback($this->con);
            $results['errors'][] = $e->getMessage();
        }
        
        return $results;
    }
    
    private function importMedicine($data) {
        $query = "INSERT INTO medicines (user_id, name, dosage, dose_type, frequency, start_date, end_date, instructions, is_active)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "isssssssi",
            $data['user_id'],
            $data['name'],
            $data['dosage'],
            $data['dose_type'],
            $data['frequency'],
            $data['start_date'],
            $data['end_date'],
            $data['instructions'],
            $data['is_active']
        );
        
        return mysqli_stmt_execute($stmt);
    }
    
    private function importHealthCondition($userId, $data) {
        // Find or create condition
        $findQuery = "SELECT id FROM health_conditions WHERE LOWER(name) = LOWER(?)";
        $stmt = mysqli_prepare($this->con, $findQuery);
        mysqli_stmt_bind_param($stmt, "s", $data['name']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        
        if ($result) {
            $conditionId = $result['id'];
        } else {
            // Create new condition
            $createQuery = "INSERT INTO health_conditions (name) VALUES (?)";
            $stmt = mysqli_prepare($this->con, $createQuery);
            mysqli_stmt_bind_param($stmt, "s", $data['name']);
            mysqli_stmt_execute($stmt);
            $conditionId = mysqli_insert_id($this->con);
        }
        
        // Link to user
        $linkQuery = "INSERT IGNORE INTO user_health_conditions (user_id, condition_id, diagnosed_date, notes)
                      VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->con, $linkQuery);
        mysqli_stmt_bind_param($stmt, "iiss", $userId, $conditionId, $data['diagnosed_date'], $data['notes']);
        
        return mysqli_stmt_execute($stmt);
    }
    
    // =====================================================
    // OCR CORRECTIONS LEARNING
    // =====================================================
    
    public function recordOCRCorrection($originalText, $correctedText) {
        $query = "INSERT INTO ocr_corrections (original_text, corrected_text, correction_count)
                  VALUES (?, ?, 1)
                  ON DUPLICATE KEY UPDATE 
                    corrected_text = VALUES(corrected_text),
                    correction_count = correction_count + 1";
        
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "ss", $originalText, $correctedText);
        return mysqli_stmt_execute($stmt);
    }
    
    public function getOCRCorrection($originalText) {
        $query = "SELECT corrected_text FROM ocr_corrections 
                  WHERE original_text = ? AND correction_count >= 3";
        
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "s", $originalText);
        mysqli_stmt_execute($stmt);
        $result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        
        return $result ? $result['corrected_text'] : null;
    }
    
    public function applyOCRCorrections($text) {
        $query = "SELECT original_text, corrected_text FROM ocr_corrections WHERE correction_count >= 3";
        $result = mysqli_query($this->con, $query);
        $corrections = mysqli_fetch_all($result, MYSQLI_ASSOC);
        
        foreach ($corrections as $correction) {
            $text = str_replace($correction['original_text'], $correction['corrected_text'], $text);
        }
        
        return $text;
    }
    
    // =====================================================
    // USER ANALYTICS
    // =====================================================
    
    public function getUserAnalytics($userId) {
        $analytics = [];
        
        // Total medicines
        $query = "SELECT COUNT(*) as count FROM medicines WHERE user_id = ? AND is_active = 1";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $analytics['active_medicines'] = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['count'] ?? 0;
        
        // Adherence rate (30 days)
        $query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'taken' THEN 1 ELSE 0 END) as taken
                  FROM pill_tracking 
                  WHERE user_id = ? AND scheduled_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $adherence = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        $analytics['adherence_rate'] = $adherence['total'] > 0 
            ? round(($adherence['taken'] / $adherence['total']) * 100, 1) 
            : 0;
        
        // Total reports
        $query = "SELECT COUNT(*) as count FROM medical_reports WHERE user_id = ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $analytics['total_reports'] = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['count'] ?? 0;
        
        // Diet plans count
        $query = "SELECT COUNT(*) as count FROM diet_plans WHERE user_id = ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $analytics['diet_plans'] = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['count'] ?? 0;
        
        // Days tracking
        $query = "SELECT DATEDIFF(NOW(), MIN(scheduled_date)) as days FROM pill_tracking WHERE user_id = ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $analytics['days_tracking'] = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['days'] ?? 0;
        
        return $analytics;
    }
    
    public function getMedicineUsageStats() {
        $query = "SELECT 
                    LOWER(m.name) as medicine_name,
                    COUNT(DISTINCT m.user_id) as user_count,
                    COUNT(*) as total_prescriptions,
                    AVG(DATEDIFF(IFNULL(m.end_date, CURDATE()), m.start_date)) as avg_duration_days
                  FROM medicines m
                  WHERE m.is_active = 1
                  GROUP BY LOWER(m.name)
                  ORDER BY user_count DESC
                  LIMIT 50";
        
        $result = mysqli_query($this->con, $query);
        return mysqli_fetch_all($result, MYSQLI_ASSOC);
    }
    
    // =====================================================
    // BACKUP & SYSTEM HEALTH
    // =====================================================
    
    public function generateBackup() {
        $tables = [
            'users', 'health_conditions', 'user_health_conditions', 'medicines',
            'medicine_schedules', 'pill_tracking', 'medical_reports', 'diet_plans',
            'notifications', 'emergency_info', 'emergency_contacts', 'caregivers'
        ];
        
        $backup = [
            'generated_at' => date('Y-m-d H:i:s'),
            'tables' => []
        ];
        
        foreach ($tables as $table) {
            $result = mysqli_query($this->con, "SELECT * FROM $table");
            if ($result) {
                $backup['tables'][$table] = [
                    'row_count' => mysqli_num_rows($result),
                    'data' => mysqli_fetch_all($result, MYSQLI_ASSOC)
                ];
            }
        }
        
        return $backup;
    }
    
    public function getOCRCorrections($limit = 100) {
        $query = "SELECT * FROM ocr_corrections ORDER BY correction_count DESC LIMIT ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $limit);
        mysqli_stmt_execute($stmt);
        return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    }
    
    public function getSystemHealth() {
        $health = [
            'status' => 'healthy',
            'database' => 'connected',
            'timestamp' => date('Y-m-d H:i:s'),
            'checks' => []
        ];
        
        // Check database connection
        if (!mysqli_ping($this->con)) {
            $health['status'] = 'unhealthy';
            $health['database'] = 'disconnected';
            $health['checks']['database'] = false;
        } else {
            $health['checks']['database'] = true;
        }
        
        // Check critical tables exist
        $criticalTables = ['users', 'medicines', 'pill_tracking', 'medical_reports'];
        foreach ($criticalTables as $table) {
            $result = mysqli_query($this->con, "SELECT 1 FROM $table LIMIT 1");
            $health['checks']['table_' . $table] = ($result !== false);
            if ($result === false) {
                $health['status'] = 'degraded';
            }
        }
        
        // Check uploads directory
        $uploadsDir = __DIR__ . '/../../uploads/reports';
        $health['checks']['uploads_writable'] = is_writable($uploadsDir);
        
        return $health;
    }
    
    public function getDatabaseStats() {
        $stats = [];
        
        $tables = [
            'users', 'medicines', 'pill_tracking', 'medical_reports',
            'diet_plans', 'notifications', 'emergency_info', 'water_intake',
            'activity_log', 'audit_log'
        ];
        
        foreach ($tables as $table) {
            $result = mysqli_query($this->con, "SELECT COUNT(*) as count FROM $table");
            if ($result) {
                $stats[$table] = mysqli_fetch_assoc($result)['count'];
            } else {
                $stats[$table] = 'N/A';
            }
        }
        
        // Database size (approximate)
        $result = mysqli_query($this->con, 
            "SELECT SUM(data_length + index_length) / 1024 / 1024 as size_mb 
             FROM information_schema.tables 
             WHERE table_schema = DATABASE()"
        );
        $stats['database_size_mb'] = round(mysqli_fetch_assoc($result)['size_mb'] ?? 0, 2);
        
        return $stats;
    }
    
    // =====================================================
    // USER MANAGEMENT (ADMIN)
    // =====================================================
    
    public function getAllUsers($limit = 100, $offset = 0) {
        $query = "SELECT id, email, full_name, phone, gender, created_at, 
                         (SELECT COUNT(*) FROM medicines WHERE user_id = users.id AND is_active = 1) as medicine_count,
                         (SELECT MAX(scheduled_date) FROM pill_tracking WHERE user_id = users.id) as last_activity
                  FROM users
                  ORDER BY created_at DESC
                  LIMIT ? OFFSET ?";
        
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "ii", $limit, $offset);
        mysqli_stmt_execute($stmt);
        return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    }
    
    public function getUserDetails($userId) {
        $details = [];
        
        // Basic profile
        $query = "SELECT id, email, full_name, phone, date_of_birth, gender, 
                         height_cm, weight_kg, blood_group, created_at
                  FROM users WHERE id = ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $details['profile'] = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        
        // Health conditions
        $query = "SELECT hc.name, uhc.diagnosed_date 
                  FROM user_health_conditions uhc
                  JOIN health_conditions hc ON uhc.condition_id = hc.id
                  WHERE uhc.user_id = ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $details['conditions'] = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
        
        // Analytics
        $details['analytics'] = $this->getUserAnalytics($userId);
        
        return $details;
    }
    
    public function toggleUserStatus($userId, $isActive) {
        // Note: This assumes an is_active column in users table
        // If not exists, this would need to be added to schema
        $query = "UPDATE users SET is_active = ? WHERE id = ?";
        $stmt = mysqli_prepare($this->con, $query);
        $active = $isActive ? 1 : 0;
        mysqli_stmt_bind_param($stmt, "ii", $active, $userId);
        return mysqli_stmt_execute($stmt);
    }
}
