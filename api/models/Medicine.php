<?php
/**
 * Medicine Model
 */

class Medicine {
    private $con;
    private $table = 'medicines';

    public $id;
    public $user_id;
    public $name;
    public $dosage;
    public $dose_type;
    public $frequency;
    public $duration_days;
    public $start_date;
    public $end_date;
    public $instructions;
    public $is_active;

    public function __construct($con) {
        $this->con = $con;
    }

    /**
     * Add new medicine
     */
    public function create() {
        $query = "INSERT INTO " . $this->table . " 
                  (user_id, name, dosage, dose_type, frequency, duration_days, start_date, end_date, instructions) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "issssisss", 
            $this->user_id, $this->name, $this->dosage, $this->dose_type, 
            $this->frequency, $this->duration_days, $this->start_date, $this->end_date, $this->instructions);

        if (mysqli_stmt_execute($stmt)) {
            $this->id = mysqli_insert_id($this->con);
            mysqli_stmt_close($stmt);
            return true;
        }
        mysqli_stmt_close($stmt);
        return false;
    }

    /**
     * Get all medicines for a user
     */
    public function getByUser($userId, $activeOnly = true) {
        $query = "SELECT m.* FROM " . $this->table . " m WHERE m.user_id = ?";
        
        if ($activeOnly) {
            $query .= " AND m.is_active = 1";
        }
        
        $query .= " ORDER BY m.created_at DESC";

        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $medicines = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        
        // Now get schedules for each medicine
        foreach ($medicines as &$medicine) {
            $scheduleQuery = "SELECT id, scheduled_time, dose_amount, meal_relation 
                              FROM medicine_schedules 
                              WHERE medicine_id = ?";
            $scheduleStmt = mysqli_prepare($this->con, $scheduleQuery);
            mysqli_stmt_bind_param($scheduleStmt, "i", $medicine['id']);
            mysqli_stmt_execute($scheduleStmt);
            $scheduleResult = mysqli_stmt_get_result($scheduleStmt);
            $medicine['schedules'] = mysqli_fetch_all($scheduleResult, MYSQLI_ASSOC);
            mysqli_stmt_close($scheduleStmt);
        }

        return $medicines;
    }

    /**
     * Get medicine by ID
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
     * Update medicine
     */
    public function update() {
        $query = "UPDATE " . $this->table . " 
                  SET name = ?, dosage = ?, dose_type = ?, 
                      frequency = ?, duration_days = ?, 
                      end_date = ?, instructions = ?, is_active = ? 
                  WHERE id = ? AND user_id = ?";

        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "ssssissiii", 
            $this->name, $this->dosage, $this->dose_type, 
            $this->frequency, $this->duration_days, 
            $this->end_date, $this->instructions, $this->is_active, $this->id, $this->user_id);

        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $success;
    }

    /**
     * Delete medicine
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
     * Add schedule to medicine
     */
    public function addSchedule($medicineId, $time, $doseAmount, $mealRelation = 'anytime') {
        $query = "INSERT INTO medicine_schedules (medicine_id, scheduled_time, dose_amount, meal_relation) 
                  VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "isds", $medicineId, $time, $doseAmount, $mealRelation);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $success;
    }

    /**
     * Get schedules for a medicine
     */
    public function getSchedules($medicineId) {
        $query = "SELECT * FROM medicine_schedules WHERE medicine_id = ? ORDER BY scheduled_time";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $medicineId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        return $rows;
    }

    /**
     * Delete all schedules for a medicine
     */
    public function deleteSchedules($medicineId) {
        $query = "DELETE FROM medicine_schedules WHERE medicine_id = ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $medicineId);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $success;
    }

    /**
     * Get today's medicines for a user
     */
    public function getTodaysMedicines($userId) {
        $today = date('Y-m-d');
        $query = "SELECT m.id, m.name, m.dosage, m.dose_type, m.instructions, m.assigned_by,
                         ms.id as schedule_id, ms.scheduled_time, ms.dose_amount, ms.meal_relation,
                         pt.status, pt.taken_at,
                         u.full_name as assigned_by_name
                  FROM " . $this->table . " m
                  JOIN medicine_schedules ms ON m.id = ms.medicine_id
                  LEFT JOIN pill_tracking pt ON ms.id = pt.schedule_id AND pt.scheduled_date = ?
                  LEFT JOIN users u ON m.assigned_by = u.id
                  WHERE m.user_id = ? 
                    AND m.is_active = 1
                    AND m.start_date <= ?
                    AND (m.end_date IS NULL OR m.end_date >= ?)
                  ORDER BY ms.scheduled_time";

        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "siss", $today, $userId, $today, $today);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        return $rows;
    }

    /**
     * Create medicine with doctor assignment
     */
    public function createWithDoctor($doctorId, $prescriptionNotes = null) {
        $query = "INSERT INTO " . $this->table . " 
                  (user_id, assigned_by, name, dosage, dose_type, frequency, duration_days, start_date, end_date, instructions, prescription_notes) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "iisssssisss", 
            $this->user_id, $doctorId, $this->name, $this->dosage, $this->dose_type, 
            $this->frequency, $this->duration_days, $this->start_date, $this->end_date, 
            $this->instructions, $prescriptionNotes);

        if (mysqli_stmt_execute($stmt)) {
            $this->id = mysqli_insert_id($this->con);
            mysqli_stmt_close($stmt);
            return true;
        }
        mysqli_stmt_close($stmt);
        return false;
    }

    /**
     * Get medicines assigned by a specific doctor
     */
    public function getByDoctor($doctorId, $patientId = null) {
        $query = "SELECT m.*, u.full_name as patient_name 
                  FROM " . $this->table . " m
                  JOIN users u ON m.user_id = u.id
                  WHERE m.assigned_by = ?";
        
        if ($patientId) {
            $query .= " AND m.user_id = ?";
        }
        
        $query .= " ORDER BY m.created_at DESC";

        $stmt = mysqli_prepare($this->con, $query);
        if ($patientId) {
            mysqli_stmt_bind_param($stmt, "ii", $doctorId, $patientId);
        } else {
            mysqli_stmt_bind_param($stmt, "i", $doctorId);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $medicines = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        return $medicines;
    }
}
