# Smart Trade Platform — SaaS License Orders

The SaaS license-order service for **Smart Trade Platform (STP)**, built with PHP and Laravel. It records tenant license purchases, tracks order status, and exchanges events with the platform’s identity and payment services.

STP supports trade between wholesalers and vendors. This module provides the ordering foundation for access to its SaaS services; the Angular web client, Identity and Access Management (IAM), and Payment modules are maintained separately.

> **Development status:** initial implementation. The repository contains order-management APIs and integration listeners. Automated license activation, renewal scheduling, entitlement enforcement, and a license-management dashboard are not implemented here.

## Capabilities

- Register tenant and merchant information received through provisioning integrations.
- Create orders for an existing license type, recording amounts, discounts, and an item snapshot.
- Mark orders as placed, completed after payment notification, or cancelled.
- Publish order events through RabbitMQ using Laravel jobs.
- Consume tenant provisioning, authentication, logout, and payment-completion messages through standalone PHP listeners.
- Store IAM access-token metadata and mark known tokens as revoked on logout notifications.

License-type definitions include `MONTHLY`, `BIYEARLY`, `YEARLY`, and `TRIAL`, with configurable durations and prices. The repository does not provide a license-type administration API or seed these definitions.

## Technology

| Component | Technology |
| --- | --- |
| Runtime | PHP `^8.2` |
| Framework | Laravel `^12.0` |
| OAuth integration | Laravel Passport `^13.0` |
| Messaging | RabbitMQ / `php-amqplib` |
| Cryptography dependency | Spatie Crypto |
| Persistence | Laravel Eloquent and migrations |
| Tests | PHPUnit `^11.5` |
| CI / Automation | GitHub Actions workflow |
| Containerization | Docker & Docker Compose |
| Optional web assets | Vite 7 and Tailwind CSS 4 |

Use `composer.lock` to install the recorded PHP dependencies. Frontend assets are Laravel scaffolding, not the STP Angular application.

## Platform integration

| Module | Relationship |
| --- | --- |
| [IAM](https://github.com/angedid/asso-iam) | Supplies tenant, merchant, authentication, and logout information. |
| [Payment](https://github.com/angedid/asso-payment) | Supplies payment-completion events used to mark orders completed. |
| [Angular client](https://github.com/angedid/angular-client) | Separate user-facing application that can integrate with the platform APIs. |

Order placement, completion, and cancellation dispatch messages to the exchanges configured by `LICENSE_ORDER_PLACED_EXCHANGE`, `LICENSE_ORDER_COMPLETED_EXCHANGE`, and `LICENSE_ORDER_CANCELLED_EXCHANGE`. The default exchange type is `fanout`.

Order status values are `PENDING`, `PLACED`, `COMPLETED`, and `CANCELLED`. A completed order cannot be cancelled through the current cancellation endpoint. Other transition restrictions and duplicate-event protection require further implementation; these values do not constitute a fully enforced state machine.

## Local development

### Prerequisites

- PHP 8.2 or later within the Composer constraints, with the extensions required by Laravel and the selected database driver.
- Composer 2 and a configured database supported by Laravel; validate migration compatibility with your chosen engine.
- RabbitMQ for messaging integration.
- Local IAM and Payment services, or controlled test substitutes, for integrated workflows.
- Node.js and npm compatible with Vite 7 if building the optional web assets.

### Install and configure

```bash
git clone https://github.com/angedid/asso-license-order.git
cd asso-license-order
composer install
cp .env.example .env
php artisan key:generate
```

Configure the application URL, database, queue backend, and exchange names in `.env`. Use an isolated development database, then run:

```bash
php artisan migrate
php artisan route:list --path=api
php artisan serve
```

Provision tenant and merchant records and create valid license-type records before submitting orders. The default seeder creates a sample Laravel user; it does not prepare the business data required for an order.

For Passport-protected integration endpoints, configure signing keys and an authorized machine client for this service. The supplied listeners request tokens using the `client_credentials` grant. A basic Laravel installation alone does not configure the full service-to-service authentication flow.

### Messaging configuration

Messaging currently uses two configuration mechanisms:

- The controller reads exchange names from Laravel environment variables.
- The publisher and standalone listeners read `global-var-config.ini` using a relative path, including broker settings, service URLs, client credentials, exchanges, and queues.

Review `app/Jobs/ProcessMessages.php` and `app/Infrastructure/Messaging/` before starting workers. Provide private development configuration and ensure the process working directory resolves the intended INI file. Setting `.env` alone is insufficient for these integrations.

If using an asynchronous Laravel queue backend, start its worker:

```bash
php artisan queue:work
```

The standalone RabbitMQ listeners must be launched separately under a process supervisor with the correct working directory. Laravel’s queue worker does not start them automatically.

### Optional web assets

```bash
npm install
npm run build
```

Use `npm run dev` for Vite development. No npm lockfile is committed in the reviewed revision; `npm ci` is therefore not an initial installation command.

## API overview

Routes below use the `/api` prefix.

| Method | Endpoint | Purpose | Configured middleware |
| --- | --- | --- | --- |
| GET | `/user` | Return the authenticated Laravel user | `auth:api` |
| POST | `/tenant-provisions` | Register a tenant and its merchant | `rabbitmq.client` |
| POST | `/access-tokens` | Store received IAM token metadata | `rabbitmq.client` |
| POST | `/logout` | Mark a stored token as revoked by `jti` | `rabbitmq.client` |
| POST | `/orders` | Create a pending license order | `iam.auth.api` |
| PUT | `/orders/{order_id}/placed-order` | Mark an order placed and dispatch an event | `iam.auth.api` |
| PUT | `/orders/{order_id}/paid-order` | Mark an order completed and dispatch an event | `rabbitmq.client` |
| PUT | `/orders/{order_id}/cancelled-order` | Cancel an order unless completed | `iam.auth.api` |

Order creation expects `tenant_id`, `merchant_id`, `license_type_id`, `amount`, and `discount`. Refer to `ApiController` for the current payload handling. Order-list and order-detail read endpoints are not provided.

Responses use the existing application envelope:

```json
{"success": 1, "faillure": 0, "response": "Order created successfully"}
```

Failures use `success: 0`, `faillure: 1`, and `raison`. The spelling is part of the current contract. Several failures return HTTP 200 or 202, so clients must inspect the envelope rather than infer success from HTTP status alone.

## Verification

The project includes an automated PHPUnit test suite covering the domain models, API endpoints, access control middleware, order status transitions, and integration lifecycle flows.

### Running the Tests

To run the full automated test suite, use either of the following documented commands:

```bash
composer test
```

or directly using Artisan:

```bash
php artisan test
```

To run a specific test suite or test class:

```bash
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
php artisan test --filter=OrderLifecycleIntegrationTest
```

To run code style checks with Laravel Pint:

```bash
./vendor/bin/pint --test tests/
```

### Test Suite Structure

| Suite | File | Focus |
| --- | --- | --- |
| **Unit** | `tests/Unit/LicenseTypeTest.php` | License type definitions (`MONTHLY`, `BIYEARLY`, `YEARLY`, `TRIAL`), pricing, and attributes |
| **Unit** | `tests/Unit/OrderTest.php` | Order constants, JSON items snapshotting, status transitions, and pricing calculation |
| **Unit** | `tests/Unit/TenantAndMerchantTest.php` | Tenant and Merchant model creation, attributes, and relationships |
| **Unit** | `tests/Unit/AccessTokenTest.php` | Access token storage, expiration timestamps, and revocation |
| **Unit** | `tests/Unit/GlobalResultHandlerTest.php` | Global result envelope structure (`success`, `faillure`, `response`/`raison`) |
| **Unit** | `tests/Unit/ControlAccessMiddlewareTest.php` | Token expiration and revocation validation logic |
| **Unit** | `tests/Unit/ProcessMessagesTest.php` | RabbitMQ queued publication job configuration |
| **Feature** | `tests/Feature/TenantProvisioningTest.php` | `POST /api/tenant-provisions` registration, transactional rollback on error, and auth protection |
| **Feature** | `tests/Feature/AccessTokenManagementTest.php` | `POST /api/access-tokens` JWT claim ingestion and `POST /api/logout` token revocation |
| **Feature** | `tests/Feature/ControlAccessMiddlewareFeatureTest.php` | `iam.auth.api` middleware Bearer token validation against active/revoked/expired records |
| **Feature** | `tests/Feature/OrderCreationTest.php` | `POST /api/orders` creation, final amount computation, and input validation |
| **Feature** | `tests/Feature/OrderStatusTransitionTest.php` | Order placement, payment completion, cancellation, and RabbitMQ message dispatching |
| **Feature** | `tests/Feature/OrderLifecycleIntegrationTest.php` | Multi-step end-to-end integration lifecycle from provisioning through payment completion |

## Continuous Integration & Docker

Continuous Integration is configured using **GitHub Actions** and containerized with **Docker** / **Docker Compose**.

### GitHub Actions Workflow

The CI workflow is configured in `.github/workflows/ci.yml` and triggers automatically on pushes and pull requests to `main` and `master`:

- **Automated Tests:** Executes the test suite against a matrix of PHP versions (8.4 and 8.5) backed by a PostgreSQL 16 service container.
- **Code Style Verification:** Validates code formatting using Laravel Pint (`./vendor/bin/pint --test tests/`).
- **Docker Verification:** Builds the container image and verifies that the application starts up cleanly.

### Docker & Docker Compose

A containerized development and execution environment is provided:

- `Dockerfile`: Multi-stage Alpine-based container (`php:8.4-cli-alpine`) preconfigured with PostgreSQL (`pdo_pgsql`, `pgsql`), RabbitMQ (`sockets`, `bcmath`), and required PHP extensions.
- `docker-compose.yml`: Multi-container stack orchestrating:
  - `app`: Laravel SaaS license order service
  - `postgres`: PostgreSQL 16 database with health check
  - `rabbitmq`: RabbitMQ 3 message broker with management console

#### Starting the stack:

```bash
docker compose up -d
```

#### Executing tests inside Docker:

```bash
docker compose exec app composer test
```

## Implementation considerations

Before deployment, address these observed limitations:

- **Access control:** `ControlAccess` permits requests without a bearer token or without a matching stored token. It decodes JWT claims without visible signature verification and logs token contents. Enforce verified authentication, tenant ownership, and authorization; remove sensitive logging.
- **Pricing:** discount validation and calculation are inconsistent. The current formula is `amount * (1 - discount)`, while validation messages describe a percentage range. Define the intended units, correct validation, and test pricing before processing purchases.
- **Order integrity:** transition validation, idempotent event processing, and reliable publication need strengthening. Database updates and message dispatch are not coordinated through an outbox, and publisher exceptions are caught without being rethrown.
- **Secrets:** review tracked INI configuration and sample payloads before reuse or redistribution. Keep credentials and tokens outside version control; rotate any exposed values that are valid.

## Code organization

| Location | Responsibility |
| --- | --- |
| `app/Domain/Model/` | Orders, license types, tenants, merchants, and access-token metadata |
| `app/Http/Controllers/ApiController.php` | Provisioning, token synchronization, and order operations |
| `app/Http/Middleware/ControlAccess.php` | Current IAM access-token checks |
| `app/Jobs/ProcessMessages.php` | RabbitMQ publication job |
| `app/Infrastructure/Messaging/` | Standalone integration listeners |
| `routes/api.php` | API endpoints and middleware assignments |
| `database/migrations/` | Database schema |
| `tests/` | Unit, feature, and integration test suite |

## Contributing

Keep changes scoped, document API or messaging contract changes, and include tests for affected business behavior. Use fictional data and local services during development. Do not commit credentials, access tokens, private keys, or customer information.

## License

`composer.json` declares MIT. No standalone project `LICENSE` file was found in the reviewed revision; the maintainer should add the intended license text and copyright notice. Third-party components retain their respective licenses.
