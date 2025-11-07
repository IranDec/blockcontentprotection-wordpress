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
const watermarkObservers = new WeakMap();

// --- Core Video Protection Logic ---
const protectVideo = (video) => {
    // If the video is already correctly wrapped, we don't need to do anything.
    // This is the key fix for lightboxes or other scripts that move video elements in the DOM.
    if (video.parentElement?.classList.contains('bcp-watermark-wrapper')) {
        return;
    }

    // Apply one-time protections only if the video is new.
    if (!processedVideos.has(video)) {
        processedVideos.add(video);
        video.setAttribute('controlsList', 'nodownload');
        video.setAttribute('disablePictureInPicture', 'true');

        // Blob URL Download Protection should only run once.
        if (bcp_settings.disable_video_download) {
            const originalSrc = video.getAttribute('src') || video.querySelector('source')?.getAttribute('src');
            if (originalSrc && !originalSrc.startsWith('blob:')) {
                video.pause();
                video.removeAttribute('src');
                video.querySelectorAll('source').forEach(s => s.remove());
                video.load();

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
                        video.setAttribute('src', originalSrc); // Restore on failure
                    });
            }
        }
    }

    video.dataset.bcpProtected = 'true';

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
    // A. Handle Watermarking - Re-wrap the video if it's been moved.
    if (video.parentNode) { // Ensure the video is attached to the DOM
        const wrapper = document.createElement('div');
        wrapper.classList.add('bcp-watermark-wrapper');
        video.parentNode.insertBefore(wrapper, video);
        wrapper.appendChild(video);

        if (bcp_settings.enable_video_watermark && bcp_settings.watermark_text) {
            applyWatermark(wrapper);
        }
    }
};

// --- Watermark Application Logic ---
const applyWatermark = (wrapper) => {
    // This function remains largely the same.
    // Disconnect any existing observer for this wrapper before removing the watermark
    if (watermarkObservers.has(wrapper)) {
        watermarkObservers.get(wrapper).disconnect();
        watermarkObservers.delete(wrapper);
    }

    wrapper.querySelector('.bcp-watermark, .bcp-wm-style-pattern')?.remove();
    const opacity = parseFloat(bcp_settings.watermark_opacity) || 0.5;
    const position = bcp_settings.watermark_position || 'animated';
    const style = bcp_settings.watermark_style || 'text';
    const text = bcp_settings.watermark_text;

    let watermarkElement; // This will be the element we observe

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
        watermarkElement = patternContainer; // Observe the container of the spans
    } else { // 'text' style
        const watermark = document.createElement('div');
        watermark.className = `bcp-watermark bcp-wm-style-text bcp-wm-position-${position}`;
        watermark.textContent = text;
        watermark.style.opacity = opacity;
        wrapper.appendChild(watermark);
        watermarkElement = watermark; // Observe the single text element
    }

    // --- Responsive Font Size Logic ---
    if ('ResizeObserver' in window && watermarkElement) {
        const resizeObserver = new ResizeObserver(entries => {
            for (let entry of entries) {
                const { width, height } = entry.contentRect;
                const smallerDim = Math.min(width, height);

                // Adjust font size relative to the container's smaller dimension
                // Clamped between 12px and 32px for readability.
                const fontSize = Math.max(12, Math.min(32, smallerDim * 0.03));

                // For pattern, apply to spans. For text, apply to the watermark itself.
                if (style === 'pattern') {
                    const spans = entry.target.querySelectorAll('.bcp-watermark-pattern-span');
                    spans.forEach(span => {
                        span.style.fontSize = `${fontSize}px`;
                    });
                } else {
                    entry.target.style.fontSize = `${fontSize}px`;
                }
            }
        });

        // Observe the wrapper, as the watermark itself has absolute positioning
        // and won't have a reliable size. The wrapper's size is tied to the video.
        resizeObserver.observe(wrapper);

        // Store the observer instance so we can disconnect it later if needed
        watermarkObservers.set(wrapper, resizeObserver);
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
