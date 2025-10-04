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
                const isPrintScreen = e.key === 'PrintScreen';
                const isMacScreenshot = e.metaKey && e.shiftKey && (e.key === '3' || e.key === '4');

                if (isPrintScreen || isMacScreenshot) {
                    e.preventDefault();
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
        // Detect screenshot attempts on Android (3-finger gesture)
        let touchCount = 0;
        let touchTimer;
        
        document.addEventListener('touchstart', (e) => {
            touchCount = e.touches.length;
            
            // Only alert on 3-finger touch (common screenshot gesture on some devices)
            if (touchCount >= 3) {
                clearTimeout(touchTimer);
                touchTimer = setTimeout(() => {
                    if (bcp_settings.screenshot_alert_message) {
                        alert(bcp_settings.screenshot_alert_message);
                    }
                }, 100);
            }
        });

        document.addEventListener('touchend', () => {
            clearTimeout(touchTimer);
            touchCount = 0;
        });
    }

    // Video Screen Recording Block
    if (bcp_settings.video_screen_record_block) {
        let isRecording = false;
        let recordingAlertShown = false;

        // Method 1: Try to detect getDisplayMedia (screen capture API)
        if (navigator.mediaDevices && navigator.mediaDevices.getDisplayMedia) {
            const original = navigator.mediaDevices.getDisplayMedia;
            
            navigator.mediaDevices.getDisplayMedia = function(...args) {
                isRecording = true;
                
                // Black out all videos
                document.querySelectorAll('video').forEach(v => {
                    v.style.filter = 'brightness(0)';
                    v.classList.add('bcp-recording-detected');
                });
                
                // Show alert once
                if (!recordingAlertShown && bcp_settings.recording_alert_message) {
                    alert(bcp_settings.recording_alert_message);
                    recordingAlertShown = true;
                }
                
                return original.apply(this, args).then(stream => {
                    // When recording stops, restore videos
                    stream.getTracks().forEach(track => {
                        track.onended = () => {
                            isRecording = false;
                            document.querySelectorAll('video').forEach(v => {
                                if (!v.classList.contains('bcp-manual-block')) {
                                    v.style.filter = '';
                                    v.classList.remove('bcp-recording-detected');
                                }
                            });
                        };
                    });
                    return stream;
                });
            };
        }

        // Method 2: Detect via visibility API (less aggressive)
        let hiddenTime = 0;
        
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                hiddenTime = Date.now();
            } else {
                const duration = Date.now() - hiddenTime;
                
                // Only trigger if page was hidden for more than 2 seconds
                // This prevents false positives from normal tab switching
                if (duration > 2000 && !isRecording) {
                    isRecording = true;
                    
                    document.querySelectorAll('video').forEach(v => {
                        if (v.paused === false) { // Only affect playing videos
                            v.style.filter = 'brightness(0)';
                            v.classList.add('bcp-recording-detected');
                        }
                    });
                    
                    if (!recordingAlertShown && bcp_settings.recording_alert_message) {
                        alert(bcp_settings.recording_alert_message);
                        recordingAlertShown = true;
                    }
                    
                    // Reset after 5 seconds (in case of false positive)
                    setTimeout(() => {
                        if (isRecording) {
                            isRecording = false;
                            document.querySelectorAll('video').forEach(v => {
                                if (!v.classList.contains('bcp-manual-block')) {
                                    v.style.filter = '';
                                    v.classList.remove('bcp-recording-detected');
                                }
                            });
                        }
                    }, 5000);
                }
            }
        });

        // Method 3: Apply basic protection to all videos
        const protectVideos = () => {
            document.querySelectorAll('video').forEach(video => {
                // Only add attributes, don't black out unless recording detected
                video.setAttribute('controlsList', 'nodownload noremoteplayback');
                video.setAttribute('disablePictureInPicture', 'true');
                
                // Apply filter only if recording was detected
                if (isRecording && !video.classList.contains('bcp-recording-detected')) {
                    video.style.filter = 'brightness(0)';
                    video.classList.add('bcp-recording-detected');
                }
            });
        };

        // Apply protection on load
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', protectVideos);
        } else {
            protectVideos();
        }

        // Watch for new videos added dynamically
        const observer = new MutationObserver(() => {
            protectVideos();
        });
        
        observer.observe(document.body, { 
            childList: true, 
            subtree: true 
        });
    }

    // DevTools Detection (optional, less aggressive)
    if (bcp_settings.disable_devtools) {
        const threshold = 200;
        let devtoolsOpen = false;
        
        const checkDevTools = () => {
            const widthDiff = window.outerWidth - window.innerWidth;
            const heightDiff = window.outerHeight - window.innerHeight;
            
            if (widthDiff > threshold || heightDiff > threshold) {
                if (!devtoolsOpen) {
                    devtoolsOpen = true;
                    // Just warn, don't block entire page
                    console.warn('Developer tools detected');
                }
            } else {
                devtoolsOpen = false;
            }
        };

        setInterval(checkDevTools, 1000);
    }

})();
