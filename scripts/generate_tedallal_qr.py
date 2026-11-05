# -*- coding: utf-8 -*-
import os
import shutil
import qrcode
from qrcode.constants import ERROR_CORRECT_H
from PIL import Image, ImageDraw

def generate_qr(url="https://tedallal.tech", output_path="tedallal_tech_qr.png"):
    # 1. Create QR Code object with High Error Correction (allows up to 30% center overlay)
    qr = qrcode.QRCode(
        version=None,
        error_correction=ERROR_CORRECT_H,
        box_size=24,
        border=3,
    )
    qr.add_data(url)
    qr.make(fit=True)

    # 2. Make QR image
    qr_img = qr.make_image(fill_color="black", back_color="white").convert("RGBA")
    
    # Target canvas size: 1024x1024 with clean white background
    target_size = 1024
    final_img = Image.new("RGBA", (target_size, target_size), (255, 255, 255, 255))
    
    # Resize QR code nicely to fit within ~840x840 inside the 1024x1024 canvas
    qr_inner_size = 840
    qr_resized = qr_img.resize((qr_inner_size, qr_inner_size), Image.Resampling.NEAREST)
    
    offset_x = (target_size - qr_inner_size) // 2
    offset_y = (target_size - qr_inner_size) // 2
    final_img.paste(qr_resized, (offset_x, offset_y), qr_resized)

    # 3. Prepare the center emblem badge
    # Open the clean extracted emblem from the original user upload
    clean_emblem_path = "clean_tedallal_emblem.png"
    if not os.path.exists(clean_emblem_path):
        # Fallback extract from uploaded media
        src_uploaded = r"C:/Users/ASUS/.gemini/antigravity/brain/8b817e4d-e32a-49c8-a0f9-9f3b7546d63d/.user_uploaded/media_1793909509227_166e9199.png"
        src_im = Image.open(src_uploaded)
        clean_emblem = src_im.crop((352 + 45, 352 + 35, 672 - 45, 672 - 35))
    else:
        clean_emblem = Image.open(clean_emblem_path)

    # Badge dimensions
    badge_size = 280
    badge_radius = 42

    # Create center rounded rectangle patch with white background
    draw = ImageDraw.Draw(final_img)
    badge_x0 = (target_size - badge_size) // 2
    badge_y0 = (target_size - badge_size) // 2
    badge_x1 = badge_x0 + badge_size
    badge_y1 = badge_y0 + badge_size

    # Draw rounded rectangle in pure white
    draw.rounded_rectangle(
        [badge_x0, badge_y0, badge_x1, badge_y1],
        radius=badge_radius,
        fill=(255, 255, 255, 255)
    )

    # Resize emblem to fit gracefully inside badge with comfortable margin
    emblem_target_w = 210
    ratio = clean_emblem.height / clean_emblem.width
    emblem_target_h = int(emblem_target_w * ratio)
    emblem_resized = clean_emblem.resize((emblem_target_w, emblem_target_h), Image.Resampling.LANCZOS)

    emblem_x = (target_size - emblem_target_w) // 2
    emblem_y = (target_size - emblem_target_h) // 2
    final_img.paste(emblem_resized, (emblem_x, emblem_y), emblem_resized)

    # Convert to RGB and save high quality PNG
    final_rgb = final_img.convert("RGB")
    final_rgb.save(output_path, "PNG", quality=100)
    print(f"Successfully generated QR code at: {output_path}")

    # Also save to public/images/ and artifact dir
    pub_path = os.path.abspath("public/images/tedallal-tech-qr.png")
    os.makedirs(os.path.dirname(pub_path), exist_ok=True)
    final_rgb.save(pub_path, "PNG", quality=100)

    artifact_dir = r"C:\Users\ASUS\.gemini\antigravity\brain\8b817e4d-e32a-49c8-a0f9-9f3b7546d63d"
    artifact_path = os.path.join(artifact_dir, "tedallal_tech_qr.png")
    final_rgb.save(artifact_path, "PNG", quality=100)

    print(f"Copied to:\n1. {pub_path}\n2. {artifact_path}")

if __name__ == "__main__":
    generate_qr()

