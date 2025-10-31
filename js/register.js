document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("registerForm");

    // Store references to fields AND their corresponding error divs
    const fields = {
        fname: { input: document.getElementById("fname"), errorDiv: document.getElementById("fname-error") },
        lname: { input: document.getElementById("lname"), errorDiv: document.getElementById("lname-error") },
        address: { input: document.getElementById("address"), errorDiv: document.getElementById("address-error") },
        email: { input: document.getElementById("email"), errorDiv: document.getElementById("email-error") },
        phone: { input: document.getElementById("phone"), errorDiv: document.getElementById("phone-error") },
        password: { input: document.getElementById("password"), errorDiv: document.getElementById("password-error") },
        confirmPassword: { input: document.getElementById("confirmPassword"), errorDiv: document.getElementById("confirmPassword-error") },
        role: { input: document.getElementById("role"), errorDiv: document.getElementById("role-error") },
        gradeLevel: { input: document.getElementById("gradeLevel"), errorDiv: document.getElementById("gradeLevel-error") } // Added grade level
    };

    const registerBtn = document.getElementById("registerBtn");
    const spinner = registerBtn.querySelector(".spinner");
    const btnText = registerBtn.querySelector(".btn-text");
    const successModal = document.getElementById("successModal"); // This is the old modal
    const goHomeBtn = document.getElementById("goHomeBtn");
    const generalErrorDiv = document.getElementById("general-error");

    /**
     * Shows an error message for a specific field.
     */
    function showError(fieldKey, message) {
        const field = fields[fieldKey];
        if (field && field.input && field.errorDiv) {
            field.input.classList.add('is-invalid');
            field.errorDiv.textContent = message;
        }
    }

    /**
     * Clears all error messages and red borders from the form.
     */
    function clearErrors() {
        for (const key in fields) {
            const field = fields[key];
            if (field && field.input && field.errorDiv) {
                field.input.classList.remove('is-invalid');
                field.errorDiv.textContent = '';
            }
        }
        if (generalErrorDiv) {
            generalErrorDiv.textContent = '';
            generalErrorDiv.style.display = 'none';
        }
    }

    form.addEventListener("submit", async (e) => {
        e.preventDefault();
        clearErrors();

        let hasError = false;

        // --- Client-Side Validation ---
        for (const key in fields) {
            const input = fields[key].input;
            if (!input) continue;

            const value = input.value.trim();
            const label = input.previousElementSibling?.textContent || key;

            // Skip gradeLevel validation if role is not student
            if (key === 'gradeLevel') {
                const roleValue = fields.role.input.value;
                if (roleValue === 'student' && !value) {
                    showError(key, `${label} is required for students.`);
                    hasError = true;
                }
                continue; // Skip the general required check for gradeLevel
            }

            if (!value) {
                showError(key, `${label} is required.`);
                hasError = true;
            }
        }

        if (fields.email.input.value.trim() && !/^\S+@\S+\.\S+$/.test(fields.email.input.value.trim())) {
            showError('email', "Please enter a valid email address.");
            hasError = true;
        }

        if (fields.phone.input.value.trim() && !/^(\+63|0)9\d{9}$/.test(fields.phone.input.value.trim().replace(/[\s-]/g, ""))) {
            showError('phone', "Please enter a valid phone number.");
            hasError = true;
        }

        if (fields.password.input.value.trim() && fields.password.input.value.trim().length < 6) {
            showError('password', "Password must be at least 6 characters.");
            hasError = true;
        }

        if (fields.confirmPassword.input.value.trim() && fields.confirmPassword.input.value.trim() !== fields.password.input.value.trim()) {
            showError('confirmPassword', "Passwords do not match.");
            hasError = true;
        }

        if (hasError) return;

        // --- Server-Side Submission ---
        spinner.style.display = "inline-block";
        btnText.textContent = "Registering...";
        registerBtn.disabled = true;

        const formData = new FormData(form);

        try {
            const res = await fetch('register-handler.php', {
                method: "POST",
                body: formData
            });

            let data;
            try {
                data = await res.json();
            } catch (err) {
                console.error("Server returned non-JSON response:", await res.text());
                throw new Error("Invalid response from server. Check register-handler.php.");
            }

            // --- THIS IS THE UPDATED SUCCESS LOGIC ---
            if (data.status === "success_code_sent") {
                // IT WORKED! Redirect to the verify page.
                window.location.href = `verify.php?email=${encodeURIComponent(data.email)}`;
            }
            else if (data.status === "success") {
                // This is the OLD logic that you are seeing.
                // We leave it here as a fallback, but it shouldn't be used
                // if your register-handler.php is correct.
                const modal = new bootstrap.Modal(successModal);
                modal.show();
            }
            // --- END UPDATED SUCCESS LOGIC ---

            else {
                // If the server sends back specific field errors
                if (data.errors && Object.keys(data.errors).length > 0) {
                    for (const fieldKey in data.errors) {
                        showError(fieldKey, data.errors[fieldKey]);
                    }
                } else {
                    // Otherwise, show a general message at the top
                    if (generalErrorDiv) {
                        generalErrorDiv.textContent = data.message || "Registration failed.";
                        generalErrorDiv.style.display = 'block'; // Show it
                    }
                }
            }
        } catch (err) {
            if (generalErrorDiv) {
                generalErrorDiv.textContent = "Server error. Please try again.";
                generalErrorDiv.style.display = 'block'; // Show it
            }
            console.error("Fetch error:", err);
        } finally {
            spinner.style.display = "none";
            btnText.textContent = "Register";
            registerBtn.disabled = false;
        }
    });

    goHomeBtn.addEventListener("click", () => {
        window.location.href = "login.php";
    });

});