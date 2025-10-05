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
        const conversationList = document.getElementById('conversation-list');
        const searchInput = document.querySelector('#messages-dropdown input[type="text"]');
        const chatModal = new bootstrap.Modal(document.getElementById('chatModal'));
        const chatModalHeader = document.getElementById('chat-modal-header');
        const chatModalBody = document.getElementById('chat-modal-body');
        const messageForm = document.getElementById('message-form');
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
                        const avatarSrc = convo.avatar_url ? `../${convo.avatar_url}` : '../assets/default_avatar.png';
                        const lastMessage = convo.last_message ? convo.last_message : 'Click to start conversation.';

                        // BUG #1 FIX IS HERE: Added data-user-name and data-user-avatar to existing conversations
                        const convoItemHTML = `<a href="#" class="list-group-item list-group-item-action" data-conversation-id="${convo.conversation_id}" data-user-name="${convo.fname} ${convo.lname}" data-user-avatar="${convo.avatar_url || ''}"><div class="d-flex align-items-center"><img src="${avatarSrc}" class="rounded-circle me-3" width="50" height="50" style="object-fit: cover;"><div><h6 class="mb-0">${convo.fname} ${convo.lname}</h6><p class="mb-0 text-muted text-truncate" style="max-width: 250px;">${lastMessage}</p></div></div></a>`;
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
                    const avatarSrc = user.avatar_url ? `../${user.avatar_url}` : '../assets/default_avatar.png';
                    const userItemHTML = `<a href="#" class="list-group-item list-group-item-action new-conversation-link" data-user-id="${user.id}" data-user-name="${user.fname} ${user.lname}" data-user-avatar="${user.avatar_url || ''}"><div class="d-flex align-items-center"><img src="${avatarSrc}" class="rounded-circle me-3" width="50" height="50" style="object-fit: cover;"><div><h6 class="mb-0">${user.fname} ${user.lname}</h6><p class="mb-0 text-muted">Click to start a conversation</p></div></div></a>`;
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
            const messagesResponse = await fetch(`../ajax/get_messages.php?conversation_id=${conversationId}`);
            const messages = await messagesResponse.json();

            if (!otherUser.fname && messages.length > 0) {
                const otherUserInfo = messages.find(msg => msg.sender_id != currentUserId);
                if (otherUserInfo) otherUser = { fname: otherUserInfo.fname, lname: otherUserInfo.lname, avatar_url: otherUserInfo.avatar_url };
            }

            const avatarSrc = otherUser.avatar_url ? `../${otherUser.avatar_url}` : '../assets/default_avatar.png';
            chatModalHeader.innerHTML = `<img src="${avatarSrc}" class="rounded-circle me-2" width="40" height="40" style="object-fit: cover;"><h5 class="modal-title fs-6 mb-0">${otherUser.fname || ''} ${otherUser.lname || ''}</h5>`;

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
    }

});