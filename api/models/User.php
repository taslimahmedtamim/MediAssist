<?php
/**
 * User Model - Updated with Doctor-Patient System
 */

class User {
    private $con;
    private $table = 'users';

    public $id;
    public $email;
    public $username;
    public $password;
    public $full_name;
    public $phone;
    public $date_of_birth;
    public $gender;
    public $height_cm;
    public $weight_kg;
    public $role;
    public $specialization;
    public $license_number;
    public $clinic_name;
    public $clinic_address;

    public function __construct($con) {
        $this->con = $con;
    }

    /**
     * Register new user
     */
    public function register() {
        $hashedPassword = password_hash($this->password, PASSWORD_DEFAULT);

        // Generate username from email if not provided
        if (empty($this->username)) {
            $this->username = explode('@', $this->email)[0] . '_' . substr(uniqid(), -4);
        }

        $query = "INSERT INTO " . $this->table . " 
                  (email, username, password, full_name, phone, date_of_birth, gender, height_cm, weight_kg, role, specialization, license_number, clinic_name, clinic_address) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($this->con, $query);
        $role = $this->role ?? 'patient';
        mysqli_stmt_bind_param($stmt, "ssssssssdsssss", 
            $this->email, $this->username, $hashedPassword, $this->full_name, $this->phone, 
            $this->date_of_birth, $this->gender, $this->height_cm, $this->weight_kg,
            $role, $this->specialization, $this->license_number, $this->clinic_name, $this->clinic_address);

        if (mysqli_stmt_execute($stmt)) {
            $this->id = mysqli_insert_id($this->con);
            mysqli_stmt_close($stmt);
            return true;
        }
        mysqli_stmt_close($stmt);
        return false;
    }

    /**
     * Login user
     */
    public function login() {
        $query = "SELECT id, email, username, password, full_name, role FROM " . $this->table . " WHERE email = ? LIMIT 1";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "s", $this->email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            if (password_verify($this->password, $row['password'])) {
                $this->id = $row['id'];
                $this->full_name = $row['full_name'];
                $this->role = $row['role'];
                $this->username = $row['username'];
                mysqli_stmt_close($stmt);
                return true;
            }
        }
        mysqli_stmt_close($stmt);
        return false;
    }

    /**
     * Get user by ID
     */
    public function getById($id) {
        $query = "SELECT id, email, username, full_name, phone, date_of_birth, gender, height_cm, weight_kg, role, specialization, license_number, clinic_name, clinic_address, created_at 
                  FROM " . $this->table . " WHERE id = ? LIMIT 1";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $row;
    }

    /**
     * Update user profile
     */
    public function update() {
        $query = "UPDATE " . $this->table . " 
                  SET full_name = ?, phone = ?, date_of_birth = ?, 
                      gender = ?, height_cm = ?, weight_kg = ? 
                  WHERE id = ?";

        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "ssssddi", 
            $this->full_name, $this->phone, $this->date_of_birth, 
            $this->gender, $this->height_cm, $this->weight_kg, $this->id);

        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $success;
    }

    /**
     * Check if email exists
     */
    public function emailExists() {
        $query = "SELECT id FROM " . $this->table . " WHERE email = ? LIMIT 1";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "s", $this->email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $exists = mysqli_num_rows($result) > 0;
        mysqli_stmt_close($stmt);
        return $exists;
    }

    /**
     * Get user health conditions
     */
    public function getHealthConditions($userId) {
        $query = "SELECT hc.id, hc.name, hc.description, hc.dietary_restrictions, 
                         uhc.diagnosed_date, uhc.notes 
                  FROM health_conditions hc 
                  JOIN user_health_conditions uhc ON hc.id = uhc.condition_id 
                  WHERE uhc.user_id = ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        return $rows;
    }

    /**
     * Add health condition to user
     */
    public function addHealthCondition($userId, $conditionId, $diagnosedDate = null, $notes = null) {
        $query = "INSERT INTO user_health_conditions (user_id, condition_id, diagnosed_date, notes) 
                  VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "iiss", $userId, $conditionId, $diagnosedDate, $notes);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $success;
    }

    /**
     * Remove health condition from user
     */
    public function removeHealthCondition($userId, $conditionId) {
        $query = "DELETE FROM user_health_conditions WHERE user_id = ? AND condition_id = ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "ii", $userId, $conditionId);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $success;
    }

    /**
     * Check if username exists
     */
    public function usernameExists($username) {
        $query = "SELECT id FROM " . $this->table . " WHERE username = ? LIMIT 1";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $exists = mysqli_num_rows($result) > 0;
        mysqli_stmt_close($stmt);
        return $exists;
    }

    /**
     * Search users by username (for doctors to find patients)
     */
    public function searchByUsername($username, $role = null) {
        $query = "SELECT id, email, username, full_name, phone, role, created_at 
                  FROM " . $this->table . " WHERE username LIKE ?";
        if ($role) {
            $query .= " AND role = ?";
        }
        $query .= " LIMIT 20";
        
        $stmt = mysqli_prepare($this->con, $query);
        $searchTerm = '%' . $username . '%';
        if ($role) {
            mysqli_stmt_bind_param($stmt, "ss", $searchTerm, $role);
        } else {
            mysqli_stmt_bind_param($stmt, "s", $searchTerm);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        return $rows;
    }

    /**
     * Get user by username
     */
    public function getByUsername($username) {
        $query = "SELECT id, email, username, full_name, phone, date_of_birth, gender, height_cm, weight_kg, role, created_at 
                  FROM " . $this->table . " WHERE username = ? LIMIT 1";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $row;
    }

    // =====================================================
    // DOCTOR-PATIENT RELATIONSHIP FUNCTIONS
    // =====================================================

    /**
     * Add patient to doctor's list
     */
    public function addPatient($doctorId, $patientId, $notes = null) {
        // Check if relationship already exists
        $checkQuery = "SELECT id FROM doctor_patients WHERE doctor_id = ? AND patient_id = ?";
        $checkStmt = mysqli_prepare($this->con, $checkQuery);
        mysqli_stmt_bind_param($checkStmt, "ii", $doctorId, $patientId);
        mysqli_stmt_execute($checkStmt);
        $result = mysqli_stmt_get_result($checkStmt);
        
        if (mysqli_num_rows($result) > 0) {
            // Update existing relationship
            $row = mysqli_fetch_assoc($result);
            mysqli_stmt_close($checkStmt);
            $updateQuery = "UPDATE doctor_patients SET status = 'active', notes = ? WHERE id = ?";
            $updateStmt = mysqli_prepare($this->con, $updateQuery);
            mysqli_stmt_bind_param($updateStmt, "si", $notes, $row['id']);
            $success = mysqli_stmt_execute($updateStmt);
            mysqli_stmt_close($updateStmt);
            return $success;
        }
        mysqli_stmt_close($checkStmt);

        $query = "INSERT INTO doctor_patients (doctor_id, patient_id, notes) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "iis", $doctorId, $patientId, $notes);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $success;
    }

    /**
     * Remove patient from doctor's list
     */
    public function removePatient($doctorId, $patientId) {
        $query = "UPDATE doctor_patients SET status = 'inactive' WHERE doctor_id = ? AND patient_id = ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "ii", $doctorId, $patientId);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $success;
    }

    /**
     * Get all patients for a doctor
     */
    public function getDoctorPatients($doctorId) {
        $query = "SELECT u.id, u.email, u.username, u.full_name, u.phone, u.date_of_birth, u.gender, 
                         dp.created_at as added_on, dp.notes, dp.status
                  FROM users u 
                  JOIN doctor_patients dp ON u.id = dp.patient_id 
                  WHERE dp.doctor_id = ? AND dp.status = 'active'
                  ORDER BY u.full_name";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $doctorId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        return $rows;
    }

    /**
     * Get all doctors for a patient
     */
    public function getPatientDoctors($patientId) {
        $query = "SELECT u.id, u.email, u.username, u.full_name, u.phone, u.specialization, 
                         u.clinic_name, u.clinic_address, dp.created_at as assigned_on
                  FROM users u 
                  JOIN doctor_patients dp ON u.id = dp.doctor_id 
                  WHERE dp.patient_id = ? AND dp.status = 'active'
                  ORDER BY u.full_name";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $patientId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        return $rows;
    }

    /**
     * Check if doctor has access to patient
     */
    public function doctorHasPatient($doctorId, $patientId) {
        $query = "SELECT id FROM doctor_patients WHERE doctor_id = ? AND patient_id = ? AND status = 'active' LIMIT 1";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "ii", $doctorId, $patientId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $exists = mysqli_num_rows($result) > 0;
        mysqli_stmt_close($stmt);
        return $exists;
    }

    /**
     * Create patient account by doctor
     */
    public function createPatientByDoctor($doctorId, $patientData) {
        // Set patient role
        $this->email = $patientData['email'];
        $this->username = $patientData['username'] ?? null;
        $this->password = $patientData['password'];
        $this->full_name = $patientData['full_name'];
        $this->phone = $patientData['phone'] ?? null;
        $this->date_of_birth = $patientData['date_of_birth'] ?? null;
        $this->gender = $patientData['gender'] ?? null;
        $this->height_cm = $patientData['height_cm'] ?? null;
        $this->weight_kg = $patientData['weight_kg'] ?? null;
        $this->role = 'patient';

        if ($this->register()) {
            // Add patient to doctor's list
            $this->addPatient($doctorId, $this->id, 'Created by doctor');
            return $this->id;
        }
        return false;
    }

    /**
     * Get patient compliance statistics
     */
    public function getPatientCompliance($patientId, $days = 7) {
        $query = "SELECT 
                    DATE(scheduled_date) as date,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'taken' THEN 1 ELSE 0 END) as taken,
                    SUM(CASE WHEN status = 'missed' THEN 1 ELSE 0 END) as missed,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
                  FROM pill_tracking 
                  WHERE user_id = ? AND scheduled_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                  GROUP BY DATE(scheduled_date)
                  ORDER BY date DESC";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "ii", $patientId, $days);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        return $rows;
    }

    /**
     * Get overall compliance percentage for a patient
     */
    public function getPatientOverallCompliance($patientId, $days = 30) {
        $query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'taken' THEN 1 ELSE 0 END) as taken,
                    SUM(CASE WHEN status = 'missed' THEN 1 ELSE 0 END) as missed
                  FROM pill_tracking 
                  WHERE user_id = ? AND scheduled_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "ii", $patientId, $days);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if ($row['total'] > 0) {
            $row['compliance_percentage'] = round(($row['taken'] / $row['total']) * 100, 2);
        } else {
            $row['compliance_percentage'] = 0;
        }
        return $row;
    }

    /**
     * Check if user is a doctor
     */
    public function isDoctor($userId) {
        $query = "SELECT role FROM " . $this->table . " WHERE id = ? LIMIT 1";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $row && $row['role'] === 'doctor';
    }
}
