# AMRV: AI-Powered Medical Report Vault

AMRV (AI-Powered Medical Report Vault) is a privacy-first, cross-platform personal health archiving system. It enables users to securely store medical reports (lab results, imaging scans, prescriptions, discharge summaries), automatically extract structured health metrics using OCR and AI, visualize their longitudinal health timeline, and securely converse with an AI medical assistant about their records.

The architecture comprises three main components:
1. **Laravel Web & API Backend** — Manages database records, user profiles, storage references, asynchronous jobs, and handles real-time WebSockets broadcasts.
2. **Flutter Mobile Application** — Serves as the primary user-facing touchpoint for mobile camera scanning, secure authentication, and interactive chat.
3. **Python AI Microservice** — Executes heavy OCR, processes document classification, parses structured medical entities, and handles streaming conversational LLM interactions.

---

## 🛠️ Project Architecture & Tech Stack

### 1. Backend (Laravel Framework)
- **Framework**: Laravel 12 (running on PHP 8.4)
- **Database**: PostgreSQL 17 (for persistent application data)
- **Caching & Queue**: Redis 8 (as cache store and background queue listener)
- **Real-Time Communication**: Laravel Reverb (WebSocket server)
- **Storage Provider & Security**: Azure Blob Storage (via `microsoft/azure-storage-blob` SDK) for secure private file storage
- **Authentication**: JWT-based API Token auth (`JwtService`) and Laravel Sanctum
- **Interactive UI**: Livewire v4 + TailwindCSS v4 + Vite asset compiler (for internal web dashboards)
- **API Documentation**: OpenAPI / Swagger UI (available at `/swagger`)

### 2. Mobile App (Flutter Client)
- **Framework**: Flutter (Dart)
- **Authentication**: Secure credential-based login (Email/Password) and integrated Google Sign-In
- **Real-Time Sync**: WebSockets connection via Laravel Echo/Reverb client for instant analysis status updates
- **Notifications**: Firebase Cloud Messaging (FCM) for background push alerts
- **Capture**: Native camera and photo library access for instant image-to-report uploads

### 3. AI Service (Python Microservice)
- **Framework**: Python 3.11+ / FastAPI
- **OCR Engine**: Tesseract OCR / Azure Computer Vision API / Google Cloud Vision (depending on deployment target)
- **NLP / LLM processing**: Custom integration with OpenAI / Google Gemini SDKs for zero-shot medical entity extraction, classification, and summarization
- **Streaming Service**: Server-Sent Events (SSE) for streaming assistant chat completions via FastAPI asynchronous streams

---

## 🔒 Security & Privacy Features

To safeguard patient health information (PHI), AMRV implements robust security mechanisms centered around **Azure Blob Storage** and strict authentication protocols:

* **Private Azure Storage Containers**: The Azure container hosting medical reports blocks all public read/write access. Reports are never directly exposed to the internet.
* **User Isolation Directory Partitioning**: Documents are structured hierarchically under user-specific paths (`user/{userId}/reports/`). Users can only access blobs within their designated path.
* **HMAC SHA-256 SharedKey Signatures**: File downloads and streaming retrievals do not use static URLs. Instead, the backend generates dynamic, short-lived `SharedKey` authorization signatures using HMAC SHA-256 with the storage account access key.
* **Backend File Proxying**: Raw files are fetched and streamed through backend validation controllers, preventing external clients from gaining permanent direct access to the storage provider.
* **Authentication Controls**: Robust authentication protocols enforce user access via JWT tokens, supporting both traditional login credentials and secure Google OAuth flows.

---

## 🐳 Dockerized Local Environment Setup

The backend features a fully containerized development stack built on Docker Compose.

### Services Defined:
* **`app`**: PHP 8.4-FPM container configured with PostgreSQL, Redis, GD, BCMath, and Zip extensions.
* **`nginx`**: Lightweight Nginx web server configured on port `8088`.
* **`postgres`**: PostgreSQL 17 database instance configured on port `5433`.
* **`redis`**: Redis 8 Alpine container configured on port `6379`.
* **`reverb`**: Second PHP container running the Laravel Reverb WebSocket server on port `8081`.
* **`queue`**: Third PHP container dedicated to running background queue workers.

### Quick Start Setup Guide

Follow these steps to configure and boot up the project locally:

1. **Clone and Navigate to Project Directory:**
   ```bash
   cd AMRV
   ```

2. **Configure Environment Variables:**
   Copy the example environment configuration. Ensure you fill in the placeholders in `.env` without exposing keys to git.
   ```bash
   cp .env.example .env
   ```

3. **Spin Up the Containers:**
   Start all backend services in detached mode:
   ```bash
   docker compose up -d --build
   ```

4. **Initialize the Application:**
   Run the automated installation script inside the running `app` container to install dependencies, generate application keys, and run migrations:
   ```bash
   docker compose exec app composer run setup
   ```
   *Note: This script installs PHP composer dependencies, copies the env file if missing, generates the app key, forces database migrations, runs npm installs, and builds Vite assets.*

5. **Set Permissions (If required):**
   ```bash
   docker compose exec app chmod -R 775 storage bootstrap/cache
   ```

6. **Verify Running Containers:**
   Check the status of your stack:
   ```bash
   docker compose ps
   ```
   You should see `amrv_app`, `amrv_nginx`, `amrv_postgres`, `amrv_redis`, `amrv_reverb`, and `amrv_queue` running.

7. **Access the Applications:**
   * **Web API / Swagger UI**: [http://localhost:8088/swagger](http://localhost:8088/swagger)
   * **WebSocket server**: `ws://127.0.0.1:8081`

---

## 📂 Project Structure & Features Detail

### 1. Laravel Backend features
* **Secure Storage Hook**: Controllers upload files directly to Azure Blob Storage container. No raw PDF text is saved in unencrypted databases.
* **Asynchronous Flow Orchestration**: 
  - `ProcessMedicalReportJob` handles the lifecycle of report processing. It fires events (`OcrStarted`, `OcrCompleted`, `AiProcessing`) to update the frontend.
  - Sends a webhook callback (`POST /api/webhooks/report-processing-complete`) to finalize and save structured medical entities.
* **FCM Push Notification dispatching**:
  - `SendPushNotificationJob` handles token registration and async dispatching to user devices when report reviews are completed.
* **API Endpoints (`app/Http/Controllers/Api/V1`)**:
  - `/devices` - Register and de-register active device FCM tokens for notifications.
  - `/notifications` - Fetch paginated user alerts, mark individual/all as read.
  - `/reports` - Create, view, categorize, and archive reports.

### 2. Flutter Mobile Application features
* **Secure Front-End Authentication**: Normal email/password credentials login accompanied by Google Auth authentication flows.
* **Structured Medical Timeline**: Chronological presentation of user's medical records with smart filtering by report type (lab results, scans, prescriptions, discharge summary).
* **Profile Vault Access**: Users can associate reports with specific family profiles (Self, Parent, Child, Spouse) to organize records.
* **WebSockets Integration**: Connects to the private user channel `reports.{user_id}`. It updates the UI dynamically during processing (e.g. showing "Extracting text...", "Structuring entities...", "Done").

### 3. Python AI Service features
* **Report Analysis API (`POST /api/v1/reports/analyze`)**:
  Receives document metadata from Laravel, fetches the file from Azure storage, runs OCR (via Tesseract or OCR API), and executes LLM-based entity extraction returning:
  - **Patient Details**: Name, contact details, birthdate.
  - **Report Details**: Title, Doctor name, Lab/Hospital name, Date.
  - **Medical Entities**: Structured list of laboratory values (e.g. Hemoglobin), values, units, reference ranges, status (Normal, Low, High, Critical).
  - **Knowledge Summary**: High-level explanation, confidence score, risk level, non-diagnostic wellness recommendations, and search indexing tags.
* **Real-time Report Chat Assistant (`POST /api/v1/chat/report-assistant`)**:
  Maintains session context (`session_id`) and permits patients to query their reports, streaming back responses over SSE with a strictly cautious, non-diagnostic medical filter.

---

## 📦 Key Dependencies

### PHP (Laravel) Dependencies:
* `laravel/framework: ^12.0` - Primary MVC application skeleton.
* `laravel/reverb: ^1.10` - High-performance WebSocket server.
* `livewire/livewire: ^4.3` - Dynamic frontend component framework.
* `microsoft/azure-storage-blob: ^1.5` - Azure SDK integration.
* `simplesoftwareio/simple-qrcode: ^4.2` - QR code utility.
* `yajra/laravel-datatables: ^12.0` - Quick backend table rendering.
* `laravel/sanctum: ^4.3` & `laravel/breeze: ^2.4` (Dev) - Authentication packages.
* `laravel/sail: ^1.41` (Dev) - Docker Sail scaffolding.

### Python (AI Service) Dependencies:
* `fastapi: ^0.110.0` - Modern, high-performance web framework for APIs.
* `uvicorn: ^0.28.0` - ASGI web server implementation.
* `sse-starlette: ^2.0.0` - Server-Sent Events support for streaming.
* `requests` - Webhook callback handler.
* `pydantic: ^2.6.0` - JSON structure validations.
* `pytesseract` / `pdf2image` - PDF text and OCR extraction tools.
* `openai` / `google-generativeai` - LLM clients for report synthesis.

### Flutter Dependencies:
* `google_sign_in` - Google OAuth authentication client.
* `flutter_secure_storage` - Secure local cache storage helper.
* `firebase_messaging` - Firebase messaging configuration wrapper.
* `laravel_echo` & `pusher_client` - WebSockets client integration.

---

## 🧪 Testing Suite

### Running local tests within Docker:
Execute application test suites inside your running PHP container.

* **Run all tests:**
  ```bash
  docker compose exec app php artisan test
  ```
* **Run specific notification integration tests:**
  ```bash
  docker compose exec app php artisan test --filter NotificationTest
  ```