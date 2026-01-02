-- =====================================================
-- MediAssist+ Complete Database Schema
-- Single file for easy import
-- =====================================================

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    username VARCHAR(50) UNIQUE,
    role ENUM('patient', 'doctor') DEFAULT 'patient',
    specialization VARCHAR(100),
    license_number VARCHAR(50),
    clinic_name VARCHAR(200),
    clinic_address TEXT,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    height_cm DECIMAL(5,2),
    weight_kg DECIMAL(5,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Health Conditions Table
CREATE TABLE IF NOT EXISTS health_conditions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    dietary_restrictions TEXT
);

-- User Health Conditions (Many-to-Many)
CREATE TABLE IF NOT EXISTS user_health_conditions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    condition_id INT NOT NULL,
    diagnosed_date DATE,
    notes TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (condition_id) REFERENCES health_conditions(id) ON DELETE CASCADE
);

-- Medicines Table
CREATE TABLE IF NOT EXISTS medicines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    assigned_by INT,
    name VARCHAR(200) NOT NULL,
    dosage VARCHAR(100),
    dose_type ENUM('tablet', 'capsule', 'syrup', 'injection', 'drops', 'cream', 'inhaler', 'other') DEFAULT 'tablet',
    frequency ENUM('once', 'twice', 'thrice', 'four_times', 'as_needed') DEFAULT 'once',
    duration_days INT,
    start_date DATE NOT NULL,
    end_date DATE,
    instructions TEXT,
    prescription_notes TEXT,
    salt_composition VARCHAR(500),
    remaining_pills INT DEFAULT 0,
    total_pills INT DEFAULT 0,
    expiry_date DATE,
    refill_reminder_days INT DEFAULT 7,
    manufacturer VARCHAR(200),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Medicine Schedules Table
CREATE TABLE IF NOT EXISTS medicine_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    medicine_id INT NOT NULL,
    scheduled_time TIME NOT NULL,
    dose_amount VARCHAR(50),
    meal_relation ENUM('before_meal', 'after_meal', 'with_meal', 'empty_stomach', 'anytime') DEFAULT 'anytime',
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE
);

-- Pill Tracking Table
CREATE TABLE IF NOT EXISTS pill_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    medicine_id INT NOT NULL,
    schedule_id INT NOT NULL,
    scheduled_date DATE NOT NULL,
    scheduled_time TIME NOT NULL,
    status ENUM('pending', 'taken', 'missed', 'skipped') DEFAULT 'pending',
    taken_at TIMESTAMP NULL,
    notes TEXT,
    missed_reason ENUM('forgot', 'unavailable', 'side_effects', 'felt_better', 'other'),
    recovery_action ENUM('taken_late', 'skipped_safely', 'double_dose', 'none'),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
    FOREIGN KEY (schedule_id) REFERENCES medicine_schedules(id) ON DELETE CASCADE
);

-- Medical Reports Table
CREATE TABLE IF NOT EXISTS medical_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    report_type ENUM('cbc', 'kidney', 'lipid', 'liver', 'thyroid', 'diabetes', 'other') NOT NULL,
    report_date DATE,
    file_path VARCHAR(500),
    ocr_text TEXT,
    parsed_data JSON,
    abnormalities JSON,
    recommendations TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Report Values Table
CREATE TABLE IF NOT EXISTS report_values (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_id INT NOT NULL,
    parameter_name VARCHAR(100) NOT NULL,
    value DECIMAL(10,3),
    unit VARCHAR(50),
    reference_min DECIMAL(10,3),
    reference_max DECIMAL(10,3),
    is_abnormal BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (report_id) REFERENCES medical_reports(id) ON DELETE CASCADE
);

-- Diet Plans Table
CREATE TABLE IF NOT EXISTS diet_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    assigned_by INT,
    plan_name VARCHAR(200),
    target_calories INT,
    target_protein_g DECIMAL(6,2),
    target_carbs_g DECIMAL(6,2),
    target_fat_g DECIMAL(6,2),
    condition_focus VARCHAR(100),
    start_date DATE,
    end_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    doctor_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Meals Table
CREATE TABLE IF NOT EXISTS meals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    diet_plan_id INT NOT NULL,
    meal_type ENUM('breakfast', 'morning_snack', 'lunch', 'afternoon_snack', 'dinner', 'evening_snack') NOT NULL,
    day_of_week TINYINT,
    meal_name VARCHAR(200),
    description TEXT,
    calories INT,
    protein_g DECIMAL(6,2),
    carbs_g DECIMAL(6,2),
    fat_g DECIMAL(6,2),
    fiber_g DECIMAL(6,2),
    sodium_mg DECIMAL(8,2),
    FOREIGN KEY (diet_plan_id) REFERENCES diet_plans(id) ON DELETE CASCADE
);

-- Restricted Foods Table
CREATE TABLE IF NOT EXISTS restricted_foods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    food_name VARCHAR(200) NOT NULL,
    reason TEXT,
    severity ENUM('avoid', 'limit', 'moderate') DEFAULT 'avoid',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Food Database Table
CREATE TABLE IF NOT EXISTS foods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    category VARCHAR(100),
    calories_per_100g INT,
    protein_g DECIMAL(6,2),
    carbs_g DECIMAL(6,2),
    fat_g DECIMAL(6,2),
    fiber_g DECIMAL(6,2),
    sodium_mg DECIMAL(8,2),
    glycemic_index INT,
    suitable_for JSON
);

-- Notifications Table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    from_user_id INT,
    type ENUM('medicine_reminder', 'missed_pill', 'refill_alert', 'expiry_alert', 'interaction_warning', 'side_effect_alert', 'report_ready', 'abnormal_value', 'health_insight', 'diet_reminder', 'water_reminder', 'weekly_summary', 'caregiver_alert', 'general', 'doctor_message', 'schedule_assigned', 'diet_assigned', 'compliance_alert') NOT NULL,
    related_medicine_id INT,
    related_diet_plan_id INT,
    title VARCHAR(200),
    message TEXT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (related_medicine_id) REFERENCES medicines(id) ON DELETE SET NULL,
    FOREIGN KEY (related_diet_plan_id) REFERENCES diet_plans(id) ON DELETE SET NULL
);

-- Doctor-Patient Relationship Table
CREATE TABLE IF NOT EXISTS doctor_patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL,
    patient_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive') DEFAULT 'active',
    notes TEXT,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_doctor_patient (doctor_id, patient_id)
);

-- Schedule Compliance Table
CREATE TABLE IF NOT EXISTS schedule_compliance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    date DATE NOT NULL,
    medicines_scheduled INT DEFAULT 0,
    medicines_taken INT DEFAULT 0,
    medicines_missed INT DEFAULT 0,
    compliance_percentage DECIMAL(5,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Appointments Table
CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL,
    patient_id INT NOT NULL,
    appointment_date DATETIME NOT NULL,
    purpose TEXT,
    status ENUM('scheduled', 'completed', 'cancelled', 'no_show') DEFAULT 'scheduled',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Medicine Interactions Table
CREATE TABLE IF NOT EXISTS medicine_interactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    medicine1_salt VARCHAR(200) NOT NULL,
    medicine2_salt VARCHAR(200) NOT NULL,
    severity ENUM('mild', 'moderate', 'severe', 'contraindicated') NOT NULL,
    description TEXT,
    recommendation TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Medicine History / Audit Log
CREATE TABLE IF NOT EXISTS medicine_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    medicine_id INT NOT NULL,
    user_id INT NOT NULL,
    action ENUM('created', 'started', 'dose_changed', 'paused', 'resumed', 'stopped', 'edited') NOT NULL,
    old_value JSON,
    new_value JSON,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Side Effects Reporting
CREATE TABLE IF NOT EXISTS side_effects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    medicine_id INT NOT NULL,
    effect_type VARCHAR(200) NOT NULL,
    severity ENUM('mild', 'moderate', 'severe', 'life_threatening') NOT NULL,
    description TEXT,
    onset_date DATE,
    duration_days INT,
    action_taken ENUM('continued', 'reduced_dose', 'stopped', 'consulted_doctor') DEFAULT 'continued',
    is_resolved BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE
);

-- Common Side Effects Reference
CREATE TABLE IF NOT EXISTS common_side_effects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    salt_name VARCHAR(200) NOT NULL,
    effect_name VARCHAR(200) NOT NULL,
    frequency ENUM('very_common', 'common', 'uncommon', 'rare') NOT NULL
);

-- Lab Reference Ranges Table
CREATE TABLE IF NOT EXISTS lab_reference_ranges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parameter_name VARCHAR(100) NOT NULL,
    unit VARCHAR(50),
    min_normal DECIMAL(10,3),
    max_normal DECIMAL(10,3),
    critical_low DECIMAL(10,3),
    critical_high DECIMAL(10,3),
    category VARCHAR(100),
    interpretation_low TEXT,
    interpretation_high TEXT
);

-- Health Risk Rules
CREATE TABLE IF NOT EXISTS health_risk_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rule_name VARCHAR(200) NOT NULL,
    condition_type VARCHAR(100),
    parameters JSON NOT NULL,
    risk_level ENUM('low', 'moderate', 'high', 'critical') NOT NULL,
    message TEXT NOT NULL,
    recommendation TEXT
);

-- Water Intake Tracking
CREATE TABLE IF NOT EXISTS water_intake (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    date DATE NOT NULL,
    amount_ml INT NOT NULL,
    logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Activity / Exercise Tracking
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    date DATE NOT NULL,
    activity_type VARCHAR(100) NOT NULL,
    duration_minutes INT NOT NULL,
    calories_burned INT,
    intensity ENUM('light', 'moderate', 'vigorous') DEFAULT 'moderate',
    notes TEXT,
    logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Activity Reference
CREATE TABLE IF NOT EXISTS activity_reference (
    id INT AUTO_INCREMENT PRIMARY KEY,
    activity_name VARCHAR(100) NOT NULL,
    calories_per_minute_light DECIMAL(4,2),
    calories_per_minute_moderate DECIMAL(4,2),
    calories_per_minute_vigorous DECIMAL(4,2),
    category VARCHAR(50)
);

-- Grocery List
CREATE TABLE IF NOT EXISTS grocery_list (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    item_name VARCHAR(200) NOT NULL,
    quantity VARCHAR(50),
    category VARCHAR(100),
    is_purchased BOOLEAN DEFAULT FALSE,
    week_start_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Condition Food Restrictions
CREATE TABLE IF NOT EXISTS condition_food_restrictions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    condition_name VARCHAR(100) NOT NULL,
    food_category VARCHAR(100) NOT NULL,
    restriction_type ENUM('avoid', 'limit', 'prefer') NOT NULL,
    reason TEXT,
    alternatives TEXT
);

-- Emergency Information
CREATE TABLE IF NOT EXISTS emergency_info (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    blood_group ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NULL,
    allergies TEXT,
    emergency_contact_name VARCHAR(100),
    emergency_contact_phone VARCHAR(20),
    emergency_contact_relation VARCHAR(50),
    secondary_contact_name VARCHAR(100),
    secondary_contact_phone VARCHAR(20),
    doctor_name VARCHAR(100),
    doctor_phone VARCHAR(20),
    hospital_preference VARCHAR(200),
    insurance_info TEXT,
    special_instructions TEXT,
    access_code VARCHAR(20) UNIQUE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Caregivers
CREATE TABLE IF NOT EXISTS caregivers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_user_id INT NOT NULL,
    caregiver_email VARCHAR(255) NOT NULL,
    caregiver_name VARCHAR(100),
    access_level ENUM('view_only', 'view_and_alert', 'full') DEFAULT 'view_only',
    can_view_medicines BOOLEAN DEFAULT TRUE,
    can_view_adherence BOOLEAN DEFAULT TRUE,
    can_view_reports BOOLEAN DEFAULT FALSE,
    can_view_diet BOOLEAN DEFAULT FALSE,
    receive_missed_alerts BOOLEAN DEFAULT TRUE,
    is_verified BOOLEAN DEFAULT FALSE,
    verification_token VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Notification Preferences
CREATE TABLE IF NOT EXISTS notification_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    medicine_reminders BOOLEAN DEFAULT TRUE,
    missed_dose_alerts BOOLEAN DEFAULT TRUE,
    refill_alerts BOOLEAN DEFAULT TRUE,
    expiry_alerts BOOLEAN DEFAULT TRUE,
    interaction_warnings BOOLEAN DEFAULT TRUE,
    lab_alerts BOOLEAN DEFAULT TRUE,
    diet_reminders BOOLEAN DEFAULT TRUE,
    water_reminders BOOLEAN DEFAULT FALSE,
    weekly_summary BOOLEAN DEFAULT TRUE,
    quiet_hours_start TIME DEFAULT '22:00:00',
    quiet_hours_end TIME DEFAULT '07:00:00',
    email_notifications BOOLEAN DEFAULT TRUE,
    push_notifications BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Weekly Summaries
CREATE TABLE IF NOT EXISTS weekly_summaries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    week_start_date DATE NOT NULL,
    week_end_date DATE NOT NULL,
    total_doses INT DEFAULT 0,
    taken_doses INT DEFAULT 0,
    missed_doses INT DEFAULT 0,
    adherence_percentage DECIMAL(5,2) DEFAULT 0,
    total_calories INT DEFAULT 0,
    avg_calories INT DEFAULT 0,
    total_water_ml INT DEFAULT 0,
    avg_water_ml INT DEFAULT 0,
    total_activity_minutes INT DEFAULT 0,
    calories_burned INT DEFAULT 0,
    new_reports INT DEFAULT 0,
    abnormal_values INT DEFAULT 0,
    health_insights JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_week (user_id, week_start_date)
);

-- Audit Log
CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action_type ENUM('login', 'logout', 'create', 'update', 'delete', 'view', 'export', 'import') NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Admin Users
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('super_admin', 'admin', 'moderator') DEFAULT 'moderator',
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Analytics Cache
CREATE TABLE IF NOT EXISTS analytics_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    metric_name VARCHAR(100) NOT NULL,
    metric_date DATE NOT NULL,
    metric_value JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_metric_date (metric_name, metric_date)
);

-- OCR Corrections
CREATE TABLE IF NOT EXISTS ocr_corrections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_text VARCHAR(500) NOT NULL,
    corrected_text VARCHAR(500) NOT NULL,
    correction_count INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_original (original_text)
);

-- =====================================================
-- INSERT DEFAULT DATA
-- =====================================================

-- Health Conditions
INSERT INTO health_conditions (name, description, dietary_restrictions) VALUES
('Diabetes Type 2', 'A chronic condition affecting blood sugar regulation', 'Low sugar, low glycemic index foods, limit refined carbs'),
('Hypertension', 'High blood pressure condition', 'Low sodium, DASH diet recommended, limit processed foods'),
('Chronic Kidney Disease', 'Progressive loss of kidney function', 'Low protein, low sodium, low potassium, low phosphorus'),
('Obesity', 'Excess body weight condition', 'Calorie deficit, high fiber, lean proteins, limit saturated fats'),
('Heart Disease', 'Cardiovascular conditions', 'Low saturated fat, low sodium, omega-3 rich foods'),
('Hyperthyroidism', 'Overactive thyroid', 'Avoid iodine-rich foods, limit caffeine'),
('Hypothyroidism', 'Underactive thyroid', 'Iodine-rich foods, selenium, avoid goitrogens'),
('Anemia', 'Low red blood cell count', 'Iron-rich foods, vitamin C, avoid calcium with iron');

-- Foods
INSERT INTO foods (name, category, calories_per_100g, protein_g, carbs_g, fat_g, fiber_g, sodium_mg, glycemic_index, suitable_for) VALUES
('Oatmeal', 'Grains', 68, 2.4, 12, 1.4, 1.7, 49, 55, '["diabetes", "heart_disease", "obesity"]'),
('Grilled Chicken Breast', 'Protein', 165, 31, 0, 3.6, 0, 74, 0, '["diabetes", "obesity", "kidney_disease"]'),
('Brown Rice', 'Grains', 111, 2.6, 23, 0.9, 1.8, 5, 50, '["diabetes", "heart_disease"]'),
('Spinach', 'Vegetables', 23, 2.9, 3.6, 0.4, 2.2, 79, 15, '["diabetes", "hypertension", "obesity", "heart_disease"]'),
('Salmon', 'Protein', 208, 20, 0, 13, 0, 59, 0, '["heart_disease", "diabetes"]'),
('Sweet Potato', 'Vegetables', 86, 1.6, 20, 0.1, 3, 55, 63, '["diabetes", "obesity"]'),
('Lentils', 'Legumes', 116, 9, 20, 0.4, 7.9, 2, 32, '["diabetes", "heart_disease", "obesity"]'),
('Greek Yogurt', 'Dairy', 59, 10, 3.6, 0.7, 0, 36, 11, '["diabetes", "obesity"]'),
('Almonds', 'Nuts', 579, 21, 22, 50, 12.5, 1, 15, '["diabetes", "heart_disease"]'),
('Broccoli', 'Vegetables', 34, 2.8, 7, 0.4, 2.6, 33, 10, '["diabetes", "hypertension", "obesity", "heart_disease", "kidney_disease"]');

-- Medicine Interactions
INSERT INTO medicine_interactions (medicine1_salt, medicine2_salt, severity, description, recommendation) VALUES
('warfarin', 'aspirin', 'severe', 'Increased risk of bleeding', 'Avoid combination or monitor closely'),
('metformin', 'alcohol', 'moderate', 'Risk of lactic acidosis', 'Limit alcohol consumption'),
('lisinopril', 'potassium', 'moderate', 'Risk of hyperkalemia', 'Monitor potassium levels'),
('simvastatin', 'grapefruit', 'moderate', 'Increased statin levels', 'Avoid grapefruit'),
('metoprolol', 'verapamil', 'severe', 'Risk of heart block', 'Use with extreme caution'),
('ciprofloxacin', 'antacids', 'moderate', 'Reduced antibiotic absorption', 'Take 2 hours apart'),
('omeprazole', 'clopidogrel', 'moderate', 'Reduced antiplatelet effect', 'Consider alternative PPI'),
('amlodipine', 'simvastatin', 'moderate', 'Increased statin levels', 'Limit simvastatin dose to 20mg'),
('ibuprofen', 'aspirin', 'moderate', 'Reduced cardioprotection', 'Take aspirin 30 min before ibuprofen'),
('levothyroxine', 'calcium', 'moderate', 'Reduced thyroid absorption', 'Take 4 hours apart'),
('digoxin', 'amiodarone', 'severe', 'Increased digoxin toxicity', 'Reduce digoxin dose by 50%'),
('fluoxetine', 'tramadol', 'severe', 'Risk of serotonin syndrome', 'Avoid combination'),
('methotrexate', 'nsaids', 'severe', 'Increased methotrexate toxicity', 'Monitor closely'),
('lithium', 'nsaids', 'moderate', 'Increased lithium levels', 'Monitor lithium levels'),
('theophylline', 'ciprofloxacin', 'severe', 'Increased theophylline toxicity', 'Reduce theophylline dose');

-- Common Side Effects
INSERT INTO common_side_effects (salt_name, effect_name, frequency) VALUES
('metformin', 'Nausea', 'common'),
('metformin', 'Diarrhea', 'common'),
('metformin', 'Stomach upset', 'very_common'),
('amlodipine', 'Swelling in ankles', 'common'),
('amlodipine', 'Headache', 'common'),
('atorvastatin', 'Muscle pain', 'common'),
('omeprazole', 'Headache', 'common'),
('omeprazole', 'Nausea', 'uncommon'),
('lisinopril', 'Dry cough', 'common'),
('lisinopril', 'Dizziness', 'common'),
('metoprolol', 'Fatigue', 'common'),
('metoprolol', 'Cold hands/feet', 'common'),
('aspirin', 'Stomach irritation', 'common'),
('ibuprofen', 'Stomach upset', 'common'),
('amoxicillin', 'Diarrhea', 'common'),
('amoxicillin', 'Rash', 'uncommon');

-- Lab Reference Ranges
INSERT INTO lab_reference_ranges (parameter_name, unit, min_normal, max_normal, critical_low, critical_high, category, interpretation_low, interpretation_high) VALUES
('Hemoglobin', 'g/dL', 12.0, 17.5, 7.0, 20.0, 'CBC', 'Anemia - may indicate blood loss, nutritional deficiency, or chronic disease', 'Polycythemia - may indicate dehydration or bone marrow disorder'),
('WBC', '10^3/uL', 4.5, 11.0, 2.0, 30.0, 'CBC', 'Leukopenia - increased infection risk', 'Leukocytosis - may indicate infection or leukemia'),
('Platelets', '10^3/uL', 150, 400, 50, 1000, 'CBC', 'Thrombocytopenia - bleeding risk', 'Thrombocytosis - clotting risk'),
('RBC', 'million/uL', 4.5, 5.5, 3.0, 7.0, 'CBC', 'Low RBC count - anemia', 'High RBC count - polycythemia'),
('Hematocrit', '%', 36, 50, 20, 60, 'CBC', 'Low hematocrit - anemia', 'High hematocrit - dehydration or polycythemia'),
('MCV', 'fL', 80, 100, 60, 120, 'CBC', 'Microcytic anemia - iron deficiency', 'Macrocytic anemia - B12/folate deficiency'),
('Creatinine', 'mg/dL', 0.7, 1.3, 0.4, 10.0, 'Kidney', 'Low creatinine - reduced muscle mass', 'High creatinine - kidney dysfunction'),
('BUN', 'mg/dL', 7, 20, 3, 100, 'Kidney', 'Low BUN - liver disease or malnutrition', 'High BUN - kidney disease or dehydration'),
('eGFR', 'mL/min', 90, 120, 15, 150, 'Kidney', 'Reduced kidney function', 'Normal or hyperfiltration'),
('Uric Acid', 'mg/dL', 3.5, 7.2, 2.0, 12.0, 'Kidney', 'Low uric acid - rare', 'High uric acid - gout risk'),
('ALT', 'U/L', 7, 56, 5, 1000, 'Liver', 'Very low ALT - rare', 'Elevated ALT - liver inflammation'),
('AST', 'U/L', 10, 40, 5, 1000, 'Liver', 'Very low AST - rare', 'Elevated AST - liver or muscle damage'),
('ALP', 'U/L', 44, 147, 30, 500, 'Liver', 'Low ALP - rare', 'Elevated ALP - liver or bone disease'),
('Bilirubin Total', 'mg/dL', 0.1, 1.2, 0.0, 15.0, 'Liver', 'Low bilirubin - not significant', 'High bilirubin - jaundice, liver disease'),
('Albumin', 'g/dL', 3.5, 5.0, 2.0, 6.0, 'Liver', 'Low albumin - liver disease or malnutrition', 'High albumin - dehydration'),
('Total Cholesterol', 'mg/dL', 0, 200, 0, 300, 'Lipid', 'Very low cholesterol - rare', 'High cholesterol - cardiovascular risk'),
('LDL Cholesterol', 'mg/dL', 0, 100, 0, 190, 'Lipid', 'Low LDL - generally good', 'High LDL - cardiovascular risk'),
('HDL Cholesterol', 'mg/dL', 40, 100, 20, 120, 'Lipid', 'Low HDL - cardiovascular risk', 'High HDL - protective'),
('Triglycerides', 'mg/dL', 0, 150, 0, 500, 'Lipid', 'Low triglycerides - good', 'High triglycerides - metabolic risk'),
('Fasting Glucose', 'mg/dL', 70, 100, 40, 400, 'Diabetes', 'Hypoglycemia - low blood sugar', 'Hyperglycemia - diabetes indicator'),
('HbA1c', '%', 4.0, 5.6, 3.0, 14.0, 'Diabetes', 'Very low HbA1c - possible hypoglycemia', 'High HbA1c - poor glucose control'),
('Post Prandial Glucose', 'mg/dL', 70, 140, 40, 400, 'Diabetes', 'Hypoglycemia', 'Impaired glucose tolerance'),
('TSH', 'mIU/L', 0.4, 4.0, 0.01, 100, 'Thyroid', 'Low TSH - hyperthyroidism', 'High TSH - hypothyroidism'),
('T3', 'ng/dL', 80, 200, 40, 400, 'Thyroid', 'Low T3 - hypothyroidism', 'High T3 - hyperthyroidism'),
('T4', 'ug/dL', 5.0, 12.0, 2.0, 20.0, 'Thyroid', 'Low T4 - hypothyroidism', 'High T4 - hyperthyroidism'),
('Sodium', 'mEq/L', 136, 145, 120, 160, 'Electrolytes', 'Hyponatremia - water retention', 'Hypernatremia - dehydration'),
('Potassium', 'mEq/L', 3.5, 5.0, 2.5, 6.5, 'Electrolytes', 'Hypokalemia - muscle weakness', 'Hyperkalemia - cardiac risk'),
('Chloride', 'mEq/L', 98, 106, 80, 120, 'Electrolytes', 'Low chloride - metabolic alkalosis', 'High chloride - metabolic acidosis'),
('Calcium', 'mg/dL', 8.5, 10.5, 6.0, 14.0, 'Electrolytes', 'Hypocalcemia - muscle cramps', 'Hypercalcemia - kidney stones risk');

-- Health Risk Rules
INSERT INTO health_risk_rules (rule_name, condition_type, parameters, risk_level, message, recommendation) VALUES
('High Creatinine', 'kidney', '{"parameter": "Creatinine", "operator": ">", "value": 1.5}', 'moderate', 'Kidney function markers show mild elevation', 'Consider consulting a nephrologist. Stay hydrated and limit protein intake.'),
('Very High Creatinine', 'kidney', '{"parameter": "Creatinine", "operator": ">", "value": 2.0}', 'high', 'Kidney function significantly impaired', 'Urgent nephrology consultation recommended. Monitor fluid intake.'),
('Low eGFR', 'kidney', '{"parameter": "eGFR", "operator": "<", "value": 60}', 'high', 'Reduced kidney filtration rate detected', 'Stage 3 CKD possible. Regular monitoring and specialist review needed.'),
('High HbA1c', 'diabetes', '{"parameter": "HbA1c", "operator": ">", "value": 6.5}', 'moderate', 'Blood sugar control needs improvement', 'Review diabetes management plan with your doctor.'),
('Very High HbA1c', 'diabetes', '{"parameter": "HbA1c", "operator": ">", "value": 9.0}', 'high', 'Poor long-term blood sugar control', 'Urgent diabetes review needed. Risk of complications.'),
('High LDL', 'cardiovascular', '{"parameter": "LDL Cholesterol", "operator": ">", "value": 130}', 'moderate', 'Elevated LDL cholesterol increases cardiovascular risk', 'Consider lifestyle changes and discuss statin therapy with doctor.'),
('Low Hemoglobin', 'anemia', '{"parameter": "Hemoglobin", "operator": "<", "value": 10}', 'moderate', 'Anemia detected - low hemoglobin levels', 'Investigate cause. May need iron supplements or further testing.'),
('High TSH', 'thyroid', '{"parameter": "TSH", "operator": ">", "value": 5.0}', 'moderate', 'Elevated TSH suggests underactive thyroid', 'Thyroid function evaluation recommended.'),
('Low TSH', 'thyroid', '{"parameter": "TSH", "operator": "<", "value": 0.3}', 'moderate', 'Low TSH suggests overactive thyroid', 'Thyroid function evaluation recommended.'),
('High Liver Enzymes', 'liver', '{"parameter": "ALT", "operator": ">", "value": 80}', 'moderate', 'Liver enzymes elevated', 'Limit alcohol, review medications, and consult gastroenterologist.');

-- Activity Reference
INSERT INTO activity_reference (activity_name, calories_per_minute_light, calories_per_minute_moderate, calories_per_minute_vigorous, category) VALUES
('Walking', 3.5, 5.0, 7.5, 'Cardio'),
('Running', 8.0, 11.0, 15.0, 'Cardio'),
('Cycling', 4.0, 7.0, 12.0, 'Cardio'),
('Swimming', 6.0, 8.0, 12.0, 'Cardio'),
('Yoga', 2.5, 4.0, 6.0, 'Flexibility'),
('Weight Training', 3.0, 5.0, 8.0, 'Strength'),
('Household Chores', 2.5, 4.0, 5.5, 'Daily'),
('Gardening', 3.0, 4.5, 6.0, 'Daily'),
('Dancing', 4.0, 6.5, 10.0, 'Cardio'),
('Climbing Stairs', 5.0, 8.0, 12.0, 'Cardio');

-- Condition Food Restrictions
INSERT INTO condition_food_restrictions (condition_name, food_category, restriction_type, reason, alternatives) VALUES
('Diabetes', 'Sugary drinks', 'avoid', 'Causes rapid blood sugar spike', 'Water, unsweetened tea, sugar-free drinks'),
('Diabetes', 'White bread', 'avoid', 'High glycemic index', 'Whole grain bread, oats'),
('Diabetes', 'Candy', 'avoid', 'Pure sugar with no nutritional value', 'Fresh fruits in moderation'),
('Diabetes', 'Fruits', 'limit', 'Natural sugars can affect blood sugar', 'Berries, green apples in small portions'),
('Hypertension', 'Salt', 'limit', 'Increases blood pressure', 'Herbs, spices, lemon juice'),
('Hypertension', 'Processed foods', 'avoid', 'High sodium content', 'Fresh, home-cooked meals'),
('Hypertension', 'Pickles', 'avoid', 'Very high sodium', 'Fresh vegetables'),
('Chronic Kidney Disease', 'High protein foods', 'limit', 'Kidneys struggle to filter protein waste', 'Plant-based proteins in moderation'),
('Chronic Kidney Disease', 'Bananas', 'avoid', 'High potassium', 'Apples, berries'),
('Chronic Kidney Disease', 'Oranges', 'avoid', 'High potassium', 'Apples, grapes'),
('Chronic Kidney Disease', 'Dairy', 'limit', 'High phosphorus', 'Non-dairy alternatives'),
('Heart Disease', 'Fried foods', 'avoid', 'High saturated fat', 'Baked, grilled, or steamed foods'),
('Heart Disease', 'Red meat', 'limit', 'High saturated fat and cholesterol', 'Fish, chicken, legumes'),
('Heart Disease', 'Butter', 'limit', 'High saturated fat', 'Olive oil, avocado'),
('Hyperthyroidism', 'Seaweed', 'avoid', 'Very high iodine content', 'Other vegetables'),
('Hyperthyroidism', 'Iodized salt', 'limit', 'Iodine can worsen condition', 'Non-iodized salt sparingly'),
('Hypothyroidism', 'Soy products', 'limit', 'May interfere with thyroid medication', 'Take medication away from soy foods'),
('Hypothyroidism', 'Cruciferous vegetables', 'limit', 'Goitrogens may affect thyroid', 'Cook these vegetables well');

-- =====================================================
-- CREATE INDEXES FOR PERFORMANCE
-- =====================================================

CREATE INDEX idx_pill_tracking_date ON pill_tracking(scheduled_date);
CREATE INDEX idx_pill_tracking_user ON pill_tracking(user_id, status);
CREATE INDEX idx_medicines_user ON medicines(user_id, is_active);
CREATE INDEX idx_notifications_user ON notifications(user_id, is_read);
CREATE INDEX idx_doctor_patients_doctor ON doctor_patients(doctor_id, status);
CREATE INDEX idx_doctor_patients_patient ON doctor_patients(patient_id, status);
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_medicines_assigned_by ON medicines(assigned_by);
CREATE INDEX idx_diet_plans_assigned_by ON diet_plans(assigned_by);
CREATE INDEX idx_schedule_compliance ON schedule_compliance(patient_id, doctor_id, date);
CREATE INDEX idx_water_intake_user_date ON water_intake(user_id, date);
CREATE INDEX idx_activity_log_user_date ON activity_log(user_id, date);
CREATE INDEX idx_medicine_history_medicine ON medicine_history(medicine_id);
CREATE INDEX idx_side_effects_user ON side_effects(user_id);
CREATE INDEX idx_side_effects_medicine ON side_effects(medicine_id);
CREATE INDEX idx_audit_log_user ON audit_log(user_id, created_at);
CREATE INDEX idx_grocery_list_user ON grocery_list(user_id, is_purchased);
CREATE INDEX idx_caregivers_patient ON caregivers(patient_user_id);
CREATE INDEX idx_weekly_summaries_user ON weekly_summaries(user_id, week_start_date);
