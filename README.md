# AMRV - Medical Report Analysis & Chat Assistant

AMRV is a Laravel-based API backend designed to process and analyze medical reports using AI models and enable conversational assistant interactions for patients. It supports automated database (in-app) and mobile device push notifications using Google Firebase Cloud Messaging (FCM).

---

## Getting Started

### 1. Requirements
- PHP >= 8.2 (with OpenSSL and standard extensions)
- MySQL / MariaDB
- Composer
- Node.js & npm

### 2. Installation & Setup
Run the automated composer setup script which installs dependencies, creates your environment configuration, generates keys, and executes database migrations:
```bash
composer run setup
```

### 3. Running the Development Server
You can run the web server, Vite, and the background queue listener concurrently using:
```bash
composer run dev
```

---

## Push & In-App Notifications Integration

This application delivers real-time notifications to users. When the AI finishes processing a report (upon review finalization), an in-app notification is recorded in the database, and a push notification is dispatched asynchronously to all active registered devices via Firebase Cloud Messaging (FCM).

### 1. Environment Variables Configuration
To enable live FCM push notifications, add the following variables to your `.env` file:
```env
# Path to your Google Service Account Credentials JSON file
FIREBASE_CREDENTIALS=/path/to/your/firebase-service-account.json

# Your Firebase Project ID
FIREBASE_PROJECT_ID=your-firebase-project-id
```
*Note: If these variables are not configured (or the credentials file is not found), the notification service will fallback to a **Mock Driver** that safely logs details to `storage/logs/laravel.log` so the application can run and be developed without needing credentials.*

### 2. Database Migrations
If you've already ran the initial setup, execute the migrations to generate the device registration table:
```bash
php artisan migrate
```

### 3. Background Queue Worker
Push notifications are dispatched asynchronously via a queued job (`SendPushNotificationJob`). Make sure you run a queue worker if you are not running the dev script:
```bash
php artisan queue:work
# or
php artisan queue:listen
```

---

## API Documentation (Swagger)

AMRV provides an interactive Swagger UI to review and test all available endpoints directly from your browser.

- **URL**: `http://localhost:8000/swagger` (redirects to `/swagger/index.html`)
- **JSON Spec**: Located in `public/swagger/openapi.json`

### Key Endpoints Added:
- **Device Management**:
  - `POST /api/v1/devices` — Register or update an active FCM token. Overwrites ownership automatically if multiple users share the same device.
  - `DELETE /api/v1/devices` — De-register an FCM token on logout/cleanup.
- **Notification Management**:
  - `GET /api/v1/notifications` — Retrieve paginated notifications (accepts `unread_only=true` filter).
  - `PATCH /api/v1/notifications/{id}/read` — Mark an individual notification as read.
  - `POST /api/v1/notifications/read-all` — Mark all unread notifications as read.

---

## Running Tests

All features are fully covered by a feature test suite running on a testing MySQL database.

To execute the notification specific test suite:
```bash
php artisan test --filter NotificationTest
```

To run all application tests:
```bash
php artisan test
```
