#!/usr/bin/env python3
"""画面を 390/320 幅で撮って、横のはみ出しを測る。 /usr/bin/python3 scripts/shot.py <base URL> <out dir>"""
import sys, os
from playwright.sync_api import sync_playwright
base, out = sys.argv[1].rstrip('/'), sys.argv[2]
os.makedirs(out, exist_ok=True)
with sync_playwright() as p:
    b = p.chromium.launch()
    for w in (390, 320):
        ctx = b.new_context(viewport={"width": w, "height": 900}); pg = ctx.new_page()
        pg.goto(base + "/kanketo.php?s=t1"); pg.click("label[for=q_overall_4]"); pg.click("label[for=q_food_5]")
        pg.click("label[for=q_again_0]"); pg.fill("textarea[name=comment]", "パスタはおいしかったけど、料理が出てくるまで30分くらい待った")
        pg.screenshot(path=f"{out}/survey-{w}.png", full_page=True)
        print(w, "survey", pg.evaluate("document.documentElement.scrollWidth-innerWidth"))
        pg.click("button[type=submit]"); pg.wait_for_load_state()
        if pg.query_selector("#dbtn"):
            pg.click("#dbtn"); pg.wait_for_selector("#dbox", state="visible", timeout=90000)
        pg.screenshot(path=f"{out}/thanks-{w}.png", full_page=True)
        print(w, "thanks", pg.evaluate("document.documentElement.scrollWidth-innerWidth"))
        pg.goto(base + "/kanketo.php?p=admin"); pg.fill("input[name=password]", "demo"); pg.click("button")
        pg.screenshot(path=f"{out}/admin-{w}.png", full_page=True)
        print(w, "admin", pg.evaluate("document.documentElement.scrollWidth-innerWidth"))
        pg.goto(base + "/kanketo.php?p=qr"); pg.wait_for_timeout(1500)
        pg.screenshot(path=f"{out}/qr-{w}.png", full_page=True)
        print(w, "qr", pg.evaluate("document.documentElement.scrollWidth-innerWidth"))
        ctx.close()
    b.close()
