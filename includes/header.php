<?php
/**
 * Header Component
 * Includes logo, search bar, and authentication controls
 */

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get cart count for clients
$cartCount = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cartCount = count($_SESSION['cart']);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>Freelance Marketplace
    </title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>css/styles.css">
</head>

<body>
    <div class="page-wrapper">
        <header class="header">
            <!-- Logo/Brand -->
            <div class="header-logo">
                <a href="<?php echo SITE_URL; ?>services/browse-services.php">Freelance Marketplace</a>
            </div>

            <!-- Search Bar -->
            <div class="header-search">
                <form action="<?php echo SITE_URL; ?>services/browse-services.php" method="GET">
                    <input type="text" name="search" class="form-input" placeholder="Search services..."
                        value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    <button type="submit" class="btn btn-primary">Search</button>
                </form>
            </div>

            <!-- Authentication Controls -->
            <div class="header-auth">
                <?php if (isLoggedIn()): ?>
                    <!-- Logged-in User -->
                    <?php
                    $userRole = getCurrentUserRole();
                    $cardClass = $userRole === 'Client' ? 'user-card-client' : 'user-card-freelancer';
                    $profilePhoto = isset($_SESSION['profile_photo']) && $_SESSION['profile_photo']
                        ? $_SESSION['profile_photo']
                        : SITE_URL . 'uploads/profiles/default.png';
                    ?>

                    <?php if ($userRole === 'Client'): ?>
                        <!-- Shopping Cart Icon (Clients Only) -->
                        <a href="<?php echo SITE_URL; ?>cart/cart.php" class="cart-icon">
                            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="9" cy="21" r="1"></circle>
                                <circle cx="20" cy="21" r="1"></circle>
                                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                            </svg>
                            <?php if ($cartCount > 0): ?>
                                <span class="cart-badge">
                                    <?php echo $cartCount; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>

                    <!-- User Profile Card -->
                    <a href="<?php echo SITE_URL; ?>profile.php" class="user-card <?php echo $cardClass; ?>">
                        <img src="<?php echo htmlspecialchars($profilePhoto); ?>" alt="Profile"
                            onerror="this.src='<?php echo SITE_URL; ?>uploads/profiles/default.png'">
                        <span>
                            <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>
                        </span>
                    </a>

                    <!-- Logout Link -->
                    <a href="<?php echo SITE_URL; ?>auth/logout.php" class="btn btn-secondary btn-sm">Logout</a>

                <?php else: ?>
                    <!-- Guest User -->
                    <a href="<?php echo SITE_URL; ?>auth/login.php" class="btn btn-primary">Login</a>
                    <a href="<?php echo SITE_URL; ?>auth/register.php" class="btn btn-secondary">Sign Up</a>
                <?php endif; ?>
            </div>
        </header>

        <div class="content-wrapper">