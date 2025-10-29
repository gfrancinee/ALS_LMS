document.addEventListener('DOMContentLoaded', () => {

    // --- Modal Instances ---
    // "addModal" has been REMOVED
    const editModal = new bootstrap.Modal(document.getElementById('editMaterialModal'));
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteMaterialModal'));

    // --- Forms & Buttons ---
    // "addMaterialForm" and "addMaterialBtn" have been REMOVED
    const editMaterialForm = document.getElementById('editMaterialForm');
    const saveMaterialChangesBtn = document.getElementById('saveMaterialChangesBtn');
    const confirmDeleteMaterialBtn = document.getElementById('confirmDeleteMaterialBtn');

    // --- Type Dropdowns ---
    // "addTypeSelect" has been REMOVED
    const editTypeSelect = document.getElementById('editType');

    /**
     * Controls which input field (File or Link) is visible based on the selected type.
     */
    function toggleFields(type, formType) {
        const fileGroup = document.getElementById(`${formType}FileGroup`);
        const linkGroup = document.getElementById(`${formType}LinkGroup`);

        if (type === 'link') {
            fileGroup.style.display = 'none';
            linkGroup.style.display = 'block';
        } else if (type === 'file' || type === 'video' || type === 'audio' || type === 'image') {
            fileGroup.style.display = 'block';
            linkGroup.style.display = 'none';
        } else {
            fileGroup.style.display = 'none';
            linkGroup.style.display = 'none';
        }
    }

    // --- Event Listeners for Type Toggling ---
    // "addTypeSelect" listener has been REMOVED
    editTypeSelect.addEventListener('change', () => {
        toggleFields(editTypeSelect.value, 'edit');
    });

    /**
     * Helper function to fetch material data
     */
    async function getMaterialData(id) {
        try {
            // Path: ../../ajax/get-material.php
            const response = await fetch(`../../ajax/admin-get-material.php?id=${id}`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return await response.json();
        } catch (error) {
            console.error("Failed to fetch material data:", error);
            return { success: false, message: 'Failed to fetch material data.' };
        }
    }

    // --- Main Event Listeners for Buttons ---

    // "Add Material" form listener has been REMOVED

    // 1. EDIT: Listen for clicks on ALL .edit-material-btn buttons
    document.querySelectorAll('.edit-material-btn').forEach(button => {
        button.addEventListener('click', async () => {
            const materialId = button.getAttribute('data-id');
            const result = await getMaterialData(materialId);

            if (result.success) {
                const data = result.data;
                // Populate the edit modal
                document.getElementById('editMaterialId').value = data.id;
                document.getElementById('editLabel').value = data.label;
                document.getElementById('editType').value = data.type;
                document.getElementById('editLink').value = data.link_url || '';

                if (data.type === 'link') {
                    document.getElementById('currentFile').textContent = 'N/A';
                } else {
                    document.getElementById('currentFile').textContent = data.file_path ? data.file_path.split('/').pop() : 'None';
                }

                toggleFields(data.type, 'edit');
                editModal.show();
            } else {
                alert(result.message);
            }
        });
    });

    // 2. EDIT: Handle the "Edit Material" form submission
    editMaterialForm.addEventListener('submit', (e) => {
        e.preventDefault();
        saveMaterialChangesBtn.disabled = true;
        saveMaterialChangesBtn.textContent = 'Saving...';

        const formData = new FormData(editMaterialForm);

        fetch('../../ajax/admin-update-material.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    editModal.hide();
                    location.reload();
                } else {
                    alert(data.message || 'Failed to update material.');
                }
            })
            .catch(error => console.error('Error updating material:', error))
            .finally(() => {
                saveMaterialChangesBtn.disabled = false;
                saveMaterialChangesBtn.textContent = 'Save Changes';
            });
    });


    // 3. DELETE: Listen for clicks on ALL .delete-material-btn buttons
    document.querySelectorAll('.delete-material-btn').forEach(button => {
        button.addEventListener('click', async () => {
            const materialId = button.getAttribute('data-id');
            const result = await getMaterialData(materialId);

            if (result.success) {
                document.getElementById('deleteMaterialLabel').textContent = result.data.label;
                confirmDeleteMaterialBtn.dataset.id = materialId;
                deleteModal.show();
            } else {
                alert(result.message);
            }
        });
    });

    // 4. DELETE: Handle the "Delete Confirm" button click
    confirmDeleteMaterialBtn.addEventListener('click', () => {
        const materialId = confirmDeleteMaterialBtn.dataset.id;
        confirmDeleteMaterialBtn.disabled = true;
        confirmDeleteMaterialBtn.textContent = 'Deleting...';

        const formData = new FormData();
        formData.append('id', materialId);

        fetch('../../ajax/admin-delete-material.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    deleteModal.hide();
                    location.reload();
                } else {
                    alert(data.message || 'Failed to delete material.');
                }
            })
            .catch(error => console.error('Error deleting material:', error))
            .finally(() => {
                confirmDeleteMaterialBtn.disabled = false;
                confirmDeleteMaterialBtn.textContent = 'Delete';
            });
    });
});