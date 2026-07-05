# AI-Powered Medical Report Vault

## Project Instruction File

This document provides high-level guidance for the AI engineering team to understand the project, features, and integration requirements based on project discussions and design decisions.

---

## 1. Project Overview

### What We Are Building

We are building **AI-Powered Medical Report Vault**, a privacy-first personal health application where users can:

- Upload medical reports (lab results, scan reports, discharge summaries, prescriptions).
- Let AI automatically analyze, categorize, and extract structured data from each report.
- Store everything securely in an encrypted vault.
- View their health history through a timeline or categorized views.
- Chat with an AI assistant about their own stored reports.
- Keep sensitive reports locked for extra privacy.

The core idea is to replace scattered PDFs, WhatsApp files, and paper documents with a single organized, encrypted, AI-understandable health vault.

---

## 2. Core Value Proposition

### Why This Project?

- Users lose or forget their medical reports over time.
- Reports are spread across multiple channels (WhatsApp, email, hospital portals, paper).
- Existing solutions are either backend-heavy, tied to one hospital ecosystem, or too complex.
- Most apps require manual data entry or donâ€™t understand reports automatically.
- Privacy is a concern: users want to control who sees their health data.

### What Makes This Different?

Even though similar products exist (e.g., Apollo Health Records, Prism, ProHealth), our differentiation is:

1. **User-owned private vault**: Stronger privacy focus with clear â€œonly the user can read itâ€ positioning.
2. **AI-first document understanding**: Automatic extraction and confirmation from any report type, not just ecosystem-linked records.
3. **Cross-platform personal archive**: Bring together reports from anywhere, not only from one hospital chain.
4. **Simpler UX**: Faster, more streamlined experience than heavy enterprise health apps.

---

## 3. Technology Stack

### Backend

- Laravel (PHP)
- PostgreSQL database
- Azure Blob Storage for file storage

### Frontend

- React web application
- Flutter mobile application (Android/iOS)

### AI Service

- Custom AI microservice (to be implemented by AI team)
- Communicates with Laravel backend via REST APIs
- Reads medical reports from Azure Blob Storage
- Uses OCR + LLM for extraction, classification, summarization, entity detection, and tagging

### Storage

- Azure Blob Storage
- Files referenced in database by:
    - `storage_provider`
    - `container`
    - `blob_name`

---

## 4. Core Features

### Feature 1: Encrypted Personal Health Vault

Users can upload and store all types of medical documents:

- Lab results (blood tests, urine tests, hormone tests, etc.)
- Imaging reports (X-ray, CT, MRI, ultrasound)
- Discharge summaries
- Prescriptions
- Vaccination records
- Other health-related documents

Data is stored in encrypted form:

- Backend and developers cannot read report contents in plain text.
- Only the authenticated user can access decrypted content.
- Sensitive documents can be marked as â€œlockedâ€ with extra access control.

This aligns with privacy principles of modern personal health record applications.

---

### Feature 2: AI-Assisted Report Capture

When a report is uploaded:

1. The AI service downloads the file from storage.
2. Performs OCR if the file is scanned or image-based.
3. Classifies the report type.
4. Extracts report metadata:
    - Title (e.g., â€œComplete Blood Countâ€)
    - Doctor name
    - Hospital/lab name
    - Report date
5. Extracts patient/profile details:
    - Name
    - Contact number
    - Email
    - Birthdate
    - Height/weight (if available)
6. Extracts structured medical entities:
    - Lab metrics (e.g., Hemoglobin, WBC, TSH, LDL)
    - Values, units, reference ranges
    - Flags like Low/Normal/High, Positive/Negative
7. Generates a knowledge summary:
    - Concise explanation of the report.
    - Risk level (Low, Medium, High, Critical, Unknown).
    - Non-diagnostic recommendations.
    - Confidence score.
8. Generates tags for indexing and search.

The user sees an auto-filled form with AI-extracted data and can confirm or edit it. Once confirmed, the structured data is saved to the database alongside the encrypted file.

---

### Feature 3: Structured Health Timeline

Each confirmed report becomes a structured record:

- Dates, type, tests, doctors, tags.

Users can:

- View a chronological timeline of their health journey.
- Filter by report type (lab, imaging, discharge, prescription).
- Search by doctor, hospital, or test name.
- Group reports by category or specialty.

This helps users quickly find past results and prepare better for doctor visits.

---

### Feature 4: Report Locking & Privacy Controls

Users can mark individual reports as locked:

- For sensitive conditions like mental health, reproductive health, HIV tests, etc.
- Locked reports require an extra unlock action:
    - Re-enter password on web.
    - Biometric or PIN on mobile.

This reinforces the â€œvaultâ€ concept and gives users concrete privacy control.

---

### Feature 5: Cross-Platform Access

- Web app (React) for rich dashboard and document management.
- Mobile app (Flutter) for on-the-go access, camera upload, and viewing reports during visits.
- Both talk to the same Laravel backend and database.

---

### Feature 6: Future Extensions (Optional)

These are not required for the hackathon MVP but can be considered for roadmap:

- Export selected reports as PDF bundles.
- Time-limited share links for doctors.
- Medication tracking from prescriptions.
- Longitudinal health insights across multiple reports.
- Risk pattern detection.

---

## 5. AI Capabilities Required

### Report Analysis AI

The AI service must:

- Retrieve files from storage using metadata.
- Perform OCR on scanned PDFs or images.
- Classify report type.
- Extract report metadata and patient profile.
- Extract structured medical entities (lab metrics, values, units, reference ranges, status).
- Generate summary text, risk level, and recommendations.
- Generate tags for categorization.
- Return output in a strict JSON schema.

### Chat AI Assistant

The AI service must:

- Support chat sessions with context from userâ€™s reports.
- Use session_id to maintain conversation continuity.
- Answer questions about uploaded reports.
- Provide safe, non-diagnostic, medically cautious responses.
- Optionally cite report references used in the answer.

---

## 6. API Integration Expectations

The AI service will provide three main APIs:

### API 1: Analyze Medical Report

- Endpoint: `POST /api/v1/reports/analyze`
- Input: Report metadata
    - `id` (report ID)
    - `user_id` (user ID)
    - `title` (report title name)
    - `storage_provider` (storage provider)
    - `container` (storage container)
    - `blob_name` (blob name)
    - `original_file_name` (original file name)
    - `mime_type` (MIME type)
    - `size` (file size)
- Output: Structured JSON with:
    - `upload` (processing metadata)
    - `report` (extracted report details)
    - `report.profile` (patient details)
    - `knowledge` (summary + risk + recommendations + confidence)
    - `entities` (structured lab metrics and other entities)
    - `tags` (categorization tags)

The AI service will:

- Read the file from storage.
- Perform OCR if needed.
- Execute extraction and summarization.
- Return structured output.

---

### API 2: Analysis Status Webhook

- Endpoint: `POST /api/v1/ai/report-analysis/webhook` (on Laravel side)
- Purpose: Notify Laravel when analysis is completed or failed.
- Payload:
    - `status`: true/false
    - `message`: human-readable status
    - `code`: HTTP-style code
    - `data`: same structure as analysis API response

The AI team must:

- Call this webhook when analysis is done.
- Support retry mechanism for delivery failures.
- Use request signing or shared secret for verification.

---

### API 3: Medical Report AI Chat Session

- Endpoint: `POST /api/v1/chat/report-assistant`
- Input:
    - `user_id`
    - `session_id`
    - `user_text` (user message)
    - `user_attachments` (optional, array of report IDs)
        - Each attachment includes:
            - `user_id` (user ID)
            - `title` (report title name)
            - `storage_provider` (storage provider)
            - `container` (storage container)
            - `blob_name` (blob name)
            - `original_file_name` (original file name)
            - `mime_type` (MIME type)
            - `size` (file size)
    - `user_report_blob_path` (path to relevant reports)
- Output:
    - `status`: true/false
    - `message`: success/failure
    - `code`: HTTP-style code
    - `data`:
        - `user_id`
        - `session_id`
        - `response_text` (AI response)

The AI service must:

- Use available report context.
- Answer safely and non-diagnostically.
- Maintain session continuity using `session_id`.

---

## 7. Functional Flow

### Report Analysis Flow

1. User uploads a medical report via React/Flutter.
2. Backend stores file in Azure Blob Storage and saves metadata in PostgreSQL.
3. Backend calls the AI analysis API with metadata and blob path.
4. AI service downloads the file from storage.
5. AI processes:
    - OCR
    - Classification
    - Metadata and profile extraction
    - Entity extraction
    - Summarization
    - Tag generation
6. AI returns analysis result or processes asynchronously.
7. When final processing is done, AI sends webhook callback to Laravel.
8. Laravel updates processing state and notifies client.

### Chat Flow

1. User opens AI assistant chat.
2. Backend sends `user_id`, `session_id`, user message, and report path to AI chat API.
3. AI uses report context to answer.
4. Response is returned to Laravel.
5. Laravel passes response back to client UI.

---

## 8. Validation and Safety Rules

### Input Validation

- Reject unsupported MIME types.
- Validate blob path and storage metadata are present.
- Validate file size limits.
- Validate required fields in every request.
- Reject malformed session IDs or empty user text in chat.

### Output Validation

- Ensure required fields exist.
- Normalize enums like risk level and status.
- Ensure confidence score is numeric and bounded 0â€“100.
- Ensure tags array is always returned.
- Respect nullability rules consistently.

### Safety Expectations

- Summaries and recommendations must be informational, not diagnostic.
- Use cautious wording for high-risk or uncertain outputs.
- Avoid hallucinating doctor, hospital, or patient details when confidence is low.
- If extraction is uncertain, return `null`, not fabricated values.

---

## 9. Non-Functional Requirements

- Support asynchronous execution for large reports.
- Reasonable timeout handling for OCR-heavy documents.
- Structured logging for each request lifecycle.
- Traceability using report ID, user ID, temporary file ID.
- Webhook retry policy for delivery failures.
- Versioned API routes for future schema evolution.
- Backward-compatible response handling.

---
