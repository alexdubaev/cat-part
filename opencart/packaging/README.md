# CatPart offline package

`python tools/admin-package.py build` creates byte-deterministic `dist/catpart-admin.zip` and SHA256 sidecar. The archive contains the storefront source, CatPart extension, portable tools/tests, owner documentation, upstream lock and the exact verified upstream archive. No runtime, credentials, real uploads or database dump is shipped. The upstream archive includes its own original installer reference SQL and licenses; this is vendor source, not a site database dump.

`package-manifest.json` records source Git revision, complete payload SHA256/size list and content-tree digest. Uncommitted packaging files are included from the explicit packaging/tools/docs allowlist, so the tree digest is authoritative until the final Git commit is made. Rebuild after a source commit for release provenance. ZIP timestamps/permissions/order are fixed; there are no build-time values.

Run `verify --package ZIP` and compare the sidecar through a trusted channel before use. Internal hashes detect corruption; they are not a signature against an attacker replacing the whole archive.

Install/update are previews unless `--apply` is explicit. Installation is limited to a fresh isolated folder with a new localhost task schema; it never installs on hosting. See `docs/admin-guide.md` and `docs/admin-migration.md`. The permanent local stand is `D:/codex/cat-part/admin-stand`; PHP/MariaDB and read-only source images remain external prerequisites. The stand's code/router/extension/defaults/assets never depend on the temporary Git worktree.
