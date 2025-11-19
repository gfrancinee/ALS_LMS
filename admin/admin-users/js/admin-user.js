document.addEventListener('DOMContentLoaded', () => {

    // --- Modal Instances ---
    const editModalEl = document.getElementById('editUserModal');
    const verifyModalEl = document.getElementById('verifyUserModal');
    const deleteModalEl = document.getElementById('deleteUserModal');

    // Create Bootstrap Modal instances
    const editModal = new bootstrap.Modal(editModalEl);
    const verifyModal = new bootstrap.Modal(verifyModalEl);
    const deleteModal = new bootstrap.Modal(deleteModalEl);

    // --- Form Elements ---
    const editUserForm = document.getElementById('editUserForm');
    const editUserError = document.getElementById('editUserError');
    const saveUserChangesBtn = document.getElementById('saveUserChangesBtn');

    const editRoleSelect = document.getElementById('editRole');
    const editGradeLevelGroup = document.getElementById('editGradeLevelGroup');

    const verifyUserEmail = document.getElementById('verifyUserEmail');
    const confirmVerifyBtn = document.getElementById('confirmVerifyBtn');

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
    document.getElementById('editRole').addEventListener('change', function () {
        const lrnGroup = document.getElementById('editLrnGroup');
        if (this.value === 'student') {
            lrnGroup.style.display = 'block';
        } else {
            lrnGroup.style.display = 'none';
        }
        // Also toggle grade level using your helper
        toggleGradeLevelField(this.value);
    });

    // When modal opens, ensure LRN field visibility is correct based on current role
    editModalEl.addEventListener('shown.bs.modal', function () {
        const role = document.getElementById('editRole').value;
        const lrnGroup = document.getElementById('editLrnGroup');
        if (role === 'student') {
            lrnGroup.style.display = 'block';
        } else {
            lrnGroup.style.display = 'none';
        }
    });

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

                    // --- ADDED: Populate LRN ---
                    if (document.querySelector('#editLrn')) {
                        document.querySelector('#editLrn').value = data.lrn || '';
                    }

                    // Update visibility for both Grade and LRN
                    toggleGradeLevelField(data.role);
                    const lrnGroup = document.getElementById('editLrnGroup');
                    if (lrnGroup) {
                        lrnGroup.style.display = (data.role === 'student') ? 'block' : 'none';
                    }

                    editModal.show();
                })
                .catch(error => {
                    console.error('Error fetching user:', error);
                    alert('Failed to load user data.');
                });
        });
    });

    document.querySelectorAll('.verify-btn').forEach(button => {
        button.addEventListener('click', () => {
            const userId = button.getAttribute('data-id');
            const userEmail = button.getAttribute('data-email');
            // NEW: Get LRN
            const userLrn = button.getAttribute('data-lrn') || 'N/A';

            // Update Modal Content
            document.getElementById('verifyUserEmail').textContent = userEmail;

            // NEW: Update LRN in Modal
            const lrnSpan = document.getElementById('verifyUserLrn');
            if (lrnSpan) {
                lrnSpan.textContent = userLrn;
                // Optional: Color code it if missing
                if (userLrn === 'N/A' || userLrn === '') {
                    lrnSpan.innerHTML = '<span class="text-danger fst-italic">No LRN provided</span>';
                }
            }

            document.getElementById('confirmVerifyBtn').dataset.id = userId;
            verifyModal.show();
        });
    });

    // 3. Listen for clicks on ALL .delete-btn buttons
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

    // 6. Listen for the Delete Confirm Button CLICK event
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', async () => {
            const userId = confirmDeleteBtn.dataset.id;
            confirmDeleteBtn.disabled = true;
            confirmDeleteBtn.textContent = 'Deleting...';

            try {
                const response = await fetch('../../ajax/delete-user.php', { // Kept your path style
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ id: userId })
                });

                const data = await response.json();

                if (data.success) {
                    deleteModal.hide();
                    location.reload();
                } else {
                    alert(data.error || 'Failed to delete user.');
                }

            } catch (error) {
                console.error('Error deleting user:', error);
                alert('An unexpected error occurred. Check the console.');

            } finally {
                confirmDeleteBtn.disabled = false;
                confirmDeleteBtn.textContent = 'Delete';
            }
        });
    }

    // 7. Listen for ROLE dropdown changes (You already had this logic, ensuring it's hooked up)
    editRoleSelect.addEventListener('change', () => {
        toggleGradeLevelField(editRoleSelect.value);
    });

});