<?php
// Emergency & Safety Model - Handles emergency info, caregiver access

class EmergencySafety {
    private $con;
    
    public function __construct($con) {
        $this->con = $con;
    }
    
    // =====================================================
    // EMERGENCY INFORMATION CARD
    // =====================================================
    
    public function getEmergencyInfo($userId) {
        $query = "SELECT ei.*, u.full_name, u.date_of_birth, u.gender
                  FROM emergency_info ei
                  JOIN users u ON ei.user_id = u.id
                  WHERE ei.user_id = ?";
        
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        
        if (!$info) {
            return null;
        }
        
        // Get current medications
        $medQuery = "SELECT name, dosage, dose_type FROM medicines 
                     WHERE user_id = ? AND is_active = 1";
        $stmt = mysqli_prepare($this->con, $medQuery);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $medications = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
        
        // Get health conditions
        $condQuery = "SELECT hc.name FROM user_health_conditions uhc
                      JOIN health_conditions hc ON uhc.condition_id = hc.id
                      WHERE uhc.user_id = ?";
        $stmt = mysqli_prepare($this->con, $condQuery);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $conditions = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
        
        $info['current_medications'] = $medications;
        $info['health_conditions'] = array_column($conditions, 'name');
        
        return $info;
    }
    
    public function getEmergencyInfoByCode($accessCode) {
        $query = "SELECT ei.*, u.full_name, u.date_of_birth, u.gender, u.id as user_id
                  FROM emergency_info ei
                  JOIN users u ON ei.user_id = u.id
                  WHERE ei.access_code = ?";
        
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "s", $accessCode);
        mysqli_stmt_execute($stmt);
        $info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        
        if (!$info) {
            return null;
        }
        
        $userId = $info['user_id'];
        
        // Get current medications
        $medQuery = "SELECT name, dosage, dose_type FROM medicines 
                     WHERE user_id = ? AND is_active = 1";
        $stmt = mysqli_prepare($this->con, $medQuery);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $info['current_medications'] = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
        
        // Get health conditions
        $condQuery = "SELECT hc.name FROM user_health_conditions uhc
                      JOIN health_conditions hc ON uhc.condition_id = hc.id
                      WHERE uhc.user_id = ?";
        $stmt = mysqli_prepare($this->con, $condQuery);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $conditions = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
        $info['health_conditions'] = array_column($conditions, 'name');
        
        // Remove sensitive info for public access
        unset($info['user_id']);
        unset($info['id']);
        
        return $info;
    }
    
    public function saveEmergencyInfo($userId, $data) {
        // Generate access code if not exists
        $existingQuery = "SELECT access_code FROM emergency_info WHERE user_id = ?";
        $stmt = mysqli_prepare($this->con, $existingQuery);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        
        $accessCode = $existing['access_code'] ?? $this->generateAccessCode();
        
        $query = "INSERT INTO emergency_info (
                    user_id, blood_group, allergies, 
                    emergency_contact_name, emergency_contact_phone, emergency_contact_relation,
                    secondary_contact_name, secondary_contact_phone,
                    doctor_name, doctor_phone, hospital_preference,
                    insurance_info, special_instructions, access_code
                  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                  ON DUPLICATE KEY UPDATE
                    blood_group = VALUES(blood_group),
                    allergies = VALUES(allergies),
                    emergency_contact_name = VALUES(emergency_contact_name),
                    emergency_contact_phone = VALUES(emergency_contact_phone),
                    emergency_contact_relation = VALUES(emergency_contact_relation),
                    secondary_contact_name = VALUES(secondary_contact_name),
                    secondary_contact_phone = VALUES(secondary_contact_phone),
                    doctor_name = VALUES(doctor_name),
                    doctor_phone = VALUES(doctor_phone),
                    hospital_preference = VALUES(hospital_preference),
                    insurance_info = VALUES(insurance_info),
                    special_instructions = VALUES(special_instructions)";
        
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "isssssssssssss",
            $userId,
            $data['blood_group'],
            $data['allergies'],
            $data['emergency_contact_name'],
            $data['emergency_contact_phone'],
            $data['emergency_contact_relation'],
            $data['secondary_contact_name'],
            $data['secondary_contact_phone'],
            $data['doctor_name'],
            $data['doctor_phone'],
            $data['hospital_preference'],
            $data['insurance_info'],
            $data['special_instructions'],
            $accessCode
        );
        
        if (mysqli_stmt_execute($stmt)) {
            return ['success' => true, 'access_code' => $accessCode];
        }
        return ['success' => false, 'error' => mysqli_error($this->con)];
    }
    
    private function generateAccessCode() {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $code;
    }
    
    public function regenerateAccessCode($userId) {
        $newCode = $this->generateAccessCode();
        
        $query = "UPDATE emergency_info SET access_code = ? WHERE user_id = ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "si", $newCode, $userId);
        
        if (mysqli_stmt_execute($stmt)) {
            return $newCode;
        }
        return false;
    }
    
    // =====================================================
    // CAREGIVER ACCESS
    // =====================================================
    
    public function addCaregiver($patientUserId, $caregiverEmail, $caregiverName, $accessLevel = 'view_only', $permissions = []) {
        // Generate verification token
        $verificationToken = bin2hex(random_bytes(32));
        
        $query = "INSERT INTO caregivers (
                    patient_user_id, caregiver_email, caregiver_name, access_level,
                    can_view_medicines, can_view_adherence, can_view_reports, can_view_diet,
                    receive_missed_alerts, verification_token
                  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $canViewMedicines = $permissions['can_view_medicines'] ?? true;
        $canViewAdherence = $permissions['can_view_adherence'] ?? true;
        $canViewReports = $permissions['can_view_reports'] ?? false;
        $canViewDiet = $permissions['can_view_diet'] ?? false;
        $receiveMissedAlerts = $permissions['receive_missed_alerts'] ?? true;
        
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "isssiiiiss",
            $patientUserId, $caregiverEmail, $caregiverName, $accessLevel,
            $canViewMedicines, $canViewAdherence, $canViewReports, $canViewDiet,
            $receiveMissedAlerts, $verificationToken
        );
        
        if (mysqli_stmt_execute($stmt)) {
            return [
                'id' => mysqli_insert_id($this->con),
                'verification_token' => $verificationToken
            ];
        }
        return false;
    }
    
    public function verifyCaregiverAccess($token) {
        $query = "UPDATE caregivers SET is_verified = 1 WHERE verification_token = ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "s", $token);
        
        if (mysqli_stmt_execute($stmt) && mysqli_affected_rows($this->con) > 0) {
            return true;
        }
        return false;
    }
    
    public function getCaregivers($patientUserId) {
        $query = "SELECT id, caregiver_email, caregiver_name, access_level,
                         can_view_medicines, can_view_adherence, can_view_reports, can_view_diet,
                         receive_missed_alerts, is_verified, created_at
                  FROM caregivers
                  WHERE patient_user_id = ?
                  ORDER BY created_at DESC";
        
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $patientUserId);
        mysqli_stmt_execute($stmt);
        return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    }
    
    public function updateCaregiverPermissions($caregiverId, $patientUserId, $permissions) {
        $query = "UPDATE caregivers SET 
                    access_level = ?,
                    can_view_medicines = ?,
                    can_view_adherence = ?,
                    can_view_reports = ?,
                    can_view_diet = ?,
                    receive_missed_alerts = ?
                  WHERE id = ? AND patient_user_id = ?";
        
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "siiiiii",
            $permissions['access_level'],
            $permissions['can_view_medicines'],
            $permissions['can_view_adherence'],
            $permissions['can_view_reports'],
            $permissions['can_view_diet'],
            $permissions['receive_missed_alerts'],
            $caregiverId,
            $patientUserId
        );
        
        return mysqli_stmt_execute($stmt);
    }
    
    public function removeCaregiver($caregiverId, $patientUserId) {
        $query = "DELETE FROM caregivers WHERE id = ? AND patient_user_id = ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "ii", $caregiverId, $patientUserId);
        return mysqli_stmt_execute($stmt);
    }
    
    public function getCaregiverPatientData($caregiverEmail, $patientUserId) {
        // Check if caregiver has access
        $accessQuery = "SELECT * FROM caregivers 
                        WHERE caregiver_email = ? AND patient_user_id = ? AND is_verified = 1";
        $stmt = mysqli_prepare($this->con, $accessQuery);
        mysqli_stmt_bind_param($stmt, "si", $caregiverEmail, $patientUserId);
        mysqli_stmt_execute($stmt);
        $caregiver = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        
        if (!$caregiver) {
            return ['error' => 'Access denied'];
        }
        
        return $this->fetchCaregiverPatientDataWithPermissions($caregiver, $patientUserId);
    }
    
    // Alternative method using access code only
    public function getCaregiverPatientDataByAccessCode($accessCode) {
        // Find caregiver by access code
        $accessQuery = "SELECT * FROM caregivers 
                        WHERE access_code = ? AND is_verified = 1";
        $stmt = mysqli_prepare($this->con, $accessQuery);
        mysqli_stmt_bind_param($stmt, "s", $accessCode);
        mysqli_stmt_execute($stmt);
        $caregiver = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        
        if (!$caregiver) {
            return null;
        }
        
        return $this->fetchCaregiverPatientDataWithPermissions($caregiver, $caregiver['patient_user_id']);
    }
    
    private function fetchCaregiverPatientDataWithPermissions($caregiver, $patientUserId) {
        $data = ['patient_id' => $patientUserId];
        
        // Get patient basic info
        $userQuery = "SELECT full_name, date_of_birth, gender FROM users WHERE id = ?";
        $stmt = mysqli_prepare($this->con, $userQuery);
        mysqli_stmt_bind_param($stmt, "i", $patientUserId);
        mysqli_stmt_execute($stmt);
        $data['patient'] = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        
        // Get data based on permissions
        if ($caregiver['can_view_medicines']) {
            $medQuery = "SELECT name, dosage, dose_type, is_active FROM medicines WHERE user_id = ?";
            $stmt = mysqli_prepare($this->con, $medQuery);
            mysqli_stmt_bind_param($stmt, "i", $patientUserId);
            mysqli_stmt_execute($stmt);
            $data['medicines'] = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
        }
        
        if ($caregiver['can_view_adherence']) {
            $adherenceQuery = "SELECT 
                                COUNT(*) as total,
                                SUM(CASE WHEN status = 'taken' THEN 1 ELSE 0 END) as taken,
                                SUM(CASE WHEN status = 'missed' THEN 1 ELSE 0 END) as missed
                              FROM pill_tracking
                              WHERE user_id = ? AND scheduled_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            $stmt = mysqli_prepare($this->con, $adherenceQuery);
            mysqli_stmt_bind_param($stmt, "i", $patientUserId);
            mysqli_stmt_execute($stmt);
            $adherence = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            $data['adherence'] = [
                'total_doses' => $adherence['total'],
                'taken' => $adherence['taken'],
                'missed' => $adherence['missed'],
                'rate' => $adherence['total'] > 0 ? round(($adherence['taken'] / $adherence['total']) * 100) : 0
            ];
        }
        
        return $data;
    }
    
    public function getCaregiversToAlert($patientUserId) {
        $query = "SELECT caregiver_email, caregiver_name FROM caregivers
                  WHERE patient_user_id = ? AND is_verified = 1 AND receive_missed_alerts = 1";
        
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $patientUserId);
        mysqli_stmt_execute($stmt);
        return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    }
    
    // =====================================================
    // ADDITIONAL METHODS
    // =====================================================
    
    public function generateQRCodeData($userId) {
        // Get or generate access code
        $query = "SELECT access_code FROM emergency_info WHERE user_id = ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        
        $accessCode = $result['access_code'] ?? null;
        
        if (!$accessCode) {
            // Create emergency info with new access code
            $accessCode = $this->generateAccessCode();
            $insertQuery = "INSERT INTO emergency_info (user_id, access_code) VALUES (?, ?)
                           ON DUPLICATE KEY UPDATE access_code = ?";
            $stmt = mysqli_prepare($this->con, $insertQuery);
            mysqli_stmt_bind_param($stmt, "iss", $userId, $accessCode, $accessCode);
            mysqli_stmt_execute($stmt);
        }
        
        return [
            'access_code' => $accessCode,
            'url' => '/emergency.php?code=' . $accessCode
        ];
    }
    
    public function updateCaregiver($caregiverId, $patientUserId, $permissions = null, $isActive = null) {
        $updates = [];
        $params = [];
        $types = "";
        
        if ($permissions !== null) {
            $updates[] = "can_view_medicines = ?";
            $updates[] = "can_view_adherence = ?";
            $updates[] = "can_view_reports = ?";
            $updates[] = "can_view_diet = ?";
            $updates[] = "receive_missed_alerts = ?";
            $params[] = $permissions['can_view_medicines'] ?? 1;
            $params[] = $permissions['can_view_adherence'] ?? 1;
            $params[] = $permissions['can_view_reports'] ?? 0;
            $params[] = $permissions['can_view_diet'] ?? 0;
            $params[] = $permissions['receive_missed_alerts'] ?? 1;
            $types .= "iiiii";
        }
        
        if ($isActive !== null) {
            $updates[] = "is_verified = ?";
            $params[] = $isActive ? 1 : 0;
            $types .= "i";
        }
        
        if (empty($updates)) {
            return true;
        }
        
        $params[] = $caregiverId;
        $params[] = $patientUserId;
        $types .= "ii";
        
        $query = "UPDATE caregivers SET " . implode(", ", $updates) . " WHERE id = ? AND patient_user_id = ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        
        return mysqli_stmt_execute($stmt);
    }
    
    // =====================================================
    // EMERGENCY CONTACTS (separate from info card)
    // =====================================================
    
    public function getEmergencyContacts($userId) {
        $query = "SELECT id, name, phone, relationship, is_primary, created_at
                  FROM emergency_contacts
                  WHERE user_id = ?
                  ORDER BY is_primary DESC, created_at ASC";
        
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    }
    
    public function addEmergencyContact($userId, $name, $phone, $relationship, $isPrimary = false) {
        // If setting as primary, unset other primaries
        if ($isPrimary) {
            $unsetQuery = "UPDATE emergency_contacts SET is_primary = 0 WHERE user_id = ?";
            $stmt = mysqli_prepare($this->con, $unsetQuery);
            mysqli_stmt_bind_param($stmt, "i", $userId);
            mysqli_stmt_execute($stmt);
        }
        
        $query = "INSERT INTO emergency_contacts (user_id, name, phone, relationship, is_primary)
                  VALUES (?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($this->con, $query);
        $primary = $isPrimary ? 1 : 0;
        mysqli_stmt_bind_param($stmt, "isssi", $userId, $name, $phone, $relationship, $primary);
        
        if (mysqli_stmt_execute($stmt)) {
            return mysqli_insert_id($this->con);
        }
        return false;
    }
    
    public function updateEmergencyContact($contactId, $userId, $data) {
        // If setting as primary, unset other primaries
        if (isset($data['is_primary']) && $data['is_primary']) {
            $unsetQuery = "UPDATE emergency_contacts SET is_primary = 0 WHERE user_id = ? AND id != ?";
            $stmt = mysqli_prepare($this->con, $unsetQuery);
            mysqli_stmt_bind_param($stmt, "ii", $userId, $contactId);
            mysqli_stmt_execute($stmt);
        }
        
        $updates = [];
        $params = [];
        $types = "";
        
        if (isset($data['name'])) {
            $updates[] = "name = ?";
            $params[] = $data['name'];
            $types .= "s";
        }
        if (isset($data['phone'])) {
            $updates[] = "phone = ?";
            $params[] = $data['phone'];
            $types .= "s";
        }
        if (isset($data['relationship'])) {
            $updates[] = "relationship = ?";
            $params[] = $data['relationship'];
            $types .= "s";
        }
        if (isset($data['is_primary'])) {
            $updates[] = "is_primary = ?";
            $params[] = $data['is_primary'] ? 1 : 0;
            $types .= "i";
        }
        
        if (empty($updates)) {
            return true;
        }
        
        $params[] = $contactId;
        $params[] = $userId;
        $types .= "ii";
        
        $query = "UPDATE emergency_contacts SET " . implode(", ", $updates) . " WHERE id = ? AND user_id = ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        
        return mysqli_stmt_execute($stmt);
    }
    
    public function deleteEmergencyContact($contactId, $userId) {
        $query = "DELETE FROM emergency_contacts WHERE id = ? AND user_id = ?";
        $stmt = mysqli_prepare($this->con, $query);
        mysqli_stmt_bind_param($stmt, "ii", $contactId, $userId);
        return mysqli_stmt_execute($stmt);
    }
}
