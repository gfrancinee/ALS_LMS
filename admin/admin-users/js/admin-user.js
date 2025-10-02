document.addEventListener('DOMContentLoaded', () => {
    const editButtons = document.querySelectorAll('.edit-btn');

    editButtons.forEach(button => {
        button.addEventListener('click', () => {
            const userId = button.getAttribute('data-id');

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

                    const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
                    modal.show();
                })
                .catch(error => {
                    console.error('Error fetching user:', error);
                    alert('Failed to load user data.');
                });
        });
    });
});