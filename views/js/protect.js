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

        // جلوگیری از انتخاب و کپی پس از دوبار کلیک
        document.querySelectorAll('p, span, div, h1, h2, h3, h4, h5, h6').forEach(el => {
            el.addEventListener('mouseup', () => {
                if (window.getSelection().toString().length > 0) {
                    setTimeout(() => window.getSelection().removeAllRanges(), 10);
                }
            });
        });
    });

    // جلوگیری از کپی کردن
    document.addEventListener('copy', e => {
        e.preventDefault();
    });

    // جلوگیری از کشیدن (dragging) تصاویر و ویدیوها
    document.addEventListener('dragstart', e => e.preventDefault());
})();
