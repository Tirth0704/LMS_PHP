<?php

class LibraryService
{
    private LibraryDatabase $db;
    private array $config;
    private ?WhatsAppService $whatsapp;
    private ?RazorpayService $razorpay;
    private ?ReceiptService $receiptService;

    public function __construct(LibraryDatabase $db, array $config, ?WhatsAppService $whatsapp = null, ?RazorpayService $razorpay = null, ?ReceiptService $receiptService = null)
    {
        $this->db = $db;
        $this->config = $config;
        $this->whatsapp = $whatsapp;
        $this->razorpay = $razorpay;
        $this->receiptService = $receiptService;
    }

    private function q(string $sql, array $params = []): array
    {
        return $this->db->fetchAll($sql, $params);
    }

    private function one(string $sql, array $params = []): ?array
    {
        return $this->db->fetch($sql, $params);
    }

    private function scalar(string $sql, array $params = [])
    {
        return $this->db->fetchValue($sql, $params);
    }

    private function exec(string $sql, array $params = []): int
    {
        return $this->db->execute($sql, $params);
    }

    private function transaction(callable $callback)
    {
        $this->db->begin();
        try {
            $result = $callback();
            $this->db->commit();
            return $result;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function librarianCredentials(): array
    {
        return $this->config['librarian'];
    }

    public function authenticateLibrarian(string $email, string $password): bool
    {
        $creds = $this->librarianCredentials();
        return strcasecmp($email, $creds['email']) === 0 && $password === $creds['password'];
    }

    public function authenticateStudent(string $email, string $password): ?array
    {
        $student = $this->studentByEmail($email);
        if ($student && password_verify($password, $student['password_hash'])) {
            return $student;
        }
        return null;
    }

    public function studentById(int $id): ?array
    {
        return $this->one('SELECT * FROM students WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    public function studentByEmail(string $email): ?array
    {
        return $this->one('SELECT * FROM students WHERE email = :email LIMIT 1', ['email' => strtolower(trim($email))]);
    }

    public function studentByEnrollment(string $enrollment): ?array
    {
        return $this->one('SELECT * FROM students WHERE enrollment_number = :enrollment LIMIT 1', ['enrollment' => strtoupper(trim($enrollment))]);
    }

    public function registerStudent(array $input): array
    {
        $email = strtolower(trim($input['email'] ?? ''));
        $enrollment = strtoupper(trim($input['enrollment_number'] ?? ''));

        if ($this->studentByEmail($email)) {
            return ['ok' => false, 'message' => 'An account with this email already exists.'];
        }

        if ($this->studentByEnrollment($enrollment)) {
            return ['ok' => false, 'message' => 'This enrollment number is already registered.'];
        }

        $this->exec(
            'INSERT INTO students (enrollment_number, full_name, department, semester, email, phone_number, password_hash, behaviour_score, account_status, joining_date, created_at, updated_at)
             VALUES (:enrollment, :full_name, :department, :semester, :email, :phone_number, :password_hash, :behaviour_score, :account_status, CURDATE(), NOW(), NOW())',
            [
                'enrollment' => $enrollment,
                'full_name' => trim($input['full_name'] ?? ''),
                'department' => trim($input['department'] ?? ''),
                'semester' => trim($input['semester'] ?? ''),
                'email' => $email,
                'phone_number' => trim($input['phone_number'] ?? ''),
                'password_hash' => password_hash((string) ($input['password'] ?? ''), PASSWORD_DEFAULT),
                'behaviour_score' => app_config('business.initial_behaviour_score', 100),
                'account_status' => account_status_for_score(app_config('business.initial_behaviour_score', 100)),
            ]
        );

        $student = $this->studentByEmail($email);
        $this->logActivity('student', (int) $student['id'], 'student', (int) $student['id'], 'registered', 'New student account created.');
        return ['ok' => true, 'student' => $student];
    }

    public function updateStudentProfile(int $studentId, array $input): bool
    {
        return $this->exec(
            'UPDATE students SET full_name = :full_name, phone_number = :phone_number, department = :department, semester = :semester, updated_at = NOW() WHERE id = :id',
            [
                'id' => $studentId,
                'full_name' => trim($input['full_name'] ?? ''),
                'phone_number' => trim($input['phone_number'] ?? ''),
                'department' => trim($input['department'] ?? ''),
                'semester' => trim($input['semester'] ?? ''),
            ]
        ) > 0;
    }

    public function categories(): array
    {
        return $this->q('SELECT c.*, COUNT(b.id) AS book_count
                         FROM categories c
                         LEFT JOIN books b ON b.category_id = c.id AND b.is_archived = 0
                         GROUP BY c.id
                         ORDER BY c.name');
    }

    public function categoryById(int $id): ?array
    {
        return $this->one('SELECT * FROM categories WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    public function createCategory(array $input): array
    {
        $name = trim($input['name'] ?? '');
        if ($name === '') {
            return ['ok' => false, 'message' => 'Category name is required.'];
        }

        $exists = $this->one('SELECT id FROM categories WHERE LOWER(name) = LOWER(:name) LIMIT 1', ['name' => $name]);
        if ($exists) {
            return ['ok' => false, 'message' => 'A category with this name already exists.'];
        }

        $this->exec('INSERT INTO categories (name, description, created_at) VALUES (:name, :description, NOW())', [
            'name' => $name,
            'description' => trim($input['description'] ?? '') ?: null,
        ]);
        return ['ok' => true];
    }

    public function deleteCategory(int $id): bool
    {
        return $this->exec('DELETE FROM categories WHERE id = :id', ['id' => $id]) > 0;
    }

    public function bookById(int $id): ?array
    {
        return $this->one(
            'SELECT b.*, c.name AS category_name
             FROM books b
             LEFT JOIN categories c ON c.id = b.category_id
             WHERE b.id = :id LIMIT 1',
            ['id' => $id]
        );
    }

    public function books(array $filters = []): array
    {
        $sql = 'SELECT b.*, c.name AS category_name
                FROM books b
                LEFT JOIN categories c ON c.id = b.category_id
                WHERE b.is_archived = 0';
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= ' AND (b.title LIKE :q OR b.author LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        if (!empty($filters['category_id'])) {
            $sql .= ' AND b.category_id = :category_id';
            $params['category_id'] = (int) $filters['category_id'];
        }

        if (!empty($filters['available_only'])) {
            $sql .= ' AND b.available_copies > 0';
        }

        $sql .= ' ORDER BY b.added_date DESC, b.title ASC';
        return $this->q($sql, $params);
    }

    public function latestBooks(int $limit = 10): array
    {
        return $this->q(
            'SELECT b.*, c.name AS category_name
             FROM books b
             LEFT JOIN categories c ON c.id = b.category_id
             WHERE b.is_archived = 0
             ORDER BY b.added_date DESC, b.id DESC
             LIMIT ' . (int) $limit
        );
    }

    public function createBook(array $input): array
    {
        $totalCopies = max(1, (int) ($input['total_copies'] ?? 1));
        $this->exec(
            'INSERT INTO books (title, author, publisher, price, total_copies, available_copies, issued_copies, category_id, status, added_date, created_at, updated_at, is_archived)
             VALUES (:title, :author, :publisher, :price, :total_copies, :available_copies, 0, :category_id, :status, CURDATE(), NOW(), NOW(), 0)',
            [
                'title' => trim($input['title'] ?? ''),
                'author' => trim($input['author'] ?? ''),
                'publisher' => trim($input['publisher'] ?? '') ?: null,
                'price' => (float) ($input['price'] ?? 0),
                'total_copies' => $totalCopies,
                'available_copies' => $totalCopies,
                'category_id' => !empty($input['category_id']) ? (int) $input['category_id'] : null,
                'status' => 'Available',
            ]
        );

        return ['ok' => true];
    }

    public function updateBook(int $id, array $input): array
    {
        $book = $this->bookById($id);
        if (!$book) {
            return ['ok' => false, 'message' => 'Book not found.'];
        }

        $newTotal = max(1, (int) ($input['total_copies'] ?? 1));
        if ($newTotal < (int) $book['issued_copies']) {
            return ['ok' => false, 'message' => 'Total copies cannot be lower than currently issued copies.'];
        }

        $available = (int) $book['available_copies'];
        $issued = (int) $book['issued_copies'];
        if ($newTotal > (int) $book['total_copies']) {
            $available += $newTotal - (int) $book['total_copies'];
        } elseif ($newTotal < (int) $book['total_copies']) {
            $available = max(0, $available - ((int) $book['total_copies'] - $newTotal));
        }

        $available = min($available, $newTotal);
        $status = $available > 0 ? 'Available' : 'Unavailable';

        $this->exec(
            'UPDATE books
             SET title = :title, author = :author, publisher = :publisher, price = :price,
                 total_copies = :total_copies, available_copies = :available_copies,
                 issued_copies = :issued_copies, category_id = :category_id,
                 status = :status, updated_at = NOW()
             WHERE id = :id',
            [
                'id' => $id,
                'title' => trim($input['title'] ?? ''),
                'author' => trim($input['author'] ?? ''),
                'publisher' => trim($input['publisher'] ?? '') ?: null,
                'price' => (float) ($input['price'] ?? 0),
                'total_copies' => $newTotal,
                'available_copies' => $available,
                'issued_copies' => $issued,
                'category_id' => !empty($input['category_id']) ? (int) $input['category_id'] : null,
                'status' => $status,
            ]
        );

        return ['ok' => true];
    }

    public function archiveBook(int $id): bool
    {
        return $this->exec('UPDATE books SET is_archived = 1, updated_at = NOW() WHERE id = :id', ['id' => $id]) > 0;
    }

    public function studentActiveIssueCount(int $studentId): int
    {
        return (int) $this->scalar(
            'SELECT COUNT(*) FROM book_issues WHERE student_id = :student_id AND is_returned = 0 AND is_lost = 0',
            ['student_id' => $studentId]
        );
    }

    public function studentPendingRequestCount(int $studentId): int
    {
        return (int) $this->scalar(
            "SELECT COUNT(*) FROM book_requests WHERE student_id = :student_id AND status = 'Pending'",
            ['student_id' => $studentId]
        );
    }

    public function hasUnpaidFine(int $studentId): bool
    {
        return (int) $this->scalar(
            "SELECT COUNT(*) FROM fines WHERE student_id = :student_id AND status = 'Unpaid'",
            ['student_id' => $studentId]
        ) > 0;
    }

    public function lastRejectedRequest(int $studentId, int $bookId): ?array
    {
        return $this->one(
            "SELECT * FROM book_requests
             WHERE student_id = :student_id AND book_id = :book_id AND status = 'Rejected'
             ORDER BY rejected_at DESC, id DESC LIMIT 1",
            [
                'student_id' => $studentId,
                'book_id' => $bookId,
            ]
        );
    }

    public function studentHasActiveIssueForBook(int $studentId, int $bookId): bool
    {
        return (int) $this->scalar(
            "SELECT COUNT(*) FROM book_issues WHERE student_id = :student_id AND book_id = :book_id AND is_returned = 0 AND is_lost = 0",
            [
                'student_id' => $studentId,
                'book_id' => $bookId,
            ]
        ) > 0;
    }

    public function pendingRequestForBook(int $studentId, int $bookId): ?array
    {
        return $this->one(
            "SELECT * FROM book_requests
             WHERE student_id = :student_id AND book_id = :book_id AND status = 'Pending'
             ORDER BY requested_at DESC, id DESC LIMIT 1",
            [
                'student_id' => $studentId,
                'book_id' => $bookId,
            ]
        );
    }

    public function createBookRequest(int $studentId, int $bookId): array
    {
        $book = $this->bookById($bookId);
        if (!$book || (int) $book['is_archived'] === 1) {
            return ['ok' => false, 'message' => 'Book not found.'];
        }

        if ((int) $book['available_copies'] < 1) {
            return ['ok' => false, 'message' => 'This book is currently unavailable.'];
        }

        if ($this->studentHasActiveIssueForBook($studentId, $bookId)) {
            return ['ok' => false, 'message' => 'You already have this book issued.'];
        }

        if ($this->pendingRequestForBook($studentId, $bookId)) {
            return ['ok' => false, 'message' => 'A pending request already exists for this book.'];
        }

        $activeCount = $this->studentActiveIssueCount($studentId);
        $pendingCount = $this->studentPendingRequestCount($studentId);
        if (($activeCount + $pendingCount) >= app_config('business.max_borrowed_books', 3)) {
            return ['ok' => false, 'message' => 'You have reached the maximum number of books allowed at a time.'];
        }

        if ($this->hasUnpaidFine($studentId)) {
            $this->exec(
                'INSERT INTO book_requests (student_id, book_id, status, requested_at, updated_at)
                 VALUES (:student_id, :book_id, "Hold", NOW(), NOW())',
                ['student_id' => $studentId, 'book_id' => $bookId]
            );
            return ['ok' => true, 'hold' => true, 'message' => 'Your request has been placed on hold because you have unpaid fines.'];
        }

        $lastRejected = $this->lastRejectedRequest($studentId, $bookId);
        if ($lastRejected && !empty($lastRejected['rejected_at'])) {
            $elapsed = (time() - strtotime($lastRejected['rejected_at'])) / 3600;
            if ($elapsed < app_config('business.re_request_hours', 24)) {
                $remaining = (int) ceil(app_config('business.re_request_hours', 24) - $elapsed);
                return ['ok' => false, 'message' => "You must wait {$remaining} more hour(s) before re-requesting this book."];
            }
        }

        $this->exec(
            'INSERT INTO book_requests (student_id, book_id, status, requested_at, updated_at)
             VALUES (:student_id, :book_id, "Pending", NOW(), NOW())',
            ['student_id' => $studentId, 'book_id' => $bookId]
        );

        return ['ok' => true, 'message' => 'Request submitted successfully.'];
    }

    public function studentRequests(int $studentId): array
    {
        return $this->q(
            'SELECT br.*, b.title AS book_title, b.author AS book_author
             FROM book_requests br
             JOIN books b ON b.id = br.book_id
             WHERE br.student_id = :student_id
             ORDER BY br.requested_at DESC, br.id DESC',
            ['student_id' => $studentId]
        );
    }

    public function requestById(int $id): ?array
    {
        return $this->one(
            'SELECT br.*, b.title AS book_title, b.author AS book_author, s.full_name AS student_name
             FROM book_requests br
             JOIN books b ON b.id = br.book_id
             JOIN students s ON s.id = br.student_id
             WHERE br.id = :id LIMIT 1',
            ['id' => $id]
        );
    }

    public function pendingRequests(?string $status = 'Pending'): array
    {
        if ($status === null || $status === 'all') {
            return $this->q(
                'SELECT br.*, b.title AS book_title, b.available_copies, s.full_name AS student_name, s.enrollment_number
                 FROM book_requests br
                 JOIN books b ON b.id = br.book_id
                 JOIN students s ON s.id = br.student_id
                 ORDER BY br.requested_at DESC'
            );
        }

        return $this->q(
            'SELECT br.*, b.title AS book_title, b.available_copies, s.full_name AS student_name, s.enrollment_number
             FROM book_requests br
             JOIN books b ON b.id = br.book_id
             JOIN students s ON s.id = br.student_id
             WHERE br.status = :status
             ORDER BY br.requested_at DESC',
            ['status' => $status]
        );
    }

    public function cancelRequest(int $requestId, int $studentId): array
    {
        $request = $this->one('SELECT * FROM book_requests WHERE id = :id AND student_id = :student_id LIMIT 1', [
            'id' => $requestId,
            'student_id' => $studentId,
        ]);

        if (!$request) {
            return ['ok' => false, 'message' => 'Request not found.'];
        }

        if ($request['status'] === 'Pending') {
            $this->exec('UPDATE book_requests SET status = "Cancelled", updated_at = NOW() WHERE id = :id', ['id' => $requestId]);
            return ['ok' => true, 'message' => 'Request cancelled successfully.'];
        }

        if ($request['status'] === 'Approved') {
            $book = $this->bookById((int) $request['book_id']);
            $student = $this->studentById($studentId);
            $this->adjustScore($studentId, (int) app_config('business.score_cancelled_approved', -3), 'cancelled_approved', 'Approved request was cancelled.');
            $this->exec('UPDATE book_requests SET status = "Cancelled", updated_at = NOW() WHERE id = :id', ['id' => $requestId]);
            $this->logActivity('student', $studentId, 'book_request', $requestId, 'cancelled_approved_request', 'Approved request was cancelled.');
            return ['ok' => true, 'message' => 'Approved request cancelled. Behaviour score updated.'];
        }

        return ['ok' => false, 'message' => 'This request cannot be cancelled.'];
    }

    public function approveRequest(int $requestId, string $issueDate): array
    {
        return $this->transaction(function () use ($requestId, $issueDate) {
            $request = $this->one(
                'SELECT br.*, b.title AS book_title, b.author AS book_author, b.available_copies, b.total_copies, b.issued_copies, b.price, s.full_name AS student_name, s.behaviour_score
                 FROM book_requests br
                 JOIN books b ON b.id = br.book_id
                 JOIN students s ON s.id = br.student_id
                 WHERE br.id = :id FOR UPDATE',
                ['id' => $requestId]
            );

            if (!$request) {
                return ['ok' => false, 'message' => 'Request not found.'];
            }

            if ($request['status'] !== 'Pending') {
                return ['ok' => false, 'message' => 'This request is no longer pending.'];
            }

            if ((int) $request['available_copies'] < 1) {
                return ['ok' => false, 'message' => 'Book is unavailable.'];
            }

            $studentId = (int) $request['student_id'];
            $bookId = (int) $request['book_id'];

            if ($this->studentActiveIssueCount($studentId) >= app_config('business.max_borrowed_books', 3)) {
                return ['ok' => false, 'message' => 'Student has reached the maximum active borrow limit.'];
            }

            $dueDate = date('Y-m-d', strtotime($issueDate . ' +' . (int) app_config('business.loan_period_days', 14) . ' days'));

            $this->exec(
                'INSERT INTO book_issues (student_id, book_id, request_id, issue_date, due_date, return_date, is_returned, is_lost, status, created_at, updated_at)
                 VALUES (:student_id, :book_id, :request_id, :issue_date, :due_date, NULL, 0, 0, "Issued", NOW(), NOW())',
                [
                    'student_id' => $studentId,
                    'book_id' => $bookId,
                    'request_id' => $requestId,
                    'issue_date' => $issueDate,
                    'due_date' => $dueDate,
                ]
            );

            $issueId = $this->db->lastInsertId();
            $this->exec(
                'UPDATE books
                 SET available_copies = available_copies - 1,
                     issued_copies = issued_copies + 1,
                     status = CASE WHEN available_copies - 1 > 0 THEN "Available" ELSE "Unavailable" END,
                     updated_at = NOW()
                 WHERE id = :book_id',
                ['book_id' => $bookId]
            );
            $this->exec('UPDATE book_requests SET status = "Approved", updated_at = NOW() WHERE id = :id', ['id' => $requestId]);
            $this->logActivity('librarian', null, 'book_issue', $issueId, 'request_approved', 'Book request approved.');
            $this->createNotification($studentId, 'request_approved', 'Book Request Approved', "Your request for {$request['book_title']} has been approved. Due date: " . date('d M Y', strtotime($dueDate)));

            if ($this->whatsapp) {
                $student = $this->studentById($studentId);
                if ($student) {
                    try {
                        $this->whatsapp->sendRequestApproved($student, $request['book_title'], $dueDate);
                    } catch (Throwable $e) {}
                }
            }

            return [
                'ok' => true,
                'message' => 'Request approved successfully.',
                'issue_id' => $issueId,
                'student_id' => $studentId,
                'book_id' => $bookId,
                'book_title' => $request['book_title'],
                'student_name' => $request['student_name'],
                'due_date' => $dueDate,
            ];
        });
    }

    public function rejectRequest(int $requestId, string $reason): array
    {
        return $this->transaction(function () use ($requestId, $reason) {
            $request = $this->requestById($requestId);
            if (!$request) {
                return ['ok' => false, 'message' => 'Request not found.'];
            }

            if ($request['status'] !== 'Pending') {
                return ['ok' => false, 'message' => 'This request is no longer pending.'];
            }

            $this->exec(
                'UPDATE book_requests SET status = "Rejected", rejection_reason = :reason, rejected_at = NOW(), updated_at = NOW() WHERE id = :id',
                [
                    'id' => $requestId,
                    'reason' => trim($reason),
                ]
            );
            $this->logActivity('librarian', null, 'book_request', $requestId, 'request_rejected', 'Book request rejected.');
            $this->createNotification((int) $request['student_id'], 'request_rejected', 'Book Request Rejected', 'Your request for ' . $request['book_title'] . ' was rejected. Reason: ' . trim($reason));

            if ($this->whatsapp) {
                $student = $this->studentById((int)$request['student_id']);
                if ($student) {
                    try {
                        $this->whatsapp->sendRequestRejected($student, $request['book_title'], trim($reason));
                    } catch (Throwable $e) {}
                }
            }

            return ['ok' => true, 'message' => 'Request rejected successfully.'];
        });
    }

    public function holdRequest(int $requestId): array
    {
        $request = $this->requestById($requestId);
        if (!$request) {
            return ['ok' => false, 'message' => 'Request not found.'];
        }
        $this->exec('UPDATE book_requests SET status = "Hold", updated_at = NOW() WHERE id = :id', ['id' => $requestId]);
        $this->logActivity('librarian', null, 'book_request', $requestId, 'request_held', 'Book request held.');
        return ['ok' => true, 'message' => 'Request placed on hold.'];
    }

    public function issues(array $filters = []): array
    {
        $sql = 'SELECT bi.*, b.title AS book_title, s.full_name AS student_name, s.enrollment_number
                FROM book_issues bi
                JOIN books b ON b.id = bi.book_id
                JOIN students s ON s.id = bi.student_id
                WHERE 1 = 1';
        $params = [];

        if (!empty($filters['active_only'])) {
            $sql .= ' AND bi.is_returned = 0 AND bi.is_lost = 0';
        }
        if (!empty($filters['overdue_only'])) {
            $sql .= ' AND bi.is_returned = 0 AND bi.is_lost = 0 AND bi.due_date < CURDATE()';
        }
        if (!empty($filters['student_id'])) {
            $sql .= ' AND bi.student_id = :student_id';
            $params['student_id'] = (int) $filters['student_id'];
        }
        $sql .= ' ORDER BY bi.due_date ASC, bi.id DESC';
        return $this->q($sql, $params);
    }

    public function issueById(int $id): ?array
    {
        return $this->one(
            'SELECT bi.*, b.title AS book_title, b.author AS book_author, b.price, b.total_copies, b.available_copies, b.issued_copies,
                    s.full_name AS student_name, s.enrollment_number, s.phone_number
             FROM book_issues bi
             JOIN books b ON b.id = bi.book_id
             JOIN students s ON s.id = bi.student_id
             WHERE bi.id = :id LIMIT 1',
            ['id' => $id]
        );
    }

    public function studentActiveIssues(int $studentId): array
    {
        return $this->issues(['student_id' => $studentId, 'active_only' => true]);
    }

    public function studentHistoryIssues(int $studentId): array
    {
        return $this->q(
            'SELECT bi.*, b.title AS book_title, b.author AS book_author, r.return_condition AS return_condition, r.total_due
             FROM book_issues bi
             JOIN books b ON b.id = bi.book_id
             LEFT JOIN book_returns r ON r.issue_id = bi.id
             WHERE bi.student_id = :student_id AND (bi.is_returned = 1 OR bi.is_lost = 1)
             ORDER BY bi.id DESC',
            ['student_id' => $studentId]
        );
    }

    public function createFine(int $studentId, int $issueId, string $fineType, float $amount, ?string $description = null): int
    {
        $this->exec(
            'INSERT INTO fines (student_id, issue_id, fine_type, amount, description, status, created_at, paid_at)
             VALUES (:student_id, :issue_id, :fine_type, :amount, :description, "Unpaid", NOW(), NULL)',
            [
                'student_id' => $studentId,
                'issue_id' => $issueId,
                'fine_type' => $fineType,
                'amount' => $amount,
                'description' => $description,
            ]
        );
        return $this->db->lastInsertId();
    }

    public function finesForStudent(int $studentId): array
    {
        return $this->q(
            "SELECT f.*, p.id AS payment_id, bi.book_id, b.title AS book_title
             FROM fines f
             JOIN book_issues bi ON bi.id = f.issue_id
             JOIN books b ON b.id = bi.book_id
             LEFT JOIN payments p ON p.fine_id = f.id AND p.status = 'Completed'
             WHERE f.student_id = :student_id
             ORDER BY f.created_at DESC, f.id DESC",
            ['student_id' => $studentId]
        );
    }

    public function unpaidFineForStudent(int $studentId): ?array
    {
        return $this->one(
            "SELECT f.*, b.title AS book_title
             FROM fines f
             JOIN book_issues bi ON bi.id = f.issue_id
             JOIN books b ON b.id = bi.book_id
             WHERE f.student_id = :student_id AND f.status = 'Unpaid'
             ORDER BY f.created_at DESC, f.id DESC LIMIT 1",
            ['student_id' => $studentId]
        );
    }

    public function paymentByFineId(int $fineId): ?array
    {
        return $this->one('SELECT * FROM payments WHERE fine_id = :fine_id ORDER BY id DESC LIMIT 1', ['fine_id' => $fineId]);
    }

    public function paymentById(int $paymentId): ?array
    {
        return $this->one('SELECT * FROM payments WHERE id = :id LIMIT 1', ['id' => $paymentId]);
    }

    public function studentPayments(int $studentId): array
    {
        return $this->q(
            'SELECT p.*, f.fine_type, f.amount AS fine_amount, b.title AS book_title
             FROM payments p
             LEFT JOIN fines f ON f.id = p.fine_id
             LEFT JOIN book_issues bi ON bi.id = f.issue_id
             LEFT JOIN books b ON b.id = bi.book_id
             WHERE p.student_id = :student_id
             ORDER BY p.created_at DESC, p.id DESC',
            ['student_id' => $studentId]
        );
    }

    public function createPayment(int $studentId, ?int $fineId, float $amount, string $method, string $status = 'Pending', ?string $reference = null): int
    {
        $this->exec(
            'INSERT INTO payments (student_id, fine_id, amount, payment_method, status, reference_no, created_at, completed_at)
             VALUES (:student_id, :fine_id, :amount, :method, :status, :reference_no, NOW(), :completed_at)',
            [
                'student_id' => $studentId,
                'fine_id' => $fineId,
                'amount' => $amount,
                'method' => $method,
                'status' => $status,
                'reference_no' => $reference,
                'completed_at' => $status === 'Completed' ? date('Y-m-d H:i:s') : null,
            ]
        );

        return $this->db->lastInsertId();
    }

    public function markFinePaid(int $fineId): bool
    {
        return $this->exec(
            'UPDATE fines SET status = "Paid", paid_at = NOW() WHERE id = :id',
            ['id' => $fineId]
        ) > 0;
    }

    public function updatePaymentStatus(int $paymentId, string $status, ?string $reference = null): bool
    {
        return $this->exec(
            'UPDATE payments SET status = :status, reference_no = :reference_no, completed_at = IF(:status = "Completed", NOW(), completed_at) WHERE id = :id',
            [
                'id' => $paymentId,
                'status' => $status,
                'reference_no' => $reference,
            ]
        ) > 0;
    }

    public function recordPaymentCompletion(int $paymentId, string $completionMode = 'online'): array
    {
        return $this->transaction(function () use ($paymentId, $completionMode) {
            $payment = $this->paymentById($paymentId);
            if (!$payment) {
                return ['ok' => false, 'message' => 'Payment not found.'];
            }

            if ($payment['status'] === 'Completed') {
                return ['ok' => false, 'message' => 'Payment already completed.'];
            }

            $this->updatePaymentStatus($paymentId, 'Completed', $payment['reference_no'] ?? null);
            if (!empty($payment['fine_id'])) {
                $this->markFinePaid((int) $payment['fine_id']);
                $this->adjustScore((int) $payment['student_id'], (int) app_config('business.score_paid_fine_immediately', 2), 'paid_fine_immediately', 'Fine paid.');
            }

            if ($this->whatsapp) {
                $student = $this->studentById((int) $payment['student_id']);
                if ($student) {
                    try {
                        $this->whatsapp->sendFinePaymentConfirmed($student, (float) $payment['amount'], $payment['payment_method'] ?? 'online', $paymentId);
                    } catch (Throwable $e) {}
                }
            }

            return ['ok' => true, 'message' => 'Payment completed successfully.'];
        });
    }

    public function processReturn(int $issueId, string $condition, string $paymentMode, float $damageFineAmount = 0.0, ?string $notes = null): array
    {
        return $this->transaction(function () use ($issueId, $condition, $paymentMode, $damageFineAmount, $notes) {
            $issue = $this->one(
                'SELECT bi.*, b.title AS book_title, b.price, b.total_copies, b.available_copies, b.issued_copies, s.full_name AS student_name, s.phone_number
                 FROM book_issues bi
                 JOIN books b ON b.id = bi.book_id
                 JOIN students s ON s.id = bi.student_id
                 WHERE bi.id = :id FOR UPDATE',
                ['id' => $issueId]
            );

            if (!$issue) {
                return ['ok' => false, 'message' => 'Issue not found.'];
            }

            if ((int) $issue['is_returned'] === 1 || (int) $issue['is_lost'] === 1) {
                return ['ok' => false, 'message' => 'This issue has already been processed.'];
            }

            $studentId = (int) $issue['student_id'];
            $bookId = (int) $issue['book_id'];
            $today = date('Y-m-d');
            $overdueDays = max(0, (int) floor((strtotime($today) - strtotime($issue['due_date'])) / 86400));

            $rent = 0.0;
            $lateFine = 0.0;
            $damageFine = 0.0;
            $lostAmount = 0.0;
            $totalDue = 0.0;
            $fineType = 'rent';

            if ($condition === 'Lost') {
                $lostAmount = lost_amount_for_price($issue['price']);
                $totalDue = $lostAmount;
                $fineType = 'lost';
            } else {
                $rent = rent_for_price($issue['price']);
                $lateFine = late_fine_for_price($issue['price'], $overdueDays);
                if ($condition === 'Damaged') {
                    $damageFine = round(max(0, $damageFineAmount), 2);
                }
                $totalDue = round($rent + $lateFine + $damageFine, 2);
            }

            $this->exec(
                'INSERT INTO book_returns (issue_id, student_id, book_id, return_date, return_condition, rent_charged, late_fine, damage_fine, lost_amount, total_due, payment_mode, librarian_notes, created_at)
                 VALUES (:issue_id, :student_id, :book_id, :return_date, :return_condition, :rent_charged, :late_fine, :damage_fine, :lost_amount, :total_due, :payment_mode, :librarian_notes, NOW())',
                [
                    'issue_id' => $issueId,
                    'student_id' => $studentId,
                    'book_id' => $bookId,
                    'return_date' => $today,
                    'return_condition' => $condition,
                    'rent_charged' => $rent,
                    'late_fine' => $lateFine,
                    'damage_fine' => $damageFine,
                    'lost_amount' => $lostAmount,
                    'total_due' => $totalDue,
                    'payment_mode' => $paymentMode,
                    'librarian_notes' => trim((string) $notes) ?: null,
                ]
            );
            $returnId = $this->db->lastInsertId();

            $this->exec(
                'INSERT INTO fines (student_id, issue_id, fine_type, amount, description, status, created_at, paid_at)
                 VALUES (:student_id, :issue_id, :fine_type, :amount, :description, "Unpaid", NOW(), NULL)',
                [
                    'student_id' => $studentId,
                    'issue_id' => $issueId,
                    'fine_type' => $fineType,
                    'amount' => $totalDue,
                    'description' => $condition === 'Lost'
                        ? 'Lost book fine for ' . $issue['book_title']
                        : 'Return charge for ' . $issue['book_title'],
                ]
            );
            $fineId = $this->db->lastInsertId();

            if ($condition === 'Lost') {
                $this->exec(
                    'UPDATE book_issues SET is_lost = 1, is_returned = 0, return_date = :return_date, status = "Lost", updated_at = NOW() WHERE id = :id',
                    ['id' => $issueId, 'return_date' => $today]
                );
                $this->exec(
                    'UPDATE books
                     SET total_copies = GREATEST(total_copies - 1, 0),
                         issued_copies = GREATEST(issued_copies - 1, 0),
                         status = CASE WHEN available_copies > 0 THEN "Available" ELSE "Unavailable" END,
                         updated_at = NOW()
                     WHERE id = :id',
                    ['id' => $bookId]
                );
                $behaviourChange = (int) app_config('business.score_lost_book', -25);
            } else {
                $this->exec(
                    'UPDATE book_issues SET is_returned = 1, return_date = :return_date, status = "Returned", updated_at = NOW() WHERE id = :id',
                    ['id' => $issueId, 'return_date' => $today]
                );
                $this->exec(
                    'UPDATE books
                     SET available_copies = LEAST(available_copies + 1, total_copies),
                         issued_copies = GREATEST(issued_copies - 1, 0),
                         status = CASE WHEN available_copies + 1 > 0 THEN "Available" ELSE "Unavailable" END,
                         updated_at = NOW()
                     WHERE id = :id',
                    ['id' => $bookId]
                );
                if ($condition === 'Damaged') {
                    $behaviourChange = (int) app_config('business.score_damaged_book', -20);
                    if ($damageFine > 0) {
                        $behaviourChange += (int) app_config('business.score_damage_fine_added', -5);
                    }
                } elseif ($overdueDays > 0) {
                    $behaviourChange = (int) app_config('business.score_returned_late', -5);
                } elseif ($today < $issue['due_date']) {
                    $behaviourChange = (int) app_config('business.score_returned_early', 3);
                } else {
                    $behaviourChange = (int) app_config('business.score_returned_on_time', 2);
                }
            }

            $this->adjustScore($studentId, $behaviourChange, 'return_processed', $condition . ' return processed.');

            $paymentStatus = $paymentMode === 'offline' ? 'Completed' : 'Pending';
            $paymentId = $this->createPayment(
                $studentId,
                $fineId,
                $totalDue,
                $paymentMode === 'offline' ? 'cash' : 'online',
                $paymentStatus,
                'RET-' . $returnId
            );

            if ($paymentMode === 'offline') {
                $this->markFinePaid($fineId);
                $this->adjustScore($studentId, (int) app_config('business.score_paid_fine_immediately', 2), 'paid_fine_immediately', 'Payment completed offline.');
            } else {
                $this->createNotification(
                    $studentId,
                    'fine_pending',
                    'Payment Pending',
                    'Your return has been recorded and an online payment is pending for ' . money($totalDue) . '.'
                );
            }

            $this->createNotification(
                $studentId,
                'book_returned',
                'Book Return Processed',
                $condition === 'Lost'
                    ? 'Your book was marked as lost. A fine of ' . money($totalDue) . ' has been added.'
                    : 'Your book return was processed. Total due: ' . money($totalDue) . '.'
            );

            $this->logActivity('librarian', null, 'book_return', $returnId, 'return_processed', 'Book return processed.');

            if ($this->whatsapp) {
                $student = $this->studentById($studentId);
                if ($student) {
                    try {
                        $this->whatsapp->sendBookReturned($student, $issue['book_title'], $condition, $totalDue);
                        if ($paymentMode === 'offline' && $totalDue > 0) {
                            $this->whatsapp->sendFinePaymentConfirmed($student, $totalDue, 'cash', $paymentId);
                        }
                    } catch (Throwable $e) {}
                }
            }

            return [
                'ok' => true,
                'message' => 'Return processed successfully.',
                'return_id' => $returnId,
                'fine_id' => $fineId,
                'payment_id' => $paymentId,
                'payment_status' => $paymentStatus,
                'total_due' => $totalDue,
                'return_condition' => $condition,
                'book_title' => $issue['book_title'],
                'student_name' => $issue['student_name'],
            ];
        });
    }

    public function notifications(int $studentId): array
    {
        return $this->q(
            'SELECT * FROM notifications WHERE student_id = :student_id ORDER BY created_at DESC, id DESC',
            ['student_id' => $studentId]
        );
    }

    public function unreadNotificationCount(int $studentId): int
    {
        return (int) $this->scalar(
            'SELECT COUNT(*) FROM notifications WHERE student_id = :student_id AND is_read = 0',
            ['student_id' => $studentId]
        );
    }

    public function createNotification(int $studentId, string $type, string $title, string $message): int
    {
        $this->exec(
            'INSERT INTO notifications (student_id, notification_type, title, message, is_read, created_at)
             VALUES (:student_id, :notification_type, :title, :message, 0, NOW())',
            [
                'student_id' => $studentId,
                'notification_type' => $type,
                'title' => $title,
                'message' => $message,
            ]
        );

        return $this->db->lastInsertId();
    }

    public function markAllNotificationsRead(int $studentId): void
    {
        $this->exec('UPDATE notifications SET is_read = 1 WHERE student_id = :student_id', ['student_id' => $studentId]);
    }

    public function createRecommendation(int $studentId, array $input): array
    {
        $this->exec(
            'INSERT INTO book_recommendations (student_id, book_name, author, publisher, reason, status, submitted_at, reviewed_at)
             VALUES (:student_id, :book_name, :author, :publisher, :reason, "Pending", NOW(), NULL)',
            [
                'student_id' => $studentId,
                'book_name' => trim($input['book_name'] ?? ''),
                'author' => trim($input['author'] ?? ''),
                'publisher' => trim($input['publisher'] ?? '') ?: null,
                'reason' => trim($input['reason'] ?? '') ?: null,
            ]
        );

        $recommendationId = $this->db->lastInsertId();
        $this->logActivity('student', $studentId, 'book_recommendation', $recommendationId, 'recommendation_submitted', 'Recommendation submitted.');
        return ['ok' => true, 'recommendation_id' => $recommendationId];
    }

    public function studentRecommendations(int $studentId): array
    {
        return $this->q(
            'SELECT * FROM book_recommendations WHERE student_id = :student_id ORDER BY submitted_at DESC, id DESC',
            ['student_id' => $studentId]
        );
    }

    public function reviewRecommendation(int $recommendationId, string $status, ?string $note = null): array
    {
        $rec = $this->one('SELECT * FROM book_recommendations WHERE id = :id LIMIT 1', ['id' => $recommendationId]);
        if (!$rec) {
            return ['ok' => false, 'message' => 'Recommendation not found.'];
        }
        if ($rec['status'] !== 'Pending') {
            return ['ok' => false, 'message' => 'Recommendation already reviewed.'];
        }

        $this->exec(
            'UPDATE book_recommendations SET status = :status, librarian_note = :note, reviewed_at = NOW() WHERE id = :id',
            [
                'id' => $recommendationId,
                'status' => $status,
                'note' => trim((string) $note) ?: null,
            ]
        );

        $this->createNotification(
            (int) $rec['student_id'],
            $status === 'Approved' ? 'recommendation_approved' : 'recommendation_rejected',
            $status === 'Approved' ? 'Recommendation Approved' : 'Recommendation Rejected',
            $status === 'Approved'
                ? 'Your recommendation for ' . $rec['book_name'] . ' was approved.'
                : 'Your recommendation for ' . $rec['book_name'] . ' was rejected.' . ($note ? ' Note: ' . $note : '')
        );

        $this->logActivity('librarian', null, 'book_recommendation', $recommendationId, 'recommendation_' . strtolower($status), 'Recommendation reviewed.');
        return ['ok' => true, 'message' => 'Recommendation reviewed successfully.'];
    }

    public function logs(string $scope = 'all', ?int $entityId = null): array
    {
        $sql = 'SELECT * FROM activity_logs WHERE 1 = 1';
        $params = [];
        if ($entityId !== null) {
            $sql .= ' AND entity_id = :entity_id';
            $params['entity_id'] = $entityId;
        }
        $sql .= ' ORDER BY created_at DESC, id DESC LIMIT 100';
        return $this->q($sql, $params);
    }

    public function logActivity(string $actorType, ?int $actorId, ?string $entityType, ?int $entityId, string $action, ?string $description = null): int
    {
        $this->exec(
            'INSERT INTO activity_logs (actor_type, actor_id, entity_type, entity_id, action, description, created_at)
             VALUES (:actor_type, :actor_id, :entity_type, :entity_id, :action, :description, NOW())',
            [
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'action' => $action,
                'description' => $description,
            ]
        );

        return $this->db->lastInsertId();
    }

    public function adjustScore(int $studentId, int $change, string $eventType, ?string $description = null): array
    {
        $student = $this->studentById($studentId);
        if (!$student) {
            return ['ok' => false];
        }

        $before = (int) $student['behaviour_score'];
        $after = clamp_score($before + $change);
        $actual = $after - $before;

        $this->exec(
            'UPDATE students SET behaviour_score = :score, account_status = :status, updated_at = NOW() WHERE id = :id',
            [
                'id' => $studentId,
                'score' => $after,
                'status' => account_status_for_score($after),
            ]
        );

        $this->exec(
            'INSERT INTO behaviour_logs (student_id, score_before, score_change, score_after, event_type, description, created_at)
             VALUES (:student_id, :before, :change, :after, :event_type, :description, NOW())',
            [
                'student_id' => $studentId,
                'before' => $before,
                'change' => $actual,
                'after' => $after,
                'event_type' => $eventType,
                'description' => $description,
            ]
        );

        return ['ok' => true, 'score_before' => $before, 'score_after' => $after];
    }

    public function librarianStats(): array
    {
        $totalStudents = (int) $this->scalar("SELECT COUNT(*) FROM students");
        $totalBooks = (int) $this->scalar("SELECT COUNT(*) FROM books WHERE is_archived = 0");
        $activeIssues = (int) $this->scalar("SELECT COUNT(*) FROM book_issues WHERE is_returned = 0 AND is_lost = 0");
        $overdueIssues = (int) $this->scalar("SELECT COUNT(*) FROM book_issues WHERE is_returned = 0 AND is_lost = 0 AND due_date < CURDATE()");
        $pendingRequests = (int) $this->scalar("SELECT COUNT(*) FROM book_requests WHERE status = 'Pending'");
        $unpaidFines = (float) ($this->scalar("SELECT COALESCE(SUM(amount), 0) FROM fines WHERE status = 'Unpaid'") ?: 0);
        $totalRevenue = (float) ($this->scalar("SELECT COALESCE(SUM(amount), 0) FROM payments") ?: 0);
        $onlinePayments = (int) $this->scalar("SELECT COUNT(*) FROM payments WHERE payment_method = 'online'");
        $offlinePayments = (int) $this->scalar("SELECT COUNT(*) FROM payments WHERE payment_method = 'cash'");

        return [
            'students' => $totalStudents,
            'total_students' => $totalStudents,
            'books' => $totalBooks,
            'total_books' => $totalBooks,
            'active_issues' => $activeIssues,
            'overdue_issues' => $overdueIssues,
            'pending_requests' => $pendingRequests,
            'unpaid_fines' => $unpaidFines,
            'total_revenue' => $totalRevenue,
            'online_payments' => $onlinePayments,
            'offline_payments' => $offlinePayments,
        ];
    }

    public function studentStats(int $studentId): array
    {
        return [
            'active_issues' => $this->studentActiveIssueCount($studentId),
            'pending_requests' => $this->studentPendingRequestCount($studentId),
            'unpaid_fines' => (float) ($this->scalar("SELECT COALESCE(SUM(amount), 0) FROM fines WHERE student_id = :student_id AND status = 'Unpaid'", ['student_id' => $studentId]) ?: 0),
            'unread_notifications' => $this->unreadNotificationCount($studentId),
        ];
    }
}





