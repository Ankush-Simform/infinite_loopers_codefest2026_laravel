---
name: swagger-generation
description: Generate or update Swagger/OpenAPI documentation for AMRV API endpoints. Zero-hallucination policy — always read route, controller, FormRequest, DTO, Resource, and Enum files before writing any annotation. Load this skill before creating or updating any file in app/Docs/Swagger/.
---

# Swagger Generation — AMRV

All API endpoints must have complete OpenAPI/Swagger documentation using PHP Attributes (via `darkaonline/l5-swagger`). Documentation files live in `app/Docs/Swagger/{Feature}Documentation.php`.

---

## CRITICAL: Zero Hallucination Policy

**NEVER make up fields, request bodies, or response structures. ALWAYS read actual code.**

1. **NO GUESSING** — Do not assume field names, types, or structures
2. **READ CODE ONLY** — Every documented field must come from actual source code
3. **VERIFY EVERYTHING** — Request validation rules, DTO properties, Resource `toArray()` methods
4. **ENUM VALUES** — Read actual Enum class for string values, not case names
5. **NEVER TRUST EXISTING DOCS** — Verify against current code before updating

If you didn't read the code, you're guessing. If you're guessing, you're wrong.

---

## Step 0 — Check Git Changes First

Before creating or updating any Swagger documentation:

```bash
# Check recent API-related changes
git diff HEAD~5..HEAD -- routes/api/
git diff HEAD~5..HEAD -- app/Http/Controllers/Api/
git diff HEAD~5..HEAD -- app/Http/Requests/
git diff HEAD~5..HEAD -- app/Http/Resources/
git diff HEAD~5..HEAD -- app/DTOs/

# Uncommitted changes
git status
git diff -- routes/api/ app/Http/Controllers/Api/ app/Http/Requests/ app/Http/Resources/
```

Update Swagger when ANY of these changed:

- New API endpoint added
- Endpoint URL/path changed
- HTTP method changed
- Request validation rules added/removed/modified
- Required fields changed to optional (or vice versa)
- Response structure modified in Resource class
- DTO properties added/removed/renamed
- New error responses added
- Authentication requirements changed

---

## Step-by-Step Verification Process

**For EVERY endpoint, follow this exact sequence before writing annotations:**

### Step 1: Read the Route File

```bash
grep -r "route-name" routes/api/
# or read the specific route file
```

Extract:

- HTTP method (GET, POST, PUT, DELETE)
- URL path and path parameters
- Controller class and method name
- Middleware (e.g., `auth:sanctum`)

### Step 2: Read the Controller Method

```php
// Example analysis:
public function store(StoreUserRequest $request): JsonResponse
{
    try {
        $user = $this->userRepository->create($request->toDTO());

        return UserResource::single($user, [
            'message' => trans('api.users.created'),
            'code' => Response::HTTP_CREATED,   // Note: 201
        ]);
    } catch (Throwable $e) {
        return UserResource::error($e->getMessage(), [], Response::HTTP_BAD_REQUEST);  // Note: 400
    }
}
```

Extract:

- Request class (FormRequest)
- Resource class used for response
- HTTP status codes for success and error
- Whether it calls Service or Repository (Pattern 1 vs 2)

### Step 3: Read the Form Request Validation Rules

```php
// Example:
public function rules(): array
{
    return [
        'first_name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', Rule::unique('users')],
        'role_id' => ['required', 'ulid', Rule::exists('roles', 'id')],
        'mfa_enabled' => ['nullable', 'boolean'],
    ];
}
```

**IMPORTANT — Check for trait-based rules:**

```php
public function rules(): array
{
    return array_merge(
        $this->addressRules(),  // ← Read the trait method too!
        [
            'subscriber_id' => ['required_with:transaction_id', 'nullable', 'ulid'],
        ]
    );
}
```

For each field extract:

- `required` → mark as required in `required=[...]` array
- `nullable` → `nullable=true`
- Type: `string`, `integer`, `boolean`, `array`
- Format: `email`, `ulid`, `date`, etc.
- Constraints: `max:255`, `min:1`, etc.
- `Rule::in()` / `Rule::enum()` → allowed values

### Step 4: Read the DTO (if `toDTO()` exists)

```php
readonly class CreateUserDTO
{
    public function __construct(
        public string $firstName,    // snake_case in request: first_name
        public string $lastName,
        public string $email,
        public ?bool $mfaEnabled = null,
    ) {}
}
```

Confirm field mapping: `first_name` (API) → `firstName` (DTO).

Add to RequestBody description: "Fields converted from snake_case to camelCase DTO."

### Step 5: Read the Resource `toArray()` Method

```php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'first_name' => $this->first_name,   // ← EXACT JSON key
        'email' => $this->email,
        'role' => $this->whenLoaded('role', fn() => RoleResource::compact($this->role)),
        'mfa_enabled' => $this->mfa_enabled,
        'created_at' => $this->created_at?->toISOString(),
    ];
}
```

Document:

- Every key in `toArray()` — these are the EXACT JSON field names
- `whenLoaded()` fields → mark as `nullable=true` (only present when eager-loaded)
- `$this->when()` conditionals → verify if test conditions trigger them
- `?->` operator → marks the field as nullable

### Step 6: Read Enum Classes for Exact String Values

```php
enum OrderType: string
{
    case NEW = 'new-number';        // ← Use 'new-number', NOT 'NEW'
    case PORTIN = 'port-in';
    case ADD_NEW = 'add-new-number';
    case ADD_PORTIN = 'add-portin-number';
}
```

In Swagger use the string VALUES, not case NAMES:

```php
// CORRECT:
enum: ["new-number", "port-in", "add-new-number", "add-portin-number"]
// WRONG:
enum: ["NEW", "PORTIN", "new", "port_in"]
```

### Step 7: Verify Error Responses from Controller

Check all `catch` blocks and note HTTP status codes used for different exception types.

---

## Core Documentation Principles

### 1. Standard Response Format

All responses use the envelope: `status` (boolean), `code` (integer), `message` (string), `data` (object/array).

### 2. Use Inline Response Structures

Define response structures directly in `@OA\Response` — never use `$ref` schema references or `allOf` patterns.

### 3. Security Declaration

All protected endpoints must declare:

```php
security={{"sanctum":{}}}
```

### 4. HTTP Status Codes to Document

For every endpoint:

- `200` — GET, PUT, DELETE success
- `201` — POST success (resource created)
- `400` — Bad request / business rule failure
- `401` — Unauthorized (missing token)
- `403` — Forbidden (insufficient permissions)
- `404` — Resource not found
- `422` — Unprocessable entity (validation failed)
- `500` — Server error

---

## Complete Example

**After reading:** `routes/api/v1/users.php`, `UserController::store()`, `StoreUserRequest::rules()`, `CreateUserDTO`, `UserResource::toArray()`

```php
/**
 * @OA\Post(
 *     path="/api/v1/users",
 *     tags={"User Management"},
 *     summary="Create a new user",
 *     security={{"sanctum":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         description="User creation data. Fields converted from snake_case to camelCase DTO (CreateUserDTO).",
 *         @OA\JsonContent(
 *             required={"first_name", "last_name", "email", "role_id"},
 *             @OA\Property(property="first_name", type="string", maxLength=255, example="John"),
 *             @OA\Property(property="last_name", type="string", maxLength=255, example="Doe"),
 *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
 *             @OA\Property(property="role_id", type="string", format="ulid", example="01ARZ3NDEKTSV4RRFFQ69G5FAV"),
 *             @OA\Property(property="mfa_enabled", type="boolean", nullable=true, example=true),
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="User created successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="code", type="integer", example=201),
 *             @OA\Property(property="message", type="string", example="User created successfully."),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="id", type="string", format="ulid"),
 *                 @OA\Property(property="first_name", type="string"),
 *                 @OA\Property(property="last_name", type="string"),
 *                 @OA\Property(property="email", type="string", format="email"),
 *                 @OA\Property(property="mfa_enabled", type="boolean"),
 *                 @OA\Property(property="created_at", type="string", format="date-time"),
 *                 @OA\Property(property="updated_at", type="string", format="date-time"),
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Bad request",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="boolean", example=false),
 *             @OA\Property(property="code", type="integer", example=400),
 *             @OA\Property(property="message", type="string"),
 *             @OA\Property(property="data", type="object", @OA\Property(property="error", type="array", @OA\Items())),
 *         )
 *     ),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=422, description="Validation error"),
 * )
 */
```

---

## Common Pitfalls to Avoid

### Pitfall 1: Fabricating Request Fields

- **Wrong:** Guess field names without reading `FormRequest::rules()`
- **Correct:** Read every field from `rules()` method, including trait methods (`array_merge`)

### Pitfall 2: Wrong Enum Values

- **Wrong:** `enum: ["NEW", "PORTIN"]` (case names)
- **Correct:** Read enum class, use `case NAME = 'value'` → use `'value'`

### Pitfall 3: Missing Optional/Nullable Fields

- **Wrong:** Only documenting `required` fields
- **Correct:** Include all fields; nullable ones get `nullable=true`

### Pitfall 4: Hallucinated Response Structure

- **Wrong:** Document `user_id`, `username`, `full_name` from memory
- **Correct:** Copy exact keys from `toArray()` method

### Pitfall 5: Marking Nullable as Required

- **Wrong:** Include `nullable` fields in the `required` array
- **Correct:** Only fields with `'required'` rule go in `required=[...]`

---

## File Structure

```
app/Docs/Swagger/
├── UserDocumentation.php
├── RoleDocumentation.php
├── DidDocumentation.php
├── SubscriberDocumentation.php
└── ... (one file per feature domain)
```

---

## Verification Checklist Before Writing ANY Annotation

- [ ] Route file read: Confirmed HTTP method, path, controller method
- [ ] Controller read: Confirmed request class, resource class, status codes
- [ ] FormRequest `rules()` read: Extracted ALL validation rules (including trait methods via `array_merge`)
- [ ] DTO constructor read: Verified property names and types (if `toDTO()` exists)
- [ ] Resource `toArray()` read: Extracted complete response structure
- [ ] Enum class read: Got exact string values (if enum validation used)
- [ ] Controller try-catch read: Identified all error response status codes
- [ ] Related traits read: Any trait methods used in validation rules

**If ANY checkbox is unchecked, DO NOT write documentation yet.**

---

## Validation Phase

After writing documentation:

```bash
# Fix code style
./vendor/bin/pint app/Docs/Swagger/

# Generate Swagger UI
php artisan l5-swagger:generate

# Fix any generation errors before committing
```

Verify:

- Documentation renders correctly in Swagger UI
- Request body validation matches documented schema
- Response structure matches documented schema

---

## DO and DON'T Summary

**DO:**

- Read actual code files before documenting
- Check `git diff` for API changes
- Follow the verification workflow: routes → controller → request → DTO → resource → enums
- Define response structures inline (no `$ref` schema references)
- Use standard response format (`status`, `code`, `message`, `data`)
- Mark nullable fields appropriately
- Use exact enum string values
- Read trait methods when FormRequest uses `array_merge`

**DON'T:**

- Make up request fields — read validation rules first
- Make up response fields — read `Resource::toArray()` first
- Assume enum values — read the Enum class
- Skip trait methods in FormRequest
- Use schema references (`ref="#/components/schemas/..."`)
- Use `allOf` patterns for response composition
- Use enum case NAMES when documentation needs string VALUES
- Trust existing documentation without verifying against current code
