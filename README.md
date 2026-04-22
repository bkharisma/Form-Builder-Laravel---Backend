<h1 align="center">🔗 Form Builder — Backend</h1>

<p align="center">
API backend for the Digital Guest Book form builder. Built with Laravel 13, serves a React SPA frontend.
</p>

---

## ⚠️ Important — This is a SPA Backend

**This directory contains ONLY the API backend.** There are no server-rendered views (Blade templates). The frontend is a separate React application located in `../frontend/`.

To run the full application, you need **both** the backend and frontend running simultaneously:

| Service | URL | Directory |
|---------|-----|-----------|
| Backend API | http://localhost:8000 | `backend/` |
| Frontend SPA | http://localhost:5173 | `frontend/` |

👉 [Jump to Frontend Setup](#-frontend-setup)

---

## ✨ Features

- **Multi-Form Builder** — Create and manage multiple forms, each with its own slug-based URL
- **13 Field Types** — Text, TextArea, Email, Number, Tel, Select, Checkbox, Radio, Date, Time, File Upload, Image Upload, Signature
- **Conditional Logic** — Show/hide fields dynamically based on other field values
- **Unique Data & Auto-fill** — Mark a field as unique to auto-populate form data from previous submissions
- **File & Image Uploads** — With size limits and extension validation
- **Signature Capture** — Canvas-based digital signature input
- **Cloudflare Turnstile** — Bot protection on login and form submission
- **Two-Factor Authentication** — Google Authenticator (TOTP) for admin accounts
- **Attendance Manager** — Event management, QR code check-in, invitation emails
- **Dynamic Reports** — Chart-based analytics with multi-field aggregation
- **Export Options** — CSV, Excel (XLSX), and PDF export
- **Bulk User Upload** — CSV-based admin user creation
- **Modular Architecture** — FormBuilder and Administration modules via `nwidart/laravel-modules`

---

## 📋 Prerequisites

| Requirement | Version | Notes |
|-------------|---------|-------|
| PHP | 8.3+ | Extensions: `mbstring`, `xml`, `mysql`, `curl`, `zip`, `gd` |
| Composer | Latest | [Install guide](https://getcomposer.org/download/) |
| MySQL / MariaDB | 5.7+ / 10.3+ | Or use SQLite for quick local dev |
| Node.js | 18+ | Required for the frontend |
| npm | Latest | Comes with Node.js |

---

## 🚀 Quick Start (Backend)

### 1. Install Dependencies

```bash
cd backend
composer install
```

### 2. Configure Environment

```bash
cp .env.example .env
php artisan key:generate
```

### 3. Configure Database

Edit `.env` and set your database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=digital_guest_book
DB_USERNAME=dgb_user
DB_PASSWORD=dgb_password
```

> **Tip**: Create the database first: `mysql -u root -e "CREATE DATABASE digital_guest_book;"`

### 4. Configure Turnstile (Required)

Cloudflare Turnstile is required for login and form submission. For development, use test keys:

```env
TURNSTILE_SITE_KEY=1x00000000000000000000AA
TURNSTILE_SECRET_KEY=1x0000000000000000000000000000000AA
```

> Get real keys at [Cloudflare Turnstile](https://developers.cloudflare.com/turnstile/).

### 5. Run Migrations & Seeders

```bash
php artisan migrate:fresh --seed
```

This creates:
- Default admin user (`admin@admin.com` / `admin123`)
- Sample form configuration ("Data Input Mahasiswa")
- Sample submissions for testing

### 6. Start the Backend Server

```bash
php artisan serve
```

Backend is now running at **http://localhost:8000**

---

## 🖥️ Frontend Setup

The frontend is a React SPA that connects to this backend API. You must run it alongside the backend.

### 1. Open a New Terminal

Keep the backend server running in your first terminal.

### 2. Navigate to Frontend Directory

```bash
cd frontend
```

(Or `cd ../frontend` from the `backend/` directory)

### 3. Install Dependencies

```bash
npm install
```

### 4. Configure Environment

Create `frontend/.env`:

```env
VITE_API_URL=http://localhost:8000/api
VITE_TURNSTILE_SITE_KEY=1x00000000000000000000AA
```

### 5. Start the Development Server

```bash
npm run dev
```

Frontend is now running at **http://localhost:5173**

### 6. Access the Application

Open http://localhost:5173 in your browser and log in:

- **Email**: `admin@dgb.local`
- **Password**: `password`

---

## 🔑 Default Credentials

> ⚠️ **Change these in production!**

| Role | Email | Password |
|------|-------|----------|
| Admin | `admin@dgb.local` | `password` |

---

## ⚡ One-Command Dev Setup

Laravel's `composer dev` script runs everything concurrently (server, queue, logs, Vite):

```bash
cd backend
composer dev
```

This starts:
- `php artisan serve` — API server
- `php artisan queue:listen` — Background job processor
- `php artisan pail` — Real-time log viewer
- `npm run dev` — Frontend Vite dev server

> Note: You still need to configure `frontend/.env` separately for the API URL.

---

## 🏗️ Architecture

This backend uses a **modular architecture** via [`nwidart/laravel-modules`](https://github.com/nWidart/laravel-modules). Each module is self-contained with its own routes, controllers, models, and views.

### Modules

| Module | Purpose |
|--------|---------|
| **FormBuilder** | Form configurations, submissions, auto-fill, reports, file uploads |
| **Administration** | Authentication, users, settings, 2FA, attendance manager, invitations |

### Directory Structure

```
backend/
├── app/
│   ├── Http/Controllers/Api/     # Core API controllers (auth, profile)
│   ├── Models/                   # Eloquent models
│   └── Services/                 # Business logic services
├── Modules/
│   ├── FormBuilder/              # Form builder module
│   │   ├── app/                  # Controllers, Models, Services
│   │   ├── routes/               # Module routes
│   │   └── config/               # Module config
│   └── Administration/           # Administration module
│       ├── app/                  # Controllers, Models, Services
│       ├── routes/               # Module routes
│       └── config/               # Module config
├── database/
│   ├── migrations/               # Database migrations
│   └── seeders/                  # Data seeders
├── routes/
│   ├── api.php                   # Auth routes
│   └── web.php                   # Web routes
└── config/                       # Laravel configuration
```

---

## 📡 API Endpoints

### Public Endpoints

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/api/public/settings` | Get public app settings |
| `GET` | `/api/public/form-config/{slug}` | Get form configuration |
| `GET` | `/api/public/lookup/{formSlug}` | Auto-fill lookup by unique field |
| `GET` | `/api/public/attend/{identifier}` | Get attendee info for check-in |
| `POST` | `/api/public/attend/check-in` | Self check-in for attendees |
| `POST` | `/api/submissions/{formSlug}` | Submit a form (requires Turnstile) |

### Auth Endpoints

| Method | Path | Description |
|--------|------|-------------|
| `POST` | `/api/login` | Login (email + password + Turnstile) |
| `POST` | `/api/login/2fa` | Verify 2FA code |
| `POST` | `/api/logout` | Logout (auth required) |
| `GET` | `/api/user` | Get current user (auth required) |

### Profile Endpoints

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/api/profile` | Get profile |
| `PUT` | `/api/profile` | Update profile |
| `PUT` | `/api/profile/password` | Change password |
| `GET` | `/api/profile/login-history` | Login history |
| `POST` | `/api/profile/two-factor/enable` | Enable 2FA |
| `POST` | `/api/profile/two-factor/verify` | Verify 2FA setup |
| `POST` | `/api/profile/two-factor/disable` | Disable 2FA |

### Admin Endpoints (Auth Required)

#### Form Configurations

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/api/admin/form-configs` | List all forms |
| `POST` | `/api/admin/form-configs` | Create form |
| `GET` | `/api/admin/form-configs/{slug}` | Get form by slug |
| `PUT` | `/api/admin/form-configs/{slug}` | Update form |
| `DELETE` | `/api/admin/form-configs/{slug}` | Delete form |

#### Submissions

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/api/admin/submissions` | List submissions (filterable) |
| `GET` | `/api/admin/submissions/{id}` | Get submission details |
| `GET` | `/api/admin/submissions/stats` | Submission statistics |
| `GET` | `/api/admin/submissions/export` | Export to CSV |
| `GET` | `/api/admin/submissions/export/xlsx` | Export to Excel |

#### File Uploads

| Method | Path | Description |
|--------|------|-------------|
| `POST` | `/api/admin/upload/file` | Upload a file |
| `POST` | `/api/admin/upload/image` | Upload an image |
| `GET` | `/api/upload/{id}` | Serve uploaded file |

#### Reports

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/api/admin/reports/dynamic-fields` | Get chart-compatible fields |
| `GET` | `/api/admin/reports/dynamic` | Get aggregated report data |
| `GET` | `/api/reports/submissions-over-time` | Submissions over time chart |

#### Admin Users (Admin Role Required)

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/api/admin-users` | List users |
| `POST` | `/api/admin-users` | Create user |
| `PUT` | `/api/admin-users/{id}` | Update user |
| `DELETE` | `/api/admin-users/{id}` | Delete user |
| `GET` | `/api/admin-users/template` | Download bulk upload template |
| `POST` | `/api/admin-users/bulk` | Bulk upload users via Excel |

#### Settings (Admin Role Required)

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/api/admin/settings` | Get app settings |
| `PUT` | `/api/admin/settings` | Update app settings |
| `POST` | `/api/admin/settings/logo` | Upload logo |
| `POST` | `/api/admin/settings/favicon` | Upload favicon |

#### Attendance Manager

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/api/attendance/events` | List events |
| `POST` | `/api/attendance/events` | Create event |
| `GET` | `/api/attendance/events/{slug}` | Get event details |
| `PUT` | `/api/attendance/events/{slug}` | Update event |
| `DELETE` | `/api/attendance/events/{slug}` | Delete event |
| `GET` | `/api/attendance/events/{slug}/attendees` | List event attendees |
| `POST` | `/api/attendance/events/{slug}/attendees/bulk-import` | Bulk import attendees |
| `POST` | `/api/attendance/check-in` | Admin manual check-in |
| `GET` | `/api/attendance/events/{slug}/report` | Get event attendance report |
| `GET` | `/api/attendance/events/{slug}/export` | Export attendance to Excel |

---

## 🧪 Testing

### Backend Tests

```bash
cd backend

# Run all tests
php artisan test

# Run with coverage
php artisan test --coverage

# Run specific test class
php artisan test --filter=Submission
```

**Test Stats**: 242 tests, 749 assertions covering models, services, controllers, API endpoints, validation, and auth.

### Frontend Tests

```bash
cd frontend

# Run tests once
npm run test:run

# Run in watch mode
npm test
```

---

## 🔧 Troubleshooting

### "No form config found" error

Run the seeder to create a default form:

```bash
cd backend
php artisan db:seed
```

### Login fails with "cf_turnstile_response is required"

Turnstile is required for login. Ensure keys are configured in both `.env` files:

```env
# backend/.env
TURNSTILE_SITE_KEY=1x00000000000000000000AA
TURNSTILE_SECRET_KEY=1x0000000000000000000000000000000AA

# frontend/.env
VITE_TURNSTILE_SITE_KEY=1x00000000000000000000AA
```

### Auto-fill not working on public form

Check that:
1. The field is marked as `is_unique: true` in the form builder
2. There are existing submissions with matching data (5+ character input required)
3. The `PublicFormConfigResource` returns `is_unique` in the API response

### CORS errors

Verify `FRONTEND_URL` in `backend/.env` matches your frontend URL:

```env
FRONTEND_URL=http://localhost:5173
```

### Import errors in frontend

Ensure all dependencies are installed:

```bash
cd frontend
npm install
```

---

## 📚 More Documentation

- **[Project README](../README.md)** — Full architecture, field types, usage guides, deployment
- **[Quick Start Guide](../QUICK-START.md)** — Detailed setup walkthrough with troubleshooting
- **[Migration Guide](../MIGRATION-GUIDE.md)** — Migration from legacy static fields to dynamic forms

---

## 📄 License

Private and proprietary.
