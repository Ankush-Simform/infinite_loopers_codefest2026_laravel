Here's a more structured technical explanation that you can share with your teammate or AI agent. It explains the complete architecture, responsibilities, and event flow.

---

# Medical Report Upload & Processing Flow

## Overview

The report upload system is designed to be **fully asynchronous**. The frontend should never wait for AI processing to complete. Instead, the frontend and backend communicate through **WebSockets**, while heavy processing is handled through **Laravel Queues**, **Azure Blob Storage**, **Azure Functions**, and the **ML Service**.

The frontend should display the current processing stage in real time based on socket events.

---

# 1. WebSocket Connection

As soon as the user opens the application:

* The Flutter application establishes a WebSocket connection.
* Laravel also establishes the socket connection.
* Every authenticated user subscribes to their own private channel.

Example:

```
user.{user_id}
```

or

```
reports.{user_id}
```

Every report-processing event will be published only to that user's channel.

This ensures:

* real-time updates
* no polling
* complete isolation between users

---

# 2. Report Upload

When the user uploads a report:

Frontend sends:

```
POST /reports
```

with

* report file
* profile_id
* report metadata (if any)

The backend controller should:

* validate request
* verify ownership of profile
* verify uploaded file
* determine which profile the report belongs to

The report may belong to:

* Self
* Father
* Mother
* Child
* Spouse
* Any linked profile

The selected profile association must be saved because every report belongs to exactly one profile.

---

# 3. Upload to Azure Storage

The controller uploads the original report to Azure Blob Storage.

Azure returns something similar to:

```
https://storage.azure.com/...
```

That URL is stored in the database.

No OCR or AI processing happens inside the controller.

---

# 4. Create Database Record

Create a report record with something similar to:

```
status = uploaded

azure_file_url = ...

profile_id = ...

uploaded_by = ...

processing_stage = upload_complete
```

This gives us a permanent record immediately after upload.

---

# 5. Immediate Response to Frontend

The frontend should **not wait** for OCR.

Immediately return

```
{
    success: true,
    report_id: 15,
    status: "uploaded"
}
```

Also broadcast

```
ReportUploaded
```

Frontend receives

```
Uploading Complete
```

and immediately moves to the processing/buffer screen.

---

# 6. Dispatch Queue Job

After storing the report,

dispatch a Laravel Queue Job.

Example:

```
ProcessMedicalReportJob
```

Only pass lightweight information such as

```
report_id

azure_file_url

profile_id

user_id
```

The queue is responsible for starting the AI pipeline.

---

# 7. Queue Starts OCR

When the queue starts processing:

broadcast

```
OCR Started
```

Frontend updates UI

```
Extracting text...
```

When OCR finishes

broadcast

```
OCR Completed
```

Frontend changes UI to

```
OCR Complete
```

---

# 8. AI Summary Generation

After OCR,

the ML service generates

* extracted text
* structured entities
* health metrics
* report summary
* recommendations
* abnormalities

While AI is processing,

broadcast

```
AI Processing
```

Frontend shows

```
Generating AI Summary...
```

---

# 9. ML Sends Webhook

The ML service never directly updates the frontend.

Instead,

once AI finishes,

ML sends a webhook to Laravel.

Example

```
POST

/api/webhooks/report-processing-complete
```

Webhook contains the complete processed result.

Example:

```
{
    report_id,

    summary,

    report_type,

    extracted_text,

    medical_entities,

    health_metrics,

    abnormal_values,

    recommendations,

    confidence_score,

    ...
}
```

Everything should arrive inside one JSON payload.

---

# 10. Store Everything

The webhook controller validates the payload and stores:

* AI summary
* OCR text
* medical entities
* health metrics
* report classification
* confidence
* report status

Update report

```
status = completed
```

---

# 11. Broadcast Final Event

Laravel broadcasts

```
ReportProcessingCompleted
```

to

```
reports.{user_id}
```

Frontend receives

```
{
    status: "completed",

    summary: ...

    report_type: ...

    metrics: ...

    recommendations: ...

}
```

Frontend immediately navigates to the report details screen without polling.

---

# Real-Time Status Flow

The frontend should receive events in this order:

```
Uploading...

↓

Upload Complete

↓

OCR Started

↓

OCR Completed

↓

Generating AI Summary

↓

AI Summary Complete

↓

Processing Finished
```

Each stage is received through WebSockets.

---

# Profile Association

A report is always uploaded for a specific profile.

Before processing:

* validate profile ownership
* ensure the profile belongs to the authenticated user
* save the selected `profile_id`
* every extracted metric and AI result must remain linked to that profile

This enables future features like:

* family report management
* profile-wise health history
* profile-wise AI insights
* separate trends for each family member

---

# Responsibilities

### Flutter

* Upload report
* Listen to WebSocket events
* Show processing progress
* Display final report

### Laravel Controller

* Validate request
* Validate profile ownership
* Upload file to Azure Storage
* Save report record
* Broadcast upload success
* Dispatch queue job

### Laravel Queue

* Start OCR/AI pipeline
* Broadcast processing stage updates
* Coordinate with Azure Functions/ML service

### Azure Storage

* Store original medical report securely
* Return permanent file URL

### ML Service

* OCR
* Document classification
* Medical entity extraction
* Health metric extraction
* AI summary generation
* Send final results back via webhook

### Laravel Webhook

* Validate ML response
* Store extracted data
* Update report status
* Broadcast final completion event

---

# Design Goals

* Fully asynchronous architecture
* No long-running HTTP requests
* Real-time progress updates using WebSockets
* Queue-driven processing for scalability
* Secure profile-based report association
* Single webhook response containing the complete processed JSON
* Automatic frontend updates without polling
* Easy to extend with additional processing stages in the future


111

For your architecture, I'd recommend **Laravel Reverb**. Since you're already using Laravel 12, it's the most natural choice.

### Recommended Stack

* **Backend:** Laravel Reverb (WebSocket server)
* **Broadcasting:** Laravel Broadcasting
* **Queue:** Laravel Queues
* **Frontend (Flutter):** `pusher_channels_flutter` (Reverb speaks the Pusher protocol)
* **Authentication:** Private channels with Laravel Sanctum

---

## Why Reverb?

It is:

* ✅ Official Laravel WebSocket server
* ✅ Self-hosted (no monthly Pusher cost)
* ✅ Supports private and presence channels
* ✅ Works seamlessly with Laravel events
* ✅ Compatible with Flutter using the Pusher protocol
* ✅ Ideal for your real-time report processing

---

## Your Architecture

```text
                Flutter App
                     │
      Connect to Laravel Reverb
                     │
         Subscribe to private channel
                     │
           reports.{user_id}
                     │
────────────────────────────────────────────

             Laravel Backend
                     │
     Broadcast ReportUploaded Event
                     │
                Laravel Reverb
                     │
             Push event to Flutter

────────────────────────────────────────────

Report Upload
      │
      ▼
Controller
      │
Upload file to Azure
      │
Save Report
      │
Broadcast Upload Completed
      │
Dispatch Queue Job
      │
Queue starts OCR
      │
Broadcast OCR Started
      │
Azure Functions / ML
      │
Webhook → Laravel
      │
Store Summary
      │
Broadcast Processing Completed
      │
Flutter updates UI
```

---

## Example Channel

Each authenticated user listens only to their own channel.

```php
PrivateChannel("reports.{$user->id}")
```

or

```php
PrivateChannel("user.{$user->id}")
```

Example events:

* `ReportUploaded`
* `OCRStarted`
* `OCRCompleted`
* `AISummaryStarted`
* `ReportProcessingCompleted`
* `ReportProcessingFailed`

---

## Event Flow

```text
Flutter Uploads Report
        │
        ▼
Laravel Controller
        │
        ├── Upload to Azure
        ├── Save DB
        ├── Broadcast UploadCompleted
        └── Dispatch Queue
                │
                ▼
        Queue Processing
                │
        Broadcast OCR Started
                │
        Broadcast OCR Completed
                │
        Broadcast AI Started
                │
Azure Function / ML
                │
Webhook → Laravel
                │
Store Results
                │
Broadcast Processing Completed
                │
Flutter Receives Final JSON
```

---

## What Flutter Needs

The Flutter app should:

1. Authenticate with your Laravel backend.
2. Connect to the Reverb server.
3. Subscribe to `private-reports.{user_id}`.
4. Listen for events.
5. Update the progress screen based on the event status.

Example statuses:

```text
Uploading...

↓

Uploaded Successfully

↓

OCR Started

↓

OCR Completed

↓

Generating AI Summary

↓

Completed
```

No polling is required.

---

## Scalability

This setup scales well because:

* Reverb handles only real-time event delivery.
* Laravel Queues handle long-running tasks.
* Azure Blob Storage stores the files.
* Azure Functions/ML performs OCR and AI processing.
* Webhooks deliver the final processed data back to Laravel.
* Flutter receives updates instantly over WebSockets.

For your hackathon project and beyond, **Laravel Reverb + Laravel Queues + Azure Functions + Webhooks** is a clean, modern, and scalable architecture.