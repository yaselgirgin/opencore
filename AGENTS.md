# AGENTS.md

## Project

OpenCore is an OpenCart-based internal business application platform.

Canonical OpenCart reference:

C:\xampp\htdocs\oc4104

Follow OpenCart 4.1.0.4 architecture and conventions as closely as possible unless:
- the relevant OpenCart feature was intentionally removed from OpenCore, or
- an accepted ADR / explicit owner decision says otherwise.

Do not reintroduce removed scope during OpenCart parity work:
- e-commerce/storefront
- OCMOD
- extension/marketplace infrastructure
- runtime self-updater

## Architecture

Use:

Controller -> Model -> Database

Do not introduce Service, Repository, ORM, DI container, migration framework,
custom routing framework, or another architectural layer unless explicitly approved.

Controllers handle request/validation/permission/response/view concerns.
Models handle database access and persistence.
Controllers must not contain direct SQL.

Custom modules should follow native OpenCart MVC conventions.
API controllers remain under catalog/controller/api/.

## OpenCart Helper Reuse

Before implementing utility or framework-level logic, check whether OpenCart 4.1.0.4
already provides an equivalent helper under `system/helper/`.

If an applicable OpenCart helper exists, prefer preserving and reusing it instead of
duplicating the behavior with native PHP calls, custom helpers, or ad-hoc utility code.

Keep OpenCart helper semantics as closely as possible.

Exceptions:
- the helper belongs only to functionality intentionally removed from OpenCore, or
- an accepted ADR / explicit owner decision requires different behavior.

Do not reintroduce removed e-commerce, extension, marketplace, OCMOD, or updater scope
only because an OpenCart helper exists.

## Canonical Distribution

Default admin directory:

admin/

Default storage directory:

system/storage/

Runtime code must always use DIR_STORAGE and must not hard-code a storage path.

OpenCore uses one root config.php.
There is no separate admin config.php.

config-dist.php is a tracked empty placeholder and must not be populated automatically.

Runtime vendor dependencies are distributed under:

system/storage/vendor/

Final users must not need Composer.

Post-install Security behavior should follow OpenCart 4.1.0.4 semantics for:
- install directory removal
- moving the complete storage directory outside the web root
- admin directory rename

Do not invent installer-specific alternatives for these operations unless explicitly approved.

## Safety and Owner Approval

Read-only inspection is allowed.

Do not perform any of the following without explicit owner approval:
- git add / staging
- commit
- push
- reset
- clean
- rebase
- cherry-pick
- branch deletion
- force push
- file or directory deletion
- destructive database changes

Never mutate the main OpenCore database for testing.

Destructive or E2E database tests must use an explicitly approved isolated test environment.

Do not delete temporary backups without explicit approval.

If a task requires a new architectural or product decision, stop and report the decision
instead of choosing on behalf of the owner.

## Task and Context Discipline

Work only on the requested task.

Do not perform repository-wide scans, audits, refactors, or comparisons unless explicitly requested.

Start from exact files, paths, routes, classes, or symbols provided by the task.

Use narrow rg/find searches only when a dependency must be located.

Do not read unrelated ADRs, plans, reports, or historical project documents.
Read only documents explicitly relevant to the current task.

Do not repeat checks already established as passing unless the current change could invalidate them.

Make the smallest safe change.
Do not include unrelated cleanup or refactoring.

## Validation

Run only checks relevant to the changed scope.

For PHP changes, run syntax checks on changed PHP files when practical.

Use targeted tests instead of broad E2E or repository-wide audits unless broad validation
is explicitly requested.

After implementation report only:
- changed files
- validation performed and result
- unresolved issue or required owner decision, if any

Do not provide a long narrative unless requested.

## Git and Sensitive Files

The repository must remain private.

Never commit:
- config.php
- .env or .env.*
- credentials or API keys
- mail/FTP passwords
- runtime logs
- cache/session files
- temporary uploads
- database backups
- customer data exports

SQL dumps and backups must not be committed.

The following canonical installer seed files are intentional tracked source files and are exceptions:

install/opencart-tr-tr.sql
install/opencart-en-gb.sql

## Instruction Priority

1. Explicit current owner instruction
2. Accepted ADR
3. AGENTS.md
4. Existing OpenCore conventions
5. Native OpenCart 4.1.0.4 conventions

Never silently override an accepted ADR or owner decision.