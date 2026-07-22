# LibraryHub — Cloudinary & Render.com Deployment Guide

> **Project**: LibraryHub (Plain PHP 8.1+ & MySQL 8.0+)  
> **Web Root**: `public/`  
> **PDF & CDN Storage**: Cloudinary REST API  
> **Cloud Host**: Render.com  
> **Integrations**: Twilio WhatsApp API & Razorpay Payment Gateway  

---

## ☁️ Part 1: Setting Up Cloudinary for Native WhatsApp PDF Attachments

**Twilio WhatsApp API** requires a **Public HTTPS URL** to attach native PDF document files in WhatsApp messages. **Cloudinary** provides instant public CDN URLs for generated PDFs without needing local storage or Ngrok!

### Step 1: Create a Free Cloudinary Account
1. Sign up for a free account at **[https://cloudinary.com](https://cloudinary.com)**.
2. On your Cloudinary Dashboard, copy your:
   - **Cloud Name** (`CLOUDINARY_CLOUD_NAME`)
   - **API Key** (`CLOUDINARY_API_KEY`)
   - **API Secret** (`CLOUDINARY_API_SECRET`)

### Step 2: Add Cloudinary Keys to `.env`
Add these keys to your `.env` file (or Render Environment Variables):
```env
CLOUDINARY_CLOUD_NAME=your_cloud_name
CLOUDINARY_API_KEY=your_api_key
CLOUDINARY_API_SECRET=your_api_secret
```

*When a payment is processed, LibraryHub automatically uploads the receipt to Cloudinary and sends the secure CDN PDF link directly to Twilio as a native WhatsApp PDF document attachment!*

---

## 🚀 Part 2: Deploying PHP & MySQL on Render.com (100% Free)

### Step 1: Push Project to GitHub
1. Create a repository on GitHub (e.g. `libraryhub`).
2. Push your project code (excluding `.env` and `Library Management System - Copy/`).

### Step 2: Create MySQL Database on Render
1. Log in to **[Render.com](https://render.com)**.
2. Click **New +** -> **MySQL Database** (or Railway MySQL).
3. Copy your database credentials:
   - `DB_HOST`
   - `DB_NAME`
   - `DB_USER`
   - `DB_PASS`
4. Import `database/schema.sql` into the database using phpMyAdmin or DBeaver.

### Step 3: Deploy PHP Web Service on Render
1. On Render, click **New +** -> **Web Service**.
2. Connect your `libraryhub` GitHub repository.
3. Environment: **PHP** (or Docker).
4. Publish Directory / Document Root: `public/`.
5. Add Environment Variables under **Environment** tab:

```env
APP_BASE_URL=https://your-app-name.onrender.com
APP_DEBUG=0
DB_HOST=<your-render-db-host>
DB_PORT=3306
DB_NAME=libraryhub
DB_USER=<your-render-db-user>
DB_PASS=<your-render-db-password>

RAZORPAY_KEY_ID=rzp_test_TBOkYHNzqykswV
RAZORPAY_KEY_SECRET=UI1595peK14aMfj7SUHIYolM

TWILIO_ACCOUNT_SID=ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_AUTH_TOKEN=89f13e6c807bf6657cc3b593598048ce
TWILIO_WHATSAPP_NUMBER=whatsapp:+14155238886

CLOUDINARY_CLOUD_NAME=<your-cloudinary-name>
CLOUDINARY_API_KEY=<your-cloudinary-key>
CLOUDINARY_API_SECRET=<your-cloudinary-secret>
```

### Step 4: Verify Live App & WhatsApp Attachments
1. Open `https://your-app-name.onrender.com`.
2. Register a student account or login as librarian (`admin.lms@gmail.com`).
3. Process an offline/online payment.
4. Check WhatsApp — Twilio will deliver the native `.pdf` receipt file attachment generated via Cloudinary CDN!

---

## 💻 Local Testing in XAMPP

If running locally on Windows with XAMPP:
1. Copy project files into `C:\xampp\htdocs\libraryhub\` (or `C:\xampp\htdocs\lib\`).
2. Start **Apache** and **MySQL** in XAMPP.
3. Import `database/schema.sql` into phpMyAdmin (`http://localhost/phpmyadmin/`).
4. Configure `.env` with your Cloudinary & Twilio keys.
5. Access app at `http://localhost/lib/public/`.

---

## ⏰ Cron Jobs for Background Notifications

Run daily via Linux crontab or Windows Task Scheduler:

```bash
php cron/due_reminders.php
php cron/overdue_notices.php
```
