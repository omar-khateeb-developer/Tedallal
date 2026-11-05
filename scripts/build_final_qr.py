# -*- coding: utf-8 -*-
"""
Generate a pixel-perfect, studio-grade 1024x1024 QR Code for https://tedallal.tech
Matching the exact visual styling, proportions, and golden Tedallal emblem of the reference image.
"""

import os
import shutil
import qrcode
from qrcode.constants import ERROR_CORRECT_H
from PIL import Image, ImageDraw
import zxingcpp

def generate_pixel_perfect_qr():
    url = "https://tedallal.tech"
    target_canvas = 1024
    
    # 1. Version 4 with High Error Correction matches the 33x33 module grid of the reference QR
    qr = qrcode.QRCode(
        version=4,
        error_correction=ERROR_CORRECT_H,
        box_size=25,
        border=0, # We handle the margin on the 1024 canvas directly
    )
    qr.add_data(url)
    qr.make(fit=False)

    qr_raw = qr.make_image(fill_color="black", back_color="white").convert("RGBA")
    raw_w, raw_h = qr_raw.size # 33 * 25 = 825x825
    
    # 2. Create crisp 1024x1024 white canvas
    final_canvas = Image.new("RGBA", (target_canvas, target_canvas), (255, 255, 255, 255))
    
    # Position the 825x825 QR grid exactly centered (margin ~100px)
    qr_x = (target_canvas - raw_w) // 2 # (1024 - 825) // 2 = 99px
    qr_y = (target_canvas - raw_h) // 2 # 99px
    final_canvas.paste(qr_raw, (qr_x, qr_y), qr_raw)

    # 3. Center Badge: exactly 11 modules wide = 11 * 25 = 275px
    badge_size = 275
    badge_radius = 40
    badge_x0 = (target_canvas - badge_size) // 2
    badge_y0 = (target_canvas - badge_size) // 2
    badge_x1 = badge_x0 + badge_size
    badge_y1 = badge_y0 + badge_size

    draw = ImageDraw.Draw(final_canvas)
    draw.rounded_rectangle(
        [badge_x0, badge_y0, badge_x1, badge_y1],
        radius=badge_radius,
        fill=(255, 255, 255, 255)
    )

    # 4. Center Logo Emblem: Clean crop from original reference
    clean_emblem = Image.open("clean_tedallal_emblem.png")
    # Emblem dimensions inside 275px badge
    emblem_w = 205
    ratio = clean_emblem.height / clean_emblem.width
    emblem_h = int(emblem_w * ratio) # ~222px

    emblem_resized = clean_emblem.resize((emblem_w, emblem_h), Image.Resampling.LANCZOS)
    emblem_x = (target_canvas - emblem_w) // 2
    emblem_y = (target_canvas - emblem_h) // 2
    final_canvas.paste(emblem_resized, (emblem_x, emblem_y), emblem_resized)

    # Convert to RGB (lossless high quality PNG)
    final_rgb = final_canvas.convert("RGB")
    
    # Save locations
    out_root = os.path.abspath("tedallal_tech_qr.png")
    out_public = os.path.abspath("public/images/tedallal-tech-qr.png")
    artifact_dir = r"C:\Users\ASUS\.gemini\antigravity\brain\8b817e4d-e32a-49c8-a0f9-9f3b7546d63d"
    out_artifact = os.path.join(artifact_dir, "tedallal_tech_qr.png")

    os.makedirs(os.path.dirname(out_public), exist_ok=True)

    final_rgb.save(out_root, "PNG", quality=100)
    final_rgb.save(out_public, "PNG", quality=100)
    final_rgb.save(out_artifact, "PNG", quality=100)

    # 5. Verify Scannability with ZXing
    res = zxingcpp.read_barcode(final_rgb)
    if res and res.text:
        print(f"VERIFICATION PASSED! Scanned text: {res.text}")
        print(f"Format: {res.format}, EC Level: {res.ec_level}, Position: {res.position}")
    else:
        raise ValueError("CRITICAL: Generated QR code could not be decoded!")

    print(f"Generated 1024x1024 QR Code saved at:\n1. {out_root}\n2. {out_public}\n3. {out_artifact}")

if __name__ == "__main__":
    generate_pixel_perfect_qr()

