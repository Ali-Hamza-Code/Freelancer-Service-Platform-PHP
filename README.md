# Freelancer Service Platform (PHP + MySQL)

## Description

This project is a Freelancer Service Platform built using PHP, HTML, CSS, and MySQL.

The system allows freelancers to create and manage services, and clients to browse available services, place orders, upload files, and request modifications.

The platform simulates a small freelance marketplace similar to Fiverr, where users can interact, manage services, and complete service requests.

---

## Main Features

### User Authentication
- User registration system
- Login and logout functionality
- Profile management system

### Services Management
- Add new services
- View available services
- Service details page
- Upload service images
- Manage services

### Shopping Cart System
- Add services to cart
- Remove services from cart
- View cart contents

### Checkout System
- Confirm service orders
- Select delivery details
- Complete service request

### Orders Management
- Place service orders
- View order history
- Track order status

### File Upload System
- Upload images and files
- Store uploaded files securely
- Attach files to services or orders

### Database Integration
- MySQL database support
- Multiple tables for:
  - Users
  - Services
  - Orders
  - Cart
  - Uploads

---

## Project Structure
```
Web-Project/
│
├── auth/ (login and register system)
├── cart/ (cart management)
├── checkout/ (checkout logic)
├── css/ (CSS styling files)
├── includes/ (shared PHP components)
├── orders/ (order management pages)
├── services/ (service management pages)
├── uploads/ (uploaded files storage)
│
├── index.php (homepage)
├── profile.php (user profile page)
├── config.php (configuration settings)
├── db.php.inc (database connection file)
├── Service.php (service logic file)
│
├── dbschema_yourNumber.sql (database schema file)
```
---

## Technologies Used

- HTML
- CSS
- PHP
- MySQL
- phpMyAdmin
- XAMPP (Local Server)

---

## Database Setup

The database file is included in the project:
dbschema_yourNumber.sql
To run the project:

1. Open phpMyAdmin
2. Create a new database
3. Import the SQL file:
   dbschema_yourNumber.sql

---

## How to Run the Project

1. Install XAMPP
2. Copy the project folder into:
htdocs/
Example:
C:\xampp\htdocs\Web Project
3. Start Apache and MySQL from XAMPP

4. Open phpMyAdmin

5. Import the database file:
dbschema_yourNumber.sql
6. Open the browser and go to:
http://localhost/Web
 Project/
---

## Future Improvements

- Add payment gateway integration
- Add rating and review system
- Improve UI design
- Add messaging system between users

---

## Author

Ali Hamza
