/**
 * MediAssist+ Main Application
 * Handles UI interactions and state management
 */

// ==================== STATE MANAGEMENT ====================

const state = {
    user: null,
    currentPage: 'dashboard',
    selectedDate: new Date(),
    medicines: [],
    reports: [],
    dietPlan: null,
    alerts: [],
    darkMode: false,
    notificationsEnabled: false
};

// Drug interactions database (common interactions)
const drugInteractions = {
    'aspirin': ['warfarin', 'ibuprofen', 'naproxen', 'heparin', 'clopidogrel'],
    'warfarin': ['aspirin', 'ibuprofen', 'vitamin k', 'garlic', 'ginkgo'],
    'metformin': ['alcohol', 'contrast dye'],
    'lisinopril': ['potassium', 'spironolactone', 'nsaids'],
    'atorvastatin': ['grapefruit', 'erythromycin', 'clarithromycin', 'niacin'],
    'amlodipine': ['simvastatin', 'grapefruit'],
    'omeprazole': ['clopidogrel', 'methotrexate'],
    'metoprolol': ['verapamil', 'clonidine', 'digoxin'],
    'losartan': ['potassium', 'lithium', 'nsaids'],
    'gabapentin': ['antacids', 'morphine', 'hydrocodone'],
    'hydrochlorothiazide': ['lithium', 'digoxin', 'nsaids'],
    'levothyroxine': ['calcium', 'iron', 'antacids', 'coffee'],
    'prednisone': ['nsaids', 'warfarin', 'diabetes medications'],
    'albuterol': ['beta blockers', 'digoxin'],
    'insulin': ['alcohol', 'beta blockers', 'ace inhibitors']
};

// ==================== INITIALIZATION ====================

document.addEventListener('DOMContentLoaded', () => {
    initializeApp();
});

function initializeApp() {
    // Load dark mode preference
    const savedDarkMode = localStorage.getItem('mediassist_darkmode');
    if (savedDarkMode === 'true') {
        state.darkMode = true;
        document.documentElement.setAttribute('data-theme', 'dark');
        updateThemeIcon();
    }
    
    // Load notification preference
    if (localStorage.getItem('mediassist_notifications') === 'true' && 
        'Notification' in window && 
        Notification.permission === 'granted') {
        state.notificationsEnabled = true;
    }

    // Check if user is logged in
    const savedUser = localStorage.getItem('mediassist_user');
    if (savedUser) {
        state.user = JSON.parse(savedUser);
        showApp();
        updateUserDisplay();
        loadDashboard();
    } else {
        showLanding();
    }

    // Initialize event listeners
    initEventListeners();
    
    // Set current date
    updateCurrentDate();
    
    // Initialize date for tracker
    updateTrackerDate();
    
    // Start real-time clock
    startLiveClock();
}

// ==================== LANDING PAGE ====================

function showLanding() {
    document.getElementById('landingPage').style.display = 'block';
    document.getElementById('appContainer').style.display = 'none';
    hideAuthModal();
}

function showApp() {
    document.getElementById('landingPage').style.display = 'none';
    document.getElementById('appContainer').style.display = 'flex';
    hideAuthModal();
}

function showLandingAuth(tab) {
    showAuthModal();
    switchAuthTab(tab);
}

function scrollToSection(sectionId) {
    const section = document.getElementById(sectionId);
    if (section) {
        section.scrollIntoView({ behavior: 'smooth' });
    }
}

function toggleMobileMenu() {
    const navLinks = document.querySelector('.landing-nav-links');
    navLinks.style.display = navLinks.style.display === 'flex' ? 'none' : 'flex';
}

function initEventListeners() {
    // Sidebar toggle
    const sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) sidebarToggle.addEventListener('click', toggleSidebar);
    
    // Navigation
    document.querySelectorAll('.nav-item').forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            const page = item.dataset.page;
            showPage(page);
        });
    });

    // Auth tabs
    document.querySelectorAll('.auth-tab').forEach(tab => {
        tab.addEventListener('click', () => switchAuthTab(tab.dataset.tab));
    });

    // Forms
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const medicineForm = document.getElementById('medicineForm');
    const uploadForm = document.getElementById('uploadForm');
    const dietPlanForm = document.getElementById('dietPlanForm');
    const conditionForm = document.getElementById('conditionForm');
    const restrictedFoodForm = document.getElementById('restrictedFoodForm');
    const profileForm = document.getElementById('profileForm');
    
    if (loginForm) loginForm.addEventListener('submit', handleLogin);
    if (registerForm) registerForm.addEventListener('submit', handleRegister);
    if (medicineForm) medicineForm.addEventListener('submit', handleMedicineSubmit);
    if (uploadForm) uploadForm.addEventListener('submit', handleReportUpload);
    if (dietPlanForm) dietPlanForm.addEventListener('submit', handleDietPlanSubmit);
    if (conditionForm) conditionForm.addEventListener('submit', handleConditionSubmit);
    if (restrictedFoodForm) restrictedFoodForm.addEventListener('submit', handleRestrictedFoodSubmit);
    if (profileForm) profileForm.addEventListener('submit', handleProfileUpdate);

    // Logout
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) logoutBtn.addEventListener('click', handleLogout);

    // Report type filter
    const reportTypeFilter = document.getElementById('reportTypeFilter');
    if (reportTypeFilter) reportTypeFilter.addEventListener('change', filterReports);

    // Date navigation for tracker
    const prevDay = document.getElementById('prevDay');
    const nextDay = document.getElementById('nextDay');
    if (prevDay) prevDay.addEventListener('click', () => navigateDay(-1));
    if (nextDay) nextDay.addEventListener('click', () => navigateDay(1));

    // File upload preview
    const reportFile = document.getElementById('reportFile');
    if (reportFile) reportFile.addEventListener('change', handleFileSelect);

    // Day selector for diet
    document.querySelectorAll('.day-selector .btn').forEach(btn => {
        btn.addEventListener('click', () => selectDietDay(btn.dataset.day));
    });

    // Food search
    const foodSearchInput = document.getElementById('foodSearchInput');
    if (foodSearchInput) {
        foodSearchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') searchFoods();
        });
    }
}

// ==================== NAVIGATION ====================

function showPage(page) {
    // Update navigation
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.toggle('active', item.dataset.page === page);
    });

    // Update pages
    document.querySelectorAll('.page').forEach(p => {
        p.classList.toggle('active', p.id === `page-${page}`);
    });

    // Update title
    const titles = {
        dashboard: 'Dashboard',
        medicines: 'My Medicines',
        tracker: 'Pill Tracker',
        reports: 'Lab Reports',
        diet: 'Diet & Nutrition',
        profile: 'My Profile'
    };
    document.getElementById('pageTitle').textContent = titles[page] || 'MediAssist+';

    state.currentPage = page;

    // Load page-specific data
    loadPageData(page);
}

function loadPageData(page) {
    if (!state.user) return;

    switch (page) {
        case 'dashboard':
            loadDashboard();
            break;
        case 'medicines':
            loadMedicines();
            break;
        case 'tracker':
            loadTracker();
            break;
        case 'reports':
            loadReports();
            break;
        case 'diet':
            loadDietPlan();
            break;
        case 'profile':
            loadProfile();
            break;
    }
}

function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('collapsed');
}

// ==================== AUTHENTICATION ====================

function showAuthModal() {
    document.getElementById('authModal').classList.add('active');
}

function hideAuthModal() {
    document.getElementById('authModal').classList.remove('active');
}

function switchAuthTab(tab) {
    document.querySelectorAll('.auth-tab').forEach(t => {
        t.classList.toggle('active', t.dataset.tab === tab);
    });
    document.querySelectorAll('.auth-form').forEach(f => {
        f.classList.toggle('active', f.id === `${tab}Form`);
    });
}

async function handleLogin(e) {
    e.preventDefault();
    
    const email = document.getElementById('loginEmail').value;
    const password = document.getElementById('loginPassword').value;

    showLoading();
    try {
        const response = await api.login(email, password);
        if (response.success) {
            state.user = response.user;
            localStorage.setItem('mediassist_user', JSON.stringify(response.user));
            showApp();
            updateUserDisplay();
            loadDashboard();
            showToast('Login successful!', 'success');
        }
    } catch (error) {
        showToast(error.message || 'Login failed', 'error');
    }
    hideLoading();
}

async function handleRegister(e) {
    e.preventDefault();
    
    const name = document.getElementById('regName').value;
    const email = document.getElementById('regEmail').value;
    const password = document.getElementById('regPassword').value;
    const confirmPassword = document.getElementById('regConfirmPassword').value;

    if (password !== confirmPassword) {
        showToast('Passwords do not match', 'error');
        return;
    }

    showLoading();
    try {
        const response = await api.register({
            email: email,
            password: password,
            full_name: name
        });
        
        if (response.success) {
            showToast('Registration successful! Please login.', 'success');
            switchAuthTab('login');
            document.getElementById('loginEmail').value = email;
        }
    } catch (error) {
        showToast(error.message || 'Registration failed', 'error');
    }
    hideLoading();
}

function handleLogout() {
    state.user = null;
    localStorage.removeItem('mediassist_user');
    showLanding();
    showToast('Logged out successfully', 'info');
}

function updateUserDisplay() {
    if (state.user) {
        document.getElementById('userName').textContent = state.user.full_name;
        const avatarUrl = `https://ui-avatars.com/api/?name=${encodeURIComponent(state.user.full_name)}&background=4f46e5&color=fff`;
        document.querySelector('.user-avatar').src = avatarUrl;
    }
}

// ==================== DASHBOARD ====================

async function loadDashboard() {
    if (!state.user) return;

    showLoading();
    try {
        const response = await api.getDashboard(state.user.id);
        
        if (response.success) {
            const dashboard = response.dashboard;
            
            // Update summary stats
            document.getElementById('totalPills').textContent = dashboard.summary.total;
            document.getElementById('takenPills').textContent = dashboard.summary.taken;
            document.getElementById('pendingPills').textContent = dashboard.summary.pending;
            document.getElementById('missedPills').textContent = dashboard.summary.missed;

            // Update progress bar
            const progress = dashboard.summary.total > 0 
                ? (dashboard.summary.taken / dashboard.summary.total) * 100 
                : 0;
            document.getElementById('pillProgress').style.width = `${progress}%`;

            // Update adherence circle
            const adherencePercent = dashboard.adherence.percentage || 0;
            document.getElementById('adherenceCircle').setAttribute('stroke-dasharray', `${adherencePercent}, 100`);
            document.getElementById('adherencePercent').textContent = `${adherencePercent}%`;

            // Update streak
            document.getElementById('currentStreak').textContent = dashboard.adherence.streak || 0;

            // Update upcoming reminders
            renderUpcomingReminders(dashboard.pills_by_time);

            // Update alerts
            state.alerts = dashboard.alerts;
            renderAlerts(dashboard.alerts);
            updateNotificationBadge(dashboard.alerts.length);

            // Load recent reports
            loadRecentReports();
            
            // Load weekly adherence chart
            loadWeeklyChart();
            
            // Load medicines for notification system
            loadMedicinesForNotifications();
        }
    } catch (error) {
        console.error('Dashboard load error:', error);
        showToast('Failed to load dashboard', 'error');
    }
    hideLoading();
}

function renderUpcomingReminders(pillsByTime) {
    const container = document.getElementById('upcomingReminders');
    const now = new Date();
    const currentTime = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
    
    let html = '';
    let upcomingCount = 0;

    Object.keys(pillsByTime).sort().forEach(time => {
        if (time >= currentTime && upcomingCount < 3) {
            pillsByTime[time].forEach(pill => {
                if (pill.status !== 'taken' && upcomingCount < 3) {
                    html += `
                        <li>
                            <i class="fas fa-clock"></i>
                            <span><strong>${formatTime(time)}</strong> - ${pill.name}</span>
                        </li>
                    `;
                    upcomingCount++;
                }
            });
        }
    });

    container.innerHTML = html || '<li class="no-data">No upcoming reminders</li>';
}

function renderAlerts(alerts) {
    const container = document.getElementById('healthAlerts');
    
    if (!alerts || alerts.length === 0) {
        container.innerHTML = '<li class="no-data">No active alerts</li>';
        return;
    }

    let html = alerts.slice(0, 3).map(alert => `
        <li class="alert-item">
            <i class="fas fa-exclamation-circle"></i>
            <span>Missed: ${alert.medicine_name} at ${formatTime(alert.scheduled_time)}</span>
        </li>
    `).join('');

    container.innerHTML = html;
}

async function loadRecentReports() {
    try {
        const response = await api.getReports(state.user.id);
        if (response.success) {
            const container = document.getElementById('recentReports');
            const reports = response.reports.slice(0, 3);

            if (reports.length === 0) {
                container.innerHTML = '<li class="no-data">No reports uploaded yet</li>';
                return;
            }

            container.innerHTML = reports.map(report => `
                <li onclick="showReportDetail(${report.id})">
                    <i class="fas fa-file-medical"></i>
                    <div>
                        <strong>${formatReportType(report.report_type)}</strong>
                        <small>${formatDate(report.report_date)}</small>
                    </div>
                </li>
            `).join('');
        }
    } catch (error) {
        console.error('Recent reports load error:', error);
    }
}

// ==================== MEDICINES ====================

async function loadMedicines() {
    if (!state.user) return;

    showLoading();
    try {
        const response = await api.getMedicines(state.user.id, false);
        if (response.success) {
            state.medicines = response.medicines;
            renderMedicines(response.medicines);
        }
    } catch (error) {
        showToast('Failed to load medicines', 'error');
    }
    hideLoading();
}

function renderMedicines(medicines) {
    const container = document.getElementById('medicinesGrid');
    
    if (!medicines || medicines.length === 0) {
        container.innerHTML = `
            <div class="no-data-large">
                <i class="fas fa-pills"></i>
                <p>No medicines added yet</p>
                <button class="btn btn-primary" onclick="showAddMedicineModal()">
                    <i class="fas fa-plus"></i> Add Medicine
                </button>
            </div>
        `;
        return;
    }

    container.innerHTML = medicines.map(med => `
        <div class="medicine-card">
            <div class="medicine-card-header">
                <h4>${med.name}</h4>
                <span class="dosage">${med.dosage || ''} ${formatDoseType(med.dose_type)}</span>
            </div>
            <div class="medicine-card-body">
                <div class="medicine-info">
                    <div class="medicine-info-item">
                        <i class="fas fa-calendar"></i>
                        <span>${formatDate(med.start_date)}</span>
                    </div>
                    ${med.end_date ? `
                    <div class="medicine-info-item">
                        <i class="fas fa-calendar-check"></i>
                        <span>Until ${formatDate(med.end_date)}</span>
                    </div>
                    ` : ''}
                    <div class="medicine-info-item">
                        <i class="fas fa-${med.is_active ? 'check-circle' : 'pause-circle'}"></i>
                        <span>${med.is_active ? 'Active' : 'Inactive'}</span>
                    </div>
                </div>
                ${med.schedules && med.schedules.length > 0 ? `
                <div class="medicine-schedules">
                    <h5>Schedule</h5>
                    <div class="schedule-tags">
                        ${med.schedules.map(s => `
                            <span class="schedule-tag">
                                <i class="fas fa-clock"></i>
                                ${formatTime(s.time || s.scheduled_time)}
                            </span>
                        `).join('')}
                    </div>
                </div>
                ` : ''}
                <div class="medicine-card-actions">
                    <button class="btn btn-sm btn-outline" onclick="editMedicine(${med.id})">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteMedicine(${med.id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `).join('');
}

function showAddMedicineModal() {
    document.getElementById('medicineModalTitle').textContent = 'Add Medicine';
    document.getElementById('medicineForm').reset();
    document.getElementById('medicineId').value = '';
    document.getElementById('medicineStartDate').valueAsDate = new Date();
    
    // Reset schedules to one default
    document.getElementById('schedulesList').innerHTML = `
        <div class="schedule-item">
            <input type="time" class="form-input schedule-time" value="08:00">
            <select class="form-select schedule-meal">
                <option value="anytime">Anytime</option>
                <option value="before_meal">Before Meal</option>
                <option value="after_meal">After Meal</option>
                <option value="with_meal">With Meal</option>
                <option value="empty_stomach">Empty Stomach</option>
            </select>
            <button type="button" class="btn btn-icon btn-danger remove-schedule" onclick="removeScheduleRow(this)">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    document.getElementById('medicineModal').classList.add('active');
}

function closeMedicineModal() {
    document.getElementById('medicineModal').classList.remove('active');
}

function addScheduleRow() {
    const container = document.getElementById('schedulesList');
    const newRow = document.createElement('div');
    newRow.className = 'schedule-item';
    newRow.innerHTML = `
        <input type="time" class="form-input schedule-time" value="12:00">
        <select class="form-select schedule-meal">
            <option value="anytime">Anytime</option>
            <option value="before_meal">Before Meal</option>
            <option value="after_meal">After Meal</option>
            <option value="with_meal">With Meal</option>
            <option value="empty_stomach">Empty Stomach</option>
        </select>
        <button type="button" class="btn btn-icon btn-danger remove-schedule" onclick="removeScheduleRow(this)">
            <i class="fas fa-times"></i>
        </button>
    `;
    container.appendChild(newRow);
}

function removeScheduleRow(btn) {
    const container = document.getElementById('schedulesList');
    if (container.children.length > 1) {
        btn.closest('.schedule-item').remove();
    }
}

async function handleMedicineSubmit(e) {
    e.preventDefault();
    
    const medicineId = document.getElementById('medicineId').value;
    const schedules = [];
    
    document.querySelectorAll('.schedule-item').forEach(item => {
        schedules.push({
            time: item.querySelector('.schedule-time').value,
            meal_relation: item.querySelector('.schedule-meal').value
        });
    });

    const medicineData = {
        user_id: state.user.id,
        name: document.getElementById('medicineName').value,
        dosage: document.getElementById('medicineDosage').value,
        dose_type: document.getElementById('medicineDoseType').value,
        start_date: document.getElementById('medicineStartDate').value,
        end_date: document.getElementById('medicineEndDate').value || null,
        instructions: document.getElementById('medicineInstructions').value,
        schedules: schedules
    };

    // Check for drug interactions (only when adding new medicine)
    if (!medicineId) {
        const interactions = checkDrugInteractions(medicineData.name);
        if (interactions.length > 0) {
            showDrugInteractionWarning(interactions);
        }
    }

    showLoading();
    try {
        let response;
        if (medicineId) {
            medicineData.medicine_id = medicineId;
            response = await api.updateMedicine(medicineData);
        } else {
            response = await api.createMedicine(medicineData);
        }
        
        if (response.success) {
            closeMedicineModal();
            showToast(medicineId ? 'Medicine updated!' : 'Medicine added!', 'success');
            loadMedicines();
        }
    } catch (error) {
        showToast(error.message || 'Failed to save medicine', 'error');
    }
    hideLoading();
}

async function editMedicine(medicineId) {
    showLoading();
    try {
        const response = await api.getMedicine(medicineId);
        if (response.success) {
            const med = response.medicine;
            
            document.getElementById('medicineModalTitle').textContent = 'Edit Medicine';
            document.getElementById('medicineId').value = med.id;
            document.getElementById('medicineName').value = med.name;
            document.getElementById('medicineDosage').value = med.dosage || '';
            document.getElementById('medicineDoseType').value = med.dose_type;
            document.getElementById('medicineStartDate').value = med.start_date;
            document.getElementById('medicineEndDate').value = med.end_date || '';
            document.getElementById('medicineInstructions').value = med.instructions || '';

            // Load schedules
            const schedulesList = document.getElementById('schedulesList');
            if (med.schedules && med.schedules.length > 0) {
                schedulesList.innerHTML = med.schedules.map(s => `
                    <div class="schedule-item">
                        <input type="time" class="form-input schedule-time" value="${s.scheduled_time}">
                        <select class="form-select schedule-meal">
                            <option value="anytime" ${s.meal_relation === 'anytime' ? 'selected' : ''}>Anytime</option>
                            <option value="before_meal" ${s.meal_relation === 'before_meal' ? 'selected' : ''}>Before Meal</option>
                            <option value="after_meal" ${s.meal_relation === 'after_meal' ? 'selected' : ''}>After Meal</option>
                            <option value="with_meal" ${s.meal_relation === 'with_meal' ? 'selected' : ''}>With Meal</option>
                            <option value="empty_stomach" ${s.meal_relation === 'empty_stomach' ? 'selected' : ''}>Empty Stomach</option>
                        </select>
                        <button type="button" class="btn btn-icon btn-danger remove-schedule" onclick="removeScheduleRow(this)">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `).join('');
            }

            document.getElementById('medicineModal').classList.add('active');
        }
    } catch (error) {
        showToast('Failed to load medicine', 'error');
    }
    hideLoading();
}

async function deleteMedicine(medicineId) {
    if (!confirm('Are you sure you want to delete this medicine?')) return;

    showLoading();
    try {
        const response = await api.deleteMedicine(medicineId, state.user.id);
        if (response.success) {
            showToast('Medicine deleted!', 'success');
            loadMedicines();
        }
    } catch (error) {
        showToast(error.message || 'Failed to delete medicine', 'error');
    }
    hideLoading();
}

// ==================== PILL TRACKER ====================

async function loadTracker() {
    if (!state.user) return;

    showLoading();
    try {
        const response = await api.getTodayTracking(state.user.id);
        if (response.success) {
            renderTrackerTimeline(response.medicines);
            updateDayProgress(response.medicines);
            loadMonthlyCalendar();
        }
    } catch (error) {
        showToast('Failed to load tracker', 'error');
    }
    hideLoading();
}

function renderTrackerTimeline(medicines) {
    const container = document.getElementById('trackerTimeline');
    
    if (!medicines || medicines.length === 0) {
        container.innerHTML = `
            <div class="no-data-large">
                <i class="fas fa-check-circle"></i>
                <p>No pills scheduled for today</p>
            </div>
        `;
        return;
    }

    // Group by time
    const groupedByTime = {};
    medicines.forEach(med => {
        const time = med.scheduled_time;
        if (!groupedByTime[time]) {
            groupedByTime[time] = [];
        }
        groupedByTime[time].push(med);
    });

    let html = '';
    Object.keys(groupedByTime).sort().forEach(time => {
        const pills = groupedByTime[time];
        html += `
            <div class="time-group">
                <div class="time-group-header">
                    <i class="fas fa-clock"></i>
                    ${formatTime(time)}
                </div>
                ${pills.map(pill => `
                    <div class="pill-item">
                        <div class="pill-status-icon ${pill.status || 'pending'}">
                            <i class="fas fa-${getStatusIcon(pill.status)}"></i>
                        </div>
                        <div class="pill-info">
                            <h4>${pill.name}</h4>
                            <p>${pill.dosage || ''} ${formatDoseType(pill.dose_type)} - ${formatMealRelation(pill.meal_relation)}</p>
                        </div>
                        <div class="pill-actions">
                            ${pill.status !== 'taken' ? `
                                <button class="btn btn-sm btn-success" onclick="markPillTaken(${pill.id}, ${pill.schedule_id})">
                                    <i class="fas fa-check"></i> Take
                                </button>
                            ` : ''}
                            ${pill.status === 'pending' ? `
                                <button class="btn btn-sm btn-outline" onclick="skipPill(${pill.id}, ${pill.schedule_id})">
                                    Skip
                                </button>
                            ` : ''}
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    });

    container.innerHTML = html;
}

function updateDayProgress(medicines) {
    const total = medicines.length;
    const taken = medicines.filter(m => m.status === 'taken').length;
    const percentage = total > 0 ? Math.round((taken / total) * 100) : 0;

    document.getElementById('dayProgressCircle').setAttribute('stroke-dasharray', `${percentage}, 100`);
    document.getElementById('dayProgressText').textContent = `${percentage}%`;
}

async function markPillTaken(medicineId, scheduleId) {
    showLoading();
    try {
        const response = await api.recordPillStatus(
            state.user.id,
            medicineId,
            scheduleId,
            'taken',
            formatDateForApi(state.selectedDate),
            new Date().toTimeString().slice(0, 8)
        );
        
        if (response.success) {
            showToast('Pill marked as taken!', 'success');
            loadTracker();
            if (state.currentPage === 'dashboard') {
                loadDashboard();
            }
        }
    } catch (error) {
        showToast(error.message || 'Failed to record', 'error');
    }
    hideLoading();
}

async function skipPill(medicineId, scheduleId) {
    showLoading();
    try {
        const response = await api.recordPillStatus(
            state.user.id,
            medicineId,
            scheduleId,
            'skipped',
            formatDateForApi(state.selectedDate),
            new Date().toTimeString().slice(0, 8)
        );
        
        if (response.success) {
            showToast('Pill skipped', 'info');
            loadTracker();
        }
    } catch (error) {
        showToast(error.message || 'Failed to record', 'error');
    }
    hideLoading();
}

function navigateDay(direction) {
    state.selectedDate.setDate(state.selectedDate.getDate() + direction);
    updateTrackerDate();
    loadTracker();
}

function updateTrackerDate() {
    const trackerDate = document.getElementById('trackerDate');
    if (trackerDate) trackerDate.textContent = formatDate(state.selectedDate);
}

async function loadMonthlyCalendar() {
    const calendar = document.getElementById('miniCalendar');
    const now = new Date();
    const year = now.getFullYear();
    const month = now.getMonth();

    try {
        const response = await api.getMonthlyAnalytics(state.user.id, month + 1, year);
        const analyticsMap = {};
        
        if (response.success && response.analytics) {
            response.analytics.forEach(day => {
                analyticsMap[day.date] = day;
            });
        }

        // Generate calendar
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const daysInMonth = lastDay.getDate();
        const startDay = firstDay.getDay();

        let html = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
            .map(d => `<div class="day-label">${d}</div>`).join('');

        // Empty cells
        for (let i = 0; i < startDay; i++) {
            html += '<div class="day empty"></div>';
        }

        // Days
        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const dayData = analyticsMap[dateStr];
            let className = 'day';

            if (day === now.getDate() && month === now.getMonth()) {
                className += ' today';
            } else if (dayData) {
                if (dayData.taken === dayData.total && dayData.total > 0) {
                    className += ' perfect';
                } else if (dayData.taken > 0) {
                    className += ' partial';
                } else if (dayData.missed > 0) {
                    className += ' missed';
                }
            }

            html += `<div class="${className}">${day}</div>`;
        }

        calendar.innerHTML = html;
    } catch (error) {
        console.error('Calendar load error:', error);
    }
}

// ==================== REPORTS ====================

async function loadReports() {
    if (!state.user) return;

    showLoading();
    try {
        const reportType = document.getElementById('reportTypeFilter').value;
        const response = await api.getReports(state.user.id, reportType || null);
        
        if (response.success) {
            state.reports = response.reports;
            renderReports(response.reports);
        }
    } catch (error) {
        showToast('Failed to load reports', 'error');
    }
    hideLoading();
}

function renderReports(reports) {
    const container = document.getElementById('reportsGrid');
    
    if (!reports || reports.length === 0) {
        container.innerHTML = `
            <div class="no-data-large">
                <i class="fas fa-file-medical"></i>
                <p>No reports uploaded yet</p>
                <button class="btn btn-primary" onclick="showUploadModal()">
                    <i class="fas fa-upload"></i> Upload Report
                </button>
            </div>
        `;
        return;
    }

    container.innerHTML = reports.map(report => {
        const parsedData = report.parsed_data ? JSON.parse(report.parsed_data) : {};
        const abnormalities = report.abnormalities ? JSON.parse(report.abnormalities) : [];
        
        return `
            <div class="report-card" onclick="showReportDetail(${report.id})">
                <div class="report-card-header">
                    <span class="report-type-badge ${report.report_type}">${formatReportType(report.report_type)}</span>
                    <span class="report-date">${formatDate(report.report_date)}</span>
                </div>
                <div class="report-card-body">
                    <div class="report-summary">
                        <div class="report-stat">
                            <span class="report-stat-value normal">${parsedData.total_parameters_found || 0}</span>
                            <span class="report-stat-label">Parameters</span>
                        </div>
                        <div class="report-stat">
                            <span class="report-stat-value abnormal">${abnormalities.length || 0}</span>
                            <span class="report-stat-label">Abnormal</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function filterReports() {
    loadReports();
}

function showUploadModal() {
    document.getElementById('uploadForm').reset();
    document.getElementById('filePreview').innerHTML = '';
    document.getElementById('uploadReportDate').valueAsDate = new Date();
    document.getElementById('uploadModal').classList.add('active');
}

function closeUploadModal() {
    document.getElementById('uploadModal').classList.remove('active');
}

function handleFileSelect(e) {
    const file = e.target.files[0];
    if (file) {
        const preview = document.getElementById('filePreview');
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                preview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
            };
            reader.readAsDataURL(file);
        } else {
            preview.innerHTML = `<p><i class="fas fa-file-pdf"></i> ${file.name}</p>`;
        }
    }
}

async function handleReportUpload(e) {
    e.preventDefault();

    const formData = new FormData();
    formData.append('user_id', state.user.id);
    formData.append('report_type', document.getElementById('uploadReportType').value);
    formData.append('report_date', document.getElementById('uploadReportDate').value);
    formData.append('report', document.getElementById('reportFile').files[0]);

    showLoading();
    try {
        const response = await api.uploadReport(formData);
        
        if (response.success) {
            closeUploadModal();
            showToast('Report uploaded and analyzed!', 'success');
            loadReports();
            
            // Show the analysis result
            if (response.report_id) {
                showReportDetail(response.report_id);
            }
        }
    } catch (error) {
        showToast(error.message || 'Upload failed', 'error');
    }
    hideLoading();
}

async function showReportDetail(reportId) {
    showLoading();
    try {
        const response = await api.getReport(reportId, state.user.id);
        
        if (response.success) {
            const report = response.report;
            const parsedData = report.parsed_data || {};
            const abnormalities = report.abnormalities || [];
            const values = report.values || [];

            document.getElementById('reportDetailTitle').textContent = 
                `${formatReportType(report.report_type)} - ${formatDate(report.report_date)}`;

            let html = '';

            // Values table
            if (values.length > 0) {
                html += `
                    <div class="report-detail-section">
                        <h4><i class="fas fa-list"></i> Test Results</h4>
                        <table class="values-table">
                            <thead>
                                <tr>
                                    <th>Parameter</th>
                                    <th>Value</th>
                                    <th>Reference Range</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${values.map(v => `
                                    <tr class="${v.is_abnormal ? 'abnormal' : ''}">
                                        <td>${v.parameter_name}</td>
                                        <td>${v.value} ${v.unit}</td>
                                        <td>${v.reference_min} - ${v.reference_max} ${v.unit}</td>
                                        <td>
                                            <span class="value-status ${getValueStatus(v)}">
                                                <i class="fas fa-${v.is_abnormal ? 'exclamation-circle' : 'check-circle'}"></i>
                                                ${getValueStatusText(v)}
                                            </span>
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                `;
            }

            // Abnormalities
            if (abnormalities.length > 0) {
                html += `
                    <div class="report-detail-section">
                        <h4><i class="fas fa-exclamation-triangle"></i> Abnormal Findings</h4>
                        <ul class="alerts-list">
                            ${abnormalities.map(a => `
                                <li class="alert-item">
                                    <i class="fas fa-arrow-${a.direction === 'high' ? 'up' : 'down'}"></i>
                                    <span><strong>${a.parameter}</strong>: ${a.value} ${a.unit} 
                                    (${a.direction === 'high' ? 'Above' : 'Below'} normal - ${a.severity})</span>
                                </li>
                            `).join('')}
                        </ul>
                    </div>
                `;
            }

            // Recommendations
            if (report.recommendations) {
                html += `
                    <div class="report-detail-section">
                        <h4><i class="fas fa-lightbulb"></i> Recommendations</h4>
                        <div class="recommendations-box">${report.recommendations}</div>
                    </div>
                `;
            }

            // OCR Text (collapsible)
            if (report.ocr_text) {
                html += `
                    <div class="report-detail-section">
                        <h4><i class="fas fa-file-alt"></i> Extracted Text</h4>
                        <details>
                            <summary>View OCR Output</summary>
                            <pre style="font-size: 0.75rem; white-space: pre-wrap; background: var(--gray-50); padding: 1rem; border-radius: var(--radius);">${report.ocr_text}</pre>
                        </details>
                    </div>
                `;
            }

            document.getElementById('reportDetailBody').innerHTML = html;
            document.getElementById('reportDetailModal').classList.add('active');
        }
    } catch (error) {
        showToast('Failed to load report details', 'error');
    }
    hideLoading();
}

function closeReportDetailModal() {
    document.getElementById('reportDetailModal').classList.remove('active');
}

function getValueStatus(v) {
    if (!v.is_abnormal) return 'normal';
    return v.value > v.reference_max ? 'high' : 'low';
}

function getValueStatusText(v) {
    if (!v.is_abnormal) return 'Normal';
    return v.value > v.reference_max ? 'High' : 'Low';
}

// ==================== DIET PLANNER ====================

async function loadDietPlan() {
    if (!state.user) return;

    showLoading();
    try {
        const response = await api.getActiveDietPlan(state.user.id);
        
        if (response.success && response.plan) {
            state.dietPlan = response.plan;
            renderDietPlan(response.plan);
        } else {
            document.getElementById('activePlanName').textContent = 'No active plan';
            document.getElementById('targetCalories').textContent = '-';
            document.getElementById('targetProtein').textContent = '-';
            document.getElementById('targetCarbs').textContent = '-';
            document.getElementById('targetFat').textContent = '-';
            document.getElementById('mealsTimeline').innerHTML = '<div class="no-data">No meal plan available</div>';
        }

        // Load restricted foods
        const restrictedResponse = await api.getRestrictedFoods(state.user.id);
        if (restrictedResponse.success) {
            renderRestrictedFoods(restrictedResponse.restricted_foods);
        }

        // Set current day as active
        const today = new Date().getDay();
        document.querySelectorAll('.day-selector .btn').forEach(btn => {
            btn.classList.toggle('active', parseInt(btn.dataset.day) === today);
        });

    } catch (error) {
        showToast('Failed to load diet plan', 'error');
    }
    hideLoading();
}

function renderDietPlan(plan) {
    document.getElementById('activePlanName').textContent = plan.plan_name;
    document.getElementById('targetCalories').textContent = `${plan.target_calories} kcal`;
    document.getElementById('targetProtein').textContent = plan.target_protein_g ? `${plan.target_protein_g}g` : '-';
    document.getElementById('targetCarbs').textContent = plan.target_carbs_g ? `${plan.target_carbs_g}g` : '-';
    document.getElementById('targetFat').textContent = plan.target_fat_g ? `${plan.target_fat_g}g` : '-';

    if (plan.meals && plan.meals.length > 0) {
        renderMeals(plan.meals);
    } else {
        document.getElementById('mealsTimeline').innerHTML = '<div class="no-data">No meals added yet</div>';
    }
}

function renderMeals(meals) {
    const mealIcons = {
        breakfast: 'sun',
        morning_snack: 'apple-alt',
        lunch: 'utensils',
        afternoon_snack: 'cookie',
        dinner: 'moon',
        evening_snack: 'cookie-bite'
    };

    const container = document.getElementById('mealsTimeline');
    container.innerHTML = meals.map(meal => `
        <div class="meal-item">
            <div class="meal-icon">
                <i class="fas fa-${mealIcons[meal.meal_type] || 'utensils'}"></i>
            </div>
            <div class="meal-content">
                <span class="meal-type">${formatMealType(meal.meal_type)}</span>
                <h4>${meal.meal_name}</h4>
                <p>${meal.description || ''}</p>
                <div class="meal-nutrients">
                    <span><i class="fas fa-fire"></i> ${meal.calories} kcal</span>
                    <span><i class="fas fa-drumstick-bite"></i> ${meal.protein_g}g protein</span>
                    <span><i class="fas fa-bread-slice"></i> ${meal.carbs_g}g carbs</span>
                </div>
            </div>
        </div>
    `).join('');
}

function renderRestrictedFoods(foods) {
    const container = document.getElementById('restrictedFoodsList');
    
    if (!foods || foods.length === 0) {
        container.innerHTML = '<li class="no-data">No restricted foods</li>';
        return;
    }

    container.innerHTML = foods.map(food => `
        <li>
            <div class="restricted-food-info">
                <i class="fas fa-ban" style="color: var(--danger);"></i>
                <div>
                    <strong>${food.food_name}</strong>
                    <small>${food.reason || ''}</small>
                </div>
                <span class="severity-badge ${food.severity}">${food.severity}</span>
            </div>
            <button class="btn btn-icon btn-sm" onclick="removeRestrictedFood(${food.id})">
                <i class="fas fa-times"></i>
            </button>
        </li>
    `).join('');
}

function selectDietDay(day) {
    document.querySelectorAll('.day-selector .btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.day === day);
    });
    
    // Filter meals by day (if needed)
    if (state.dietPlan && state.dietPlan.meals) {
        const filteredMeals = state.dietPlan.meals.filter(m => 
            m.day_of_week === null || m.day_of_week === parseInt(day)
        );
        renderMeals(filteredMeals);
    }
}

function showCreateDietPlanModal() {
    document.getElementById('dietPlanForm').reset();
    document.getElementById('dietPlanModal').classList.add('active');
}

function closeDietPlanModal() {
    document.getElementById('dietPlanModal').classList.remove('active');
}

async function handleDietPlanSubmit(e) {
    e.preventDefault();

    const planData = {
        user_id: state.user.id,
        plan_name: document.getElementById('planName').value,
        target_calories: document.getElementById('planCalories').value,
        target_protein_g: document.getElementById('planProtein').value || null,
        target_carbs_g: document.getElementById('planCarbs').value || null,
        target_fat_g: document.getElementById('planFat').value || null,
        condition_focus: document.getElementById('planCondition').value || null
    };

    showLoading();
    try {
        const response = await api.createDietPlan(planData);
        if (response.success) {
            closeDietPlanModal();
            showToast('Diet plan created!', 'success');
            loadDietPlan();
        }
    } catch (error) {
        showToast(error.message || 'Failed to create diet plan', 'error');
    }
    hideLoading();
}

async function generateMealPlan() {
    const condition = document.getElementById('planCondition').value;
    const calories = document.getElementById('planCalories').value;

    if (!condition || !calories) {
        showToast('Please select a condition and target calories', 'warning');
        return;
    }

    showLoading();
    try {
        const response = await api.generateMealPlan(state.user.id, condition, calories);
        if (response.success) {
            showToast('Meal plan generated! Create the plan to save it.', 'success');
            console.log('Generated plan:', response.meal_plan);
        }
    } catch (error) {
        showToast(error.message || 'Failed to generate meal plan', 'error');
    }
    hideLoading();
}

function showAddRestrictedFoodModal() {
    document.getElementById('restrictedFoodForm').reset();
    document.getElementById('restrictedFoodModal').classList.add('active');
}

function closeRestrictedFoodModal() {
    document.getElementById('restrictedFoodModal').classList.remove('active');
}

async function handleRestrictedFoodSubmit(e) {
    e.preventDefault();

    showLoading();
    try {
        const response = await api.addRestrictedFood(
            state.user.id,
            document.getElementById('restrictedFoodName').value,
            document.getElementById('restrictedFoodReason').value,
            document.getElementById('restrictedFoodSeverity').value
        );

        if (response.success) {
            closeRestrictedFoodModal();
            showToast('Restricted food added!', 'success');
            loadDietPlan();
        }
    } catch (error) {
        showToast(error.message || 'Failed to add restricted food', 'error');
    }
    hideLoading();
}

async function removeRestrictedFood(id) {
    if (!confirm('Remove this restricted food?')) return;

    showLoading();
    try {
        const response = await api.removeRestrictedFood(id, state.user.id);
        if (response.success) {
            showToast('Restricted food removed!', 'success');
            loadDietPlan();
        }
    } catch (error) {
        showToast(error.message || 'Failed to remove', 'error');
    }
    hideLoading();
}

async function searchFoods() {
    const keyword = document.getElementById('foodSearchInput').value;
    if (!keyword) return;

    showLoading();
    try {
        const response = await api.searchFoods(keyword);
        if (response.success) {
            const container = document.getElementById('foodSearchResults');
            
            if (!response.foods || response.foods.length === 0) {
                container.innerHTML = '<div class="no-data">No foods found</div>';
            } else {
                container.innerHTML = response.foods.map(food => `
                    <div class="food-item">
                        <div>
                            <h5>${food.name}</h5>
                            <p>${food.category} - ${food.calories_per_100g} kcal/100g</p>
                        </div>
                    </div>
                `).join('');
            }
        }
    } catch (error) {
        showToast('Search failed', 'error');
    }
    hideLoading();
}

// ==================== PROFILE ====================

async function loadProfile() {
    if (!state.user) return;

    showLoading();
    try {
        const response = await api.getProfile(state.user.id);
        if (response.success && response.user) {
            const user = response.user;
            
            document.getElementById('profileName').textContent = user.full_name;
            document.getElementById('profileEmail').textContent = user.email;
            document.getElementById('profileAvatar').src = 
                `https://ui-avatars.com/api/?name=${encodeURIComponent(user.full_name)}&background=4f46e5&color=fff&size=128`;
            
            document.getElementById('inputFullName').value = user.full_name;
            document.getElementById('inputPhone').value = user.phone || '';
            document.getElementById('inputDob').value = user.date_of_birth || '';
            document.getElementById('inputGender').value = user.gender || '';
            document.getElementById('inputHeight').value = user.height_cm || '';
            document.getElementById('inputWeight').value = user.weight_kg || '';
        }

        // Load user conditions
        const conditionsResponse = await api.getUserConditions(state.user.id);
        if (conditionsResponse.success) {
            renderUserConditions(conditionsResponse.conditions);
        }

    } catch (error) {
        showToast('Failed to load profile', 'error');
    }
    hideLoading();
}

function renderUserConditions(conditions) {
    const container = document.getElementById('userConditionsList');
    
    if (!conditions || conditions.length === 0) {
        container.innerHTML = '<li class="no-data">No health conditions added</li>';
        return;
    }

    container.innerHTML = conditions.map(c => `
        <li>
            <div class="condition-info">
                <h5>${c.name}</h5>
                <p>${c.diagnosed_date ? 'Since ' + formatDate(c.diagnosed_date) : ''}</p>
            </div>
            <button class="btn btn-icon btn-sm btn-danger" onclick="removeCondition(${c.id})">
                <i class="fas fa-times"></i>
            </button>
        </li>
    `).join('');
}

async function handleProfileUpdate(e) {
    e.preventDefault();

    const profileData = {
        user_id: state.user.id,
        full_name: document.getElementById('inputFullName').value,
        phone: document.getElementById('inputPhone').value,
        date_of_birth: document.getElementById('inputDob').value || null,
        gender: document.getElementById('inputGender').value || null,
        height_cm: document.getElementById('inputHeight').value || null,
        weight_kg: document.getElementById('inputWeight').value || null
    };

    showLoading();
    try {
        const response = await api.updateProfile(profileData);
        if (response.success) {
            state.user.full_name = profileData.full_name;
            localStorage.setItem('mediassist_user', JSON.stringify(state.user));
            updateUserDisplay();
            showToast('Profile updated!', 'success');
        }
    } catch (error) {
        showToast(error.message || 'Failed to update profile', 'error');
    }
    hideLoading();
}

function showAddConditionModal() {
    loadConditionsForSelect();
    document.getElementById('conditionForm').reset();
    document.getElementById('conditionModal').classList.add('active');
}

function closeConditionModal() {
    document.getElementById('conditionModal').classList.remove('active');
}

async function loadConditionsForSelect() {
    try {
        const response = await api.getHealthConditions();
        if (response.success) {
            const select = document.getElementById('conditionSelect');
            select.innerHTML = '<option value="">Select...</option>' +
                response.conditions.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
        }
    } catch (error) {
        console.error('Failed to load conditions:', error);
    }
}

async function handleConditionSubmit(e) {
    e.preventDefault();

    showLoading();
    try {
        const response = await api.addUserCondition(
            state.user.id,
            document.getElementById('conditionSelect').value,
            document.getElementById('conditionDate').value || null,
            document.getElementById('conditionNotes').value || null
        );

        if (response.success) {
            closeConditionModal();
            showToast('Health condition added!', 'success');
            loadProfile();
        }
    } catch (error) {
        showToast(error.message || 'Failed to add condition', 'error');
    }
    hideLoading();
}

async function removeCondition(conditionId) {
    if (!confirm('Remove this health condition?')) return;

    showLoading();
    try {
        const response = await api.removeUserCondition(state.user.id, conditionId);
        if (response.success) {
            showToast('Condition removed!', 'success');
            loadProfile();
        }
    } catch (error) {
        showToast(error.message || 'Failed to remove condition', 'error');
    }
    hideLoading();
}

// ==================== UTILITY FUNCTIONS ====================

function showLoading() {
    document.getElementById('loadingOverlay').classList.add('active');
}

function hideLoading() {
    document.getElementById('loadingOverlay').classList.remove('active');
}

function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    const icons = {
        success: 'check-circle',
        error: 'times-circle',
        warning: 'exclamation-circle',
        info: 'info-circle'
    };
    
    toast.innerHTML = `
        <i class="fas fa-${icons[type]}"></i>
        <span>${message}</span>
    `;
    
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideIn 0.3s ease reverse';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

function updateCurrentDate() {
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    const currentDate = document.getElementById('currentDate');
    if (currentDate) currentDate.textContent = new Date().toLocaleDateString('en-US', options);
}

function updateNotificationBadge(count) {
    const badge = document.getElementById('notificationBadge');
    if (!badge) return;
    badge.textContent = count;
    badge.style.display = count > 0 ? 'block' : 'none';
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function formatDateForApi(date) {
    return date.toISOString().split('T')[0];
}

function formatTime(timeStr) {
    if (!timeStr) return '';
    const [hours, minutes] = timeStr.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const hour12 = hour % 12 || 12;
    return `${hour12}:${minutes} ${ampm}`;
}

function formatDoseType(type) {
    const types = {
        tablet: 'Tablet',
        capsule: 'Capsule',
        syrup: 'Syrup',
        injection: 'Injection',
        drops: 'Drops',
        cream: 'Cream',
        inhaler: 'Inhaler',
        other: 'Other'
    };
    return types[type] || type;
}

function formatMealRelation(relation) {
    const relations = {
        anytime: 'Anytime',
        before_meal: 'Before meal',
        after_meal: 'After meal',
        with_meal: 'With meal',
        empty_stomach: 'Empty stomach'
    };
    return relations[relation] || relation;
}

function formatReportType(type) {
    const types = {
        cbc: 'CBC',
        kidney: 'Kidney Function',
        lipid: 'Lipid Profile',
        liver: 'Liver Function',
        diabetes: 'Diabetes',
        thyroid: 'Thyroid',
        other: 'Other'
    };
    return types[type] || type;
}

function formatMealType(type) {
    const types = {
        breakfast: 'Breakfast',
        morning_snack: 'Morning Snack',
        lunch: 'Lunch',
        afternoon_snack: 'Afternoon Snack',
        dinner: 'Dinner',
        evening_snack: 'Evening Snack'
    };
    return types[type] || type;
}

function getStatusIcon(status) {
    const icons = {
        taken: 'check',
        missed: 'times',
        skipped: 'forward',
        pending: 'clock'
    };
    return icons[status] || 'clock';
}

// ==================== EXPOSE FUNCTIONS GLOBALLY ====================
// Required for onclick handlers in HTML

window.showLandingAuth = showLandingAuth;
window.scrollToSection = scrollToSection;
window.toggleMobileMenu = toggleMobileMenu;
window.showAddMedicineModal = showAddMedicineModal;
window.closeMedicineModal = closeMedicineModal;
window.addScheduleRow = addScheduleRow;
window.removeScheduleRow = removeScheduleRow;
window.editMedicine = editMedicine;
window.deleteMedicine = deleteMedicine;
window.markPillTaken = markPillTaken;
window.skipPill = skipPill;
window.showUploadModal = showUploadModal;
window.closeUploadModal = closeUploadModal;
window.showReportDetail = showReportDetail;
window.closeReportDetailModal = closeReportDetailModal;
window.showCreateDietPlanModal = showCreateDietPlanModal;
window.closeDietPlanModal = closeDietPlanModal;
window.generateMealPlan = generateMealPlan;
window.selectDietDay = selectDietDay;
window.showAddRestrictedFoodModal = showAddRestrictedFoodModal;
window.closeRestrictedFoodModal = closeRestrictedFoodModal;
window.removeRestrictedFood = removeRestrictedFood;
window.searchFoods = searchFoods;
window.showAddConditionModal = showAddConditionModal;
window.closeConditionModal = closeConditionModal;
window.removeCondition = removeCondition;
window.hideAuthModal = hideAuthModal;
window.toggleDarkMode = toggleDarkMode;
window.showNotificationPanel = showNotificationPanel;
window.closeNotificationPanel = closeNotificationPanel;
window.enableBrowserNotifications = enableBrowserNotifications;
window.sendTestNotification = sendTestNotification;
window.exportToPDF = exportToPDF;
window.closeInteractionModal = closeInteractionModal;

// ==================== DARK MODE ====================

function toggleDarkMode() {
    state.darkMode = !state.darkMode;
    
    if (state.darkMode) {
        document.documentElement.setAttribute('data-theme', 'dark');
    } else {
        document.documentElement.removeAttribute('data-theme');
    }
    
    localStorage.setItem('mediassist_darkmode', state.darkMode);
    updateThemeIcon();
}

function updateThemeIcon() {
    const icon = document.getElementById('themeIcon');
    const landingIcon = document.getElementById('landingThemeIcon');
    const iconClass = state.darkMode ? 'fas fa-sun' : 'fas fa-moon';
    
    if (icon) {
        icon.className = iconClass;
    }
    if (landingIcon) {
        landingIcon.className = iconClass;
    }
}

async function loadMedicinesForNotifications() {
    if (!state.user) return;
    
    try {
        const response = await api.getMedicines(state.user.id);
        if (response.success) {
            state.medicines = response.medicines;
            console.log('Medicines loaded for notifications:', state.medicines.length);
        }
    } catch (error) {
        console.error('Failed to load medicines for notifications:', error);
    }
}

// ==================== REAL-TIME CLOCK ====================

let clockInterval = null;
let lastNotifiedSchedule = new Set(); // Track which schedules we've already notified

function startLiveClock() {
    // Update immediately
    updateClock();
    
    // Update every second
    clockInterval = setInterval(() => {
        updateClock();
        checkScheduledNotifications();
    }, 1000);
}

function updateClock() {
    const clockElement = document.getElementById('clockTime');
    if (!clockElement) return;
    
    const now = new Date();
    let hours = now.getHours();
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');
    const ampm = hours >= 12 ? 'PM' : 'AM';
    
    // Convert to 12-hour format
    hours = hours % 12;
    hours = hours ? hours : 12; // 0 should be 12
    const hoursStr = String(hours).padStart(2, '0');
    
    clockElement.textContent = `${hoursStr}:${minutes}:${seconds} ${ampm}`;
    
    // Check if there's an upcoming medicine in the next 5 minutes
    checkUpcomingMedicine(now);
}

function checkUpcomingMedicine(now) {
    const clockElement = document.getElementById('liveClock');
    if (!clockElement || !state.medicines) return;
    
    const currentMinutes = now.getHours() * 60 + now.getMinutes();
    let hasUpcoming = false;
    
    state.medicines.forEach(med => {
        if (med.schedules) {
            med.schedules.forEach(schedule => {
                const scheduleTime = schedule.scheduled_time || schedule.time;
                if (scheduleTime) {
                    const [h, m] = scheduleTime.split(':').map(Number);
                    const scheduleMinutes = h * 60 + m;
                    const diff = scheduleMinutes - currentMinutes;
                    
                    // If medicine is due in 0-5 minutes, show alert style
                    if (diff >= 0 && diff <= 5) {
                        hasUpcoming = true;
                    }
                }
            });
        }
    });
    
    if (hasUpcoming) {
        clockElement.classList.add('alert');
    } else {
        clockElement.classList.remove('alert');
    }
}

function checkScheduledNotifications() {
    if (!state.user || !state.medicines || !Notification || Notification.permission !== 'granted') return;
    
    const now = new Date();
    const currentTime = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
    const today = now.toISOString().split('T')[0];
    
    state.medicines.forEach(med => {
        if (med.schedules) {
            med.schedules.forEach(schedule => {
                const scheduleTime = schedule.scheduled_time || schedule.time;
                if (!scheduleTime) return;
                
                // Get just HH:MM from schedule time (might be HH:MM:SS)
                const scheduleHHMM = scheduleTime.substring(0, 5);
                const scheduleKey = `${med.id}-${schedule.id}-${today}-${scheduleHHMM}`;
                
                // Check if it's time and we haven't notified yet
                if (scheduleHHMM === currentTime && !lastNotifiedSchedule.has(scheduleKey)) {
                    // Mark as notified
                    lastNotifiedSchedule.add(scheduleKey);
                    
                    // Send notification
                    new Notification('💊 Time for your medicine!', {
                        body: `Take ${med.name} ${med.dosage || ''} now`,
                        icon: 'https://ui-avatars.com/api/?name=M&background=4f46e5&color=fff',
                        tag: scheduleKey,
                        requireInteraction: true
                    });
                    
                    // Also show in-app toast
                    showToast(`Time to take ${med.name}!`, 'info');
                    
                    // Play sound if available
                    playNotificationSound();
                }
            });
        }
    });
    
    // Clean old entries (keep only today's)
    cleanOldNotifications(today);
}

function cleanOldNotifications(today) {
    const toDelete = [];
    lastNotifiedSchedule.forEach(key => {
        if (!key.includes(today)) {
            toDelete.push(key);
        }
    });
    toDelete.forEach(key => lastNotifiedSchedule.delete(key));
}

function playNotificationSound() {
    try {
        // Create a simple beep sound using Web Audio API
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        oscillator.frequency.value = 800;
        oscillator.type = 'sine';
        gainNode.gain.value = 0.3;
        
        oscillator.start();
        oscillator.stop(audioContext.currentTime + 0.3);
        
        // Second beep
        setTimeout(() => {
            const osc2 = audioContext.createOscillator();
            osc2.connect(gainNode);
            osc2.frequency.value = 1000;
            osc2.type = 'sine';
            osc2.start();
            osc2.stop(audioContext.currentTime + 0.3);
        }, 400);
    } catch (e) {
        console.log('Audio not available');
    }
}

// ==================== NOTIFICATIONS ====================

function showNotificationPanel() {
    document.getElementById('notificationPanel').classList.add('active');
    loadNotifications();
}

function closeNotificationPanel() {
    document.getElementById('notificationPanel').classList.remove('active');
}

function loadNotifications() {
    const container = document.getElementById('notificationList');
    
    // Get pending pills for today as notifications
    const notifications = [];
    
    if (state.alerts && state.alerts.length > 0) {
        state.alerts.forEach(alert => {
            notifications.push({
                type: 'alert',
                title: 'Missed Medication',
                message: `${alert.medicine_name} at ${formatTime(alert.scheduled_time)}`,
                time: 'Today'
            });
        });
    }
    
    // Add reminder notifications
    notifications.push({
        type: 'reminder',
        title: 'Daily Reminder',
        message: 'Don\'t forget to take your medications on time!',
        time: 'Just now'
    });
    
    if (notifications.length === 0) {
        container.innerHTML = '<div class="no-data">No notifications</div>';
        return;
    }
    
    container.innerHTML = notifications.map(n => `
        <div class="notification-item ${n.unread ? 'unread' : ''}">
            <div class="notification-icon ${n.type}">
                <i class="fas fa-${n.type === 'alert' ? 'exclamation-circle' : n.type === 'success' ? 'check-circle' : 'bell'}"></i>
            </div>
            <div class="notification-content">
                <h4>${n.title}</h4>
                <p>${n.message}</p>
                <small>${n.time}</small>
            </div>
        </div>
    `).join('');
}

async function enableBrowserNotifications() {
    if (!('Notification' in window)) {
        showToast('Browser notifications not supported', 'error');
        return;
    }
    
    try {
        const permission = await Notification.requestPermission();
        
        if (permission === 'granted') {
            state.notificationsEnabled = true;
            localStorage.setItem('mediassist_notifications', 'true');
            showToast('Browser notifications enabled!', 'success');
            
            // Show a test notification immediately
            const notification = new Notification('✅ MediAssist+ Notifications Enabled', {
                body: 'You will receive medicine reminders on time!',
                icon: 'https://ui-avatars.com/api/?name=M&background=10b981&color=fff',
                requireInteraction: false
            });
            
            // Load medicines if user is logged in
            if (state.user) {
                await loadMedicinesForNotifications();
            }
        } else if (permission === 'denied') {
            showToast('Notifications blocked. Enable in browser settings.', 'error');
        } else {
            showToast('Notification permission dismissed', 'warning');
        }
    } catch (error) {
        console.error('Notification error:', error);
        showToast('Failed to enable notifications', 'error');
    }
}

function sendTestNotification() {
    if (!('Notification' in window)) {
        showToast('Browser notifications not supported', 'error');
        return;
    }
    
    if (Notification.permission === 'granted') {
        new Notification('🔔 MediAssist+ Test', {
            body: 'Great! Notifications are working. You will be reminded when it\'s time to take your medicine.',
            icon: 'https://ui-avatars.com/api/?name=M&background=4f46e5&color=fff',
            tag: 'test-notification'
        });
        showToast('Test notification sent!', 'success');
    } else if (Notification.permission === 'denied') {
        showToast('Notifications are blocked. Please enable them in your browser settings.', 'error');
    } else {
        // Permission not yet requested
        enableBrowserNotifications();
    }
}

function scheduleMedicineReminders() {
    // Real-time clock now handles notifications via checkScheduledNotifications()
    // This function is kept for compatibility but the clock does the work
    console.log('Medicine reminders are managed by real-time clock system');
}

function checkMedicineReminders() {
    // Deprecated - now handled by checkScheduledNotifications() in the clock
    // Keeping for backward compatibility
    checkScheduledNotifications();
}

// ==================== WEEKLY CHART ====================

async function loadWeeklyChart() {
    if (!state.user) return;
    
    const container = document.getElementById('weeklyChart');
    if (!container) return;
    
    const today = new Date();
    const weekData = [];
    
    // Get last 7 days
    for (let i = 6; i >= 0; i--) {
        const date = new Date(today);
        date.setDate(date.getDate() - i);
        weekData.push({
            date: date,
            dayName: date.toLocaleDateString('en-US', { weekday: 'short' }),
            taken: 0,
            missed: 0,
            total: 0
        });
    }
    
    try {
        const month = today.getMonth() + 1;
        const year = today.getFullYear();
        const response = await api.getMonthlyAnalytics(state.user.id, month, year);
        
        if (response.success && response.analytics) {
            response.analytics.forEach(day => {
                const dayDate = new Date(day.date);
                const weekIndex = weekData.findIndex(w => 
                    w.date.toDateString() === dayDate.toDateString()
                );
                
                if (weekIndex !== -1) {
                    weekData[weekIndex].taken = day.taken || 0;
                    weekData[weekIndex].missed = day.missed || 0;
                    weekData[weekIndex].total = day.total || 0;
                }
            });
        }
    } catch (error) {
        console.error('Failed to load weekly data:', error);
    }
    
    // Find max for scaling
    const maxValue = Math.max(...weekData.map(d => d.total), 1);
    
    container.innerHTML = weekData.map(day => {
        const takenHeight = day.total > 0 ? (day.taken / maxValue) * 100 : 0;
        const missedHeight = day.total > 0 ? (day.missed / maxValue) * 100 : 0;
        
        return `
            <div class="chart-day">
                <div class="chart-bars">
                    ${day.taken > 0 ? `<div class="chart-bar taken" style="height: ${takenHeight}%">
                        <span class="chart-bar-label">${day.taken}</span>
                    </div>` : ''}
                    ${day.missed > 0 ? `<div class="chart-bar missed" style="height: ${missedHeight}%">
                        <span class="chart-bar-label">${day.missed}</span>
                    </div>` : ''}
                    ${day.total === 0 ? '<div class="chart-bar" style="height: 5%; background: var(--gray-200);"></div>' : ''}
                </div>
                <span class="chart-day-label">${day.dayName}</span>
            </div>
        `;
    }).join('');
}

// ==================== EXPORT TO PDF ====================

function exportToPDF() {
    showLoading();
    
    // Create a simple PDF-like content
    const reportContent = generateHealthReport();
    
    // Create a new window for printing
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>MediAssist+ Health Report</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 40px; max-width: 800px; margin: 0 auto; }
                h1 { color: #4f46e5; border-bottom: 2px solid #4f46e5; padding-bottom: 10px; }
                h2 { color: #374151; margin-top: 30px; }
                .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
                .logo { font-size: 24px; font-weight: bold; color: #4f46e5; }
                .date { color: #6b7280; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                th, td { border: 1px solid #e5e7eb; padding: 12px; text-align: left; }
                th { background: #f3f4f6; }
                .stat-box { display: inline-block; padding: 15px 25px; margin: 10px; background: #f3f4f6; border-radius: 8px; text-align: center; }
                .stat-value { font-size: 24px; font-weight: bold; color: #4f46e5; }
                .stat-label { font-size: 12px; color: #6b7280; }
                .success { color: #10b981; }
                .danger { color: #ef4444; }
                .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 12px; }
                @media print { body { print-color-adjust: exact; -webkit-print-color-adjust: exact; } }
            </style>
        </head>
        <body>
            ${reportContent}
            <div class="footer">
                Generated by MediAssist+ on ${new Date().toLocaleDateString()}
            </div>
            <script>window.onload = function() { window.print(); }</script>
        </body>
        </html>
    `);
    printWindow.document.close();
    
    hideLoading();
    showToast('Generating PDF report...', 'info');
}

function generateHealthReport() {
    const userName = state.user?.full_name || 'User';
    const today = new Date().toLocaleDateString('en-US', { 
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' 
    });
    
    let medicinesHtml = '<p>No medicines recorded.</p>';
    if (state.medicines && state.medicines.length > 0) {
        medicinesHtml = `
            <table>
                <thead>
                    <tr>
                        <th>Medicine</th>
                        <th>Dosage</th>
                        <th>Schedule</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    ${state.medicines.map(med => `
                        <tr>
                            <td>${med.name}</td>
                            <td>${med.dosage || '-'} ${formatDoseType(med.dose_type)}</td>
                            <td>${med.schedules ? med.schedules.map(s => formatTime(s.scheduled_time)).join(', ') : '-'}</td>
                            <td>${med.is_active ? '<span class="success">Active</span>' : '<span class="danger">Inactive</span>'}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    }
    
    return `
        <div class="header">
            <div class="logo">♥ MediAssist+</div>
            <div class="date">${today}</div>
        </div>
        
        <h1>Health Report for ${userName}</h1>
        
        <h2>📊 Weekly Summary</h2>
        <div style="text-align: center;">
            <div class="stat-box">
                <div class="stat-value">${document.getElementById('totalPills')?.textContent || '0'}</div>
                <div class="stat-label">Total Pills Today</div>
            </div>
            <div class="stat-box">
                <div class="stat-value success">${document.getElementById('takenPills')?.textContent || '0'}</div>
                <div class="stat-label">Taken</div>
            </div>
            <div class="stat-box">
                <div class="stat-value danger">${document.getElementById('missedPills')?.textContent || '0'}</div>
                <div class="stat-label">Missed</div>
            </div>
            <div class="stat-box">
                <div class="stat-value">${document.getElementById('adherencePercent')?.textContent || '0%'}</div>
                <div class="stat-label">Adherence Rate</div>
            </div>
        </div>
        
        <h2>💊 Current Medications</h2>
        ${medicinesHtml}
        
        <h2>📋 Recommendations</h2>
        <ul>
            <li>Take medications at the same time every day for better adherence</li>
            <li>Set reminders on your phone as a backup</li>
            <li>Keep a 7-day pill organizer to track doses</li>
            <li>Consult your doctor if you experience any side effects</li>
        </ul>
    `;
}

// ==================== DRUG INTERACTIONS ====================

function checkDrugInteractions(newMedicineName) {
    if (!state.medicines || state.medicines.length === 0) return [];
    
    const newDrug = newMedicineName.toLowerCase();
    const interactions = [];
    
    // Check against existing medicines
    state.medicines.forEach(med => {
        const existingDrug = med.name.toLowerCase();
        
        // Check if new drug has interactions with existing
        if (drugInteractions[newDrug]) {
            drugInteractions[newDrug].forEach(interactsWith => {
                if (existingDrug.includes(interactsWith) || interactsWith.includes(existingDrug)) {
                    interactions.push({
                        drug1: newMedicineName,
                        drug2: med.name,
                        severity: 'moderate',
                        description: `${newMedicineName} may interact with ${med.name}. Consult your doctor or pharmacist.`
                    });
                }
            });
        }
        
        // Check reverse
        if (drugInteractions[existingDrug]) {
            drugInteractions[existingDrug].forEach(interactsWith => {
                if (newDrug.includes(interactsWith) || interactsWith.includes(newDrug)) {
                    const alreadyAdded = interactions.some(i => 
                        (i.drug1 === med.name && i.drug2 === newMedicineName) ||
                        (i.drug1 === newMedicineName && i.drug2 === med.name)
                    );
                    if (!alreadyAdded) {
                        interactions.push({
                            drug1: med.name,
                            drug2: newMedicineName,
                            severity: 'moderate',
                            description: `${med.name} may interact with ${newMedicineName}. Consult your doctor or pharmacist.`
                        });
                    }
                }
            });
        }
    });
    
    return interactions;
}

function showDrugInteractionWarning(interactions) {
    if (!interactions || interactions.length === 0) return;
    
    const container = document.getElementById('interactionBody');
    container.innerHTML = interactions.map(i => `
        <div class="interaction-warning">
            <h4>
                <i class="fas fa-exclamation-triangle"></i>
                ${i.drug1} + ${i.drug2}
                <span class="interaction-severity ${i.severity}">${i.severity}</span>
            </h4>
            <p>${i.description}</p>
        </div>
    `).join('') + `
        <p style="margin-top: 1rem; color: var(--text-secondary); font-size: 0.9rem;">
            <i class="fas fa-info-circle"></i> 
            This is an automated warning. Always consult your healthcare provider before making changes to your medication regimen.
        </p>
    `;
    
    document.getElementById('interactionModal').classList.add('active');
}

function closeInteractionModal() {
    document.getElementById('interactionModal').classList.remove('active');
}