---
name: backend-standards
description: Backend architecture rules for AMRV — controllers, services, repositories, models, exceptions, enums, form requests, and the API response envelope. Load this skill before writing or reviewing any PHP/Laravel backend code.
---

# Backend Standards — AMRV

## Development Principles

1. Follow **pure Laravel 12 conventions** — Eloquent, FormRequests, Enums, Sanctum for auth
2. Follow **PSR-12** via `./vendor/bin/pint` (default Laravel preset — there is no custom `pint.json`)
3. `declare(strict_types=1);` at the top of every new/edited PHP file — including Models and Enums. Some existing files (all current Models, `ActivityType`, `ProfileRelation`) skip this; that is a gap to close, not a pattern to copy.
4. All API routes live in `routes/api.php`, prefixed `v1`, with dotted route names: `api.v1.{domain}.{action}` (e.g. `api.v1.profile.show`)
5. Primary keys are auto-increment `bigint` (`$table->id()`, `$table->foreignId()->constrained()`) — do not introduce UUID/ULID primary keys
6. **No DTO layer.** Pass `$request->validated()` / FormRequest properties directly into Models or Services. Do not create an `app/DTOs/` directory.
7. Store all credentials/secrets in `.env` — never hardcoded (Azure Blob keys, AI microservice URL/secret, etc.)
8. Return every response through `App\Support\ApiResponse` — never `response()->json()` directly

---

## 1. Controller → Service/Repository → Model Layering

AMRV does not force a Service or Repository for every action. Pick the lightest layer that fits.

### Simple CRUD, no external integration

Controller talks to the Eloquent model directly. This is the dominant pattern today (`AuthController`, `ProfileController`).

```php
final class ProfileController extends Controller
{
    public function store(ProfileStoreRequest $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->profile()->create($this->profileData($request));

        return ApiResponse::success(ProfileResource::make($profile), 'Profile created.', 201);
    }
}
```

### Business logic, orchestration, or an external integration (Azure Blob Storage, AI microservice, notifications)

Put the logic in a Service class under `app/Services/{Domain}/`, matching the pre-scaffolded folders: `Auth`, `Chat`, `Health`, `Notifications`, `Profile`, `Reports`. Controllers must never call an external HTTP client directly — wrap it in a Service (and, for AI calls, `App\Integrations\AIApiService` per `app/Services/README.md`, once it exists).

```php
final class ReportController extends Controller
{
    public function __construct(private readonly ReportAnalysisService $analysisService) {}

    public function store(ReportStoreRequest $request): JsonResponse
    {
        $report = $this->analysisService->uploadAndQueueAnalysis($request->user(), $request->validated());

        return ApiResponse::success(MedicalReportResource::make($report), 'Report uploaded.', 201);
    }
}
```

### Reusable or complex queries (filtering, searching, multi-condition sorting needed by more than one caller)

Only then introduce a Repository. Per `app/Repositories/README.md`, repositories are **optional**. Extend the existing generic `App\Repositories\EloquentRepository` rather than writing a bespoke interface per model unless a second concrete implementation genuinely exists.

```php
final class MedicalReportRepository extends EloquentRepository
{
    public function __construct(MedicalReport $model)
    {
        parent::__construct($model);
    }

    /**
     * @return Collection<int, MedicalReport>
     */
    public function forProfileFilteredByCategory(int $profileId, ?int $categoryId): Collection
    {
        return $this->model->newQuery()
            ->where('profile_id', $profileId)
            ->when($categoryId, fn ($q) => $q->where('report_category_id', $categoryId))
            ->latest('report_date')
            ->get();
    }
}
```

**Never** wire both a Service and a Repository into a controller for the same simple operation.

---

## 2. Controller Rules

- `final class` unless there's a reason to extend
- Single-action controllers use `__invoke()` (see `StatusController`)
- **No `try/catch` in controller actions.** Exceptions bubble to the global handler — `bootstrap/app.php` renders JSON for any `api/*` request via `shouldRenderJsonWhen()`. Throw an `ApiException` subclass instead of catching generically.
- Validate via a `FormRequest` extending `ApiFormRequest` — never validate inline
- Return every response through `ApiResponse` — never a bare Resource or `response()->json()`

**Correct:**

```php
public function login(LoginRequest $request): JsonResponse
{
    $user = User::where('email', $request->email)->first();

    if (! $user || ! Hash::check($request->password, $user->password)) {
        return ApiResponse::error('Invalid credentials.', Response::HTTP_UNAUTHORIZED);
    }

    return ApiResponse::success(['token' => $token, 'user' => UserResource::make($user)], 'Login successful.');
}
```

**Incorrect:**

```php
public function login(LoginRequest $request)
{
    try {
        // ...
        return response()->json(['status' => true, 'data' => $data]); // wrong envelope + bare response()
    } catch (\Exception $e) {                                          // wrong: generic exception, unneeded catch
        return response()->json(['status' => false, 'message' => $e->getMessage()], 400);
    }
}
```

---

## 3. Response Envelope — `App\Support\ApiResponse`

Every controller response must go through this class. Fields are **`success`, `message`, `data`, `errors`, `meta`** — not `status`/`code`.

| Method                                                      | Use for                                                                          |
| ----------------------------------------------------------- | -------------------------------------------------------------------------------- |
| `ApiResponse::success($data, $message, $status = 200)`      | Any 2xx response                                                                 |
| `ApiResponse::error($message, $status = 400, $errors = [])` | Business-rule failures, auth failures                                            |
| `ApiResponse::validationError($errors)`                     | Validation failures — used automatically by `ApiFormRequest::failedValidation()` |
| `ApiResponse::paginated($paginator, $message)`              | Paginated list responses — wraps `meta.pagination` automatically                 |

```json
{ "success": true,  "message": "Profile created.",     "data": { "...": "..." }, "meta": {} }
{ "success": false, "message": "Invalid credentials.", "errors": {},             "meta": {} }
```

Do not invent a different envelope shape or add ad hoc top-level keys — put extra context in `meta`.

---

## 4. Exceptions — Never Use Generic Exceptions

Throw a subclass of `App\Exceptions\ApiException`, never a bare `\Exception`/`\RuntimeException`/`\LogicException`. `ApiException` self-renders via `render(): JsonResponse` using `ApiResponse::error()`, so nothing needs to catch it.

| Class                      | When to use                                                                      |
| -------------------------- | -------------------------------------------------------------------------------- |
| `ApiException`             | Generic API-facing failure with a specific status code                           |
| `ExternalServiceException` | Azure Blob Storage or AI microservice call fails (defaults to `502 Bad Gateway`) |

If neither fits (e.g. a locked-report violation, an OTP failure), add a new class in `app/Exceptions/` extending `ApiException`:

```php
final class ReportLockedException extends ApiException
{
    public function __construct(string $message = 'This report is locked.', array $errors = [])
    {
        parent::__construct($message, Response::HTTP_FORBIDDEN, $errors);
    }
}
```

**Incorrect:**

```php
throw new \Exception('Something went wrong');   // WRONG
throw new \RuntimeException('Report not found'); // WRONG
```

---

## 5. Form Requests

Every request body is validated through a `FormRequest` extending `App\Http\Requests\Api\V1\ApiFormRequest`, which already wires `authorize(): true` and a JSON `failedValidation()` via `ApiResponse::validationError()`. Never validate manually inside a controller.

Location: `app/Http/Requests/Api/V1/{Domain}/{Action}Request.php`.

```php
final class ProfileStoreRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'relation' => ['required', Rule::in(array_map(fn (ProfileRelation $c) => $c->value, ProfileRelation::cases()))],
        ];
    }
}
```

---

## 6. Enums

Backed string enums live in `app/Enums/`, cast directly on the model via `casts()`:

```php
enum ReportStatus: string
{
    case UPLOADED = 'uploaded';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}

// Model
protected function casts(): array
{
    return ['status' => ReportStatus::class];
}
```

Validate enum fields in FormRequests with `Rule::in(array_map(fn ($c) => $c->value, Enum::cases()))` — never hardcode the allowed string list a second time.

---

## 7. Models

One file per model, directly in `app/Models/`. Do **not** split relations/scopes into a `Traits/{Model}/` subfolder — AMRV keeps everything inline with a banner comment:

```php
class MedicalReport extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [...];

    protected function casts(): array
    {
        return ['status' => ReportStatus::class];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
```

- Add `declare(strict_types=1);` and a full return-type hint on every relation method (`HasMany`, `BelongsTo`, etc.)
- Use `SoftDeletes` on any medical/report data — reports must never be hard-deleted
- Eager-load relations a Resource's `whenLoaded()` calls depend on; avoid N+1 once list endpoints exist

---

## 8. API Resources

Resources extend `JsonResource` and only implement `toArray()` — there are no `::single()`/`::collection()`/`::error()` static factories in this codebase. Response enveloping is `ApiResponse`'s job, not the Resource's.

```php
final class ProfileResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'profile_photo_url' => $this->when($this->profile_photo_path, fn () => Storage::disk('public')->url($this->profile_photo_path)),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
```

- Use `?->` for any possibly-null attribute (dates, relations)
- Use `whenLoaded()` for relations, `when()` for conditional fields — never assume a relation is loaded

---

## 9. Things NOT to Do

- Don't create `app/DTOs/` — pass validated arrays/FormRequest data directly
- Don't add a Service or Repository for a plain create/read/update/delete with no external call
- Don't use `response()->json()` — always `ApiResponse`
- Don't use `try/catch` in a controller action just to re-wrap an exception — throw an `ApiException` subclass and let it render itself
- Don't throw bare `\Exception`/`\RuntimeException`/`\LogicException`
- Don't introduce UUID/ULID primary keys — this project uses auto-increment `id()`
- Don't call Azure Blob Storage or the AI microservice directly from a controller — wrap it in a Service
- Don't duplicate an enum's allowed values as a hardcoded string list in validation rules
- Don't split Model relations/scopes into separate trait files — keep them inline

---

## 10. Code Quality

No PHPStan/Larastan or SonarQube gate is configured in this repo — the only enforced check is Pint. Run it after every change:

```bash
./vendor/bin/pint
```

Still apply the _spirit_ of static-analysis discipline even without the tooling:

- Explicit parameter and return types on every method
- No unused imports, variables, or unreachable code
- No magic numbers/strings — use Enums or named constants
- Never dereference a possibly-null value without `?->` or an explicit null check
- Never concatenate user input into SQL — Eloquent or parameter binding only
- PHPDoc on methods returning collections/paginators: `@return Collection<int, Model>`, `@return LengthAwarePaginator<int, Model>`

---

## Related Skill

For OpenAPI/Swagger documentation of endpoints, use the `swagger-generation` skill. Its illustrative examples currently reference patterns that don't exist in this codebase yet (a `DTOs` layer, `Resource::single()/::error()` statics, a `status`/`code` response envelope, and the `darkaonline/l5-swagger` package, which isn't installed) — treat **this** skill as the source of truth for actual AMRV backend structure until that one is updated.
