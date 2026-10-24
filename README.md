# LipPoint (library Management System) 📚🏫

A modern, comprehensive web application designed to streamline library operations, digital catalog management, book lending, and member administration for educational institutions.

---

## 📋 Table of Contents

- [Overview](#-overview)
- [Key Features](#-key-features)
  - [Student & Member Portal](#student--member-portal)
  - [Librarian & Admin Dashboard](#librarian--admin-dashboard)
  - [Core Library Operations](#core-library-operations)
- [System Architecture & Tech Stack](#-system-architecture--tech-stack)
- [Database Schema](#-database-schema)
- [Installation & Setup](#-installation--setup)
  - [Prerequisites](#prerequisites)
  - [Step-by-Step Installation](#step-by-step-installation)
- [Configuration (.env)](#-configuration-env)
- [API Documentation](#-api-documentation)
- [User Roles & Permissions](#-user-roles--permissions)
- [Future Enhancements](#-future-enhancements)
- [Contributing](#-contributing)
- [License](#-license)

---

## 🌟 Overview

The **School Library Management System** bridges the gap between traditional library resource tracking and modern digital administration. It automates repetitive administrative tasks such as tracking borrowed books, calculating fine amounts, managing inventory, and sending automated due-date reminders.

Whether operating in a primary school, high school, or university, this system improves resource accessibility for students while empowering librarians with powerful management tools.

---

## 🚀 Key Features

### 👨‍🎓 Student & Member Portal
* **Catalog Search & Filtering:** Search books by title, author, ISBN, category, or availability status.
* **Borrowing History:** View current active loans, past reading history, and outstanding fines.
* **Book Reservations:** Reserve books online that are currently out on loan.
* **Wishlist & Favorites:** Save books for future reading or research.

### 🧙‍♂️ Librarian & Admin Dashboard
* **Resource Management (CRUD):** Add, edit, archive, or remove books, ebooks, and physical media items.
* **Barcode / QR Code Scanner Support:** Fast check-in and check-out process via physical hardware or web camera interface.
* **Automated Fine Management:** Auto-calculates overdue penalties based on customizable daily rates.
* **Analytics & Reporting:** Generate PDF/CSV reports for popular books, overdue statistics, total fine collection, and inventory audits.
* **Member Management:** Manage student, teacher, and staff accounts, including issuing digital library cards.

### ⚙️ Core Library Operations
* **Circulation Rules Engine:** Customizable borrowing limits based on user role (e.g., Teachers: 10 books for 30 days; Students: 3 books for 14 days).
* **Automated Notifications:** Email/SMS notifications for upcoming due dates, overdue items, and available reservations.

---

## 🛠 System Architecture & Tech Stack

This project is built using modern full-stack web architecture:

| Layer | Technology Options |
| :--- | :--- |
| **Frontend** | React.js / Next.js / Vue.js, Tailwind CSS |
| **Backend** | Node.js (Express) / Python (Django/FastAPI) / Java (Spring Boot) |
| **Database** | PostgreSQL / MySQL / MongoDB |
| **ORM / Query Builder** | Prisma / Sequelize / SQLAlchemy |
| **Authentication** | JWT (JSON Web Tokens) / OAuth 2.0 / NextAuth |
| **File Storage** | AWS S3 / Cloudinary (for book covers & digital PDFs) |

---

## 🗄 Database Schema Overview

```
 [Users] 1 --- * [Loans] * --- 1 [Books]
    |               |
    |               | --- 1 [Fines]
    *
 [Reservations] * --- 1 [Books]
```

### Core Entities
1. **Users:** `id`, `name`, `email`, `password_hash`, `role` (Admin/Librarian/Student/Teacher), `grade_level`, `status`.
2. **Books:** `id`, `isbn`, `title`, `author`, `category_id`, `publisher`, `total_copies`, `available_copies`, `cover_image_url`.
3. **Loans:** `id`, `user_id`, `book_id`, `issue_date`, `due_date`, `return_date`, `status` (Borrowed/Returned/Overdue).
4. **Fines:** `id`, `loan_id`, `amount`, `status` (Paid/Unpaid), `issued_date`.
5. **Reservations:** `id`, `user_id`, `book_id`, `reservation_date`, `status` (Pending/Fulfilled/Cancelled).

---

## 💻 Installation & Setup

### Prerequisites
Make sure you have the following installed on your local machine:
* **Node.js** (v18.0.0 or higher) or runtime of choice
* **Git**
* **PostgreSQL / MySQL** database server

### Step-by-Step Installation

1. **Clone the Repository:**
   ```bash
   git clone https://github.com/your-username/school-library-management-system.git
   cd school-library-management-system
   ```

2. **Install Dependencies:**
   ```bash
   # Install backend dependencies
   cd backend
   npm install

   # Install frontend dependencies
   cd ../frontend
   npm install
   ```

3. **Configure Environment Variables:**
   Copy the `.env.example` file to `.env` in both `frontend` and `backend` directories and update the variables (see below).

4. **Database Setup & Migrations:**
   ```bash
   cd backend
   npm run db:migrate
   npm run db:seed
   ```

5. **Run the Application:**
   ```bash
   # Start backend API server
   cd backend
   npm run dev

   # In a new terminal, start the frontend development server
   cd frontend
   npm run dev
   ```

6. Open your browser and navigate to `http://localhost:3000`.

---

## ⚙️ Configuration (.env)

Example `.env` configuration file:

```env
# Server Config
PORT=5000
NODE_ENV=development

# Database Configuration
DATABASE_URL="postgresql://username:password@localhost:5432/library_db?schema=public"

# Authentication
JWT_SECRET="your_super_secret_jwt_key_here"
JWT_EXPIRES_IN="7d"

# Email Notifications (SMTP)
SMTP_HOST="smtp.mailtrap.io"
SMTP_PORT=2525
SMTP_USER="your_smtp_user"
SMTP_PASS="your_smtp_password"
FROM_EMAIL="noreply@schoollibrary.edu"
```

---

## 📡 API Documentation

### Auth Routes
* `POST /api/v1/auth/register` - Register a new user
* `POST /api/v1/auth/login` - Authenticate user & receive token

### Book Routes
* `GET /api/v1/books` - Get list of books (supports pagination & query filters)
* `GET /api/v1/books/:id` - Get specific book details
* `POST /api/v1/books` - Add a new book (Librarian/Admin only)
* `PUT /api/v1/books/:id` - Update book info (Librarian/Admin only)
* `DELETE /api/v1/books/:id` - Remove a book (Admin only)

### Circulation Routes
* `POST /api/v1/loans/issue` - Issue a book to a user
* `POST /api/v1/loans/return` - Return an issued book
* `GET /api/v1/loans/user/:userId` - Get loan history for a specific user

---

## 👥 User Roles & Permissions

| Feature | Student | Teacher | Librarian | Admin |
| :--- | :---: | :---: | :---: | :---: |
| Search & View Books | ✅ | ✅ | ✅ | ✅ |
| Reserve Books | ✅ | ✅ | ✅ | ✅ |
| Issue / Return Books | ❌ | ❌ | ✅ | ✅ |
| Manage Catalog | ❌ | ❌ | ✅ | ✅ |
| Manage User Accounts | ❌ | ❌ | ❌ | ✅ |
| Export Reports | ❌ | ❌ | ✅ | ✅ |

---

## 🔮 Future Enhancements

- [ ] Integration with Open Library API for automated metadata fetching.
- [ ] In-app e-Reader module for digital PDFs/eBooks.
- [ ] Push notifications via Progressive Web App (PWA) support.
- [ ] Self-checkout station kiosk mode using RFID tags.

---

## 🤝 Contributing

Contributions are welcome! Please follow these steps:

1. Fork the Project repository.
2. Create your Feature Branch (`git checkout -b feature/AmazingFeature`).
3. Commit your Changes (`git commit -m 'Add some AmazingFeature'`).
4. Push to the Branch (`git checkout -origin feature/AmazingFeature`).
5. Open a Pull Request.

---

## 📄 License

Distributed under the MIT License. See `LICENSE` for more information.
