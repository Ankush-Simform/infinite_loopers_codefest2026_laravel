# AMRV - Medical Report Analysis & Chat Assistant

AMRV is a Laravel-based API backend designed to process and analyze medical reports using AI models and enable conversational assistant interactions for patients. It supports automated database (in-app) and mobile device push notifications using Google Firebase Cloud Messaging (FCM), as well as real-time progress updates over WebSockets using Laravel Reverb.

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

To start the **Laravel Reverb WebSocket server** locally for real-time status updates:
```bash
php artisan reverb:start
```

---

## Azure Blob Storage Integration

All file uploads (e.g. profile photos and original medical reports) are uploaded directly to Azure Blob Storage. Configure the credentials in your `.env` file:

```env
AZURE_STORAGE_NAME="your_storage_account_name"
AZURE_STORAGE_CONTAINER="your_container_name"
AZURE_STORAGE_KEY="your_base64_encoded_storage_key"
```

---

## Asynchronous WebSockets & ML Pipeline Flow

Medical report uploads are **fully asynchronous**. The frontend uploads the report and immediately receives an `uploaded` status, then listens to real-time events over WebSockets as the background queue job processes the file.

### 1. Real-Time Socket Events Flow
Users listen on the private channels:
- `private-reports.{user_id}`
- `private-user.{user_id}`

Events are broadcasted in the following order during processing:
- **`ReportUploaded`** — Dispatched instantly upon HTTP upload. Status becomes `uploaded`.
- **`OcrStarted`** — Fired when text extraction starts.
- **`OcrCompleted`** — Fired when text extraction completes.
- **`AiProcessing`** — Fired when AI summary and entity analysis starts.
- **`ReportProcessingCompleted`** — Fired when the ML service sends back the complete analysis payload. Status becomes `completed`.
- **`ReportProcessingFailed`** — Fired if any error occurs in the pipeline.

### 2. ML Webhook endpoint
Once the AI model completes analysis, the ML service posts the payload back to Laravel:
- **URL**: `POST /api/webhooks/report-processing-complete`
- **Payload**:
  ```json
  {
      "report_id": 15,
      "summary": "AI Generated Report Summary",
      "report_type": "blood_test",
      "extracted_text": "Raw OCR content...",
      "risk_level": "Low",
      "confidence_score": 98.5,
      "recommendations": ["Recommendation 1", "Recommendation 2"],
      "medical_entities": [
          {
              "entity_type": "vital",
              "entity_name": "Systolic Blood Pressure",
              "value": "120",
              "unit" : "mmHg"
          }
      ]
  }
  ```

---

## Push & In-App Notifications Integration

This application delivers real-time notifications to users. When report review is finalized, an in-app notification is recorded in the database, and a push notification is dispatched asynchronously to all active registered devices via Firebase Cloud Messaging (FCM).

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
If you've already run the initial setup, execute the migrations to generate the device registration table:
```bash
php artisan migrate
```

---

## API Documentation (Swagger)

AMRV provides an interactive Swagger UI to review and test all available endpoints directly from your browser.

- **URL**: `http://localhost:8000/swagger` (redirects to `/swagger/index.html`)
- **JSON Spec**: Located in `public/swagger/openapi.json`

---

## Running Tests

All features are fully covered by a feature test suite running on a testing MySQL database.

To execute the notification and WebSocket flow test suite:
```bash
php artisan test --filter NotificationTest
```

To run all application tests:
```bash
php artisan test
```
