document.addEventListener('DOMContentLoaded', () => {

    // --- Modal Instances ---
    const editModalEl = document.getElementById('editStrandModal');
    const deleteModalEl = document.getElementById('deleteStrandModal');
    const editModal = new bootstrap.Modal(editModalEl);
    const deleteModal = new bootstrap.Modal(deleteModalEl);

    // --- Form Elements ---
    const editStrandForm = document.getElementById('editStrandForm');
    const saveStrandChangesBtn = document.getElementById('saveStrandChangesBtn');
    const deleteStrandTitle = document.getElementById('deleteStrandTitle');
    const confirmDeleteStrandBtn = document.getElementById('confirmDeleteStrandBtn');

    /**
     * Helper function to fetch strand data
     */
    async function getStrandData(id) {
        try {
            // UPDATED PATH: ../../ajax/
            const response = await fetch(`../../ajax/admin-get-strand.php?id=${id}`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const result = await response.json();
            return result;
        } catch (error) {
            console.error("Failed to fetch strand data:", error);
            return { success: false, message: 'Failed to fetch strand data.' };
        }
    }

    // --- Event Listeners ---

    // 1. Listen for clicks on ALL .edit-strand-btn buttons
    document.querySelectorAll('.edit-strand-btn').forEach(button => {
        button.addEventListener('click', async () => {
            const strandId = button.getAttribute('data-id');
            const result = await getStrandData(strandId);

            if (result.success) {
                const data = result.data;
                document.getElementById('editStrandId').value = data.id;
                document.getElementById('editStrandTitle').value = data.strand_title;
                document.getElementById('editStrandCode').value = data.strand_code;
                document.getElementById('editGradeLevel').value = data.grade_level || '';
                editModal.show();
            } else {
                alert(result.message);
            }
        });
    });

    // 2. Listen for clicks on ALL .delete-strand-btn buttons
    document.querySelectorAll('.delete-strand-btn').forEach(button => {
        button.addEventListener('click', async () => {
            const strandId = button.getAttribute('data-id');
            const result = await getStrandData(strandId);

            if (result.success) {
                deleteStrandTitle.textContent = result.data.strand_title;
                confirmDeleteStrandBtn.dataset.id = strandId;
                deleteModal.show();
            } else {
                alert(result.message);
            }
        });
    });

    // 3. Listen for the Edit Form SUBMIT event
    editStrandForm.addEventListener('submit', (e) => {
        e.preventDefault();
        saveStrandChangesBtn.disabled = true;
        saveStrandChangesBtn.textContent = 'Saving...';
        const formData = new FormData(editStrandForm);

        // UPDATED PATH: ../../ajax/
        fetch('../../ajax/admin-update-strand.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    editModal.hide();
                    location.reload();
                } else {
                    alert(data.message || 'Failed to update strand.');
                }
            })
            .catch(error => console.error('Error updating strand:', error))
            .finally(() => {
                saveStrandChangesBtn.disabled = false;
                saveStrandChangesBtn.textContent = 'Save Changes';
            });
    });

    // 4. Listen for the Delete Confirm Button CLICK event
    confirmDeleteStrandBtn.addEventListener('click', () => {
        const strandId = confirmDeleteStrandBtn.dataset.id;
        confirmDeleteStrandBtn.disabled = true;
        confirmDeleteStrandBtn.textContent = 'Deleting...';
        const formData = new FormData();
        formData.append('id', strandId);

        // UPDATED PATH: ../../ajax/
        fetch('../../ajax/admin-delete-strand.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    deleteModal.hide();
                    location.reload();
                } else {
                    alert(data.message || 'Failed to delete strand.');
                }
            })
            .catch(error => console.error('Error deleting strand:', error))
            .finally(() => {
                confirmDeleteStrandBtn.disabled = false;
                confirmDeleteStrandBtn.textContent = 'Delete';
            });
    });

    // 5. Listen for clicks on ALL .manage-materials-btn buttons
    document.querySelectorAll('.manage-materials-btn').forEach(button => {
        button.addEventListener('click', () => {
            const strandId = button.getAttribute('data-id');

            // Redirect to the new page, passing the strand ID
            window.location.href = `manage_materials.php?strand_id=${strandId}`;
        });
    });
});