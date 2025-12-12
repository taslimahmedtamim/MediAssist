-- MediAssist+ Database Schema
-- MySQL Database Setup

CREATE DATABASE IF NOT EXISTS mediassist;
USE mediassist;

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
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
    name VARCHAR(200) NOT NULL,
    dosage VARCHAR(100),
    dose_type ENUM('tablet', 'capsule', 'syrup', 'injection', 'drops', 'cream', 'inhaler', 'other') DEFAULT 'tablet',
    frequency ENUM('once', 'twice', 'thrice', 'four_times', 'as_needed') DEFAULT 'once',
    duration_days INT,
    start_date DATE NOT NULL,
    end_date DATE,
    instructions TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
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

-- Report Values Table (Extracted Lab Values)
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
    plan_name VARCHAR(200),
    target_calories INT,
    target_protein_g DECIMAL(6,2),
    target_carbs_g DECIMAL(6,2),
    target_fat_g DECIMAL(6,2),
    condition_focus VARCHAR(100),
    start_date DATE,
    end_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
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
    type ENUM('medicine_reminder', 'missed_pill', 'report_ready', 'diet_reminder', 'general') NOT NULL,
    title VARCHAR(200),
    message TEXT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert Default Health Conditions
INSERT INTO health_conditions (name, description, dietary_restrictions) VALUES
('Diabetes Type 2', 'A chronic condition affecting blood sugar regulation', 'Low sugar, low glycemic index foods, limit refined carbs'),
('Hypertension', 'High blood pressure condition', 'Low sodium, DASH diet recommended, limit processed foods'),
('Chronic Kidney Disease', 'Progressive loss of kidney function', 'Low protein, low sodium, low potassium, low phosphorus'),
('Obesity', 'Excess body weight condition', 'Calorie deficit, high fiber, lean proteins, limit saturated fats'),
('Heart Disease', 'Cardiovascular conditions', 'Low saturated fat, low sodium, omega-3 rich foods'),
('Hyperthyroidism', 'Overactive thyroid', 'Avoid iodine-rich foods, limit caffeine'),
('Hypothyroidism', 'Underactive thyroid', 'Iodine-rich foods, selenium, avoid goitrogens'),
('Anemia', 'Low red blood cell count', 'Iron-rich foods, vitamin C, avoid calcium with iron');

-- Insert Sample Foods
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

-- Create indexes for better performance
CREATE INDEX idx_pill_tracking_date ON pill_tracking(scheduled_date);
CREATE INDEX idx_pill_tracking_user ON pill_tracking(user_id, status);
CREATE INDEX idx_medicines_user ON medicines(user_id, is_active);
CREATE INDEX idx_notifications_user ON notifications(user_id, is_read);
