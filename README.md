# Shiv Agro Stock Management System

A comprehensive stock management system for agricon shops with features for managing products, generating bills, and tracking inventory.

## Features

- Product Management
  - Add, edit, and delete products
  - Track product quantities and prices
  - Set low stock alerts
  - Categorize products
  - Manage product sizes/weights

- Company Management
  - Add and manage product companies
  - View company-wise product listings
  - Track company-wise stock levels

- Customer Management
  - Add and manage customers
  - Track customer purchase history
  - View customer payment status

- Billing System
  - Generate bills for sales
  - Support for multiple payment methods
  - Track partial and full payments
  - Print bills
  - View bill history

- Stock Management
  - Real-time stock tracking
  - Low stock alerts
  - Stock value calculation
  - Stock movement history

- Reports
  - Sales reports
  - Stock reports
  - Payment reports
  - Customer reports

## Installation

1. Clone the repository
2. Import the database schema from `database/schema.sql`
3. Configure database connection in `config/database.php`
4. Start your web server
5. Access the application through your web browser

## Usage

1. Login with default credentials:
   - Username: admin
   - Password: admin123

2. Add your first company and products
3. Start managing your inventory and sales

## Security Features

- User authentication
- Role-based access control
- Secure password storage
- Input validation and sanitization
- SQL injection prevention

## Technical Details

- Built with PHP and MySQL
- Uses Bootstrap for responsive design
- Implements AJAX for dynamic updates
- Follows MVC architecture
- Uses prepared statements for database queries

## Contributing

1. Fork the repository
2. Create your feature branch
3. Commit your changes
4. Push to the branch
5. Create a new Pull Request 