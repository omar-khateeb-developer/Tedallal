#!/usr/bin/env python3
"""
Studio-Grade Broadcast Marketing Promo Video (MP4) for Tedallal Platform.
Format: 1080x1920 Vertical (9:16), 30 FPS, H.264 / AAC Stereo.
Master Quality:
- 100% Isolated, pure transparent golden mobile splash calligraphy logo with gaussian glow.
- Seamless 10-frame crossfade transitions between all scenes (zero jump cuts).
- Safe margin constraints (max text width 700px, 190px+ side margins) for zero edge clipping.
- Studio-grade multi-track stereo audio (sub-bass boom, tense heartbeat, descending bleed tones,
  groovy 120 BPM drums, crystalline service bell ding, holographic laser scan, and golden orchestral finale).
"""

import os
import sys
import math
import wave
import struct
import random
import subprocess
import numpy as np
from PIL import Image, ImageDraw, ImageFont, ImageFilter
import imageio
import imageio_ffmpeg
import arabic_reshaper
import bidi.algorithm

# Ensure UTF-8 output on Windows console
if sys.stdout.encoding != 'utf-8':
    try:
        sys.stdout.reconfigure(encoding='utf-8')
    except Exception:
        pass

# Paths
BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PUBLIC_DIR = os.path.join(BASE_DIR, "public")
IMAGES_DIR = os.path.join(PUBLIC_DIR, "images")
STORYBOARD_DIR = os.path.join(IMAGES_DIR, "storyboard")
VIDEOS_DIR = os.path.join(PUBLIC_DIR, "videos")
os.makedirs(VIDEOS_DIR, exist_ok=True)

OUTPUT_MP4 = os.path.join(VIDEOS_DIR, "tedallal-promo.mp4")
TEMP_VIDEO_ONLY = os.path.join(VIDEOS_DIR, "temp_video_only.mp4")
TEMP_AUDIO = os.path.join(VIDEOS_DIR, "temp_audio.wav")

WIDTH = 1080
HEIGHT = 1920
FPS = 30
TOTAL_FRAMES = 480  # 16.0 seconds

# Asset Paths
IMG_SCENE_1 = os.path.join(STORYBOARD_DIR, "scene_1_restaurant_rush_1793695520429.jpg")
IMG_SCENE_3 = os.path.join(STORYBOARD_DIR, "scene_3_tedallal_qr_solution_1793695540137.jpg")
IMG_SCENE_4 = os.path.join(STORYBOARD_DIR, "scene_4_kitchen_kds_automation_1793695563497.jpg")
IMG_GOLD_LOGO = os.path.join(IMAGES_DIR, "tedallal-golden-logo-pure.png")
IMG_QR = os.path.join(IMAGES_DIR, "tedallal-production-qr.png")

# Load Fonts
FONT_PATH = "C:/Windows/Fonts/tahomabd.ttf"
if not os.path.exists(FONT_PATH):
    FONT_PATH = "C:/Windows/Fonts/arialbd.ttf"

def get_font(size):
    return ImageFont.truetype(FONT_PATH, size)

# Balanced font sizes with safe margins
FONT_TITLE = get_font(44)
FONT_SUBTITLE = get_font(32)
FONT_BADGE = get_font(26)
FONT_SMALL = get_font(22)
FONT_BIG_STAT = get_font(80)
FONT_BRAND = get_font(50)

# Background Cache
bg_cache = {}

def ar(text):
    """Reshape Arabic text for RTL display in Pillow."""
    reshaped = arabic_reshaper.reshape(text)
    return bidi.algorithm.get_display(reshaped)

def draw_multiline_text_centered(draw, text, y, font, fill_color, max_width=700, shadow=True, line_spacing=12):
    """Draw centered Arabic text with strict line wrapping to guarantee zero clipping."""
    words = text.split()
    lines = []
    current_line = []
    
    for word in words:
        test_line = " ".join(current_line + [word])
        bidi_test = ar(test_line)
        bbox = draw.textbbox((0, 0), bidi_test, font=font)
        if bbox[2] - bbox[0] <= max_width or not current_line:
            current_line.append(word)
        else:
            lines.append(" ".join(current_line))
            current_line = [word]
    if current_line:
        lines.append(" ".join(current_line))
        
    for line in lines:
        bidi_line = ar(line)
        bbox = draw.textbbox((0, 0), bidi_line, font=font)
        tw = bbox[2] - bbox[0]
        th = bbox[3] - bbox[1]
        x = (WIDTH - tw) // 2
        if shadow:
            draw.text((x + 2, y + 3), bidi_line, font=font, fill=(0, 0, 0, 190))
        draw.text((x, y), bidi_line, font=font, fill=fill_color)
        y += th + line_spacing
    return y + 4

def draw_pill(draw, text, y, font, bg_color, text_color, border_color=None, padding_x=26, padding_y=9):
    """Draw a centered badge/pill with safe margin bounds."""
    bidi_text = ar(text)
    bbox = draw.textbbox((0, 0), bidi_text, font=font)
    tw = bbox[2] - bbox[0]
    th = bbox[3] - bbox[1]
    
    w = tw + padding_x * 2
    h = th + padding_y * 2
    x = (WIDTH - w) // 2
    
    rect = [x, y, x + w, y + h]
    draw.rounded_rectangle(rect, radius=h//2, fill=bg_color, outline=border_color, width=2 if border_color else 0)
    draw.text((x + padding_x, y + padding_y - 2), bidi_text, font=font, fill=text_color)
    return y + h + 16

def create_glass_card(width, height, radius=24, bg_color=(14, 18, 30, 220), border_color=(212, 175, 55, 90)):
    """Generate a rounded glassmorphic card image with alpha."""
    card = Image.new("RGBA", (width, height), (0, 0, 0, 0))
    draw = ImageDraw.Draw(card)
    draw.rounded_rectangle([0, 0, width, height], radius=radius, fill=bg_color, outline=border_color, width=2)
    return card

def get_ken_burns_bg(path, frame_in_scene, total_scene_frames, zoom_start=1.0, zoom_end=1.09):
    """Create subtle slow Ken Burns zoom on background image."""
    if path not in bg_cache:
        if os.path.exists(path):
            im = Image.open(path).convert("RGB")
            bg_cache[path] = im
        else:
            bg_cache[path] = Image.new("RGB", (WIDTH, HEIGHT), (12, 16, 24))

    src = bg_cache[path]
    progress = frame_in_scene / max(1, total_scene_frames)
    zoom = zoom_start + (zoom_end - zoom_start) * progress

    src_w, src_h = src.size
    aspect_target = WIDTH / HEIGHT
    
    crop_h = int(src_h / zoom)
    crop_w = int(crop_h * aspect_target)
    
    if crop_w > src_w:
        crop_w = int(src_w / zoom)
        crop_h = int(crop_w / aspect_target)

    left = (src_w - crop_w) // 2
    top = (src_h - crop_h) // 2
    cropped = src.crop((left, top, left + crop_w, top + crop_h))
    return cropped.resize((WIDTH, HEIGHT), Image.Resampling.LANCZOS)

def apply_dark_gradient(base_img, top_opacity=0.64, bottom_opacity=0.88):
    """Add a dark vertical gradient overlay for optimal text contrast."""
    overlay = Image.new("RGBA", (WIDTH, HEIGHT), (0, 0, 0, 0))
    draw = ImageDraw.Draw(overlay)
    
    for y in range(HEIGHT):
        alpha = int(255 * (top_opacity + (bottom_opacity - top_opacity) * (y / HEIGHT)))
        draw.line([(0, y), (WIDTH, y)], fill=(8, 11, 18, alpha))
        
    return Image.alpha_composite(base_img.convert("RGBA"), overlay)

# ----------------- BASE SCENE RENDERERS -----------------

def render_scene_1_base(frame_idx):
    """Scene 1: The Restaurant Rush & Waiter Delay (0 - 95 frames / 0.0s - 3.2s)"""
    bg = get_ken_burns_bg(IMG_SCENE_1, frame_idx, 95, zoom_start=1.0, zoom_end=1.08)
    frame = apply_dark_gradient(bg, top_opacity=0.66, bottom_opacity=0.88)
    draw = ImageDraw.Draw(frame)

    # Top Brand Bar
    draw_pill(draw, "تدلّل | حلول المطاعم الذكية ⚡", 110, FONT_BADGE, (20, 26, 38, 230), (255, 215, 0), (212, 175, 55, 120))

    # Warning alert badge with subtle pulse
    pulse = math.sin(frame_idx * 0.22) * 3
    draw_pill(draw, "⚠️ العجقة وضياع الوقت", int(580 + pulse), FONT_BADGE, (140, 20, 20, 230), (255, 220, 220), (220, 50, 50, 200))

    # Glass Card for main text (Width 840px -> Margin 120px on each side)
    card_w = 840
    card = create_glass_card(card_w, 450, radius=28, bg_color=(12, 16, 26, 225), border_color=(220, 60, 60, 140))
    card_draw = ImageDraw.Draw(card)
    
    y = 50
    y = draw_multiline_text_centered(card_draw, "كم طاولة عم تخسر كل ليلة؟", y, FONT_TITLE, (255, 255, 255), max_width=700)
    y += 12
    y = draw_multiline_text_centered(card_draw, "الويترز ملخومين والزبائن ناطرين..", y, FONT_SUBTITLE, (255, 190, 190), max_width=700)
    y = draw_multiline_text_centered(card_draw, "وتأخير الطلب عم يضيع زبائنك كل يوم!", y, FONT_SUBTITLE, (220, 220, 230), max_width=700)
    
    frame.paste(card, ((WIDTH - card_w) // 2, 670), card)

    # Bottom indicators with safe padding
    draw_pill(draw, "❌ بطء تسجيل الفواتير   •   ❌ تذمر الصالة", 1210, FONT_SMALL, (20, 25, 38, 225), (255, 140, 140))
    return frame.convert("RGB")

def render_scene_2_base(frame_idx):
    """Scene 2: Commission Bleed & Order Chaos (96 - 190 frames / 3.2s - 6.33s)"""
    frame = Image.new("RGBA", (WIDTH, HEIGHT), (14, 10, 16))
    draw = ImageDraw.Draw(frame)

    # Ambient pulsing red circles
    for r in range(400, 100, -50):
        alpha = int(22 * (1 - r / 400))
        draw.ellipse([WIDTH//2 - r, 720 - r, WIDTH//2 + r, 720 + r], fill=(180, 20, 20, alpha))

    # Top Brand Bar
    draw_pill(draw, "نزيف الأرباح في المطاعم 💸", 110, FONT_BADGE, (32, 16, 22, 230), (255, 120, 120), (200, 50, 50, 120))

    # Main Metric Card: -25% Commission (Safe width 840px)
    card_w = 840
    card = create_glass_card(card_w, 550, radius=30, bg_color=(20, 12, 18, 235), border_color=(220, 50, 50, 180))
    c_draw = ImageDraw.Draw(card)
    
    y = 35
    bidi_stat = ar("-25% عمولات")
    bbox = c_draw.textbbox((0, 0), bidi_stat, font=FONT_BIG_STAT)
    tw = bbox[2] - bbox[0]
    c_draw.text(((card_w - tw) // 2, y), bidi_stat, font=FONT_BIG_STAT, fill=(255, 80, 80))
    y += bbox[3] - bbox[1] + 15
    
    y = draw_multiline_text_centered(c_draw, "تطبيقات التوصيل والوسائط", y, FONT_TITLE, (255, 255, 255), max_width=700)
    y += 10
    y = draw_multiline_text_centered(c_draw, "عم تاكل نص ربحك الصافي كل شهر!", y, FONT_SUBTITLE, (255, 200, 160), max_width=700)
    y += 14
    y = draw_multiline_text_centered(c_draw, "خربطة فواتير ورقية  •  تلف الفواتير بالمطبخ", y, FONT_SMALL, (200, 200, 210), max_width=700)
    
    frame.paste(card, ((WIDTH - card_w) // 2, 510), card)

    # Transition Promise Pill
    draw_pill(draw, "💡 مطعمك بيستاهل سيستم أذكى بكتير!", 1170, FONT_TITLE, (35, 25, 15, 240), (255, 215, 0), (212, 175, 55, 180))
    return frame.convert("RGB")

def render_scene_3_base(frame_idx):
    """Scene 3: The Tedallal QR Solution & Instant Scan (191 - 290 frames / 6.33s - 9.66s)"""
    bg = get_ken_burns_bg(IMG_SCENE_3, frame_idx - 191, 100, zoom_start=1.0, zoom_end=1.08)
    frame = apply_dark_gradient(bg, top_opacity=0.68, bottom_opacity=0.90)
    draw = ImageDraw.Draw(frame)

    # Top Brand Bar
    draw_pill(draw, "الحل السحري: منصة تدلّل ⚡", 110, FONT_BADGE, (20, 26, 38, 230), (255, 215, 0), (212, 175, 55, 140))

    # Phone Mockup in center with subtle floating motion
    phone_w, phone_h = 390, 560
    phone_x = (WIDTH - phone_w) // 2
    float_y = int(math.sin((frame_idx - 191) * 0.1) * 5)
    phone_y = 445 + float_y
    
    phone = Image.new("RGBA", (phone_w, phone_h), (0, 0, 0, 0))
    p_draw = ImageDraw.Draw(phone)
    p_draw.rounded_rectangle([0, 0, phone_w, phone_h], radius=44, fill=(12, 16, 26, 245), outline=(212, 175, 55, 210), width=4)
    # Notch
    p_draw.rounded_rectangle([phone_w//2 - 45, 10, phone_w//2 + 45, 26], radius=8, fill=(5, 8, 14))

    # Draw QR code inside phone screen
    if os.path.exists(IMG_QR):
        qr_img = Image.open(IMG_QR).convert("RGBA").resize((250, 250))
        phone.paste(qr_img, ((phone_w - 250)//2, 105), qr_img)
    
    # Scanning laser line moving up and down
    laser_y = int(115 + 230 * (0.5 + 0.5 * math.sin((frame_idx - 191) * 0.16)))
    p_draw.line([(45, laser_y), (phone_w - 45, laser_y)], fill=(255, 220, 0, 255), width=4)
    p_draw.line([(45, laser_y - 2), (phone_w - 45, laser_y - 2)], fill=(255, 215, 0, 130), width=2)
    p_draw.line([(45, laser_y + 2), (phone_w - 45, laser_y + 2)], fill=(255, 215, 0, 130), width=2)

    # Phone screen footer text
    bidi_scan = ar("مسح باركود مباشر")
    p_draw.text(((phone_w - p_draw.textbbox((0, 0), bidi_scan, font=FONT_BADGE)[2]) // 2, 405), bidi_scan, font=FONT_BADGE, fill=(255, 215, 0))
    bidi_sub = ar("منيو فاخر • 0 ثانية انتظار")
    p_draw.text(((phone_w - p_draw.textbbox((0, 0), bidi_sub, font=FONT_SMALL)[2]) // 2, 465), bidi_sub, font=FONT_SMALL, fill=(200, 220, 240))

    frame.paste(phone, (phone_x, phone_y), phone)

    # Headline Glass Card below (Safe width 840px)
    card_w = 840
    card = create_glass_card(card_w, 310, radius=26, bg_color=(12, 16, 26, 235), border_color=(212, 175, 55, 140))
    c_draw = ImageDraw.Draw(card)
    y = 30
    y = draw_multiline_text_centered(c_draw, "زبونك بيمسح الباركود وبيطلب بثواني!", y, FONT_TITLE, (255, 255, 255), max_width=700)
    y += 10
    y = draw_multiline_text_centered(c_draw, "منيو تفاعلي فخم وبدون ما ينزل أي تطبيق!", y, FONT_SUBTITLE, (255, 215, 0), max_width=700)
    y = draw_multiline_text_centered(c_draw, "يدعم كل اللغات والصور والخيارات الإضافية", y, FONT_SMALL, (190, 210, 230), max_width=700)
    frame.paste(card, ((WIDTH - card_w) // 2, 1130), card)

    return frame.convert("RGB")

def render_scene_4_base(frame_idx):
    """Scene 4: Kitchen KDS Automation & 100% Direct Profit (291 - 385 frames / 9.66s - 12.83s)"""
    bg = get_ken_burns_bg(IMG_SCENE_4, frame_idx - 291, 95, zoom_start=1.0, zoom_end=1.09)
    frame = apply_dark_gradient(bg, top_opacity=0.65, bottom_opacity=0.90)
    draw = ImageDraw.Draw(frame)

    # Top Brand Bar
    draw_pill(draw, "أتمتة المطبخ والسرعة الفائقة ⚡", 110, FONT_BADGE, (18, 30, 24, 230), (50, 220, 120), (40, 180, 100, 140))

    # KDS Ticket Card (Safe width 840px)
    card_w = 840
    card = create_glass_card(card_w, 450, radius=28, bg_color=(12, 22, 18, 240), border_color=(50, 200, 120, 180))
    c_draw = ImageDraw.Draw(card)

    # Header of ticket
    c_draw.rounded_rectangle([25, 25, card_w - 25, 90], radius=16, fill=(20, 38, 28))
    bidi_hdr = ar("شاشة المطبخ الذكية (KDS) - طاولة #07")
    tw = c_draw.textbbox((0, 0), bidi_hdr, font=FONT_BADGE)[2]
    c_draw.text(((card_w - tw) // 2, 40), bidi_hdr, font=FONT_BADGE, fill=(80, 240, 140))

    # Order rows
    c_draw.rounded_rectangle([25, 110, card_w - 25, 195], radius=14, fill=(16, 26, 22))
    bidi_row1 = ar("🍔 برغر ترافل فاخر (x2)  •  درجة الاستواء: وسط")
    tw = c_draw.textbbox((0, 0), bidi_row1, font=FONT_SUBTITLE)[2]
    c_draw.text(((card_w - tw) // 2, 130), bidi_row1, font=FONT_SUBTITLE, fill=(255, 255, 255))

    c_draw.rounded_rectangle([25, 210, card_w - 25, 295], radius=14, fill=(16, 26, 22))
    bidi_row2 = ar("🍟 بطاطا مقرمشة بالجبن  •  جاهز للتقديم ✔")
    tw = c_draw.textbbox((0, 0), bidi_row2, font=FONT_SUBTITLE)[2]
    c_draw.text(((card_w - tw) // 2, 230), bidi_row2, font=FONT_SUBTITLE, fill=(120, 240, 160))

    # Sub status
    y = 325
    y = draw_multiline_text_centered(c_draw, "الطلب بيوصل المطبخ بلمح البصر وبلا أي غلط!", y, FONT_TITLE, (255, 215, 0), max_width=700)
    y = draw_multiline_text_centered(c_draw, "ودّع الفواتير الضائعة وخلافات الصالة للأبد", y, FONT_SMALL, (180, 220, 200), max_width=700)

    frame.paste(card, ((WIDTH - card_w) // 2, 520), card)

    # 100% Profit Hero Card (Safe width 840px)
    profit_card = create_glass_card(card_w, 220, radius=24, bg_color=(10, 30, 18, 245), border_color=(60, 220, 120, 200))
    p_draw = ImageDraw.Draw(profit_card)
    y = 32
    y = draw_multiline_text_centered(p_draw, "💰 أرباحك 100% بجيبتك وبلا وسيط!", y, FONT_TITLE, (80, 255, 140), max_width=700)
    y = draw_multiline_text_centered(p_draw, "صفر عمولات • تحصيل فوري مباشر لحسابك", y, FONT_SUBTITLE, (255, 255, 255), max_width=700)
    frame.paste(profit_card, ((WIDTH - card_w) // 2, 1070), profit_card)

    return frame.convert("RGB")

def render_scene_5_base(frame_idx):
    """Scene 5: Grand Finale with 100% Clean Cutout Mobile Splash Golden Logo (386 - 480 frames / 12.83s - 16.0s)"""
    frame = Image.new("RGBA", (WIDTH, HEIGHT), (8, 11, 18))
    draw = ImageDraw.Draw(frame)

    # Floating golden bokeh / particles with soft depth of field
    rel_frame = frame_idx - 386
    for i in range(24):
        bx = int((i * 137 + rel_frame * 3) % WIDTH)
        by = int((i * 251 - rel_frame * 2) % HEIGHT)
        br = int(4 + 6 * abs(math.sin(i)))
        alpha = int(45 + 20 * math.sin(i + rel_frame * 0.1))
        draw.ellipse([bx, by, bx + br, by + br], fill=(212, 175, 55, alpha))

    # Mobile Splash Reveal Animation: Scale up smoothly & fade in
    reveal_prog = min(1.0, rel_frame / 22.0)
    scale = 0.85 + 0.15 * reveal_prog
    alpha_factor = reveal_prog

    # Golden Logo Cutout with soft Gaussian Blur Halo
    if os.path.exists(IMG_GOLD_LOGO):
        gold_raw = Image.open(IMG_GOLD_LOGO).convert("RGBA")
        gw, gh = gold_raw.size
        target_w = int(470 * scale)
        target_h = int(target_w * (gh / gw))
        gold_resized = gold_raw.resize((target_w, target_h), Image.Resampling.LANCZOS)
        
        # Soft gaussian aura behind logo (NO solid circle/disc!)
        glow_size = (target_w + 120, target_h + 120)
        glow_mask = Image.new("RGBA", glow_size, (0, 0, 0, 0))
        glow_alpha = gold_resized.split()[3]
        glow_color = Image.new("RGBA", (target_w, target_h), (212, 175, 55, int(150 * alpha_factor)))
        glow_mask.paste(glow_color, (60, 60), glow_alpha)
        glow_blurred = glow_mask.filter(ImageFilter.GaussianBlur(28))

        # Apply alpha fade on the pure logo
        if alpha_factor < 1.0:
            r, g, b, a = gold_resized.split()
            a = a.point(lambda p: int(p * alpha_factor))
            gold_resized = Image.merge("RGBA", (r, g, b, a))

        logo_x = (WIDTH - target_w) // 2
        logo_y = 470 - target_h // 2
        
        frame.paste(glow_blurred, (logo_x - 60, logo_y - 60), glow_blurred)
        frame.paste(gold_resized, (logo_x, logo_y), gold_resized)
    else:
        draw_multiline_text_centered(draw, "تدلّل", 450, FONT_BIG_STAT, (255, 215, 0))

    # Main Brand Titles below logo with safe wrapping
    y = 690
    y = draw_multiline_text_centered(draw, "تدلّل | المنظومة السحابية الذكية", y, FONT_BRAND, (255, 215, 0), max_width=720)
    y += 8
    y = draw_multiline_text_centered(draw, "لإدارة ونمو المطاعم والكافيهات", y, FONT_SUBTITLE, (240, 240, 245), max_width=720)

    # CTA Card (Safe width 840px)
    card_w = 840
    cta = create_glass_card(card_w, 420, radius=30, bg_color=(15, 22, 34, 240), border_color=(212, 175, 55, 180))
    c_draw = ImageDraw.Draw(cta)
    
    cy = 40
    bidi_cta_title = ar("ابدأ اليوم وضاعف أرباح مطعمك!")
    tw = c_draw.textbbox((0, 0), bidi_cta_title, font=FONT_TITLE)[2]
    c_draw.text(((card_w - tw) // 2, cy), bidi_cta_title, font=FONT_TITLE, fill=(255, 255, 255))
    cy += 75

    # WhatsApp Button (Width 680px inside card)
    btn_w = 680
    btn_x = (card_w - btn_w) // 2
    c_draw.rounded_rectangle([btn_x, cy, btn_x + btn_w, cy + 105], radius=52, fill=(37, 211, 102), outline=(255, 255, 255), width=2)
    bidi_wa = ar("💬 تواصل عبر واتساب: 963934578117+")
    tw = c_draw.textbbox((0, 0), bidi_wa, font=FONT_BADGE)[2]
    c_draw.text(((card_w - tw) // 2, cy + 28), bidi_wa, font=FONT_BADGE, fill=(255, 255, 255))
    cy += 140

    # Domain pill
    dom_w = 520
    dom_x = (card_w - dom_w) // 2
    c_draw.rounded_rectangle([dom_x, cy, dom_x + dom_w, cy + 65], radius=24, fill=(22, 30, 46), outline=(212, 175, 55, 120), width=1)
    bidi_dom = ar("الموقع الرسمي:  tedallal.tech")
    tw = c_draw.textbbox((0, 0), bidi_dom, font=FONT_SMALL)[2]
    c_draw.text(((card_w - tw) // 2, cy + 16), bidi_dom, font=FONT_SMALL, fill=(212, 175, 55))

    frame.paste(cta, ((WIDTH - card_w) // 2, 1060), cta)

    return frame.convert("RGB")

# ----------------- SEAMLESS CROSSFADE ROUTER -----------------

def render_frame_with_crossfade(frame_idx):
    """
    Render frame with smooth 10-frame studio crossfades at every scene boundary:
    - 86..95: Scene 1 blends into Scene 2
    - 181..190: Scene 2 blends into Scene 3
    - 281..290: Scene 3 blends into Scene 4
    - 376..385: Scene 4 blends into Scene 5
    """
    fade_len = 10
    
    # Boundary 1: Scene 1 -> Scene 2 (Frame 96)
    if 96 - fade_len <= frame_idx < 96:
        alpha = (frame_idx - (96 - fade_len)) / float(fade_len)
        f1 = render_scene_1_base(frame_idx)
        f2 = render_scene_2_base(96)
        return Image.blend(f1, f2, alpha)
    elif frame_idx < 96:
        return render_scene_1_base(frame_idx)
        
    # Boundary 2: Scene 2 -> Scene 3 (Frame 191)
    elif 191 - fade_len <= frame_idx < 191:
        alpha = (frame_idx - (191 - fade_len)) / float(fade_len)
        f2 = render_scene_2_base(frame_idx)
        f3 = render_scene_3_base(191)
        return Image.blend(f2, f3, alpha)
    elif frame_idx < 191:
        return render_scene_2_base(frame_idx)
        
    # Boundary 3: Scene 3 -> Scene 4 (Frame 291)
    elif 291 - fade_len <= frame_idx < 291:
        alpha = (frame_idx - (291 - fade_len)) / float(fade_len)
        f3 = render_scene_3_base(frame_idx)
        f4 = render_scene_4_base(291)
        return Image.blend(f3, f4, alpha)
    elif frame_idx < 291:
        return render_scene_3_base(frame_idx)
        
    # Boundary 4: Scene 4 -> Scene 5 (Frame 386)
    elif 386 - fade_len <= frame_idx < 386:
        alpha = (frame_idx - (386 - fade_len)) / float(fade_len)
        f4 = render_scene_4_base(frame_idx)
        f5 = render_scene_5_base(386)
        return Image.blend(f4, f5, alpha)
    else:
        return render_scene_5_base(frame_idx)

# ----------------- STUDIO-GRADE MULTI-TRACK AUDIO GENERATOR -----------------

def generate_studio_audio(duration_sec, output_wav):
    """
    Generate a broadcast commercial studio soundtrack with scene-synchronized beats and harmonic chords.
    """
    sample_rate = 44100
    n_samples = int(duration_sec * sample_rate)
    frames = bytearray()
    
    random.seed(42)
    noise_table = [random.uniform(-1, 1) for _ in range(44100)]
    def get_noise(idx):
        return noise_table[idx % 44100]

    bpm = 120.0
    spb = 60.0 / bpm  # 0.5s per beat

    for i in range(n_samples):
        t = i / sample_rate
        t_in_beat = t % spb

        # 1. STUDIO KICK DRUM (From 6.33s through 14.0s)
        kick = 0.0
        if 6.33 <= t < 14.0:
            k_phase = t_in_beat / spb
            k_freq = 150.0 * math.exp(-k_phase * 18) + 45.0
            k_body = math.sin(2 * math.pi * k_freq * k_phase) * math.exp(-k_phase * 6.5)
            k_click = get_noise(i) * math.exp(-k_phase * 60) * 0.22
            kick = (k_body * 0.8 + k_click) * 0.65

        # 2. HI-HATS / SHAKER (16th notes from 6.33s to 14.0s)
        hat = 0.0
        if 6.33 <= t < 14.0:
            t_16 = t % 0.125
            hat = get_noise(i) * math.exp(-t_16 * 40.0) * 0.08
            if (int(t / 0.125) % 2) == 1:
                hat *= 1.4

        # 3. HARMONIC CHORDS & SCENE-SPECIFIC SFX
        chord_l = 0.0
        chord_r = 0.0

        # SCENE 1 (0.0s - 3.2s): Low Atmospheric D minor & Tension Heartbeat
        if t < 3.2:
            boom = math.sin(2 * math.pi * (80.0 * math.exp(-t * 2.0) + 38.0) * t) * math.exp(-t * 1.2) * 0.55
            pad = 0.10 * math.sin(2 * math.pi * 73.4 * t) + 0.08 * math.sin(2 * math.pi * 110.0 * t) + 0.06 * math.sin(2 * math.pi * 174.6 * t)
            hb_t = t % 0.6
            hb = math.sin(2 * math.pi * 60 * hb_t) * math.exp(-hb_t * 12) * 0.35
            chord_l = boom + pad + hb
            chord_r = boom + pad * 0.9 + hb * 0.8

        # SCENE 2 (3.2s - 6.33s): Tension Transition & Warning Drops
        elif t < 6.33:
            t_rel = t - 3.2
            hit = math.sin(2 * math.pi * 140 * math.exp(-t_rel * 4.0) * t_rel) * math.exp(-t_rel * 2.5) * 0.6
            whoosh = get_noise(i) * math.exp(-t_rel * 8.0) * 0.15
            chime = 0.08 * math.sin(2 * math.pi * 277.18 * t) * math.exp(-(t_rel % 0.8) * 4)
            sweep_f = max(40.0, 180.0 - t_rel * 35.0)
            bass = 0.12 * math.sin(2 * math.pi * sweep_f * t)
            chord_l = hit + whoosh + chime + bass
            chord_r = hit * 0.95 + whoosh * 1.1 + chime * 1.2 + bass

        # SCENE 3 (6.33s - 9.66s): Modern Upbeat Tech (C Major / G Major) & Laser Scan
        elif t < 9.66:
            t_rel = t - 6.33
            laser = 0.0
            if 0.45 <= t_rel <= 0.85:
                l_prog = (t_rel - 0.45) / 0.40
                l_f = 900.0 + 900.0 * math.sin(l_prog * math.pi * 0.5)
                laser = 0.30 * math.sin(2 * math.pi * l_f * l_prog) * math.sin(l_prog * math.pi)
            if t_rel < 1.75:
                c1 = 0.08 * math.sin(2 * math.pi * 261.63 * t)
                c2 = 0.08 * math.sin(2 * math.pi * 329.63 * t)
                c3 = 0.08 * math.sin(2 * math.pi * 392.00 * t)
                c4 = 0.06 * math.sin(2 * math.pi * 523.25 * t)
            else:
                c1 = 0.08 * math.sin(2 * math.pi * 196.00 * t)
                c2 = 0.08 * math.sin(2 * math.pi * 246.94 * t)
                c3 = 0.08 * math.sin(2 * math.pi * 293.66 * t)
                c4 = 0.06 * math.sin(2 * math.pi * 392.00 * t)
            bass = 0.18 * math.sin(2 * math.pi * 65.41 * t)
            chord_l = c1 + c2 + c3 + c4 + bass + laser
            chord_r = c1 * 0.9 + c2 * 1.1 + c3 + c4 * 1.05 + bass + laser * 0.8

        # SCENE 4 (9.66s - 12.83s): High Energy & Kitchen Bell
        elif t < 12.83:
            t_rel = t - 9.66
            bell = (0.35 * math.sin(2 * math.pi * 1174.66 * t_rel) +
                    0.15 * math.sin(2 * math.pi * 2349.32 * t_rel) +
                    0.08 * math.sin(2 * math.pi * 3523.98 * t_rel)) * math.exp(-t_rel * 2.2)
            arp_freqs = [293.66, 369.99, 440.00, 587.33]
            note = arp_freqs[int((t_rel * 8) % 4)]
            arp = 0.10 * math.sin(2 * math.pi * note * t) * math.exp(-(t_rel % 0.125) * 14)
            bass = 0.18 * math.sin(2 * math.pi * 73.42 * t)
            chord_l = bell + arp + bass
            chord_r = bell * 0.9 + arp * 1.1 + bass

        # SCENE 5 (12.83s - 16.0s): Grand Golden Finale
        else:
            t_rel = t - 12.83
            f1 = 0.16 * math.sin(2 * math.pi * 293.66 * t)
            f2 = 0.14 * math.sin(2 * math.pi * 369.99 * t)
            f3 = 0.14 * math.sin(2 * math.pi * 440.00 * t)
            f4 = 0.12 * math.sin(2 * math.pi * 554.37 * t)
            f5 = 0.10 * math.sin(2 * math.pi * 659.25 * t)
            fanfare = (f1 + f2 + f3 + f4 + f5) * (0.85 + 0.15 * math.sin(2 * math.pi * 1.5 * t_rel))
            sub = 0.22 * math.sin(2 * math.pi * 73.42 * t) * math.exp(-t_rel * 0.4)
            sparkle = 0.04 * math.sin(2 * math.pi * (4500 + 1500 * math.sin(t * 12)) * t) * math.exp(-t_rel * 0.6)
            fade = 1.0
            if t > 14.8:
                fade = max(0.0, (16.0 - t) / 1.2)
            chord_l = (fanfare + sub + sparkle) * fade
            chord_r = (fanfare * 1.05 + sub + sparkle * 0.8) * fade

        sample_l = math.tanh((kick + hat + chord_l) * 1.15) * 0.88
        sample_r = math.tanh((kick + hat + chord_r) * 1.15) * 0.88
        
        frames.extend(struct.pack('<hh', int(sample_l * 32767), int(sample_r * 32767)))

    with wave.open(output_wav, 'wb') as wav:
        wav.setnchannels(2)
        wav.setsampwidth(2)
        wav.setframerate(sample_rate)
        wav.writeframes(frames)

# ----------------- MAIN PIPELINE -----------------

def main():
    print(f"🎬 Starting Studio-Grade Tedallal Promo Video Generation ({WIDTH}x{HEIGHT} @ {FPS}fps)...")
    
    # 1. Render Video Frames with Crossfades
    writer = imageio.get_writer(
        TEMP_VIDEO_ONLY,
        fps=FPS,
        codec="libx264",
        pixelformat="yuv420p",
        quality=9,
        macro_block_size=None
    )

    for i in range(TOTAL_FRAMES):
        frame = render_frame_with_crossfade(i)
        writer.append_data(np.array(frame))
        if i % 60 == 0:
            print(f"   Rendering: {i}/{TOTAL_FRAMES} frames ({int(i/TOTAL_FRAMES*100)}%)...")

    writer.close()
    print("✅ All video frames rendered with seamless studio crossfades!")

    # 2. Generate Studio Audio Track
    duration = TOTAL_FRAMES / FPS
    print(f"🎵 Generating studio-grade audio track ({duration}s)...")
    generate_studio_audio(duration, TEMP_AUDIO)

    # 3. Merge Video + Audio using FFmpeg
    print("🎞️ Multiplexing video and audio into final MP4...")
    ffmpeg_exe = imageio_ffmpeg.get_ffmpeg_exe()
    cmd = [
        ffmpeg_exe, "-y",
        "-i", TEMP_VIDEO_ONLY,
        "-i", TEMP_AUDIO,
        "-c:v", "copy",
        "-c:a", "aac",
        "-b:a", "192k",
        "-shortest",
        OUTPUT_MP4
    ]
    subprocess.run(cmd, check=True)

    # Clean up temp files
    if os.path.exists(TEMP_VIDEO_ONLY):
        os.remove(TEMP_VIDEO_ONLY)
    if os.path.exists(TEMP_AUDIO):
        os.remove(TEMP_AUDIO)

    # Copy to Artifact directory as well
    artifact_dir = "C:/Users/ASUS/.gemini/antigravity/brain/8b817e4d-e32a-49c8-a0f9-9f3b7546d63d"
    if os.path.exists(artifact_dir):
        artifact_dest = os.path.join(artifact_dir, "tedallal_promo_video.mp4")
        import shutil
        shutil.copy2(OUTPUT_MP4, artifact_dest)
        print(f"📦 Copied to artifact directory: {artifact_dest}")

    file_size_mb = os.path.getsize(OUTPUT_MP4) / (1024 * 1024)
    print(f"🎉 Studio Promo Video successfully generated at: {OUTPUT_MP4} ({file_size_mb:.2f} MB)")

if __name__ == "__main__":
    main()
