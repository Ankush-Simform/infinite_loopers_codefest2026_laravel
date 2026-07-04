# Services

Services contain business logic and orchestration.

Use services to coordinate models, repositories, policies, storage, queues, notifications, and integrations. Controllers should never call Flask directly; use `App\Integrations\Flask\FlaskApiService`.
