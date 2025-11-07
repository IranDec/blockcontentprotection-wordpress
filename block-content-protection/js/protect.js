// Exit if settings are not defined
if (typeof bcp_settings === 'undefined') {
    // We don't return here in a module, but the rest of the script won't run.
    console.error("BCP Error: bcp_settings object not found.");
} else {

    const processedVideos = new WeakSet();

    // --- Core Video Protection Logic ---
    const protectVideo = (video) => {
        if (processedVideos.has(video) || video.dataset.bcpProtected === 'true') return;
        processedVideos.add(video);
        video.dataset.bcpProtected = 'true';

        // Apply general protections that don't depend on other settings
        video.setAttribute('controlsList', 'nodownload');
        video.setAttribute('disablePictureInPicture', 'true');

        // A. Handle Watermarking
        // If watermarking is enabled, we need to create the wrapper first.
        let wrapper = video.closest('.bcp-watermark-wrapper');
        if (!wrapper) {
            wrapper = document.createElement('div');
            wrapper.classList.add('bcp-watermark-wrapper');
            video.parentNode.insertBefore(wrapper, video);
            wrapper.appendChild(video);
        }

        if (bcp_settings.enable_video_watermark && bcp_settings.watermark_text && bcp_settings.watermark_text.length > 0) {
            applyWatermark(wrapper, video);
        }

        // B. Improved Download Protection (Blob URL)
        if (bcp_settings.disable_video_download) {
            const originalSrc = video.getAttribute('src') || (video.querySelector('source') ? video.querySelector('source').getAttribute('src') : null);
            if (originalSrc && !originalSrc.startsWith('blob:')) {
                // Immediately pause, clear sources, and call load() to stop the browser's original fetch.
                // This is a more robust way to prevent download managers from latching onto the initial src.
                video.pause();
                video.removeAttribute('src');
                video.querySelectorAll('source').forEach(s => s.remove());
                video.load();

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

    // --- Fullscreen Watermark Handling ---
    let fullscreenWatermarkObserver = null;
    const handleFullscreenChange = () => {
        const fullscreenElement = document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement || document.msFullscreenElement;

        // Disconnect any existing observer
        if (fullscreenWatermarkObserver) {
            fullscreenWatermarkObserver.disconnect();
            fullscreenWatermarkObserver = null;
        }

        // Clean up any lingering watermarks
        document.querySelectorAll('.bcp-fullscreen-watermark').forEach(wm => wm.remove());

        // Check if we entered fullscreen with a video that should be protected
        if (fullscreenElement && fullscreenElement.tagName === 'VIDEO' && processedVideos.has(fullscreenElement) && bcp_settings.enable_video_watermark && bcp_settings.watermark_text) {

            const createWatermark = () => {
                // Double-check and remove before creating a new one
                document.querySelectorAll('.bcp-fullscreen-watermark').forEach(wm => wm.remove());

                const watermark = document.createElement('div');
                watermark.className = 'bcp-watermark bcp-fullscreen-watermark'; // Base classes
                watermark.textContent = bcp_settings.watermark_text;
                watermark.style.opacity = parseFloat(bcp_settings.watermark_opacity) || 0.5;

                const position = bcp_settings.watermark_position || 'animated';
                const style = bcp_settings.watermark_style || 'text';

                // We only support 'text' style for fullscreen for now, as pattern is more complex.
                watermark.classList.add('bcp-wm-style-text');
                if (position === 'animated') {
                    watermark.classList.add('bcp-wm-position-animated');
                } else {
                    watermark.classList.add(`bcp-wm-position-${position}`);
                }

                // Append directly to the fullscreen element itself.
                // This is crucial for keeping it contained within the fullscreen view.
                fullscreenElement.parentElement.appendChild(watermark);

                 // Add ResizeObserver to adjust font size based on video dimensions
                 if ('ResizeObserver' in window) {
                    fullscreenWatermarkObserver = new ResizeObserver(entries => {
                        for (let entry of entries) {
                            const { width, height } = entry.contentRect;
                            // Simple scaling factor: font size as a percentage of the smaller dimension
                            const smallerDimension = Math.min(width, height);
                            const fontSize = Math.max(12, Math.min(32, smallerDimension * 0.03)); // Clamp font size between 12px and 32px
                            watermark.style.fontSize = `${fontSize}px`;
                        }
                    });
                    fullscreenWatermarkObserver.observe(fullscreenElement);
                }
            };
            // Use a small timeout to ensure the fullscreen transition is complete
            setTimeout(createWatermark, 100);
        }
    };


    // --- Page Watermark ---
    /*
    const applyPageWatermark = () => {
        if (!bcp_settings.enable_page_watermark || !bcp_settings.watermark_text || bcp_settings.watermark_text.length === 0) {
            return;
        }

        if (document.querySelector('.bcp-page-watermark-container')) {
            return; // Already exists
        }

        const watermarkContainer = document.createElement('div');
        watermarkContainer.classList.add('bcp-page-watermark-container');

        const opacity = parseFloat(bcp_settings.watermark_opacity) || 0.5;
        const style = bcp_settings.watermark_style || 'text';
        const text = bcp_settings.watermark_text;

        watermarkContainer.style.opacity = opacity;

        if (style === 'pattern') {
            watermarkContainer.classList.add('bcp-wm-style-pattern');
            for (let i = 0; i < 150; i++) { // More spans to cover the page
                const span = document.createElement('span');
                span.classList.add('bcp-watermark-pattern-span');
                span.textContent = text;
                watermarkContainer.appendChild(span);
            }
        } else { // 'text' style
            const watermark = document.createElement('div');
            watermark.classList.add('bcp-watermark', 'bcp-wm-style-text', 'bcp-wm-position-animated');
            watermark.textContent = bcp_settings.watermark_text;
            watermarkContainer.appendChild(watermark);
        }

        document.body.appendChild(watermarkContainer);
    };
    */

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

        // Apply the full-page watermark
        // applyPageWatermark();
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

    // Fullscreen change events
    ['fullscreenchange', 'webkitfullscreenchange', 'mozfullscreenchange', 'MSFullscreenChange'].forEach(event => {
        document.addEventListener(event, handleFullscreenChange, false);
    });

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

    // Keyboard Shortcuts (DevTools & Screenshot) & Blackout on Focus Loss
    if (bcp_settings.disable_devtools || bcp_settings.disable_screenshot) {
        if (bcp_settings.disable_screenshot) {
            window.addEventListener('blur', () => {
                document.body.classList.add('bcp-screenshot-detected');
            });
            window.addEventListener('focus', () => {
                document.body.classList.remove('bcp-screenshot-detected');
            });
        }

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

                // Apply blackout effect
                document.body.classList.add('bcp-screenshot-detected');

                // Show alert if enabled
                if (bcp_settings.enable_custom_messages && bcp_settings.screenshot_alert_message) {
                    alert(bcp_settings.screenshot_alert_message);
                }

                // Remove blackout effect after a short delay
                setTimeout(() => {
                    document.body.classList.remove('bcp-screenshot-detected');
                }, 1000); // 1 second delay
            }
        });
    }

    // Screen Recording Detection
    if (bcp_settings.video_screen_record_block && navigator.mediaDevices && navigator.mediaDevices.getDisplayMedia) {
        const originalGetDisplayMedia = navigator.mediaDevices.getDisplayMedia;

        // This function adds a class to the video wrapper to apply the blackout effect from CSS.
        const handleRecordingStart = () => {
            document.querySelectorAll('video').forEach(video => {
                const wrapper = video.closest('.bcp-watermark-wrapper') || video;
                wrapper.classList.add('bcp-recording-detected');
            });
        };

        // This function removes the class to restore the video.
        const handleRecordingStop = () => {
            document.querySelectorAll('.bcp-recording-detected').forEach(wrapper => {
                wrapper.classList.remove('bcp-recording-detected');
            });
        };

        // We override the native getDisplayMedia function to hook into the screen sharing process.
        navigator.mediaDevices.getDisplayMedia = async function(...args) {
            // This logic is corrected to only apply the blackout effect *after* the
            // user has given permission to record the screen.
            try {
                const stream = await originalGetDisplayMedia.apply(this, args);

                // If the promise resolves, the user has started sharing. Now, we apply the block.
                handleRecordingStart();

                // When the user stops sharing, remove the block.
                stream.getTracks().forEach(track => {
                    track.onended = handleRecordingStop;
                });
                return stream;
            } catch (err) {
                // If the promise rejects, the user cancelled the prompt.
                // We ensure the block is removed in case it was somehow applied.
                handleRecordingStop();
                throw err;
            }
        };
    }
}
