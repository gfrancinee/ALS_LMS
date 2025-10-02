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
});