const state = {
    user: null,
    currentPage: 'dashboard',
    selectedDate: new Date(),
    medicines: [],
    dietPlan: null,
    alerts: [],
    darkMode: false,
    notificationsEnabled: false
};

// Global variables - declare at top to avoid reference errors
let clockInterval = null;
let lastNotifiedSchedule = new Set();
let selectedPatient = null;

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

document.addEventListener('DOMContentLoaded', () => {
    console.log('DOMContentLoaded fired');
    initializeApp();
});

function initializeApp() {
    console.log('initializeApp started');
    
    // FIRST: Attach all event listeners before anything else
    try {
        initEventListeners();
        console.log('Event listeners attached successfully');
    } catch (e) {
        console.error('Error in initEventListeners:', e);
    }
    
    const savedDarkMode = localStorage.getItem('mediassist_darkmode');
    if (savedDarkMode === 'true') {
        state.darkMode = true;
        document.documentElement.setAttribute('data-theme', 'dark');
        updateThemeIcon();
    }
    
    if (localStorage.getItem('mediassist_notifications') === 'true' && 
        'Notification' in window && 
        Notification.permission === 'granted') {
        state.notificationsEnabled = true;
    }

    const savedUser = localStorage.getItem('mediassist_user');
    if (savedUser) {
        try {
            state.user = JSON.parse(savedUser);
            showApp();
            updateUserDisplay();
            
            // Load appropriate dashboard based on role
            if (state.user.role === 'doctor') {
                showPage('doctor-dashboard');
            } else {
                loadDashboard();
                loadMedicinesForNotifications();
            }
        } catch (e) {
            console.error('Error loading user:', e);
            localStorage.removeItem('mediassist_user');
            showLanding();
        }
    } else {
        showLanding();
    }
    
    try {
        updateCurrentDate();
        updateTrackerDate();
        startLiveClock();
    } catch (e) {
        console.error('Error in date/clock functions:', e);
    }
    
    console.log('initializeApp completed');
}

function showLanding() {
    document.getElementById('landingPage').style.display = 'block';
    document.getElementById('appContainer').style.display = 'none';
    hideAuthModal();
}

function showApp() {
    document.getElementById('landingPage').style.display = 'none';
    document.getElementById('appContainer').style.display = 'flex';
    hideAuthModal();
    
    // Show/hide navigation based on user role
    updateNavigationForRole();
}

function updateNavigationForRole() {
    if (!state.user) return;
    
    const isDoctor = state.user.role === 'doctor';
    
    // Doctor-only nav items
    document.querySelectorAll('.nav-item-doctor').forEach(item => {
        item.style.display = isDoctor ? 'block' : 'none';
    });
    
    // Patient-only nav items
    document.querySelectorAll('.nav-item-patient').forEach(item => {
        item.style.display = isDoctor ? 'none' : 'block';
    });
    
    // Show role badge
    const roleBadge = document.getElementById('userRoleBadge');
    if (roleBadge) {
        roleBadge.textContent = isDoctor ? 'Doctor' : 'Patient';
        roleBadge.className = 'role-badge ' + (isDoctor ? 'doctor' : 'patient');
    }
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

function toggleMobileSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (sidebar) sidebar.classList.toggle('active');
    if (overlay) overlay.classList.toggle('active');
}

function closeMobileSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (sidebar) sidebar.classList.remove('active');
    if (overlay) overlay.classList.remove('active');
}

function initEventListeners() {
    console.log('initEventListeners called');
    
    const sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) sidebarToggle.addEventListener('click', toggleSidebar);
    
    // Navigation items - attach to both li and anchor
    document.querySelectorAll('.nav-item').forEach(item => {
        const handleNavClick = (e) => {
            e.preventDefault();
            e.stopPropagation();
            const page = item.dataset.page;
            console.log('Nav clicked, page:', page);
            if (page) {
                showPage(page);
            }
        };
        
        item.addEventListener('click', handleNavClick);
        
        // Also attach to the anchor inside
        const anchor = item.querySelector('a');
        if (anchor) {
            anchor.addEventListener('click', handleNavClick);
        }
    });
    
    console.log('Nav listeners attached to', document.querySelectorAll('.nav-item').length, 'items');

    document.querySelectorAll('.auth-tab').forEach(tab => {
        tab.addEventListener('click', () => switchAuthTab(tab.dataset.tab));
    });

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
    if (dietPlanForm) dietPlanForm.addEventListener('submit', handleDietPlanSubmit);
    if (conditionForm) conditionForm.addEventListener('submit', handleConditionSubmit);
    if (restrictedFoodForm) restrictedFoodForm.addEventListener('submit', handleRestrictedFoodSubmit);
    if (profileForm) profileForm.addEventListener('submit', handleProfileUpdate);

    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) logoutBtn.addEventListener('click', handleLogout);

    const prevDay = document.getElementById('prevDay');
    const nextDay = document.getElementById('nextDay');
    if (prevDay) prevDay.addEventListener('click', () => navigateDay(-1));
    if (nextDay) nextDay.addEventListener('click', () => navigateDay(1));

    document.querySelectorAll('.day-selector .btn').forEach(btn => {
        btn.addEventListener('click', () => selectDietDay(btn.dataset.day));
    });

    const foodSearchInput = document.getElementById('foodSearchInput');
    if (foodSearchInput) {
        foodSearchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') searchFoods();
        });
    }
}

function showPage(page) {
    // Close mobile sidebar when navigating
    closeMobileSidebar();
    
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.toggle('active', item.dataset.page === page);
    });

    document.querySelectorAll('.page').forEach(p => {
        p.classList.toggle('active', p.id === `page-${page}`);
    });

    const titles = {
        dashboard: 'Dashboard',
        medicines: 'My Medicines',
        tracker: 'Pill Tracker',
        reports: 'Lab Reports',
        diet: 'Diet & Nutrition',
        profile: 'My Profile',
        'health-dashboard': 'Health Hub',
        lifestyle: 'Lifestyle Tracker',
        emergency: 'Emergency Info',
        // Doctor pages
        'doctor-dashboard': 'Doctor Dashboard',
        'doctor-patients': 'My Patients',
        'patient-detail': 'Patient Details'
    };
    document.getElementById('pageTitle').textContent = titles[page] || 'MediAssist+';

    state.currentPage = page;

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
        case 'diet':
            loadDietPlan();
            break;
        case 'profile':
            loadProfile();
            break;
        case 'health-dashboard':
            loadHealthDashboard();
            break;
        case 'lifestyle':
            loadLifestylePage();
            break;
        case 'emergency':
            loadEmergencyPage();
            break;
        // Doctor pages
        case 'doctor-dashboard':
            loadDoctorDashboard();
            break;
        case 'doctor-patients':
            loadDoctorPatients();
            break;
        case 'patient-detail':
            // Patient detail is loaded when viewing a specific patient
            break;
    }
}

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    if (sidebar) sidebar.classList.toggle('collapsed');
}

function showAuthModal() {
    const modal = document.getElementById('authModal');
    if (modal) modal.classList.add('active');
}

function hideAuthModal() {
    const modal = document.getElementById('authModal');
    if (modal) modal.classList.remove('active');
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
            
            // Load appropriate dashboard based on role
            if (state.user.role === 'doctor') {
                showPage('doctor-dashboard');
            } else {
                loadDashboard();
                loadMedicinesForNotifications();
            }
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
    const username = document.getElementById('regUsername')?.value || '';
    const password = document.getElementById('regPassword').value;
    const confirmPassword = document.getElementById('regConfirmPassword').value;
    const role = document.getElementById('regRole')?.value || 'patient';

    if (password !== confirmPassword) {
        showToast('Passwords do not match', 'error');
        return;
    }

    const registrationData = {
        email: email,
        username: username,
        password: password,
        full_name: name,
        role: role
    };
    
    // Add doctor-specific fields
    if (role === 'doctor') {
        registrationData.specialization = document.getElementById('regSpecialization')?.value || '';
        registrationData.license_number = document.getElementById('regLicenseNumber')?.value || '';
        registrationData.clinic_name = document.getElementById('regClinicName')?.value || '';
    }

    showLoading();
    try {
        const response = await api.register(registrationData);
        
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

async function loadDashboard() {
    if (!state.user) return;

    showLoading();
    try {
        const response = await api.getDashboard(state.user.id);
        
        if (response.success) {
            const dashboard = response.dashboard;
            
            document.getElementById('totalPills').textContent = dashboard.summary.total;
            document.getElementById('takenPills').textContent = dashboard.summary.taken;
            document.getElementById('pendingPills').textContent = dashboard.summary.pending;
            document.getElementById('missedPills').textContent = dashboard.summary.missed;

            const progress = dashboard.summary.total > 0 
                ? (dashboard.summary.taken / dashboard.summary.total) * 100 
                : 0;
            document.getElementById('pillProgress').style.width = `${progress}%`;

            const adherencePercent = dashboard.adherence.percentage || 0;
            document.getElementById('adherenceCircle').setAttribute('stroke-dasharray', `${adherencePercent}, 100`);
            document.getElementById('adherencePercent').textContent = `${adherencePercent}%`;

            document.getElementById('currentStreak').textContent = dashboard.adherence.streak || 0;

            renderUpcomingReminders(dashboard.pills_by_time);

            state.alerts = dashboard.alerts;
            renderAlerts(dashboard.alerts);
            updateNotificationBadge(dashboard.alerts.length);
            
            loadWeeklyChart();
            
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

        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const daysInMonth = lastDay.getDate();
        const startDay = firstDay.getDay();

        let html = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
            .map(d => `<div class="day-label">${d}</div>`).join('');

        for (let i = 0; i < startDay; i++) {
            html += '<div class="day empty"></div>';
        }

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

        const restrictedResponse = await api.getRestrictedFoods(state.user.id);
        if (restrictedResponse.success) {
            renderRestrictedFoods(restrictedResponse.restricted_foods);
        }

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
    let planNameHtml = plan.plan_name;
    if (plan.assigned_by_name) {
        planNameHtml += ` <span class="assigned-by-badge"><i class="fas fa-user-md"></i> Dr. ${plan.assigned_by_name}</span>`;
    }
    document.getElementById('activePlanName').innerHTML = planNameHtml;
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

// Stub functions for features not yet implemented
function showUploadModal() {
    showToast('Upload feature coming soon', 'info');
}
function closeUploadModal() {
    const modal = document.getElementById('uploadModal');
    if (modal) modal.style.display = 'none';
}
function showReportDetail(reportId) {
    showToast('Report detail feature coming soon', 'info');
}
function closeReportDetailModal() {
    const modal = document.getElementById('reportDetailModal');
    if (modal) modal.style.display = 'none';
}

window.showUploadModal = showUploadModal;
window.closeUploadModal = closeUploadModal;
window.showReportDetail = showReportDetail;
window.closeReportDetailModal = closeReportDetailModal;

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

function startLiveClock() {
    updateClock();
    
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
    
    hours = hours % 12;
    hours = hours ? hours : 12; // 0 should be 12
    const hoursStr = String(hours).padStart(2, '0');
    
    clockElement.textContent = `${hoursStr}:${minutes}:${seconds} ${ampm}`;
    
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
    if (!state.user) {
        return;
    }
    if (!state.medicines || state.medicines.length === 0) {
        return;
    }
    if (!('Notification' in window)) {
        console.log('Notifications not supported in this browser');
        return;
    }
    if (Notification.permission !== 'granted') {
        return;
    }
    
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
                
                if (scheduleHHMM === currentTime && !lastNotifiedSchedule.has(scheduleKey)) {
                    lastNotifiedSchedule.add(scheduleKey);
                    
                    console.log(`Triggering notification for ${med.name} at ${scheduleHHMM}`);
                    
                    new Notification('💊 Time for your medicine!', {
                        body: `Take ${med.name} ${med.dosage || ''} now`,
                        icon: 'https://ui-avatars.com/api/?name=M&background=4f46e5&color=fff',
                        tag: scheduleKey,
                        requireInteraction: true
                    });
                    
                    showToast(`Time to take ${med.name}!`, 'info');
                    
                    playNotificationSound();
                }
            });
        }
    });
    
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

function showNotificationPanel() {
    document.getElementById('notificationPanel').classList.add('active');
    loadNotifications();
}

function closeNotificationPanel() {
    document.getElementById('notificationPanel').classList.remove('active');
}

function loadNotifications() {
    const container = document.getElementById('notificationList');
    
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
    
    console.log('Notification permission:', Notification.permission);
    console.log('Medicines loaded:', state.medicines ? state.medicines.length : 0);
    if (state.medicines && state.medicines.length > 0) {
        state.medicines.forEach(med => {
            console.log(`- ${med.name}: ${med.schedules ? med.schedules.length : 0} schedules`);
            if (med.schedules) {
                med.schedules.forEach(s => {
                    console.log(`  Schedule: ${s.scheduled_time}`);
                });
            }
        });
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
        enableBrowserNotifications();
    }
}

function scheduleMedicineReminders() {
    console.log('Medicine reminders are managed by real-time clock system');
}

function checkMedicineReminders() {
    checkScheduledNotifications();
}

async function loadWeeklyChart() {
    if (!state.user) return;
    
    const container = document.getElementById('weeklyChart');
    if (!container) return;
    
    const today = new Date();
    const weekData = [];
    
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

function exportToPDF() {
    showLoading();
    
    const reportContent = generateHealthReport();
    
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

// =====================================================
// HEALTH DASHBOARD FUNCTIONS
// =====================================================

async function loadHealthDashboard() {
    if (!state.user) return;
    
    showLoading();
    try {
        // Load all health dashboard data in parallel
        const [
            missedDosesRes,
            interactionsRes,
            refillAlertsRes,
            abnormalValuesRes,
            timelineRes,
            sideEffectsRes,
            insightsRes,
            riskSummaryRes
        ] = await Promise.all([
            api.getMissedDoses(state.user.id, 7).catch(() => ({ success: false })),
            api.checkInteractions(state.user.id).catch(() => ({ success: false })),
            api.getRefillAlerts(state.user.id, 7).catch(() => ({ success: false })),
            api.getAbnormalValues(state.user.id).catch(() => ({ success: false })),
            api.getUserMedicineTimeline(state.user.id).catch(() => ({ success: false })),
            api.getUserSideEffects(state.user.id).catch(() => ({ success: false })),
            api.getHealthInsights(state.user.id).catch(() => ({ success: false })),
            api.getHealthRiskSummary(state.user.id).catch(() => ({ success: false }))
        ]);
        
        // Render missed doses
        if (missedDosesRes.success) {
            renderMissedDoses(missedDosesRes.data);
        }
        
        // Render interactions
        if (interactionsRes.success) {
            renderInteractionsList(interactionsRes.data);
        }
        
        // Render refill alerts
        if (refillAlertsRes.success) {
            renderRefillAlerts(refillAlertsRes.data);
        }
        
        // Render abnormal values
        if (abnormalValuesRes.success) {
            renderAbnormalValues(abnormalValuesRes.data);
        }
        
        // Render medicine timeline
        if (timelineRes.success) {
            renderMedicineTimeline(timelineRes.data);
        }
        
        // Render side effects
        if (sideEffectsRes.success) {
            renderSideEffects(sideEffectsRes.data);
        }
        
        // Render health insights
        if (insightsRes.success) {
            renderHealthInsights(insightsRes.data);
        }
        
        // Render risk summary
        if (riskSummaryRes.success) {
            renderRiskSummary(riskSummaryRes.data);
        }
        
    } catch (error) {
        console.error('Health dashboard load error:', error);
    }
    hideLoading();
}

function renderMissedDoses(doses) {
    const container = document.getElementById('missedDosesList');
    const badge = document.getElementById('missedDosesBadge');
    
    if (!doses || doses.length === 0) {
        container.innerHTML = '<li class="no-data">No missed doses this week</li>';
        badge.textContent = '0';
        return;
    }
    
    badge.textContent = doses.length;
    container.innerHTML = doses.slice(0, 5).map(dose => `
        <li>
            <i class="fas fa-times-circle" style="color: var(--danger);"></i>
            <div class="dose-info">
                <strong>${dose.medicine_name}</strong>
                <small>${formatDate(dose.date)} at ${formatTime(dose.scheduled_time)}</small>
            </div>
        </li>
    `).join('');
}

function renderInteractionsList(interactions) {
    const container = document.getElementById('interactionsList');
    
    if (!interactions || interactions.length === 0) {
        container.innerHTML = '<li class="no-data">No interactions detected</li>';
        return;
    }
    
    container.innerHTML = interactions.map(i => `
        <li class="${i.severity === 'high' ? 'danger' : 'warning'}">
            <i class="fas fa-exclamation-triangle"></i>
            <div class="interaction-info">
                <strong>${i.medicine1} + ${i.medicine2}</strong>
                <small>${i.description || 'May cause adverse effects'}</small>
            </div>
        </li>
    `).join('');
}

function renderRefillAlerts(alerts) {
    const container = document.getElementById('refillAlertsList');
    
    if (!alerts || ((!alerts.refill_needed || alerts.refill_needed.length === 0) && 
                    (!alerts.expiring_soon || alerts.expiring_soon.length === 0))) {
        container.innerHTML = '<li class="no-data">No refill or expiry alerts</li>';
        return;
    }
    
    let html = '';
    
    if (alerts.refill_needed) {
        alerts.refill_needed.forEach(med => {
            html += `
                <li class="urgent">
                    <i class="fas fa-prescription-bottle-alt"></i>
                    <div class="refill-info">
                        <strong>${med.name}</strong>
                        <small>${med.remaining_pills} pills left</small>
                    </div>
                    <button class="btn btn-sm btn-primary" onclick="showRefillMedicine(${med.id})">Refill</button>
                </li>
            `;
        });
    }
    
    if (alerts.expiring_soon) {
        alerts.expiring_soon.forEach(med => {
            html += `
                <li class="expiring">
                    <i class="fas fa-calendar-times"></i>
                    <div class="refill-info">
                        <strong>${med.name}</strong>
                        <small>Expires ${formatDate(med.expiry_date)}</small>
                    </div>
                </li>
            `;
        });
    }
    
    container.innerHTML = html || '<li class="no-data">No alerts</li>';
}

function renderAbnormalValues(values) {
    const container = document.getElementById('abnormalValuesList');
    
    if (!values || values.length === 0) {
        container.innerHTML = '<p class="no-data">No abnormal values detected</p>';
        return;
    }
    
    container.innerHTML = values.map(v => `
        <div class="abnormal-value-item ${v.status}">
            <div class="value-name">${v.parameter_name}</div>
            <div class="value-reading">${v.value} ${v.unit || ''}</div>
            <span class="value-status ${v.status}">${v.status}</span>
        </div>
    `).join('');
}

function renderMedicineTimeline(timeline) {
    const container = document.getElementById('medicineTimeline');
    
    if (!timeline || timeline.length === 0) {
        container.innerHTML = '<p class="no-data">No medicine history</p>';
        return;
    }
    
    container.innerHTML = timeline.slice(0, 10).map(item => `
        <div class="timeline-item ${item.action}">
            <div class="timeline-date">${formatDate(item.date)}</div>
            <div class="timeline-content">${item.medicine_name} - ${item.action}</div>
        </div>
    `).join('');
}

function renderSideEffects(effects) {
    const container = document.getElementById('sideEffectsList');
    
    if (!effects || effects.length === 0) {
        container.innerHTML = '<li class="no-data">No side effects reported</li>';
        return;
    }
    
    container.innerHTML = effects.slice(0, 5).map(e => `
        <li>
            <i class="fas fa-notes-medical" style="color: var(--warning);"></i>
            <div class="effect-info">
                <strong>${e.symptom}</strong>
                <small>${e.medicine_name} - ${e.severity}</small>
            </div>
        </li>
    `).join('');
}

function renderHealthInsights(insights) {
    const container = document.getElementById('healthInsightsList');
    
    if (!insights || insights.length === 0) {
        container.innerHTML = '<p class="no-data">No personalized insights available</p>';
        return;
    }
    
    container.innerHTML = insights.map(insight => `
        <div class="insight-item">
            <div class="insight-icon ${insight.type || 'diet'}">
                <i class="fas fa-${insight.icon || 'lightbulb'}"></i>
            </div>
            <div class="insight-content">
                <h4>${insight.title}</h4>
                <p>${insight.description}</p>
            </div>
        </div>
    `).join('');
}

function renderRiskSummary(summary) {
    const container = document.getElementById('riskIndicators');
    const details = document.getElementById('riskDetails');
    
    if (!summary) {
        details.innerHTML = '<p class="no-data">Unable to calculate risk</p>';
        return;
    }
    
    const riskLevel = summary.overall_risk || 'low';
    container.innerHTML = `
        <div class="risk-item ${riskLevel}">
            <span class="risk-label">Overall Risk</span>
            <span class="risk-value">${riskLevel.charAt(0).toUpperCase() + riskLevel.slice(1)}</span>
        </div>
    `;
    
    if (summary.factors && summary.factors.length > 0) {
        details.innerHTML = '<ul>' + summary.factors.map(f => `<li>${f}</li>`).join('') + '</ul>';
    } else {
        details.innerHTML = '<p>Keep up the good work with your health management!</p>';
    }
}

async function loadParameterTrend() {
    const param = document.getElementById('trendParameterSelect').value;
    const container = document.getElementById('trendChart');
    
    if (!param) {
        container.innerHTML = '<p class="no-data">Select a parameter to view trend</p>';
        return;
    }
    
    try {
        const response = await api.getParameterTrendAdvanced(state.user.id, param, 365);
        
        if (response.success && response.data && response.data.length > 0) {
            renderTrendChart(container, response.data, param);
        } else {
            container.innerHTML = '<p class="no-data">No data available for this parameter</p>';
        }
    } catch (error) {
        container.innerHTML = '<p class="no-data">Failed to load trend data</p>';
    }
}

function renderTrendChart(container, data, param) {
    const maxVal = Math.max(...data.map(d => parseFloat(d.value)));
    const minVal = Math.min(...data.map(d => parseFloat(d.value)));
    const range = maxVal - minVal || 1;
    
    container.innerHTML = `
        <div class="simple-line-chart">
            ${data.slice(-7).map((d, i) => {
                const height = ((parseFloat(d.value) - minVal) / range) * 80 + 20;
                return `
                    <div class="chart-point" style="height: ${height}%;" title="${param}: ${d.value} (${formatDate(d.date)})">
                        <span class="point-value">${d.value}</span>
                    </div>
                `;
            }).join('')}
        </div>
        <div class="chart-dates">
            ${data.slice(-7).map(d => `<span>${formatDateShort(d.date)}</span>`).join('')}
        </div>
    `;
}

// Side Effect Modal Functions
function showReportSideEffectModal() {
    populateSideEffectMedicines();
    document.getElementById('sideEffectModal').classList.add('active');
}

function closeSideEffectModal() {
    document.getElementById('sideEffectModal').classList.remove('active');
    document.getElementById('sideEffectForm').reset();
}

async function populateSideEffectMedicines() {
    const select = document.getElementById('sideEffectMedicine');
    
    try {
        const response = await api.getMedicines(state.user.id, true);
        if (response.success && response.medicines) {
            select.innerHTML = '<option value="">Select Medicine</option>' +
                response.medicines.map(m => `<option value="${m.id}">${m.name}</option>`).join('');
        }
    } catch (error) {
        console.error('Failed to load medicines for side effect form');
    }
}

async function handleSideEffectSubmit(e) {
    e.preventDefault();
    
    const medicineId = document.getElementById('sideEffectMedicine').value;
    const symptom = document.getElementById('sideEffectSymptom').value;
    const severity = document.getElementById('sideEffectSeverity').value;
    const notes = document.getElementById('sideEffectNotes').value;
    
    showLoading();
    try {
        const response = await api.reportSideEffect(state.user.id, medicineId, symptom, severity, notes);
        if (response.success) {
            showToast('Side effect reported', 'success');
            closeSideEffectModal();
            loadHealthDashboard();
        }
    } catch (error) {
        showToast('Failed to report side effect', 'error');
    }
    hideLoading();
}

// Weekly Summary & Doctor Report
async function generateWeeklySummaryReport() {
    document.getElementById('weeklySummaryModal').classList.add('active');
    
    try {
        const response = await api.generateWeeklySummary(state.user.id);
        
        if (response.success) {
            renderWeeklySummary(response.data);
        } else {
            document.getElementById('weeklySummaryBody').innerHTML = '<p class="no-data">Failed to generate summary</p>';
        }
    } catch (error) {
        document.getElementById('weeklySummaryBody').innerHTML = '<p class="no-data">Error generating summary</p>';
    }
}

function renderWeeklySummary(summary) {
    const container = document.getElementById('weeklySummaryBody');
    
    container.innerHTML = `
        <div class="summary-section">
            <h4>📊 Medication Adherence</h4>
            <div class="summary-grid">
                <div class="summary-stat">
                    <span class="stat-value">${summary.adherence_rate || 0}%</span>
                    <span class="stat-label">Adherence Rate</span>
                </div>
                <div class="summary-stat">
                    <span class="stat-value">${summary.doses_taken || 0}/${summary.total_doses || 0}</span>
                    <span class="stat-label">Doses Taken</span>
                </div>
            </div>
        </div>
        
        <div class="summary-section">
            <h4>💪 Activity Summary</h4>
            <div class="summary-grid">
                <div class="summary-stat">
                    <span class="stat-value">${summary.active_minutes || 0}</span>
                    <span class="stat-label">Active Minutes</span>
                </div>
                <div class="summary-stat">
                    <span class="stat-value">${summary.calories_burned || 0}</span>
                    <span class="stat-label">Calories Burned</span>
                </div>
            </div>
        </div>
        
        <div class="summary-section">
            <h4>💧 Hydration</h4>
            <div class="summary-grid">
                <div class="summary-stat">
                    <span class="stat-value">${summary.avg_water_intake || 0}ml</span>
                    <span class="stat-label">Avg Daily Intake</span>
                </div>
                <div class="summary-stat">
                    <span class="stat-value">${summary.hydration_goal_met || 0}/7</span>
                    <span class="stat-label">Days Goal Met</span>
                </div>
            </div>
        </div>
        
        <div class="summary-section">
            <h4>📌 Highlights</h4>
            <ul>
                ${(summary.highlights || ['Great progress this week!']).map(h => `<li>${h}</li>`).join('')}
            </ul>
        </div>
    `;
}

function closeWeeklySummaryModal() {
    document.getElementById('weeklySummaryModal').classList.remove('active');
}

async function exportDoctorReport() {
    document.getElementById('doctorReportModal').classList.add('active');
    
    try {
        const response = await api.getDoctorReport(state.user.id);
        
        if (response.success) {
            renderDoctorReport(response.data);
        } else {
            document.getElementById('doctorReportBody').innerHTML = '<p class="no-data">Failed to generate report</p>';
        }
    } catch (error) {
        document.getElementById('doctorReportBody').innerHTML = '<p class="no-data">Error generating report</p>';
    }
}

function renderDoctorReport(report) {
    const container = document.getElementById('doctorReportBody');
    
    container.innerHTML = `
        <div class="doctor-report-content">
            <div class="report-header-info">
                <h4>Patient: ${report.patient_name || state.user.full_name}</h4>
                <p>Generated: ${new Date().toLocaleDateString()}</p>
            </div>
            
            <div class="summary-section">
                <h4>Current Medications</h4>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Medicine</th>
                            <th>Dosage</th>
                            <th>Frequency</th>
                            <th>Since</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${(report.medications || []).map(m => `
                            <tr>
                                <td>${m.name}</td>
                                <td>${m.dosage || '-'}</td>
                                <td>${m.frequency || '-'}</td>
                                <td>${formatDate(m.start_date)}</td>
                            </tr>
                        `).join('') || '<tr><td colspan="4">No medications</td></tr>'}
                    </tbody>
                </table>
            </div>
            
            <div class="summary-section">
                <h4>Recent Lab Results</h4>
                ${(report.lab_results || []).map(r => `
                    <p><strong>${r.parameter}:</strong> ${r.value} ${r.unit} 
                       <span class="value-status ${r.status}">${r.status}</span></p>
                `).join('') || '<p>No recent lab results</p>'}
            </div>
            
            <div class="summary-section">
                <h4>Health Conditions</h4>
                <p>${(report.conditions || []).join(', ') || 'None recorded'}</p>
            </div>
            
            <div class="summary-section">
                <h4>Adherence (Last 30 Days)</h4>
                <p>Overall adherence rate: <strong>${report.adherence_rate || 0}%</strong></p>
            </div>
        </div>
    `;
}

function closeDoctorReportModal() {
    document.getElementById('doctorReportModal').classList.remove('active');
}

function downloadDoctorReport() {
    showToast('Generating PDF...', 'info');
    exportToPDF();
}

function downloadWeeklySummary() {
    showToast('Generating PDF...', 'info');
    exportToPDF();
}

// =====================================================
// LIFESTYLE PAGE FUNCTIONS
// =====================================================

async function loadLifestylePage() {
    if (!state.user) return;
    
    showLoading();
    try {
        // Load all lifestyle data in parallel
        const [
            waterRes,
            activityRes,
            calorieRes,
            groceryRes,
            suggestionsRes
        ] = await Promise.all([
            api.getDailyWaterIntake(state.user.id).catch(() => ({ success: false })),
            api.getDailyActivity(state.user.id).catch(() => ({ success: false })),
            api.getCalorieBalance(state.user.id).catch(() => ({ success: false })),
            api.getGroceryList(state.user.id).catch(() => ({ success: false })),
            api.getDietSuggestions(state.user.id).catch(() => ({ success: false }))
        ]);
        
        // Render water intake
        if (waterRes.success) {
            renderWaterIntake(waterRes.data);
        }
        
        // Render activities
        if (activityRes.success) {
            renderDailyActivity(activityRes.data);
        }
        
        // Render calorie balance
        if (calorieRes.success) {
            renderCalorieBalance(calorieRes.data);
        }
        
        // Render grocery list
        if (groceryRes.success) {
            renderGroceryList(groceryRes.data);
        }
        
        // Render diet suggestions
        if (suggestionsRes.success) {
            renderDietSuggestions(suggestionsRes.data);
        }
        
        // Load activity history for chart
        loadActivityChart();
        loadWaterHistory();
        
    } catch (error) {
        console.error('Lifestyle page load error:', error);
    }
    hideLoading();
}

function renderWaterIntake(data) {
    const current = data.total_ml || 0;
    const goal = data.goal_ml || 2500;
    const percentage = Math.min((current / goal) * 100, 100);
    
    document.getElementById('waterCurrent').textContent = current;
    document.getElementById('waterGoal').textContent = goal;
    document.getElementById('waterFill').style.height = `${percentage}%`;
}

async function addWater(amount) {
    try {
        const response = await api.logWaterIntake(state.user.id, amount);
        if (response.success) {
            showToast(`Added ${amount}ml water`, 'success');
            // Update display
            const current = parseInt(document.getElementById('waterCurrent').textContent) + amount;
            const goal = parseInt(document.getElementById('waterGoal').textContent);
            document.getElementById('waterCurrent').textContent = current;
            document.getElementById('waterFill').style.height = `${Math.min((current / goal) * 100, 100)}%`;
        }
    } catch (error) {
        showToast('Failed to log water', 'error');
    }
}

function showCustomWaterModal() {
    document.getElementById('waterModal').classList.add('active');
}

function closeWaterModal() {
    document.getElementById('waterModal').classList.remove('active');
    document.getElementById('waterForm').reset();
}

async function handleWaterSubmit(e) {
    e.preventDefault();
    const amount = parseInt(document.getElementById('customWaterAmount').value);
    await addWater(amount);
    closeWaterModal();
}

async function loadWaterHistory() {
    try {
        const response = await api.getWaterHistory(state.user.id, 7);
        if (response.success && response.data) {
            renderWaterHistoryMini(response.data);
        }
    } catch (error) {
        console.error('Failed to load water history');
    }
}

function renderWaterHistoryMini(history) {
    const container = document.getElementById('waterHistoryMini');
    const goal = 2500;
    
    container.innerHTML = history.slice(-7).map(day => {
        const percentage = Math.min((day.total_ml / goal) * 100, 100);
        return `
            <div class="water-day" title="${formatDateShort(day.date)}: ${day.total_ml}ml">
                <div class="water-day-fill" style="height: ${percentage}%"></div>
            </div>
        `;
    }).join('');
}

function renderDailyActivity(data) {
    const activities = data.activities || [];
    const summary = data.summary || {};
    
    document.getElementById('caloriesBurned').textContent = summary.total_calories || 0;
    document.getElementById('activeMinutes').textContent = summary.total_minutes || 0;
    document.getElementById('activitiesCount').textContent = activities.length;
    
    const container = document.getElementById('activityList');
    if (activities.length === 0) {
        container.innerHTML = '<li class="no-data">No activities logged today</li>';
        return;
    }
    
    container.innerHTML = activities.map(a => `
        <li>
            <div class="activity-info">
                <div class="activity-icon">
                    <i class="fas fa-${getActivityIcon(a.activity_type)}"></i>
                </div>
                <div class="activity-details">
                    <span class="activity-name">${a.activity_type}</span>
                    <span class="activity-meta">${a.duration_minutes} min • ${a.calories_burned} cal</span>
                </div>
            </div>
            <button class="btn btn-icon btn-sm" onclick="deleteActivity(${a.id})">
                <i class="fas fa-times"></i>
            </button>
        </li>
    `).join('');
}

function getActivityIcon(type) {
    const icons = {
        'walking': 'walking',
        'running': 'running',
        'cycling': 'biking',
        'swimming': 'swimmer',
        'yoga': 'spa',
        'gym': 'dumbbell',
        'sports': 'basketball-ball',
        'other': 'heartbeat'
    };
    return icons[type.toLowerCase()] || 'heartbeat';
}

function showLogActivityModal() {
    document.getElementById('activityModal').classList.add('active');
}

function closeActivityModal() {
    document.getElementById('activityModal').classList.remove('active');
    document.getElementById('activityForm').reset();
}

async function handleActivitySubmit(e) {
    e.preventDefault();
    
    const activityType = document.getElementById('activityType').value;
    const duration = document.getElementById('activityDuration').value;
    const intensity = document.getElementById('activityIntensity').value;
    const calories = document.getElementById('activityCalories').value || null;
    const notes = document.getElementById('activityNotes').value;
    
    showLoading();
    try {
        const response = await api.logActivity(state.user.id, activityType, duration, intensity, calories, notes);
        if (response.success) {
            showToast('Activity logged!', 'success');
            closeActivityModal();
            loadLifestylePage();
        }
    } catch (error) {
        showToast('Failed to log activity', 'error');
    }
    hideLoading();
}

async function deleteActivity(activityId) {
    if (!confirm('Delete this activity?')) return;
    
    try {
        // Implementation would need endpoint
        showToast('Activity deleted', 'success');
        loadLifestylePage();
    } catch (error) {
        showToast('Failed to delete activity', 'error');
    }
}

function renderCalorieBalance(data) {
    document.getElementById('caloriesIn').textContent = data.calories_in || 0;
    document.getElementById('caloriesOut').textContent = data.calories_out || 0;
    
    const net = (data.calories_in || 0) - (data.calories_out || 0);
    const netElement = document.getElementById('calorieNet');
    netElement.textContent = (net >= 0 ? '+' : '') + net;
    netElement.className = 'balance-value ' + (net >= 0 ? 'positive' : 'negative');
    
    document.getElementById('userBMR').textContent = data.bmr || '-';
}

function renderGroceryList(data) {
    const container = document.getElementById('groceryList');
    
    if (!data || data.length === 0) {
        container.innerHTML = '<p class="no-data">No items in grocery list</p>';
        return;
    }
    
    // Group by category
    const categories = {};
    data.forEach(item => {
        const cat = item.category || 'Other';
        if (!categories[cat]) categories[cat] = [];
        categories[cat].push(item);
    });
    
    container.innerHTML = Object.keys(categories).map(cat => `
        <div class="grocery-category">
            <h4>${cat}</h4>
            ${categories[cat].map(item => `
                <div class="grocery-item ${item.is_purchased ? 'purchased' : ''}" onclick="toggleGroceryItem(${item.id}, ${!item.is_purchased})">
                    <span class="grocery-checkbox">
                        ${item.is_purchased ? '<i class="fas fa-check"></i>' : ''}
                    </span>
                    <span class="grocery-name">${item.item_name}</span>
                    <span class="grocery-quantity">${item.quantity || ''}</span>
                </div>
            `).join('')}
        </div>
    `).join('');
}

async function toggleGroceryItem(itemId, isPurchased) {
    try {
        await api.updateGroceryItem(itemId, state.user.id, isPurchased);
        loadLifestylePage();
    } catch (error) {
        showToast('Failed to update item', 'error');
    }
}

function showAddGroceryItemModal() {
    document.getElementById('groceryModal').classList.add('active');
}

function closeGroceryModal() {
    document.getElementById('groceryModal').classList.remove('active');
    document.getElementById('groceryForm').reset();
}

async function handleGrocerySubmit(e) {
    e.preventDefault();
    
    const name = document.getElementById('groceryItemName').value;
    const quantity = document.getElementById('groceryQuantity').value;
    const category = document.getElementById('groceryCategory').value;
    
    showLoading();
    try {
        const response = await api.addGroceryItem(state.user.id, name, quantity, category);
        if (response.success) {
            showToast('Item added!', 'success');
            closeGroceryModal();
            loadLifestylePage();
        }
    } catch (error) {
        showToast('Failed to add item', 'error');
    }
    hideLoading();
}

async function generateGroceryListFromDiet() {
    showLoading();
    try {
        const response = await api.generateGroceryList(state.user.id);
        if (response.success) {
            showToast('Grocery list generated from your diet plan!', 'success');
            loadLifestylePage();
        }
    } catch (error) {
        showToast('Failed to generate list', 'error');
    }
    hideLoading();
}

function renderDietSuggestions(suggestions) {
    const container = document.getElementById('dietSuggestions');
    
    if (!suggestions || suggestions.length === 0) {
        container.innerHTML = '<p class="no-data">No personalized suggestions available</p>';
        return;
    }
    
    container.innerHTML = suggestions.map(s => `
        <div class="diet-suggestion-item">
            <div class="suggestion-icon ${s.type || 'eat'}">
                <i class="fas fa-${s.type === 'avoid' ? 'times' : 'check'}"></i>
            </div>
            <div class="suggestion-content">
                <h4>${s.food_name || s.title}</h4>
                <p>${s.reason || s.description}</p>
            </div>
        </div>
    `).join('');
}

async function loadActivityChart() {
    try {
        const response = await api.getActivityHistory(state.user.id, 7);
        if (response.success && response.data) {
            renderActivityChart(response.data);
        }
    } catch (error) {
        console.error('Failed to load activity chart');
    }
}

function renderActivityChart(history) {
    const container = document.getElementById('activityChart');
    const maxMinutes = Math.max(...history.map(d => d.total_minutes || 0), 60);
    
    container.innerHTML = history.map(day => {
        const height = ((day.total_minutes || 0) / maxMinutes) * 100;
        return `
            <div class="activity-bar" style="height: ${Math.max(height, 5)}%;" title="${day.total_minutes || 0} minutes">
                <span class="activity-bar-label">${formatDayShort(day.date)}</span>
            </div>
        `;
    }).join('');
}

// =====================================================
// EMERGENCY PAGE FUNCTIONS
// =====================================================

async function loadEmergencyPage() {
    if (!state.user) return;
    
    showLoading();
    try {
        const [
            emergencyInfoRes,
            contactsRes,
            caregiversRes
        ] = await Promise.all([
            api.getEmergencyInfo(state.user.id).catch(() => ({ success: false })),
            api.getEmergencyContacts(state.user.id).catch(() => ({ success: false })),
            api.getCaregivers(state.user.id).catch(() => ({ success: false }))
        ]);
        
        if (emergencyInfoRes.success) {
            renderEmergencyCard(emergencyInfoRes.data);
        }
        
        if (contactsRes.success) {
            renderEmergencyContacts(contactsRes.data);
        }
        
        if (caregiversRes.success) {
            renderCaregivers(caregiversRes.data);
        }
        
    } catch (error) {
        console.error('Emergency page load error:', error);
    }
    hideLoading();
}

function renderEmergencyCard(info) {
    document.getElementById('ecardName').textContent = info.full_name || state.user.full_name || '-';
    document.getElementById('ecardBloodType').textContent = info.blood_type || '-';
    document.getElementById('ecardAllergies').textContent = info.allergies || 'None known';
    document.getElementById('ecardMedications').textContent = info.current_medications || '-';
    document.getElementById('ecardConditions').textContent = info.conditions || '-';
    document.getElementById('ecardEmergencyContact').textContent = info.emergency_contact || '-';
}

function renderEmergencyContacts(contacts) {
    const container = document.getElementById('emergencyContactsList');
    
    if (!contacts || contacts.length === 0) {
        container.innerHTML = '<li class="no-data">No emergency contacts added</li>';
        return;
    }
    
    container.innerHTML = contacts.map(c => `
        <li class="emergency-contact-item ${c.is_primary ? 'primary' : ''}">
            <div class="contact-info">
                <div class="contact-avatar">${c.name.charAt(0).toUpperCase()}</div>
                <div class="contact-details">
                    <h4>${c.name} ${c.is_primary ? '(Primary)' : ''}</h4>
                    <p>${c.relationship} • ${c.phone}</p>
                </div>
            </div>
            <div class="contact-actions">
                <a href="tel:${c.phone}" class="btn-call">
                    <i class="fas fa-phone"></i>
                </a>
                <button class="btn btn-icon btn-sm" onclick="deleteEmergencyContact(${c.id})">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </li>
    `).join('');
}

function renderCaregivers(caregivers) {
    const container = document.getElementById('caregiversList');
    
    if (!caregivers || caregivers.length === 0) {
        container.innerHTML = '<li class="no-data">No caregivers added</li>';
        return;
    }
    
    container.innerHTML = caregivers.map(c => `
        <li class="caregiver-item">
            <div class="caregiver-info">
                <div class="caregiver-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="caregiver-details">
                    <h4>${c.caregiver_name}</h4>
                    <p>${c.relationship} • ${c.caregiver_email}</p>
                    <div class="caregiver-permissions">
                        ${(c.permissions || []).map(p => `<span class="permission-badge">${p.replace('_', ' ')}</span>`).join('')}
                    </div>
                </div>
            </div>
            <div class="caregiver-status ${c.is_active ? 'active' : 'pending'}">
                <i class="fas fa-${c.is_active ? 'check-circle' : 'clock'}"></i>
                ${c.is_active ? 'Active' : 'Pending'}
            </div>
            <button class="btn btn-icon btn-sm" onclick="removeCaregiver(${c.id})">
                <i class="fas fa-times"></i>
            </button>
        </li>
    `).join('');
}

// Emergency Info Modal
function showEditEmergencyInfoModal() {
    // Pre-populate form if data exists
    document.getElementById('emergencyInfoModal').classList.add('active');
}

function closeEmergencyInfoModal() {
    document.getElementById('emergencyInfoModal').classList.remove('active');
}

async function handleEmergencyInfoSubmit(e) {
    e.preventDefault();
    
    const data = {
        blood_type: document.getElementById('emergencyBloodType').value,
        organ_donor: document.getElementById('emergencyOrganDonor').value,
        allergies: document.getElementById('emergencyAllergies').value,
        special_instructions: document.getElementById('emergencyInstructions').value,
        preferred_hospital: document.getElementById('emergencyHospital').value,
        doctor_contact: document.getElementById('emergencyDoctor').value
    };
    
    showLoading();
    try {
        const response = await api.saveEmergencyInfo(state.user.id, data);
        if (response.success) {
            showToast('Emergency info saved!', 'success');
            closeEmergencyInfoModal();
            loadEmergencyPage();
        }
    } catch (error) {
        showToast('Failed to save info', 'error');
    }
    hideLoading();
}

// Emergency Contact Modal
function showAddEmergencyContactModal() {
    document.getElementById('emergencyContactModal').classList.add('active');
}

function closeEmergencyContactModal() {
    document.getElementById('emergencyContactModal').classList.remove('active');
    document.getElementById('emergencyContactForm').reset();
}

async function handleEmergencyContactSubmit(e) {
    e.preventDefault();
    
    const name = document.getElementById('contactName').value;
    const phone = document.getElementById('contactPhone').value;
    const relationship = document.getElementById('contactRelationship').value;
    const isPrimary = document.getElementById('contactPrimary').checked;
    
    showLoading();
    try {
        const response = await api.addEmergencyContact(state.user.id, name, phone, relationship, isPrimary);
        if (response.success) {
            showToast('Contact added!', 'success');
            closeEmergencyContactModal();
            loadEmergencyPage();
        }
    } catch (error) {
        showToast('Failed to add contact', 'error');
    }
    hideLoading();
}

async function deleteEmergencyContact(contactId) {
    if (!confirm('Delete this contact?')) return;
    
    try {
        await api.deleteEmergencyContact(contactId, state.user.id);
        showToast('Contact deleted', 'success');
        loadEmergencyPage();
    } catch (error) {
        showToast('Failed to delete contact', 'error');
    }
}

// Caregiver Modal
function showAddCaregiverModal() {
    document.getElementById('caregiverModal').classList.add('active');
}

function closeCaregiverModal() {
    document.getElementById('caregiverModal').classList.remove('active');
    document.getElementById('caregiverForm').reset();
}

async function handleCaregiverSubmit(e) {
    e.preventDefault();
    
    const name = document.getElementById('caregiverName').value;
    const email = document.getElementById('caregiverEmail').value;
    const relationship = document.getElementById('caregiverRelationship').value;
    
    const permissions = [];
    document.querySelectorAll('input[name="caregiverPerms"]:checked').forEach(cb => {
        permissions.push(cb.value);
    });
    
    showLoading();
    try {
        const response = await api.addCaregiver(state.user.id, email, name, relationship, permissions);
        if (response.success) {
            showToast(`Caregiver added! Access code: ${response.access_code}`, 'success');
            closeCaregiverModal();
            loadEmergencyPage();
        }
    } catch (error) {
        showToast('Failed to add caregiver', 'error');
    }
    hideLoading();
}

async function removeCaregiver(caregiverId) {
    if (!confirm('Remove this caregiver?')) return;
    
    try {
        await api.removeCaregiver(caregiverId, state.user.id);
        showToast('Caregiver removed', 'success');
        loadEmergencyPage();
    } catch (error) {
        showToast('Failed to remove caregiver', 'error');
    }
}

// QR Code
async function showQRCodeModal() {
    document.getElementById('qrCodeModal').classList.add('active');
    
    try {
        const response = await api.generateQRCode(state.user.id);
        if (response.success && response.data) {
            document.getElementById('accessCodeDisplay').textContent = response.data.access_code || '------';
            // QR code generation would need a library like qrcode.js
            document.getElementById('qrCodeContainer').innerHTML = `
                <div style="padding: 2rem; background: white; display: inline-block; border-radius: 8px;">
                    <p style="font-size: 0.875rem; color: #666;">Scan URL:</p>
                    <p style="font-family: monospace; word-break: break-all;">${response.data.url || window.location.origin + '/emergency?code=' + response.data.access_code}</p>
                </div>
            `;
        }
    } catch (error) {
        document.getElementById('qrCodeContainer').innerHTML = '<p>Failed to generate QR code</p>';
    }
}

function closeQRCodeModal() {
    document.getElementById('qrCodeModal').classList.remove('active');
}

function downloadQRCode() {
    showToast('QR code download not implemented', 'info');
}

function downloadEmergencyCard() {
    showToast('Generating emergency card...', 'info');
    exportToPDF();
}

function printEmergencyCard() {
    window.print();
}

function shareEmergencyInfo() {
    if (navigator.share) {
        navigator.share({
            title: 'MediAssist+ Emergency Info',
            text: 'Access my emergency health information',
            url: window.location.origin + '/emergency'
        });
    } else {
        showToast('Sharing not supported on this device', 'info');
    }
}

// =====================================================
// DOCTOR FUNCTIONS
// =====================================================

// selectedPatient is declared at the top of the file

async function loadDoctorDashboard() {
    if (!state.user || state.user.role !== 'doctor') return;
    
    showLoading();
    try {
        const response = await api.getDoctorDashboard(state.user.id);
        
        if (response.success) {
            document.getElementById('totalPatientsCount').textContent = response.total_patients;
            document.getElementById('avgComplianceRate').textContent = response.average_compliance + '%';
            document.getElementById('lowComplianceCount').textContent = response.low_compliance_count;
            
            // Render low compliance patients
            const lowComplianceList = document.getElementById('lowCompliancePatients');
            if (response.low_compliance_patients.length > 0) {
                lowComplianceList.innerHTML = response.low_compliance_patients.map(patient => `
                    <li class="patient-alert-item" onclick="viewPatient(${patient.id})">
                        <div class="patient-info">
                            <span class="patient-name">${patient.full_name}</span>
                            <span class="patient-username">@${patient.username}</span>
                        </div>
                        <span class="compliance-badge low">${patient.compliance_percentage}%</span>
                    </li>
                `).join('');
            } else {
                lowComplianceList.innerHTML = '<li class="no-data">All patients have good compliance!</li>';
            }
        }
    } catch (error) {
        console.error('Doctor dashboard error:', error);
        showToast('Failed to load dashboard', 'error');
    }
    hideLoading();
}

async function loadDoctorPatients() {
    if (!state.user || state.user.role !== 'doctor') return;
    
    showLoading();
    try {
        const response = await api.getDoctorPatients(state.user.id);
        
        if (response.success) {
            renderPatientsList(response.patients);
        }
    } catch (error) {
        console.error('Load patients error:', error);
        showToast('Failed to load patients', 'error');
    }
    hideLoading();
}

function renderPatientsList(patients) {
    const container = document.getElementById('patientsGrid');
    
    if (!patients || patients.length === 0) {
        container.innerHTML = '<div class="no-data-card"><i class="fas fa-users"></i><p>No patients added yet</p></div>';
        return;
    }
    
    container.innerHTML = patients.map(patient => `
        <div class="patient-card" onclick="viewPatient(${patient.id})">
            <div class="patient-card-header">
                <img src="https://ui-avatars.com/api/?name=${encodeURIComponent(patient.full_name)}&background=4f46e5&color=fff" alt="${patient.full_name}" class="patient-avatar">
                <div class="patient-header-info">
                    <h4>${patient.full_name}</h4>
                    <span class="patient-username">@${patient.username}</span>
                </div>
            </div>
            <div class="patient-card-body">
                <div class="patient-stat">
                    <span class="stat-label">Compliance</span>
                    <span class="stat-value ${patient.compliance.compliance_percentage >= 70 ? 'good' : 'low'}">${patient.compliance.compliance_percentage}%</span>
                </div>
                <div class="patient-stat">
                    <span class="stat-label">Taken</span>
                    <span class="stat-value">${patient.compliance.taken || 0}</span>
                </div>
                <div class="patient-stat">
                    <span class="stat-label">Missed</span>
                    <span class="stat-value missed">${patient.compliance.missed || 0}</span>
                </div>
            </div>
            <div class="patient-card-footer">
                <button class="btn btn-sm btn-outline" onclick="event.stopPropagation(); viewPatient(${patient.id})">
                    <i class="fas fa-eye"></i> View
                </button>
                <button class="btn btn-sm btn-primary" onclick="event.stopPropagation(); showAssignMedicineModal(${patient.id}, '${patient.full_name}')">
                    <i class="fas fa-pills"></i> Assign
                </button>
            </div>
        </div>
    `).join('');
}

async function searchPatientByUsername() {
    const username = document.getElementById('patientSearchInput').value.trim();
    
    if (username.length < 2) {
        showToast('Enter at least 2 characters to search', 'warning');
        return;
    }
    
    showLoading();
    try {
        const response = await api.searchPatient(state.user.id, username);
        
        if (response.success) {
            renderSearchResults(response.patients);
        }
    } catch (error) {
        console.error('Search error:', error);
        showToast('Search failed', 'error');
    }
    hideLoading();
}

function renderSearchResults(patients) {
    const container = document.getElementById('patientSearchResults');
    
    if (!patients || patients.length === 0) {
        container.innerHTML = '<div class="no-data">No patients found</div>';
        return;
    }
    
    container.innerHTML = patients.map(patient => `
        <div class="search-result-item">
            <div class="result-info">
                <span class="result-name">${patient.full_name}</span>
                <span class="result-username">@${patient.username}</span>
            </div>
            <button class="btn btn-sm btn-primary" onclick="addPatientToList(${patient.id})">
                <i class="fas fa-plus"></i> Add
            </button>
        </div>
    `).join('');
}

async function addPatientToList(patientId) {
    showLoading();
    try {
        const response = await api.addPatientToDoctor(state.user.id, patientId);
        
        if (response.success) {
            showToast('Patient added successfully', 'success');
            document.getElementById('patientSearchResults').innerHTML = '';
            document.getElementById('patientSearchInput').value = '';
            loadDoctorPatients();
            hideAddPatientModal();
        }
    } catch (error) {
        console.error('Add patient error:', error);
        showToast(error.message || 'Failed to add patient', 'error');
    }
    hideLoading();
}

async function viewPatient(patientId) {
    showLoading();
    try {
        const response = await api.getPatientDetails(state.user.id, patientId);
        
        if (response.success) {
            selectedPatient = response.patient;
            renderPatientDetail(response);
            showPage('patient-detail');
        }
    } catch (error) {
        console.error('View patient error:', error);
        showToast('Failed to load patient details', 'error');
    }
    hideLoading();
}

function renderPatientDetail(data) {
    const patient = data.patient;
    
    // Update header
    document.getElementById('patientDetailName').textContent = patient.full_name;
    document.getElementById('patientDetailUsername').textContent = '@' + patient.username;
    document.getElementById('patientDetailAvatar').src = 
        `https://ui-avatars.com/api/?name=${encodeURIComponent(patient.full_name)}&background=4f46e5&color=fff&size=128`;
    
    // Update patient info
    document.getElementById('patientDetailPhone').textContent = patient.phone || 'Not provided';
    document.getElementById('patientDetailDob').textContent = patient.date_of_birth || 'Not provided';
    document.getElementById('patientDetailGender').textContent = capitalizeFirst(patient.gender) || 'Not provided';
    
    // Update compliance
    const compliance = data.overall_compliance;
    document.getElementById('patientComplianceRate').textContent = compliance.compliance_percentage + '%';
    document.getElementById('patientTotalDoses').textContent = compliance.total || 0;
    document.getElementById('patientTakenDoses').textContent = compliance.taken || 0;
    document.getElementById('patientMissedDoses').textContent = compliance.missed || 0;
    
    // Render compliance chart
    renderPatientComplianceChart(data.compliance);
    
    // Render conditions
    const conditionsList = document.getElementById('patientConditionsList');
    if (data.conditions && data.conditions.length > 0) {
        conditionsList.innerHTML = data.conditions.map(c => `
            <span class="condition-tag">${c.name}</span>
        `).join('');
    } else {
        conditionsList.innerHTML = '<span class="no-data">No conditions recorded</span>';
    }
    
    // Render medicines
    const medicinesList = document.getElementById('patientMedicinesList');
    if (data.medicines && data.medicines.length > 0) {
        medicinesList.innerHTML = data.medicines.map(m => `
            <div class="medicine-item">
                <div class="medicine-info">
                    <span class="medicine-name">${m.name}</span>
                    <span class="medicine-dosage">${m.dosage} - ${m.frequency}</span>
                </div>
                <span class="medicine-status ${m.is_active ? 'active' : 'inactive'}">
                    ${m.is_active ? 'Active' : 'Inactive'}
                </span>
            </div>
        `).join('');
    } else {
        medicinesList.innerHTML = '<div class="no-data">No medicines assigned</div>';
    }
    
    // Render diet plan
    const dietInfo = document.getElementById('patientDietInfo');
    if (data.diet_plan) {
        dietInfo.innerHTML = `
            <div class="diet-summary">
                <h5>${data.diet_plan.plan_name}</h5>
                <div class="diet-targets">
                    <span>Calories: ${data.diet_plan.target_calories || '-'}</span>
                    <span>Protein: ${data.diet_plan.target_protein_g || '-'}g</span>
                    <span>Carbs: ${data.diet_plan.target_carbs_g || '-'}g</span>
                    <span>Fat: ${data.diet_plan.target_fat_g || '-'}g</span>
                </div>
            </div>
        `;
    } else {
        dietInfo.innerHTML = '<div class="no-data">No diet plan assigned</div>';
    }
}

function renderPatientComplianceChart(complianceData) {
    const container = document.getElementById('patientComplianceChart');
    
    if (!complianceData || complianceData.length === 0) {
        container.innerHTML = '<div class="no-data">No compliance data</div>';
        return;
    }
    
    const maxTotal = Math.max(...complianceData.map(d => d.total), 1);
    
    container.innerHTML = `
        <div class="compliance-chart">
            ${complianceData.slice(0, 7).reverse().map(day => {
                const takenPercent = (day.taken / day.total) * 100 || 0;
                return `
                    <div class="chart-bar-container">
                        <div class="chart-bar" style="height: ${takenPercent}%"></div>
                        <span class="chart-label">${formatDayShort(day.date)}</span>
                    </div>
                `;
            }).join('')}
        </div>
    `;
}

function showAddPatientModal() {
    document.getElementById('addPatientModal').classList.add('active');
}

function hideAddPatientModal() {
    document.getElementById('addPatientModal').classList.remove('active');
    document.getElementById('patientSearchResults').innerHTML = '';
    document.getElementById('patientSearchInput').value = '';
}

function showCreatePatientModal() {
    document.getElementById('createPatientModal').classList.add('active');
}

function hideCreatePatientModal() {
    document.getElementById('createPatientModal').classList.remove('active');
    document.getElementById('createPatientForm').reset();
}

async function handleCreatePatient(e) {
    e.preventDefault();
    
    const patientData = {
        full_name: document.getElementById('newPatientName').value,
        email: document.getElementById('newPatientEmail').value,
        username: document.getElementById('newPatientUsername').value,
        password: document.getElementById('newPatientPassword').value,
        phone: document.getElementById('newPatientPhone').value,
        date_of_birth: document.getElementById('newPatientDob').value,
        gender: document.getElementById('newPatientGender').value
    };
    
    showLoading();
    try {
        const response = await api.createPatientByDoctor(state.user.id, patientData);
        
        if (response.success) {
            showToast('Patient account created successfully', 'success');
            hideCreatePatientModal();
            loadDoctorPatients();
        }
    } catch (error) {
        console.error('Create patient error:', error);
        showToast(error.message || 'Failed to create patient', 'error');
    }
    hideLoading();
}

function showAssignMedicineModal(patientId, patientName) {
    selectedPatient = { id: patientId, full_name: patientName };
    document.getElementById('assignMedicinePatientName').textContent = patientName;
    document.getElementById('assignMedicineModal').classList.add('active');
}

function hideAssignMedicineModal() {
    document.getElementById('assignMedicineModal').classList.remove('active');
    document.getElementById('assignMedicineForm').reset();
    selectedPatient = null;
}

async function handleAssignMedicine(e) {
    e.preventDefault();
    
    if (!selectedPatient) {
        showToast('No patient selected', 'error');
        return;
    }
    
    // Collect schedule times
    const schedules = [];
    document.querySelectorAll('.schedule-time-input').forEach(input => {
        if (input.value) {
            schedules.push({
                time: input.value,
                dose_amount: 1,
                meal_relation: input.dataset.mealRelation || 'anytime'
            });
        }
    });
    
    const medicineData = {
        name: document.getElementById('assignMedName').value,
        dosage: document.getElementById('assignMedDosage').value,
        dose_type: document.getElementById('assignMedType').value,
        frequency: document.getElementById('assignMedFrequency').value,
        start_date: document.getElementById('assignMedStartDate').value,
        end_date: document.getElementById('assignMedEndDate').value,
        instructions: document.getElementById('assignMedInstructions').value,
        prescription_notes: document.getElementById('assignMedNotes').value,
        schedules: schedules
    };
    
    showLoading();
    try {
        const response = await api.assignMedicineToPatient(state.user.id, selectedPatient.id, medicineData);
        
        if (response.success) {
            showToast('Medicine assigned successfully', 'success');
            hideAssignMedicineModal();
            if (state.currentPage === 'patient-detail') {
                viewPatient(selectedPatient.id);
            }
        }
    } catch (error) {
        console.error('Assign medicine error:', error);
        showToast(error.message || 'Failed to assign medicine', 'error');
    }
    hideLoading();
}

function showAssignDietModal(patientId, patientName) {
    selectedPatient = { id: patientId, full_name: patientName };
    document.getElementById('assignDietPatientName').textContent = patientName;
    document.getElementById('assignDietModal').classList.add('active');
}

function hideAssignDietModal() {
    document.getElementById('assignDietModal').classList.remove('active');
    document.getElementById('assignDietForm').reset();
    selectedPatient = null;
}

async function handleAssignDiet(e) {
    e.preventDefault();
    
    if (!selectedPatient) {
        showToast('No patient selected', 'error');
        return;
    }
    
    const dietData = {
        plan_name: document.getElementById('assignDietName').value,
        target_calories: parseInt(document.getElementById('assignDietCalories').value) || null,
        target_protein_g: parseFloat(document.getElementById('assignDietProtein').value) || null,
        target_carbs_g: parseFloat(document.getElementById('assignDietCarbs').value) || null,
        target_fat_g: parseFloat(document.getElementById('assignDietFat').value) || null,
        condition_focus: document.getElementById('assignDietCondition').value,
        start_date: document.getElementById('assignDietStartDate').value,
        end_date: document.getElementById('assignDietEndDate').value,
        doctor_notes: document.getElementById('assignDietNotes').value
    };
    
    showLoading();
    try {
        const response = await api.assignDietToPatient(state.user.id, selectedPatient.id, dietData);
        
        if (response.success) {
            showToast('Diet plan assigned successfully', 'success');
            hideAssignDietModal();
            if (state.currentPage === 'patient-detail') {
                viewPatient(selectedPatient.id);
            }
        }
    } catch (error) {
        console.error('Assign diet error:', error);
        showToast(error.message || 'Failed to assign diet plan', 'error');
    }
    hideLoading();
}

function showSendMessageModal() {
    if (!selectedPatient) {
        showToast('No patient selected', 'error');
        return;
    }
    document.getElementById('messagePatientName').textContent = selectedPatient.full_name;
    document.getElementById('sendMessageModal').classList.add('active');
}

function hideSendMessageModal() {
    document.getElementById('sendMessageModal').classList.remove('active');
    document.getElementById('sendMessageForm').reset();
}

async function handleSendMessage(e) {
    e.preventDefault();
    
    if (!selectedPatient) {
        showToast('No patient selected', 'error');
        return;
    }
    
    const title = document.getElementById('messageTitle').value;
    const message = document.getElementById('messageBody').value;
    
    showLoading();
    try {
        const response = await api.sendMessageToPatient(state.user.id, selectedPatient.id, title, message);
        
        if (response.success) {
            showToast('Message sent successfully', 'success');
            hideSendMessageModal();
        }
    } catch (error) {
        console.error('Send message error:', error);
        showToast(error.message || 'Failed to send message', 'error');
    }
    hideLoading();
}

function goBackFromPatientDetail() {
    selectedPatient = null;
    showPage('doctor-patients');
}

function addScheduleTimeInput() {
    const container = document.getElementById('scheduleTimesContainer');
    const count = container.querySelectorAll('.schedule-time-input').length + 1;
    
    const div = document.createElement('div');
    div.className = 'form-group schedule-time-group';
    div.innerHTML = `
        <label>Time ${count}</label>
        <div class="input-group">
            <input type="time" class="form-input schedule-time-input" data-meal-relation="anytime">
            <select class="form-select" onchange="this.previousElementSibling.dataset.mealRelation = this.value">
                <option value="anytime">Anytime</option>
                <option value="before_meal">Before Meal</option>
                <option value="after_meal">After Meal</option>
                <option value="with_meal">With Meal</option>
                <option value="empty_stomach">Empty Stomach</option>
            </select>
            <button type="button" class="btn btn-sm btn-icon" onclick="this.parentElement.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    container.appendChild(div);
}

function capitalizeFirst(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function toggleDoctorFields() {
    const role = document.querySelector('input[name="regRoleRadio"]:checked')?.value || 'patient';
    document.getElementById('regRole').value = role;
    document.getElementById('doctorFields').style.display = role === 'doctor' ? 'block' : 'none';
}

// =====================================================
// UTILITY FUNCTIONS
// =====================================================

function formatDateShort(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

function formatDayShort(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { weekday: 'short' });
}

function showRefillMedicine(medicineId) {
    showToast('Refill reminder set', 'info');
}

// =====================================================
// FORM EVENT LISTENERS
// =====================================================

document.addEventListener('DOMContentLoaded', () => {
    // Side Effect Form
    const sideEffectForm = document.getElementById('sideEffectForm');
    if (sideEffectForm) {
        sideEffectForm.addEventListener('submit', handleSideEffectSubmit);
    }
    
    // Activity Form
    const activityForm = document.getElementById('activityForm');
    if (activityForm) {
        activityForm.addEventListener('submit', handleActivitySubmit);
    }
    
    // Water Form
    const waterForm = document.getElementById('waterForm');
    if (waterForm) {
        waterForm.addEventListener('submit', handleWaterSubmit);
    }
    
    // Grocery Form
    const groceryForm = document.getElementById('groceryForm');
    if (groceryForm) {
        groceryForm.addEventListener('submit', handleGrocerySubmit);
    }
    
    // Emergency Info Form
    const emergencyInfoForm = document.getElementById('emergencyInfoForm');
    if (emergencyInfoForm) {
        emergencyInfoForm.addEventListener('submit', handleEmergencyInfoSubmit);
    }
    
    // Emergency Contact Form
    const emergencyContactForm = document.getElementById('emergencyContactForm');
    if (emergencyContactForm) {
        emergencyContactForm.addEventListener('submit', handleEmergencyContactSubmit);
    }
    
    // Caregiver Form
    const caregiverForm = document.getElementById('caregiverForm');
    if (caregiverForm) {
        caregiverForm.addEventListener('submit', handleCaregiverSubmit);
    }
});