// Ensure the settings object is available
if (typeof bcp_settings === 'undefined') {
    throw new Error("BCP Error: The bcp_settings object is not defined.");
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

    // A. Handle Watermarking
    let wrapper = video.closest('.bcp-watermark-wrapper');
    if (!wrapper) {
        wrapper = document.createElement('div');
        wrapper.classList.add('bcp-watermark-wrapper');
        video.parentNode.insertBefore(wrapper, video);
        wrapper.appendChild(video);
    }
    if (bcp_settings.enable_video_watermark && bcp_settings.watermark_text) {
        applyWatermark(wrapper);
    }

    // B. Blob URL Download Protection
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
};

// --- Watermark Application Logic ---
const applyWatermark = (wrapper) => {
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
    } else { // 'text' style
        const watermark = document.createElement('div');
        watermark.className = `bcp-watermark bcp-wm-style-text bcp-wm-position-${position}`;
        watermark.textContent = text;
        watermark.style.opacity = opacity;
        wrapper.appendChild(watermark);
    }
};

// --- Fullscreen Watermark Handling ---
let fullscreenWatermarkObserver = null;
const handleFullscreenChange = () => {
    fullscreenWatermarkObserver?.disconnect();
    document.querySelectorAll('.bcp-fullscreen-watermark').forEach(wm => wm.remove());

    const fullscreenElement = document.fullscreenElement || document.webkitFullscreenElement;
    if (fullscreenElement?.tagName === 'VIDEO' && processedVideos.has(fullscreenElement) && bcp_settings.enable_video_watermark && bcp_settings.watermark_text) {
        setTimeout(() => createFullscreenWatermark(fullscreenElement), 100);
    }
};

const createFullscreenWatermark = (videoElement) => {
    document.querySelectorAll('.bcp-fullscreen-watermark').forEach(wm => wm.remove()); // Final cleanup

    const watermark = document.createElement('div');
    const position = bcp_settings.watermark_position || 'animated';
    watermark.className = `bcp-watermark bcp-fullscreen-watermark bcp-wm-style-text bcp-wm-position-${position}`;
    watermark.textContent = bcp_settings.watermark_text;
    watermark.style.opacity = parseFloat(bcp_settings.watermark_opacity) || 0.5;

    videoElement.parentElement.appendChild(watermark);

    if ('ResizeObserver' in window) {
        fullscreenWatermarkObserver = new ResizeObserver(entries => {
            for (let entry of entries) {
                const smallerDim = Math.min(entry.contentRect.width, entry.contentRect.height);
                const fontSize = Math.max(12, Math.min(32, smallerDim * 0.03)); // Clamp font size
                watermark.style.fontSize = `${fontSize}px`;
            }
        });
        fullscreenWatermarkObserver.observe(videoElement);
    }
};

// --- Event Handlers ---
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
    if (!bcp_settings.video_screen_record_block || !navigator.mediaDevices?.getDisplayMedia) return;

    const originalGetDisplayMedia = navigator.mediaDevices.getDisplayMedia;
    navigator.mediaDevices.getDisplayMedia = async function(...args) {
        try {
            const stream = await originalGetDisplayMedia.apply(this, args);
            document.querySelectorAll('video').forEach(v => v.closest('.bcp-watermark-wrapper, video')?.classList.add('bcp-recording-detected'));
            stream.getTracks().forEach(track => {
                track.onended = () => document.querySelectorAll('.bcp-recording-detected').forEach(el => el.classList.remove('bcp-recording-detected'));
            });
            return stream;
        } catch (err) {
            document.querySelectorAll('.bcp-recording-detected').forEach(el => el.classList.remove('bcp-recording-detected'));
            throw err;
        }
    };
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
                if (node.tagName === 'VIDEO') protectVideo(node);
                else node.querySelectorAll?.('video').forEach(protectVideo);
            }
        });
    });
});

// --- Public API ---
export function init() {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initProtection);
    } else {
        initProtection();
    }

    observer.observe(document.body, { childList: true, subtree: true });

    // Event-based protections
    ['fullscreenchange', 'webkitfullscreenchange'].forEach(e => document.addEventListener(e, handleFullscreenChange, false));
    if (bcp_settings.disable_right_click) document.addEventListener('contextmenu', preventDefault, false);
    if (bcp_settings.disable_copy) document.addEventListener('copy', preventDefault, false);
    if (bcp_settings.disable_image_drag) document.addEventListener('dragstart', e => { if (e.target.tagName === 'IMG') e.preventDefault(); }, false);

    if (bcp_settings.disable_devtools || bcp_settings.disable_screenshot) {
        document.addEventListener('keydown', handleKeydown);
    }
     if (bcp_settings.disable_screenshot) {
        window.addEventListener('blur', () => document.body.classList.add('bcp-screenshot-detected'));
        window.addEventListener('focus', () => document.body.classList.remove('bcp-screenshot-detected'));
    }

    handleScreenRecording();
}
