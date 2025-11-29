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

// Use a WeakSet to keep track of videos that have already been processed
const processedVideos = new WeakSet();

// --- Core Video Protection Logic ---
const protectVideo = (video) => {
    // Exit if the video has already been processed or is marked as protected
    if (processedVideos.has(video) || video.dataset.bcpProtected === 'true') return;
    processedVideos.add(video);
    video.dataset.bcpProtected = 'true';

    // Disable native controls that are not needed
    video.setAttribute('controlsList', 'nodownload');
    video.setAttribute('disablePictureInPicture', 'true');

    // Create a wrapper for the video and its watermark.
    // Plyr will use the video's parent as the container.
    let wrapper = document.createElement('div');
    wrapper.classList.add('bcp-player-wrapper');
    video.parentNode.insertBefore(wrapper, video);
    wrapper.appendChild(video);

    // --- Plyr Player Initialization ---
    const player = new Plyr(video, {
        controls: [
            'play-large', 'play', 'progress', 'current-time', 'duration', 'mute',
            'volume', 'captions', 'settings', 'pip', 'fullscreen',
        ],
        settings: ['captions', 'quality', 'speed'],
        speed: {
            selected: 1,
            options: [0.5, 0.75, 1, 1.25, 1.5, 2],
        },
        quality: {
            default: 1080, // Placeholder
            options: [1080, 720], // Placeholder
            forced: true,
            onChange: (quality) => console.log('Quality changed to:', quality),
        },
        previewThumbnails: {
            enabled: true,
            src: 'https://cdn.plyr.io/static/demo/thumbs/100p.vtt', // Placeholder
        },
        tooltips: {
            controls: true,
            seek: true,
        },
    });

    // Apply watermark if enabled in settings
    if (bcp_settings.enable_video_watermark && bcp_settings.watermark_text) {
        applyWatermark(player.elements.container); // Apply watermark to the Plyr container
    }

    // Secure the video source if download protection is enabled
    if (bcp_settings.disable_video_download) {
        protectVideoSource(video);
    }
};

const protectVideoSource = (video) => {
    const originalSrc = video.getAttribute('src') || video.querySelector('source')?.getAttribute('src');
    if (originalSrc && !originalSrc.startsWith('blob:')) {
        fetch(originalSrc, { credentials: 'omit' })
            .then(response => {
                if (!response.ok) throw new Error(`BCP: Network error fetching video: ${response.statusText}`);
                return response.blob();
            })
            .then(blob => {
                video.src = URL.createObjectURL(blob);
            })
            .catch(err => {
                console.error('BCP Error:', err);
                video.setAttribute('src', originalSrc);
            });
    }
};

const applyWatermark = (wrapper) => {
    wrapper.querySelector('.bcp-watermark, .bcp-wm-style-pattern')?.remove();
    const { watermark_opacity = 0.5, watermark_animated = true, watermark_position = 'top_left', watermark_style = 'text', watermark_text, watermark_count = 30 } = bcp_settings;

    const element = document.createElement('div');
    if (watermark_style === 'pattern') {
        element.className = 'bcp-wm-style-pattern';
        element.style.opacity = watermark_opacity;
        for (let i = 0; i < watermark_count; i++) {
            const span = document.createElement('span');
            span.className = 'bcp-watermark-pattern-span';
            span.textContent = watermark_text;
            element.appendChild(span);
        }
    } else {
        const positionClass = watermark_animated ? 'bcp-wm-position-animated' : `bcp-wm-position-${watermark_position}`;
        element.className = `bcp-watermark bcp-wm-style-text ${positionClass}`;
        element.textContent = watermark_text;
        element.style.opacity = watermark_opacity;
    }
    wrapper.appendChild(element);
};

// --- General Protection Event Handlers ---
const preventDefault = e => e.preventDefault();

const handleKeydown = (e) => {
    const key = e.key.toUpperCase();
    const ctrl = e.ctrlKey || e.metaKey;

    if (bcp_settings.disable_devtools && (e.key === 'F12' || (ctrl && e.shiftKey && ['I', 'J', 'C'].includes(key)) || (ctrl && key === 'U'))) {
        e.preventDefault();
    }
    if (bcp_settings.disable_screenshot && (e.key === 'PrintScreen' || (ctrl && e.shiftKey && ['3', '4', 'S'].includes(key)))) {
        e.preventDefault();
        document.body.classList.add('bcp-screenshot-detected');
        if (bcp_settings.enable_custom_messages && bcp_settings.screenshot_alert_message) {
            alert(bcp_settings.screenshot_alert_message);
        }
        setTimeout(() => document.body.classList.remove('bcp-screenshot-detected'), 1000);
    }
};

const handleScreenRecording = () => {
    if (!bcp_settings.video_screen_record_block) return;

    document.addEventListener('visibilitychange', () => {
        document.querySelectorAll('video').forEach(v => {
            const wrapper = v.closest('.bcp-player-wrapper');
            if (wrapper) {
                wrapper.classList.toggle('bcp-recording-detected', document.hidden);
            }
        });
    });

    if (navigator.mediaDevices?.getDisplayMedia) {
        const originalGetDisplayMedia = navigator.mediaDevices.getDisplayMedia;
        navigator.mediaDevices.getDisplayMedia = async function(...args) {
            document.querySelectorAll('.bcp-player-wrapper').forEach(w => w.classList.add('bcp-recording-detected'));
            try {
                const stream = await originalGetDisplayMedia.apply(this, args);
                stream.getTracks().forEach(track => {
                    track.onended = () => document.querySelectorAll('.bcp-recording-detected').forEach(el => el.classList.remove('bcp-recording-detected'));
                });
                return stream;
            } catch (err) {
                document.querySelectorAll('.bcp-recording-detected').forEach(el => el.classList.remove('bcp-recording-detected'));
                throw err;
            }
        };
    }
};

// --- Initialization ---
const initProtection = () => {
    document.querySelectorAll('video').forEach(protectVideo);
    if (bcp_settings.disable_text_selection) {
        document.body.style.cssText += 'user-select:none;-webkit-user-select:none;';
    }
    if (bcp_settings.enhanced_protection) {
        document.body.classList.add('bcp-enhanced-protection');
    }
};

const observer = new MutationObserver(mutations => {
    mutations.forEach(mutation => {
        mutation.addedNodes.forEach(node => {
            if (node.nodeType === 1) {
                if (node.tagName === 'VIDEO') {
                    protectVideo(node);
                } else {
                    node.querySelectorAll?.('video').forEach(protectVideo);
                }
            }
        });
    });
});

const BCP_Init = () => {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initProtection);
    } else {
        initProtection();
    }

    observer.observe(document.body, { childList: true, subtree: true });

    if (bcp_settings.disable_right_click) document.addEventListener('contextmenu', preventDefault, false);
    if (bcp_settings.disable_copy) document.addEventListener('copy', preventDefault, false);
    if (bcp_settings.disable_image_drag) document.addEventListener('dragstart', e => { if (e.target.tagName === 'IMG') e.preventDefault(); }, false);
    if (bcp_settings.disable_devtools || bcp_settings.disable_screenshot) {
        document.addEventListener('keydown', handleKeydown);
    }

    handleScreenRecording();
};

BCP_Init();
