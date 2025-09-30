(() => {
    // Exit if settings are not defined
    if (typeof bcp_settings === 'undefined') {
        return;
    }

    // Disable Right Click
    if (bcp_settings.disable_right_click) {
        document.addEventListener('contextmenu', e => e.preventDefault(), false);
    }

    // Disable Developer Tools & Screenshot
    if (bcp_settings.disable_devtools || bcp_settings.disable_screenshot) {
        document.addEventListener('keydown', e => {
            // DevTools
            if (bcp_settings.disable_devtools) {
                if (e.key === 'F12' ||
                   (e.ctrlKey && e.shiftKey && ['I', 'J', 'C'].includes(e.key.toUpperCase())) ||
                   (e.ctrlKey && e.key.toUpperCase() === 'U')) {
                    e.preventDefault();
                }
            }

            // Screenshot
            if (bcp_settings.disable_screenshot) {
                // Windows: PrintScreen
                // macOS: Cmd+Shift+3, Cmd+Shift+4
                const isPrintScreen = e.key === 'PrintScreen';
                const isMacScreenshot = e.metaKey && e.shiftKey && (e.key === '3' || e.key === '4');

                if (isPrintScreen || isMacScreenshot) {
                    e.preventDefault();
                    // Attempt to clear clipboard to prevent pasting the screenshot
                    navigator.clipboard.writeText('');
                    if (bcp_settings.screenshot_alert_message) {
                        alert(bcp_settings.screenshot_alert_message);
                    }
                }
            }
        });
    }

    // Disable Copy
    if (bcp_settings.disable_copy) {
        document.addEventListener('copy', e => e.preventDefault(), false);
    }

    // Disable Text Selection
    if (bcp_settings.disable_text_selection) {
        document.body.style.userSelect = 'none';
        document.body.style.webkitUserSelect = 'none';
        document.body.style.mozUserSelect = 'none';
        document.body.style.msUserSelect = 'none';
    }

    // Disable Image Dragging
    if (bcp_settings.disable_image_drag) {
        document.addEventListener('dragstart', e => {
            if (e.target.tagName === 'IMG') {
                e.preventDefault();
            }
        }, false);
    }

    // Enhanced Screen Protection
    if (bcp_settings.enhanced_protection) {
        document.body.classList.add('bcp-enhanced-protection');
    }

    // Disable Video Download
    if (bcp_settings.disable_video_download) {
        document.querySelectorAll('video').forEach(video => {
            video.setAttribute('controlsList', 'nodownload');
        });
    }

})();