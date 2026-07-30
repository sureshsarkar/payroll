"""MBS GURU premium OG banner v2 — 1200x630 JPG.

Modern aurora-mesh dark design.  No cheesy dashboard mockup.
"""
from PIL import Image, ImageDraw, ImageFont, ImageFilter
from pathlib import Path
import random

W, H = 1200, 630

INK = (4, 7, 26)
NAVY = (9, 16, 46)
PURPLE = (96, 89, 247)
PURPLE_L = (148, 140, 255)
BLUE = (59, 130, 246)
TEAL = (34, 211, 238)
PINK = (236, 72, 153)
WHITE = (255, 255, 255)
OFFWHITE = (228, 232, 245)
MUTED = (148, 163, 199)
DIM = (108, 122, 158)
GREEN = (16, 185, 129)

FONTS = "C:/Windows/Fonts/"
F_BOLD = FONTS + "segoeuib.ttf"
F_SEMI = FONTS + "seguisb.ttf"
F_REG = FONTS + "segoeui.ttf"
F_LIGHT = FONTS + "segoeuil.ttf"


def font(p, s):
    return ImageFont.truetype(p, s)


def text_w(d, t, f):
    b = d.textbbox((0, 0), t, font=f)
    return b[2] - b[0], b[3] - b[1]


def vgradient(w, h, top, bot):
    img = Image.new("RGB", (w, h), top)
    px = img.load()
    for y in range(h):
        t = y / (h - 1)
        row = (int(top[0] + (bot[0] - top[0]) * t),
               int(top[1] + (bot[1] - top[1]) * t),
               int(top[2] + (bot[2] - top[2]) * t))
        for x in range(w):
            px[x, y] = row
    return img.convert("RGBA")


def aurora_orb(img, cx, cy, r, color, alpha=200, blur=90):
    layer = Image.new("RGBA", img.size, (0, 0, 0, 0))
    ld = ImageDraw.Draw(layer)
    ld.ellipse((cx - r, cy - r, cx + r, cy + r), fill=(*color, alpha))
    layer = layer.filter(ImageFilter.GaussianBlur(blur))
    img.alpha_composite(layer)


def grain(img, intensity=10, density=30):
    random.seed(7)
    noise = Image.new("RGBA", img.size, (0, 0, 0, 0))
    nd = ImageDraw.Draw(noise)
    for _ in range(img.size[0] * img.size[1] // density):
        x = random.randint(0, img.size[0] - 1)
        y = random.randint(0, img.size[1] - 1)
        a = random.randint(0, intensity)
        c = random.choice([(255, 255, 255, a), (0, 0, 0, a)])
        nd.point((x, y), fill=c)
    img.alpha_composite(noise)


def logo_mark(img, cx, cy, r):
    glow = Image.new("RGBA", img.size, (0, 0, 0, 0))
    gd = ImageDraw.Draw(glow)
    gd.ellipse((cx - r - 18, cy - r - 18, cx + r + 18, cy + r + 18),
               fill=(*PURPLE, 110))
    glow = glow.filter(ImageFilter.GaussianBlur(20))
    img.alpha_composite(glow)
    d = ImageDraw.Draw(img, "RGBA")
    d.ellipse((cx - r, cy - r, cx + r, cy + r), fill=(*PURPLE, 255))
    d.ellipse((cx - r + 3, cy - r + 3, cx + r - 3, cy + r - 3),
              outline=(255, 255, 255, 70), width=2)
    # white G-mark center
    d.ellipse((cx - 10, cy - 10, cx + 10, cy + 10), fill=WHITE)
    d.ellipse((cx - 4, cy - 4, cx + 4, cy + 4), fill=PURPLE)


def glass_card(img, x, y, w, h, radius=18, fill_a=22, border_a=70):
    card = Image.new("RGBA", img.size, (0, 0, 0, 0))
    cd = ImageDraw.Draw(card)
    cd.rounded_rectangle((x, y, x + w, y + h), radius=radius,
                         fill=(255, 255, 255, fill_a),
                         outline=(255, 255, 255, border_a), width=1)
    img.alpha_composite(card)


def soft_shadow(img, x, y, w, h, radius=18, blur=22, opacity=140):
    s = Image.new("RGBA", img.size, (0, 0, 0, 0))
    sd = ImageDraw.Draw(s)
    sd.rounded_rectangle((x, y, x + w, y + h), radius=radius,
                         fill=(0, 0, 0, opacity))
    s = s.filter(ImageFilter.GaussianBlur(blur))
    img.alpha_composite(s)


def build():
    img = vgradient(W, H, INK, NAVY)

    # Aurora mesh — overlapping blurred orbs
    aurora_orb(img, 1080, 60, 320, PURPLE, 210, 100)
    aurora_orb(img, 1180, 360, 280, BLUE, 170, 110)
    aurora_orb(img, 880, 640, 320, PINK, 110, 130)
    aurora_orb(img, -60, -20, 240, BLUE, 140, 100)
    aurora_orb(img, -30, 680, 240, PURPLE, 130, 110)
    aurora_orb(img, 560, -120, 260, TEAL, 70, 120)

    grain(img, intensity=8, density=45)

    d = ImageDraw.Draw(img, "RGBA")
    PAD_L = 80

    # ---------- LOGO LOCKUP ----------
    logo_mark(img, PAD_L + 28, 88, 28)
    d = ImageDraw.Draw(img, "RGBA")
    f_brand = font(F_BOLD, 28)
    d.text((PAD_L + 70, 76), "MBS", font=f_brand, fill=WHITE)
    bw, _ = text_w(d, "MBS", f_brand)
    d.text((PAD_L + 70 + bw + 10, 76), "GURU", font=f_brand, fill=PURPLE_L)

    # Tiny eyebrow
    f_eye = font(F_SEMI, 10)
    d.text((PAD_L + 70, 110), "E D U C A T O R   P L A T F O R M",
           font=f_eye, fill=DIM)

    # ---------- HEADLINE ----------
    f_h = font(F_BOLD, 78)
    line_h = 86
    y = 218
    d.text((PAD_L, y), "Build your", font=f_h, fill=WHITE)
    y += line_h
    d.text((PAD_L, y), "teaching business.", font=f_h, fill=PURPLE_L)

    # ---------- SUBLINE ----------
    f_sub = font(F_REG, 21)
    d.text((PAD_L, 412),
           "Courses, live classes, payments & analytics —",
           font=f_sub, fill=OFFWHITE)
    d.text((PAD_L, 442),
           "one platform, built for modern educators.",
           font=f_sub, fill=MUTED)

    # ---------- BOTTOM BAR ----------
    bar_y = 555
    # Hairline divider
    line = Image.new("RGBA", img.size, (0, 0, 0, 0))
    ld = ImageDraw.Draw(line)
    ld.line((PAD_L, bar_y - 18, W - PAD_L, bar_y - 18),
            fill=(255, 255, 255, 35), width=1)
    img.alpha_composite(line)

    f_label = font(F_SEMI, 10)
    f_val = font(F_BOLD, 15)

    # Left cluster: trust
    d.text((PAD_L, bar_y), "T R U S T E D   B Y",
           font=f_label, fill=DIM)
    d.text((PAD_L, bar_y + 18),
           "50,000+ educators worldwide",
           font=f_val, fill=WHITE)

    # Center cluster: features (3 bullets)
    feats = ["Live classes", "Payments", "Analytics"]
    cx = 480
    for i, ft in enumerate(feats):
        x = cx + i * 130
        d.ellipse((x, bar_y + 24, x + 6, bar_y + 30), fill=PURPLE_L)
        d.text((x + 14, bar_y + 18), ft, font=font(F_SEMI, 13), fill=OFFWHITE)

    # Right cluster: URL
    f_url = font(F_BOLD, 15)
    url = "mbsguru.com"
    uw, _ = text_w(d, url, f_url)
    d.text((W - PAD_L - uw, bar_y),
           "V I S I T", font=f_label, fill=DIM)
    d.text((W - PAD_L - uw, bar_y + 18),
           url, font=f_url, fill=PURPLE_L)

    # ---------- RIGHT: floating glass course-card stack ----------
    cx0 = 780
    # Back card (most blurred glass)
    glass_card(img, cx0 + 60, 168, 320, 90, 18, 18, 55)
    # Middle card
    glass_card(img, cx0 + 30, 222, 320, 90, 18, 28, 80)

    # Front card — solid frosted with content
    fx, fy, fw, fh = cx0, 278, 340, 188
    soft_shadow(img, fx + 4, fy + 16, fw, fh, 22, 28, 130)
    front = Image.new("RGBA", img.size, (0, 0, 0, 0))
    fd = ImageDraw.Draw(front)
    # Subtle gradient-ish body using two stacked colored rounds: dark frosted
    fd.rounded_rectangle((fx, fy, fx + fw, fy + fh), radius=20,
                         fill=(18, 22, 50, 240),
                         outline=(255, 255, 255, 55), width=1)
    img.alpha_composite(front)
    d = ImageDraw.Draw(img, "RGBA")

    # Thumbnail block (gradient simulated by two colors layered)
    thx, thy, thw, thh = fx + 20, fy + 20, 84, 84
    thumb = Image.new("RGBA", img.size, (0, 0, 0, 0))
    td = ImageDraw.Draw(thumb)
    td.rounded_rectangle((thx, thy, thx + thw, thy + thh), radius=14,
                         fill=PURPLE)
    img.alpha_composite(thumb)
    # Diagonal accent
    accent = Image.new("RGBA", img.size, (0, 0, 0, 0))
    ad = ImageDraw.Draw(accent)
    ad.polygon([(thx, thy + thh), (thx + thw, thy + thh),
                (thx + thw, thy + thh - 38)],
               fill=(255, 255, 255, 35))
    img.alpha_composite(accent)
    d = ImageDraw.Draw(img, "RGBA")
    # Play triangle
    px, py = thx + thw // 2, thy + thh // 2
    d.polygon([(px - 8, py - 11), (px - 8, py + 11), (px + 12, py)], fill=WHITE)

    # Course meta
    d.text((thx + thw + 18, thy + 4),
           "FEATURED COURSE",
           font=font(F_SEMI, 10), fill=PURPLE_L)
    d.text((thx + thw + 18, thy + 20),
           "Public Speaking Mastery",
           font=font(F_BOLD, 18), fill=WHITE)
    d.text((thx + thw + 18, thy + 46),
           "12 modules · 8h 30m",
           font=font(F_REG, 13), fill=MUTED)

    # Live badge top-right
    lx, ly = fx + fw - 78, fy + 22
    d.rounded_rectangle((lx, ly, lx + 58, ly + 22), radius=11,
                        fill=(16, 185, 129, 255))
    d.ellipse((lx + 9, ly + 8, lx + 15, ly + 14), fill=WHITE)
    d.text((lx + 19, ly + 4), "LIVE", font=font(F_BOLD, 11), fill=WHITE)

    # Divider
    d.line((fx + 20, fy + 122, fx + fw - 20, fy + 122),
           fill=(255, 255, 255, 30), width=1)

    # Bottom stats row inside card
    stats = [("4.9", "Rating"), ("12k", "Students"), ("₹2,499", "Price")]
    sx = fx + 20
    f_seg_emoji = FONTS + "seguisym.ttf"
    for i, (val, lbl) in enumerate(stats):
        x = sx + i * 105
        d.text((x, fy + 138), val, font=font(F_BOLD, 18), fill=WHITE)
        if i == 0:
            vw, _ = text_w(d, val, font(F_BOLD, 18))
            try:
                d.text((x + vw + 4, fy + 140),
                       "★", font=font(f_seg_emoji, 16), fill=(245, 158, 11))
            except OSError:
                d.text((x + vw + 4, fy + 140),
                       "*", font=font(F_BOLD, 18), fill=(245, 158, 11))
        d.text((x, fy + 162), lbl, font=font(F_SEMI, 11), fill=MUTED)

    return img


def main():
    out_dir = Path("public/uploads/website-images")
    out_dir.mkdir(parents=True, exist_ok=True)
    img = build()
    out = out_dir / "og-banner.jpg"
    img.convert("RGB").save(out, "JPEG", quality=94,
                            optimize=True, progressive=True)
    print(f"OK: {out}  ({out.stat().st_size // 1024} KB)")


if __name__ == "__main__":
    main()
