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
                    // Attempt to clear clipboard
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText('').catch(() => {});
                    }
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
            video.setAttribute('disablePictureInPicture', 'true');
        });
    }

    // Mobile Screenshot Block
    if (bcp_settings.mobile_screenshot_block) {
        // Add blur on visibility change (works on some devices)
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                document.body.classList.add('bcp-blur-content');
            } else {
                document.body.classList.remove('bcp-blur-content');
            }
        });

        // Detect screenshot attempts on Android
        let lastTouch = 0;
        document.addEventListener('touchstart', (e) => {
            const now = Date.now();
            if (e.touches.length === 3 && now - lastTouch < 500) {
                if (bcp_settings.screenshot_alert_message) {
                    alert(bcp_settings.screenshot_alert_message);
                }
            }
            lastTouch = now;
        });

        // Block on focus loss (screenshot gesture detection)
        window.addEventListener('blur', () => {
            document.body.style.filter = 'blur(20px)';
            setTimeout(() => {
                document.body.style.filter = '';
            }, 100);
        });
    }

    // Video Screen Recording Block
    if (bcp_settings.video_screen_record_block) {
        let recordingDetected = false;

        // Detect screen recording via Page Visibility API
        const checkRecording = () => {
            if (document.hidden) {
                recordingDetected = true;
                document.querySelectorAll('video').forEach(video => {
                    video.classList.add('bcp-video-blocked');
                    video.style.filter = 'brightness(0)';
                });
                if (bcp_settings.recording_alert_message && !document.body.dataset.recordingAlertShown) {
                    document.body.dataset.recordingAlertShown = 'true';
                    alert(bcp_settings.recording_alert_message);
                }
            }
        };

        document.addEventListener('visibilitychange', checkRecording);

        // Additional recording detection methods
        const detectRecording = () => {
            // Check for screen capture via getDisplayMedia
            if (navigator.mediaDevices && navigator.mediaDevices.getDisplayMedia) {
                const originalGetDisplayMedia = navigator.mediaDevices.getDisplayMedia;
                navigator.mediaDevices.getDisplayMedia = function() {
                    recordingDetected = true;
                    document.querySelectorAll('video').forEach(video => {
                        video.classList.add('bcp-video-blocked');
                        video.style.filter = 'brightness(0)';
                    });
                    if (bcp_settings.recording_alert_message) {
                        alert(bcp_settings.recording_alert_message);
                    }
                    return originalGetDisplayMedia.apply(this, arguments);
                };
            }
        };

        detectRecording();

        // Monitor video elements
        const protectVideos = () => {
            document.querySelectorAll('video').forEach(video => {
                // Add DRM-like protection attributes
                video.setAttribute('controlsList', 'nodownload noremoteplayback');
                video.setAttribute('disablePictureInPicture', 'true');
                
                // Apply black screen on recording
                if (recordingDetected) {
                    video.style.filter = 'brightness(0)';
                }

                // Detect if video is being captured
                video.addEventListener('play', () => {
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');
                    
                    const checkCapture = setInterval(() => {
                        if (video.paused || video.ended) {
                            clearInterval(checkCapture);
                            return;
                        }
                        
                        try {
                            ctx.drawImage(video, 0, 0);
                            // If we can draw, no recording; if error, might be recording
                        } catch (e) {
                            recordingDetected = true;
                            video.style.filter = 'brightness(0)';
                            clearInterval(checkCapture);
                        }
                    }, 1000);
                });
            });
        };

        // Apply protection on load
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', protectVideos);
        } else {
            protectVideos();
        }

        // Watch for new videos added dynamically
        const observer = new MutationObserver(protectVideos);
        observer.observe(document.body, { childList: true, subtree: true });
    }

    // Additional layer: Detect DevTools opening
    const detectDevTools = () => {
        const threshold = 160;
        const widthThreshold = window.outerWidth - window.innerWidth > threshold;
        const heightThreshold = window.outerHeight - window.innerHeight > threshold;
        
        if (widthThreshold || heightThreshold) {
            document.body.style.display = 'none';
            if (bcp_settings.disable_devtools) {
                alert('Developer tools detected. Page access blocked.');
            }
        }
    };

    if (bcp_settings.disable_devtools) {
        setInterval(detectDevTools, 1000);
        window.addEventListener('resize', detectDevTools);
    }

    // Prevent iframe embedding for extra security
    if (window.top !== window.self) {
        document.body.style.display = 'none';
    }

})();
