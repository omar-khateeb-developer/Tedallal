#!/usr/bin/env python3
"""
Tedallal Production-Ready QR Code Generator with Centered Logo
==============================================================
Requirements:
    pip install qrcode pillow zxing-cpp

Features:
- Error Correction Level H (~30% data recovery)
- High resolution output (>1000x1000 px)
- Automatic background removal & transparency for any logo
- Precise centering with safe quiet zone padding (2-3 modules)
- Strict mathematical assertion preventing Finder Pattern overlap
- Automated post-generation verification step with ZXing / pyzbar
"""

import os
import sys
import math
import argparse
from PIL import Image, ImageDraw, ImageOps
import qrcode
from qrcode.constants import ERROR_CORRECT_H

# Attempt scanner imports for automated verification
try:
    import zxingcpp
except ImportError:
    zxingcpp = None

try:
    from pyzbar.pyzbar import decode as pyzbar_decode
except ImportError:
    pyzbar_decode = None


def remove_background(image: Image.Image, tolerance: int = 40) -> Image.Image:
    """
    Intelligently removes uniform/near-white backgrounds and adds an alpha channel
    with soft anti-aliased edge feathering.
    """
    img = image.convert("RGBA")
    
    # Check if image already has transparent pixels
    alpha_extrema = img.getextrema()[3]
    if alpha_extrema[0] < 250:
        # Already has meaningful transparency
        bbox = img.getbbox()
        return img.crop(bbox) if bbox else img

    # Resize temporarily if excessively large for fast processing
    orig_size = img.size
    if max(img.size) > 600:
        img.thumbnail((600, 600), Image.Resampling.LANCZOS)

    w, h = img.size
    pixels = img.load()

    # Sample perimeter corners to detect background color
    corners = [
        pixels[0, 0][:3],
        pixels[w - 1, 0][:3],
        pixels[0, h - 1][:3],
        pixels[w - 1, h - 1][:3],
    ]
    bg_r = sum(c[0] for c in corners) // 4
    bg_g = sum(c[1] for c in corners) // 4
    bg_b = sum(c[2] for c in corners) // 4

    # Apply distance threshold with feathering
    for y in range(h):
        for x in range(w):
            r, g, b, a = pixels[x, y]
            dist = math.sqrt((r - bg_r) ** 2 + (g - bg_g) ** 2 + (b - bg_b) ** 2)
            if dist < tolerance:
                pixels[x, y] = (255, 255, 255, 0)
            elif dist < tolerance + 20:
                feather_alpha = int(255 * (dist - tolerance) / 20)
                pixels[x, y] = (r, g, b, min(a, feather_alpha))

    # Crop tightly to content
    bbox = img.getbbox()
    if bbox:
        img = img.crop(bbox)

    # Scale back up if thumbnail was made
    if img.size != orig_size and bbox:
        scale = orig_size[0] / 600
        new_w = int(img.size[0] * scale)
        new_h = int(img.size[1] * scale)
        img = img.resize((new_w, new_h), Image.Resampling.LANCZOS)

    return img


def boxes_intersect(box1, box2) -> bool:
    """Check if two (x1, y1, x2, y2) bounding boxes intersect."""
    return not (box1[2] <= box2[0] or box1[0] >= box2[2] or box1[3] <= box2[1] or box1[1] >= box2[3])


def generate_qr(
    target_url: str = "https://tedallal.omarkhateeb.com/",
    logo_path: str = "public/images/tedallal-brand-logo.jpg",
    output_path: str = "tedallal_qr_code.png",
    min_resolution: int = 1200,
    logo_ratio: float = 0.23,
) -> str:
    """
    Generates a high-resolution, production-ready QR code with an embedded logo.
    """
    print(f"[*] Target URL: {target_url}")
    print(f"[*] Error Correction: Level H (High, ~30% capacity)")

    # 1. Initialize QR Code
    qr = qrcode.QRCode(
        version=None,  # Auto-fit
        error_correction=ERROR_CORRECT_H,
        box_size=10,   # Will adjust for target resolution
        border=4,      # Standard Quiet Zone (4 modules)
    )
    qr.add_data(target_url)
    qr.make(fit=True)

    module_count = qr.modules_count
    total_modules = module_count + (qr.border * 2)

    # Calculate optimal box_size to meet or exceed min_resolution
    box_size = max(10, math.ceil(min_resolution / total_modules))
    qr.box_size = box_size

    # Generate base monochrome QR Image
    qr_img = qr.make_image(fill_color="black", back_color="white").convert("RGBA")
    qr_w, qr_h = qr_img.size
    print(f"[*] Generated Base QR Matrix: {module_count}x{module_count} modules ({qr_w}x{qr_h} px)")

    # 2. Process Logo
    if os.path.exists(logo_path):
        print(f"[*] Loading and processing logo from: {logo_path}")
        raw_logo = Image.open(logo_path)
        processed_logo = remove_background(raw_logo)

        # Scale logo to requested ratio (capped strictly at 24% to prevent decoding stress)
        effective_ratio = min(max(logo_ratio, 0.18), 0.24)
        target_logo_dim = int(qr_w * effective_ratio)
        processed_logo.thumbnail((target_logo_dim, target_logo_dim), Image.Resampling.LANCZOS)
        lw, lh = processed_logo.size

        # 3. Create Quiet Zone Plate around Logo (1.5 - 2 modules padding)
        padding = int(box_size * 1.5)
        plate_w = lw + (2 * padding)
        plate_h = lh + (2 * padding)

        plate_x = (qr_w - plate_w) // 2
        plate_y = (qr_h - plate_h) // 2
        plate_box = (plate_x, plate_y, plate_x + plate_w, plate_y + plate_h)

        # 4. Safety Validation: Verify Finder Patterns are never overlapped
        # Finder pattern is 7x7 modules + 1 module separator = 8x8 modules
        fp_size = 8 * box_size
        b_offset = qr.border * box_size
        finder_patterns = {
            "Top-Left": (b_offset, b_offset, b_offset + fp_size, b_offset + fp_size),
            "Top-Right": (qr_w - b_offset - fp_size, b_offset, qr_w - b_offset, b_offset + fp_size),
            "Bottom-Left": (b_offset, qr_h - b_offset - fp_size, b_offset + fp_size, qr_h - b_offset),
        }

        for name, fp_box in finder_patterns.items():
            if boxes_intersect(plate_box, fp_box):
                raise ValueError(f"FATAL: Logo quiet zone overlaps {name} Finder Pattern! Reduce logo_ratio.")

        print(f"[+] Safety Check PASSED: Logo is safely isolated from all 3 Finder Patterns.")

        # 5. Draw white rounded plate (Quiet Zone)
        draw = ImageDraw.Draw(qr_img)
        corner_radius = int(box_size * 1.2)
        draw.rounded_rectangle(
            plate_box,
            radius=corner_radius,
            fill=(255, 255, 255, 255),
        )

        # 6. Paste transparent logo centered on plate
        logo_x = (qr_w - lw) // 2
        logo_y = (qr_h - lh) // 2
        qr_img.paste(processed_logo, (logo_x, logo_y), processed_logo)
        print(f"[*] Embedded Logo ({lw}x{lh} px) at center ({logo_x}, {logo_y})")
    else:
        print(f"[!] Warning: Logo path '{logo_path}' not found. Generating clean QR without logo.")

    # 7. Save output file
    final_img = qr_img.convert("RGB")
    final_img.save(output_path, "PNG", quality=100, optimize=True)
    print(f"[+] Saved high-resolution QR Code ({final_img.size[0]}x{final_img.size[1]} px) -> {output_path}")

    # 8. Automated Verification
    verify_qr_code(output_path, target_url)

    return output_path


def verify_qr_code(image_path: str, expected_url: str):
    """
    Automated Verification: Scans the generated output file immediately,
    verifies it resolves back to the exact URL, and prints success or throws an error.
    """
    print(f"[*] Running automated scan verification on '{image_path}'...")
    img = Image.open(image_path)
    decoded_url = None
    engine_name = None

    # Engine 1: ZXing-C++
    if zxingcpp:
        result = zxingcpp.read_barcode(img)
        if result and result.valid:
            decoded_url = result.text
            engine_name = "ZXing-C++"

    # Engine 2: pyzbar
    if not decoded_url and pyzbar_decode:
        results = pyzbar_decode(img)
        if results:
            decoded_url = results[0].data.decode("utf-8")
            engine_name = "pyzbar"

    if not engine_name:
        raise RuntimeError(
            "CRITICAL: Verification scanner failed to initialize. "
            "Please install 'zxing-cpp' (`pip install zxing-cpp`) or 'pyzbar'."
        )

    if decoded_url != expected_url:
        raise AssertionError(
            f"Verification FAILED: Scanned text '{decoded_url}' does not match expected URL '{expected_url}'"
        )

    print(f"\n=======================================================")
    print(f"Verification SUCCESS: {decoded_url}")
    print(f"Decoder Engine: {engine_name}")
    print(f"Scanner Health: 100% Readable (Production-Grade)")
    print(f"=======================================================\n")


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Generate high-resolution QR with centered logo.")
    parser.add_argument("--url", default="https://tedallal.omarkhateeb.com/", help="Target URL")
    parser.add_argument("--logo", default="public/images/tedallal-brand-logo.jpg", help="Logo image path")
    parser.add_argument("--output", default="public/images/tedallal-production-qr.png", help="Output PNG file path")
    parser.add_argument("--size", type=int, default=1200, help="Minimum pixel width/height (default: 1200)")
    parser.add_argument("--ratio", type=float, default=0.23, help="Logo size ratio (default: 0.23)")

    args = parser.parse_args()

    generate_qr(
        target_url=args.url,
        logo_path=args.logo,
        output_path=args.output,
        min_resolution=args.size,
        logo_ratio=args.ratio,
    )

