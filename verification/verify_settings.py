from playwright.sync_api import sync_playwright

def take_screenshot():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()
        page.goto("file:///home/jules/verification/settings.html")
        page.screenshot(path="/home/jules/verification/settings.png")
        browser.close()

if __name__ == "__main__":
    take_screenshot()
