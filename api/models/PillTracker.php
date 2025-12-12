<?php
/**
 * Pill Tracker Model
 */

class PillTracker {
    private $conn;
    private $table = 'pill_tracking';

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Record pill taken/missed/skipped
     */
    public function record($userId, $medicineId, $scheduleId, $date, $time, $status, $notes = null) {
        // Check if record exists
        $query = "SELECT id FROM " . $this->table . " 
                  WHERE user_id = :user_id AND schedule_id = :schedule_id AND scheduled_date = :date";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':schedule_id', $scheduleId);
        $stmt->bindParam(':date', $date);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            // Update existing record
            $row = $stmt->fetch();
            $query = "UPDATE " . $this->table . " 
                      SET status = :status, taken_at = :taken_at, notes = :notes 
                      WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $takenAt = ($status === 'taken') ? date('Y-m-d H:i:s') : null;
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':taken_at', $takenAt);
            $stmt->bindParam(':notes', $notes);
            $stmt->bindParam(':id', $row['id']);
        } else {
            // Create new record
            $query = "INSERT INTO " . $this->table . " 
                      (user_id, medicine_id, schedule_id, scheduled_date, scheduled_time, status, taken_at, notes) 
                      VALUES (:user_id, :medicine_id, :schedule_id, :date, :time, :status, :taken_at, :notes)";
            $stmt = $this->conn->prepare($query);
            $takenAt = ($status === 'taken') ? date('Y-m-d H:i:s') : null;
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':medicine_id', $medicineId);
            $stmt->bindParam(':schedule_id', $scheduleId);
            $stmt->bindParam(':date', $date);
            $stmt->bindParam(':time', $time);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':taken_at', $takenAt);
            $stmt->bindParam(':notes', $notes);
        }

        return $stmt->execute();
    }

    /**
     * Get today's tracking for a user
     */
    public function getTodayTracking($userId) {
        $today = date('Y-m-d');
        $query = "SELECT pt.*, m.name as medicine_name, m.dosage, m.dose_type
                  FROM " . $this->table . " pt
                  JOIN medicines m ON pt.medicine_id = m.id
                  WHERE pt.user_id = :user_id AND pt.scheduled_date = :today
                  ORDER BY pt.scheduled_time";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':today', $today);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Get adherence statistics for a user
     */
    public function getAdherenceStats($userId, $days = 30) {
        $startDate = date('Y-m-d', strtotime("-{$days} days"));
        
        $query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'taken' THEN 1 ELSE 0 END) as taken,
                    SUM(CASE WHEN status = 'missed' THEN 1 ELSE 0 END) as missed,
                    SUM(CASE WHEN status = 'skipped' THEN 1 ELSE 0 END) as skipped,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
                  FROM " . $this->table . " 
                  WHERE user_id = :user_id AND scheduled_date >= :start_date";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':start_date', $startDate);
        $stmt->execute();

        $stats = $stmt->fetch();
        
        // Calculate adherence percentage
        $completed = $stats['total'] - $stats['pending'];
        $stats['adherence_percentage'] = $completed > 0 
            ? round(($stats['taken'] / $completed) * 100, 1) 
            : 0;

        return $stats;
    }

    /**
     * Get current streak
     */
    public function getCurrentStreak($userId) {
        $query = "SELECT scheduled_date, 
                         COUNT(*) as total,
                         SUM(CASE WHEN status = 'taken' THEN 1 ELSE 0 END) as taken
                  FROM " . $this->table . " 
                  WHERE user_id = :user_id 
                  GROUP BY scheduled_date 
                  ORDER BY scheduled_date DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();

        $streak = 0;
        $today = date('Y-m-d');
        $expectedDate = $today;

        while ($row = $stmt->fetch()) {
            // Check if this is a perfect adherence day (all pills taken)
            if ($row['scheduled_date'] == $expectedDate && $row['taken'] == $row['total']) {
                $streak++;
                $expectedDate = date('Y-m-d', strtotime($expectedDate . ' -1 day'));
            } else {
                break;
            }
        }

        return $streak;
    }

    /**
     * Get monthly analytics
     */
    public function getMonthlyAnalytics($userId, $month = null, $year = null) {
        $month = $month ?? date('m');
        $year = $year ?? date('Y');
        
        $query = "SELECT 
                    DATE(scheduled_date) as date,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'taken' THEN 1 ELSE 0 END) as taken,
                    SUM(CASE WHEN status = 'missed' THEN 1 ELSE 0 END) as missed
                  FROM " . $this->table . " 
                  WHERE user_id = :user_id 
                    AND MONTH(scheduled_date) = :month 
                    AND YEAR(scheduled_date) = :year
                  GROUP BY DATE(scheduled_date)
                  ORDER BY date";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':month', $month);
        $stmt->bindParam(':year', $year);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Get missed pills that need alerts
     */
    public function getMissedPillAlerts($userId) {
        $today = date('Y-m-d');
        $currentTime = date('H:i:s');
        
        // Get pills that are past their scheduled time and still pending
        $query = "SELECT pt.*, m.name as medicine_name, m.dosage
                  FROM " . $this->table . " pt
                  JOIN medicines m ON pt.medicine_id = m.id
                  WHERE pt.user_id = :user_id 
                    AND pt.scheduled_date = :today 
                    AND pt.status = 'pending'
                    AND pt.scheduled_time < :current_time";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':today', $today);
        $stmt->bindParam(':current_time', $currentTime);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Auto-generate tracking records for today
     */
    public function generateTodayRecords($userId) {
        $today = date('Y-m-d');
        
        // Get all active medicines with schedules that don't have today's tracking record
        $query = "INSERT INTO " . $this->table . " 
                  (user_id, medicine_id, schedule_id, scheduled_date, scheduled_time, status)
                  SELECT m.user_id, m.id, ms.id, ?, ms.scheduled_time, 'pending'
                  FROM medicines m
                  JOIN medicine_schedules ms ON m.id = ms.medicine_id
                  WHERE m.user_id = ? 
                    AND m.is_active = 1
                    AND m.start_date <= ?
                    AND (m.end_date IS NULL OR m.end_date >= ?)
                    AND NOT EXISTS (
                        SELECT 1 FROM pill_tracking pt 
                        WHERE pt.schedule_id = ms.id AND pt.scheduled_date = ?
                    )";

        $stmt = $this->conn->prepare($query);

        return $stmt->execute([$today, $userId, $today, $today, $today]);
    }

    /**
     * Get history for a specific medicine
     */
    public function getMedicineHistory($userId, $medicineId, $days = 30) {
        $startDate = date('Y-m-d', strtotime("-{$days} days"));
        
        $query = "SELECT pt.*, m.name as medicine_name
                  FROM " . $this->table . " pt
                  JOIN medicines m ON pt.medicine_id = m.id
                  WHERE pt.user_id = :user_id 
                    AND pt.medicine_id = :medicine_id
                    AND pt.scheduled_date >= :start_date
                  ORDER BY pt.scheduled_date DESC, pt.scheduled_time DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':medicine_id', $medicineId);
        $stmt->bindParam(':start_date', $startDate);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
