<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/agri/assets/css/style.css" rel="stylesheet">
    <style>
        .navbar {
            background: #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border-bottom: 1px solid #eaeaea;
            padding: 0.4rem 0;
        }
        .navbar-brand {
            display: flex;
            align-items: center;
            font-size: 1.45rem;
            font-weight: 800;
            color: #222;
            letter-spacing: 0.5px;
            gap: 0.5rem;
            text-decoration: none;
        }
        .navbar-brand i {
            color: #1976d2;
            font-size: 1.7rem;
            margin-right: 0.35rem;
        }
        .navbar-nav {
            align-items: center;
        }
        .nav-link {
            color: #444;
            font-weight: 500;
            padding: 0.45rem 1.1rem;
            border-radius: 6px;
            margin: 0 0.1rem;
            transition: background 0.18s, color 0.18s;
        }
        .nav-link i {
            margin-right: 0.4rem;
            font-size: 1rem;
        }
        .nav-link:hover {
            background: #e3f0fb;
            color: #1976d2;
        }
        .nav-link.active {
            background: #1976d2;
            color: #fff !important;
            font-weight: 700;
        }
        .nav-link.active i {
            color: #fff !important;
        }
        .navbar-toggler {
            border: none;
            padding: 0.4rem;
        }
        .navbar-toggler:focus {
            box-shadow: none;
        }
        @media (max-width: 991.98px) {
            .navbar-collapse {
                background: #fff;
                padding: 0.7rem;
                border-radius: 8px;
                margin-top: 0.5rem;
                box-shadow: 0 4px 15px rgba(0,0,0,0.07);
            }
            .nav-link {
                padding: 0.7rem 1rem;
                margin: 0.2rem 0;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="/agri/index.php">
                <i class="bi bi-award"></i>
                Shiv Agro
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" 
                           href="/agri/index.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'companies.php' ? 'active' : ''; ?>" 
                           href="/agri/dashboard/companies.php">
                            <i class="bi bi-building"></i> Companies
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'sell.php' ? 'active' : ''; ?>" 
                           href="/agri/dashboard/sell.php">
                            <i class="bi bi-cart-plus"></i> Sell
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'customers.php' ? 'active' : ''; ?>" 
                           href="/agri/dashboard/customers.php">
                            <i class="bi bi-people"></i> Customers
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'bills.php' ? 'active' : ''; ?>" 
                           href="/agri/dashboard/bills.php">
                            <i class="bi bi-receipt"></i> Bills
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'company_sales.php' ? 'active' : ''; ?>" 
                           href="/agri/dashboard/company_sales.php">
                            <i class="bi bi-building"></i> Company Sales
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/agri/auth/logout.php">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
</body>
</html> 