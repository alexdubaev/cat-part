# Local CatPart preview

This folder is an isolated preview environment. It uses a local MariaDB instance on port 3307 and PHP on port 8080. The production `app/Config/config.local.php` is not copied; the local override points only to `127.0.0.1`.

After the bundled runtimes are extracted, start with:

```powershell
.\start-local.ps1
```

Open `http://127.0.0.1:8080/`. Stop with:

```powershell
.\stop-local.ps1
```

The first start creates `data/mariadb`, creates `local_catpart`, and imports the dump from the parent folder. The original site folder, production database settings, and uploaded images are not changed. Local `site/upload` is a private copy of the downloaded images so local edits cannot affect the source tree.
