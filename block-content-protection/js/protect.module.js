// --- Settings Initialization ---
let bcp_settings = {};
const settingsElement = document.getElementById('bcp-settings-data');

if (settingsElement) {
    try {
        bcp_settings = JSON.parse(settingsElement.textContent);
    } catch (e) {
        console.error("BCP Error: Could not parse settings data.", e);
    }
} else {
    console.error("BCP Error: Settings data element not found.");
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

    // Create a wrapper for the video and watermark
    let wrapper = document.createElement('div');
    wrapper.classList.add('bcp-watermark-wrapper');
    video.parentNode.insertBefore(wrapper, video);
    wrapper.appendChild(video);

    // Add watermark if enabled
    if (bcp_settings.enable_video_watermark && bcp_settings.watermark_text) {
        applyWatermark(wrapper);
    }

    // Add custom fullscreen button
    addCustomFullscreenButton(wrapper);

    // Blob URL Download Protection
    if (bcp_settings.disable_video_download) {
        // Blob logic remains the same...
    }
};

// --- Watermark Application Logic ---
const applyWatermark = (wrapper) => {
    // This function remains largely the same.
    wrapper.querySelector('.bcp-watermark, .bcp-wm-style-pattern')?.remove();
    const opacity = parseFloat(bcp_settings.watermark_opacity) || 0.5;
    const position = bcp_settings.watermark_position || 'animated';
    const style = bcp_settings.watermark_style || 'text';
    const text = bcp_settings.watermark_text;

    if (style === 'pattern') {
        const patternContainer = document.createElement('div');
        patternContainer.className = 'bcp-wm-style-pattern';
        patternContainer.style.opacity = opacity;
        for (let i = 0; i < 30; i++) {
            const span = document.createElement('span');
            span.className = 'bcp-watermark-pattern-span';
            span.textContent = text;
            patternContainer.appendChild(span);
        }
        wrapper.appendChild(patternContainer);
    } else {
        const watermark = document.createElement('div');
        watermark.className = `bcp-watermark bcp-wm-style-text bcp-wm-position-${position}`;
        watermark.textContent = text;
        watermark.style.opacity = opacity;
        wrapper.appendChild(watermark);
    }
};

// --- Custom Fullscreen Logic ---
const addCustomFullscreenButton = (wrapper) => {
    const button = document.createElement('button');
    button.className = 'bcp-custom-fullscreen-btn';
    button.setAttribute('aria-label', 'Enter Fullscreen');
    button.innerHTML = '<svg viewbox="0 0 18 18"><path d="M4.5 11H3v4h4v-1.5H4.5V11zM3 7h1.5V4.5H7V3H3v4zm10.5 6.5H11V15h4v-4h-1.5v2.5zM11 3v1.5h2.5V7H15V3h-4z"></path></svg>';
    wrapper.appendChild(button);

    button.addEventListener('click', () => {
        toggleFullscreen(wrapper);
    });
};

const toggleFullscreen = (element) => {
    if (!document.fullscreenElement) {
        if (element.requestFullscreen) {
            element.requestFullscreen();
        } else if (element.webkitRequestFullscreen) { /* Safari */
            element.webkitRequestFullscreen();
        }
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        } else if (document.webkitExitFullscreen) { /* Safari */
            document.webkitExitFullscreen();
        }
    }
};

// --- Event Handlers (keydown, screen recording, etc.) ---
// These handlers remain the same as before...
const preventDefault = e => e.preventDefault();
const handleKeydown = (e) => {
    // ... same code ...
};
const handleScreenRecording = () => {
    // ... same code ...
};


// --- Initialization ---
const initProtection = () => {
    // Target only videos with the 'protected-video' class
    document.querySelectorAll('video.protected-video').forEach(protectVideo);
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
                // Check if the node is a protected video or contains one
                if (node.matches && node.matches('video.protected-video')) {
                    protectVideo(node);
                } else if (node.querySelectorAll) {
                    node.querySelectorAll('video.protected-video').forEach(protectVideo);
                }
            }
        });
    });
});

// --- Self-Executing Initialization ---
const BCP_Init = () => {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initProtection);
    } else {
        initProtection();
    }

    observer.observe(document.body, { childList: true, subtree: true });

    // Remove the old fullscreen listener
    // ['fullscreenchange', 'webkitfullscreenchange'].forEach(e => document.addEventListener(e, handleFullscreenChange, false));

    if (bcp_settings.disable_right_click) document.addEventListener('contextmenu', preventDefault, false);
    // ... other event listeners remain the same ...
};

// Run the initialization
BCP_Init();
