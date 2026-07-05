# API Controllers

Controllers in this namespace are thin HTTP adapters for Flutter-facing `/api/v1` endpoints.

Use controllers for request orchestration only:

- Accept a Form Request.
- Authorize through policies where needed.
- Call a service class.
- Return an API Resource through `App\Support\ApiResponse`.

Business logic belongs in `app/Services`.
