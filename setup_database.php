<?php
// Database configuration
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'shiv_agro';

// Create connection without database
$conn = new mysqli($host, $user, $pass);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set character set
$conn->set_charset("utf8mb4");

echo "Connected successfully to MySQL server.\n";

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn->query($sql) === TRUE) {
    echo "Database created successfully or already exists.\n";
} else {
    die("Error creating database: " . $conn->error);
}

// Select the database
$conn->select_db($dbname);

// Disable foreign key checks
$conn->query("SET FOREIGN_KEY_CHECKS = 0");

// Drop tables in correct order (respecting foreign key constraints)
$tables = [
    'payments',    // Drop first as it references bills and customers
    'bill_items',  // Drop second as it references both bills and products
    'bills',       // Drop third as it's referenced by bill_items and payments
    'products',    // Drop fourth as it references categories and companies
    'categories',  // Drop fifth as it's referenced by products
    'companies',   // Drop sixth as it's referenced by products
    'customers',   // Drop seventh as it's referenced by bills and payments
    'users'        // Drop last as it's referenced by bills and payments
];

foreach ($tables as $table) {
    $sql = "DROP TABLE IF EXISTS $table";
    if ($conn->query($sql) === TRUE) {
        echo "Table $table dropped successfully.\n";
    } else {
        die("Error dropping table $table: " . $conn->error);
    }
}

// Re-enable foreign key checks
$conn->query("SET FOREIGN_KEY_CHECKS = 1");

// Create users table
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql) === TRUE) {
    echo "Users table created successfully.\n";
} else {
    die("Error creating users table: " . $conn->error);
}

// Create categories table
$sql = "CREATE TABLE IF NOT EXISTS categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql) === TRUE) {
    echo "Categories table created successfully.\n";
} else {
    die("Error creating categories table: " . $conn->error);
}

// Create companies table
$sql = "CREATE TABLE IF NOT EXISTS companies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql) === TRUE) {
    echo "Companies table created successfully.\n";
} else {
    die("Error creating companies table: " . $conn->error);
}

// Create customers table
$sql = "CREATE TABLE IF NOT EXISTS customers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(15),
    village VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_phone (phone),
    INDEX idx_village (village)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql) === TRUE) {
    echo "Customers table created successfully.\n";
} else {
    die("Error creating customers table: " . $conn->error);
}

// Create products table
$sql = "CREATE TABLE IF NOT EXISTS products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    category_id INT,
    quantity INT NOT NULL DEFAULT 0,
    price_per_unit DECIMAL(10,2) NOT NULL,
    low_stock_threshold INT DEFAULT 10,
    company VARCHAR(255) NOT NULL,
    size VARCHAR(50) DEFAULT NULL COMMENT 'Size/Weight of the product',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (company) REFERENCES companies(name) ON DELETE CASCADE,
    INDEX idx_name (name),
    INDEX idx_company (company),
    INDEX idx_category (category_id),
    INDEX idx_quantity (quantity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql) === TRUE) {
    echo "Products table created successfully.\n";
} else {
    die("Error creating products table: " . $conn->error);
}

// Create bills table
$sql = "CREATE TABLE IF NOT EXISTS bills (
    id INT PRIMARY KEY AUTO_INCREMENT,
    bill_number VARCHAR(20) UNIQUE NOT NULL,
    customer_id INT,
    created_by INT,
    subtotal DECIMAL(10,2) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_status ENUM('paid', 'partial', 'unpaid') DEFAULT 'unpaid',
    amount_paid DECIMAL(10,2) DEFAULT 0.00,
    amount_due DECIMAL(10,2) DEFAULT 0.00,
    discount_amount DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_bill_number (bill_number),
    INDEX idx_customer (customer_id),
    INDEX idx_payment_status (payment_status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql) === TRUE) {
    echo "Bills table created successfully.\n";
} else {
    die("Error creating bills table: " . $conn->error);
}

// Create payments table
$sql = "CREATE TABLE IF NOT EXISTS payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    bill_id INT NOT NULL,
    customer_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    payment_method ENUM('cash', 'bank_transfer', 'upi') DEFAULT 'cash',
    notes TEXT,
    created_by INT NOT NULL,
    FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_bill (bill_id),
    INDEX idx_customer (customer_id),
    INDEX idx_payment_date (payment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql) === TRUE) {
    echo "Payments table created successfully.\n";
} else {
    die("Error creating payments table: " . $conn->error);
}

// Create bill_items table
$sql = "CREATE TABLE IF NOT EXISTS bill_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    bill_id INT,
    product_id INT,
    quantity DECIMAL(10,2) NOT NULL,
    price_per_unit DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_bill (bill_id),
    INDEX idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql) === TRUE) {
    echo "Bill items table created successfully.\n";
} else {
    die("Error creating bill items table: " . $conn->error);
}

// Insert default admin user
$username = 'admin';
$password = password_hash('admin123', PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT IGNORE INTO users (username, password) VALUES (?, ?)");
$stmt->bind_param("ss", $username, $password);

if ($stmt->execute()) {
    echo "Default admin user created successfully.\n";
} else {
    echo "Error creating default admin user: " . $stmt->error . "\n";
}

// Insert default categories
$categories = [
    'Fertilizers',
    'Pesticides',
    'Seeds',
    'Tools',
    'Equipment',
    'Other'
];

$stmt = $conn->prepare("INSERT IGNORE INTO categories (name) VALUES (?)");
foreach ($categories as $category) {
    $stmt->bind_param("s", $category);
    if (!$stmt->execute()) {
        echo "Error inserting category {$category}: " . $stmt->error . "\n";
    }
}
echo "Default categories created successfully.\n";

// Close connection
$conn->close();
echo "Database setup completed successfully.\n";
?> 