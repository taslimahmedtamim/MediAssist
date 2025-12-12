# MediAssist+ 

A comprehensive healthcare management application designed to reduce medication errors, simplify interpretation of lab test reports using OCR, and deliver personalized diet suggestions for conditions such as diabetes, hypertension, kidney disease, and obesity.

## Features

### 🏥 Smart Medicine Reminder
- Add and manage medications with dosage information
- Schedule multiple times per day
- Track meal-relation instructions (before/after/with meal, empty stomach)
- Set start and end dates for medication courses

### 💊 Pill Tracker Dashboard
- Daily pill tracking with visual timeline
- Mark pills as taken, skipped, or missed
- Monthly calendar view showing adherence patterns
- Real-time progress tracking
- Streak calculations for motivation

### 📊 Medical Test Report Analyzer (OCR)
- Upload lab reports (images or PDFs)
- Automatic text extraction using OCR (Tesseract)
- Parameter detection for:
  - Complete Blood Count (CBC)
  - Kidney Function Tests
  - Lipid Profile
  - Liver Function Tests
  - Diabetes markers (HbA1c, glucose)
  - Thyroid panel
- Abnormality detection with reference range comparison
- Visual highlighting of abnormal values

### 🥗 Diet & Nutrition Planner
- Personalized diet plans based on health conditions
- Condition-specific meal suggestions for:
  - Diabetes
  - Hypertension
  - Kidney Disease
  - Obesity
- Macro nutrient tracking (calories, protein, carbs, fat)
- Restricted foods management
- Food database with nutritional information

### 👤 User Profile
- Personal health information
- Health conditions tracking
- Height/weight management

## Technology Stack

- **Frontend**: HTML5, CSS3 (Custom Properties, Flexbox, Grid), Vanilla JavaScript
- **Backend**: PHP 7.4+ with PDO
- **Database**: MySQL 5.7+ / MariaDB
- **OCR Service**: Python 3.8+ with Flask, Tesseract, pdf2image

## Project Structure

```
mediAssist/
├── api/
│   ├── config/
│   │   ├── config.php         # App configuration & CORS
│   │   └── database.php       # PDO database connection
│   ├── endpoints/
│   │   ├── users.php          # User authentication & profile
│   │   ├── medicines.php      # Medicine CRUD operations
│   │   ├── tracker.php        # Pill tracking & analytics
│   │   ├── reports.php        # Medical reports management
│   │   └── diet.php           # Diet plans & nutrition
│   └── models/
│       ├── User.php           # User model
│       ├── Medicine.php       # Medicine model
│       ├── PillTracker.php    # Tracking model
│       ├── MedicalReport.php  # Reports model
│       └── DietPlan.php       # Diet model
├── css/
│   └── style.css              # Main stylesheet
├── database/
│   └── schema.sql             # MySQL database schema
├── js/
│   ├── api.js                 # API service class
│   └── app.js                 # Main application logic
├── ocr_service/
│   ├── app.py                 # Python Flask OCR service
│   ├── requirements.txt       # Python dependencies
│   └── README.md              # OCR service documentation
├── uploads/                   # Uploaded report files
├── index.html                 # Main HTML file
└── README.md                  # This file
```

## Installation

### Prerequisites

1. **XAMPP** (or similar) with:
   - Apache 2.4+
   - PHP 7.4+
   - MySQL 5.7+ / MariaDB 10.4+

2. **Python 3.8+** with pip

3. **Tesseract OCR**
   - Windows: Download from [UB Mannheim](https://github.com/UB-Mannheim/tesseract/wiki)
   - Linux: `sudo apt-get install tesseract-ocr`
   - macOS: `brew install tesseract`

4. **Poppler** (for PDF support)
   - Windows: Download from [poppler releases](http://blog.alivate.com.au/poppler-windows/)
   - Linux: `sudo apt-get install poppler-utils`
   - macOS: `brew install poppler`

### Step 1: Database Setup

1. Start MySQL service in XAMPP
2. Open phpMyAdmin (http://localhost/phpmyadmin)
3. Create a new database named `mediassist`
4. Import the schema:
   ```sql
   -- Run the contents of database/schema.sql
   ```

### Step 2: PHP Backend Configuration

1. Ensure the project is in your htdocs folder:
   ```
   C:\xampp\htdocs\mediAssist\
   ```

2. Update database credentials in `api/config/config.php` if needed:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'mediassist');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```

3. Start Apache in XAMPP

### Step 3: Python OCR Service Setup

1. Navigate to the OCR service directory:
   ```bash
   cd C:\xampp\htdocs\mediAssist\ocr_service
   ```

2. Create and activate a virtual environment (optional but recommended):
   ```bash
   python -m venv venv
   venv\Scripts\activate  # Windows
   source venv/bin/activate  # Linux/Mac
   ```

3. Install Python dependencies:
   ```bash
   pip install -r requirements.txt
   ```

4. Update Tesseract path in `app.py` if not in PATH:
   ```python
   pytesseract.pytesseract.tesseract_cmd = r'C:\Program Files\Tesseract-OCR\tesseract.exe'
   ```

5. Start the OCR service:
   ```bash
   python app.py
   ```
   The service will run on `http://localhost:5000`

### Step 4: Access the Application

Open your browser and navigate to:
```
http://localhost/mediAssist/
```

## API Endpoints

### Users
| Method | Endpoint | Action |
|--------|----------|--------|
| POST | /api/endpoints/users.php?action=register | Register new user |
| POST | /api/endpoints/users.php?action=login | User login |
| GET | /api/endpoints/users.php?action=profile | Get user profile |
| POST | /api/endpoints/users.php?action=update_profile | Update profile |

### Medicines
| Method | Endpoint | Action |
|--------|----------|--------|
| GET | /api/endpoints/medicines.php?action=list | Get all medicines |
| GET | /api/endpoints/medicines.php?action=get | Get single medicine |
| POST | /api/endpoints/medicines.php?action=create | Create medicine |
| POST | /api/endpoints/medicines.php?action=update | Update medicine |
| DELETE | /api/endpoints/medicines.php?action=delete | Delete medicine |

### Tracker
| Method | Endpoint | Action |
|--------|----------|--------|
| GET | /api/endpoints/tracker.php?action=today | Get today's pills |
| GET | /api/endpoints/tracker.php?action=dashboard | Get dashboard data |
| POST | /api/endpoints/tracker.php?action=record | Record pill status |
| GET | /api/endpoints/tracker.php?action=monthly | Get monthly analytics |

### Reports
| Method | Endpoint | Action |
|--------|----------|--------|
| GET | /api/endpoints/reports.php?action=list | Get all reports |
| GET | /api/endpoints/reports.php?action=get | Get report details |
| POST | /api/endpoints/reports.php?action=upload | Upload & analyze report |
| DELETE | /api/endpoints/reports.php?action=delete | Delete report |

### Diet
| Method | Endpoint | Action |
|--------|----------|--------|
| GET | /api/endpoints/diet.php?action=active_plan | Get active diet plan |
| POST | /api/endpoints/diet.php?action=create_plan | Create diet plan |
| POST | /api/endpoints/diet.php?action=generate_meals | Generate AI meal plan |
| GET | /api/endpoints/diet.php?action=restricted_foods | Get restricted foods |

## Usage Guide

### Adding a Medicine

1. Navigate to "My Medicines" from the sidebar
2. Click "Add Medicine"
3. Enter medicine details:
   - Name (required)
   - Dosage (e.g., 500mg)
   - Type (tablet, capsule, syrup, etc.)
   - Start date
   - End date (optional)
   - Special instructions
4. Add schedule times with meal relations
5. Click "Save Medicine"

### Tracking Pills

1. Navigate to "Pill Tracker"
2. View today's scheduled pills organized by time
3. Click "Take" to mark a pill as taken
4. Click "Skip" to skip a pill
5. View your progress in the circle chart
6. Check the monthly calendar for adherence patterns

### Uploading Lab Reports

1. Navigate to "Lab Reports"
2. Click "Upload Report"
3. Select report type (CBC, Kidney, etc.)
4. Choose the report date
5. Select an image or PDF file
6. Click "Upload & Analyze"
7. View extracted parameters with normal/abnormal indicators

### Creating a Diet Plan

1. Navigate to "Diet & Nutrition"
2. Click "Create Plan"
3. Enter plan details:
   - Plan name
   - Target calories
   - Macro goals (optional)
   - Condition focus
4. Click "Create Plan"
5. Add meals manually or use "Generate Meals" for AI suggestions

## Security Notes

⚠️ **This is a development/demo application. Before production use:**

1. Implement proper JWT authentication
2. Add input sanitization and validation
3. Use HTTPS in production
4. Hash passwords with bcrypt (implemented)
5. Implement rate limiting
6. Add CSRF protection
7. Validate file uploads thoroughly
8. Configure proper CORS settings

## Troubleshooting

### OCR Not Working
- Verify Tesseract is installed and in PATH
- Check the Tesseract path in `ocr_service/app.py`
- Ensure Poppler is installed for PDF support
- Check OCR service logs for errors

### Database Connection Failed
- Verify MySQL is running in XAMPP
- Check credentials in `api/config/config.php`
- Ensure `mediassist` database exists

### CORS Errors
- The API includes CORS headers by default
- For production, configure specific origins in `api/config/config.php`

### File Upload Issues
- Check PHP upload limits in `php.ini`:
  ```ini
  upload_max_filesize = 10M
  post_max_size = 12M
  ```
- Ensure `uploads/` directory exists and is writable

## License

This project is for educational/demonstration purposes.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Support

For issues and questions, please open an issue on the repository.
