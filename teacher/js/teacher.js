document.addEventListener('DOMContentLoaded', () => {

    // Get all the links in the sidebar
    const sidebarLinks = document.querySelectorAll('.sidebar-link');

    // Specifically get the 'courses' tab to use as the default
    const coursesTab = document.querySelector('[data-tab="courses"]');

    // --- Part 1: Handle clicks ON the sidebar icons ---
    sidebarLinks.forEach(link => {
        link.addEventListener('click', function (event) {
            // Remove 'active-tab' from all links first
            sidebarLinks.forEach(s_link => s_link.classList.remove('active-tab'));

            // Add 'active-tab' to the one that was just clicked
            this.classList.add('active-tab');
        });
    });

    // --- Part 2: Handle clicks ANYWHERE ELSE on the page ---
    document.addEventListener('click', function (event) {
        // Find the main sidebar element
        const sidebar = document.querySelector('.sidebar');

        // Check if the click happened OUTSIDE the sidebar
        // The .closest() method is perfect for this
        if (!sidebar.contains(event.target)) {
            // If the click was outside, remove 'active-tab' from all links
            sidebarLinks.forEach(link => link.classList.remove('active-tab'));

            // And add it back to the default 'courses' tab
            if (coursesTab) {
                coursesTab.classList.add('active-tab');
            }
        }
    });

    // Special handling for the profile dropdown trigger
    const profileDropdownTrigger = document.querySelector('.dropdown .sidebar-link');
    if (profileDropdownTrigger) {
        profileDropdownTrigger.addEventListener('click', function () {
            // When the profile dropdown is clicked, make it active
            sidebarLinks.forEach(s_link => s_link.classList.remove('active-tab'));
            this.classList.add('active-tab');
        });
    }

    // Edit Strand Modal
    const editStrandModal = document.getElementById('editStrandModal');
    if (editStrandModal) {
        editStrandModal.addEventListener('show.bs.modal', function (event) {
            // Button that triggered the modal
            const button = event.relatedTarget;

            // Extract info from data-bs-* attributes
            const strandId = button.getAttribute('data-bs-id');
            const title = button.getAttribute('data-bs-title');
            const code = button.getAttribute('data-bs-code');
            const grade = button.getAttribute('data-bs-grade');
            const description = button.getAttribute('data-bs-description');

            // Get the modal's form elements
            const modal = this;
            const modalTitleInput = modal.querySelector('#edit-strand-title');
            const modalCodeInput = modal.querySelector('#edit-strand-code');
            const modalGradeSelect = modal.querySelector('#edit-grade-level');
            const modalDescriptionTextarea = modal.querySelector('#edit-description');
            const modalIdInput = modal.querySelector('#edit-strand-id');

            // Update the form fields with the strand's data
            modalIdInput.value = strandId;
            modalTitleInput.value = title;
            modalCodeInput.value = code;
            modalGradeSelect.value = grade;
            modalDescriptionTextarea.value = description;
        });
    }

    // Delete Strand Modal
    const deleteStrandModal = document.getElementById('deleteStrandModal');
    if (deleteStrandModal) {
        deleteStrandModal.addEventListener('show.bs.modal', function (event) {
            // Button that triggered the modal
            const button = event.relatedTarget;
            // Extract info from data-bs-id attribute
            const strandId = button.getAttribute('data-bs-id');

            // Update the modal's hidden input
            const modalInput = deleteStrandModal.querySelector('#deleteStrandId');
            modalInput.value = strandId;
        });
    }
});