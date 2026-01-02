<?php
/**
 * Pill Tracker Model
 */

class PillTracker {
    private $con;
    private $table = 'pill_tracking';

    public function __construct($con) {
        $this->con = $con;
    }

    /**
     * Record pill taken/missed/skipped
     */
    public function record($userId, $medicineId, $scheduleId, $date, $time, $status, $notes = null) {
        // Check if record exists
        $query = "SELECT id FROM " . $this->table . " 
                  WHERE user_id = ? AND schedule_id = ? AND scheduled_date = ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "iis", $userId, $scheduleId, $date);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) > 0) {
            // Update existing record
            $row = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);
            
            $query = "UPDATE " . $this->table . " 
                      SET status = ?, taken_at = ?, notes = ? 
                      WHERE id = ?";
            $stmt = mysqli_prepare($this->con, $query);
            $takenAt = ($status === 'taken') ? date('Y-m-d H:i:s') : null;
            mysqli_stmt_bind_param($stmt, "sssi", $status, $takenAt, $notes, $row['id']);
        } else {
            mysqli_stmt_close($stmt);
            // Create new record
            $query = "INSERT INTO " . $this->table . " 
                      (user_id, medicine_id, schedule_id, scheduled_date, scheduled_time, status, taken_at, notes) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($this->con, $query);
            $takenAt = ($status === 'taken') ? date('Y-m-d H:i:s') : null;
            mysqli_stmt_bind_param($stmt, "iiisssss", $userId, $medicineId, $scheduleId, $date, $time, $status, $takenAt, $notes);
        }

        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $success;
    }

    /**
     * Get today's tracking for a user
     */
    public function getTodayTracking($userId) {
        $today = date('Y-m-d');
        $query = "SELECT pt.*, m.name as medicine_name, m.dosage, m.dose_type
                  FROM " . $this->table . " pt
                  JOIN medicines m ON pt.medicine_id = m.id
                  WHERE pt.user_id = ? AND pt.scheduled_date = ?
                  ORDER BY pt.scheduled_time";

        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "is", $userId, $today);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        return $rows;
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
                  WHERE user_id = ? AND scheduled_date >= ?";

        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "is", $userId, $startDate);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $stats = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
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
                  WHERE user_id = ? 
                  GROUP BY scheduled_date 
                  ORDER BY scheduled_date DESC";

        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $streak = 0;
        $today = date('Y-m-d');
        $expectedDate = $today;

        while ($row = mysqli_fetch_assoc($result)) {
            // Check if this is a perfect adherence day (all pills taken)
            if ($row['scheduled_date'] == $expectedDate && $row['taken'] == $row['total']) {
                $streak++;
                $expectedDate = date('Y-m-d', strtotime($expectedDate . ' -1 day'));
            } else {
                break;
            }
        }

        mysqli_stmt_close($stmt);
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
                  WHERE user_id = ? 
                    AND MONTH(scheduled_date) = ? 
                    AND YEAR(scheduled_date) = ?
                  GROUP BY DATE(scheduled_date)
                  ORDER BY date";

        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "iii", $userId, $month, $year);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        return $rows;
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
                  WHERE pt.user_id = ? 
                    AND pt.scheduled_date = ? 
                    AND pt.status = 'pending'
                    AND pt.scheduled_time < ?";

        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "iss", $userId, $today, $currentTime);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        return $rows;
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

        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "sisss", $today, $userId, $today, $today, $today);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $success;
    }

    /**
     * Get history for a specific medicine
     */
    public function getMedicineHistory($userId, $medicineId, $days = 30) {
        $startDate = date('Y-m-d', strtotime("-{$days} days"));
        
        $query = "SELECT pt.*, m.name as medicine_name
                  FROM " . $this->table . " pt
                  JOIN medicines m ON pt.medicine_id = m.id
                  WHERE pt.user_id = ? 
                    AND pt.medicine_id = ?
                    AND pt.scheduled_date >= ?
                  ORDER BY pt.scheduled_date DESC, pt.scheduled_time DESC";

        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "iis", $userId, $medicineId, $startDate);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        return $rows;
    }
}
