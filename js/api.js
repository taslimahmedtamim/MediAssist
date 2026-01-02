const API_BASE = 'api/endpoints';

class ApiService {
    constructor() {
        this.baseUrl = API_BASE;
    }

    async request(endpoint, options = {}) {
        const url = `${this.baseUrl}/${endpoint}`;
        
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json'
            }
        };

        const mergedOptions = { ...defaultOptions, ...options };

        try {
            const response = await fetch(url, mergedOptions);
            const text = await response.text();
            
            let data;
            try {
                data = JSON.parse(text);
            } catch (parseError) {
                console.error('JSON Parse Error. Response was:', text);
                throw new Error('Server returned invalid response. Check if database is set up correctly.');
            }

            if (!response.ok) {
                throw new Error(data.error || 'Request failed');
            }

            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }

    async register(userData) {
        return this.request('users.php?action=register', {
            method: 'POST',
            body: JSON.stringify(userData)
        });
    }

    async login(email, password) {
        return this.request('users.php?action=login', {
            method: 'POST',
            body: JSON.stringify({ email, password })
        });
    }

    async getProfile(userId) {
        return this.request(`users.php?action=profile&user_id=${userId}`);
    }

    async updateProfile(userData) {
        return this.request('users.php', {
            method: 'PUT',
            body: JSON.stringify(userData)
        });
    }

    async getUserConditions(userId) {
        return this.request(`users.php?action=conditions&user_id=${userId}`);
    }

    async addUserCondition(userId, conditionId, diagnosedDate, notes) {
        return this.request('users.php?action=add_condition', {
            method: 'POST',
            body: JSON.stringify({
                user_id: userId,
                condition_id: conditionId,
                diagnosed_date: diagnosedDate,
                notes: notes
            })
        });
    }

    async removeUserCondition(userId, conditionId) {
        return this.request(`users.php?action=remove_condition&user_id=${userId}&condition_id=${conditionId}`, {
            method: 'DELETE'
        });
    }

    async getMedicines(userId, activeOnly = true) {
        return this.request(`medicines.php?user_id=${userId}&active_only=${activeOnly}`);
    }

    async getMedicine(medicineId) {
        return this.request(`medicines.php?action=single&medicine_id=${medicineId}`);
    }

    async getTodaysMedicines(userId) {
        return this.request(`medicines.php?action=today&user_id=${userId}`);
    }

    async createMedicine(medicineData) {
        return this.request('medicines.php?action=create', {
            method: 'POST',
            body: JSON.stringify(medicineData)
        });
    }

    async updateMedicine(medicineData) {
        return this.request('medicines.php', {
            method: 'PUT',
            body: JSON.stringify(medicineData)
        });
    }

    async deleteMedicine(medicineId, userId) {
        return this.request(`medicines.php?medicine_id=${medicineId}&user_id=${userId}`, {
            method: 'DELETE'
        });
    }

    async getDashboard(userId) {
        return this.request(`tracker.php?action=dashboard&user_id=${userId}`);
    }

    async getTodayTracking(userId) {
        return this.request(`tracker.php?action=today&user_id=${userId}`);
    }

    async recordPillStatus(userId, medicineId, scheduleId, status, date, time, notes) {
        return this.request('tracker.php?action=record', {
            method: 'POST',
            body: JSON.stringify({
                user_id: userId,
                medicine_id: medicineId,
                schedule_id: scheduleId,
                status: status,
                date: date,
                time: time,
                notes: notes
            })
        });
    }

    async getAdherenceStats(userId, days = 30) {
        return this.request(`tracker.php?action=stats&user_id=${userId}&days=${days}`);
    }

    async getMonthlyAnalytics(userId, month, year) {
        return this.request(`tracker.php?action=monthly&user_id=${userId}&month=${month}&year=${year}`);
    }

    async getMissedAlerts(userId) {
        return this.request(`tracker.php?action=alerts&user_id=${userId}`);
    }

    async getActiveDietPlan(userId) {
        return this.request(`diet.php?action=active&user_id=${userId}`);
    }

    async getDietPlans(userId) {
        return this.request(`diet.php?user_id=${userId}`);
    }

    async createDietPlan(planData) {
        return this.request('diet.php?action=create', {
            method: 'POST',
            body: JSON.stringify(planData)
        });
    }

    async updateDietPlan(planData) {
        return this.request('diet.php', {
            method: 'PUT',
            body: JSON.stringify(planData)
        });
    }

    async deleteDietPlan(planId, userId) {
        return this.request(`diet.php?plan_id=${planId}&user_id=${userId}`, {
            method: 'DELETE'
        });
    }

    async addMeal(planId, mealData) {
        return this.request('diet.php?action=add_meal', {
            method: 'POST',
            body: JSON.stringify({
                plan_id: planId,
                ...mealData
            })
        });
    }

    async getMeals(planId, day = null) {
        let url = `diet.php?action=meals&plan_id=${planId}`;
        if (day !== null) {
            url += `&day=${day}`;
        }
        return this.request(url);
    }

    async deleteMeal(mealId, planId) {
        return this.request(`diet.php?action=meal&meal_id=${mealId}&plan_id=${planId}`, {
            method: 'DELETE'
        });
    }

    async getRestrictedFoods(userId) {
        return this.request(`diet.php?action=restricted&user_id=${userId}`);
    }

    async addRestrictedFood(userId, foodName, reason, severity) {
        return this.request('diet.php?action=add_restricted', {
            method: 'POST',
            body: JSON.stringify({
                user_id: userId,
                food_name: foodName,
                reason: reason,
                severity: severity
            })
        });
    }

    async removeRestrictedFood(restrictedId, userId) {
        return this.request(`diet.php?action=restricted&restricted_id=${restrictedId}&user_id=${userId}`, {
            method: 'DELETE'
        });
    }

    async searchFoods(keyword) {
        return this.request(`diet.php?action=foods&keyword=${encodeURIComponent(keyword)}`);
    }

    async getFoodsForCondition(condition) {
        return this.request(`diet.php?action=foods&condition=${encodeURIComponent(condition)}`);
    }

    async getHealthConditions() {
        return this.request('diet.php?action=conditions');
    }

    async generateMealPlan(userId, condition, targetCalories) {
        return this.request('diet.php?action=generate', {
            method: 'POST',
            body: JSON.stringify({
                user_id: userId,
                condition: condition,
                target_calories: targetCalories
            })
        });
    }

    // =====================================================
    // HEALTH FEATURES API
    // =====================================================

    async getMissedDoses(userId, days = 7) {
        return this.request(`health.php?action=getMissedDoses&user_id=${userId}&days=${days}`);
    }

    async updateMissedDoseReason(trackerId, reason, userId) {
        return this.request('health.php?action=updateMissedDoseReason', {
            method: 'PUT',
            body: JSON.stringify({ tracker_id: trackerId, reason, user_id: userId })
        });
    }

    async getRecoverySuggestion(medicineId, missedTime) {
        return this.request(`health.php?action=getRecoverySuggestion&medicine_id=${medicineId}&missed_time=${missedTime}`);
    }

    async checkInteractions(userId) {
        return this.request(`health.php?action=checkInteractions&user_id=${userId}`);
    }

    async getRefillAlerts(userId, threshold = 7) {
        return this.request(`health.php?action=getRefillAlerts&user_id=${userId}&threshold=${threshold}`);
    }

    async updateMedicinePills(medicineId, userId, remainingPills) {
        return this.request('health.php?action=updateMedicinePills', {
            method: 'PUT',
            body: JSON.stringify({ medicine_id: medicineId, user_id: userId, remaining_pills: remainingPills })
        });
    }

    async getMedicineHistory(medicineId, userId) {
        return this.request(`health.php?action=getMedicineHistory&medicine_id=${medicineId}&user_id=${userId}`);
    }

    async getUserMedicineTimeline(userId) {
        return this.request(`health.php?action=getUserMedicineTimeline&user_id=${userId}`);
    }

    async reportSideEffect(userId, medicineId, symptom, severity, notes) {
        return this.request('health.php?action=reportSideEffect', {
            method: 'POST',
            body: JSON.stringify({ user_id: userId, medicine_id: medicineId, symptom, severity, notes })
        });
    }

    async getUserSideEffects(userId) {
        return this.request(`health.php?action=getUserSideEffects&user_id=${userId}`);
    }

    async getCommonSideEffects(medicineName) {
        return this.request(`health.php?action=getCommonSideEffects&medicine_name=${encodeURIComponent(medicineName)}`);
    }

    // =====================================================
    // LAB INTELLIGENCE API
    // =====================================================

    async analyzeReport(reportId, userId) {
        return this.request(`lab.php?action=analyzeReport&report_id=${reportId}&user_id=${userId}`);
    }

    async getAbnormalValues(userId) {
        return this.request(`lab.php?action=getAbnormalValues&user_id=${userId}`);
    }

    async getParameterTrendAdvanced(userId, parameterName, days = 365) {
        return this.request(`lab.php?action=getParameterTrend&user_id=${userId}&parameter_name=${encodeURIComponent(parameterName)}&days=${days}`);
    }

    async compareReports(reportId1, reportId2, userId) {
        return this.request(`lab.php?action=compareReports&report_id_1=${reportId1}&report_id_2=${reportId2}&user_id=${userId}`);
    }

    async getHealthRiskSummary(userId) {
        return this.request(`lab.php?action=getHealthRiskSummary&user_id=${userId}`);
    }

    async getHealthInsights(userId) {
        return this.request(`lab.php?action=getHealthInsights&user_id=${userId}`);
    }

    async getDoctorReport(userId) {
        return this.request(`lab.php?action=getDoctorReport&user_id=${userId}`);
    }

    // =====================================================
    // LIFESTYLE API
    // =====================================================

    async logWaterIntake(userId, amountMl, date = null) {
        return this.request('lifestyle.php?action=logWater', {
            method: 'POST',
            body: JSON.stringify({ user_id: userId, amount_ml: amountMl, date })
        });
    }

    async getDailyWaterIntake(userId, date = null) {
        let url = `lifestyle.php?action=getDailyWater&user_id=${userId}`;
        if (date) url += `&date=${date}`;
        return this.request(url);
    }

    async getWaterHistory(userId, days = 7) {
        return this.request(`lifestyle.php?action=getWaterHistory&user_id=${userId}&days=${days}`);
    }

    async logActivity(userId, activityType, durationMinutes, intensity = 'moderate', caloriesBurned = null, notes = null) {
        return this.request('lifestyle.php?action=logActivity', {
            method: 'POST',
            body: JSON.stringify({ user_id: userId, activity_type: activityType, duration_minutes: durationMinutes, intensity, calories_burned: caloriesBurned, notes })
        });
    }

    async getDailyActivity(userId, date = null) {
        let url = `lifestyle.php?action=getDailyActivity&user_id=${userId}`;
        if (date) url += `&date=${date}`;
        return this.request(url);
    }

    async getActivityHistory(userId, days = 7) {
        return this.request(`lifestyle.php?action=getActivityHistory&user_id=${userId}&days=${days}`);
    }

    async getActivityTypes() {
        return this.request('lifestyle.php?action=getActivityTypes');
    }

    async getCalorieBalance(userId, date = null) {
        let url = `lifestyle.php?action=getCalorieBalance&user_id=${userId}`;
        if (date) url += `&date=${date}`;
        return this.request(url);
    }

    async generateGroceryList(userId, weekStartDate = null) {
        return this.request('lifestyle.php?action=generateGroceryList', {
            method: 'POST',
            body: JSON.stringify({ user_id: userId, week_start_date: weekStartDate })
        });
    }

    async getGroceryList(userId, weekStartDate = null) {
        let url = `lifestyle.php?action=getGroceryList&user_id=${userId}`;
        if (weekStartDate) url += `&week_start_date=${weekStartDate}`;
        return this.request(url);
    }

    async addGroceryItem(userId, itemName, quantity, category = 'Other') {
        return this.request('lifestyle.php?action=addGroceryItem', {
            method: 'POST',
            body: JSON.stringify({ user_id: userId, item_name: itemName, quantity, category })
        });
    }

    async updateGroceryItem(itemId, userId, isPurchased) {
        return this.request('lifestyle.php?action=updateGroceryItem', {
            method: 'PUT',
            body: JSON.stringify({ id: itemId, user_id: userId, is_purchased: isPurchased })
        });
    }

    async getFoodRestrictions(userId) {
        return this.request(`lifestyle.php?action=getFoodRestrictions&user_id=${userId}`);
    }

    async getDietSuggestions(userId) {
        return this.request(`lifestyle.php?action=getDietSuggestions&user_id=${userId}`);
    }

    // =====================================================
    // EMERGENCY & SAFETY API
    // =====================================================

    async getEmergencyInfo(userId) {
        return this.request(`emergency.php?action=getEmergencyInfo&user_id=${userId}`);
    }

    async getEmergencyInfoByCode(accessCode) {
        return this.request(`emergency.php?action=getEmergencyInfoByCode&code=${accessCode}`);
    }

    async saveEmergencyInfo(userId, data) {
        return this.request('emergency.php?action=saveEmergencyInfo', {
            method: 'POST',
            body: JSON.stringify({ user_id: userId, ...data })
        });
    }

    async generateQRCode(userId) {
        return this.request(`emergency.php?action=generateQRCode&user_id=${userId}`);
    }

    async addCaregiver(patientUserId, caregiverEmail, caregiverName, relationship, permissions = []) {
        return this.request('emergency.php?action=addCaregiver', {
            method: 'POST',
            body: JSON.stringify({ patient_user_id: patientUserId, caregiver_email: caregiverEmail, caregiver_name: caregiverName, relationship, permissions })
        });
    }

    async getCaregivers(userId) {
        return this.request(`emergency.php?action=getCaregivers&user_id=${userId}`);
    }

    async removeCaregiver(caregiverId, userId) {
        return this.request(`emergency.php?action=removeCaregiver&id=${caregiverId}&user_id=${userId}`, {
            method: 'DELETE'
        });
    }

    async verifyCaregiverAccess(accessCode) {
        return this.request('emergency.php?action=verifyCaregiverAccess', {
            method: 'POST',
            body: JSON.stringify({ access_code: accessCode })
        });
    }

    async getCaregiverPatientData(accessCode) {
        return this.request(`emergency.php?action=getCaregiverPatientData&access_code=${accessCode}`);
    }

    async getEmergencyContacts(userId) {
        return this.request(`emergency.php?action=getEmergencyContacts&user_id=${userId}`);
    }

    async addEmergencyContact(userId, name, phone, relationship, isPrimary = false) {
        return this.request('emergency.php?action=addEmergencyContact', {
            method: 'POST',
            body: JSON.stringify({ user_id: userId, name, phone, relationship, is_primary: isPrimary })
        });
    }

    async deleteEmergencyContact(contactId, userId) {
        return this.request(`emergency.php?action=deleteEmergencyContact&id=${contactId}&user_id=${userId}`, {
            method: 'DELETE'
        });
    }

    // =====================================================
    // NOTIFICATIONS API
    // =====================================================

    async getNotifications(userId, unreadOnly = false, limit = 50) {
        return this.request(`notifications.php?action=getNotifications&user_id=${userId}&unread_only=${unreadOnly}&limit=${limit}`);
    }

    async getUnreadNotificationCount(userId) {
        return this.request(`notifications.php?action=getUnreadCount&user_id=${userId}`);
    }

    async markNotificationAsRead(notificationId, userId) {
        return this.request('notifications.php?action=markAsRead', {
            method: 'PUT',
            body: JSON.stringify({ id: notificationId, user_id: userId })
        });
    }

    async markAllNotificationsAsRead(userId) {
        return this.request('notifications.php?action=markAllAsRead', {
            method: 'PUT',
            body: JSON.stringify({ user_id: userId })
        });
    }

    async getNotificationPreferences(userId) {
        return this.request(`notifications.php?action=getPreferences&user_id=${userId}`);
    }

    async updateNotificationPreferences(userId, preferences) {
        return this.request('notifications.php?action=updatePreferences', {
            method: 'PUT',
            body: JSON.stringify({ user_id: userId, ...preferences })
        });
    }

    async generateWeeklySummary(userId) {
        return this.request('notifications.php?action=generateWeeklySummary', {
            method: 'POST',
            body: JSON.stringify({ user_id: userId })
        });
    }

    async getWeeklySummary(userId, weekStartDate = null) {
        let url = `notifications.php?action=getWeeklySummary&user_id=${userId}`;
        if (weekStartDate) url += `&week_start_date=${weekStartDate}`;
        return this.request(url);
    }

    async getWeeklySummaryHistory(userId, limit = 10) {
        return this.request(`notifications.php?action=getWeeklySummaryHistory&user_id=${userId}&limit=${limit}`);
    }

    // =====================================================
    // ADMIN / SYSTEM API
    // =====================================================

    async getAuditLog(filters = {}, limit = 100, offset = 0) {
        let url = `admin.php?action=getAuditLog&limit=${limit}&offset=${offset}`;
        Object.keys(filters).forEach(key => {
            if (filters[key]) url += `&${key}=${filters[key]}`;
        });
        return this.request(url);
    }

    async getUserActivityLog(userId, limit = 50) {
        return this.request(`admin.php?action=getUserActivityLog&user_id=${userId}&limit=${limit}`);
    }

    async getAnalyticsSummary() {
        return this.request('admin.php?action=getAnalyticsSummary');
    }

    async getUserAnalytics(userId) {
        return this.request(`admin.php?action=getUserAnalytics&user_id=${userId}`);
    }

    async exportUserData(userId, format = 'json') {
        return this.request(`admin.php?action=exportUserData&user_id=${userId}&format=${format}`);
    }

    async importUserData(userId, data) {
        return this.request('admin.php?action=importUserData', {
            method: 'POST',
            body: JSON.stringify({ user_id: userId, data })
        });
    }

    async recordOCRCorrection(reportId, originalText, correctedText, fieldType) {
        return this.request('admin.php?action=recordOCRCorrection', {
            method: 'POST',
            body: JSON.stringify({ report_id: reportId, original_text: originalText, corrected_text: correctedText, field_type: fieldType })
        });
    }

    async getOCRCorrections(fieldType = null, limit = 100) {
        let url = `admin.php?action=getOCRCorrections&limit=${limit}`;
        if (fieldType) url += `&field_type=${fieldType}`;
        return this.request(url);
    }

    async applyOCRCorrections(text) {
        return this.request('admin.php?action=applyOCRCorrections', {
            method: 'POST',
            body: JSON.stringify({ text })
        });
    }

    // =====================================================
    // DOCTOR API FUNCTIONS
    // =====================================================

    async getDoctorDashboard(doctorId) {
        return this.request(`doctor.php?action=dashboard&doctor_id=${doctorId}`);
    }

    async getDoctorPatients(doctorId) {
        return this.request(`doctor.php?action=patients&doctor_id=${doctorId}`);
    }

    async searchPatient(doctorId, username) {
        return this.request(`doctor.php?action=search_patient&doctor_id=${doctorId}&username=${encodeURIComponent(username)}`);
    }

    async addPatientToDoctor(doctorId, patientId, notes = null) {
        return this.request('doctor.php?action=add_patient', {
            method: 'POST',
            body: JSON.stringify({ doctor_id: doctorId, patient_id: patientId, notes })
        });
    }

    async removePatientFromDoctor(doctorId, patientId) {
        return this.request(`doctor.php?action=remove_patient&doctor_id=${doctorId}&patient_id=${patientId}`, {
            method: 'DELETE'
        });
    }

    async createPatientByDoctor(doctorId, patientData) {
        return this.request('doctor.php?action=create_patient', {
            method: 'POST',
            body: JSON.stringify({ doctor_id: doctorId, ...patientData })
        });
    }

    async getPatientDetails(doctorId, patientId) {
        return this.request(`doctor.php?action=patient_details&doctor_id=${doctorId}&patient_id=${patientId}`);
    }

    async getPatientCompliance(doctorId, patientId, days = 7) {
        return this.request(`doctor.php?action=patient_compliance&doctor_id=${doctorId}&patient_id=${patientId}&days=${days}`);
    }

    async assignMedicineToPatient(doctorId, patientId, medicineData) {
        return this.request('doctor.php?action=assign_medicine', {
            method: 'POST',
            body: JSON.stringify({ doctor_id: doctorId, patient_id: patientId, ...medicineData })
        });
    }

    async assignDietToPatient(doctorId, patientId, dietData) {
        return this.request('doctor.php?action=assign_diet', {
            method: 'POST',
            body: JSON.stringify({ doctor_id: doctorId, patient_id: patientId, ...dietData })
        });
    }

    async updatePatientMedicine(doctorId, patientId, medicineId, medicineData) {
        return this.request('doctor.php?action=update_medicine', {
            method: 'PUT',
            body: JSON.stringify({ doctor_id: doctorId, patient_id: patientId, medicine_id: medicineId, ...medicineData })
        });
    }

    async sendMessageToPatient(doctorId, patientId, title, message) {
        return this.request('doctor.php?action=send_message', {
            method: 'POST',
            body: JSON.stringify({ doctor_id: doctorId, patient_id: patientId, title, message })
        });
    }

    async getPatientDoctors(patientId) {
        return this.request(`users.php?action=doctors&patient_id=${patientId}`);
    }
}

const api = new ApiService();
