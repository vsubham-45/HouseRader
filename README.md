# 🏠 HouseRader – Full Stack Real Estate Platform

HouseRader is a full-stack real estate web application built using PHP, MySQL, HTML, CSS, and JavaScript.
It allows users to browse properties, sellers to manage listings, and admins to control and moderate the platform.

---

## 🚀 Features

### 👤 User Features

* User registration & login (OTP + Google OAuth)
* Browse and view property listings
* Messaging system (user ↔ seller)
* Profile management

### 🏢 Seller Features

* Add, edit, and manage property listings
* View inquiries/messages from users
* Switch between seller and user roles

### 🛡️ Admin Features

* Admin login & dashboard
* Approve / reject property listings
* Monitor platform activity
* Manage users and properties

### 💬 Additional Features

* Real-time messaging/inbox system
* Property featuring system (mock payment gateway)
* Image upload & handling
* Responsive UI with dark mode support

---

## 🛠️ Tech Stack

* **Frontend:** HTML, CSS, JavaScript
* **Backend:** PHP
* **Database:** MySQL
* **Authentication:** OTP + Google OAuth
* **Server:** XAMPP (Local)

---

## 📂 Project Structure

```
admin/        → Admin panel & controls  
api/          → Backend APIs (OTP, messages, auth, etc.)  
public/       → Main application (user/seller UI)  
src/          → Core backend logic (DB, sessions)  
storage/      → Logs  
tools/        → Debug & maintenance scripts  
```

---

## ⚙️ Setup Instructions

### 1. Clone the repository

```
git clone https://github.com/vsubham-45/HouseRader.git
cd HouseRader
```

### 2. Move to XAMPP

Place the project inside:

```
htdocs/HouseRader
```

### 3. Setup Database

* Open phpMyAdmin
* Create a database:

```
houserader
```

* Import the provided `.sql` file

---

### 4. Configure Database Connection

Edit:

```
src/db.php
```

Set your credentials:

```php
$DB_HOST = 'localhost';
$DB_NAME = 'houserader';
$DB_USER = 'root';
$DB_PASS = '';
```

---

### 5. Run the Project

Open in browser:

```
http://localhost/HouseRader/public/index.php
```

---

## 🔐 Environment Setup (Important)

Create a file:

```
src/config.php
```

Add:

```php
<?php
define('GOOGLE_CLIENT_ID', 'your_client_id');
define('GOOGLE_CLIENT_SECRET', 'your_client_secret');
```

⚠️ This file is not included in the repository for security reasons.

---

## 🧪 Demo Login (For Testing)

Use this demo account:

* **Email:** [demoacc@gmail.com](mailto:demoacc@gmail.com)
* **Password:** 12345678

---

## 📌 Highlights

* Multi-role authentication system (User / Seller / Admin)
* Full backend logic with structured PHP
* Real-world features like messaging, OTP, payments simulation
* Clean database design with relationships
* Responsive UI with dark mode

---

## 📈 Future Improvements

* Deploy to live server (Hostinger / VPS)
* Add real payment gateway integration
* Improve search & filtering system
* Add REST API structure
* Implement security enhancements (rate limiting, validation layers)

---

## 👨‍💻 Author

**Subham Vishwakarma**
BSc IT Graduate | Aspiring Full Stack Developer

---

## ⭐ Support

If you like this project, consider giving it a ⭐ on GitHub!
