document.addEventListener('DOMContentLoaded', function () {
    const messageToggle = document.getElementById('enable_custom_messages');

    if (messageToggle) {
        // Find the parent row of the toggle checkbox
        const toggleRow = messageToggle.closest('tr');

        if (toggleRow) {
            // The fields to toggle are the next two rows in the table
            const field1 = toggleRow.nextElementSibling;
            const field2 = field1 ? field1.nextElementSibling : null;

            const toggleVisibility = () => {
                if (messageToggle.checked) {
                    if (field1) field1.classList.remove('bcp-hidden');
                    if (field2) field2.classList.remove('bcp-hidden');
                } else {
                    if (field1) field1.classList.add('bcp-hidden');
                    if (field2) field2.classList.add('bcp-hidden');
                }
            };

            // Initial check on page load
            toggleVisibility();

            // Add event listener
            messageToggle.addEventListener('change', toggleVisibility);
        }
    }
});