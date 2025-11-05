document.addEventListener('DOMContentLoaded', () => {

    // --- Modal Instances ---
    const editModalEl = document.getElementById('editUserModal');
    const verifyModalEl = document.getElementById('verifyUserModal');
    const deleteModalEl = document.getElementById('deleteUserModal'); // ADDED

    // Create Bootstrap Modal instances
    const editModal = new bootstrap.Modal(editModalEl);
    const verifyModal = new bootstrap.Modal(verifyModalEl);
    const deleteModal = new bootstrap.Modal(deleteModalEl); // ADDED

    // --- Form Elements ---
    const editUserForm = document.getElementById('editUserForm');
    const editUserError = document.getElementById('editUserError');
    const saveUserChangesBtn = document.getElementById('saveUserChangesBtn');

    const editRoleSelect = document.getElementById('editRole');
    const editGradeLevelGroup = document.getElementById('editGradeLevelGroup');

    const verifyUserEmail = document.getElementById('verifyUserEmail');
    const confirmVerifyBtn = document.getElementById('confirmVerifyBtn');

    // --- ADDED: Delete Modal Elements ---
    const deleteUserEmail = document.getElementById('deleteUserEmail');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

    /**
     * Helper function to show/hide the Grade Level field based on role
     */
    function toggleGradeLevelField(role) {
        if (role === 'student') {
            editGradeLevelGroup.style.display = 'block';
        } else {
            editGradeLevelGroup.style.display = 'none';
        }
    }

    // --- Event Listeners ---

    // 1. Listen for clicks on ALL .edit-btn buttons
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', () => {
            const userId = button.getAttribute('data-id');

            // Reset form state
            editUserForm.reset();
            editUserError.style.display = 'none';

            fetch(`../../ajax/get-user.php?id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert(data.error);
                        return;
                    }
                    document.querySelector('#editUserId').value = data.id;
                    document.querySelector('#editFname').value = data.fname;
                    document.querySelector('#editLname').value = data.lname;
                    document.querySelector('#editEmail').value = data.email;
                    document.querySelector('#editRole').value = data.role;
                    document.querySelector('#editGradeLevel').value = data.grade_level || '';
                    toggleGradeLevelField(data.role);
                    editModal.show();
                })
                .catch(error => {
                    console.error('Error fetching user:', error);
                    alert('Failed to load user data.');
                });
        });
    });

    // 2. Listen for clicks on ALL .verify-btn buttons
    document.querySelectorAll('.verify-btn').forEach(button => {
        button.addEventListener('click', () => {
            const userId = button.getAttribute('data-id');
            const userEmail = button.getAttribute('data-email');
            verifyUserEmail.textContent = userEmail;
            confirmVerifyBtn.dataset.id = userId;
            verifyModal.show();
        });
    });

    // 3. --- ADDED: Listen for clicks on ALL .delete-btn buttons ---
    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', () => {
            const userId = button.getAttribute('data-id');
            const userEmail = button.getAttribute('data-email');

            // Pass data to the delete modal
            deleteUserEmail.textContent = userEmail;
            confirmDeleteBtn.dataset.id = userId;

            deleteModal.show();
        });
    });


    // 4. Listen for the Edit Form SUBMIT event
    editUserForm.addEventListener('submit', (e) => {
        e.preventDefault();
        saveUserChangesBtn.disabled = true;
        saveUserChangesBtn.textContent = 'Saving...';
        editUserError.style.display = 'none';

        const formData = new FormData(editUserForm);

        fetch('../../ajax/update-user.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    editModal.hide();
                    location.reload();
                } else {
                    editUserError.textContent = data.message;
                    editUserError.style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error updating user:', error);
                editUserError.textContent = 'An unexpected error occurred.';
                editUserError.style.display = 'block';
            })
            .finally(() => {
                saveUserChangesBtn.disabled = false;
                saveUserChangesBtn.textContent = 'Save Changes';
            });
    });

    // 5. Listen for the Verify Confirm Button CLICK event
    confirmVerifyBtn.addEventListener('click', () => {
        const userId = confirmVerifyBtn.dataset.id;
        confirmVerifyBtn.disabled = true;
        confirmVerifyBtn.textContent = 'Verifying...';

        const formData = new FormData();
        formData.append('id', userId);

        fetch('../../ajax/verify-user.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    verifyModal.hide();
                    location.reload();
                } else {
                    alert(data.message || 'Failed to verify user.');
                }
            })
            .catch(error => {
                console.error('Error verifying user:', error);
                alert('An unexpected error occurred.');
            })
            .finally(() => {
                confirmVerifyBtn.disabled = false;
                confirmVerifyBtn.textContent = 'Verify';
            });
    });

    // Get the modal and button elements

    if (confirmDeleteBtn && deleteModal) {

        // --- ADDED: Listen for the Delete Confirm Button CLICK event ---
        confirmDeleteBtn.addEventListener('click', async () => {
            const userId = confirmDeleteBtn.dataset.id; // Get the ID from the button

            // Show loading state
            confirmDeleteBtn.disabled = true;
            confirmDeleteBtn.textContent = 'Deleting...';

            try {
                // --- THIS IS THE FIX ---
                const response = await fetch('/ALS_LMS/ajax/delete-user.php', { // 1. Fixed path and filename
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json' // 2. Set header to JSON
                    },
                    body: JSON.stringify({ id: userId }) // 3. Send data as a JSON string
                });
                // --- END OF FIX ---

                const data = await response.json();

                if (data.success) {
                    // Success! Hide modal and reload page
                    deleteModal.hide();
                    location.reload();
                } else {
                    // Show an alert on failure
                    alert(data.error || 'Failed to delete user.');
                }

            } catch (error) {
                // This catches network errors or JSON parsing errors (like "Unexpected token 'I'")
                console.error('Error deleting user:', error);
                alert('An unexpected error occurred. Check the console.');

            } finally {
                // Reset button state regardless of success or failure
                confirmDeleteBtn.disabled = false;
                confirmDeleteBtn.textContent = 'Delete';
            }
        });
    }

    // You also need a function to set the ID on the button when the modal is opened.
    // This is just an example:
    document.body.addEventListener('click', (e) => {
        if (e.target.matches('.delete-user-btn')) { // Assume your trash can icon has this class
            const userId = e.target.dataset.id;
            if (confirmDeleteBtn) {
                // Set the ID on the confirm button so we know who to delete
                confirmDeleteBtn.dataset.id = userId;
            }
        }
    });

    // 7. Listen for ROLE dropdown changes
    editRoleSelect.addEventListener('change', () => {
        toggleGradeLevelField(editRoleSelect.value);
    });

});