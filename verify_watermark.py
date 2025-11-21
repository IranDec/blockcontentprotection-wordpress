import asyncio
from playwright.async_api import async_playwright, expect
import http.server
import socketserver
import threading
import os
import time
import re

PORT = 8000
SCREENSHOT_PATH = "/home/jules/verification/screen_blackout_verification.png"
TEST_URL = f"http://localhost:{PORT}/test.html"

class Handler(http.server.SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=os.getcwd(), **kwargs)

def run_server():
    with socketserver.TCPServer(("", PORT), Handler) as httpd:
        httpd.allow_reuse_address = True
        print(f"Serving at port {PORT}")
        httpd.serve_forever()

async def verify_screen_blackout():
    server_thread = threading.Thread(target=run_server, daemon=True)
    server_thread.start()
    time.sleep(1)

    async with async_playwright() as p:
        browser = await p.chromium.launch(
            headless=True,
            args=[
                '--use-fake-device-for-media-stream',
                '--use-fake-ui-for-media-stream'
            ]
        )
        page = await browser.new_page()

        try:
            await page.goto(TEST_URL, wait_until="networkidle")
            video_wrapper = page.locator(".bcp-watermark-wrapper")
            await expect(video_wrapper).to_be_visible(timeout=10000)

            # Simulate starting screen recording
            await page.evaluate('navigator.mediaDevices.getDisplayMedia()')

            # Use a regex to check for the class name
            await expect(video_wrapper).to_have_class(re.compile(r'\bbcp-recording-detected\b'), timeout=5000)

            print("Verification successful: .bcp-recording-detected class was applied.")

            os.makedirs(os.path.dirname(SCREENSHOT_PATH), exist_ok=True)
            await page.screenshot(path=SCREENSHOT_PATH)
            print(f"Screenshot saved to {SCREENSHOT_PATH}")

        except Exception as e:
            print(f"An error occurred during verification: {e}")
            await page.screenshot(path="/home/jules/verification/verification_error.png")
            raise
        finally:
            await browser.close()

async def main():
    await verify_screen_blackout()

if __name__ == "__main__":
    asyncio.run(main())
