document.addEventListener('DOMContentLoaded', () => {
    // This top part for the sidebar is fine.
    const sidebarLinks = document.querySelectorAll('.sidebar-link');
    const coursesTab = document.querySelector('[data-tab="courses"]');
    sidebarLinks.forEach(link => {
        link.addEventListener('click', function (event) {
            sidebarLinks.forEach(s_link => s_link.classList.remove('active-tab'));
            this.classList.add('active-tab');
        });
    });
    document.addEventListener('click', function (event) {
        const sidebar = document.querySelector('.sidebar');
        if (!sidebar.contains(event.target)) {
            sidebarLinks.forEach(link => link.classList.remove('active-tab'));
            if (coursesTab) {
                coursesTab.classList.add('active-tab');
            }
        }
    });
    const profileDropdownTrigger = document.querySelector('.dropdown .sidebar-link');
    if (profileDropdownTrigger) {
        profileDropdownTrigger.addEventListener('click', function () {
            sidebarLinks.forEach(s_link => s_link.classList.remove('active-tab'));
            this.classList.add('active-tab');
        });
    }

    // --- THIS IS THE CORRECTED CODE FOR THE MODALS ---

    // Edit Strand Modal
    const editStrandModal = document.getElementById('editStrandModal');
    if (editStrandModal) {
        editStrandModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;

            // Extract strand data from button
            const strandId = button.getAttribute('data-strand-id');
            const title = button.getAttribute('data-title');
            const code = button.getAttribute('data-code');
            const grade = button.getAttribute('data-grade');
            const description = button.getAttribute('data-desc');

            // Get form fields
            const modalIdInput = document.getElementById('edit-strand-id');
            const modalTitleInput = document.getElementById('edit-strand-title');
            const modalCodeInput = document.getElementById('edit-strand-code');
            const modalGradeSelect = document.getElementById('edit-grade-level');
            const modalDescriptionTextarea = document.getElementById('edit-description');

            // ✅ Set values for visible fields
            modalIdInput.value = strandId;
            modalTitleInput.value = title;
            modalCodeInput.value = code;
            modalGradeSelect.value = grade;
            modalDescriptionTextarea.value = description;

            // ✅ Set value for hidden input used in form submission
            const modalStrandIdInput = document.getElementById('editStrandIdInput');
            if (modalStrandIdInput) {
                modalStrandIdInput.value = strandId;
            }
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
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert(data.message); // Or use a toast
                        location.reload();   // Refresh to show updated strand
                    } else {
                        alert(data.message); // Show error
                    }
                })
                .catch(err => {
                    console.error('Edit strand failed:', err);
                    alert('Something went wrong while updating the strand.');
                });
        });
    }

    // Delete Strand Modal
    const deleteStrandModal = document.getElementById('deleteStrandModal');
    if (deleteStrandModal) {
        deleteStrandModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;

            // CORRECTED: Extract info from data-id attribute (NO "-bs-")
            const strandId = button.getAttribute('data-strand-id');

            // Update the modal's hidden input
            const modalInput = document.getElementById('deleteStrandId');
            modalInput.value = strandId;
        });
    }
});