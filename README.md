# Ìø• MediAssist+

**Smart Healthcare Companion** - A web application for managing medications, tracking health, and facilitating doctor-patient communication.

![PHP](https://img.shields.io/badge/PHP-8.0+-purple.svg)
![MySQL](https://img.shields.io/badge/MySQL-5.7+-orange.svg)
![License](https://img.shields.io/badge/license-MIT-green.svg)

---

## ‚ú® Features

- **Medicine Reminders** - Smart notifications for medication schedules
- **Pill Tracker** - Track daily medication adherence with visual charts
- **Doctor Dashboard** - Manage patients, assign medicines & diet plans
- **Diet Planner** - Personalized diet plans based on health conditions
- **Health Hub** - Monitor health conditions and vitals
- **Lifestyle Tracker** - Track water intake and physical activities
- **Emergency Card** - Store emergency contacts and medical info
- **Dark Mode** - Full dark/light theme support

---

## Ì∫Ä Installation

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

## Ì≥Å Project Structure

```
MediAssist/
‚îú‚îÄ‚îÄ api/
‚îÇ   ‚îú‚îÄ‚îÄ config/         # Database & app configuration
‚îÇ   ‚îú‚îÄ‚îÄ endpoints/      # REST API endpoints
‚îÇ   ‚îî‚îÄ‚îÄ models/         # Business logic classes
‚îú‚îÄ‚îÄ css/                # Stylesheets
‚îú‚îÄ‚îÄ database/           # SQL schema
‚îú‚îÄ‚îÄ js/                 # Frontend JavaScript
‚îú‚îÄ‚îÄ uploads/            # User uploads
‚îî‚îÄ‚îÄ index.html          # Main application
```

---

## Ì±• User Roles

| Role | Description |
|------|-------------|
| **Patient** | Track medications, view diet plans, monitor health |
| **Doctor** | Manage patients, assign medicines & diets, view compliance |

---

## Ì¥ù Contributing

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

## ‚ö†Ô∏è Note

This app requires **PHP + MySQL** backend. It will **NOT** work on GitHub Pages (static hosting only). Use XAMPP locally or deploy to a PHP hosting service like:
- [InfinityFree](https://infinityfree.net) (Free)
- [000webhost](https://www.000webhost.com) (Free)
- [Hostinger](https://hostinger.com) (Paid)

---

## Ì≥Ñ License

MIT License - see [LICENSE](LICENSE) for details.

---

<p align="center">Made with ‚ù§Ô∏è for better healthcare management</p>
