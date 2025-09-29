(function() {
    // Wait for the DOM to be fully loaded
    document.addEventListener('DOMContentLoaded', function() {

        // Check if settings are available
        if (typeof bcp_settings === 'undefined') {
            console.warn('Block Content Protection: Settings not found.');
            return;
        }

        // 1. Disable Right Click
        if (bcp_settings.disable_right_click == '1') {
            document.addEventListener('contextmenu', function(e) {
                e.preventDefault();
            });
        }

        // 2. Disable Developer Tools
        if (bcp_settings.disable_devtools == '1') {
            document.addEventListener('keydown', function(e) {
                if (e.keyCode === 123 || // F12
                   (e.ctrlKey && e.shiftKey && e.keyCode === 73) || // Ctrl+Shift+I
                   (e.ctrlKey && e.shiftKey && e.keyCode === 74) || // Ctrl+Shift+J
                   (e.ctrlKey && e.shiftKey && e.keyCode === 67) || // Ctrl+Shift+C
                   (e.ctrlKey && e.keyCode === 85) // Ctrl+U
                ) {
                    e.preventDefault();
                }
            });
        }

        // 3. Disable Screenshot (PrintScreen)
        if (bcp_settings.disable_screenshot == '1') {
             document.addEventListener('keyup', function(e) {
                if (e.key === 'PrintScreen' || e.keyCode === 44) {
                    // This is a superficial attempt. We can't truly block screenshots.
                    // We can try to hide the content briefly to make the screenshot blank.
                    document.body.style.opacity = '0';
                    setTimeout(function() {
                        document.body.style.opacity = '1';
                    }, 100);
                }
            });
        }

        // 4. Disable Video Download & 5. Disable Image Drag
        if (bcp_settings.disable_video_download == '1' || bcp_settings.disable_image_drag == '1') {
            document.addEventListener('contextmenu', function(e) {
                if (bcp_settings.disable_video_download == '1' && e.target.nodeName === 'VIDEO') {
                    e.preventDefault();
                }
                 if (bcp_settings.disable_image_drag == '1' && e.target.nodeName === 'IMG') {
                    e.preventDefault();
                }
            }, true);
        }

        if (bcp_settings.disable_image_drag == '1') {
            document.addEventListener('dragstart', function(e) {
                if (e.target.nodeName === 'IMG') {
                    e.preventDefault();
                }
            });
        }

        // 6. Disable Text Selection & 7. Disable Copy
        if (bcp_settings.disable_text_selection == '1' || bcp_settings.disable_copy == '1') {
            var style = document.createElement('style');
            var css = '';
            if(bcp_settings.disable_text_selection == '1'){
                css += 'body { -webkit-user-select: none; -moz-user-select: none; -ms-user-select: none; user-select: none; }';
            }
            style.type = 'text/css';
            if (style.styleSheet){
                style.styleSheet.cssText = css;
            } else {
                style.appendChild(document.createTextNode(css));
            }
            document.head.appendChild(style);

            if(bcp_settings.disable_copy == '1'){
                 document.addEventListener('copy', function(e) {
                    e.preventDefault();
                });
                document.addEventListener('keydown', function(e) {
                    if (e.ctrlKey && (e.keyCode === 67 || e.keyCode === 88 || e.keyCode === 65)) { // Ctrl+C, Ctrl+X, Ctrl+A
                        e.preventDefault();
                    }
                });
            }
        }
    });
})();
