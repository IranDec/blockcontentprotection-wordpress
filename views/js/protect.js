(() => {
    // Check if bcp_settings is defined
    if (typeof bcp_settings === 'undefined') {
        return;
    }

    if (bcp_settings.BCP_DISABLE_RIGHTCLICK) {
        document.addEventListener('contextmenu', e => e.preventDefault());
    }

    if (bcp_settings.BCP_DISABLE_DEVTOOLS) {
        document.addEventListener('keydown', e => {
            if (
                e.key === 'F12' ||
                (e.ctrlKey && e.shiftKey && ['I', 'J', 'C'].includes(e.key.toUpperCase())) ||
                (e.ctrlKey && e.key.toUpperCase() === 'U')
            ) {
                e.preventDefault();
            }
        });
    }

    if (bcp_settings.BCP_DISABLE_SCREENSHOT) {
        document.addEventListener('keydown', e => {
            if (e.key === 'PrintScreen') {
                navigator.clipboard.writeText('');
                alert('Screenshots are disabled.');
            }
        });
    }

    window.addEventListener('DOMContentLoaded', () => {
        if (bcp_settings.BCP_DISABLE_VIDEO_DOWNLOAD) {
            document.querySelectorAll('video').forEach(video => {
                video.setAttribute('controlsList', 'nodownload');
            });
        }

        document.querySelectorAll('img, video').forEach(el => {
            el.setAttribute('draggable', 'false');
            el.setAttribute('oncontextmenu', 'return false');
        });

        // Disable text selection
        if (bcp_settings.BCP_DISABLE_TEXT_SELECTION) {
            document.body.style.userSelect = 'none';
            document.body.style.webkitUserSelect = 'none';
            document.body.style.mozUserSelect = 'none';
            document.body.style.msUserSelect = 'none';
        }

        // Enhanced screen protection
        if (bcp_settings.BCP_ENHANCED_PROTECTION) {
            document.body.classList.add('bcp-enhanced-protection');
        }
    });

    // Disable copy
    if (bcp_settings.BCP_DISABLE_DBLCLICK_COPY) {
        document.addEventListener('copy', e => {
            e.preventDefault();
        });
    }

    // Disable drag
    document.addEventListener('dragstart', e => {
        if (e.target.tagName === 'IMG' || e.target.tagName === 'VIDEO') {
            e.preventDefault();
        }
    });
})();