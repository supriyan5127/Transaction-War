# SECURE WEB APPLICATION SPA (Phase 1)

This project contains a full-stack, secure web application meeting Phase 1 criteria for the War Game assessment.

## Features
- **Frontend SPA**: A Single-Page Application using only HTML, CSS, JavaScript without any third-party framework wrappers.
- **Backend API**: PHP backend connecting to a Dockerized MySQL database. Outputting JSON.
- **Secure Authentication**: Utilizing `password_hash()` (bcrypt) for storing user passwords safely.
- **Transactional Integrity**: Uses MySQL InnoDB and `beginTransaction`/`commit` commands to prevent race conditions or negative balances during money transfers.
- **Dynamic Routing**: Uses JS hash changes for high-speed responsiveness.

## How to Run (Phase 1 Deliverable)

### 1. Requirements
Ensure you have **Docker** and **Docker Compose** installed on your machine.
No local PHP or MySQL installation is required on the host system.

### 2. Setup Database and Web Server
Open a terminal in the project directory (`C:\transaction` or where you unzipped the project) and run:
`docker-compose up -d --build`

This command will:
1. Build the PHP 8.2 Apache backend.
2. Pull and start a **MySQL 8.0** database container.
3. Automatically network them together.

*Wait a moment for the database container to fully start up and become "healthy".*

### 3. Run the Database Seed Script
Before logging in, you must create the database schema and initialize the starting users. The Docker container exposes the application on port `8000`.

Visit the following URL in your browser:
**[http://localhost:8000/seed.php](http://localhost:8000/seed.php)**

If successful, it will return a JSON success message: `{"status":"success","message":"Schema created successfully."}`

### 4. Open the Application
Navigate to the root URL to start using the Single-Page Application:
**[http://localhost:8000/](http://localhost:8000/)**

### Default Accounts
The following users are automatically seeded with Rs. 100:
- Username: `alice` | Password: `password123`
- Username: `bob` | Password: `password123`
