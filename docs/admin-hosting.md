# CatPart admin hosting deployment

Hosting installation uses `tools/hosting-install.php`. The localhost installers,
test database restrictions and capture-only local worker retain their existing guards.
The pinned OpenCart 4.1.0.4 core is unchanged.

Run the installer with the hosting PHP 8.3 CLI and explicit `--site`, `--runtime`,
`--source`, `--upstream`, `--origin` and `--database` values. Runtime and source must
be outside the public site. Use a clean reviewed source archive with the package
checksums verified before execution. `--preflight` reports the source digest;
every modifying phase additionally requires that exact `--source-sha256`.
Never put installation tools, source packages, private runtime, storage or account
files in the document root. Do not print configuration or account file contents.

`--prepare` requires an unowned installation to have no `oc_` or `cp_` tables/views
or conflicting admin paths. It builds empty native tables and additive CatPart
metadata without cloning or replacing the canonical catalog. It creates one owner
account and saves its credentials privately on the server. It stages the native
assets and production bridges without changing public routes or article dates.
An owned manifest, exact schema and source identity are required to resume.
Interrupted preparation resumes only journaled table definitions verified against
temporary tables on the same database server. Native reference data and the single
owner account commit together with a completion marker; retries preserve that
account. Unknown tables and changed definitions stop the operation.

The production bridge loads existing site configuration at runtime. It preserves
real mail configuration and HTTPS. The native core, sessions, logs and vendors
remain private. Only the admin adapter and selected assets become public.
Writable image/media directories must deny script execution.

Before activation, install the hosting notification worker using the existing
crontab, preserving every unrelated entry. Bind the command to this private runtime
and exact site. Run its `--check` mode: this validates configuration and PHP mail
availability without processing jobs or sending test mail. Verify the installed
schedule and save the matching private transport receipt. Local transport checks
use an injected sender; they do not prove inbox delivery. A successful real job is
labelled “accepted by mail server”, never “delivered”.

`--activate --transport-ready=PRIVATE_RECEIPT` switches the reviewed public app and
admin under a short maintenance response. The article migration records originals
and column types: Moscow `DATETIME` values convert once; `TIMESTAMP` instants are
already timezone-aware and must not shift. Null publication dates remain null.
Existing catalog IDs, configurations, branding, uploads and customer requests survive.

`--rollback` disables the admin entry while retaining the compatible UTC public
application, additive tables and notification pipeline. This is an admin disable,
not restoration of old storefront code. Do not restore a database snapshot or old
article timestamps over subsequent article edits or new requests. Private code
backups support investigation; any further restoration needs an explicit compatible
procedure. Interrupted activation recovery must keep the site consistent before
removing its maintenance response.

Before claiming success verify the public native login/assets, Secure and HttpOnly
session cookie, denied private/config/install paths, actual native authentication
and permissions, article preview/edit/publication, and representative storefront
routes. Recheck catalog counts, prior article instants and published URLs. Rehearse
install/retry/failure recovery and admin disable against an isolated fresh schema;
never run fixture cleanup against the production database.

Hosting evidence and deployment receipts live outside Git. Record actual checks,
source commit, runtime identity, publication time and any checks not run. Keep the
total request-to-confirmed-result time separate from cleanup/reporting.
