# OpenCore Release Builder

The release builder creates the two assets consumed by the native OpenCore updater. It does not publish a GitHub Release and never changes `system/version.php`.

## Build

Run from the repository root with a clean working tree:

```text
C:\xampp\php\php.exe tools/release/build.php --source-version=2026.07.1 --composer=C:\path\to\composer.phar
```

The target version is read only from `system/version.php`. Output is written to `build/releases/`:

```text
opencore-<version>.zip
opencore-<version>.zip.sha256
```

The application payload contains tracked Git files, excluding repository-only `docs/`, `tools/release/`, `.gitignore`, and `.gitattributes`, plus local-only `AGENTS.md` and `UI-Sample/`. Protected and local paths such as `config.php`, `ocadmin/config.php`, `.env*`, `.git/`, external storage, and build output are rejected or excluded. Untracked files are never packaged. The vendor payload is built afresh from `composer.lock` using the supplied Composer PHAR and is checked against the lock package set before packaging.

Every compatible installed version must be supplied explicitly with a repeated `--source-version`. File removals are never inferred; supply each approved removal as a repeated `--remove=relative/path`. Database update identifiers are also explicit repeated `--database-update=<step-version>.NNN` values. With no database update arguments, the manifest contains `database.required=false` and `database.updates=[]`.

`--allow-dirty` exists only for isolated development/test builds and permits modified tracked files to be read from the working tree. It never makes untracked files releasable. A public or reviewed release must be built from a clean committed tree. `SOURCE_DATE_EPOCH` may be set to make the manifest time and ZIP entry timestamps reproducible.

The builder validates the completed ZIP with the current updater's archive extraction and staging validation code. Review the resulting manifest, SHA-256 sidecar, release notes, compatible source versions, removals, and database identifiers before uploading both assets to a GitHub Release tagged `v<version>`.
