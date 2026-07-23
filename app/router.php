<?php

$route = current_path();

switch ($route) {
    case 'home':
        home_page();
        break;
    case 'login':
        login_page();
        break;
    case 'register':
        register_page();
        break;
    case 'logout':
        logout_page();
        break;

    // Student Routes
    case 'student/dashboard':
        student_dashboard();
        break;
    case 'student/profile':
        student_profile_page();
        break;
    case 'books':
        books_page();
        break;
    case 'book/view':
        book_view_page();
        break;
    case 'book/request':
        book_request_page();
        break;
    case 'my-requests':
        my_requests_page();
        break;
    case 'request/cancel':
        request_cancel_page();
        break;
    case 'my-books':
        my_books_page();
        break;
    case 'my-history':
        my_history_page();
        break;
    case 'my-fines':
        my_fines_page();
        break;
    case 'student/pay-fine':
        student_pay_fine_page();
        break;
    case 'student/payments/razorpay-callback':
        student_razorpay_callback_page();
        break;
    case 'student/receipts':
        student_receipts_page();
        break;
    case 'receipt-pdf':
        receipt_pdf_page();
        break;
    case 'recommendations':
        recommendations_page();
        break;
    case 'notifications':
        notifications_page();
        break;

    // Librarian Routes
    case 'librarian/dashboard':
        librarian_dashboard();
        break;
    case 'librarian/books':
        librarian_books_page();
        break;
    case 'librarian/books/add':
        librarian_book_add_page();
        break;
    case 'librarian/books/edit':
        librarian_book_edit_page();
        break;
    case 'librarian/books/delete':
        librarian_book_delete_page();
        break;
    case 'librarian/categories':
        librarian_categories_page();
        break;
    case 'librarian/requests':
        librarian_requests_page();
        break;
    case 'librarian/requests/approve':
        librarian_approve_request_page();
        break;
    case 'librarian/requests/reject':
        librarian_reject_request_page();
        break;
    case 'librarian/requests/hold':
        librarian_hold_request_page();
        break;
    case 'librarian/issues':
        librarian_issues_page();
        break;
    case 'librarian/returns':
        librarian_returns_page();
        break;
    case 'librarian/returns/process':
        librarian_return_process_page();
        break;
    case 'librarian/returns/complete':
        librarian_return_complete_page();
        break;
    case 'librarian/students':
        librarian_students_page();
        break;
    case 'librarian/students/view':
        librarian_student_view_page();
        break;
    case 'librarian/adjust-score':
        librarian_adjust_score_page();
        break;
    case 'librarian/payments':
        librarian_payments_page();
        break;
    case 'librarian/payments/mark-offline':
        librarian_mark_offline_payment_page();
        break;
    case 'librarian/recommendations':
        librarian_recommendations_page();
        break;
    case 'librarian/recommendations/review':
        librarian_recommendation_review_page();
        break;
    case 'librarian/whatsapp-logs':
        librarian_whatsapp_logs_page();
        break;
    case 'librarian/notifications':
        librarian_notifications_page();
        break;

    default:
        http_response_code(404);
        render('Page Not Found', '<div class="alert alert-danger mb-0">The requested page could not be found.</div>');
        break;
}

// ─── ROUTE CONTROLLER FUNCTIONS ──────────────────────────────────────────────

function home_page() {
    if (is_student_logged_in()) {
        redirect_to('student/dashboard');
    }
    if (is_librarian_logged_in()) {
        redirect_to('librarian/dashboard');
    }
    ob_start();
    ?>
    <div class="row align-items-center g-5 py-3">
        <div class="col-lg-7">
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill mb-3">LibraryHub Management</span>
            <h1 class="display-4 fw-extrabold mb-3">Smart Library Resource & Student Platform</h1>
            <p class="lead text-muted mb-4">Complete library lifecycle handling: online & offline book borrowing, lost & damage handling, Razorpay payments, Twilio WhatsApp notifications, and automated student behaviour scoring.</p>
            <div class="d-flex flex-wrap gap-3">
                <a href="<?= esc(route_url('login')) ?>" class="btn btn-primary btn-lg px-4">Student & Staff Login</a>
                <a href="<?= esc(route_url('register')) ?>" class="btn btn-outline-secondary btn-lg px-4">New Student Register</a>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card border-0 shadow-lg p-4 rounded-4 bg-white">
                <h3 class="h5 fw-bold mb-3">Library Portal Access</h3>
                <form method="post" action="<?= esc(route_url('login')) ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="user@university.edu" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Enter password" required>
                    </div>
                    <button class="btn btn-primary w-100 py-2 fw-semibold mb-3">Sign In</button>
                </form>
                <div class="text-center border-top pt-3">
                    <span class="small text-muted">New student? <a href="<?= esc(route_url('register')) ?>" class="fw-semibold">Create an account</a></span>
                </div>
            </div>
        </div>
    </div>
    <?php
    render('Welcome', ob_get_clean());
}

function login_page() {
    if (is_student_logged_in()) redirect_to('student/dashboard');
    if (is_librarian_logged_in()) redirect_to('librarian/dashboard');

    if (is_post()) {
        verify_csrf();
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (library()->authenticateLibrarian($email, $password)) {
            $_SESSION['librarian_logged_in'] = true;
            $_SESSION['librarian_email'] = $email;
            library()->logActivity('librarian', null, null, null, 'login', 'Librarian logged in.');
            flash('success', 'Welcome back, Librarian.');
            redirect_to('librarian/dashboard');
        }

        $student = library()->authenticateStudent($email, $password);
        if ($student) {
            $_SESSION['student'] = $student;
            library()->logActivity('student', (int)$student['id'], 'student', (int)$student['id'], 'login', 'Student logged in.');
            flash('success', 'Welcome back, ' . $student['full_name'] . '.');
            redirect_to('student/dashboard');
        }

        flash('danger', 'Invalid email address or password.');
    }

    ob_start();
    ?>
    <div class="row justify-content-center">
        <div class="col-lg-5 col-md-8">
            <div class="card border-0 shadow-sm p-4 rounded-4">
                <h2 class="h4 fw-bold mb-4">Account Login</h2>
                <form method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?= esc($_POST['email'] ?? '') ?>" required placeholder="name@example.com">
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required placeholder="Enter password">
                    </div>
                    <button class="btn btn-primary w-100 py-2 fw-semibold">Login</button>
                </form>
                <div class="text-center mt-3">
                    <small class="text-muted">Don't have an account? <a href="<?= esc(route_url('register')) ?>">Register here</a></small>
                </div>
            </div>
        </div>
    </div>
    <?php
    render('Login', ob_get_clean());
}

function register_page() {
    if (is_student_logged_in()) redirect_to('student/dashboard');
    if (is_librarian_logged_in()) redirect_to('librarian/dashboard');

    if (is_post()) {
        verify_csrf();
        $result = library()->registerStudent([
            'full_name' => trim($_POST['full_name'] ?? ''),
            'enrollment_number' => trim($_POST['enrollment_number'] ?? ''),
            'department' => trim($_POST['department'] ?? ''),
            'semester' => trim($_POST['semester'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone_number' => trim($_POST['phone_number'] ?? ''),
            'password' => $_POST['password'] ?? '',
        ]);

        if ($result['ok']) {
            flash('success', 'Account created successfully. Please log in with your credentials.');
            redirect_to('login');
        }
        flash('danger', $result['message'] ?? 'Registration failed.');
    }

    $depts = ['Computer Science', 'Information Technology', 'Electronics', 'Mechanical', 'Civil', 'Electrical', 'Chemical', 'Other'];
    ob_start();
    ?>
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">
            <!-- Twilio WhatsApp Sandbox Info & QR Code Card -->
            <div class="card border-0 shadow-sm p-4 rounded-4 mb-4 bg-white">
                <div class="row align-items-center text-center text-md-start">
                    <div class="col-md-7 mb-3 mb-md-0">
                        <h5 class="fw-bold mb-1 text-dark"><i class="bi bi-whatsapp text-success me-2"></i>Send a WhatsApp message</h5>
                        <p class="text-muted small mb-3">To receive instant borrowing, due date, and payment receipt notifications, send a message from your device to:</p>
                        <div class="d-inline-flex align-items-center gap-2 bg-light px-3 py-2 rounded-3 mb-3 border">
                            <i class="bi bi-whatsapp text-success fs-5"></i>
                            <strong class="fs-6 text-dark">+1 415 523 8886</strong>
                        </div>
                        <p class="small text-muted mb-3">with code <code class="bg-light text-dark border px-2 py-1 rounded fw-bold fs-6">join wherever-according</code></p>
                        <a href="https://wa.me/14155238886?text=join%20wherever-according" target="_blank" class="btn btn-primary px-4 rounded-pill fw-semibold shadow-sm">
                            <i class="bi bi-whatsapp me-1"></i> Open WhatsApp <i class="bi bi-box-arrow-up-right ms-1 small"></i>
                        </a>
                    </div>
                    <div class="col-md-1 d-none d-md-block text-center text-muted fw-bold border-start py-4">
                        OR
                    </div>
                    <div class="col-md-4 text-center mt-3 mt-md-0">
                        <h6 class="fw-bold mb-2 text-dark">Scan the QR code on mobile</h6>
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=https%3A%2F%2Fwa.me%2F14155238886%3Ftext%3Djoin%2520wherever-according" alt="Twilio WhatsApp Sandbox QR" class="img-fluid rounded-3 border p-2 shadow-sm bg-white" style="max-width: 160px;">
                        <div class="small text-muted mt-2">Twilio WhatsApp Sandbox</div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm p-4 rounded-4">
                <h2 class="h4 fw-bold mb-4">Student Registration</h2>
                <form method="post">
                    <?= csrf_field() ?>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" class="form-control" required value="<?= esc($_POST['full_name'] ?? '') ?>" placeholder="John Doe">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Enrollment Number</label>
                            <input type="text" name="enrollment_number" class="form-control" required value="<?= esc($_POST['enrollment_number'] ?? '') ?>" placeholder="EN123456">
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" required value="<?= esc($_POST['email'] ?? '') ?>" placeholder="student@univ.edu">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone Number (WhatsApp)</label>
                            <input type="tel" name="phone_number" class="form-control" required value="<?= esc($_POST['phone_number'] ?? '') ?>" placeholder="9876543210">
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Department</label>
                            <select name="department" class="form-select" required>
                                <option value="">Select Department</option>
                                <?php foreach ($depts as $dept): ?>
                                    <option value="<?= esc($dept) ?>" <?= ($_POST['department'] ?? '') === $dept ? 'selected' : '' ?>><?= esc($dept) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Semester</label>
                            <select name="semester" class="form-select" required>
                                <option value="">Select Semester</option>
                                <?php for ($i = 1; $i <= 8; $i++): ?>
                                    <option value="Semester <?= $i ?>" <?= ($_POST['semester'] ?? '') === "Semester {$i}" ? 'selected' : '' ?>>Semester <?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required minlength="6">
                    </div>
                    <button class="btn btn-primary w-100 py-2 fw-semibold">Register Account</button>
                </form>
            </div>
        </div>
    </div>
    <?php
    render('Student Registration', ob_get_clean());
}

function logout_page() {
    if (!is_post()) {
        http_response_code(405);
        render('Logout', '<div class="alert alert-warning mb-0">Logout requires POST.</div>');
        return;
    }
    verify_csrf();
    if (is_librarian_logged_in()) {
        library()->logActivity('librarian', null, null, null, 'logout', 'Librarian logged out.');
    } elseif (is_student_logged_in()) {
        $student = current_student();
        library()->logActivity('student', (int)$student['id'], 'student', (int)$student['id'], 'logout', 'Student logged out.');
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    session_start();
    flash('success', 'Logged out successfully.');
    redirect_to('login');
}

// ─── STUDENT CONTROLLER FUNCTIONS ────────────────────────────────────────────

function student_dashboard() {
    require_student();
    $student = library()->studentById((int)current_student()['id']);
    $_SESSION['student'] = $student; // sync latest profile/score
    $stats = library()->studentStats((int)$student['id']);
    $latestBooks = library()->latestBooks(6);

    ob_start();
    ?>
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm p-3 bg-white rounded-3">
                <div class="text-muted small">Behaviour Score</div>
                <div class="d-flex align-items-center justify-content-between mt-1">
                    <span class="h3 fw-bold mb-0"><?= (int)$student['behaviour_score'] ?></span>
                    <?= status_badge($student['account_status']) ?>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm p-3 bg-white rounded-3">
                <div class="text-muted small">Active Books</div>
                <div class="h3 fw-bold mb-0 mt-1"><?= (int)$stats['active_issues'] ?> / 3</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm p-3 bg-white rounded-3">
                <div class="text-muted small">Pending Requests</div>
                <div class="h3 fw-bold mb-0 mt-1"><?= (int)$stats['pending_requests'] ?></div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm p-3 bg-white rounded-3">
                <div class="text-muted small">Unpaid Fine / Rent</div>
                <div class="h3 fw-bold mb-0 mt-1 text-danger"><?= money($stats['unpaid_fines']) ?></div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="h5 fw-bold mb-0">Newly Added Books</h3>
        <a href="<?= esc(route_url('books')) ?>" class="btn btn-sm btn-outline-primary">Browse All Books</a>
    </div>
    <div class="row g-3">
        <?php foreach ($latestBooks as $b): ?>
            <div class="col-md-4 col-sm-6">
                <div class="card border-0 shadow-sm h-100 p-3 rounded-3">
                    <h5 class="h6 fw-bold mb-1"><?= esc($b['title']) ?></h5>
                    <p class="text-muted small mb-2">by <?= esc($b['author']) ?></p>
                    <div class="d-flex justify-content-between align-items-center mt-auto pt-2 border-top">
                        <span class="badge bg-light text-dark border"><?= esc($b['category_name'] ?? 'General') ?></span>
                        <a href="<?= esc(route_url('book/view', ['id' => $b['id']])) ?>" class="btn btn-sm btn-primary">Details</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    render('Student Dashboard', ob_get_clean());
}

function student_profile_page() {
    require_student();
    $studentId = (int) current_student()['id'];
    $student = library()->studentById($studentId);

    if (is_post()) {
        verify_csrf();
        library()->updateStudentProfile($studentId, [
            'full_name' => $_POST['full_name'] ?? '',
            'phone_number' => $_POST['phone_number'] ?? '',
            'department' => $_POST['department'] ?? '',
            'semester' => $_POST['semester'] ?? '',
        ]);
        $_SESSION['student'] = library()->studentById($studentId);
        flash('success', 'Profile updated successfully.');
        redirect_to('student/profile');
    }

    $depts = ['Computer Science', 'Information Technology', 'Electronics', 'Mechanical', 'Civil', 'Electrical', 'Chemical', 'Other'];
    ob_start();
    ?>
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm p-4 rounded-4">
                <h3 class="h5 fw-bold mb-4">My Student Profile</h3>
                <form method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Enrollment Number (Read Only)</label>
                        <input type="text" class="form-control" value="<?= esc($student['enrollment_number']) ?>" readonly disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control" value="<?= esc($student['full_name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email (Read Only)</label>
                        <input type="email" class="form-control" value="<?= esc($student['email']) ?>" readonly disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone Number (WhatsApp)</label>
                        <input type="tel" name="phone_number" class="form-control" value="<?= esc($student['phone_number']) ?>" required>
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Department</label>
                            <select name="department" class="form-select" required>
                                <?php foreach ($depts as $d): ?>
                                    <option value="<?= esc($d) ?>" <?= $student['department'] === $d ? 'selected' : '' ?>><?= esc($d) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Semester</label>
                            <select name="semester" class="form-select" required>
                                <?php for ($i = 1; $i <= 8; $i++): ?>
                                    <option value="Semester <?= $i ?>" <?= $student['semester'] === "Semester {$i}" ? 'selected' : '' ?>>Semester <?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <button class="btn btn-primary w-100 fw-semibold">Save Profile Changes</button>
                </form>
            </div>
        </div>
    </div>
    <?php
    render('Profile', ob_get_clean());
}

function books_page() {
    require_student();
    $q = trim($_GET['q'] ?? '');
    $catId = !empty($_GET['category_id']) ? (int)$_GET['category_id'] : null;

    $books = library()->books(['q' => $q, 'category_id' => $catId]);
    $categories = library()->categories();

    ob_start();
    ?>
    <form class="row g-2 mb-4" method="get">
        <input type="hidden" name="route" value="books">
        <div class="col-md-6">
            <input type="text" name="q" class="form-control" value="<?= esc($q) ?>" placeholder="Search by title, author, or publisher...">
        </div>
        <div class="col-md-4">
            <select name="category_id" class="form-select">
                <option value="">All Categories</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $catId === (int)$c['id'] ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-primary w-100">Search</button>
        </div>
    </form>

    <div class="table-responsive bg-white rounded-3 shadow-sm">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Title</th>
                    <th>Author</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Available Copies</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($books)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No books found matching your criteria.</td></tr>
                <?php else: ?>
                    <?php foreach ($books as $b): ?>
                        <tr>
                            <td class="fw-bold"><?= esc($b['title']) ?></td>
                            <td><?= esc($b['author']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= esc($b['category_name'] ?? 'Uncategorized') ?></span></td>
                            <td><?= money($b['price']) ?></td>
                            <td><?= (int)$b['available_copies'] ?> / <?= (int)$b['total_copies'] ?></td>
                            <td>
                                <a href="<?= esc(route_url('book/view', ['id' => $b['id']])) ?>" class="btn btn-sm btn-outline-primary">View & Request</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    render('Library Books Catalogue', ob_get_clean());
}

function book_view_page() {
    require_student();
    $id = (int)($_GET['id'] ?? 0);
    $book = library()->bookById($id);
    if (!$book) {
        flash('danger', 'Book not found.');
        redirect_to('books');
    }
    $studentId = (int)current_student()['id'];

    ob_start();
    ?>
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm p-4 rounded-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h2 class="h3 fw-bold mb-1"><?= esc($book['title']) ?></h2>
                        <p class="text-muted fs-5">by <?= esc($book['author']) ?></p>
                    </div>
                    <span class="badge bg-primary fs-6"><?= esc($book['category_name'] ?? 'General') ?></span>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-sm-4">
                        <div class="p-3 bg-light rounded-3 text-center">
                            <div class="text-muted small">Price</div>
                            <div class="fw-bold fs-5"><?= money($book['price']) ?></div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="p-3 bg-light rounded-3 text-center">
                            <div class="text-muted small">Rent (14 Days)</div>
                            <div class="fw-bold fs-5"><?= money(rent_for_price($book['price'])) ?></div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="p-3 bg-light rounded-3 text-center">
                            <div class="text-muted small">Available Stock</div>
                            <div class="fw-bold fs-5"><?= (int)$book['available_copies'] ?> copies</div>
                        </div>
                    </div>
                </div>

                <form method="post" action="<?= esc(route_url('book/request', ['id' => $book['id']])) ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-primary btn-lg w-100 fw-semibold" <?= (int)$book['available_copies'] < 1 ? 'disabled' : '' ?>>
                        <?= (int)$book['available_copies'] > 0 ? 'Request to Borrow Book' : 'Currently Unavailable' ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php
    render($book['title'], ob_get_clean());
}

function book_request_page() {
    require_student();
    if (!is_post()) redirect_to('books');
    verify_csrf();

    $bookId = (int)($_GET['id'] ?? 0);
    $studentId = (int)current_student()['id'];

    $result = library()->createBookRequest($studentId, $bookId);
    flash($result['ok'] ? 'success' : 'danger', $result['message'] ?? 'Unable to process request.');
    redirect_to('my-requests');
}

function my_requests_page() {
    require_student();
    $studentId = (int)current_student()['id'];
    $requests = library()->studentRequests($studentId);

    ob_start();
    ?>
    <div class="table-responsive bg-white rounded-3 shadow-sm">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Book Title</th>
                    <th>Author</th>
                    <th>Requested On</th>
                    <th>Status</th>
                    <th>Notes / Reason</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($requests)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">You have no book requests.</td></tr>
                <?php else: ?>
                    <?php foreach ($requests as $r): ?>
                        <tr>
                            <td class="fw-bold"><?= esc($r['book_title']) ?></td>
                            <td><?= esc($r['book_author']) ?></td>
                            <td><?= format_datetime($r['requested_at']) ?></td>
                            <td><?= status_badge($r['status']) ?></td>
                            <td><?= esc($r['rejection_reason'] ?: '—') ?></td>
                            <td>
                                <?php if ($r['status'] === 'Pending'): ?>
                                    <form method="post" action="<?= esc(route_url('request/cancel', ['id' => $r['id']])) ?>" class="d-inline" onsubmit="return confirm('Are you sure you want to cancel this request?');">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline-danger">Cancel</button>
                                    </form>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    render('My Book Requests', ob_get_clean());
}

function request_cancel_page() {
    require_student();
    if (!is_post()) redirect_to('my-requests');
    verify_csrf();

    $requestId = (int)($_GET['id'] ?? 0);
    $studentId = (int)current_student()['id'];

    $result = library()->cancelRequest($requestId, $studentId);
    flash($result['ok'] ? 'success' : 'danger', $result['message'] ?? 'Unable to cancel request.');
    redirect_to('my-requests');
}

function my_books_page() {
    require_student();
    $studentId = (int)current_student()['id'];
    $issues = library()->studentActiveIssues($studentId);

    ob_start();
    ?>
    <div class="table-responsive bg-white rounded-3 shadow-sm">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Book Title</th>
                    <th>Issue Date</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Rent</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($issues)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">You have no active issued books.</td></tr>
                <?php else: ?>
                    <?php foreach ($issues as $i): ?>
                        <tr>
                            <td class="fw-bold"><?= esc($i['book_title']) ?></td>
                            <td><?= format_date($i['issue_date']) ?></td>
                            <td><?= format_date($i['due_date']) ?></td>
                            <td>
                                <?= strtotime($i['due_date']) < strtotime(date('Y-m-d')) ? status_badge('Overdue') : status_badge('Issued') ?>
                            </td>
                            <td><?= money(rent_for_price($i['price'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    render('My Issued Books', ob_get_clean());
}

function my_history_page() {
    require_student();
    $studentId = (int)current_student()['id'];
    $issues = library()->studentHistoryIssues($studentId);

    ob_start();
    ?>
    <div class="table-responsive bg-white rounded-3 shadow-sm">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Book Title</th>
                    <th>Issue Date</th>
                    <th>Return Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($issues)): ?>
                    <tr><td colspan="4" class="text-center text-muted py-4">No borrowing history found.</td></tr>
                <?php else: ?>
                    <?php foreach ($issues as $i): ?>
                        <tr>
                            <td class="fw-bold"><?= esc($i['book_title']) ?></td>
                            <td><?= format_date($i['issue_date']) ?></td>
                            <td><?= format_date($i['return_date']) ?></td>
                            <td><?= status_badge($i['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    render('Borrowing History', ob_get_clean());
}

function my_fines_page() {
    require_student();
    $studentId = (int)current_student()['id'];
    $fines = library()->finesForStudent($studentId);

    ob_start();
    ?>
    <div class="table-responsive bg-white rounded-3 shadow-sm">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Fine Type</th>
                    <th>Description</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($fines)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">No fines recorded. You have a clear record!</td></tr>
                <?php else: ?>
                    <?php foreach ($fines as $f): ?>
                        <tr>
                            <td class="fw-bold"><?= esc(ucwords(str_replace('_', ' ', $f['fine_type']))) ?></td>
                            <td><?= esc($f['description'] ?? '—') ?></td>
                            <td class="fw-bold text-danger"><?= money($f['amount']) ?></td>
                            <td><?= status_badge($f['status']) ?></td>
                            <td>
                                <?php if ($f['status'] === 'Unpaid'): ?>
                                    <a href="<?= esc(route_url('student/pay-fine', ['fine_id' => $f['id']])) ?>" class="btn btn-sm btn-success"><i class="bi bi-credit-card me-1"></i> Pay Online (Razorpay)</a>
                                <?php else: ?>
                                    <?php
                                    $paymentId = $f['payment_id'] ?? null;
                                    if (!$paymentId) {
                                        $p = library()->paymentByFineId((int)$f['id']);
                                        $paymentId = $p['id'] ?? null;
                                    }
                                    ?>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="bi bi-check-circle-fill me-1"></i> Paid</span>
                                        <?php if ($paymentId): ?>
                                            <a href="<?= esc(route_url('receipt-pdf', ['id' => $paymentId])) ?>" target="_blank" class="btn btn-sm btn-outline-primary py-1 px-2">
                                                <i class="bi bi-file-earmark-pdf me-1"></i> Download Receipt
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    render('My Fines & Dues', ob_get_clean());
}

function student_pay_fine_page() {
    require_student();
    $fineId = (int)($_GET['fine_id'] ?? 0);
    $student = library()->studentById((int)current_student()['id']);

    $fine = db()->fetch("SELECT * FROM fines WHERE id = :id AND student_id = :sid AND status = 'Unpaid' LIMIT 1", [
        'id' => $fineId,
        'sid' => $student['id']
    ]);

    if (!$fine) {
        flash('danger', 'Fine record not found or already paid.');
        redirect_to('my-fines');
    }

    $order = razorpay()->createOrder((float)$fine['amount'], (int)$fine['id'], (int)$student['id']);
    if (!$order['ok']) {
        flash('danger', $order['message'] ?? 'Unable to initiate Razorpay order.');
        redirect_to('my-fines');
    }

    ob_start();
    ?>
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm p-4 rounded-4 text-center">
                <h3 class="h4 fw-bold mb-2">Online Fine Payment</h3>
                <p class="text-muted mb-4">Secure payment via Razorpay (UPI, Credit/Debit Cards, Net Banking)</p>
                <div class="p-3 bg-light rounded-3 mb-4 text-start">
                    <div class="d-flex justify-content-between mb-2"><span>Fine Type:</span><strong><?= esc(ucwords(str_replace('_', ' ', $fine['fine_type']))) ?></strong></div>
                    <div class="d-flex justify-content-between mb-2"><span>Description:</span><strong><?= esc($fine['description']) ?></strong></div>
                    <div class="d-flex justify-content-between text-danger fs-5 fw-bold border-top pt-2"><span>Total Amount:</span><span><?= money($fine['amount']) ?></span></div>
                </div>

                <button id="rzp-button1" class="btn btn-primary btn-lg w-100 fw-semibold mb-3">Pay <?= money($fine['amount']) ?> Now</button>
                <a href="<?= esc(route_url('my-fines')) ?>" class="btn btn-link text-muted">Cancel and return</a>

                <!-- Razorpay Callback Form -->
                <form id="razorpay_form" method="post" action="<?= esc(route_url('student/payments/razorpay-callback')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="fine_id" value="<?= (int)$fine['id'] ?>">
                    <input type="hidden" name="razorpay_order_id" id="razorpay_order_id">
                    <input type="hidden" name="razorpay_payment_id" id="razorpay_payment_id">
                    <input type="hidden" name="razorpay_signature" id="razorpay_signature">
                </form>
            </div>
        </div>
    </div>

    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
    var options = {
        "key": "<?= esc(app_config('razorpay.key_id')) ?>",
        "amount": "<?= (int)($order['amount']) ?>",
        "currency": "INR",
        "name": "LibraryHub",
        "description": "Fine Payment - <?= esc($fine['fine_type']) ?>",
        "order_id": "<?= esc($order['id']) ?>",
        "handler": function (response){
            document.getElementById('razorpay_payment_id').value = response.razorpay_payment_id;
            document.getElementById('razorpay_order_id').value = response.razorpay_order_id;
            document.getElementById('razorpay_signature').value = response.razorpay_signature;
            document.getElementById('razorpay_form').submit();
        },
        "prefill": {
            "name": "<?= esc($student['full_name']) ?>",
            "email": "<?= esc($student['email']) ?>",
            "contact": "<?= esc($student['phone_number']) ?>"
        },
        "theme": {
            "color": "#4f46e5"
        }
    };
    var rzp1 = new Razorpay(options);
    document.getElementById('rzp-button1').onclick = function(e){
        rzp1.open();
        e.preventDefault();
    }
    </script>
    <?php
    render('Pay Fine Online', ob_get_clean());
}

function student_razorpay_callback_page() {
    require_student();
    if (!is_post()) redirect_to('my-fines');
    verify_csrf();

    $studentId = (int)current_student()['id'];
    $fineId = (int)($_POST['fine_id'] ?? 0);
    $orderId = trim($_POST['razorpay_order_id'] ?? '');
    $paymentId = trim($_POST['razorpay_payment_id'] ?? '');
    $signature = trim($_POST['razorpay_signature'] ?? '');

    $result = razorpay()->completePayment($fineId, $studentId, $orderId, $paymentId, $signature);
    if ($result['ok']) {
        flash('success', 'Payment successful! Digital receipt has been generated.');
        redirect_to('student/receipts');
    }

    flash('danger', $result['message'] ?? 'Payment completion failed.');
    redirect_to('my-fines');
}

function student_receipts_page() {
    require_student();
    $studentId = (int)current_student()['id'];
    $payments = library()->studentPayments($studentId);

    ob_start();
    ?>
    <div class="table-responsive bg-white rounded-3 shadow-sm">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Receipt No.</th>
                    <th>Date</th>
                    <th>Method</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payments)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No completed payment receipts found.</td></tr>
                <?php else: ?>
                    <?php foreach ($payments as $p): ?>
                        <tr>
                            <td class="fw-bold">RCP-<?= sprintf('%06d', $p['id']) ?></td>
                            <td><?= format_datetime($p['created_at']) ?></td>
                            <td><?= esc(ucfirst($p['payment_method'])) ?></td>
                            <td class="fw-bold"><?= money($p['amount']) ?></td>
                            <td><?= status_badge($p['status']) ?></td>
                            <td>
                                <a href="<?= esc(route_url('receipt-pdf', ['id' => $p['id']])) ?>" target="_blank" class="btn btn-sm btn-primary py-1 px-3 fw-semibold">
                                    <i class="bi bi-file-earmark-pdf-fill me-1"></i> Download PDF Receipt
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    render('Payment Receipts', ob_get_clean());
}

function receipt_pdf_page() {
    $id = (int)($_GET['id'] ?? 0);
    $html = receipt_service()->renderReceiptHtml($id);

    if (!$html) {
        http_response_code(404);
        exit('Receipt not found.');
    }

    echo $html;
    exit;
}

function recommendations_page() {
    require_student();
    $studentId = (int)current_student()['id'];

    if (is_post()) {
        verify_csrf();
        $result = library()->createRecommendation($studentId, [
            'book_name' => trim($_POST['book_name'] ?? ''),
            'author' => trim($_POST['author'] ?? ''),
            'publisher' => trim($_POST['publisher'] ?? ''),
            'reason' => trim($_POST['reason'] ?? ''),
        ]);
        flash($result['ok'] ? 'success' : 'danger', $result['message'] ?? 'Failed to submit recommendation.');
        redirect_to('recommendations');
    }

    $recs = library()->studentRecommendations($studentId);
    ob_start();
    ?>
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm p-4 rounded-4">
                <h3 class="h5 fw-bold mb-3">Recommend a Book</h3>
                <form method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Book Name *</label>
                        <input type="text" name="book_name" class="form-control" required placeholder="e.g. Design Patterns">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Author *</label>
                        <input type="text" name="author" class="form-control" required placeholder="e.g. Erich Gamma">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Publisher (Optional)</label>
                        <input type="text" name="publisher" class="form-control" placeholder="e.g. Addison-Wesley">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason for Recommendation</label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="Why should the library add this book?"></textarea>
                    </div>
                    <button class="btn btn-primary w-100 fw-semibold">Submit Recommendation</button>
                </form>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="table-responsive bg-white rounded-3 shadow-sm">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Book & Author</th>
                            <th>Status</th>
                            <th>Librarian Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recs)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-4">No recommendations submitted yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recs as $r): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= esc($r['book_name']) ?></div>
                                        <div class="text-muted small">by <?= esc($r['author']) ?></div>
                                    </td>
                                    <td><?= status_badge($r['status']) ?></td>
                                    <td><?= esc($r['librarian_note'] ?? '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
    render('Book Recommendations', ob_get_clean());
}

function notifications_page() {
    require_student();
    $studentId = (int)current_student()['id'];
    library()->markAllNotificationsRead($studentId);
    $notifications = library()->notifications($studentId);

    ob_start();
    ?>
    <div class="list-group shadow-sm border-0 rounded-4">
        <?php if (empty($notifications)): ?>
            <div class="p-4 text-center text-muted bg-white rounded-4">No notifications.</div>
        <?php else: ?>
            <?php foreach ($notifications as $n): ?>
                <div class="list-group-item p-3 border-bottom border-light">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <h5 class="h6 fw-bold mb-0"><?= esc($n['title']) ?></h5>
                        <small class="text-muted"><?= format_datetime($n['created_at']) ?></small>
                    </div>
                    <p class="text-secondary mb-0 small"><?= esc($n['message']) ?></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php
    render('In-App Notifications', ob_get_clean());
}

// ─── LIBRARIAN CONTROLLER FUNCTIONS ───────────────────────────────────────────

function librarian_dashboard() {
    require_librarian();
    $stats = library()->librarianStats();

    ob_start();
    ?>
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm p-3 bg-white rounded-3">
                <div class="text-muted small">Total Books</div>
                <div class="h3 fw-bold mb-0 mt-1"><?= (int)$stats['total_books'] ?></div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm p-3 bg-white rounded-3">
                <div class="text-muted small">Active Issues</div>
                <div class="h3 fw-bold mb-0 mt-1"><?= (int)$stats['active_issues'] ?></div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm p-3 bg-white rounded-3">
                <div class="text-muted small">Pending Requests</div>
                <div class="h3 fw-bold mb-0 mt-1 text-warning"><?= (int)$stats['pending_requests'] ?></div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm p-3 bg-white rounded-3">
                <div class="text-muted small">Total Revenue</div>
                <div class="h3 fw-bold mb-0 mt-1 text-success"><?= money($stats['total_revenue']) ?></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm p-3 rounded-3">
                <h4 class="h6 fw-bold mb-3">Quick Navigation</h4>
                <div class="d-grid gap-2">
                    <a href="<?= esc(route_url('librarian/requests')) ?>" class="btn btn-outline-primary text-start">Review Pending Requests (<?= (int)$stats['pending_requests'] ?>)</a>
                    <a href="<?= esc(route_url('librarian/returns')) ?>" class="btn btn-outline-primary text-start">Process Returns & Lost Books</a>
                    <a href="<?= esc(route_url('librarian/books/add')) ?>" class="btn btn-outline-success text-start">+ Add New Book</a>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm p-3 rounded-3">
                <h4 class="h6 fw-bold mb-3">System Overview</h4>
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between px-0"><span>Registered Students</span><strong><?= (int)$stats['total_students'] ?></strong></li>
                    <li class="list-group-item d-flex justify-content-between px-0"><span>Overdue Books</span><strong class="text-danger"><?= (int)$stats['overdue_issues'] ?></strong></li>
                </ul>
            </div>
        </div>
    </div>
    <?php
    render('Librarian Dashboard', ob_get_clean());
}

function librarian_books_page() {
    require_librarian();
    $q = trim($_GET['q'] ?? '');
    $books = library()->books(['q' => $q]);

    ob_start();
    ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <form method="get" class="d-flex gap-2 col-md-6">
            <input type="hidden" name="route" value="librarian/books">
            <input type="text" name="q" class="form-control" value="<?= esc($q) ?>" placeholder="Search books...">
            <button class="btn btn-primary">Search</button>
        </form>
        <a href="<?= esc(route_url('librarian/books/add')) ?>" class="btn btn-success">+ Add Book</a>
    </div>

    <div class="table-responsive bg-white rounded-3 shadow-sm">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Title</th>
                    <th>Author</th>
                    <th>Price</th>
                    <th>Copies (Avail/Total)</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($books as $b): ?>
                    <tr>
                        <td class="fw-bold"><?= esc($b['title']) ?></td>
                        <td><?= esc($b['author']) ?></td>
                        <td><?= money($b['price']) ?></td>
                        <td><?= (int)$b['available_copies'] ?> / <?= (int)$b['total_copies'] ?></td>
                        <td>
                            <a href="<?= esc(route_url('librarian/books/edit', ['id' => $b['id']])) ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                            <form method="post" action="<?= esc(route_url('librarian/books/delete', ['id' => $b['id']])) ?>" class="d-inline" onsubmit="return confirm('Archive this book?');">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-danger">Archive</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    render('Book Inventory', ob_get_clean());
}

function librarian_book_add_page() {
    require_librarian();

    if (is_post()) {
        verify_csrf();
        $result = library()->createBook([
            'title' => $_POST['title'] ?? '',
            'author' => $_POST['author'] ?? '',
            'publisher' => $_POST['publisher'] ?? '',
            'price' => $_POST['price'] ?? 0,
            'total_copies' => $_POST['total_copies'] ?? 1,
            'category_id' => $_POST['category_id'] ?? null,
        ]);
        flash($result['ok'] ? 'success' : 'danger', $result['message'] ?? 'Failed to add book.');
        redirect_to('librarian/books');
    }

    $categories = library()->categories();
    ob_start();
    ?>
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm p-4 rounded-4">
                <h3 class="h5 fw-bold mb-3">Add New Book</h3>
                <form method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3"><label class="form-label">Title *</label><input type="text" name="title" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Author *</label><input type="text" name="author" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Publisher</label><input type="text" name="publisher" class="form-control"></div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6"><label class="form-label">Price (₹) *</label><input type="number" step="0.01" name="price" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">Total Copies *</label><input type="number" name="total_copies" class="form-control" value="1" min="1" required></div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-select">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= esc($c['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn btn-primary w-100 fw-semibold">Save Book</button>
                </form>
            </div>
        </div>
    </div>
    <?php
    render('Add Book', ob_get_clean());
}

function librarian_book_edit_page() {
    require_librarian();
    $id = (int)($_GET['id'] ?? 0);
    $book = library()->bookById($id);
    if (!$book) {
        flash('danger', 'Book not found.');
        redirect_to('librarian/books');
    }

    if (is_post()) {
        verify_csrf();
        $result = library()->updateBook($id, [
            'title' => $_POST['title'] ?? '',
            'author' => $_POST['author'] ?? '',
            'publisher' => $_POST['publisher'] ?? '',
            'price' => $_POST['price'] ?? 0,
            'total_copies' => $_POST['total_copies'] ?? 1,
            'category_id' => $_POST['category_id'] ?? null,
        ]);
        flash($result['ok'] ? 'success' : 'danger', $result['message'] ?? 'Failed to update book.');
        redirect_to('librarian/books');
    }

    $categories = library()->categories();
    ob_start();
    ?>
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm p-4 rounded-4">
                <h3 class="h5 fw-bold mb-3">Edit Book</h3>
                <form method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3"><label class="form-label">Title *</label><input type="text" name="title" class="form-control" value="<?= esc($book['title']) ?>" required></div>
                    <div class="mb-3"><label class="form-label">Author *</label><input type="text" name="author" class="form-control" value="<?= esc($book['author']) ?>" required></div>
                    <div class="mb-3"><label class="form-label">Publisher</label><input type="text" name="publisher" class="form-control" value="<?= esc($book['publisher']) ?>"></div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6"><label class="form-label">Price (₹) *</label><input type="number" step="0.01" name="price" class="form-control" value="<?= $book['price'] ?>" required></div>
                        <div class="col-md-6"><label class="form-label">Total Copies *</label><input type="number" name="total_copies" class="form-control" value="<?= $book['total_copies'] ?>" min="1" required></div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-select">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= (int)$book['category_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn btn-primary w-100 fw-semibold">Update Book</button>
                </form>
            </div>
        </div>
    </div>
    <?php
    render('Edit Book', ob_get_clean());
}

function librarian_book_delete_page() {
    require_librarian();
    if (!is_post()) redirect_to('librarian/books');
    verify_csrf();

    $id = (int)($_GET['id'] ?? 0);
    library()->archiveBook($id);
    flash('success', 'Book archived successfully.');
    redirect_to('librarian/books');
}

function librarian_categories_page() {
    require_librarian();
    if (is_post()) {
        verify_csrf();
        $result = library()->createCategory(['name' => $_POST['name'] ?? '', 'description' => $_POST['description'] ?? '']);
        flash($result['ok'] ? 'success' : 'danger', $result['message'] ?? 'Failed to create category.');
        redirect_to('librarian/categories');
    }

    $categories = library()->categories();
    ob_start();
    ?>
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm p-4 rounded-4">
                <h3 class="h5 fw-bold mb-3">Add Category</h3>
                <form method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3"><label class="form-label">Category Name *</label><input type="text" name="name" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"></textarea></div>
                    <button class="btn btn-primary w-100 fw-semibold">Create Category</button>
                </form>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="table-responsive bg-white rounded-3 shadow-sm">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Category Name</th><th>Description</th><th>Books Count</th></tr></thead>
                    <tbody>
                        <?php foreach ($categories as $c): ?>
                            <tr><td class="fw-bold"><?= esc($c['name']) ?></td><td><?= esc($c['description'] ?? '—') ?></td><td><?= (int)$c['book_count'] ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
    render('Book Categories', ob_get_clean());
}

function librarian_requests_page() {
    require_librarian();
    $requests = library()->pendingRequests('Pending');

    ob_start();
    ?>
    <div class="table-responsive bg-white rounded-3 shadow-sm">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Student Name</th>
                    <th>Book Title</th>
                    <th>Requested On</th>
                    <th>Available Copies</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($requests)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">No pending requests to review.</td></tr>
                <?php else: ?>
                    <?php foreach ($requests as $r): ?>
                        <tr>
                            <td class="fw-bold"><?= esc($r['student_name']) ?></td>
                            <td><?= esc($r['book_title']) ?></td>
                            <td><?= format_datetime($r['requested_at']) ?></td>
                            <td><?= (int)($r['available_copies'] ?? 0) ?></td>
                            <td>
                                <!-- Approve Modal Button -->
                                <button type="button" class="btn btn-sm btn-success me-1" data-bs-toggle="modal" data-bs-target="#approveModal<?= $r['id'] ?>">Approve</button>
                                <!-- Reject Modal Button -->
                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?= $r['id'] ?>">Reject</button>
                            </td>
                        </tr>

                        <!-- Approve Modal -->
                        <div class="modal fade" id="approveModal<?= $r['id'] ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="post" action="<?= esc(route_url('librarian/requests/approve', ['id' => $r['id']])) ?>">
                                        <?= csrf_field() ?>
                                        <div class="modal-header"><h5 class="modal-title">Approve Book Request</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                        <div class="modal-body">
                                            <p>Approve <strong><?= esc($r['book_title']) ?></strong> for <strong><?= esc($r['student_name']) ?></strong>.</p>
                                            <div class="mb-3"><label class="form-label">Issue Date</label><input type="date" name="issue_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                                        </div>
                                        <div class="modal-footer"><button class="btn btn-success">Confirm Approval</button></div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Reject Modal -->
                        <div class="modal fade" id="rejectModal<?= $r['id'] ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="post" action="<?= esc(route_url('librarian/requests/reject', ['id' => $r['id']])) ?>">
                                        <?= csrf_field() ?>
                                        <div class="modal-header"><h5 class="modal-title">Reject Book Request</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                        <div class="modal-body">
                                            <div class="mb-3"><label class="form-label">Rejection Reason *</label><textarea name="reason" class="form-control" required rows="3" placeholder="Specify reason..."></textarea></div>
                                        </div>
                                        <div class="modal-footer"><button class="btn btn-danger">Confirm Rejection</button></div>
                                    </form>
                                </div>
                            </div>
                        </div>

                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    render('Pending Requests', ob_get_clean());
}

function librarian_approve_request_page() {
    require_librarian();
    if (!is_post()) redirect_to('librarian/requests');
    verify_csrf();

    $id = (int)($_GET['id'] ?? 0);
    $issueDate = trim($_POST['issue_date'] ?? date('Y-m-d'));

    $result = library()->approveRequest($id, $issueDate);
    flash($result['ok'] ? 'success' : 'danger', $result['message'] ?? 'Unable to approve request.');
    redirect_to('librarian/requests');
}

function librarian_reject_request_page() {
    require_librarian();
    if (!is_post()) redirect_to('librarian/requests');
    verify_csrf();

    $id = (int)($_GET['id'] ?? 0);
    $reason = trim($_POST['reason'] ?? 'Not specified');

    $result = library()->rejectRequest($id, $reason);
    flash($result['ok'] ? 'success' : 'danger', $result['message'] ?? 'Unable to reject request.');
    redirect_to('librarian/requests');
}

function librarian_hold_request_page() {
    require_librarian();
    redirect_to('librarian/requests');
}

function librarian_issues_page() {
    require_librarian();
    $issues = library()->issues(['active_only' => true]);

    ob_start();
    ?>
    <div class="table-responsive bg-white rounded-3 shadow-sm">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Student Name</th>
                    <th>Book Title</th>
                    <th>Issue Date</th>
                    <th>Due Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($issues as $i): ?>
                    <tr>
                        <td class="fw-bold"><?= esc($i['student_name']) ?></td>
                        <td><?= esc($i['book_title']) ?></td>
                        <td><?= format_date($i['issue_date']) ?></td>
                        <td><?= format_date($i['due_date']) ?></td>
                        <td><?= strtotime($i['due_date']) < strtotime(date('Y-m-d')) ? status_badge('Overdue') : status_badge('Issued') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    render('Active Book Issues', ob_get_clean());
}

function librarian_returns_page() {
    require_librarian();
    $issues = library()->issues(['active_only' => true]);

    ob_start();
    ?>
    <div class="table-responsive bg-white rounded-3 shadow-sm">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Student Name</th>
                    <th>Book Title</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($issues as $i): ?>
                    <tr>
                        <td class="fw-bold"><?= esc($i['student_name']) ?></td>
                        <td><?= esc($i['book_title']) ?></td>
                        <td><?= format_date($i['due_date']) ?></td>
                        <td><?= strtotime($i['due_date']) < strtotime(date('Y-m-d')) ? status_badge('Overdue') : status_badge('Issued') ?></td>
                        <td>
                            <a href="<?= esc(route_url('librarian/returns/process', ['id' => $i['id']])) ?>" class="btn btn-sm btn-primary">Process Return / Lost</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    render('Process Book Return', ob_get_clean());
}

function librarian_return_process_page() {
    require_librarian();
    $id = (int)($_GET['id'] ?? 0);
    $issue = library()->issueById($id);

    if (!$issue) {
        flash('danger', 'Issue record not found.');
        redirect_to('librarian/returns');
    }

    $overdueDays = max(0, (int) floor((strtotime(date('Y-m-d')) - strtotime($issue['due_date'])) / 86400));
    $rent = rent_for_price($issue['price']);
    $lateFine = late_fine_for_price($issue['price'], $overdueDays);

    ob_start();
    ?>
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm p-4 rounded-4">
                <h3 class="h4 fw-bold mb-3">Process Book Return</h3>
                <div class="p-3 bg-light rounded-3 mb-4">
                    <div class="row g-2">
                        <div class="col-md-6"><strong>Student:</strong> <?= esc($issue['student_name']) ?></div>
                        <div class="col-md-6"><strong>Book:</strong> <?= esc($issue['book_title']) ?></div>
                        <div class="col-md-6"><strong>Book Price:</strong> <?= money($issue['price']) ?></div>
                        <div class="col-md-6"><strong>Rent Amount:</strong> <?= money($rent) ?></div>
                        <div class="col-md-6"><strong>Overdue Days:</strong> <?= $overdueDays ?> days</div>
                        <div class="col-md-6"><strong>Late Fine:</strong> <?= money($lateFine) ?></div>
                    </div>
                </div>

                <form method="post" action="<?= esc(route_url('librarian/returns/complete', ['id' => $issue['id']])) ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Return Condition *</label>
                        <select name="condition" class="form-select" required id="conditionSelect">
                            <option value="Good">Good Condition</option>
                            <option value="Damaged">Damaged Book</option>
                            <option value="Lost">Lost Book (Student Pays Full Book Price)</option>
                        </select>
                    </div>

                    <div class="mb-3" id="damageFineGroup" style="display:none;">
                        <label class="form-label">Damage Fine Amount (₹)</label>
                        <input type="number" step="0.01" name="damage_fine" class="form-control" value="0.00">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Payment Mode *</label>
                        <select name="payment_mode" class="form-select" required>
                            <option value="offline">Offline (Cash Collected at Desk)</option>
                            <option value="online">Online (Pending - Student Pays via Razorpay Dashboard)</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Librarian Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>

                    <button class="btn btn-primary w-100 py-2 fw-semibold">Complete Return Processing</button>
                </form>
            </div>
        </div>
    </div>

    <script>
    document.getElementById('conditionSelect').addEventListener('change', function() {
        document.getElementById('damageFineGroup').style.display = this.value === 'Damaged' ? 'block' : 'none';
    });
    </script>
    <?php
    render('Complete Return', ob_get_clean());
}

function librarian_return_complete_page() {
    require_librarian();
    if (!is_post()) redirect_to('librarian/returns');
    verify_csrf();

    $id = (int)($_GET['id'] ?? 0);
    $condition = trim($_POST['condition'] ?? 'Good');
    $paymentMode = trim($_POST['payment_mode'] ?? 'offline');
    $damageFine = (float)($_POST['damage_fine'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    $result = library()->processReturn($id, $condition, $paymentMode, $damageFine, $notes);
    flash($result['ok'] ? 'success' : 'danger', $result['message'] ?? 'Unable to process return.');
    redirect_to('librarian/returns');
}

function librarian_students_page() {
    require_librarian();
    $q = trim($_GET['q'] ?? '');
    $students = $q === ''
        ? db()->fetchAll('SELECT * FROM students ORDER BY full_name ASC')
        : db()->fetchAll('SELECT * FROM students WHERE full_name LIKE :q OR enrollment_number LIKE :q OR email LIKE :q ORDER BY full_name ASC', ['q' => "%{$q}%"]);

    ob_start();
    ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <form method="get" class="d-flex gap-2 col-md-6">
            <input type="hidden" name="route" value="librarian/students">
            <input type="text" name="q" class="form-control" value="<?= esc($q) ?>" placeholder="Search student by name, enrollment, email...">
            <button class="btn btn-primary">Search</button>
        </form>
    </div>

    <div class="table-responsive bg-white rounded-3 shadow-sm">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Enrollment</th>
                    <th>Department</th>
                    <th>Phone</th>
                    <th>Behaviour Score</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $s): ?>
                    <tr>
                        <td class="fw-bold"><?= esc($s['full_name']) ?></td>
                        <td><?= esc($s['enrollment_number']) ?></td>
                        <td><?= esc($s['department']) ?></td>
                        <td><?= esc($s['phone_number']) ?></td>
                        <td><span class="fw-bold"><?= (int)$s['behaviour_score'] ?></span></td>
                        <td><?= status_badge($s['account_status']) ?></td>
                        <td>
                            <a href="<?= esc(route_url('librarian/students/view', ['id' => $s['id']])) ?>" class="btn btn-sm btn-outline-primary">View Student</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    render('Student Directory', ob_get_clean());
}

function librarian_student_view_page() {
    require_librarian();
    $id = (int)($_GET['id'] ?? 0);
    $student = library()->studentById($id);
    if (!$student) {
        flash('danger', 'Student record not found.');
        redirect_to('librarian/students');
    }

    $activeIssues = library()->studentActiveIssues($id);
    $fines = library()->finesForStudent($id);
    $historyLogs = db()->fetchAll('SELECT * FROM behaviour_logs WHERE student_id = :sid ORDER BY created_at DESC', ['sid' => $id]);

    ob_start();
    ?>
    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm p-4 rounded-4">
                <h3 class="h5 fw-bold mb-3"><?= esc($student['full_name']) ?></h3>
                <ul class="list-group list-group-flush mb-4 small">
                    <li class="list-group-item d-flex justify-content-between px-0"><span>Enrollment:</span><strong><?= esc($student['enrollment_number']) ?></strong></li>
                    <li class="list-group-item d-flex justify-content-between px-0"><span>Department:</span><strong><?= esc($student['department']) ?></strong></li>
                    <li class="list-group-item d-flex justify-content-between px-0"><span>Semester:</span><strong><?= esc($student['semester']) ?></strong></li>
                    <li class="list-group-item d-flex justify-content-between px-0"><span>Email:</span><strong><?= esc($student['email']) ?></strong></li>
                    <li class="list-group-item d-flex justify-content-between px-0"><span>Phone:</span><strong><?= esc($student['phone_number']) ?></strong></li>
                    <li class="list-group-item d-flex justify-content-between px-0"><span>Behaviour Score:</span><strong class="fs-6"><?= (int)$student['behaviour_score'] ?> (<?= esc($student['account_status']) ?>)</strong></li>
                </ul>

                <?php if ((int)$student['behaviour_score'] < 30): ?>
                    <div class="p-3 bg-warning-subtle rounded-3">
                        <h6 class="fw-bold mb-2 text-warning-emphasis">Manual Score Adjustment</h6>
                        <form method="post" action="<?= esc(route_url('librarian/adjust-score', ['id' => $student['id']])) ?>">
                            <?= csrf_field() ?>
                            <div class="mb-2"><label class="form-label small mb-1">Score Change (+/-)</label><input type="number" name="score_change" class="form-control form-control-sm" required></div>
                            <div class="mb-3"><label class="form-label small mb-1">Reason</label><input type="text" name="reason" class="form-control form-control-sm" required></div>
                            <button class="btn btn-warning btn-sm w-100 fw-semibold">Adjust Score</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-8">
            <h4 class="h6 fw-bold mb-2">Active Book Issues</h4>
            <div class="table-responsive bg-white rounded-3 shadow-sm mb-4">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Book Title</th><th>Issue Date</th><th>Due Date</th></tr></thead>
                    <tbody>
                        <?php foreach ($activeIssues as $ai): ?>
                            <tr><td><?= esc($ai['book_title']) ?></td><td><?= format_date($ai['issue_date']) ?></td><td><?= format_date($ai['due_date']) ?></td></tr>
                        <?php endforeach; ?>
                        <?php if (empty($activeIssues)): ?><tr><td colspan="3" class="text-muted text-center py-2">No active issues</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>

            <h4 class="h6 fw-bold mb-2">Behaviour Log History</h4>
            <div class="table-responsive bg-white rounded-3 shadow-sm">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Event</th><th>Change</th><th>Score After</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php foreach ($historyLogs as $hl): ?>
                            <tr>
                                <td><?= esc($hl['event_type']) ?> - <?= esc($hl['description']) ?></td>
                                <td class="<?= (int)$hl['score_change'] >= 0 ? 'text-success' : 'text-danger' ?> fw-bold"><?= (int)$hl['score_change'] > 0 ? '+' : '' ?><?= (int)$hl['score_change'] ?></td>
                                <td><?= (int)$hl['score_after'] ?></td>
                                <td><?= format_datetime($hl['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
    render('Student Details', ob_get_clean());
}

function librarian_adjust_score_page() {
    require_librarian();
    if (!is_post()) redirect_to('librarian/students');
    verify_csrf();

    $id = (int)($_GET['id'] ?? 0);
    $change = (int)($_POST['score_change'] ?? 0);
    $reason = trim($_POST['reason'] ?? 'Librarian manual adjustment');

    library()->adjustScore($id, $change, 'librarian_manual', $reason);
    flash('success', 'Behaviour score updated successfully.');
    redirect_to('librarian/students/view', ['id' => $id]);
}

function librarian_payments_page() {
    require_librarian();
    $payments = db()->fetchAll('SELECT p.*, s.full_name AS student_name, f.fine_type FROM payments p JOIN students s ON s.id = p.student_id LEFT JOIN fines f ON f.id = p.fine_id ORDER BY p.created_at DESC');

    ob_start();
    ?>
    <div class="table-responsive bg-white rounded-3 shadow-sm">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Student Name</th>
                    <th>Payment Method</th>
                    <th>Status</th>
                    <th>Amount</th>
                    <th>Created Date</th>
                    <th>Razorpay ID</th>
                    <th>Receipt PDF</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $p): ?>
                    <tr>
                        <td class="fw-bold"><?= esc($p['student_name']) ?></td>
                        <td><?= esc(ucfirst($p['payment_method'])) ?></td>
                        <td><?= status_badge($p['status']) ?></td>
                        <td class="fw-bold"><?= money($p['amount']) ?></td>
                        <td><?= format_datetime($p['created_at']) ?></td>
                        <td><code><?= esc($p['razorpay_payment_id'] ?? '—') ?></code></td>
                        <td>
                            <?php if ($p['status'] === 'Completed'): ?>
                                <a href="<?= esc(route_url('receipt-pdf', ['id' => $p['id']])) ?>" target="_blank" class="btn btn-sm btn-outline-primary py-1 px-2 fw-semibold">
                                    <i class="bi bi-file-earmark-pdf-fill text-danger me-1"></i> View / Print PDF
                                </a>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    render('Payments History', ob_get_clean());
}

function librarian_mark_offline_payment_page() {
    require_librarian();
    redirect_to('librarian/payments');
}

function librarian_recommendations_page() {
    require_librarian();
    $recs = db()->fetchAll('SELECT r.*, s.full_name AS student_name FROM book_recommendations r JOIN students s ON s.id = r.student_id ORDER BY r.submitted_at DESC');

    ob_start();
    ?>
    <div class="table-responsive bg-white rounded-3 shadow-sm">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Student</th>
                    <th>Book Title & Author</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recs as $r): ?>
                    <tr>
                        <td class="fw-bold"><?= esc($r['student_name']) ?></td>
                        <td><strong><?= esc($r['book_name']) ?></strong><br><small class="text-muted">by <?= esc($r['author']) ?></small></td>
                        <td><?= esc($r['reason'] ?? '—') ?></td>
                        <td><?= status_badge($r['status']) ?></td>
                        <td>
                            <?php if ($r['status'] === 'Pending'): ?>
                                <form method="post" action="<?= esc(route_url('librarian/recommendations/review', ['id' => $r['id']])) ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="review_status" value="Approved">
                                    <button class="btn btn-sm btn-success">Approve</button>
                                </form>
                                <form method="post" action="<?= esc(route_url('librarian/recommendations/review', ['id' => $r['id']])) ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="review_status" value="Rejected">
                                    <button class="btn btn-sm btn-danger">Reject</button>
                                </form>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    render('Book Recommendations Review', ob_get_clean());
}

function librarian_recommendation_review_page() {
    require_librarian();
    if (!is_post()) redirect_to('librarian/recommendations');
    verify_csrf();

    $id = (int)($_GET['id'] ?? 0);
    $status = trim($_POST['review_status'] ?? 'Rejected');
    $note = trim($_POST['note'] ?? '');

    library()->reviewRecommendation($id, $status, $note);
    flash('success', 'Recommendation review updated.');
    redirect_to('librarian/recommendations');
}

function librarian_whatsapp_logs_page() {
    require_librarian();
    $logs = db()->fetchAll('SELECT wl.*, s.full_name AS student_name FROM whatsapp_logs wl LEFT JOIN students s ON s.id = wl.student_id ORDER BY wl.created_at DESC LIMIT 100');

    ob_start();
    ?>
    <div class="table-responsive bg-white rounded-3 shadow-sm">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Student</th>
                    <th>Event Type</th>
                    <th>To Number</th>
                    <th>Status</th>
                    <th>Twilio SID</th>
                    <th>Date Sent</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No WhatsApp logs recorded yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $l): ?>
                        <tr>
                            <td><?= esc($l['student_name'] ?? 'System') ?></td>
                            <td><span class="badge bg-secondary"><?= esc($l['event_type']) ?></span></td>
                            <td><code><?= esc($l['to_number']) ?></code></td>
                            <td><?= status_badge($l['status'] === 'sent' ? 'Sent' : 'Failed') ?></td>
                            <td><small><?= esc($l['twilio_sid'] ?? $l['error_message'] ?? '—') ?></small></td>
                            <td><?= format_datetime($l['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    render('Twilio WhatsApp Logs', ob_get_clean());
}

function librarian_notifications_page() {
    require_librarian();
    $notifications = db()->fetchAll('SELECT n.*, s.full_name AS student_name FROM notifications n JOIN students s ON s.id = n.student_id ORDER BY n.created_at DESC LIMIT 100');

    ob_start();
    ?>
    <div class="table-responsive bg-white rounded-3 shadow-sm">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Student Name</th>
                    <th>Type</th>
                    <th>Title</th>
                    <th>Message</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($notifications as $n): ?>
                    <tr>
                        <td class="fw-bold"><?= esc($n['student_name']) ?></td>
                        <td><span class="badge bg-info text-dark"><?= esc($n['notification_type']) ?></span></td>
                        <td><?= esc($n['title']) ?></td>
                        <td><?= esc($n['message']) ?></td>
                        <td><?= format_datetime($n['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    render('In-App Notification Audit', ob_get_clean());
}
