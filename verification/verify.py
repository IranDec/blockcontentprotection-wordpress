
from playwright.sync_api import sync_playwright
import os

def run():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Get the absolute path to the test file
        test_html_path = "file://" + os.path.abspath("test.html")

        # Verify test.html
        page.goto(test_html_path)
        page.screenshot(path="/home/jules/verification/media_protection.png")

        browser.close()

if __name__ == "__main__":
    run()
