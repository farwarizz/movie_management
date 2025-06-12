Movie Management System
A comprehensive, admin-focused web application for managing a movie database, including cinema showtimes, streaming service availability, and user bookings. Built with PHP and a MySQL database, running on a WAMP server.

Key Features
Complete Admin Dashboard: A secure and centralized panel for all management tasks.

Movie Management: Full CRUD (Create, Read, Update, Delete) functionality for movie listings, including details like genre, release date, duration, and rating.

Cinema Management: Manage cinema details, locations, and screen types (e.g., Standard, 3D, IMAX).

Streaming Platform Management: Add or update streaming platforms like Netflix, Prime Video, etc., and link them to available movies with specific pricing for different resolutions (720p, 1080p, 4K).

User & Role Management: View all registered users, add new users, and dynamically change user roles between "Admin" and "Viewer". Admins are protected from self-deletion or role changes.

Booking Overview: A dedicated view to monitor all cinema bookings made by users, showing detailed information from across the database.

Dynamic UI: The interface uses JavaScript to provide a smoother user experience with dynamic modals for editing records without page reloads.

Technology Stack
Backend: PHP

Database: MySQL

Frontend: HTML, CSS, JavaScript

UI Framework: Tailwind CSS

Local Server: WAMP (Windows, Apache, MySQL, PHP)

Setup and Installation
Follow these steps to get the project running on your local machine.

Prerequisites:

WAMP Server installed and running.

The movie_management.sql file from this repository.

1. Clone the Repository
Clone this repository into the www directory of your WAMP installation.

git clone https://github.com/farwarizz/movie_management.git C:\wamp64\www\movie_management

2. Create the Database

Start your WAMP server.

Open a web browser and navigate to http://localhost/phpmyadmin.

Click on the Databases tab.

In the "Create database" field, enter movie_management and click Create.

3. Import the SQL File

Select the movie_management database from the list on the left.

Click on the Import tab.

Click "Choose File" and select the movie_management.sql file from this project's folder.

Scroll down and click Go to import the tables and data.

4. Configure the Database Connection

In the project folder, locate the db_connect.php file.

Ensure the database connection variables match your setup (the defaults below should work for a standard WAMP installation):

<?php
$servername = "localhost";
$username = "root"; // Your MySQL username (default is root)
$password = ""; // Your MySQL password (default is empty)
$dbname = "movie_management"; // The name of the database

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>

5. Run the Application

You can now access the project by navigating to http://localhost/movie_management/ in your web browser.

Project Team
Hamza - Database & Backend Development

Farwa - Backend Development & Presentation

Ayesha - Frontend Development & Documentation
