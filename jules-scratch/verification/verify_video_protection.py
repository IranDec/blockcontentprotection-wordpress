from playwright.sync_api import sync_playwright, expect

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    context = browser.new_context()
    page = context.new_page()

    try:
        # Navigate to a local WordPress page that is assumed to have a video.
        # The user will need to have a local server running for this to work.
        page.goto("http://localhost/sample-page", wait_until="networkidle")

        # Find the video element on the page.
        video = page.locator("video").first
        expect(video).to_be_visible()

        # 1. Verify Download Protection: The 'src' should be a blob URL.
        # We give it a generous timeout because fetching the video can be slow.
        expect(video).to_have_attribute("src", r"blob:http://localhost/\w{8}-\w{4}-\w{4}-\w{4}-\w{12}", timeout=15000)

        # 2. Verify Watermark: Check if the watermark div is present.
        # The watermark is added inside a wrapper div.
        wrapper = page.locator(".bcp-watermark-wrapper").first
        expect(wrapper).to_be_visible()

        watermark = wrapper.locator(".bcp-watermark")
        expect(watermark).to_be_visible()
        expect(watermark).not_to_be_empty() # Check it has some text

        # Take a screenshot of the video area for visual confirmation.
        wrapper.screenshot(path="jules-scratch/verification/verification.png")

        print("Verification script completed successfully. Screenshot saved.")

    except Exception as e:
        print(f"An error occurred during verification: {e}")
        # Take a screenshot anyway for debugging
        page.screenshot(path="jules-scratch/verification/verification_error.png")


    finally:
        # Clean up
        context.close()
        browser.close()

with sync_playwright() as playwright:
    run(playwright)