<?php
/**
 * Navigation Component
 * Role-based navigation menu
 */

// Determine current page for active state
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

// Function to check if link is active
function isActive($pages)
{
    global $currentPage, $currentDir;
    if (is_array($pages)) {
        return in_array($currentPage, $pages) || in_array($currentDir, $pages);
    }
    return $currentPage === $pages || $currentDir === $pages;
}
?>
<nav class="navigation">
    <ul class="nav-list">
        <!-- Home / Browse Services - All Users -->
        <li class="nav-item">
            <a href="<?php echo SITE_URL; ?>services/browse-services.php"
                class="nav-link <?php echo isActive(['browse-services.php', 'index.php']) ? 'nav-link-active' : ''; ?>">
                Home
            </a>
        </li>
        <li class="nav-item">
            <a href="<?php echo SITE_URL; ?>services/browse-services.php"
                class="nav-link <?php echo isActive('browse-services.php') ? 'nav-link-active' : ''; ?>">
                Browse Services
            </a>
        </li>

        <?php if (!isLoggedIn()): ?>
            <!-- Guest Navigation -->
            <li class="nav-item">
                <a href="<?php echo SITE_URL; ?>auth/login.php"
                    class="nav-link <?php echo isActive('login.php') ? 'nav-link-active' : ''; ?>">
                    Login
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo SITE_URL; ?>auth/register.php"
                    class="nav-link <?php echo isActive('register.php') ? 'nav-link-active' : ''; ?>">
                    Sign Up
                </a>
            </li>

        <?php elseif (isClient()): ?>
            <!-- Client Navigation -->
            <li class="nav-item">
                <a href="<?php echo SITE_URL; ?>cart/cart.php"
                    class="nav-link nav-link-client <?php echo isActive('cart.php') ? 'nav-link-active' : ''; ?>">
                    Shopping Cart
                    <?php if (isset($_SESSION['cart']) && count($_SESSION['cart']) > 0): ?>
                        <span class="badge badge-warning">
                            <?php echo count($_SESSION['cart']); ?>
                        </span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo SITE_URL; ?>orders/my-orders.php"
                    class="nav-link nav-link-client <?php echo isActive(['my-orders.php', 'order-details.php', 'orders']) ? 'nav-link-active' : ''; ?>">
                    My Orders
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo SITE_URL; ?>profile.php"
                    class="nav-link nav-link-client <?php echo isActive('profile.php') ? 'nav-link-active' : ''; ?>">
                    My Profile
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo SITE_URL; ?>auth/logout.php" class="nav-link">
                    Logout
                </a>
            </li>

        <?php elseif (isFreelancer()): ?>
            <!-- Freelancer Navigation -->
            <li class="nav-item">
                <a href="<?php echo SITE_URL; ?>services/my-services.php"
                    class="nav-link nav-link-freelancer <?php echo isActive(['my-services.php', 'create-service.php', 'edit-service.php']) ? 'nav-link-active' : ''; ?>">
                    My Services
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo SITE_URL; ?>services/create-service.php"
                    class="nav-link nav-link-freelancer <?php echo isActive('create-service.php') ? 'nav-link-active' : ''; ?>">
                    Create New Service
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo SITE_URL; ?>orders/my-orders.php"
                    class="nav-link nav-link-freelancer <?php echo isActive(['my-orders.php', 'order-details.php', 'orders']) ? 'nav-link-active' : ''; ?>">
                    My Orders
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo SITE_URL; ?>profile.php"
                    class="nav-link nav-link-freelancer <?php echo isActive('profile.php') ? 'nav-link-active' : ''; ?>">
                    My Profile
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo SITE_URL; ?>auth/logout.php" class="nav-link">
                    Logout
                </a>
            </li>
        <?php endif; ?>
    </ul>
</nav>

<main class="main-content">
    <?php
    // Display success message if set
    $successMessage = getSuccessMessage();
    if ($successMessage): ?>
        <div class="message message-success">
            <?php echo htmlspecialchars($successMessage); ?>
        </div>
    <?php endif; ?>

    <?php
    // Display error message if set
    $errorMessage = getErrorMessage();
    if ($errorMessage): ?>
        <div class="message message-error">
            <?php echo htmlspecialchars($errorMessage); ?>
        </div>
    <?php endif; ?>