"""Cache WebP product derivatives beside preserved PNG originals.

Run on the local asset volume or deployment job; generated derivatives are not
part of the repository because the catalog contains ~23k source PNGs.
"""
from __future__ import annotations
import argparse
from pathlib import Path
from PIL import Image

ROOT = Path(__file__).resolve().parents[1] / "local-dev/site/upload/products"

def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--limit", type=int, default=0, help="convert at most N files (0 = all)")
    args = parser.parse_args()
    converted = skipped = 0
    for source in ROOT.rglob("*.png"):
        target = source.with_suffix(".webp")
        if target.exists() and target.stat().st_mtime >= source.stat().st_mtime:
            skipped += 1
            continue
        with Image.open(source) as image:
            image.thumbnail((1200, 1200), Image.Resampling.LANCZOS)
            if "A" in image.getbands():
                background = Image.new("RGBA", image.size, (255, 255, 255, 255))
                background.alpha_composite(image.convert("RGBA"))
                image = background.convert("RGB")
            else:
                image = image.convert("RGB")
            image.save(target, "WEBP", quality=84, method=6)
        converted += 1
        if args.limit and converted >= args.limit:
            break
    print(f"converted={converted} skipped={skipped}")

if __name__ == "__main__":
    main()
