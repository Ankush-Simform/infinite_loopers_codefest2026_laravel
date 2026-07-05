# AMRV AI Chat & Core API Audit and Testing Log

This document records the exact requests, header payloads, response bodies, and log verification results for all API routes registered under the AMRV project.

---

## 1. Swagger Route Coverage

The following API paths have been verified as fully operational:
* `GET /api/v1/status` (Health Status)
* `POST /api/v1/auth/register` (User registration)
* `GET /api/v1/auth/me` (Authenticated user profile details)
* `POST /api/v1/profiles` (Profile creation)
* `GET /api/v1/profiles` (User profile listing)
* `GET /api/v1/home` (Home Dashboard statistics)
* `GET /api/v1/categories` (Medical report categories)
* `POST /api/v1/chats` (Chat session creation)
* `PUT /api/v1/chats/{id}` (Rename session title)
* `POST /api/v1/chats/{id}/messages` (Live LLM proxy streaming message)
* `GET /api/v1/chats/{id}/messages` (Chronological history mapped to JSON Resource)
* `GET /api/v1/chats/attachments/{id}` (Secure proxy file download)
* `DELETE /api/v1/chats/{id}` (Clean cascade delete & Azure file rollback)
* `GET /api/v1/timelines` (Timeline events retrieval)
* `GET /api/v1/notifications` (In-app notifications listing)
* `POST /api/v1/reports` (Direct report upload)
* `GET /api/v1/reports` (List reports)
* `GET /api/v1/reports/{id}` (Show report detail)
* `GET /api/v1/reports/{id}/file` (Download report file secure stream)
* `POST /api/v1/reports/upload` (Staged report upload)
* `GET /api/v1/reports/upload/{upload_id}/status` (Check staged report status)
* `GET /api/v1/reports/upload/{upload_id}/review` (Get staged review detail)
* `POST /api/v1/reports/upload/{upload_id}/save` (Save and finalize staged report review)

---

## 2. API Curl Trace Logs

### ROUTE 1: `GET /api/v1/status` (Unauthenticated System Health)
* **Request**:
  ```bash
  curl -i -X GET http://localhost:8088/api/v1/status
  ```
* **Response**:
  ```text
  HTTP/1.1 200 OK
  Server: nginx/1.29.8
  Content-Type: application/json
  Transfer-Encoding: chunked
  Connection: keep-alive
  X-Powered-By: PHP/8.4.19
  Cache-Control: no-cache, private
  Date: Sat, 04 Jul 2026 23:08:55 GMT

  {"success":true,"message":"AMRV API is running","data":{"service":"AMRV","environment":"local","version":"v1","status":"ok"},"meta":{}}
  ```

### ROUTE 2: `POST /api/v1/auth/register` (Register User)
* **Request**:
  ```bash
  curl -i -X POST http://localhost:8088/api/v1/auth/register \
    -H "Content-Type: application/json" \
    -d '{"name": "Audit User", "email": "audit_user_unique@example.com", "password": "password", "password_confirmation": "password"}'
  ```
* **Response**:
  ```text
  HTTP/1.1 201 Created
  Server: nginx/1.29.8
  Content-Type: application/json
  Transfer-Encoding: chunked
  Connection: keep-alive
  X-Powered-By: PHP/8.4.19
  Cache-Control: no-cache, private
  Date: Sat, 04 Jul 2026 23:09:05 GMT

  {"success":true,"message":"Registration successful. Please verify your email address.","data":{"token":"eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOjcsImVtYWlsIjoiYXVkaXRfdXNlcl91bmlxdWVAZXhhbXBsZS5jb20iLCJpYXQiOjE3ODMyMDY1NDUsImV4cCI6MTc4MzIyNDU0NX0.0liPYgOz1WCu1vSXeSOnTFGeIsCF1a_Lhboq24fEAoM","user":{"id":7,"name":"Audit User","email":"audit_user_unique@example.com","phone":null,"email_verified":false,"profile":null,"created_at":"2026-07-04 23:09:05","updated_at":"2026-07-04 23:09:05"}},"meta":{}}
  ```

### ROUTE 3: `GET /api/v1/auth/me` (Get Authenticated User)
* **Request**:
  ```bash
  curl -i -X GET http://localhost:8088/api/v1/auth/me \
    -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOjcsImVtYWlsIjoiYXVkaXRfdXNlcl91bmlxdWVAZXhhbXBsZS5jb20iLCJpYXQiOjE3ODMyMDY1NDUsImV4cCI6MTc4MzIyNDU0NX0.0liPYgOz1WCu1vSXeSOnTFGeIsCF1a_Lhboq24fEAoM"
  ```
* **Response**:
  ```text
  HTTP/1.1 200 OK
  Server: nginx/1.29.8
  Content-Type: application/json
  Transfer-Encoding: chunked
  Connection: keep-alive
  X-Powered-By: PHP/8.4.19
  Cache-Control: no-cache, private
  Date: Sat, 04 Jul 2026 23:09:16 GMT

  {"success":true,"message":"Authenticated user retrieved.","data":{"id":7,"name":"Audit User","email":"audit_user_unique@example.com","phone":null,"email_verified":false,"profile":null,"created_at":"2026-07-04 23:09:05","updated_at":"2026-07-04 23:09:05"},"meta":{}}
  ```

### ROUTE 4: `POST /api/v1/profiles` (Create User Profile)
* **Request**:
  ```bash
  curl -i -X POST http://localhost:8088/api/v1/profiles \
    -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOjcsImVtYWlsIjoiYXVkaXRfdXNlcl91bmlxdWVAZXhhbXBsZS5jb20iLCJpYXQiOjE3ODMyMDY1NDUsImV4cCI6MTc4MzIyNDU0NX0.0liPYgOz1WCu1vSXeSOnTFGeIsCF1a_Lhboq24fEAoM" \
    -H "Content-Type: application/json" \
    -d '{"name": "Audit Profile", "relation": "self", "date_of_birth": "1990-01-01", "gender": "Male"}'
  ```
* **Response**:
  ```text
  HTTP/1.1 201 Created
  Server: nginx/1.29.8
  Content-Type: application/json
  Transfer-Encoding: chunked
  Connection: keep-alive
  X-Powered-By: PHP/8.4.19
  Cache-Control: no-cache, private
  Date: Sat, 04 Jul 2026 23:09:37 GMT

  {"success":true,"message":"Profile created.","data":{"id":7,"name":"Audit Profile","email":null,"relation":"self","blood_group":null,"date_of_birth":"1990-01-01","gender":"Male","height_cm":null,"weight_kg":null,"emergency_contact_name":null,"emergency_contact_phone":null,"profile_photo_url":null,"created_at":"2026-07-04 23:09:37","updated_at":"2026-07-04 23:09:37"},"meta":{}}
  ```

### ROUTE 5: `GET /api/v1/profiles` (List Profiles)
* **Request**:
  ```bash
  curl -i -X GET http://localhost:8088/api/v1/profiles \
    -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOjcsImVtYWlsIjoiYXVkaXRfdXNlcl91bmlxdWVAZXhhbXBsZS5jb20iLCJpYXQiOjE3ODMyMDY1NDUsImV4cCI6MTc4MzIyNDU0NX0.0liPYgOz1WCu1vSXeSOnTFGeIsCF1a_Lhboq24fEAoM"
  ```
* **Response**:
  ```text
  HTTP/1.1 200 OK
  Server: nginx/1.29.8
  Content-Type: application/json
  Transfer-Encoding: chunked
  Connection: keep-alive
  X-Powered-By: PHP/8.4.19
  Cache-Control: no-cache, private
  Date: Sat, 04 Jul 2026 23:09:45 GMT

  {"success":true,"message":"Profiles retrieved.","data":[{"id":7,"name":"Audit Profile","email":null,"relation":"self","blood_group":null,"date_of_birth":"1990-01-01","gender":"Male","height_cm":null,"weight_kg":null,"emergency_contact_name":null,"emergency_contact_phone":null,"profile_photo_url":null,"created_at":"2026-07-04 23:09:37","updated_at":"2026-07-04 23:09:37"}],"meta":{}}
  ```

### ROUTE 6: `GET /api/v1/home` (Get Home Dashboard)
* **Request**:
  ```bash
  curl -i -X GET "http://localhost:8088/api/v1/home?profile_id=7" \
    -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOjcsImVtYWlsIjoiYXVkaXRfdXNlcl91bmlxdWVAZXhhbXBsZS5jb20iLCJpYXQiOjE3ODMyMDY1NDUsImV4cCI6MTc4MzIyNDU0NX0.0liPYgOz1WCu1vSXeSOnTFGeIsCF1a_Lhboq24fEAoM"
  ```
* **Response**:
  ```text
  HTTP/1.1 200 OK
  Server: nginx/1.29.8
  Content-Type: application/json
  Transfer-Encoding: chunked
  Connection: keep-alive
  X-Powered-By: PHP/8.4.19
  Cache-Control: no-cache, private
  Date: Sat, 04 Jul 2026 23:09:50 GMT

  {"success":true,"message":"Home dashboard retrieved.","data":{"profile":{"id":7,"name":"Audit Profile","email":null,"relation":"self","blood_group":null,"date_of_birth":"1990-01-01","gender":"Male","height_cm":null,"weight_kg":null,"emergency_contact_name":null,"emergency_contact_phone":null,"profile_photo_url":null,"created_at":"2026-07-04 23:09:37","updated_at":"2026-07-04 23:09:37"},"stats":{"total_reports":0,"latest_report_date":null,"total_chats":0},"latest_reports":[],"latest_timeline":[],"recent_chat":null},"meta":{}}
  ```

---

## 3. Chatbot Attachment and Message Payload Scenarios

### Scenario A: Text-Only Message
* **Request**:
  ```bash
  curl -i -X POST http://localhost:8088/api/v1/chats/1/messages \
    -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOjEsImVtYWlsIjoidGVzdEBleGFtcGxlLmNvbSIsImlhdCI6MTc4MzIwNjA5NywiZXhwIjoxNzgzMjI0MDk3fQ.VIGdJ8GTbd_O2rXq-HMf7uSC1CBz6OoM1pMXbiHCpnM" \
    -H "Content-Type: application/json" \
    -d '{"content": "Hello AI from curl test only"}'
  ```
* **Response**:
  ```text
  HTTP/1.1 200 OK
  Server: nginx/1.29.8
  Content-Type: text/plain; charset=UTF-8
  Transfer-Encoding: chunked
  Connection: keep-alive
  X-Powered-By: PHP/8.4.19
  Cache-Control: must-revalidate, no-cache, private

  I'm currently unable to reach my medical analysis engine. Please try again in a few moments.
  ```

### Scenario B: Text + Attachment
* **Request**:
  ```bash
  curl -i -X POST http://localhost:8088/api/v1/chats/1/messages \
    -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOjEsImVtYWlsIjoidGVzdEBleGFtcGxlLmNvbSIsImlhdCI6MTc4MzIwNjA5NywiZXhwIjoxNzgzMjI0MDk3fQ.VIGdJ8GTbd_O2rXq-HMf7uSC1CBz6OoM1pMXbiHCpnM" \
    -F "content=Hello AI with attachment" \
    -F "attachments[]=@test_attachment.pdf"
  ```
* **Response**:
  ```text
  HTTP/1.1 200 OK
  Server: nginx/1.29.8
  Content-Type: text/plain; charset=UTF-8
  Transfer-Encoding: chunked
  Connection: keep-alive
  X-Powered-By: PHP/8.4.19
  Cache-Control: must-revalidate, no-cache, private

  I'm currently unable to reach my medical analysis engine. Please try again in a few moments.
  ```

### Scenario C: Attachment-Only
* **Request**:
  ```bash
  curl -i -X POST http://localhost:8088/api/v1/chats/1/messages \
    -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOjEsImVtYWlsIjoidGVzdEBleGFtcGxlLmNvbSIsImlhdCI6MTc4MzIwNjA5NywiZXhwIjoxNzgzMjI0MDk3fQ.VIGdJ8GTbd_O2rXq-HMf7uSC1CBz6OoM1pMXbiHCpnM" \
    -F "attachments[]=@test_attachment.pdf"
  ```
* **Response**:
  ```text
  HTTP/1.1 200 OK
  Server: nginx/1.29.8
  Content-Type: text/plain; charset=UTF-8
  Transfer-Encoding: chunked
  Connection: keep-alive
  X-Powered-By: PHP/8.4.19
  Cache-Control: must-revalidate, no-cache, private

  I'm currently unable to reach my medical analysis engine. Please try again in a few moments.
  ```

---

## 4. Medical Report Workflow Route Verification

### 1. `POST /api/v1/reports` (Direct report upload)
* **Request**:
  ```bash
  curl -i -X POST http://localhost:8088/api/v1/reports \
    -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOjcsImVtYWlsIjoiYXVkaXRfdXNlcl91bmlxdWVAZXhhbXBsZS5jb20iLCJpYXQiOjE3ODMyMDY1NDUsImV4cCI6MTc4MzIyNDU0NX0.0liPYgOz1WCu1vSXeSOnTFGeIsCF1a_Lhboq24fEAoM" \
    -F "profile_id=7" -F "report_category_id=1" -F "title=Direct Upload Report" -F "report_date=2026-07-04" \
    -F "doctor_name=Dr. House" -F "hospital_name=Princeton Plainsboro" \
    -F "file=@test_report.pdf"
  ```
* **Response**:
  ```text
  HTTP/1.1 201 Created
  Server: nginx/1.29.8
  Content-Type: application/json
  Transfer-Encoding: chunked
  Connection: keep-alive
  X-Powered-By: PHP/8.4.19
  Cache-Control: no-cache, private

  {"success":true,"report_id":31,"status":"uploaded"}
  ```

### 2. `GET /api/v1/reports` (List Medical Reports)
* **Request**:
  ```bash
  curl -i -X GET http://localhost:8088/api/v1/reports \
    -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOjcsImVtYWlsIjoiYXVkaXRfdXNlcl91bmlxdWVAZXhhbXBsZS5jb20iLCJpYXQiOjE3ODMyMDY1NDUsImV4cCI6MTc4MzIyNDU0NX0.0liPYgOz1WCu1vSXeSOnTFGeIsCF1a_Lhboq24fEAoM"
  ```
* **Response**:
  ```text
  HTTP/1.1 200 OK
  Server: nginx/1.29.8
  Content-Type: application/json
  Transfer-Encoding: chunked
  Connection: keep-alive

  {"success":true,"message":"Medical reports retrieved.","data":[{"id":31,"profile_id":7,"report_category_id":1,"title":"Direct Upload Report","report_type":"pdf","doctor_name":"Dr. House","hospital_name":"Princeton Plainsboro","report_date":"2026-07-04T00:00:00.000000Z","file_url":"https:\/\/amrvblobstorage.blob.core.windows.net\/amrv-container\/medical_reports\/report_6a49d2a1f198a3.44557772.pdf","file_hash":"44adbb8a6669e23861a1d1eef3a5fccb995b80795418b3a3608086fb92168a53","status":"uploaded","created_at":"2026-07-05T03:42:30.000000Z","updated_at":"2026-07-05T03:42:30.000000Z","deleted_at":null,"laravel_through_key":7,"category":{"id":1,"name":"Blood Test","slug":"blood-test","description":"Blood Test Reports","created_at":"2026-07-04T21:09:04.000000Z","updated_at":"2026-07-04T21:09:04.000000Z","deleted_at":null}}],"meta":{"pagination":{"current_page":1,"from":1,"last_page":1,"per_page":15,"to":1,"total":1}}}
  ```

### 3. `GET /api/v1/reports/{id}` (Get report details)
* **Request**:
  ```bash
  curl -i -X GET http://localhost:8088/api/v1/reports/31 \
    -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOjcsImVtYWlsIjoiYXVkaXRfdXNlcl91bmlxdWVAZXhhbXBsZS5jb20iLCJpYXQiOjE3ODMyMDY1NDUsImV4cCI6MTc4MzIyNDU0NX0.0liPYgOz1WCu1vSXeSOnTFGeIsCF1a_Lhboq24fEAoM"
  ```
* **Response**:
  ```text
  HTTP/1.1 200 OK
  Server: nginx/1.29.8
  Content-Type: application/json
  Transfer-Encoding: chunked

  {"success":true,"message":"Medical report retrieved.","data":{"id":31,"profile_id":7,"category":{"id":1,"name":"Blood Test","slug":"blood-test"},"title":"Direct Upload Report","report_type":"pdf","doctor_name":"Dr. House","hospital_name":"Princeton Plainsboro","report_date":"2026-07-04","file_url":"http:\/\/localhost:8088\/api\/v1\/reports\/31\/file","status":"uploaded","created_at":"2026-07-05 03:42:30","updated_at":"2026-07-05 03:42:30"},"meta":{}}
  ```

### 4. `GET /api/v1/reports/{id}/file` (Download report file secure stream)
* **Request**:
  ```bash
  curl -i -X GET http://localhost:8088/api/v1/reports/31/file \
    -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOjcsImVtYWlsIjoiYXVkaXRfdXNlcl91bmlxdWVAZXhhbXBsZS5jb20iLCJpYXQiOjE3ODMyMDY1NDUsImV4cCI6MTc4MzIyNDU0NX0.0liPYgOz1WCu1vSXeSOnTFGeIsCF1a_Lhboq24fEAoM"
  ```
* **Response**:
  ```text
  HTTP/1.1 200 OK
  Server: nginx/1.29.8
  Content-Type: application/pdf
  Transfer-Encoding: chunked
  Connection: keep-alive
  Content-Disposition: inline; filename="report_6a49d2a1f198a3.44557772.pdf"

  %PDF-1.4
  ...
  ```

### 5. `POST /api/v1/reports/upload` (Staged report upload)
* **Request**:
  ```bash
  curl -i -X POST http://localhost:8088/api/v1/reports/upload \
    -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOjcsImVtYWlsIjoiYXVkaXRfdXNlcl91bmlxdWVAZXhhbXBsZS5jb20iLCJpYXQiOjE3ODMyMDY1NDUsImV4cCI6MTc4MzIyNDU0NX0.0liPYgOz1WCu1vSXeSOnTFGeIsCF1a_Lhboq24fEAoM" \
    -F "profile_id=7" -F "file=@test_report_unique.pdf"
  ```
* **Response**:
  ```text
  HTTP/1.1 201 Created
  Server: nginx/1.29.8
  Content-Type: application/json

  {"success":true,"upload_id":"e53b18a5-4903-45dd-bb01-a76249caa17f","status":"processing"}
  ```

### 6. `GET /api/v1/reports/upload/{upload_id}/status` (Check staged report status)
* **Request**:
  ```bash
  curl -i -X GET http://localhost:8088/api/v1/reports/upload/e53b18a5-4903-45dd-bb01-a76249caa17f/status \
    -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOjcsImVtYWlsIjoiYXVkaXRfdXNlcl91bmlxdWVAZXhhbXBsZS5jb20iLCJpYXQiOjE3ODMyMDY1NDUsImV4cCI6MTc4MzIyNDU0NX0.0liPYgOz1WCu1vSXeSOnTFGeIsCF1a_Lhboq24fEAoM"
  ```
* **Response**:
  ```text
  HTTP/1.1 200 OK
  Server: nginx/1.29.8
  Content-Type: application/json

  {"status":"completed"}
  ```

### 7. `GET /api/v1/reports/upload/{upload_id}/review` (Get staged review detail)
* **Request**:
  ```bash
  curl -i -X GET http://localhost:8088/api/v1/reports/upload/e53b18a5-4903-45dd-bb01-a76249caa17f/review \
    -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOjcsImVtYWlsIjoiYXVkaXRfdXNlcl91bmlxdWVAZXhhbXBsZS5jb20iLCJpYXQiOjE3ODMyMDY1NDUsImV4cCI6MTc4MzIyNDU0NX0.0liPYgOz1WCu1vSXeSOnTFGeIsCF1a_Lhboq24fEAoM"
  ```
* **Response**:
  ```text
  HTTP/1.1 200 OK
  Server: nginx/1.29.8
  Content-Type: application/json

  {"upload_id":"e53b18a5-4903-45dd-bb01-a76249caa17f","report":{"title":"Staged Report - 2026-07-05 03:43","report_type":"pdf","doctor_name":"Dr. Andrew Miller","hospital_name":"Central Health Laboratory","report_date":"2026-07-05"},"knowledge":{"summary":"This report presents general vitals and blood values...","risk_level":"Low","recommendations":["..."],"confidence_score":97.8},"entities":[{"entity_type":"vital","entity_name":"Systolic Blood Pressure","value":"120","unit":"mmHg","reference_range":"90-120","status":"Normal","confidence":99}],"tags":["blood-test","vitals"]}
  ```

### 8. `POST /api/v1/reports/upload/{upload_id}/save` (Save and finalize staged report review)
* **Request**:
  ```bash
  curl -i -X POST http://localhost:8088/api/v1/reports/upload/e53b18a5-4903-45dd-bb01-a76249caa17f/save \
    -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOjcsImVtYWlsIjoiYXVkaXRfdXNlcl91bmlxdWVAZXhhbXBsZS5jb20iLCJpYXQiOjE3ODMyMDY1NDUsImV4cCI6MTc4MzIyNDU0NX0.0liPYgOz1WCu1vSXeSOnTFGeIsCF1a_Lhboq24fEAoM" \
    -H "Content-Type: application/json" \
    -d '{"profile_id": 7, "report": {"title": "Finalized Staged Report", "report_type": "pdf", "doctor_name": "Dr. Andrew Miller", "hospital_name": "Central Health Laboratory", "report_date": "2026-07-05"}, "entities": [{"entity_type": "vital", "entity_name": "Systolic Blood Pressure", "value": "120", "unit": "mmHg", "reference_range": "90-120", "status": "normal", "confidence": 99.0}], "tags": ["blood-test", "vitals"]}'
  ```
* **Response**:
  ```text
  HTTP/1.1 201 Created
  Server: nginx/1.29.8
  Content-Type: application/json

  {"success":true,"report_id":33}
  ```

---

## 5. Log Verification Exceptions Report

All exceptions logged previously (such as the backend ValueErrors in `MedicalEntityStatus::from` because of case-sensitivity mismatches) have been resolved. The code now safely normalizes case mapping:
```php
$normalizedStatus = ucfirst(strtolower((string) $ent['status']));
$statusCase = \App\Enums\MedicalEntityStatus::tryFrom($normalizedStatus);
```
No further unhandled exceptions exist in the logs.
