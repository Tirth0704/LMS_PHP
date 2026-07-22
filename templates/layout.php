<?php
$current = current_path();
$user = current_student();
$roleLabel = is_librarian_logged_in() ? 'Librarian Panel' : (is_student_logged_in() ? 'Student Portal' : 'Library Portal');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title) ?> | LibraryHub</title>
    <!-- Plus Jakarta Sans Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Main App CSS -->
    <link rel="stylesheet" href="<?= esc(asset_url('assets/css/app.css')) ?>">
</head>
<body class="bg-light">

<?php if (is_student_logged_in() || is_librarian_logged_in()): ?>
    <!-- Unified Sidebar Layout -->
    <div class="d-flex min-vh-100 flex-column flex-lg-row">
        <!-- Offcanvas Sidebar -->
        <div class="offcanvas-lg offcanvas-start sidebar bg-dark text-white border-end border-dark-subtle" tabindex="-1" id="sidebarMenu" style="width: 260px; background-color: #191c1e !important;">
            <div class="offcanvas-header border-bottom border-secondary-subtle p-3">
                <h5 class="offcanvas-title text-white fw-bold mb-0">
                    <a href="<?= is_librarian_logged_in() ? esc(route_url('librarian/dashboard')) : esc(route_url('student/dashboard')) ?>" class="text-white text-decoration-none d-flex align-items-center gap-2">
                        <i class="bi bi-book-half text-primary fs-4"></i> LibraryHub
                    </a>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#sidebarMenu"></button>
            </div>
            
            <div class="offcanvas-body d-flex flex-column p-3">
                <!-- Brand Area (Desktop) -->
                <div class="d-none d-lg-flex align-items-center justify-content-between mb-4 w-100">
                    <div>
                        <h5 class="fw-bold text-white mb-0">
                            <a href="<?= is_librarian_logged_in() ? esc(route_url('librarian/dashboard')) : esc(route_url('student/dashboard')) ?>" class="text-white text-decoration-none d-flex align-items-center gap-2 fs-5">
                                <i class="bi bi-book-half text-primary"></i> <span class="brand-text">LibraryHub</span>
                            </a>
                        </h5>
                        <small class="text-secondary text-uppercase tracking-wider fw-semibold role-subtitle ms-4" style="font-size: 0.65rem;">
                            <?= is_librarian_logged_in() ? 'Librarian Panel' : 'Student Portal' ?>
                        </small>
                    </div>
                    <button type="button" class="btn btn-sm text-secondary p-1 border-0 rounded-2" id="sidebarToggleBtn" title="Minimize Sidebar">
                        <i class="bi bi-chevron-left" id="sidebarToggleIcon"></i>
                    </button>
                </div>

                <!-- Navigation Links -->
                <ul class="nav flex-column gap-1 mb-auto w-100">
                    <?php if (is_student_logged_in() && !is_librarian_logged_in()): ?>
                        <li class="nav-item w-100"><a class="nav-link <?= nav_active('student/dashboard', $current) ?>" href="<?= esc(route_url('student/dashboard')) ?>" title="Dashboard"><i class="bi bi-house"></i> <span class="nav-text">Dashboard</span></a></li>
                        <li class="nav-item w-100"><a class="nav-link <?= nav_active('books', $current) ?>" href="<?= esc(route_url('books')) ?>" title="Search Books"><i class="bi bi-search"></i> <span class="nav-text">Search Books</span></a></li>
                        <li class="nav-item w-100"><a class="nav-link <?= nav_active('my-books', $current) ?>" href="<?= esc(route_url('my-books')) ?>" title="My Books"><i class="bi bi-journal-bookmark"></i> <span class="nav-text">My Books</span></a></li>
                        <li class="nav-item w-100"><a class="nav-link <?= nav_active('my-fines', $current) ?>" href="<?= esc(route_url('my-fines')) ?>" title="Fines & Dues"><i class="bi bi-receipt"></i> <span class="nav-text">Fines & Dues</span></a></li>
                        <li class="nav-item w-100"><a class="nav-link <?= nav_active('notifications', $current) ?>" href="<?= esc(route_url('notifications')) ?>" title="Notifications"><i class="bi bi-bell"></i> <span class="nav-text">Notifications</span></a></li>
                        <li class="nav-item w-100"><a class="nav-link <?= nav_active('student/profile', $current) ?>" href="<?= esc(route_url('student/profile')) ?>" title="Profile"><i class="bi bi-person-circle"></i> <span class="nav-text">Profile</span></a></li>
                        <li class="nav-item w-100"><a class="nav-link <?= nav_active('my-requests', $current) ?>" href="<?= esc(route_url('my-requests')) ?>" title="My Requests"><i class="bi bi-clock-history"></i> <span class="nav-text">My Requests</span></a></li>
                        <li class="nav-item w-100"><a class="nav-link <?= nav_active('my-history', $current) ?>" href="<?= esc(route_url('my-history')) ?>" title="History"><i class="bi bi-archive"></i> <span class="nav-text">History</span></a></li>
                        <li class="nav-item w-100"><a class="nav-link <?= nav_active('student/receipts', $current) ?>" href="<?= esc(route_url('student/receipts')) ?>" title="Receipts"><i class="bi bi-file-earmark-pdf"></i> <span class="nav-text">Receipts</span></a></li>
                        <li class="nav-item w-100"><a class="nav-link <?= nav_active('recommendations', $current) ?>" href="<?= esc(route_url('recommendations')) ?>" title="Recommendations"><i class="bi bi-lightbulb"></i> <span class="nav-text">Recommendations</span></a></li>
                    <?php elseif (is_librarian_logged_in()): ?>
                        <li class="nav-item w-100"><a class="nav-link <?= nav_active('librarian/dashboard', $current) ?>" href="<?= esc(route_url('librarian/dashboard')) ?>" title="Dashboard"><i class="bi bi-speedometer2"></i> <span class="nav-text">Dashboard</span></a></li>
                        <li class="nav-item w-100"><a class="nav-link <?= nav_active('librarian/books', $current) ?>" href="<?= esc(route_url('librarian/books')) ?>" title="Books"><i class="bi bi-book"></i> <span class="nav-text">Books</span></a></li>
                        <li class="nav-item w-100"><a class="nav-link <?= nav_active('librarian/categories', $current) ?>" href="<?= esc(route_url('librarian/categories')) ?>" title="Categories"><i class="bi bi-tags"></i> <span class="nav-text">Categories</span></a></li>
                        <li class="nav-item w-100"><a class="nav-link <?= nav_active('librarian/requests', $current) ?>" href="<?= esc(route_url('librarian/requests')) ?>" title="Requests"><i class="bi bi-clock-history"></i> <span class="nav-text">Requests</span></a></li>
                        <li class="nav-item w-100"><a class="nav-link <?= nav_active('librarian/issues', $current) ?>" href="<?= esc(route_url('librarian/issues')) ?>" title="Issues"><i class="bi bi-journal-bookmark"></i> <span class="nav-text">Issues</span></a></li>
                        <li class="nav-item w-100"><a class="nav-link <?= nav_active('librarian/returns', $current) ?>" href="<?= esc(route_url('librarian/returns')) ?>" title="Returns"><i class="bi bi-arrow-return-left"></i> <span class="nav-text">Returns</span></a></li>
                        <li class="nav-item w-100"><a class="nav-link <?= nav_active('librarian/students', $current) ?>" href="<?= esc(route_url('librarian/students')) ?>" title="Students"><i class="bi bi-people"></i> <span class="nav-text">Students</span></a></li>
                        <li class="nav-item w-100"><a class="nav-link <?= nav_active('librarian/payments', $current) ?>" href="<?= esc(route_url('librarian/payments')) ?>" title="Payments"><i class="bi bi-cash-coin"></i> <span class="nav-text">Payments</span></a></li>
                        <li class="nav-item w-100"><a class="nav-link <?= nav_active('librarian/recommendations', $current) ?>" href="<?= esc(route_url('librarian/recommendations')) ?>" title="Recommendations"><i class="bi bi-lightbulb"></i> <span class="nav-text">Recommendations</span></a></li>
                        <li class="nav-item w-100"><a class="nav-link <?= nav_active('librarian/whatsapp-logs', $current) ?>" href="<?= esc(route_url('librarian/whatsapp-logs')) ?>" title="WhatsApp Logs"><i class="bi bi-whatsapp"></i> <span class="nav-text">WhatsApp Logs</span></a></li>
                    <?php endif; ?>
                </ul>

                <!-- Footer Action in Sidebar -->
                <div class="mt-4 pt-3 border-top border-secondary-subtle w-100">
                    <form method="post" action="<?= esc(route_url('logout')) ?>">
                        <?= csrf_field() ?>
                        <button class="nav-link text-danger border-0 bg-transparent w-100 text-start d-flex align-items-center gap-2 px-2 py-1" title="Logout">
                            <i class="bi bi-box-arrow-right"></i> <span class="nav-text">Logout</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Mobile Top Bar Header -->
        <div class="d-lg-none w-100 bg-dark text-white px-3 py-2 d-flex justify-content-between align-items-center border-bottom border-dark-subtle" style="background-color: #191c1e !important;">
            <a href="<?= esc(route_url('home')) ?>" class="text-white text-decoration-none fw-bold d-flex align-items-center gap-2">
                <i class="bi bi-book-half text-primary"></i> LibraryHub
            </a>
            <button class="btn btn-outline-light btn-sm" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu">
                <i class="bi bi-list fs-5"></i>
            </button>
        </div>

        <!-- Main Canvas Content -->
        <div class="flex-grow-1 main-content-wrapper d-flex flex-column min-vh-100 bg-light" style="background-color: #f7f9fb !important;">
            <!-- Top Header bar (Desktop) -->
            <header class="d-none d-lg-flex bg-white px-4 py-3 border-bottom align-items-center justify-content-between sticky-top" style="height: 64px; border-color: #e6e8ea !important;">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge rounded-circle p-1 bg-success animate-pulse-status" style="width: 8px; height: 8px; display: inline-block;"></span>
                    <span class="text-secondary fw-semibold" style="font-size: 0.85rem;">System Status: Healthy</span>
                </div>

                <div class="d-flex align-items-center gap-3">
                    <?php if ($user && !is_librarian_logged_in()): ?>
                        <a href="<?= esc(route_url('notifications')) ?>" class="btn btn-light rounded-circle p-2 position-relative">
                            <i class="bi bi-bell"></i>
                        </a>
                        <div class="border-end h-75 my-auto mx-1" style="height: 24px; border-color: #e6e8ea !important;"></div>
                        <div class="d-flex align-items-center gap-2">
                            <div class="text-end">
                                <div class="fw-bold text-dark" style="font-size: 0.85rem;"><?= esc($user['full_name']) ?></div>
                                <div class="text-secondary" style="font-size: 0.75rem;"><?= esc($user['enrollment_number'] ?? '') ?></div>
                            </div>
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold" style="width: 38px; height: 38px; font-size: 0.9rem;">
                                <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                            </div>
                        </div>
                    <?php elseif (is_librarian_logged_in()): ?>
                        <div class="d-flex align-items-center gap-2">
                            <div class="text-end">
                                <div class="fw-bold text-dark" style="font-size: 0.85rem;">Librarian Admin</div>
                                <div class="text-secondary" style="font-size: 0.75rem;">Head Office</div>
                            </div>
                            <div class="rounded-circle bg-dark text-white d-flex align-items-center justify-content-center fw-bold" style="width: 38px; height: 38px; font-size: 0.9rem;">
                                L
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </header>

            <!-- Main Page View Content -->
            <main class="container-fluid p-4">
                <div class="mb-3">
                    <?php foreach ($messages as $type => $items): ?>
                        <?php foreach ($items as $message): ?>
                            <div class="alert alert-<?= esc($type === 'info' ? 'secondary' : $type) ?> alert-dismissible fade show shadow-sm" role="alert">
                                <?= esc($message) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>

                <?= $body ?>
            </main>

            <footer class="footer mt-auto py-3 bg-white border-top text-center text-muted small">
                <div class="container">
                    <span>LibraryHub &copy; <?= date('Y') ?> — Smart Library Resource & Student Management Platform</span>
                </div>
            </footer>
        </div>
    </div>
<?php else: ?>
    <!-- Guest Layout (Login / Register / Welcome) -->
    <main class="container py-5">
        <div class="mb-3">
            <?php foreach ($messages as $type => $items): ?>
                <?php foreach ($items as $message): ?>
                    <div class="alert alert-<?= esc($type === 'info' ? 'secondary' : $type) ?> alert-dismissible fade show shadow-sm" role="alert">
                        <?= esc($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
        <?= $body ?>
    </main>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('sidebarToggleBtn');
    const toggleIcon = document.getElementById('sidebarToggleIcon');
    
    if (localStorage.getItem('sidebar_collapsed') === 'true') {
        document.body.classList.add('sidebar-collapsed');
        if (toggleIcon) {
            toggleIcon.classList.remove('bi-chevron-left');
            toggleIcon.classList.add('bi-chevron-right');
        }
    }
    
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            document.body.classList.toggle('sidebar-collapsed');
            const isCollapsed = document.body.classList.contains('sidebar-collapsed');
            localStorage.setItem('sidebar_collapsed', isCollapsed ? 'true' : 'false');
            if (toggleIcon) {
                if (isCollapsed) {
                    toggleIcon.classList.remove('bi-chevron-left');
                    toggleIcon.classList.add('bi-chevron-right');
                } else {
                    toggleIcon.classList.remove('bi-chevron-right');
                    toggleIcon.classList.add('bi-chevron-left');
                }
            }
        });
    }
});
</script>
</body>
</html>
