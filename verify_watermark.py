from playwright.sync_api import sync_playwright

def verify_watermark():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Navigate to the local test file
        page.goto("http://localhost:8000/test.html")

        # Wait for the video to be ready and hover over it
        video_wrapper = page.locator(".bcp-watermark-wrapper")
        video_wrapper.wait_for(state="visible")
        video_wrapper.hover()

        # Take a screenshot
        page.screenshot(path="/home/jules/verification/watermark_verification.png")

        browser.close()

if __name__ == "__main__":
    verify_watermark()
