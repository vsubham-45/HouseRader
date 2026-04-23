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

## 🎥 Demo Video

👉 Watch full working demo here:  https://drive.google.com/drive/folders/1f2pkF6ZGHjL5Kqkz-lmGT0fu5-Tc9pMG?usp=sharing

⚠️ Note: Google OAuth requires proper domain configuration.  
For testing all features, please use the demo credentials provided below.
---

## 📸 Screenshots
Homepage:
<img width="1920" height="1080" alt="image_2026-02-28_10-29-34" src="https://github.com/user-attachments/assets/596ab3b5-5829-416a-9766-5cdf891b3553" />

Properties details page:
<img width="1920" height="1080" alt="image_2026-02-28_10-32-07 (2)" src="https://github.com/user-attachments/assets/ef3edc69-35b1-43df-8252-37b75a47c14e" />

Inbox:
<img width="1920" height="1080" alt="image_2026-02-28_10-42-30 (2)" src="https://github.com/user-attachments/assets/d0d1d937-d8e2-4b3f-9c7f-f0b0cd4f5443" />

Seller Dashboard:
<img width="1920" height="1080" alt="image_2026-02-28_10-35-19" src="https://github.com/user-attachments/assets/f11e2549-41cf-4c81-8a2b-004d54f31deb" />

Featuring page:
<img width="1920" height="1080" alt="image_2026-02-28_10-38-53" src="https://github.com/user-attachments/assets/65b5dad4-c93b-4f6c-8d2b-0a03bdf8e14d" />

Admin Dashboard:
<img width="1920" height="1080" alt="image_2026-02-28_10-29-02 (2)" src="https://github.com/user-attachments/assets/90d5b11f-5fb1-4130-92c8-321692241fbc" />
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
