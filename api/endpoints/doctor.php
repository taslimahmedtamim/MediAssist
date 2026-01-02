<?php
/**
 * Doctor API Endpoints
 * Handles all doctor-specific operations
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Medicine.php';
require_once __DIR__ . '/../models/DietPlan.php';

if (!$con) {
    sendError('Database connection failed', 500);
}

$user = new User($con);
$medicine = new Medicine($con);
$dietPlan = new DietPlan($con);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Verify doctor role for most actions
function verifyDoctor($con, $doctorId) {
    $user = new User($con);
    if (!$user->isDoctor($doctorId)) {
        sendError('Access denied. Doctor privileges required.', 403);
        exit;
    }
}

switch ($method) {
    case 'GET':
        $doctorId = $_GET['doctor_id'] ?? null;
        
        if ($action === 'patients') {
            // Get all patients for a doctor
            if (!$doctorId) {
                sendError('Doctor ID required');
            }
            verifyDoctor($con, $doctorId);
            
            $patients = $user->getDoctorPatients($doctorId);
            
            // Get compliance data for each patient
            foreach ($patients as &$patient) {
                $compliance = $user->getPatientOverallCompliance($patient['id'], 30);
                $patient['compliance'] = $compliance;
            }
            
            sendResponse(['success' => true, 'patients' => $patients]);
        }
        elseif ($action === 'search_patient') {
            // Search for patients by username
            $doctorId = $_GET['doctor_id'] ?? null;
            $username = $_GET['username'] ?? '';
            
            if (!$doctorId) {
                sendError('Doctor ID required');
            }
            verifyDoctor($con, $doctorId);
            
            if (strlen($username) < 2) {
                sendError('Username must be at least 2 characters');
            }
            
            $patients = $user->searchByUsername($username, 'patient');
            sendResponse(['success' => true, 'patients' => $patients]);
        }
        elseif ($action === 'patient_details') {
            // Get detailed patient info for doctor view
            $doctorId = $_GET['doctor_id'] ?? null;
            $patientId = $_GET['patient_id'] ?? null;
            
            if (!$doctorId || !$patientId) {
                sendError('Doctor ID and Patient ID required');
            }
            verifyDoctor($con, $doctorId);
            
            // Verify doctor has access to this patient
            if (!$user->doctorHasPatient($doctorId, $patientId)) {
                sendError('You do not have access to this patient', 403);
            }
            
            $patientInfo = $user->getById($patientId);
            $conditions = $user->getHealthConditions($patientId);
            $medicines = $medicine->getByUser($patientId, true);
            $activeDiet = $dietPlan->getActivePlan($patientId);
            $compliance = $user->getPatientCompliance($patientId, 7);
            $overallCompliance = $user->getPatientOverallCompliance($patientId, 30);
            
            sendResponse([
                'success' => true,
                'patient' => $patientInfo,
                'conditions' => $conditions,
                'medicines' => $medicines,
                'diet_plan' => $activeDiet,
                'compliance' => $compliance,
                'overall_compliance' => $overallCompliance
            ]);
        }
        elseif ($action === 'patient_compliance') {
            // Get patient compliance data
            $doctorId = $_GET['doctor_id'] ?? null;
            $patientId = $_GET['patient_id'] ?? null;
            $days = $_GET['days'] ?? 7;
            
            if (!$doctorId || !$patientId) {
                sendError('Doctor ID and Patient ID required');
            }
            verifyDoctor($con, $doctorId);
            
            if (!$user->doctorHasPatient($doctorId, $patientId)) {
                sendError('You do not have access to this patient', 403);
            }
            
            $compliance = $user->getPatientCompliance($patientId, $days);
            $overall = $user->getPatientOverallCompliance($patientId, $days);
            
            sendResponse([
                'success' => true,
                'daily_compliance' => $compliance,
                'overall' => $overall
            ]);
        }
        elseif ($action === 'patient_medicines') {
            // Get medicines assigned to patient
            $doctorId = $_GET['doctor_id'] ?? null;
            $patientId = $_GET['patient_id'] ?? null;
            
            if (!$doctorId || !$patientId) {
                sendError('Doctor ID and Patient ID required');
            }
            verifyDoctor($con, $doctorId);
            
            $medicines = $medicine->getByUser($patientId, true);
            sendResponse(['success' => true, 'medicines' => $medicines]);
        }
        elseif ($action === 'patient_diet') {
            // Get diet plan for patient
            $doctorId = $_GET['doctor_id'] ?? null;
            $patientId = $_GET['patient_id'] ?? null;
            
            if (!$doctorId || !$patientId) {
                sendError('Doctor ID and Patient ID required');
            }
            verifyDoctor($con, $doctorId);
            
            $plan = $dietPlan->getActivePlan($patientId);
            $meals = [];
            if ($plan) {
                $meals = $dietPlan->getMeals($plan['id']);
            }
            
            sendResponse(['success' => true, 'diet_plan' => $plan, 'meals' => $meals]);
        }
        elseif ($action === 'dashboard') {
            // Get doctor dashboard statistics
            $doctorId = $_GET['doctor_id'] ?? null;
            
            if (!$doctorId) {
                sendError('Doctor ID required');
            }
            verifyDoctor($con, $doctorId);
            
            $patients = $user->getDoctorPatients($doctorId);
            $totalPatients = count($patients);
            
            // Calculate low compliance patients (below 70%)
            $lowCompliancePatients = [];
            $totalCompliance = 0;
            
            foreach ($patients as $patient) {
                $compliance = $user->getPatientOverallCompliance($patient['id'], 7);
                $totalCompliance += $compliance['compliance_percentage'];
                
                if ($compliance['compliance_percentage'] < 70) {
                    $patient['compliance_percentage'] = $compliance['compliance_percentage'];
                    $lowCompliancePatients[] = $patient;
                }
            }
            
            $avgCompliance = $totalPatients > 0 ? round($totalCompliance / $totalPatients, 2) : 0;
            
            sendResponse([
                'success' => true,
                'total_patients' => $totalPatients,
                'average_compliance' => $avgCompliance,
                'low_compliance_patients' => $lowCompliancePatients,
                'low_compliance_count' => count($lowCompliancePatients)
            ]);
        }
        break;

    case 'POST':
        $data = getJsonInput();
        
        if ($action === 'add_patient') {
            // Add existing patient to doctor's list
            $required = ['doctor_id', 'patient_id'];
            $missing = validateRequired($data, $required);
            
            if (!empty($missing)) {
                sendError('Missing required fields: ' . implode(', ', $missing));
            }
            
            verifyDoctor($con, $data['doctor_id']);
            
            // Verify patient exists and is a patient
            $patientInfo = $user->getById($data['patient_id']);
            if (!$patientInfo || $patientInfo['role'] !== 'patient') {
                sendError('Invalid patient ID');
            }
            
            if ($user->addPatient($data['doctor_id'], $data['patient_id'], $data['notes'] ?? null)) {
                // Send notification to patient
                $notifQuery = "INSERT INTO notifications (user_id, from_user_id, type, title, message) 
                              VALUES (?, ?, 'doctor_message', 'New Doctor Added', 'A doctor has added you to their patient list.')";
                $notifStmt = mysqli_prepare($con, $notifQuery);
                mysqli_stmt_bind_param($notifStmt, "ii", $data['patient_id'], $data['doctor_id']);
                mysqli_stmt_execute($notifStmt);
                mysqli_stmt_close($notifStmt);
                
                sendResponse(['success' => true, 'message' => 'Patient added successfully']);
            } else {
                sendError('Failed to add patient', 500);
            }
        }
        elseif ($action === 'create_patient') {
            // Create new patient account
            $required = ['doctor_id', 'email', 'password', 'full_name'];
            $missing = validateRequired($data, $required);
            
            if (!empty($missing)) {
                sendError('Missing required fields: ' . implode(', ', $missing));
            }
            
            verifyDoctor($con, $data['doctor_id']);
            
            // Check if email already exists
            $user->email = sanitize($data['email']);
            if ($user->emailExists()) {
                sendError('Email already registered');
            }
            
            // Check if username exists (if provided)
            if (!empty($data['username'])) {
                if ($user->usernameExists($data['username'])) {
                    sendError('Username already taken');
                }
            }
            
            $patientData = [
                'email' => sanitize($data['email']),
                'username' => sanitize($data['username'] ?? ''),
                'password' => $data['password'],
                'full_name' => sanitize($data['full_name']),
                'phone' => sanitize($data['phone'] ?? ''),
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'gender' => $data['gender'] ?? null,
                'height_cm' => $data['height_cm'] ?? null,
                'weight_kg' => $data['weight_kg'] ?? null
            ];
            
            $patientId = $user->createPatientByDoctor($data['doctor_id'], $patientData);
            
            if ($patientId) {
                sendResponse([
                    'success' => true,
                    'message' => 'Patient account created successfully',
                    'patient_id' => $patientId
                ], 201);
            } else {
                sendError('Failed to create patient account', 500);
            }
        }
        elseif ($action === 'assign_medicine') {
            // Assign medicine schedule to patient
            $required = ['doctor_id', 'patient_id', 'name', 'start_date'];
            $missing = validateRequired($data, $required);
            
            if (!empty($missing)) {
                sendError('Missing required fields: ' . implode(', ', $missing));
            }
            
            verifyDoctor($con, $data['doctor_id']);
            
            // Verify doctor has access to patient
            if (!$user->doctorHasPatient($data['doctor_id'], $data['patient_id'])) {
                sendError('You do not have access to this patient', 403);
            }
            
            $medicine->user_id = $data['patient_id'];
            $medicine->name = sanitize($data['name']);
            $medicine->dosage = sanitize($data['dosage'] ?? '');
            $medicine->dose_type = $data['dose_type'] ?? 'tablet';
            $medicine->frequency = $data['frequency'] ?? 'once';
            $medicine->duration_days = $data['duration_days'] ?? null;
            $medicine->start_date = $data['start_date'];
            $medicine->end_date = $data['end_date'] ?? null;
            $medicine->instructions = sanitize($data['instructions'] ?? '');
            
            if ($medicine->createWithDoctor($data['doctor_id'], $data['prescription_notes'] ?? null)) {
                // Add schedules if provided
                if (!empty($data['schedules'])) {
                    foreach ($data['schedules'] as $schedule) {
                        $medicine->addSchedule(
                            $medicine->id,
                            $schedule['time'],
                            $schedule['dose_amount'] ?? 1,
                            $schedule['meal_relation'] ?? 'anytime'
                        );
                    }
                }
                
                // Send notification to patient
                $doctorInfo = $user->getById($data['doctor_id']);
                $notifQuery = "INSERT INTO notifications (user_id, from_user_id, type, title, message, related_medicine_id) 
                              VALUES (?, ?, 'schedule_assigned', 'New Medicine Assigned', ?, ?)";
                $notifStmt = mysqli_prepare($con, $notifQuery);
                $message = "Dr. " . $doctorInfo['full_name'] . " has assigned a new medicine: " . $data['name'];
                mysqli_stmt_bind_param($notifStmt, "iisi", $data['patient_id'], $data['doctor_id'], $message, $medicine->id);
                mysqli_stmt_execute($notifStmt);
                mysqli_stmt_close($notifStmt);
                
                sendResponse([
                    'success' => true,
                    'message' => 'Medicine assigned successfully',
                    'medicine_id' => $medicine->id
                ], 201);
            } else {
                sendError('Failed to assign medicine', 500);
            }
        }
        elseif ($action === 'assign_diet') {
            // Assign diet plan to patient
            $required = ['doctor_id', 'patient_id', 'plan_name'];
            $missing = validateRequired($data, $required);
            
            if (!empty($missing)) {
                sendError('Missing required fields: ' . implode(', ', $missing));
            }
            
            verifyDoctor($con, $data['doctor_id']);
            
            if (!$user->doctorHasPatient($data['doctor_id'], $data['patient_id'])) {
                sendError('You do not have access to this patient', 403);
            }
            
            $dietPlan->user_id = $data['patient_id'];
            $dietPlan->plan_name = sanitize($data['plan_name']);
            $dietPlan->target_calories = $data['target_calories'] ?? null;
            $dietPlan->target_protein_g = $data['target_protein_g'] ?? null;
            $dietPlan->target_carbs_g = $data['target_carbs_g'] ?? null;
            $dietPlan->target_fat_g = $data['target_fat_g'] ?? null;
            $dietPlan->condition_focus = sanitize($data['condition_focus'] ?? '');
            $dietPlan->start_date = $data['start_date'] ?? date('Y-m-d');
            $dietPlan->end_date = $data['end_date'] ?? null;
            
            if ($dietPlan->createWithDoctor($data['doctor_id'], $data['doctor_notes'] ?? null)) {
                // Add meals if provided
                if (!empty($data['meals'])) {
                    foreach ($data['meals'] as $meal) {
                        $dietPlan->addMeal($dietPlan->id, $meal);
                    }
                }
                
                // Send notification to patient
                $doctorInfo = $user->getById($data['doctor_id']);
                $notifQuery = "INSERT INTO notifications (user_id, from_user_id, type, title, message, related_diet_plan_id) 
                              VALUES (?, ?, 'diet_assigned', 'New Diet Plan Assigned', ?, ?)";
                $notifStmt = mysqli_prepare($con, $notifQuery);
                $message = "Dr. " . $doctorInfo['full_name'] . " has created a diet plan for you: " . $data['plan_name'];
                mysqli_stmt_bind_param($notifStmt, "iisi", $data['patient_id'], $data['doctor_id'], $message, $dietPlan->id);
                mysqli_stmt_execute($notifStmt);
                mysqli_stmt_close($notifStmt);
                
                sendResponse([
                    'success' => true,
                    'message' => 'Diet plan assigned successfully',
                    'plan_id' => $dietPlan->id
                ], 201);
            } else {
                sendError('Failed to assign diet plan', 500);
            }
        }
        elseif ($action === 'send_message') {
            // Send message/notification to patient
            $required = ['doctor_id', 'patient_id', 'title', 'message'];
            $missing = validateRequired($data, $required);
            
            if (!empty($missing)) {
                sendError('Missing required fields: ' . implode(', ', $missing));
            }
            
            verifyDoctor($con, $data['doctor_id']);
            
            if (!$user->doctorHasPatient($data['doctor_id'], $data['patient_id'])) {
                sendError('You do not have access to this patient', 403);
            }
            
            $query = "INSERT INTO notifications (user_id, from_user_id, type, title, message) 
                      VALUES (?, ?, 'doctor_message', ?, ?)";
            $stmt = mysqli_prepare($con, $query);
            mysqli_stmt_bind_param($stmt, "iiss", $data['patient_id'], $data['doctor_id'], $data['title'], $data['message']);
            
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                sendResponse(['success' => true, 'message' => 'Message sent successfully']);
            } else {
                mysqli_stmt_close($stmt);
                sendError('Failed to send message', 500);
            }
        }
        break;

    case 'PUT':
        $data = getJsonInput();
        
        if ($action === 'update_medicine') {
            // Update medicine for patient
            $required = ['doctor_id', 'patient_id', 'medicine_id'];
            $missing = validateRequired($data, $required);
            
            if (!empty($missing)) {
                sendError('Missing required fields: ' . implode(', ', $missing));
            }
            
            verifyDoctor($con, $data['doctor_id']);
            
            if (!$user->doctorHasPatient($data['doctor_id'], $data['patient_id'])) {
                sendError('You do not have access to this patient', 403);
            }
            
            $medicine->id = $data['medicine_id'];
            $medicine->user_id = $data['patient_id'];
            $medicine->name = sanitize($data['name'] ?? '');
            $medicine->dosage = sanitize($data['dosage'] ?? '');
            $medicine->dose_type = $data['dose_type'] ?? 'tablet';
            $medicine->frequency = $data['frequency'] ?? 'once';
            $medicine->duration_days = $data['duration_days'] ?? null;
            $medicine->end_date = $data['end_date'] ?? null;
            $medicine->instructions = sanitize($data['instructions'] ?? '');
            $medicine->is_active = $data['is_active'] ?? 1;
            
            if ($medicine->update()) {
                sendResponse(['success' => true, 'message' => 'Medicine updated successfully']);
            } else {
                sendError('Failed to update medicine', 500);
            }
        }
        break;

    case 'DELETE':
        $doctorId = $_GET['doctor_id'] ?? null;
        
        if ($action === 'remove_patient') {
            $patientId = $_GET['patient_id'] ?? null;
            
            if (!$doctorId || !$patientId) {
                sendError('Doctor ID and Patient ID required');
            }
            
            verifyDoctor($con, $doctorId);
            
            if ($user->removePatient($doctorId, $patientId)) {
                sendResponse(['success' => true, 'message' => 'Patient removed successfully']);
            } else {
                sendError('Failed to remove patient', 500);
            }
        }
        break;

    default:
        sendError('Method not allowed', 405);
}
