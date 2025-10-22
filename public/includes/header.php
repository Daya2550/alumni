<?php
/**
 * Enhanced Header Navigation
 * Modern UI with improved user experience
 */

$user = Auth::getCurrentUser();
$unread_notifications = 0;
$pending_staff = 0;

if ($user) {
    $unread_notifications = db()->fetchOne(
        "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0",
        [$user['id']]
    )['count'] ?? 0;
    
    if ($user['role'] === 'staff') {
        $pending_staff = db()->fetchOne(
            "SELECT COUNT(*) as count FROM users WHERE role = 'staff' AND is_active = 0",
            []
        )['count'] ?? 0;
    }
}

$main_nav_links = [
    ['href' => 'index.php', 'icon' => 'fas fa-home', 'text' => 'Home'],
    ['href' => 'notices.php', 'icon' => 'fas fa-bullhorn', 'text' => 'Notices', 'user_only' => true],
    ['href' => 'feed.php', 'icon' => 'fas fa-stream', 'text' => 'Feed', 'user_only' => true],
    ['href' => 'news.php', 'icon' => 'fas fa-newspaper', 'text' => 'News', 'user_only' => true],
    ['href' => 'jobs.php', 'icon' => 'fas fa-briefcase', 'text' => 'Jobs', 'user_only' => true],
    ['href' => 'events.php', 'icon' => 'fas fa-calendar-alt', 'text' => 'Events', 'user_only' => true],
    ['href' => 'surveys.php', 'icon' => 'fas fa-poll', 'text' => 'Surveys', 'user_only' => true],
    ['href' => 'projects.php', 'icon' => 'fas fa-code', 'text' => 'Projects', 'user_only' => true],
];

$more_dropdown_items = [
    ['href' => 'directory.php', 'icon' => 'fas fa-users', 'text' => 'Directory'],
    ['href' => 'message.php', 'icon' => 'fas fa-comments', 'text' => 'Chat', 'id' => 'headerChatBtn'],
    ['href' => 'feedback.php', 'icon' => 'fas fa-comment-dots', 'text' => 'Feedback'],
    ['divider' => true],
    ['href' => 'gallery.php', 'icon' => 'fas fa-images', 'text' => 'Gallery'],
    ['divider' => true, 'roles' => ['admin', 'staff']],
    ['href' => 'admin/user_management.php', 'icon' => 'fas fa-users', 'text' => 'Manage Users', 'roles' => ['admin', 'staff']],
    ['href' => 'analytics.php', 'icon' => 'fas fa-chart-bar', 'text' => 'Analytics Dashboard', 'roles' => ['admin', 'staff']],
    ['href' => 'aiassistant-admin.php', 'icon' => 'fas fa-robot', 'text' => 'AI Assistant Admin', 'roles' => ['admin', 'staff']],
    ['href' => 'admin/feedback.php', 'icon' => 'fas fa-inbox', 'text' => 'Admin Feedback', 'roles' => ['admin']],
];
?>

<nav class="navbar navbar-expand-lg navbar-dark enhanced-header sticky-top">
    <div class="container-fluid px-3 px-lg-4">
        <!-- Left Section: Back Button & Logo -->
        <div class="d-flex align-items-center">
            <button type="button" id="globalBackBtn" class="btn btn-sm back-btn me-3 d-none d-lg-flex" title="Go Back">
                <i class="fas fa-arrow-left"></i>
            </button>
            
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <div class="brand-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <span class="brand-text"><?php echo APP_NAME; ?></span>
            </a>
        </div>
        
        <!-- Mobile Toggle -->
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <!-- Center Navigation (Desktop) -->
        <div class="collapse navbar-collapse justify-content-center" id="navbarNav">
            <ul class="navbar-nav nav-pills-custom">
                <?php foreach ($main_nav_links as $link): ?>
                    <?php if (empty($link['user_only']) || $user): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $link['href']; ?>">
                                <i class="<?php echo $link['icon']; ?>"></i>
                                <span class="nav-text"><?php echo $link['text']; ?></span>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
                
                <?php if ($user): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="commonDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-th-large"></i>
                            <span class="nav-text">More</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-custom shadow-lg">
                            <?php foreach ($more_dropdown_items as $item): ?>
                                <?php 
                                    $show_item = true;
                                    if (isset($item['roles'])) {
                                        $show_item = Auth::hasAnyRole($item['roles']);
                                    }
                                ?>
                                <?php if ($show_item): ?>
                                    <?php if (isset($item['divider'])): ?>
                                        <li><hr class="dropdown-divider"></li>
                                    <?php else: ?>
                                        <li>
                                            <a class="dropdown-item" href="<?php echo $item['href']; ?>" <?php echo isset($item['id']) ? 'id="' . $item['id'] . '"' : ''; ?>>
                                                <i class="<?php echo $item['icon']; ?>"></i>
                                                <span><?php echo $item['text']; ?></span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
        
        <!-- Right Section: User Menu -->
        <div class="d-none d-lg-flex align-items-center">
            <?php if ($user): ?>
                <div class="nav-item dropdown">
                    <a class="nav-link user-profile-btn" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <div class="user-avatar">
                            <?php if ($user['profile_photo']): ?>
                                <img src="<?php echo htmlspecialchars($user['profile_photo']); ?>" alt="Profile">
                            <?php else: ?>
                                <i class="fas fa-user"></i>
                            <?php endif; ?>
                            <?php if ($unread_notifications > 0): ?>
                                <span class="notification-dot" id="notifBadge"></span>
                            <?php else: ?>
                                <span class="notification-dot d-none" id="notifBadge"></span>
                            <?php endif; ?>
                        </div>
                        <div class="user-info">
                            <span class="user-name"><?php echo htmlspecialchars($user['name']); ?></span>
                            <span class="user-role"><?php echo ucfirst($user['role']); ?></span>
                        </div>
                        <i class="fas fa-chevron-down ms-2"></i>
                    </a>
                    
                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-custom user-dropdown shadow-lg">
                        <li class="dropdown-header">
                            <div class="text-center pb-2">
                                <div class="user-avatar-large mb-2">
                                    <?php if ($user['profile_photo']): ?>
                                        <img src="<?php echo htmlspecialchars($user['profile_photo']); ?>" alt="Profile">
                                    <?php else: ?>
                                        <i class="fas fa-user"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="fw-bold"><?php echo htmlspecialchars($user['name']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($user['email'] ?? ''); ?></small>
                            </div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="profile.php">
                                <i class="fas fa-user"></i>
                                <span>My Profile</span>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="settings.php">
                                <i class="fas fa-cog"></i>
                                <span>Settings</span>
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="notifications.php">
                                <i class="fas fa-bell"></i>
                                <span>Notifications</span>
                                <?php if ($unread_notifications > 0): ?>
                                    <span class="badge bg-danger ms-auto"><?php echo $unread_notifications; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        
                        <?php if ($user['role'] === 'staff' && $pending_staff > 0): ?>
                            <li>
                                <a class="dropdown-item text-warning" href="admin/pending_staff.php">
                                    <i class="fas fa-user-clock"></i>
                                    <span>Pending Staff</span>
                                    <span class="badge bg-warning text-dark ms-auto"><?php echo $pending_staff; ?></span>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="logout.php">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Logout</span>
                            </a>
                        </li>
                    </ul>
                </div>
            <?php else: ?>
                <a class="btn btn-outline-custom me-2" href="login.php">
                    <i class="fas fa-sign-in-alt me-1"></i> Login
                </a>
                <a class="btn btn-primary-custom" href="register.php">
                    <i class="fas fa-user-plus me-1"></i> Register
                </a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Mobile Offcanvas -->
<div class="offcanvas offcanvas-start offcanvas-custom" tabindex="-1" id="offcanvasNavbar">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title">
            <i class="fas fa-graduation-cap me-2"></i><?php echo APP_NAME; ?>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <?php if ($user): ?>
            <!-- User Profile Section -->
            <div class="mobile-user-section mb-4">
                <div class="d-flex align-items-center">
                    <div class="user-avatar-large me-3">
                        <?php if ($user['profile_photo']): ?>
                            <img src="<?php echo htmlspecialchars($user['profile_photo']); ?>" alt="Profile">
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="fw-bold text-white"><?php echo htmlspecialchars($user['name']); ?></div>
                        <small class="text-white-50"><?php echo ucfirst($user['role']); ?></small>
                    </div>
                </div>
            </div>
            <hr class="border-secondary">
        <?php endif; ?>
        
        <!-- Navigation Links -->
        <ul class="navbar-nav">
            <?php foreach ($main_nav_links as $link): ?>
                <?php if (empty($link['user_only']) || $user): ?>
                    <li class="nav-item">
                        <a class="nav-link mobile-nav-link" href="<?php echo $link['href']; ?>">
                            <i class="<?php echo $link['icon']; ?>"></i>
                            <span><?php echo $link['text']; ?></span>
                        </a>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
            
            <?php if ($user): ?>
                <li class="nav-item">
                    <hr class="dropdown-divider border-secondary my-3">
                </li>
                <li class="nav-item">
                    <span class="nav-link text-uppercase fw-bold text-white-50 small">More Options</span>
                </li>
                <?php foreach ($more_dropdown_items as $item): ?>
                    <?php 
                        $show_item = true;
                        if (isset($item['roles'])) {
                            $show_item = Auth::hasAnyRole($item['roles']);
                        }
                    ?>
                    <?php if ($show_item && !isset($item['divider'])): ?>
                        <li class="nav-item">
                            <a class="nav-link mobile-nav-link" href="<?php echo $item['href']; ?>">
                                <i class="<?php echo $item['icon']; ?>"></i>
                                <span><?php echo $item['text']; ?></span>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
                
                <li class="nav-item">
                    <hr class="dropdown-divider border-secondary my-3">
                </li>
                <li class="nav-item">
                    <a class="nav-link mobile-nav-link" href="profile.php">
                        <i class="fas fa-user"></i>
                        <span>My Profile</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link mobile-nav-link" href="settings.php">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link mobile-nav-link" href="notifications.php">
                        <i class="fas fa-bell"></i>
                        <span>Notifications</span>
                        <?php if ($unread_notifications > 0): ?>
                            <span class="badge bg-danger ms-auto"><?php echo $unread_notifications; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link mobile-nav-link text-danger" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </li>
            <?php else: ?>
                <li class="nav-item mt-3">
                    <a class="btn btn-primary-custom w-100 mb-2" href="login.php">
                        <i class="fas fa-sign-in-alt me-1"></i> Login
                    </a>
                </li>
                <li class="nav-item">
                    <a class="btn btn-outline-custom w-100" href="register.php">
                        <i class="fas fa-user-plus me-1"></i> Register
                    </a>
                </li>
            <?php endif; ?>
        </ul>
        
        <!-- Back Button -->
        <div class="mt-4">
            <button type="button" id="globalBackBtnOffcanvas" class="btn btn-outline-custom w-100">
                <i class="fas fa-arrow-left me-2"></i> Back
            </button>
        </div>
    </div>
</div>

<style>
/* Enhanced Header Styles */
.enhanced-header {
    background: linear-gradient(135deg, #124170 0%, #26667F 100%);
    box-shadow: 0 2px 20px rgba(18, 65, 112, 0.3);
    backdrop-filter: blur(10px);
    padding: 0.5rem 0;
}

/* Brand Styling */
.navbar-brand {
    font-weight: 700;
    font-size: 1.5rem;
    color: #DDF4E7 !important;
    text-decoration: none;
    transition: transform 0.3s ease;
}

.navbar-brand:hover {
    transform: translateY(-2px);
}

.brand-icon {
    width: 40px;
    height: 40px;
    background: rgba(103, 192, 144, 0.2);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    font-size: 1.2rem;
    color: #67C090;
}

.brand-text {
    background: linear-gradient(135deg, #DDF4E7 0%, #67C090 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* Back Button */
.back-btn {
    background: rgba(221, 244, 231, 0.1);
    border: 1px solid rgba(221, 244, 231, 0.2);
    color: #DDF4E7;
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.back-btn:hover {
    background: rgba(103, 192, 144, 0.2);
    border-color: #67C090;
    color: #67C090;
    transform: translateX(-3px);
}

/* Navigation Pills */
.nav-pills-custom .nav-link {
    color: #DDF4E7;
    padding: 0.5rem 1rem;
    margin: 0 0.25rem;
    border-radius: 10px;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 500;
}

.nav-pills-custom .nav-link:hover,
.nav-pills-custom .nav-link:focus {
    background: rgba(103, 192, 144, 0.15);
    color: #67C090;
    transform: translateY(-2px);
}

.nav-pills-custom .nav-link i {
    font-size: 1.1rem;
}

.nav-text {
    font-size: 0.95rem;
}

/* Dropdown Menu */
.dropdown-menu-custom {
    background: linear-gradient(135deg, #124170 0%, #1a4d7a 100%);
    border: 1px solid rgba(103, 192, 144, 0.2);
    border-radius: 12px;
    padding: 0.5rem;
    min-width: 240px;
    margin-top: 0.5rem !important;
}

.dropdown-menu-custom .dropdown-item {
    color: #DDF4E7;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.dropdown-menu-custom .dropdown-item:hover,
.dropdown-menu-custom .dropdown-item:focus {
    background: rgba(103, 192, 144, 0.15);
    color: #67C090;
    transform: translateX(5px);
}

.dropdown-menu-custom .dropdown-item i {
    width: 20px;
    text-align: center;
}

.dropdown-divider {
    border-color: rgba(221, 244, 231, 0.15);
    margin: 0.5rem 0;
}

/* User Profile Button */
.user-profile-btn {
    display: flex !important;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 1rem;
    background: rgba(221, 244, 231, 0.1);
    border: 1px solid rgba(221, 244, 231, 0.2);
    border-radius: 50px;
    color: #DDF4E7 !important;
    transition: all 0.3s ease;
}

.user-profile-btn:hover {
    background: rgba(103, 192, 144, 0.2);
    border-color: #67C090;
    transform: translateY(-2px);
}

.user-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, #67C090 0%, #26667F 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
}

.user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.user-avatar i {
    font-size: 1.2rem;
    color: #DDF4E7;
}

.notification-dot {
    position: absolute;
    top: -2px;
    right: -2px;
    width: 12px;
    height: 12px;
    background: #dc3545;
    border: 2px solid #124170;
    border-radius: 50%;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.user-info {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}

.user-name {
    font-weight: 600;
    font-size: 0.95rem;
    line-height: 1.2;
}

.user-role {
    font-size: 0.75rem;
    opacity: 0.8;
}

/* User Dropdown */
.user-dropdown {
    min-width: 280px;
}

.user-dropdown .dropdown-header {
    padding: 1rem;
    background: rgba(103, 192, 144, 0.1);
    border-radius: 8px;
    margin-bottom: 0.5rem;
}

.user-avatar-large {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #67C090 0%, #26667F 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    overflow: hidden;
}

.user-avatar-large img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.user-avatar-large i {
    font-size: 1.8rem;
    color: #DDF4E7;
}

/* Buttons */
.btn-outline-custom {
    color: #DDF4E7;
    border: 1px solid rgba(221, 244, 231, 0.3);
    background: transparent;
    border-radius: 10px;
    padding: 0.5rem 1.25rem;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-outline-custom:hover {
    background: rgba(221, 244, 231, 0.1);
    border-color: #67C090;
    color: #67C090;
    transform: translateY(-2px);
}

.btn-primary-custom {
    background: linear-gradient(135deg, #67C090 0%, #26667F 100%);
    border: none;
    color: #fff;
    border-radius: 10px;
    padding: 0.5rem 1.25rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-primary-custom:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(103, 192, 144, 0.3);
}

/* Offcanvas Mobile Menu */
.offcanvas-custom {
    background: linear-gradient(135deg, #124170 0%, #26667F 100%);
    max-width: 300px;
}

.offcanvas-custom .offcanvas-title {
    color: #DDF4E7;
    font-weight: 700;
    font-size: 1.3rem;
}

.mobile-user-section {
    background: rgba(103, 192, 144, 0.1);
    padding: 1rem;
    border-radius: 12px;
}

.mobile-nav-link {
    color: #DDF4E7 !important;
    padding: 0.75rem 1rem;
    border-radius: 10px;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 500;
}

.mobile-nav-link:hover {
    background: rgba(103, 192, 144, 0.15);
    color: #67C090 !important;
    transform: translateX(5px);
}

.mobile-nav-link i {
    width: 24px;
    text-align: center;
    font-size: 1.1rem;
}

/* Mobile Responsive */
@media (max-width: 991.98px) {
    .enhanced-header .navbar-collapse {
        display: none !important;
    }
    
    .navbar-brand {
        font-size: 1.2rem;
    }
    
    .brand-icon {
        width: 35px;
        height: 35px;
        font-size: 1rem;
    }
}

/* Navbar Toggler */
.navbar-toggler {
    border: none;
    padding: 0.5rem;
    border-radius: 8px;
    background: rgba(221, 244, 231, 0.1);
}

.navbar-toggler:focus {
    box-shadow: none;
}

.navbar-toggler-icon {
    filter: brightness(0) invert(1);
}
</style>

<?php 
if ($user): 
    include __DIR__ . '/../aiassistant-widget.php';
?>
<script>
(function() {
    function updateNotif() {
        fetch('api/notifications.php?action=check', { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                const badges = document.querySelectorAll('#notifBadge');
                const dropdownItemBadge = document.querySelector('.dropdown-menu [href="notifications.php"] .badge');
                const count = (data && data.success) ? (data.count || 0) : 0;
                
                badges.forEach(badge => {
                    if (count > 0) {
                        badge.classList.remove('d-none');
                    } else {
                        badge.classList.add('d-none');
                    }
                });
                
                if (dropdownItemBadge) {
                    if (count > 0) {
                        dropdownItemBadge.textContent = count;
                        dropdownItemBadge.style.display = 'inline-block';
                    } else {
                        dropdownItemBadge.style.display = 'none';
                    }
                }
            })
            .catch(() => {});
    }
    updateNotif();
    setInterval(updateNotif, 30000);
})();
</script>
<?php endif; ?>

<script>
(function(){
    function goBack(){
        const path = location.pathname.replace(/^\/+/, '');
        if (path.endsWith('admin/survey_edit.php')) {
            location.href = 'admin/surveys.php';
            return;
        }
        if (path.endsWith('admin/surveys.php')) {
            location.href = 'surveys.php';
            return;
        }
        if (window.history.length > 1) {
            window.history.back();
        } else {
            location.href = 'index.php';
        }
    }
    
    const btn = document.getElementById('globalBackBtn');
    if (btn) btn.addEventListener('click', goBack);
    
    const btnOffcanvas = document.getElementById('globalBackBtnOffcanvas');
    if (btnOffcanvas) btnOffcanvas.addEventListener('click', goBack);

    const backBarDiv = document.getElementById('backBar');
    if (backBarDiv) backBarDiv.remove();
})();
</script>