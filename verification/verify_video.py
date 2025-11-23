from playwright.sync_api import sync_playwright

def take_screenshot():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()
        page.goto("http://localhost:8000/verification/video.html")
        page.wait_for_timeout(1000) # wait for js to load
        page.screenshot(path="/app/verification/video.png")
        browser.close()

if __name__ == "__main__":
    take_screenshot()
