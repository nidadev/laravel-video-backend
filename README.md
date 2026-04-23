# 🎥 Laravel Video Backend API

A scalable backend API system built with Laravel for managing video content. This project demonstrates secure authentication, file handling, and RESTful API design suitable for integration with mobile apps or frontend frameworks like Vue or React.

---

## 🚀 Features

- User Authentication (Laravel Sanctum / JWT)
- Role-based Access Control (Admin/User)
- Video Upload & Management System
- RESTful API Architecture
- Secure File Storage Handling
- Video Listing, Detail View & Deletion
- Clean MVC Structure (Laravel Best Practices)
- API-ready for frontend/mobile integration

---

## 🛠 Tech Stack

- Laravel (PHP Framework)
- MySQL
- Laravel Sanctum / JWT Authentication
- REST API
- File Storage (Local / Cloud-ready)
- Postman (API Testing)

---

## 🔐 Authentication

This project uses **token-based authentication**.

After login, a token is generated and must be sent in all protected API requests:


---

## 📡 API Endpoints

### 🔑 Authentication
- POST /api/register → Register new user  
- POST /api/login → User login  
- POST /api/logout → Logout user  

---

### 🎥 Videos

- GET /api/videos → Get all videos  
- POST /api/videos → Upload new video  
- GET /api/videos/{id} → Get single video details  
- DELETE /api/videos/{id} → Delete video  

---



