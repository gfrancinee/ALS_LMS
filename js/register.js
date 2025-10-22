document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("registerForm");

    // Store references to fields and their corresponding error divs
    const fields = {
        fname: { input: document.getElementById("fname"), errorDiv: document.getElementById("fname-error") },
        lname: { input: document.getElementById("lname"), errorDiv: document.getElementById("lname-error") },
        address: { input: document.getElementById("address"), errorDiv: document.getElementById("address-error") },
        email: { input: document.getElementById("email"), errorDiv: document.getElementById("email-error") },
        phone: { input: document.getElementById("phone"), errorDiv: document.getElementById("phone-error") },
        password: { input: document.getElementById("password"), errorDiv: document.getElementById("password-error") },
        confirmPassword: { input: document.getElementById("confirmPassword"), errorDiv: document.getElementById("confirmPassword-error") },
        role: { input: document.getElementById("role"), errorDiv: document.getElementById("role-error") }
    };

    const registerBtn = document.getElementById("registerBtn");
    const spinner = registerBtn.querySelector(".spinner");
    const btnText = registerBtn.querySelector(".btn-text");
    const successModal = document.getElementById("successModal");
    const goHomeBtn = document.getElementById("goHomeBtn");
    const generalErrorDiv = document.getElementById("general-error");

    // Helper function to show an error for a specific field
    function showError(fieldKey, message) {
        const field = fields[fieldKey];
        if (field && field.input && field.errorDiv) {
            field.input.classList.add('is-invalid'); // Add red border
            field.errorDiv.textContent = message;    // Show error message
        }
    }

    // Helper function to clear all previous errors
    function clearErrors() {
        for (const key in fields) {
            const field = fields[key];
            if (field && field.input && field.errorDiv) {
                field.input.classList.remove('is-invalid');
                field.errorDiv.textContent = '';
            }
        }
        if (generalErrorDiv) generalErrorDiv.textContent = '';
    }

    form.addEventListener("submit", async (e) => {
        e.preventDefault();
        clearErrors();

        const values = {};
        let hasError = false;

        // --- Client-Side Validation ---
        // This loop now checks all fields before stopping
        for (const key in fields) {
            const input = fields[key].input;
            // Check if input exists before trying to read its value
            if (!input) {
                console.error(`Missing input element for: ${key}`);
                continue; // Skip this field
            }
            const value = input.value.trim();
            values[key] = value;

            const label = input.previousElementSibling?.textContent || key;

            if (!value) {
                showError(key, `${label} is required.`);
                hasError = true;
            }
        }

        // Specific format validations
        if (values.email && !/^\S+@\S+\.\S+$/.test(values.email)) {
            showError('email', "Please enter a valid email address.");
            hasError = true;
        }

        if (values.phone && !/^(\+63|0)9\d{9}$/.test(values.phone.replace(/[\s-]/g, ""))) {
            showError('phone', "Please enter a valid phone number.");
            hasError = true;
        }

        if (values.password && values.password.length < 6) {
            showError('password', "Password must be at least 6 characters.");
            hasError = true;
        }

        if (values.confirmPassword && values.confirmPassword !== values.password) {
            showError('confirmPassword', "Passwords do not match.");
            hasError = true;
        }

        if (hasError) return; // Stop if there are any client-side errors

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
            } catch {
                throw new Error("Invalid JSON response from server. Check register-handler.php for errors.");
            }

            if (data.status === "success") {
                const modal = new bootstrap.Modal(successModal);
                modal.show();
            } else {
                // If the server sends back specific field errors
                if (data.errors) {
                    for (const fieldKey in data.errors) {
                        showError(fieldKey, data.errors[fieldKey]);
                    }
                } else {
                    // Otherwise, show a general message
                    if (generalErrorDiv) generalErrorDiv.textContent = data.message || "Registration failed.";
                }
            }
        } catch (err) {
            if (generalErrorDiv) generalErrorDiv.textContent = "Server error. Please try again.";
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