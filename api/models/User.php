<?php
/**
 * User Model
 */

class User {
    private $conn;
    private $table = 'users';

    public $id;
    public $email;
    public $password;
    public $full_name;
    public $phone;
    public $date_of_birth;
    public $gender;
    public $height_cm;
    public $weight_kg;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Register new user
     */
    public function register() {
        $query = "INSERT INTO " . $this->table . " 
                  (email, password, full_name, phone, date_of_birth, gender, height_cm, weight_kg) 
                  VALUES (:email, :password, :full_name, :phone, :dob, :gender, :height, :weight)";

        $stmt = $this->conn->prepare($query);
        
        // Hash password
        $hashedPassword = password_hash($this->password, PASSWORD_DEFAULT);

        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':full_name', $this->full_name);
        $stmt->bindParam(':phone', $this->phone);
        $stmt->bindParam(':dob', $this->date_of_birth);
        $stmt->bindParam(':gender', $this->gender);
        $stmt->bindParam(':height', $this->height_cm);
        $stmt->bindParam(':weight', $this->weight_kg);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    /**
     * Login user
     */
    public function login() {
        $query = "SELECT id, email, password, full_name FROM " . $this->table . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $this->email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch();
            if (password_verify($this->password, $row['password'])) {
                $this->id = $row['id'];
                $this->full_name = $row['full_name'];
                return true;
            }
        }
        return false;
    }

    /**
     * Get user by ID
     */
    public function getById($id) {
        $query = "SELECT id, email, full_name, phone, date_of_birth, gender, height_cm, weight_kg, created_at 
                  FROM " . $this->table . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetch();
    }

    /**
     * Update user profile
     */
    public function update() {
        $query = "UPDATE " . $this->table . " 
                  SET full_name = :full_name, phone = :phone, date_of_birth = :dob, 
                      gender = :gender, height_cm = :height, weight_kg = :weight 
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':full_name', $this->full_name);
        $stmt->bindParam(':phone', $this->phone);
        $stmt->bindParam(':dob', $this->date_of_birth);
        $stmt->bindParam(':gender', $this->gender);
        $stmt->bindParam(':height', $this->height_cm);
        $stmt->bindParam(':weight', $this->weight_kg);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }

    /**
     * Check if email exists
     */
    public function emailExists() {
        $query = "SELECT id FROM " . $this->table . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $this->email);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Get user health conditions
     */
    public function getHealthConditions($userId) {
        $query = "SELECT hc.id, hc.name, hc.description, hc.dietary_restrictions, 
                         uhc.diagnosed_date, uhc.notes 
                  FROM health_conditions hc 
                  JOIN user_health_conditions uhc ON hc.id = uhc.condition_id 
                  WHERE uhc.user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Add health condition to user
     */
    public function addHealthCondition($userId, $conditionId, $diagnosedDate = null, $notes = null) {
        $query = "INSERT INTO user_health_conditions (user_id, condition_id, diagnosed_date, notes) 
                  VALUES (:user_id, :condition_id, :diagnosed_date, :notes)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':condition_id', $conditionId);
        $stmt->bindParam(':diagnosed_date', $diagnosedDate);
        $stmt->bindParam(':notes', $notes);

        return $stmt->execute();
    }

    /**
     * Remove health condition from user
     */
    public function removeHealthCondition($userId, $conditionId) {
        $query = "DELETE FROM user_health_conditions WHERE user_id = :user_id AND condition_id = :condition_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':condition_id', $conditionId);

        return $stmt->execute();
    }
}
