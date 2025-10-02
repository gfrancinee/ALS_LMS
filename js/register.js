document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("registerForm");
    const fields = {
        fname: document.getElementById("fname"),
        lname: document.getElementById("lname"),
        address: document.getElementById("address"),
        email: document.getElementById("email"),
        phone: document.getElementById("phone"),
        password: document.getElementById("password"),
        confirmPassword: document.getElementById("confirmPassword"),
        role: document.getElementById("role")
    };

    const registerBtn = document.getElementById("registerBtn");
    const spinner = registerBtn.querySelector(".spinner");
    const btnText = registerBtn.querySelector(".btn-text");
    const successModal = document.getElementById("successModal");
    const goHomeBtn = document.getElementById("goHomeBtn");
    let lastRegisteredRole = '';
    const messageBox = createMessageBox();

    form.addEventListener("submit", async (e) => {
        e.preventDefault();
        showMessage(""); // Clear previous messages

        const values = {};
        let hasError = false;

        for (const key in fields) {
            const input = fields[key];
            const value = input.value.trim();
            values[key] = value;

            const label = input.previousElementSibling?.textContent || key;

            if (!value) {
                showMessage(`${label} is required.`);
                input.focus();
                hasError = true;
                break;
            }

            if (key === "email" && !/^\S+@\S+\.\S+$/.test(value)) {
                showMessage("Please enter a valid email address.");
                input.focus();
                hasError = true;
                break;
            }

            if (key === "phone" && !/^(\+63|0)9\d{9}$/.test(value.replace(/[\s-]/g, ""))) {
                showMessage("Please enter a valid Philippine phone number.");
                input.focus();
                hasError = true;
                break;
            }

            if (key === "password" && value.length < 6) {
                showMessage("Password must be at least 6 characters.");
                input.focus();
                hasError = true;
                break;
            }

            if (key === "confirmPassword" && value !== fields.password.value.trim()) {
                showMessage("Passwords do not match.");
                input.focus();
                hasError = true;
                break;
            }

            if (key === "role" && value === "") {
                showMessage("Please select a role.");
                input.focus();
                hasError = true;
                break;
            }
        }

        if (hasError) return;

        // Show spinner
        spinner.style.display = "inline-block";
        btnText.textContent = "Registering...";
        registerBtn.disabled = true;

        const formData = new FormData();
        for (const key in values) {
            formData.append(key, values[key]);
        }

        try {
            const res = await fetch('register-handler.php', {
                method: "POST",
                body: formData
            });

            let data;
            try {
                data = await res.json();
            } catch {
                throw new Error("Invalid JSON response");
            }

            spinner.style.display = "none";
            btnText.textContent = "Register";
            registerBtn.disabled = false;

            if (data.status === "success") {
                lastRegisteredRole = data.role;
                const modal = new bootstrap.Modal(successModal);
                modal.show();
            } else {
                showMessage(data.message || "Registration failed.");
            }
        } catch (err) {
            spinner.style.display = "none";
            btnText.textContent = "Register";
            registerBtn.disabled = false;
            showMessage("Server error. Please try again.");
            console.error("Fetch error:", err);
        }
    });

    goHomeBtn.addEventListener("click", () => {
        if (lastRegisteredRole === "student") {
            // This is the correct path you specified
            window.location.href = "student/student.php";
        } else if (lastRegisteredRole === "teacher") {
            window.location.href = "teacher/teacher.php";
        } else {
            window.location.href = "login.php";
        }
    });

    function showMessage(msg) {
        messageBox.textContent = msg;
        messageBox.className = msg ? "text-danger mt-2 text-center" : "";
    }

    function createMessageBox() {
        let box = document.getElementById("message");
        if (!box) {
            box = document.createElement("div");
            box.id = "message";
            box.className = "text-danger mt-2 text-center";
            form.insertBefore(box, form.firstChild);
        }
        return box;
    }
});