# LibraryHub PHP Rebuild

Plain PHP + MySQL rebuild of the library management system.

## Stack
- Backend: PHP
- Frontend: HTML, CSS, JavaScript
- Database: MySQL / MariaDB

## What changed in this rebuild
- Removed the old Python/Flask implementation.
- Rebuilt the app around a plain PHP front controller.
- Kept the librarian credentials hardcoded as requested.
- Added a return-time payment toggle for the librarian:
  - `offline` records a cash payment immediately
  - `online` creates a pending payment that can be completed later
- Fixed the lost-book workflow:
  - Librarian marks the issue as lost
  - Fine is added to the student account
  - Total book copies decrease by 1
  - Issued copies decrease by 1
  - The copy is not returned to available stock
- Removed the public receipt exposure pattern from the old app.
- Added CSRF protection to all POST actions.
- Replaced auto-start scheduler behavior with explicit cron scripts.

## Setup
1. Import `database/schema.sql` into MySQL.
2. Create a `.env` file in the project root or set environment variables.
3. Point your web server document root at `public/`.
4. Set up cron jobs for reminders if you want automatic notifications:
   - `php cron/due_reminders.php`
   - `php cron/overdue_notices.php`
5. Open the app in a browser.

## Notes
- Librarian login:
  - Email: `admin.lms@gmail.com`
  - Password: `admin@#$123`
- The app is intentionally lightweight and framework-free.

## Deployment
- See [DEPLOYMENT.md](DEPLOYMENT.md) for Apache, Nginx, database, and cron setup.


## XAMPP Quick Start
- Copy .env.example to .env in the project root.
- Start Apache and MySQL in XAMPP.
- Open the app at http://localhost/libraryhub/public/ if the project is inside htdocs.
- If the project stays on another drive, create an Apache virtual host for it.


