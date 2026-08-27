# AGENTS.md

## Project Overview

This repository is an OpenCart 4.x based internal business application platform.

The project does not use OpenCart primarily as an e-commerce storefront.  
The OpenCart admin panel is used as the main application interface for ERP, CRM, agenda, quotation, fair notes, reports, and other internal business modules.

The current first objective is to remove the stock OpenCart e-commerce functionality while preserving the useful framework and admin infrastructure.

## Project Paths

Project root example:

```text
C:\xampp\htdocs\opencore
```

Admin directory:

```text
admin/
```

Canonical default storage directory:

```text
system/storage/
```

Local development currently uses the repository canonical storage directory.

External storage remains a supported deployment option. The installer may optionally move the complete storage directory outside the repository.

Runtime code must continue to use `DIR_STORAGE` and must not hard-code either the internal or an external storage filesystem path.

The installer directory has already been deleted.

## Architecture Decision

The project uses the following architecture:

```text
Controller → Model → Database
```

Keep this architecture.

Do not introduce any of the following unless explicitly requested in a future ADR:

- Service layer
- Repository layer
- Custom module loader
- Dependency injection container
- New extension framework
- Domain-driven architecture
- ORM
- Migration framework
- New routing framework

Controllers should manage:

- Request handling
- Input validation
- Permission checks
- Model calls
- Response generation
- View rendering

Models should manage:

- Database queries
- Data retrieval
- Insert, update, and delete operations

Controllers must not contain direct SQL queries.

## Module Structure

Custom admin modules must follow the native OpenCart structure.

Example:

```text
admin/controller/agenda/calendar.php
admin/model/agenda/calendar.php
admin/language/tr-tr/agenda/calendar.php
admin/view/template/agenda/calendar.twig
```

Example route:

```text
agenda/calendar
```

API controllers must live under the catalog API namespace.

Example:

```text
catalog/controller/api/agenda/calendar.php
```

Example API route:

```text
api/agenda/calendar
```

Do not move custom modules into the OpenCart extension system unless explicitly requested.

## Protected Core Components

Do not remove or redesign these components without explicit approval:

- Registry
- Loader
- Router
- Action
- Controller base class
- Model base class
- Proxy
- Event system
- Config
- Request
- Response
- Session
- Cache
- Log
- Database drivers
- Language
- URL
- Document
- Twig/template integration
- Encryption and security helpers
- Upload helpers
- Pagination
- Admin login and logout
- Admin user management
- User groups
- Access and modify permissions
- Common admin header and footer
- Admin error pages
- Settings infrastructure

Mail and image components must be preserved when used by custom business modules.

## Current Primary Task

The current task is defined by:

```text
docs/adr/ADR-001-opencart-eticaret-temizligi.md
```

The objective is to physically remove stock OpenCart e-commerce code while preserving the admin framework and custom business modules.

Before deleting files, classify them as one of:

- `CORE`
- `ECOMMERCE-STOCK`
- `CUSTOM-BUSINESS`
- `SHARED`
- `UNKNOWN`

Never delete a file based only on its filename.

Names such as the following may also be used by custom ERP functionality:

- order
- customer
- product
- category
- report
- mail
- image
- setting

Search for actual dependencies before removing them.

## Cleanup Rules

Follow this order:

1. Create dependency inventories.
2. Remove admin e-commerce navigation.
3. Remove stock e-commerce dashboard widgets.
4. Convert catalog to API-only behavior.
5. Add and verify `api/system/ping`.
6. Remove stock e-commerce areas incrementally.
7. Remove orphan routes, models, languages, templates, events, and cron references.
8. Run smoke tests after each logical change.
9. Produce a final cleanup report.

Required inventory files:

```text
docs/cleanup/file-inventory.md
docs/cleanup/route-inventory.md
docs/cleanup/table-inventory.md
```

Required final report:

```text
docs/cleanup/cleanup-report.md
```

## Database Safety

Do not execute destructive database operations during the current cleanup task.

Forbidden unless explicitly approved:

```sql
DROP TABLE
TRUNCATE TABLE
DROP DATABASE
```

Do not delete production or development data.

Classify database tables as:

- `KEEP`
- `MIGRATE`
- `DROP-CANDIDATE`

Database table removal will be handled in a separate ADR.

## Catalog Policy

The catalog side will not serve a storefront.

Target behavior:

- API-only catalog application
- JSON responses
- No product listing
- No category pages
- No cart
- No checkout
- No customer account storefront
- No wishlist
- No product compare
- No storefront theme output

Required health endpoint:

```text
GET index.php?route=api/system/ping
```

Expected response:

```json
{
  "success": true
}
```

Unknown API routes should return a JSON 404 response.

## Admin Policy

The admin application must continue to provide:

- Login
- Logout
- Users
- User groups
- Access permissions
- Modify permissions
- Settings
- Common layout
- Error handling
- Custom business modules

The stock e-commerce menus and dashboard widgets must be removed.

The repository canonical default admin directory is:

```text
admin/
```

The installer may optionally rename the admin directory during installation. Runtime code must not hard-code the admin directory name or create a parallel second admin application directory.

## Git Rules

The repository must remain private.

Do not commit directly to `main` during cleanup.

Use:

```text
cleanup/remove-ecommerce
```

for the e-commerce cleanup work.

Make small, logical, reversible commits.

Examples:

```text
chore(cleanup): add ecommerce dependency inventories
refactor(admin): remove ecommerce navigation
refactor(catalog): convert storefront to api-only
chore(cleanup): remove cart and checkout
fix(cleanup): remove orphan ecommerce references
test(cleanup): add admin and api smoke tests
docs(cleanup): add final cleanup report
```

Before each commit:

1. Review `git diff`.
2. Review `git status`.
3. Run PHP syntax checks on changed PHP files.
4. Run available automated tests.
5. Check application logs.
6. Verify admin login.
7. Verify permissions.
8. Verify at least one custom module.
9. Verify API ping.

Do not create commits containing unrelated refactoring.

Do not push secrets.

## Files That Must Not Be Committed

Never commit:

```text
/config.php
.env
.env.*
*.sql
*.sql.gz
*.zip
*.7z
*.log
```

Also do not commit:

- Database credentials
- API keys
- Mail passwords
- FTP credentials
- Session files
- Cache files
- Runtime logs
- Temporary uploads
- Database backups
- Customer data exports

The external storage directory is not part of the repository.

## Change Discipline

Do not perform broad rewrites while removing e-commerce code.

For every deletion:

1. Search route references.
2. Search controller references.
3. Search model loading calls.
4. Search language loading calls.
5. Search Twig includes and links.
6. Search event actions.
7. Search cron references.
8. Search database table usage.
9. Confirm custom modules do not depend on the target.
10. Run smoke tests.

When a dependency is uncertain:

- Do not delete it.
- Mark it as `SHARED` or `UNKNOWN`.
- Record it in the cleanup report.

## Testing Requirements

At minimum verify:

### Admin

- Login page loads.
- Valid login succeeds.
- Invalid login fails.
- Logout works.
- User page loads.
- User group page loads.
- Access permission works.
- Modify permission works.
- Custom dashboard loads.
- At least one custom module can list, open, save, and delete records.
- Common header and footer assets load.
- No missing controller, model, language, template, or class errors appear in logs.

### API

- Ping endpoint returns HTTP 200.
- Ping endpoint returns JSON.
- Unknown API route returns JSON 404.
- Storefront product route does not work.
- Storefront category route does not work.
- Cart route does not work.
- Checkout route does not work.
- Customer account storefront route does not work.

### Static Checks

- No active references to deleted routes.
- No active references to deleted models.
- No active references to deleted language files.
- No active references to deleted Twig templates.
- No orphan event actions.
- No orphan cron jobs.
- PHP syntax checks pass.

## Working Style for Codex

Before modifying code:

1. Read this file.
2. Read the relevant ADR.
3. Inspect the repository.
4. Identify affected files.
5. Identify risks.
6. Make the smallest safe change.

After modifying code:

1. Summarize changed files.
2. Explain important dependency decisions.
3. List tests executed.
4. Report failures honestly.
5. List files intentionally not deleted because they are `SHARED` or `UNKNOWN`.
6. Do not claim success without test evidence.

## Priority Order

When instructions conflict, use this priority:

1. Explicit current user instruction
2. Accepted ADR
3. This `AGENTS.md`
4. Existing project conventions
5. Native OpenCart conventions

Do not silently override an ADR.
