// FINAL COMPLETE CODE for js/teacher.js and js/student.js
document.addEventListener('DOMContentLoaded', () => {

    const sidebar = document.querySelector('.sidebar');
    const sidebarLinks = document.querySelectorAll('.sidebar .sidebar-link, .sidebar .dropdown > a');
    const coursesTab = document.querySelector('.sidebar [data-tab="courses"]');

    sidebarLinks.forEach(link => {
        link.addEventListener('click', function (event) {
            const isDropdown = this.hasAttribute('data-bs-toggle');
            if (!isDropdown) { event.preventDefault(); }
            sidebarLinks.forEach(s_link => s_link.classList.remove('active-tab'));
            this.classList.add('active-tab');
        });
    });

    document.addEventListener('click', function (event) {
        if (sidebar && !sidebar.contains(event.target) && !event.target.closest('.dropdown-menu')) {
            sidebarLinks.forEach(link => link.classList.remove('active-tab'));
            if (coursesTab) { coursesTab.classList.add('active-tab'); }
        }
    });

    const messagesIconWrapper = document.getElementById('messages-icon-wrapper');
    if (messagesIconWrapper) {
        let currentChatPartner = {};
        const conversationList = document.getElementById('conversation-list');
        const searchInput = document.querySelector('#messages-dropdown input[type="text"]');
        const chatModal = new bootstrap.Modal(document.getElementById('chatModal'));
        const chatModalHeader = document.getElementById('chat-modal-header');
        const chatModalBody = document.getElementById('chat-modal-body');
        const messageForm = document.getElementById('message-form');
        const messageDot = document.getElementById('message-notification-dot');
        const chatConversationIdInput = document.getElementById('chat-conversation-id');
        const chatMessageInput = document.getElementById('chat-message-input');
        let debounceTimer;

        const fetchConversations = async () => {
            try {
                const response = await fetch('../ajax/get_conversations.php');
                const conversations = await response.json();
                conversationList.innerHTML = '';
                if (conversations && conversations.length > 0) {
                    conversations.forEach(convo => {
                        const avatarElement = convo.avatar_url
                            ? `<img src="../${convo.avatar_url}" class="rounded-circle me-3" width="50" height="50" style="object-fit: cover;">`
                            : `<i class="bi bi-person-circle me-3" style="font-size: 50px; color: #6c757d;"></i>`;

                        const lastMessage = convo.last_message ? convo.last_message : 'No messages yet.';
                        const convoItemHTML = `<a href="#" class="list-group-item list-group-item-action" data-conversation-id="${convo.conversation_id}" data-user-name="${convo.fname} ${convo.lname}" data-user-avatar="${convo.avatar_url || ''}"><div class="d-flex align-items-center">${avatarElement}<div><h6 class="mb-0">${convo.fname} ${convo.lname}</h6><p class="mb-0 text-muted text-truncate" style="max-width: 250px;">${lastMessage}</p></div></div></a>`;
                        conversationList.insertAdjacentHTML('beforeend', convoItemHTML);
                    });
                } else {
                    conversationList.innerHTML = '<div class="text-center text-muted p-5" id="no-messages-placeholder">No messages yet.</div>';
                }
            } catch (error) { console.error('Fetch conversations failed:', error); }
        };

        const displaySearchResults = (users) => {
            conversationList.innerHTML = '';
            if (users.length > 0) {
                users.forEach(user => {
                    const avatarElement = user.avatar_url
                        ? `<img src="../${user.avatar_url}" class="rounded-circle me-3" width="50" height="50" style="object-fit: cover;">`
                        : `<i class="bi bi-person-circle me-3" style="font-size: 50px; color: #6c757d;"></i>`;

                    const userItemHTML = `<a href="#" class="list-group-item list-group-item-action new-conversation-link" data-user-id="${user.id}" data-user-name="${user.fname} ${user.lname}" data-user-avatar="${user.avatar_url || ''}"><div class="d-flex align-items-center">${avatarElement}<div><h6 class="mb-0">${user.fname} ${user.lname}</h6><p class="mb-0 text-muted">Click to start a conversation</p></div></div></a>`;
                    conversationList.insertAdjacentHTML('beforeend', userItemHTML);
                });
            } else {
                conversationList.innerHTML = '<div class="text-center text-muted p-3">No users found.</div>';
            }
        };

        searchInput.addEventListener('keyup', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(async () => {
                const term = searchInput.value.trim();
                if (term === '') { fetchConversations(); return; }
                try {
                    const response = await fetch(`../ajax/search_users.php?term=${encodeURIComponent(term)}`);
                    const users = await response.json();
                    displaySearchResults(users);
                } catch (error) { console.error('Search failed:', error); }
            }, 300);
        });

        const openChatWindow = async (conversationId, otherUser = {}) => {
            chatConversationIdInput.value = conversationId;

            // Mark messages as read
            const formData = new FormData();
            formData.append('conversation_id', conversationId);
            await fetch('../ajax/mark_messages_read.php', { method: 'POST', body: formData });

            // "Remember" the user if their details are provided
            if (otherUser.fname) {
                currentChatPartner = otherUser;
            }
            // If details are NOT provided (like after sending a message), use the remembered details
            if (!otherUser.fname) {
                otherUser = currentChatPartner;
            }

            const messagesResponse = await fetch(`../ajax/get_messages.php?conversation_id=${conversationId}`);
            const messages = await messagesResponse.json();

            const avatarElementForHeader = otherUser.avatar_url
                ? `<img src="../${otherUser.avatar_url}" class="rounded-circle me-2" width="40" height="40" style="object-fit: cover;">`
                : `<i class="bi bi-person-circle me-2" style="font-size: 40px; color: #6c757d;"></i>`;
            chatModalHeader.innerHTML = `${avatarElementForHeader}<h5 class="modal-title fs-6 mb-0">${otherUser.fname || ''} ${otherUser.lname || ''}</h5>`;

            chatModalBody.innerHTML = '';
            messages.forEach(msg => {
                const isMe = msg.sender_id == currentUserId;
                const msgHtml = `<div class="d-flex ${isMe ? 'justify-content-end' : ''}"><div class="p-2 rounded-3 mb-2" style="max-width: 75%; background-color: ${isMe ? '#0d6efd' : '#e9ecef'}; color: ${isMe ? 'white' : 'black'};">${msg.message_text}</div></div>`;
                chatModalBody.insertAdjacentHTML('beforeend', msgHtml);
            });
            chatModalBody.scrollTop = chatModalBody.scrollHeight;

            const dropdownInstance = bootstrap.Dropdown.getInstance(messagesIconWrapper);
            if (dropdownInstance) dropdownInstance.hide();
            chatModal.show();
        };

        conversationList.addEventListener('click', async (event) => {
            event.preventDefault();
            const link = event.target.closest('.list-group-item-action');
            if (!link) return;
            const otherUser = { fname: link.dataset.userName.split(' ')[0], lname: link.dataset.userName.split(' ').slice(1).join(' '), avatar_url: link.dataset.userAvatar };
            if (link.classList.contains('new-conversation-link')) {
                const formData = new FormData();
                formData.append('other_user_id', link.dataset.userId);
                const response = await fetch('../ajax/get_or_create_conversation.php', { method: 'POST', body: formData });
                const data = await response.json();
                openChatWindow(data.conversation_id, otherUser);
            } else {
                openChatWindow(link.dataset.conversationId, otherUser);
            }
        });

        messageForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const sentMessageText = chatMessageInput.value;
            if (sentMessageText.trim() === '') return;
            const formData = new FormData(messageForm);
            try {
                const response = await fetch('../ajax/send_message.php', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.status === 'success') {
                    chatMessageInput.value = '';
                    openChatWindow(chatConversationIdInput.value);

                    // BUG #2 FIX IS HERE: Manually update the list after sending
                    const convoLink = conversationList.querySelector(`[data-conversation-id="${chatConversationIdInput.value}"]`);
                    if (convoLink) {
                        const lastMessageElement = convoLink.querySelector('p');
                        if (lastMessageElement) {
                            lastMessageElement.textContent = "You: " + sentMessageText;
                        }
                    } else {
                        // If it's a new conversation, just refresh the whole list
                        fetchConversations();
                    }
                } else { alert(result.message); }
            } catch (error) { console.error('Failed to send message:', error); }
        });

        messagesIconWrapper.parentElement.addEventListener('show.bs.dropdown', () => {
            fetchConversations();
        });

        // --- SECTION: MESSAGES REAL-TIME CHECK ---
        const checkForMessages = async () => {
            const messageDot = document.getElementById('message-notification-dot');
            if (!messageDot) return;

            try {
                const response = await fetch('../ajax/check_new_messages.php');
                const data = await response.json();

                if (data.unread_count > 0) {
                    messageDot.classList.remove('d-none');
                } else {
                    messageDot.classList.add('d-none');
                }
            } catch (error) {
                // Silently fail is okay for a background check
            }
        };

        // Check immediately on page load, and then every 20 seconds
        checkForMessages();
        setInterval(checkForMessages, 20000);
    }

    // --- SECTION: NOTIFICATION LOGIC ---
    const notificationsIconWrapper = document.getElementById('notifications-icon-wrapper');
    if (notificationsIconWrapper) {
        const notificationList = document.getElementById('notification-list');
        const noNotificationsPlaceholder = document.getElementById('no-notifications-placeholder');
        const notificationDot = document.getElementById('general-notification-dot');

        const fetchNotifications = async () => {
            try {
                const response = await fetch('../ajax/get_notifications.php');
                const notifications = await response.json();

                notificationList.innerHTML = ''; // Clear current list
                let unreadCount = 0;

                if (notifications && notifications.length > 0) {
                    notifications.forEach(notif => {
                        const itemClass = notif.is_read == 0 ? 'bg-light' : '';
                        const link = notif.link ? `../${notif.link}` : '#';
                        const notifHTML = `
            <a href="${link}" class="list-group-item list-group-item-action ${itemClass}" data-notif-id="${notif.id}">
                <div class="d-flex w-100 justify-content-between">
                    <p class="mb-1 small">${notif.message}</p>
                </div>
                <small class="text-muted">${new Date(notif.created_at).toLocaleString()}</small>
            </a>
        `;
                        notificationList.insertAdjacentHTML('beforeend', notifHTML);
                    });
                } else {
                    // This is the logic from your messages code
                    notificationList.innerHTML = '<div class="text-center text-muted p-5" id="no-notifications-placeholder">No new notifications.</div>';
                }
            } catch (error) {
                console.error("Failed to fetch notifications:", error);
                notificationList.innerHTML = '<div class="p-3 text-danger">Could not load notifications.</div>';
            }
        };

        // Fetch notifications when the dropdown is opened
        notificationsIconWrapper.parentElement.addEventListener('show.bs.dropdown', () => {
            fetchNotifications();
        });

        // Mark notification as read when clicked
        notificationList.addEventListener('click', (event) => {
            const link = event.target.closest('.list-group-item-action');
            if (!link) return;

            const notifId = link.dataset.notifId;
            if (notifId && link.classList.contains('bg-light')) {
                // Mark as read visually right away
                link.classList.remove('bg-light');

                // This checks if there are any other unread (bg-light) items left.
                if (notificationList.querySelectorAll('.bg-light').length === 0) {
                    notificationDot.classList.add('d-none');
                }

                // Tell the server to mark it as read
                const formData = new FormData();
                formData.append('id', notifId);
                fetch('../ajax/mark_notification_read.php', { method: 'POST', body: formData });
            }
        });

        // --- Real-time Polling for the Notification Dot ---
        const checkForNotifications = async () => {
            const notificationDot = document.getElementById('general-notification-dot');
            if (!notificationDot) return;

            try {
                const response = await fetch('../ajax/check_new_notifications.php');
                const data = await response.json();

                if (data.unread_count > 0) {
                    notificationDot.classList.remove('d-none');
                } else {
                    notificationDot.classList.add('d-none');
                }
            } catch (error) {
                // Silently fail is okay for a background check
            }
        };

        // Check immediately on page load, and then every 20 seconds
        checkForNotifications();
        setInterval(checkForNotifications, 20000);
    }

});