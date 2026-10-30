"""Cache WebP product derivatives beside preserved PNG originals.

Run on the local asset volume or deployment job; generated derivatives are not
part of the repository because the catalog contains ~23k source PNGs.
"""
from __future__ import annotations
import argparse
from io import BytesIO
from pathlib import Path
from PIL import Image, UnidentifiedImageError

ROOT = Path(__file__).resolve().parents[1] / "local-dev/site/upload/products"

def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--limit", type=int, default=0, help="convert at most N files (0 = all)")
    parser.add_argument("--shard", type=int, default=0, help="zero-based shard index")
    parser.add_argument("--shards", type=int, default=1, help="number of disjoint shards")
    args = parser.parse_args()
    converted = skipped = failed = repaired = 0
    sources = sorted(ROOT.rglob("*.png"))
    if args.shards > 1:
        sources = [source for index, source in enumerate(sources) if index % args.shards == args.shard]
    for source in sources:
        target = source.with_suffix(".webp")
        if target.exists() and target.stat().st_mtime >= source.stat().st_mtime:
            skipped += 1
            continue
        try:
            image_context = Image.open(source)
        except UnidentifiedImageError:
            raw = source.read_bytes()
            # A small subset of the imported files lost only the 8-byte PNG
            # signature; repair the derived read in memory, never the source.
            if raw[4:8] != b"IHDR":
                failed += 1
                continue
            image_context = Image.open(BytesIO(b"\x89PNG\r\n\x1a\n" + raw))
            repaired += 1
        with image_context as image:
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
    print(f"converted={converted} skipped={skipped} repaired={repaired} failed={failed}")

if __name__ == "__main__":
    main()
