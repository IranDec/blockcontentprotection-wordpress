(() => {
    // Exit if settings are not defined
    if (typeof bcp_settings === 'undefined') {
        return;
    }

    // Disable Right Click
    if (bcp_settings.disable_right_click) {
        document.addEventListener('contextmenu', e => e.preventDefault(), false);
    }

    // Disable Developer Tools
    if (bcp_settings.disable_devtools) {
        document.addEventListener('keydown', e => {
            if (e.key === 'F12' ||
               (e.ctrlKey && e.shiftKey && ['I', 'J', 'C'].includes(e.key.toUpperCase())) ||
               (e.ctrlKey && e.key.toUpperCase() === 'U')) {
                e.preventDefault();
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

})();