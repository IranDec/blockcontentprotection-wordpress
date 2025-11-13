document.addEventListener('DOMContentLoaded', () => {
// 1. Wrap all video elements in a container for the CSS rule to work.
document.querySelectorAll('video').forEach(video => {
if (video.closest('.video-blackout-wrapper')) {
return; // Already wrapped
}
const wrapper = document.createElement('div');
wrapper.className = 'video-blackout-wrapper';
video.parentNode.insertBefore(wrapper, video);
wrapper.appendChild(video);
});

// 2. Check if the getDisplayMedia API is available.
if (navigator.mediaDevices && navigator.mediaDevices.getDisplayMedia) {
const originalGetDisplayMedia = navigator.mediaDevices.getDisplayMedia;

// This function adds the blackout class to all video wrappers.
const startBlackout = () => {
document.querySelectorAll('.video-blackout-wrapper').forEach(wrapper => {
wrapper.classList.add('bcp-recording-detected');
});
};

// This function removes the blackout class.
const stopBlackout = () => {
document.querySelectorAll('.bcp-recording-detected').forEach(wrapper => {
wrapper.classList.remove('bcp-recording-detected');
});
};

// 3. Override the original getDisplayMedia function.
navigator.mediaDevices.getDisplayMedia = async function(...args) {
try {
// Wait for the user to select a screen to share.
const stream = await originalGetDisplayMedia.apply(this, args);

// If they start sharing, apply the blackout.
startBlackout();

// Add an 'onended' event to each track in the stream.
// This fires when the user stops sharing.
stream.getTracks().forEach(track => {
track.onended = stopBlackout;
});

return stream;
} catch (err) {
// If the user cancels the screen share prompt, ensure the blackout is removed.
stopBlackout();
throw err;
}
};
} else {
console.log('Screen recording detection is not supported by this browser.');
}
});
