document.addEventListener('DOMContentLoaded', () => {
    // Animate buttons on hover
    const controlButtons = document.querySelectorAll('.solid-btn');

    controlButtons.forEach(button => {
        button.addEventListener('mouseenter', () => {
            button.classList.add('shadow-lg');
        });

        button.addEventListener('mouseleave', () => {
            button.classList.remove('shadow-lg');
        });
    });

    // Optional: Show welcome toast if present
    const toast = document.querySelector('.toast');
    if (toast) {
        const bootstrapToast = new bootstrap.Toast(toast);
        bootstrapToast.show();
    }

    // --- NEW: FLOATING MESSAGE ICON CODE ---

    const messageIcon = document.getElementById('messages-icon-float');
    const messageDot = document.getElementById('message-notification-dot');

    // 1. Function to check for new messages
    async function checkNewMessages() {
        // This check prevents errors if the icon isn't on the page
        if (!messageIcon || !messageDot) return;

        try {
            // Path is ../ajax/ because admin.js is in /admin/
            const response = await fetch('../ajax/check_new_messages.php');
            const data = await response.json();

            if (data.unread_count > 0) {
                // Show the red dot
                messageDot.classList.remove('d-none');
            } else {
                // Hide the red dot
                messageDot.classList.add('d-none');
            }
        } catch (error) {
            console.error('Error checking messages:', error);
        }
    }

    // 2. Function to mark messages as read when icon is clicked
    async function markMessagesAsRead() {
        try {
            // Hide the dot immediately for a fast UI
            if (messageDot) {
                messageDot.classList.add('d-none');
            }

            // Tell the server to mark ALL messages as read (no conversation_id sent)
            await fetch('../ajax/mark_messages_read.php', { method: 'POST' });
        } catch (error) { // <-- FIX: Added { here
            console.error('Error marking messages as read:', error);
        } // <-- FIX: Added } here
    }

    // 3. Add listener to the icon
    if (messageIcon) {
        messageIcon.addEventListener('click', (e) => {
            e.preventDefault();

            // This is just an example. You should open your
            // message modal or page here.
            console.log('Floating message icon clicked, opening messages...');
            // window.location.href = 'messages.php'; // Or change to your messages page

            // Mark all messages as read
            markMessagesAsRead();
        });
    }

    // 4. Run the check when the page loads, and then every 10 seconds
    if (messageIcon) {
        checkNewMessages(); // Check immediately
        setInterval(checkNewMessages, 10000); // Check every 10 seconds
    }

    // --- END OF NEW CODE ---
});