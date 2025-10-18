document.addEventListener('DOMContentLoaded', function () {
    const messageToggle = document.getElementById('enable_custom_messages');
    const messageFields = document.getElementById('bcp_messages_section_fields');

    if (messageToggle && messageFields) {
        // Function to toggle visibility
        const toggleVisibility = () => {
            if (messageToggle.checked) {
                messageFields.classList.remove('bcp-hidden');
            } else {
                messageFields.classList.add('bcp-hidden');
            }
        };

        // Initial check on page load
        toggleVisibility();

        // Add event listener
        messageToggle.addEventListener('change', toggleVisibility);
    }
});