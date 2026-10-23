#!/usr/bin/env python3
"""Kurage お客様アンケート の出品・紹介用の画像（1200×630）を作る。
  /usr/bin/python3 scripts/make_ogp.py  → outputs/kanketo-ogp.png
"""
import os
from PIL import Image, ImageDraw, ImageFont

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
FB = "/usr/share/fonts/opentype/noto/NotoSansCJK-Black.ttc"
FM = "/usr/share/fonts/opentype/noto/NotoSansCJK-Bold.ttc"
MASCOT = "/home/kojima/work/kurage_web/images/kurage-mascot-cutout.png"
W, H = 1200, 630
im = Image.new("RGB", (W, H), "#f3faf9")
d = ImageDraw.Draw(im)
d.rectangle([0, 0, 18, H], fill="#0a9a8f")
m = Image.open(MASCOT).convert("RGBA")
m.thumbnail((300, 300))   # 縦横比を保つ（マスコットを変形させない）
im.paste(m, (W - m.width - 70, H - m.height - 90), m)
d.text((80, 70), "Kurage", font=ImageFont.truetype(FB, 44), fill="#076f67")
d.text((80, 128), "お客様アンケート", font=ImageFont.truetype(FB, 84), fill="#1d3038")
d.rounded_rectangle([80, 250, 390, 300], radius=25, fill="#e2f6f3", outline="#0a9a8f", width=3)
d.text((104, 254), "QRで1分・4か国語", font=ImageFont.truetype(FM, 30), fill="#076f67")
f = ImageFont.truetype(FM, 34)
for i, t in enumerate(["飲食店・美容室・クリニック・宿の", "来店アンケート。低い評価はすぐメールで、", "Googleへの案内は全員に同じように。"]):
    d.text((80, 340 + i * 54), t, font=f, fill="#3f5259")
d.text((80, H - 70), "株式会社エクスブリッジ", font=ImageFont.truetype(FM, 24), fill="#5f7078")
out = os.path.join(ROOT, "outputs", "kanketo-ogp.png")
im.save(out)
print(out)
