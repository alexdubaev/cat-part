"""Convert ImageGen source manifest entries to optimized public WebP derivatives."""
from __future__ import annotations
import json
from pathlib import Path
from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
MANIFEST = ROOT / ".autopilot/2026-10-30-home-polish--wip/asset-manifest.json"
PUBLIC = ROOT / "local-dev/site/assets/img"

def main() -> None:
    entries = json.loads(MANIFEST.read_text(encoding="utf-8"))
    for item in entries:
        source = Path(item["path"])
        if not source.is_file():
            continue
        group = item["group"]
        target_dir = PUBLIC / group
        target_dir.mkdir(parents=True, exist_ok=True)
        target = target_dir / f"{item['name']}.webp"
        image = Image.open(source).convert("RGB")
        if group == "catalog-icons":
            image.thumbnail((384, 384), Image.Resampling.LANCZOS)
            canvas = Image.new("RGB", (384, 384), "white")
            canvas.paste(image, ((384 - image.width) // 2, (384 - image.height) // 2))
            canvas.save(target, "WEBP", quality=82, method=6)
        elif group == "benefits":
            image.thumbnail((640, 480), Image.Resampling.LANCZOS)
            canvas = Image.new("RGB", (640, 480), "white")
            canvas.paste(image, ((640 - image.width) // 2, (480 - image.height) // 2))
            canvas.save(target, "WEBP", quality=84, method=6)
        else:
            image.thumbnail((1200, 675), Image.Resampling.LANCZOS)
            canvas = Image.new("RGB", (1200, 675), "white")
            canvas.paste(image, ((1200 - image.width) // 2, (675 - image.height) // 2))
            canvas.save(target, "WEBP", quality=82, method=6)
            thumb = target_dir / f"{item['name']}-thumb.webp"
            small = canvas.resize((600, 338), Image.Resampling.LANCZOS)
            small.save(thumb, "WEBP", quality=78, method=6)
        print(f"{group}/{target.name}: {target.stat().st_size} bytes")

if __name__ == "__main__":
    main()
