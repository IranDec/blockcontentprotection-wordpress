(() => {
    // Exit if settings are not defined
    if (typeof bcp_settings === 'undefined') {
        return;
    }

    const processedVideos = new WeakSet();

    // --- Core Video Protection Logic ---
    const protectVideo = (video) => {
        if (processedVideos.has(video) || video.dataset.bcpProtected === 'true') return;
        processedVideos.add(video);
        video.dataset.bcpProtected = 'true';

        // Apply general protections
        video.setAttribute('controlsList', 'nodownload');
        video.setAttribute('disablePictureInPicture', 'true');

        // A. Improved Download Protection (Blob URL)
        if (bcp_settings.disable_video_download) {
            const originalSrc = video.getAttribute('src') || (video.querySelector('source') ? video.querySelector('source').getAttribute('src') : null);
            if (originalSrc && !originalSrc.startsWith('blob:')) {
                video.removeAttribute('src');
                video.querySelectorAll('source').forEach(s => s.removeAttribute('src'));

                fetch(originalSrc, { credentials: 'omit' })
                    .then(response => {
                        if (!response.ok) throw new Error('BCP: Network response was not ok for: ' + originalSrc);
                        return response.blob();
                    })
                    .then(blob => {
                        const blobUrl = URL.createObjectURL(blob);
                        video.src = blobUrl;
                    })
                    .catch(err => {
                        console.error('BCP Error: Could not fetch video for protection.', err);
                        video.setAttribute('src', originalSrc); // Restore original src on failure
                    });
            }
        }

        // B. Dynamic Watermark
        if (bcp_settings.enable_watermark && bcp_settings.watermark_text && bcp_settings.watermark_text.length > 0) {
            const wrapper = document.createElement('div');
            wrapper.classList.add('bcp-watermark-wrapper');
            video.parentNode.insertBefore(wrapper, video);
            wrapper.appendChild(video);

            applyWatermark(wrapper, video);
        }
    };

    // --- Watermark Application Logic ---
    const applyWatermark = (wrapper, video) => {
        // Remove existing watermark to re-apply
        const existingWatermark = wrapper.querySelector('.bcp-watermark, .bcp-wm-style-pattern');
        if (existingWatermark) {
            existingWatermark.remove();
        }

        const opacity = parseFloat(bcp_settings.watermark_opacity) || 0.5;
        const position = bcp_settings.watermark_position || 'animated';
        const style = bcp_settings.watermark_style || 'text';

        if (style === 'pattern') {
            const patternContainer = document.createElement('div');
            patternContainer.classList.add('bcp-wm-style-pattern');
            patternContainer.style.opacity = opacity;

            // Create a grid of text spans to fill the container
            const text = bcp_settings.watermark_text;
            for (let i = 0; i < 30; i++) { // Create a fixed number of spans
                const span = document.createElement('span');
                span.classList.add('bcp-watermark-pattern-span');
                span.textContent = text;
                patternContainer.appendChild(span);
            }
            wrapper.appendChild(patternContainer);

        } else { // 'text' style
            const watermark = document.createElement('div');
            watermark.classList.add('bcp-watermark', 'bcp-wm-style-text');

            // Add position class
            if (position === 'animated') {
                watermark.classList.add('bcp-wm-position-animated');
            } else {
                watermark.classList.add(`bcp-wm-position-${position}`);
            }

            watermark.textContent = bcp_settings.watermark_text;
            watermark.style.opacity = opacity;
            wrapper.appendChild(watermark);
        }
    };


    // --- Initialization and Observation ---
    const initProtection = () => {
        // Apply protection to all existing video elements
        document.querySelectorAll('video').forEach(protectVideo);

        // Apply body-level protections
        if (bcp_settings.disable_text_selection) {
            document.body.style.cssText += 'user-select:none !important;-webkit-user-select:none !important;';
        }
        if (bcp_settings.enhanced_protection) {
            document.body.classList.add('bcp-enhanced-protection');
        }
    };

    // Use MutationObserver to protect dynamically added videos
    const observer = new MutationObserver(mutations => {
        mutations.forEach(mutation => {
            mutation.addedNodes.forEach(node => {
                if (node.nodeType === 1) { // Element node
                    if (node.tagName === 'VIDEO') {
                        protectVideo(node);
                    } else if (node.querySelectorAll) {
                        node.querySelectorAll('video').forEach(protectVideo);
                    }
                }
            });
        });
    });

    observer.observe(document.body, { childList: true, subtree: true });

    // Run initial protection when the DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initProtection);
    } else {
        initProtection();
    }


    // --- Event-based Protections ---

    // Right Click
    if (bcp_settings.disable_right_click) {
        document.addEventListener('contextmenu', e => e.preventDefault(), false);
    }

    // Copy
    if (bcp_settings.disable_copy) {
        document.addEventListener('copy', e => e.preventDefault(), false);
    }

    // Image Dragging
    if (bcp_settings.disable_image_drag) {
        document.addEventListener('dragstart', e => {
            if (e.target.tagName === 'IMG') e.preventDefault();
        }, false);
    }

    // Keyboard Shortcuts (DevTools & Screenshot)
    if (bcp_settings.disable_devtools || bcp_settings.disable_screenshot) {
        document.addEventListener('keydown', e => {
            const key = e.key.toUpperCase();
            const ctrl = e.ctrlKey || e.metaKey;

            // DevTools
            if (bcp_settings.disable_devtools && (key === 'F12' || (ctrl && e.shiftKey && ['I', 'J', 'C'].includes(key)) || (ctrl && key === 'U'))) {
                e.preventDefault();
            }

            // Screenshot
            if (bcp_settings.disable_screenshot && (e.key === 'PrintScreen' || (ctrl && e.shiftKey && ['3', '4', 'S'].includes(key)))) {
                e.preventDefault();
                if (bcp_settings.enable_custom_messages && bcp_settings.screenshot_alert_message) {
                    alert(bcp_settings.screenshot_alert_message);
                }
            }
        });
    }

    // Screen Recording Detection
    if (bcp_settings.video_screen_record_block && navigator.mediaDevices && navigator.mediaDevices.getDisplayMedia) {
        const originalGetDisplayMedia = navigator.mediaDevices.getDisplayMedia;
        let recordingAlertShown = false;
        
        navigator.mediaDevices.getDisplayMedia = async function(...args) {
            // Apply blackout and show alert
            document.querySelectorAll('video').forEach(v => v.style.filter = 'brightness(0)');
            if (!recordingAlertShown && bcp_settings.enable_custom_messages && bcp_settings.recording_alert_message) {
                alert(bcp_settings.recording_alert_message);
                recordingAlertShown = true;
            }
            
            try {
                const stream = await originalGetDisplayMedia.apply(this, args);
                // When recording stops, restore videos
                stream.getTracks().forEach(track => {
                    track.onended = () => {
                        document.querySelectorAll('video').forEach(v => v.style.filter = '');
                        recordingAlertShown = false; // Reset for next time
                    };
                });
                return stream;
            } catch (err) {
                // User cancelled the screen share prompt, restore videos
                document.querySelectorAll('video').forEach(v => v.style.filter = '');
                recordingAlertShown = false;
                throw err;
            }
        };
    }

})();