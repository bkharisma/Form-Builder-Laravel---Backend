<div align="center">

# 📝 Form Builder - Frontend

### Modern drag-and-drop form builder SPA built with React 19, TypeScript, and Vite

[![React 19](https://img.shields.io/badge/react-19-61dafb.svg?style=for-the-badge&logo=react&logoColor=black)](https://react.dev/)
[![TypeScript](https://img.shields.io/badge/typescript-5.x-3178c6.svg?style=for-the-badge&logo=typescript&logoColor=white)](https://www.typescriptlang.org/)
[![Vite](https://img.shields.io/badge/vite-6.x-646cff.svg?style=for-the-badge&logo=vite&logoColor=white)](https://vite.dev/)
[![TailwindCSS 4](https://img.shields.io/badge/tailwindcss-4.x-06b6d4.svg?style=for-the-badge&logo=tailwindcss&logoColor=white)](https://tailwindcss.com/)
[![Testing: Vitest](https://img.shields.io/badge/testing-vitest-6da13f.svg?style=for-the-badge)](https://vitest.dev/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg?style=for-the-badge)](https://opensource.org/licenses/MIT)

A single-page application for building forms, managing submissions with generating dynamic reports and autofilled form with unique data type feature  — all connected to a Laravel backend API.

[Quick Start](#quick-start) · [Features](#features) · [Project Structure](#project-structure) · [Configuration](#configuration) · [Scripts](#available-scripts) · [Troubleshooting](#troubleshooting)

---

</div>

<div align="center">
  <img src="Dashboard.png" alt="Form Builder in action" width="500"> <img src="Field type.png" alt="Form Builder in action" width="500"><br/>
  <img src="autofill.gif" alt="Form Builder in action" width="400"> <img src="Dynamic Report.gif" alt="Form Builder in action" width="400">
  <p><em>Drag-and-drop form builder with live preview and 13+ field types</em></p>
  <a href="https://formbuilder.kdigital.technology"><p>Form Builder tiral visit this site</p></a>
</div>

## Features

| Feature                      | Description                                                                                  |
| ---------------------------- | -------------------------------------------------------------------------------------------- |
| **Public Form Submission**   | Users fill out forms at `/form/{slug}` — no login required                                   |
| **Admin Dashboard**          | Manage forms, view submissions, generate reports from a single panel                        |
| **Drag-and-Drop Builder**    | Visual field editor with 13+ field types powered by `@dnd-kit`                              |
| **Auto-Fill**                | Automatically populate fields from previous submissions                                     |
| **Dynamic Reports**          | Chart-based analytics with Chart.js — bar, pie, line, and more                              |
| **Export**                   | Download submissions as CSV, Excel (XLSX), or PDF                                            |
| **Rich Text Editor**         | `@tiptap` powered WYSIWYG field type                                                        |
| **Signature Capture**        | `react-signature-canvas` for handwritten signature fields                                   |
| **Responsive**               | Fully functional on mobile and desktop                                                       |
| **Bot Protection**           | Cloudflare Turnstile on login and form submission                                            |

## Quick Start

### Prerequisites

| Tool        | Version                  |
| ----------- | ------------------------ |
| **Node.js** | 18+                      |
| **npm**     | 9+                       |
| **PHP**     | 8.3+ (for backend)       |
| **Composer** | 2.x (for backend)       |
| **MySQL**   | 5.7+ / MariaDB 10.3+     |

### 1. Backend Setup (Required First)

The frontend is a SPA — all data comes from the Laravel backend API. You **must** set it up first.

```bash
cd ../backend

composer install
cp .env.example .env
php artisan key:generate
```

Edit `backend/.env` and configure your database:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=form_builder
DB_USERNAME=your_user
DB_PASSWORD=your_password
```

Run migrations and seeders (creates the default admin user and a sample form):

```bash
php artisan migrate:fresh --seed
```

<details>
<summary><b>Cloudflare Turnstile Setup</b> (required for login & form submission)</summary>

Turnstile is required for bot protection. For development, use Cloudflare's test keys:

```env
# In backend/.env
TURNSTILE_SITE_KEY=1x00000000000000000000AA
TURNSTILE_SECRET_KEY=1x0000000000000000000000000000000AA
```

For production, get real keys at [Cloudflare Turnstile Dashboard](https://dash.cloudflare.com/?to=/:account/turnstile).

</details>

Start the backend server:

```bash
php artisan serve
# API available at http://localhost:8000
```

### 2. Frontend Setup

```bash
cd frontend

npm install
cp .env.example .env
```

Edit `frontend/.env`:

```env
# API URL — for local dev, the Vite proxy handles this
# (requests to /api and /storage are proxied to http://localhost:8000)
VITE_API_URL=/api

# Cloudflare Turnstile site key (use test key for development)
VITE_TURNSTILE_SITE_KEY=1x00000000000000000000AA

# App name shown in browser tab
VITE_APP_NAME="Form Builder"
```

Start the development server:

```bash
npm run dev
# Frontend available at http://localhost:5173
```

### 3. Log In

Go to [http://localhost:5173](http://localhost:5173):

| Field      | Value            |
| ---------- | ---------------- |
| **Email**  | `admin@dgb.local` |
| **Password** | `password`      |

---

## How It Connects to the Backend

The Vite dev server proxies API requests so both frontend and backend run seamlessly in development:

```
┌──────────────────┐        ┌──────────────────────┐        ┌──────────────────┐
│  Browser         │───────>│  Vite Dev Server     │───────>│  Laravel API     │
│  localhost:5173  │<───────│  (Proxy)             │<───────│  localhost:8000  │
└──────────────────┘        └──────────────────────┘        └──────────────────┘
   /api/* → proxied             /api, /storage                REST API
   /storage/* → proxied         forwarded                     + Storage
```

Configured in `vite.config.ts`:

```ts
server: {
  proxy: {
    '/api': { target: 'http://localhost:8000', changeOrigin: true },
    '/storage': { target: 'http://localhost:8000', changeOrigin: true },
  },
}
```

<details>
<summary><b>Production Deployment</b></summary>

For production, set `VITE_API_URL` to the full backend URL, rebuild, and serve the `dist/` folder:

```env
VITE_API_URL=https://your-api-domain.com/api
```

```bash
npm run build    # Output goes to dist/
```

Serve `dist/` with any static file server (Nginx, Apache, etc.). Ensure the backend CORS `FRONTEND_URL` matches your production domain.

</details>

---

## Project Structure

```
frontend/
├── public/                  # Static assets (favicon, etc.)
├── src/
│   ├── components/          # Reusable UI components
│   ├── pages/               # Page-level components
│   │   ├── LandingPage.tsx  # Public landing page
│   │   ├── LoginPage.tsx    # Admin login
│   │   ├── DashboardPage.tsx
│   │   ├── SubmissionPage.tsx  # Public form submission
│   │   ├── SettingsPage.tsx
│   │   ├── ProfilePage.tsx
│   │   ├── AdminUsersPage.tsx
│   │   ├── FormDashboardPage.tsx
│   │   └── form/            # Form-related pages
│   │       ├── FormBuilderPage.tsx
│   │       ├── FormSubmissionsPage.tsx
│   │       ├── FormReportsPage.tsx
│   │       ├── FormTitlePage.tsx
│   │       └── FormDashboardListPage.tsx
│   ├── services/            # API service layer (axios)
│   ├── hooks/               # Custom React hooks
│   ├── types/               # TypeScript type definitions
│   ├── utils/               # Utility functions
│   ├── config/              # App configuration
│   ├── layouts/             # Layout components
│   ├── assets/              # Images, fonts, etc.
│   ├── test/                # Test setup and utilities
│   ├── App.tsx              # Root component with routing
│   ├── App.css              # Global styles
│   ├── index.css            # TailwindCSS imports
│   └── main.tsx             # Entry point
├── .env                     # Environment variables
├── .env.example             # Environment template
├── index.html               # HTML entry point
├── vite.config.ts           # Vite + Vitest configuration
├── tsconfig.json            # TypeScript configuration
├── eslint.config.js         # ESLint configuration
└── package.json             # Dependencies and scripts
```

---

## Key Dependencies

| Package                       | Purpose                              |
| ----------------------------- | ------------------------------------ |
| **React 19**                  | UI framework                         |
| **TypeScript**                | Type safety                          |
| **Vite**                      | Build tool and dev server with HMR   |
| **TailwindCSS 4**             | Utility-first CSS framework          |
| **react-router-dom**          | Client-side routing (SPA)            |
| **axios**                     | HTTP client for API calls            |
| **@dnd-kit**                  | Drag-and-drop for Form Builder       |
| **@tiptap**                   | Rich text editor field type          |
| **Chart.js + react-chartjs-2**| Dynamic report charts                |
| **jsPDF + autotable + html2canvas** | PDF export generation          |
| **react-signature-canvas**    | Signature capture field              |
| **Vitest + Testing Library**  | Unit and integration tests           |

---

## Configuration

### Environment Variables

| Variable                | Description                                        | Default                              |
| ----------------------- | -------------------------------------------------- | ------------------------------------ |
| `VITE_API_URL`          | Backend API base URL                               | `/api` (uses Vite proxy in dev)      |
| `VITE_TURNSTILE_SITE_KEY` | Cloudflare Turnstile site key                    | `1x00000000000000000000AA` (test key) |
| `VITE_APP_NAME`         | App display name in browser tab                    | `"Kopega Poltekpar Palembang APP"`   |

### Backend Variables (in `backend/.env`)

| Variable              | Description                       | Default   |
| --------------------- | --------------------------------- | --------- |
| `DB_CONNECTION`       | Database driver                   | `mysql`   |
| `DB_HOST`             | Database host                     | `127.0.0.1` |
| `DB_PORT`             | Database port                     | `3306`    |
| `DB_DATABASE`         | Database name                     | `form_builder` |
| `DB_USERNAME`         | Database user                     | —         |
| `DB_PASSWORD`         | Database password                 | —         |
| `TURNSTILE_SITE_KEY`  | Turnstile site key (must match frontend) | —   |
| `TURNSTILE_SECRET_KEY`| Turnstile secret key              | —         |
| `FRONTEND_URL`        | Frontend URL for CORS             | `http://localhost:5173` |

See [`.env.example`](.env.example) for all supported parameters.

---

## Available Scripts

| Command              | Description                                              |
| -------------------- | -------------------------------------------------------- |
| `npm run dev`        | Start dev server with HMR at [localhost:5173](http://localhost:5173) |
| `npm run build`      | Type-check and build for production (`dist/`)            |
| `npm run preview`    | Preview the production build locally                     |
| `npm run lint`       | Run ESLint checks                                        |
| `npm run test`       | Run tests in watch mode                                  |
| `npm run test:run`   | Run tests once (CI-friendly)                             |

---

## Troubleshooting

<details>
<summary><b>Login fails with "cf_turnstile_response is required"</b></summary>

Turnstile is required on the login page. Ensure both `.env` files have matching keys:

- `backend/.env` → `TURNSTILE_SITE_KEY` and `TURNSTILE_SECRET_KEY`
- `frontend/.env` → `VITE_TURNSTILE_SITE_KEY`

For development, use [Cloudflare's test keys](https://developers.cloudflare.com/turnstile/troubleshooting/testing/).

</details>

<details>
<summary><b>CORS errors</b></summary>

Make sure `FRONTEND_URL` in `backend/.env` matches your frontend URL:

```env
FRONTEND_URL=http://localhost:5173
```

</details>

<details>
<summary><b>npm install fails</b></summary>

Ensure Node.js 18+ is installed:

```bash
node -v   # Should be v18 or higher
npm -v    # Should be v9 or higher
```

If dependency conflicts persist, delete `node_modules` and `package-lock.json`, then retry:

```bash
rm -rf node_modules package-lock.json
npm install
```

</details>

<details>
<summary><b>API returns 404</b></summary>

The backend server must be running at `http://localhost:8000`. Start it with:

```bash
cd ../backend && php artisan serve
```

Also verify the Vite proxy is configured correctly in `vite.config.ts`.

</details>

---

## More Documentation

- **[../README.md](../README.md)** — Full project overview, architecture, and API reference
- **[../QUICK-START.md](../QUICK-START.md)** — Detailed setup guide for both frontend and backend
- **[../MIGRATION-GUIDE.md](../MIGRATION-GUIDE.md)** — Migration from legacy static fields

---

## Contributing

- Report bugs or suggest features via [Issues](https://github.com/YOUR_USERNAME/YOUR_REPO/issues)
- Improve test coverage
- Add new field types to the Form Builder
- Submit PRs with `npm run lint` and `npm run test:run` passing

```bash
git checkout -b my-feature
npm run lint && npm run test:run
# Open a pull request
```

---

## License

MIT License. See [LICENSE](LICENSE) for details.

Built with [React](https://react.dev/) · [TypeScript](https://www.typescriptlang.org/) · [Vite](https://vite.dev/) · [TailwindCSS](https://tailwindcss.com/) · [Chart.js](https://www.chartjs.org/)
```

**A few things to customize before using:**

1. **Screenshot** — Replace `pic.png` with an actual screenshot of your Form Builder UI, or remove the image block entirely.
2. **License badge** — I used MIT as a placeholder. Change it if your project uses a different license.
3. **GitHub links** — Replace `YOUR_USERNAME/YOUR_REPO` in the Contributing section with your actual repo path.
4. **`VITE_APP_NAME` default** — The default in your `.env.example` is `"Kopega Poltekpar Palembang APP"` which looks institution-specific. I kept it as-is in the table but used `"Form Builder"` in the setup example for clarity. Adjust as needed.
