# ��� MediAssist+

**Smart Healthcare Companion** - A web application for managing medications, tracking health, and facilitating doctor-patient communication.

![PHP](https://img.shields.io/badge/PHP-8.0+-purple.svg)
![MySQL](https://img.shields.io/badge/MySQL-5.7+-orange.svg)
![License](https://img.shields.io/badge/license-MIT-green.svg)

---

## ✨ Features

### 💊 Medicine Reminders
- Set multiple daily medication schedules with specific times
- Meal-relation tracking (before meal, after meal, empty stomach)
- Course duration management with start and end dates
- Dosage and dose type support (tablet, capsule, syrup, injection, etc.)
- Browser push notifications for timely reminders

### 📊 Pill Tracker
- Daily medication checklist with take/miss buttons
- Visual adherence charts showing weekly and monthly progress
- Streak counter to encourage consistent medication taking
- Missed dose tracking with reason logging
- Monthly calendar view of adherence history

### 👨‍⚕️ Doctor Dashboard
- Create and manage patient accounts
- Search patients by username
- Assign medications with detailed prescriptions
- Create personalized diet plans for patients
- Real-time compliance monitoring with alerts for low adherence
- View patient health conditions and history

### 🥗 Diet Planner
- Personalized meal plans based on health conditions
- Macro nutrient tracking (calories, protein, carbs, fat)
- Meal-by-meal guidance (breakfast, lunch, dinner, snacks)
- Condition-specific food recommendations
- Restricted foods alerts based on health conditions

### 🏥 Health Hub
- Store and manage health conditions (diabetes, hypertension, etc.)
- Track vital signs and health metrics
- Medical report storage and analysis
- Drug interaction warnings
- Side effects reporting and tracking

### 🏃 Lifestyle Tracker
- Daily water intake logging with goals
- Physical activity tracking with calorie burn calculation
- Exercise logging (walking, running, yoga, etc.)
- Weekly wellness summaries
- Activity history and trends

### 🚨 Emergency Card
- Blood group and allergy information
- Primary and secondary emergency contacts
- Doctor contact details
- Hospital preference
- Special medical instructions
- Shareable access code for emergency responders

### 🌙 Dark Mode
- Full dark/light theme support
- Easy toggle switch in header
- Persisted preference across sessions

---

## ��� Installation

### Prerequisites

- [XAMPP](https://www.apachefriends.org/) (Apache + MySQL + PHP)
- PHP 8.0+
- MySQL 5.7+

### Setup Steps

1. **Clone the repository**
   ```bash
   cd C:\xampp\htdocs
   git clone https://github.com/taslimahmedtamim/MediAssist.git
   ```

2. **Start XAMPP**
   - Open XAMPP Control Panel
   - Start **Apache** and **MySQL**

3. **Create Database**
   - Open [http://localhost/phpmyadmin](http://localhost/phpmyadmin)
   - Create new database: `mediassist`
   - Import `database/schema.sql`

4. **Configure Database**
   - Copy `api/config/database.example.php` to `api/config/database.php`
   - Update credentials if needed:
     ```php
     $servername = "localhost";
     $username = "root";
     $password = "";
     $dbname = "mediassist";
     ```

5. **Run the Application**
   - Open [http://localhost/MediAssist](http://localhost/MediAssist)

---

## ��� Project Structure

```
MediAssist/
├── api/
│   ├── config/         # Database & app configuration
│   ├── endpoints/      # REST API endpoints
│   └── models/         # Business logic classes
├── css/                # Stylesheets
├── database/           # SQL schema
├── js/                 # Frontend JavaScript
├── uploads/            # User uploads
└── index.html          # Main application
```

---

## ��� User Roles

| Role | Description |
|------|-------------|
| **Patient** | Track medications, view diet plans, monitor health |
| **Doctor** | Manage patients, assign medicines & diets, view compliance |

---

## ��� Contributing

1. **Fork** the repository
2. **Clone** your fork
   ```bash
   git clone https://github.com/YOUR_USERNAME/MediAssist.git
   ```
3. **Create** a branch
   ```bash
   git checkout -b feature/your-feature-name
   ```
4. **Make** your changes
5. **Commit** your changes
   ```bash
   git commit -m "Add: your feature description"
   ```
6. **Push** to your branch
   ```bash
   git push origin feature/your-feature-name
   ```
7. **Open** a Pull Request

---

## ⚠️ Note

This app requires **PHP + MySQL** backend. It will **NOT** work on GitHub Pages (static hosting only). Use XAMPP locally or deploy to a PHP hosting service like:
- [InfinityFree](https://infinityfree.net) (Free)
- [000webhost](https://www.000webhost.com) (Free)
- [Hostinger](https://hostinger.com) (Paid)

---

## ��� License

MIT License - see [LICENSE](LICENSE) for details.

---

<p align="center">Made with ❤️ for better healthcare management</p>
