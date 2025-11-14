import asyncio
from playwright.async_api import async_playwright
import http.server
import socketserver
import threading
import os
import time

PORT = 8000
SCREENSHOT_PATH = "/home/jules/verification/automatic_protection_verification.png"
TEST_URL = f"http://localhost:{PORT}/test.html"

class Handler(http.server.SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=os.getcwd(), **kwargs)

def run_server():
    with socketserver.TCPServer(("", PORT), Handler) as httpd:
        httpd.allow_reuse_address = True
        print(f"Serving at port {PORT}")
        httpd.serve_forever()

async def verify_automatic_protection():
    server_thread = threading.Thread(target=run_server, daemon=True)
    server_thread.start()
    time.sleep(1)

    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True)
        page = await browser.new_page()

        try:
            await page.goto(TEST_URL, wait_until="networkidle")
            video_wrapper = page.locator(".bcp-watermark-wrapper")
            await video_wrapper.wait_for(state="visible", timeout=10000)

            # Get the video's src attribute
            video_src = await page.eval_on_selector("video", "el => el.src")
            print(f"Video src: {video_src}")

            # Verify the JS did NOT convert the tokenized URL to a blob
            if video_src.startswith('blob:'):
                raise Exception("Verification failed: Video source was incorrectly converted to a blob URL.")

            print("Verification successful: JS correctly skipped blob conversion for the tokenized URL.")

            await video_wrapper.hover()
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
    await verify_automatic_protection()

if __name__ == "__main__":
    asyncio.run(main())
