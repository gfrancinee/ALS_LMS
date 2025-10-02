document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("loginForm");
    const emailInput = document.getElementById("email");
    const passwordInput = document.getElementById("password");
    const emailError = document.getElementById("emailError");
    const passwordError = document.getElementById("passwordError");
    const loginBtn = document.getElementById("loginBtn");
    const spinner = loginBtn.querySelector(".spinner-border");
    const btnText = loginBtn.querySelector(".btn-text");

    form.addEventListener("submit", (e) => {
        e.preventDefault();

        emailError.textContent = "";
        emailError.style.display = "none";
        passwordError.textContent = "";
        passwordError.style.display = "none";

        const email = emailInput.value.trim();
        const password = passwordInput.value.trim();
        let hasError = false;

        if (!email) {
            emailError.textContent = "Email is required.";
            emailError.style.display = "block";
            hasError = true;
        } else if (!/^\S+@\S+\.\S+$/.test(email)) {
            emailError.textContent = "Please enter a valid email address.";
            hasError = true;
        }

        if (!password) {
            passwordError.textContent = "Password is required.";
            passwordError.style.display = "block";
            hasError = true;
        }

        if (hasError) return;

        // Show spinner
        spinner.style.display = "inline-block";
        btnText.textContent = "Logging in...";
        loginBtn.disabled = true;

        // Delay actual submission
        setTimeout(() => {
            form.submit();
        }, 300);
    });
});