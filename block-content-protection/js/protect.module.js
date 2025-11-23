// --- Settings Initialization ---
let bcp_settings = {};
const settingsElement = document.getElementById('bcp-settings-data');
if (settingsElement) {
    try {
        bcp_settings = JSON.parse(settingsElement.textContent);
    } catch (e) {
        console.error("BCP Error: Could not parse settings data.", e);
    }
}

// Use a WeakSet to keep track of media that has already been processed
const processedMedia = new WeakSet();

// --- Device ID Management ---
const getDeviceId = () => {
    let deviceId = localStorage.getItem('bcp_device_id');
    if (!deviceId) {
        // Generate a simple unique ID
        deviceId = 'bcp-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
        localStorage.setItem('bcp_device_id', deviceId);
    }
    return deviceId;
};

// --- Core Media Protection Logic ---
const protectMedia = (media) => {
    // Exit if the media has already been processed or is marked as protected
    if (processedMedia.has(media) || media.dataset.bcpProtected === 'true') return;
    processedMedia.add(media);
    media.dataset.bcpProtected = 'true';

    // Disable native controls that are not needed
    media.setAttribute('controlsList', 'nodownload');
    if (media.tagName === 'VIDEO') {
        media.setAttribute('disablePictureInPicture', 'true');
    }

    let wrapper;
    if (media.tagName === 'VIDEO') {
        wrapper = document.createElement('div');
        wrapper.classList.add('bcp-watermark-wrapper');
        media.parentNode.insertBefore(wrapper, media);
        wrapper.appendChild(media);
    } else {
        wrapper = media;
    }

    // Apply watermark if enabled in settings
    if (media.tagName === 'VIDEO' && bcp_settings.enable_video_watermark && bcp_settings.watermark_text) {
        applyWatermark(wrapper);
        addCustomControls(wrapper);
    }

    // --- Device ID Handling for Media URLs ---
    if (bcp_settings.enable_device_limit) {
        const deviceId = getDeviceId();
        const placeholder = '{DEVICE_ID}';

        // Replace placeholder in the main src attribute
        const currentSrc = media.getAttribute('src');
        if (currentSrc && currentSrc.includes(placeholder)) {
            media.setAttribute('src', currentSrc.replace(placeholder, deviceId));
        }

        // Replace placeholder in any <source> elements
        media.querySelectorAll('source').forEach(source => {
            const sourceSrc = source.getAttribute('src');
            if (sourceSrc && sourceSrc.includes(placeholder)) {
                source.setAttribute('src', sourceSrc.replace(placeholder, deviceId));
            }
        });
    }

    // Secure the media source if download protection is enabled
    if (bcp_settings.disable_media_download) {
        protectMediaSource(media);
    }
};

const protectMediaSource = (media) => {
    const originalSrc = media.getAttribute('src') || media.querySelector('source')?.getAttribute('src');

    // If the URL is already a secure link from the server, don't convert it to a Blob.
    if (originalSrc && (originalSrc.includes('bcp_media_token=') || originalSrc.startsWith('blob:'))) {
        return;
    }

    if (originalSrc) {
        fetch(originalSrc, { credentials: 'omit' })
            .then(response => {
                if (!response.ok) throw new Error(`BCP: Network error fetching media: ${response.statusText}`);
                return response.blob();
            })
            .then(blob => {
                media.src = URL.createObjectURL(blob);
            })
            .catch(err => {
                console.error('BCP Error:', err);
                media.setAttribute('src', originalSrc); // Restore original source on failure
            });
    }
}

// --- Watermark & Fullscreen UI ---
const applyWatermark = (wrapper) => {
    // Remove any existing watermark to prevent duplicates
    wrapper.querySelector('.bcp-watermark, .bcp-wm-style-pattern')?.remove();
    const { watermark_opacity = 0.5, watermark_animated = true, watermark_position = 'top_left', watermark_style = 'text', watermark_text, watermark_count = 30 } = bcp_settings;

    const element = document.createElement('div');
    if (watermark_style === 'pattern') {
        element.className = 'bcp-wm-style-pattern';
        element.style.opacity = watermark_opacity;
        // Create multiple spans for the pattern effect
        for (let i = 0; i < watermark_count; i++) {
            const span = document.createElement('span');
            span.className = 'bcp-watermark-pattern-span';
            span.textContent = watermark_text;
            element.appendChild(span);
        }
    } else {
        // Determine the correct class based on whether animation is enabled
        const positionClass = watermark_animated ? 'bcp-wm-position-animated' : `bcp-wm-position-${watermark_position}`;
        element.className = `bcp-watermark bcp-wm-style-text ${positionClass}`;
        element.textContent = watermark_text;
        element.style.opacity = watermark_opacity;
    }
    wrapper.appendChild(element);
};

const addCustomControls = (wrapper) => {
    const video = wrapper.querySelector('video');
    if (!video) return;

    // Create the main controls container
    const controlsContainer = document.createElement('div');
    controlsContainer.className = 'bcp-custom-controls';

    // Create the timeline
    const timeline = document.createElement('div');
    timeline.className = 'bcp-timeline';
    const progress = document.createElement('div');
    progress.className = 'bcp-progress';
    timeline.appendChild(progress);
    controlsContainer.appendChild(timeline);

    // Create the bottom controls row
    const bottomControls = document.createElement('div');
    bottomControls.className = 'bcp-bottom-controls';

    // Left side controls (Play, Rewind, Forward, Volume)
    const leftControls = document.createElement('div');
    leftControls.className = 'bcp-left-controls';

    const playBtn = document.createElement('button');
    playBtn.innerHTML = '<svg viewbox="0 0 24 24"><path d="M8 5v14l11-7z"></path></svg>'; // Play icon
    const pauseIcon = '<svg viewbox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"></path></svg>'; // Pause icon
    leftControls.appendChild(playBtn);

    const rewindBtn = document.createElement('button');
    rewindBtn.innerHTML = '<svg viewbox="0 0 24 24"><path d="M11 18V6l-8.5 6 8.5 6zm.5-6l8.5 6V6l-8.5 6z"></path></svg>'; // Rewind icon
    leftControls.appendChild(rewindBtn);

    const forwardBtn = document.createElement('button');
    forwardBtn.innerHTML = '<svg viewbox="0 0 24 24"><path d="M4 18l8.5-6L4 6v12zm9-12v12l8.5-6L13 6z"></path></svg>'; // Forward icon
    leftControls.appendChild(forwardBtn);

    const volumeBtn = document.createElement('button');
    volumeBtn.innerHTML = '<svg viewbox="0 0 24 24"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"></path></svg>'; // Volume icon
    leftControls.appendChild(volumeBtn);

    const volumeSlider = document.createElement('input');
    volumeSlider.type = 'range';
    volumeSlider.min = '0';
    volumeSlider.max = '1';
    volumeSlider.step = '0.1';
    volumeSlider.value = '1';
    volumeSlider.className = 'bcp-volume-slider';
    leftControls.appendChild(volumeSlider);

    bottomControls.appendChild(leftControls);

    // Right side controls (Time, Settings, Fullscreen)
    const rightControls = document.createElement('div');
    rightControls.className = 'bcp-right-controls';

    const timeDisplay = document.createElement('span');
    timeDisplay.className = 'bcp-time-display';
    timeDisplay.textContent = '0:00 / 0:00';
    rightControls.appendChild(timeDisplay);

    const settingsBtn = document.createElement('button');
    settingsBtn.innerHTML = '<svg viewbox="0 0 24 24"><path d="M19.43 12.98c.04-.32.07-.64.07-.98s-.03-.66-.07-.98l2.11-1.65c.19-.15.24-.42.12-.64l-2-3.46c-.12-.22-.39-.3-.61-.22l-2.49 1c-.52-.4-1.08-.73-1.69-.98l-.38-2.65C14.46 2.18 14.25 2 14 2h-4c-.25 0-.46.18-.49.42l-.38 2.65c-.61.25-1.17.59-1.69.98l-2.49-1c-.23-.09-.49 0-.61.22l-2 3.46c-.13.22-.07.49.12.64l2.11 1.65c-.04.32-.07.65-.07.98s.03.66.07.98l-2.11 1.65c-.19.15-.24.42-.12.64l2 3.46c.12.22.39.3.61.22l2.49-1c.52.4 1.08.73 1.69.98l.38 2.65c.03.24.24.42.49.42h4c.25 0 .46-.18.49-.42l.38-2.65c.61-.25 1.17-.59 1.69-.98l2.49 1c.23.09.49 0 .61-.22l2-3.46c.12-.22.07-.49-.12-.64l-2.11-1.65zM12 15.5c-1.93 0-3.5-1.57-3.5-3.5s1.57-3.5 3.5-3.5 3.5 1.57 3.5 3.5-1.57 3.5-3.5 3.5z"></path></svg>';
    rightControls.appendChild(settingsBtn);

    const fullscreenBtn = document.createElement('button');
    fullscreenBtn.innerHTML = '<svg viewbox="0 0 18 18"><path d="M4.5 11H3v4h4v-1.5H4.5V11zM3 7h1.5V4.5H7V3H3v4zm10.5 6.5H11V15h4v-4h-1.5v2.5zM11 3v1.5h2.5V7H15V3h-4z"></path></svg>';
    rightControls.appendChild(fullscreenBtn);

    bottomControls.appendChild(rightControls);
    controlsContainer.appendChild(bottomControls);
    wrapper.appendChild(controlsContainer);

    // --- Event Listeners for Controls ---
    playBtn.addEventListener('click', () => {
        if (video.paused) {
            video.play();
            playBtn.innerHTML = pauseIcon;
        } else {
            video.pause();
            playBtn.innerHTML = '<svg viewbox="0 0 24 24"><path d="M8 5v14l11-7z"></path></svg>'; // Play icon
        }
    });

    rewindBtn.addEventListener('click', () => video.currentTime -= 5);
    forwardBtn.addEventListener('click', () => video.currentTime += 5);
    volumeSlider.addEventListener('input', (e) => video.volume = e.target.value);

    video.addEventListener('timeupdate', () => {
        const progressPercent = (video.currentTime / video.duration) * 100;
        progress.style.width = `${progressPercent}%`;
        timeDisplay.textContent = `${formatTime(video.currentTime)} / ${formatTime(video.duration)}`;
    });

    timeline.addEventListener('click', (e) => {
        const timelineWidth = timeline.clientWidth;
        video.currentTime = (e.offsetX / timelineWidth) * video.duration;
    });

    fullscreenBtn.addEventListener('click', () => {
        if (!document.fullscreenElement) {
            wrapper.requestFullscreen();
        } else {
            document.exitFullscreen();
        }
    });

    function formatTime(time) {
        const minutes = Math.floor(time / 60);
        const seconds = Math.floor(time % 60).toString().padStart(2, '0');
        return `${minutes}:${seconds}`;
    }
};

// --- General Protection Event Handlers ---
const preventDefault = e => e.preventDefault();

const handleKeydown = (e) => {
    const key = e.key.toUpperCase();
    const ctrl = e.ctrlKey || e.metaKey;

    // Block developer tools shortcuts
    if (bcp_settings.disable_devtools && (e.key === 'F12' || (ctrl && e.shiftKey && ['I', 'J', 'C'].includes(key)) || (ctrl && key === 'U'))) {
        e.preventDefault();
    }
    // Block screenshot shortcuts
    if (bcp_settings.disable_screenshot && (e.key === 'PrintScreen' || (ctrl && e.shiftKey && ['3', '4', 'S'].includes(key)))) {
        e.preventDefault();
        document.body.classList.add('bcp-screenshot-detected');
        if (bcp_settings.enable_custom_messages && bcp_settings.screenshot_alert_message) {
            alert(bcp_settings.screenshot_alert_message);
        }
        setTimeout(() => document.body.classList.remove('bcp-screenshot-detected'), 1000);
    }
};

// Intercept screen recording attempts
const handleScreenRecording = () => {
    if (!bcp_settings.video_screen_record_block) return;

    // Black out video when tab loses focus
    document.addEventListener('visibilitychange', () => {
        const isPageHidden = document.hidden;
        document.querySelectorAll('video').forEach(v => {
            const wrapper = v.closest('.bcp-watermark-wrapper');
            if (wrapper) {
                if (isPageHidden) {
                    wrapper.classList.add('bcp-recording-detected');
                } else {
                    wrapper.classList.remove('bcp-recording-detected');
                }
            }
        });
    });

    if (!navigator.mediaDevices?.getDisplayMedia) return;

    const originalGetDisplayMedia = navigator.mediaDevices.getDisplayMedia;
    navigator.mediaDevices.getDisplayMedia = async function(...args) {
        // When a recording attempt is detected, apply a class to blur the video
        document.querySelectorAll('video').forEach(v => v.closest('.bcp-watermark-wrapper')?.classList.add('bcp-recording-detected'));
        try {
            const stream = await originalGetDisplayMedia.apply(this, args);
            // When the stream ends (user stops recording), remove the class
            stream.getTracks().forEach(track => {
                track.onended = () => document.querySelectorAll('.bcp-recording-detected').forEach(el => el.classList.remove('bcp-recording-detected'));
            });
            return stream;
        } catch (err) {
            // If the user cancels the recording, remove the class
            document.querySelectorAll('.bcp-recording-detected').forEach(el => el.classList.remove('bcp-recording-detected'));
            throw err;
        }
    };
};

// --- Initialization ---
const initProtection = () => {
    // Apply protection to all existing video and audio elements on the page
    document.querySelectorAll('video, audio').forEach(protectMedia);
    // Disable text selection if enabled
    if (bcp_settings.disable_text_selection) {
        document.body.style.cssText += 'user-select:none;-webkit-user-select:none;';
    }
    // Apply enhanced CSS protection if enabled
    if (bcp_settings.enhanced_protection) {
        document.body.classList.add('bcp-enhanced-protection');
    }
};

// Use a MutationObserver to detect and protect videos added to the DOM dynamically
const observer = new MutationObserver(mutations => {
    mutations.forEach(mutation => {
        mutation.addedNodes.forEach(node => {
            if (node.nodeType === 1) { // Element node
                if (node.tagName === 'VIDEO' || node.tagName === 'AUDIO') {
                    protectMedia(node);
                } else {
                    // Also check for media within newly added complex elements
                    node.querySelectorAll?.('video, audio').forEach(protectMedia);
                }
            }
        });
    });
});

// Main execution block
const BCP_Init = () => {
    // Run protection when the DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initProtection);
    } else {
        initProtection();
    }

    // Start observing for future DOM changes
    observer.observe(document.body, { childList: true, subtree: true });

    // Add global event listeners based on settings
    if (bcp_settings.disable_right_click) document.addEventListener('contextmenu', preventDefault, false);
    if (bcp_settings.disable_copy) document.addEventListener('copy', preventDefault, false);
    if (bcp_settings.disable_image_drag) document.addEventListener('dragstart', e => { if (e.target.tagName === 'IMG') e.preventDefault(); }, false);
    if (bcp_settings.disable_devtools || bcp_settings.disable_screenshot) {
        document.addEventListener('keydown', handleKeydown);
    }

    handleScreenRecording();
};

BCP_Init();
