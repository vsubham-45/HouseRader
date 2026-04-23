# 🏠 HouseRader – Full Stack Real Estate Platform  

![PHP](https://img.shields.io/badge/PHP-Backend-blue)
![MySQL](https://img.shields.io/badge/MySQL-Database-orange)
![Status](https://img.shields.io/badge/Status-Active-success)

HouseRader is a full-stack real estate web application built using **PHP, MySQL, HTML, CSS, and JavaScript**.  
It enables users to explore properties, sellers to manage listings, and admins to control and moderate the platform.

---

## 🚀 Features

### 👤 User
- Secure login (OTP + Google OAuth)
- Browse & view property listings
- Real-time messaging with sellers
- Profile management

### 🏢 Seller
- Add, edit, and manage properties
- Handle user inquiries/messages
- Switch between seller & user roles

### 🛡️ Admin
- Admin dashboard
- Approve / reject properties
- Monitor platform activity

### 💬 System Features
- Messaging/inbox system
- Property featuring (mock payment system)
- Image upload handling
- Responsive UI + dark mode

---

## 📸 Screenshots
Admin Dashboard:
<img width="1920" height="1080" alt="image_2026-02-28_10-28-00" src="https://github.com/user-attachments/assets/98f63a46-2a35-4536-9939-a80383550466" />


---

## 🛠️ Tech Stack

- **Frontend:** HTML, CSS, JavaScript  
- **Backend:** PHP  
- **Database:** MySQL  
- **Authentication:** OTP + Google OAuth  
- **Server:** XAMPP  

---

## 📂 Project Structure

```
admin/        → Admin panel  
api/          → Backend APIs  
public/       → UI (user/seller)  
src/          → Core backend logic  
storage/      → Logs  
tools/        → Debug scripts  
database/     → SQL file  
```

---

## ⚙️ Setup Instructions

### 1. Clone Repository

```bash
git clone https://github.com/vsubham-45/HouseRader.git
cd HouseRader
```

---

### 2. Move to XAMPP

```
htdocs/HouseRader
```

---

### 3. Setup Database

- Open phpMyAdmin  
- Create database:

```
houserader
```

- Import:

```
database/houserader_sample.sql
```

---

### 4. Configure Database

Edit:

```
src/db.php
```

```php
$DB_HOST = 'localhost';
$DB_NAME = 'houserader';
$DB_USER = 'root';
$DB_PASS = '';
```

---

### 5. Run the Project

```
http://localhost/HouseRader/public/index.php
```

---

## 🔐 Environment Setup

Create:

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

## 🧪 Demo Login

```
Email: demoacc@gmail.com
Password: 12345678
```

---

## 📌 Why This Project?

- Demonstrates full-stack development skills  
- Includes real-world features (auth, messaging, payments simulation)  
- Shows database design + backend logic  
- Built with a modular and scalable structure  

---

## 📈 Future Improvements

- Deploy to production server  
- Add real payment gateway  
- Improve search & filtering  
- REST API architecture  
- Security enhancements  

---

## 👨‍💻 Author

**Subham Vishwakarma**  
BSc IT Graduate | Aspiring Full Stack Developer  

---

## ⭐ Support

If you like this project, consider giving it a ⭐ on GitHub!
