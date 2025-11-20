document.addEventListener('DOMContentLoaded', () => {

    // --- Your original code ---
    const controlButtons = document.querySelectorAll('.solid-btn');
    controlButtons.forEach(button => {
        button.addEventListener('mouseenter', () => button.classList.add('shadow-lg'));
        button.addEventListener('mouseleave', () => button.classList.remove('shadow-lg'));
    });
    const toast = document.querySelector('.toast');
    if (toast) {
        const bootstrapToast = new bootstrap.Toast(toast);
        bootstrapToast.show();
    }
    // --- End of your original code ---


    // === NEW: CHAT/DROPDOWN LOGIC ===

    // Note: The main icon ID is 'messages-icon-float'
    const messagesIconWrapper = document.getElementById('messages-icon-float');

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

        // --- UPDATED fetchConversations FUNCTION (With Loading Spinner) ---
        const fetchConversations = async () => {
            // 1. Show Loading Spinner immediately
            conversationList.innerHTML = `
                <div class="text-center p-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>`;

            try {
                const response = await fetch('../ajax/get_conversations.php');
                const conversations = await response.json();

                // 2. Clear the spinner
                conversationList.innerHTML = '';

                if (conversations && conversations.length > 0) {
                    conversations.forEach(convo => {
                        const avatarElement = convo.avatar_url
                            ? `<img src="../${convo.avatar_url}" class="rounded-circle me-3" width="50" height="50" style="object-fit: cover;">`
                            : `<i class="bi bi-person-circle me-3" style="font-size: 50px; color: #6c757d;"></i>`;

                        const isUnread = convo.unread_count > 0;
                        const otherUserName = convo.other_user_name;
                        const lastMessageText = convo.last_message
                            ? (isUnread ? `<strong>${convo.last_message}</strong>` : convo.last_message)
                            : 'No messages yet.';
                        const lastMessageTime = convo.last_message_time || '';

                        const convoItemHTML = `
                    <a href="#" class="list-group-item list-group-item-action" 
                       data-conversation-id="${convo.conversation_id}" 
                       data-user-name="${convo.other_user_name}" 
                       data-user-avatar="${convo.avatar_url || ''}">
                        
                        <div class="d-flex align-items-center">
                            ${avatarElement}
                            <div class="flex-grow-1" style="min-width: 0;">
                                <div class="d-flex justify-content-between">
                                    <h6 class="mb-0 text-truncate">${isUnread ? `<strong>${otherUserName}</strong>` : otherUserName}</h6>
                                    <small class="text-muted flex-shrink-0 ms-2">${isUnread ? `<strong>${lastMessageTime}</strong>` : lastMessageTime}</small>
                                </div>
                                <p class="mb-0 text-muted text-truncate" style="max-width: 250px;">
                                    ${lastMessageText}
                                </p>
                            </div>
                            ${isUnread ? '<span class="badge bg-primary rounded-pill ms-3 p-1"></span>' : ''}
                        </div>
                    </a>`;
                        conversationList.insertAdjacentHTML('beforeend', convoItemHTML);
                    });
                } else {
                    conversationList.innerHTML = '<div class="text-center text-muted p-5" id="no-messages-placeholder">No messages yet.</div>';
                }
            } catch (error) {
                console.error('Fetch conversations failed:', error);
                conversationList.innerHTML = '<div class="text-center text-danger p-3">Failed to load messages.</div>';
            }
        };

        // --- 2. Function to show search results (Unchanged) ---
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

        // --- 3. Search keyup listener (Unchanged) ---
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

        // --- 4. Open chat window function (UPDATED) ---
        const openChatWindow = async (conversationId, otherUser = {}) => {
            chatConversationIdInput.value = conversationId;

            // Mark this specific conversation as read (Scenario A)
            const formData = new FormData();
            formData.append('conversation_id', conversationId);
            await fetch('../ajax/mark_messages_read.php', { method: 'POST', body: formData });

            // --- THIS IS THE FIX ---
            // After marking as read, immediately check the total count again.
            // This will make the red dot disappear instantly if it was the last unread message.
            checkForMessages();
            // --- END OF FIX ---

            if (otherUser.fname) {
                currentChatPartner = otherUser;
            }
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
                // 'currentUserId' is defined on your admin page <script> tag
                const isMe = msg.sender_id == currentUserId;
                const msgHtml = `<div class="d-flex ${isMe ? 'justify-content-end' : ''}"><div class="p-2 rounded-3 mb-2" style="max-width: 75%; background-color: ${isMe ? '#0d6efd' : '#e9ecef'}; color: ${isMe ? 'white' : 'black'};">${msg.message_text}</div></div>`;
                chatModalBody.insertAdjacentHTML('beforeend', msgHtml);
            });
            chatModalBody.scrollTop = chatModalBody.scrollHeight;

            const dropdownInstance = bootstrap.Dropdown.getInstance(messagesIconWrapper);
            if (dropdownInstance) dropdownInstance.hide();
            chatModal.show();
        };

        // --- 5. Conversation list click listener (Unchanged) ---
        conversationList.addEventListener('click', async (event) => {
            event.preventDefault();
            const link = event.target.closest('.list-group-item-action');
            if (!link) return;
            const userName = link.dataset.userName;
            const otherUser = {
                fname: userName.split(' ')[0],
                lname: userName.split(' ').slice(1).join(' '),
                avatar_url: link.dataset.userAvatar
            };

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

        // --- 6. Message form submit listener (Unchanged) ---
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

                    const convoLink = conversationList.querySelector(`[data-conversation-id="${chatConversationIdInput.value}"]`);
                    if (convoLink) {
                        const lastMessageElement = convoLink.querySelector('p');
                        if (lastMessageElement) {
                            lastMessageElement.innerHTML = `<strong>You: ${sentMessageText}</strong>`;
                        }
                    } else {
                        fetchConversations();
                    }
                } else { alert(result.message); }
            } catch (error) { console.error('Failed to send message:', error); }
        });

        // --- 7. Dropdown 'show' listener (UPDATED) ---
        // We only fetch conversations. We DO NOT mark as read or hide the dot.
        messagesIconWrapper.parentElement.addEventListener('show.bs.dropdown', () => {
            fetchConversations();
        });

        // --- 8. Real-time check (UPDATED) ---
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
            } catch (error) { /* Silently fail */ }
        };

        checkForMessages();
        setInterval(checkForMessages, 10000); // Check every 10 seconds
    }
});