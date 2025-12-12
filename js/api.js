/**
 * MediAssist+ API Service
 * Handles all communication with the PHP backend
 */

const API_BASE = 'api/endpoints';

class ApiService {
    constructor() {
        this.baseUrl = API_BASE;
    }

    /**
     * Generic fetch wrapper with error handling
     */
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
            
            // Try to parse as JSON
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

    // ==================== USER ENDPOINTS ====================

    /**
     * Register new user
     */
    async register(userData) {
        return this.request('users.php?action=register', {
            method: 'POST',
            body: JSON.stringify(userData)
        });
    }

    /**
     * Login user
     */
    async login(email, password) {
        return this.request('users.php?action=login', {
            method: 'POST',
            body: JSON.stringify({ email, password })
        });
    }

    /**
     * Get user profile
     */
    async getProfile(userId) {
        return this.request(`users.php?action=profile&user_id=${userId}`);
    }

    /**
     * Update user profile
     */
    async updateProfile(userData) {
        return this.request('users.php', {
            method: 'PUT',
            body: JSON.stringify(userData)
        });
    }

    /**
     * Get user health conditions
     */
    async getUserConditions(userId) {
        return this.request(`users.php?action=conditions&user_id=${userId}`);
    }

    /**
     * Add health condition to user
     */
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

    /**
     * Remove health condition from user
     */
    async removeUserCondition(userId, conditionId) {
        return this.request(`users.php?action=remove_condition&user_id=${userId}&condition_id=${conditionId}`, {
            method: 'DELETE'
        });
    }

    // ==================== MEDICINE ENDPOINTS ====================

    /**
     * Get all medicines for user
     */
    async getMedicines(userId, activeOnly = true) {
        return this.request(`medicines.php?user_id=${userId}&active_only=${activeOnly}`);
    }

    /**
     * Get single medicine
     */
    async getMedicine(medicineId) {
        return this.request(`medicines.php?action=single&medicine_id=${medicineId}`);
    }

    /**
     * Get today's medicines
     */
    async getTodaysMedicines(userId) {
        return this.request(`medicines.php?action=today&user_id=${userId}`);
    }

    /**
     * Create new medicine
     */
    async createMedicine(medicineData) {
        return this.request('medicines.php?action=create', {
            method: 'POST',
            body: JSON.stringify(medicineData)
        });
    }

    /**
     * Update medicine
     */
    async updateMedicine(medicineData) {
        return this.request('medicines.php', {
            method: 'PUT',
            body: JSON.stringify(medicineData)
        });
    }

    /**
     * Delete medicine
     */
    async deleteMedicine(medicineId, userId) {
        return this.request(`medicines.php?medicine_id=${medicineId}&user_id=${userId}`, {
            method: 'DELETE'
        });
    }

    // ==================== PILL TRACKER ENDPOINTS ====================

    /**
     * Get dashboard data
     */
    async getDashboard(userId) {
        return this.request(`tracker.php?action=dashboard&user_id=${userId}`);
    }

    /**
     * Get today's tracking
     */
    async getTodayTracking(userId) {
        return this.request(`tracker.php?action=today&user_id=${userId}`);
    }

    /**
     * Record pill status
     */
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

    /**
     * Get adherence statistics
     */
    async getAdherenceStats(userId, days = 30) {
        return this.request(`tracker.php?action=stats&user_id=${userId}&days=${days}`);
    }

    /**
     * Get monthly analytics
     */
    async getMonthlyAnalytics(userId, month, year) {
        return this.request(`tracker.php?action=monthly&user_id=${userId}&month=${month}&year=${year}`);
    }

    /**
     * Get missed pill alerts
     */
    async getMissedAlerts(userId) {
        return this.request(`tracker.php?action=alerts&user_id=${userId}`);
    }

    // ==================== REPORTS ENDPOINTS ====================

    /**
     * Get all reports for user
     */
    async getReports(userId, reportType = null) {
        let url = `reports.php?user_id=${userId}`;
        if (reportType) {
            url += `&report_type=${reportType}`;
        }
        return this.request(url);
    }

    /**
     * Get single report
     */
    async getReport(reportId, userId) {
        return this.request(`reports.php?action=single&report_id=${reportId}&user_id=${userId}`);
    }

    /**
     * Upload report (uses FormData)
     */
    async uploadReport(formData) {
        const response = await fetch(`${this.baseUrl}/reports.php?action=upload`, {
            method: 'POST',
            body: formData
        });
        return response.json();
    }

    /**
     * Delete report
     */
    async deleteReport(reportId, userId) {
        return this.request(`reports.php?report_id=${reportId}&user_id=${userId}`, {
            method: 'DELETE'
        });
    }

    /**
     * Get abnormal values history
     */
    async getAbnormalHistory(userId) {
        return this.request(`reports.php?action=abnormal_history&user_id=${userId}`);
    }

    /**
     * Get parameter trend
     */
    async getParameterTrend(userId, parameter) {
        return this.request(`reports.php?action=parameter_trend&user_id=${userId}&parameter=${parameter}`);
    }

    // ==================== DIET ENDPOINTS ====================

    /**
     * Get active diet plan
     */
    async getActiveDietPlan(userId) {
        return this.request(`diet.php?action=active&user_id=${userId}`);
    }

    /**
     * Get all diet plans
     */
    async getDietPlans(userId) {
        return this.request(`diet.php?user_id=${userId}`);
    }

    /**
     * Create diet plan
     */
    async createDietPlan(planData) {
        return this.request('diet.php?action=create', {
            method: 'POST',
            body: JSON.stringify(planData)
        });
    }

    /**
     * Update diet plan
     */
    async updateDietPlan(planData) {
        return this.request('diet.php', {
            method: 'PUT',
            body: JSON.stringify(planData)
        });
    }

    /**
     * Delete diet plan
     */
    async deleteDietPlan(planId, userId) {
        return this.request(`diet.php?plan_id=${planId}&user_id=${userId}`, {
            method: 'DELETE'
        });
    }

    /**
     * Add meal to plan
     */
    async addMeal(planId, mealData) {
        return this.request('diet.php?action=add_meal', {
            method: 'POST',
            body: JSON.stringify({
                plan_id: planId,
                ...mealData
            })
        });
    }

    /**
     * Get meals for plan
     */
    async getMeals(planId, day = null) {
        let url = `diet.php?action=meals&plan_id=${planId}`;
        if (day !== null) {
            url += `&day=${day}`;
        }
        return this.request(url);
    }

    /**
     * Delete meal
     */
    async deleteMeal(mealId, planId) {
        return this.request(`diet.php?action=meal&meal_id=${mealId}&plan_id=${planId}`, {
            method: 'DELETE'
        });
    }

    /**
     * Get restricted foods
     */
    async getRestrictedFoods(userId) {
        return this.request(`diet.php?action=restricted&user_id=${userId}`);
    }

    /**
     * Add restricted food
     */
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

    /**
     * Remove restricted food
     */
    async removeRestrictedFood(restrictedId, userId) {
        return this.request(`diet.php?action=restricted&restricted_id=${restrictedId}&user_id=${userId}`, {
            method: 'DELETE'
        });
    }

    /**
     * Search foods
     */
    async searchFoods(keyword) {
        return this.request(`diet.php?action=foods&keyword=${encodeURIComponent(keyword)}`);
    }

    /**
     * Get foods for condition
     */
    async getFoodsForCondition(condition) {
        return this.request(`diet.php?action=foods&condition=${encodeURIComponent(condition)}`);
    }

    /**
     * Get all health conditions
     */
    async getHealthConditions() {
        return this.request('diet.php?action=conditions');
    }

    /**
     * Generate meal plan
     */
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
}

// Export singleton instance
const api = new ApiService();
