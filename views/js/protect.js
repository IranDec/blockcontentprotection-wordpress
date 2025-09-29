(() => {
    if (typeof BCP_DISABLE_RIGHTCLICK !== 'undefined' && BCP_DISABLE_RIGHTCLICK) {
        document.addEventListener('contextmenu', e => e.preventDefault());
    }

    if (typeof BCP_DISABLE_DEVTOOLS !== 'undefined' && BCP_DISABLE_DEVTOOLS) {
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

    if (typeof BCP_DISABLE_SCREENSHOT !== 'undefined' && BCP_DISABLE_SCREENSHOT) {
        document.addEventListener('keydown', e => {
            if (e.key === 'PrintScreen') {
                navigator.clipboard.writeText('');
                alert('Screenshots are disabled.');
            }
        });
    }

    window.addEventListener('DOMContentLoaded', () => {
        if (typeof BCP_DISABLE_VIDEO_DOWNLOAD !== 'undefined' && BCP_DISABLE_VIDEO_DOWNLOAD) {
            document.querySelectorAll('video').forEach(video => {
                video.setAttribute('controlsList', 'nodownload');
                video.removeAttribute('download');
            });
        }

        document.querySelectorAll('img, video').forEach(el => {
            el.setAttribute('draggable', 'false');
            el.setAttribute('oncontextmenu', 'return false');
        });

        // جلوگیری از انتخاب متن
        if (typeof BCP_DISABLE_TEXT_SELECTION !== 'undefined' && BCP_DISABLE_TEXT_SELECTION) {
            document.body.classList.add('unselectable');
        }

        // محافظت پیشرفته
        if (typeof BCP_ENHANCED_PROTECTION !== 'undefined' && BCP_ENHANCED_PROTECTION) {
            document.body.classList.add('enhanced-protection');
            document.querySelectorAll('video').forEach(video => {
                video.classList.add('protected-video');
            });
        }
    });

    // جلوگیری از کپی کردن
    if (typeof BCP_DISABLE_DBLCLICK_COPY !== 'undefined' && BCP_DISABLE_DBLCLICK_COPY) {
        document.addEventListener('copy', e => {
            e.preventDefault();
        });
    }

    // جلوگیری از کشیدن (dragging) تصاویر و ویدیوها
    document.addEventListener('dragstart', e => e.preventDefault());
})();
