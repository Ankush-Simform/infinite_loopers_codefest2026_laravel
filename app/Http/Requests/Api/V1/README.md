# API Form Requests

Place Flutter-facing validation classes here.

Extend `App\Http\Requests\Api\V1\ApiFormRequest` so validation errors are returned as:

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {}
}
```
