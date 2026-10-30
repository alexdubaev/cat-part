"""Small deterministic local gate for the CatPart home-polish ticket."""
from __future__ import annotations
import subprocess
import sys
from pathlib import Path
from urllib.request import urlopen

ROOT = Path(__file__).resolve().parents[1]
PHP = ROOT / "local-dev/bin/php/php.exe"
PHP_FILES = [
    ROOT / p for p in [
        "local-dev/site/app/Support/helpers.php",
        "local-dev/site/app/Models/Product.php",
        "local-dev/site/app/Controllers/CartController.php",
        "local-dev/site/app/routes.php",
        "local-dev/site/app/Views/home.php",
        "local-dev/site/app/Views/cart/index.php",
        "local-dev/site/app/Views/pages/about.php",
        "local-dev/site/app/Views/pages/contacts.php",
        "local-dev/site/app/Views/search/index.php",
    ]
]
URLS = ["/", "/catalog", "/poisk?q=3B-8489", "/o-kompanii", "/kontakty", "/stati", "/korzina"]

def main() -> int:
    failed = False
    for path in PHP_FILES:
        result = subprocess.run([str(PHP), "-l", str(path)], capture_output=True, text=True)
        print(result.stdout.strip() or result.stderr.strip())
        failed |= result.returncode != 0
    for route in URLS:
        try:
            with urlopen("http://127.0.0.1:8080" + route, timeout=8) as response:
                print(f"HTTP {response.status} {route}")
                failed |= response.status != 200
        except Exception as exc:
            print(f"HTTP FAIL {route}: {exc}")
            failed = True
    assets = list((ROOT / "local-dev/site/assets/img/catalog-icons").glob("*.webp"))
    print(f"catalog WebP assets: {len(assets)}")
    failed |= len(assets) < 18
    benefits = list((ROOT / "local-dev/site/assets/img/benefits").glob("*.webp"))
    print(f"benefit WebP assets: {len(benefits)}")
    failed |= len(benefits) < 4
    articles = [p for p in (ROOT / "local-dev/site/assets/img/articles").glob("*.webp") if not p.name.endswith("-thumb.webp")]
    thumbs = list((ROOT / "local-dev/site/assets/img/articles").glob("*-thumb.webp"))
    print(f"article WebP assets: {len(articles)} covers, {len(thumbs)} thumbs")
    failed |= len(articles) < 4 or len(thumbs) < 4
    return 1 if failed else 0

if __name__ == "__main__":
    sys.exit(main())
