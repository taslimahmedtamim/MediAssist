<?php
/**
 * Medicine Model
 */

class Medicine {
    private $conn;
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

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Add new medicine
     */
    public function create() {
        $query = "INSERT INTO " . $this->table . " 
                  (user_id, name, dosage, dose_type, frequency, duration_days, start_date, end_date, instructions) 
                  VALUES (:user_id, :name, :dosage, :dose_type, :frequency, :duration, :start_date, :end_date, :instructions)";

        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':user_id', $this->user_id);
        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':dosage', $this->dosage);
        $stmt->bindParam(':dose_type', $this->dose_type);
        $stmt->bindParam(':frequency', $this->frequency);
        $stmt->bindParam(':duration', $this->duration_days);
        $stmt->bindParam(':start_date', $this->start_date);
        $stmt->bindParam(':end_date', $this->end_date);
        $stmt->bindParam(':instructions', $this->instructions);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    /**
     * Get all medicines for a user
     */
    public function getByUser($userId, $activeOnly = true) {
        // First get all medicines
        $query = "SELECT m.* FROM " . $this->table . " m WHERE m.user_id = :user_id";
        
        if ($activeOnly) {
            $query .= " AND m.is_active = 1";
        }
        
        $query .= " ORDER BY m.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();

        $medicines = $stmt->fetchAll();
        
        // Now get schedules for each medicine
        foreach ($medicines as &$medicine) {
            $scheduleQuery = "SELECT id, scheduled_time, dose_amount, meal_relation 
                              FROM medicine_schedules 
                              WHERE medicine_id = :medicine_id";
            $scheduleStmt = $this->conn->prepare($scheduleQuery);
            $scheduleStmt->bindParam(':medicine_id', $medicine['id']);
            $scheduleStmt->execute();
            $medicine['schedules'] = $scheduleStmt->fetchAll();
        }

        return $medicines;
    }

    /**
     * Get medicine by ID
     */
    public function getById($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetch();
    }

    /**
     * Update medicine
     */
    public function update() {
        $query = "UPDATE " . $this->table . " 
                  SET name = :name, dosage = :dosage, dose_type = :dose_type, 
                      frequency = :frequency, duration_days = :duration, 
                      end_date = :end_date, instructions = :instructions, is_active = :is_active 
                  WHERE id = :id AND user_id = :user_id";

        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':dosage', $this->dosage);
        $stmt->bindParam(':dose_type', $this->dose_type);
        $stmt->bindParam(':frequency', $this->frequency);
        $stmt->bindParam(':duration', $this->duration_days);
        $stmt->bindParam(':end_date', $this->end_date);
        $stmt->bindParam(':instructions', $this->instructions);
        $stmt->bindParam(':is_active', $this->is_active);
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':user_id', $this->user_id);

        return $stmt->execute();
    }

    /**
     * Delete medicine
     */
    public function delete($id, $userId) {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id AND user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':user_id', $userId);

        return $stmt->execute();
    }

    /**
     * Add schedule to medicine
     */
    public function addSchedule($medicineId, $time, $doseAmount, $mealRelation = 'anytime') {
        $query = "INSERT INTO medicine_schedules (medicine_id, scheduled_time, dose_amount, meal_relation) 
                  VALUES (:medicine_id, :time, :dose, :meal)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':medicine_id', $medicineId);
        $stmt->bindParam(':time', $time);
        $stmt->bindParam(':dose', $doseAmount);
        $stmt->bindParam(':meal', $mealRelation);

        return $stmt->execute();
    }

    /**
     * Get schedules for a medicine
     */
    public function getSchedules($medicineId) {
        $query = "SELECT * FROM medicine_schedules WHERE medicine_id = :medicine_id ORDER BY scheduled_time";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':medicine_id', $medicineId);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Delete all schedules for a medicine
     */
    public function deleteSchedules($medicineId) {
        $query = "DELETE FROM medicine_schedules WHERE medicine_id = :medicine_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':medicine_id', $medicineId);

        return $stmt->execute();
    }

    /**
     * Get today's medicines for a user
     */
    public function getTodaysMedicines($userId) {
        $today = date('Y-m-d');
        $query = "SELECT m.id, m.name, m.dosage, m.dose_type, m.instructions,
                         ms.id as schedule_id, ms.scheduled_time, ms.dose_amount, ms.meal_relation,
                         pt.status, pt.taken_at
                  FROM " . $this->table . " m
                  JOIN medicine_schedules ms ON m.id = ms.medicine_id
                  LEFT JOIN pill_tracking pt ON ms.id = pt.schedule_id AND pt.scheduled_date = ?
                  WHERE m.user_id = ? 
                    AND m.is_active = 1
                    AND m.start_date <= ?
                    AND (m.end_date IS NULL OR m.end_date >= ?)
                  ORDER BY ms.scheduled_time";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([$today, $userId, $today, $today]);

        return $stmt->fetchAll();
    }
}
