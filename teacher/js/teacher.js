// FINAL COMPLETE CODE for js/teacher.js
document.addEventListener('DOMContentLoaded', () => {

    //==================================================//
    // START: SIDEBAR LOGIC
    //==================================================//
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

    //==================================================//
    // START: MESSAGING LOGIC
    //==================================================//
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
        let currentChatPartner = {};

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
                    conversationList.innerHTML = '<div class="text-center text-muted p-5">No messages yet.</div>';
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
            currentChatPartner = otherUser;
            const messagesResponse = await fetch(`../ajax/get_messages.php?conversation_id=${conversationId}`);
            const messages = await messagesResponse.json();

            if (!currentChatPartner.fname && messages.length > 0) {
                const otherUserInfo = messages.find(msg => msg.sender_id != currentUserId);
                if (otherUserInfo) currentChatPartner = { fname: otherUserInfo.fname, lname: otherUserInfo.lname, avatar_url: otherUserInfo.avatar_url };
            }

            const avatarSrc = currentChatPartner.avatar_url ? `../${currentChatPartner.avatar_url}` : '../assets/default_avatar.png';
            chatModalHeader.innerHTML = `<img src="${avatarSrc}" class="rounded-circle me-2" width="40" height="40" style="object-fit: cover;"><h5 class="modal-title fs-6 mb-0">${currentChatPartner.fname || 'Chat'} ${currentChatPartner.lname || ''}</h5>`;

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
                    openChatWindow(chatConversationIdInput.value, currentChatPartner);
                    fetchConversations();
                } else { alert(result.message); }
            } catch (error) { console.error('Failed to send message:', error); }
        });

        messagesIconWrapper.parentElement.addEventListener('show.bs.dropdown', () => {
            fetchConversations();
        });
    }

    //==================================================//
    // START: TEACHER-ONLY LEARNING STRAND MODAL LOGIC
    //==================================================//

    // AJAX logic for Creating a Learning Strand
    const createStrandForm = document.getElementById('createStrandForm');
    const createStrandModal = document.getElementById('createStrandModal');
    if (createStrandForm && createStrandModal) {
        createStrandForm.addEventListener('submit', function (e) {
            e.preventDefault();

            fetch('../ajax/create-strand.php', {
                method: 'POST',
                body: new FormData(createStrandForm)
            })
                .then(response => response.json())
                .then(data => {
                    // Hide the modal instance
                    const modalInstance = bootstrap.Modal.getInstance(createStrandModal);
                    if (modalInstance) modalInstance.hide();

                    if (data.status === 'success') {
                        // Success: Reload the page to display the session success message and update the list
                        location.reload();
                    } else {
                        // Error: Show the alert message returned from PHP
                        alert(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while creating the learning strand.');
                });
        });
    }
    // END NEW: AJAX logic for Creating a Learning Strand

    const editStrandModal = document.getElementById('editStrandModal');
    if (editStrandModal) {
        editStrandModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            editStrandModal.querySelector('#edit-strand-id').value = button.getAttribute('data-strand-id');
            editStrandModal.querySelector('#edit-strand-title').value = button.getAttribute('data-title');
            editStrandModal.querySelector('#edit-strand-code').value = button.getAttribute('data-code');
            editStrandModal.querySelector('#edit-grade-level').value = button.getAttribute('data-grade');
            editStrandModal.querySelector('#edit-description').value = button.getAttribute('data-desc');
        });
    }

    const editStrandForm = document.getElementById('editStrandForm');
    if (editStrandForm) {
        editStrandForm.addEventListener('submit', function (e) {
            e.preventDefault();
            fetch('../ajax/edit-strand.php', {
                method: 'POST',
                body: new FormData(editStrandForm)
            })
                .then(response => response.json())
                .then(data => {
                    // FIXED: Check for success status
                    if (data.status === 'success') {
                        // REMOVED alert(data.message) here
                        // Reload the page immediately so PHP can display the session message
                        location.reload();
                    } else {
                        // Keep alert for error messages
                        alert(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while saving changes.');
                });
        });
    }

    const deleteStrandModal = document.getElementById('deleteStrandModal');
    if (deleteStrandModal) {
        deleteStrandModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            // NOTE: The button's attribute for strand ID might be 'data-strand-id' 
            // or 'data-bs-id'. Using 'data-bs-id' as per your existing code.
            const strandId = button.getAttribute('data-bs-id');
            // Set the hidden input in the modal
            deleteStrandModal.querySelector('#deleteStrandId').value = strandId;
        });

        // FIXED: Add event listener to the actual delete button inside the modal
        const confirmDeleteBtn = deleteStrandModal.querySelector('#confirmDeleteStrandBtn');
        if (confirmDeleteBtn) {
            confirmDeleteBtn.addEventListener('click', function () {
                const strandId = deleteStrandModal.querySelector('#deleteStrandId').value;

                // Using fetch API for consistency with the edit logic
                fetch('../ajax/delete-strand.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `strand_id=${encodeURIComponent(strandId)}`
                })
                    .then(response => response.json())
                    .then(data => {
                        // Hide the modal regardless of success/fail
                        const modalInstance = bootstrap.Modal.getInstance(deleteStrandModal);
                        if (modalInstance) modalInstance.hide();

                        if (data.status === 'success') {
                            // Reload the page to show the updated list and the session success message
                            location.reload();
                        } else {
                            // Show alert for error messages
                            alert(data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while deleting the learning strand.');
                        const modalInstance = bootstrap.Modal.getInstance(deleteStrandModal);
                        if (modalInstance) modalInstance.hide();
                    });
            });
        }
    }
    //==================================================//
    // END: LEARNING STRAND MODAL LOGIC
    //==================================================//
});