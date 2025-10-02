document.addEventListener('DOMContentLoaded', () => {
    // Animate buttons on hover
    const controlButtons = document.querySelectorAll('.solid-btn');

    controlButtons.forEach(button => {
        button.addEventListener('mouseenter', () => {
            button.classList.add('shadow-lg');
        });

        button.addEventListener('mouseleave', () => {
            button.classList.remove('shadow-lg');
        });
    });

    // Optional: Show welcome toast if present
    const toast = document.querySelector('.toast');
    if (toast) {
        const bootstrapToast = new bootstrap.Toast(toast);
        bootstrapToast.show();
    }
});