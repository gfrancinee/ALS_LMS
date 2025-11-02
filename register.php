<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>ALS Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/register.css" />
    <script src="js/register.js" defer></script>
</head>

<body>

    <main class="container d-flex justify-content-center min-vh-100">
        <div class="register-container">
            <header>
                <h1 class="text-center">Registration Form</h1>
            </header>

            <form id="registerForm" novalidate>

                <div id="general-error" class="alert alert-danger text-center" style="display: none;"></div>
                <div class="mb-3">
                    <label for="fname" class="form-label">Firstname</label>
                    <input type="text" class="form-control" id="fname" name="fname" required>
                    <div class="invalid-feedback" id="fname-error"></div>
                </div>
                <div class="mb-3">
                    <label for="lname" class="form-label">Lastname</label>
                    <input type="text" class="form-control" id="lname" name="lname" required>
                    <div class="invalid-feedback" id="lname-error"></div>
                </div>
                <div class="mb-3">
                    <label for="address" class="form-label">Address</label>
                    <input type="text" class="form-control" id="address" name="address" required>
                    <div class="invalid-feedback" id="address-error"></div>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                    <div class="invalid-feedback" id="email-error"></div>
                </div>
                <div class="mb-3">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input type="tel" class="form-control" id="phone" name="phone" required>
                    <div class="invalid-feedback" id="phone-error"></div>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                    <div class="invalid-feedback" id="password-error"></div>
                </div>
                <div class="mb-3">
                    <label for="confirmPassword" class="form-label">Confirm Password</label>
                    <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" required>
                    <div class="invalid-feedback" id="confirmPassword-error"></div>
                </div>
                <div class="mb-3">
                    <label for="role" class="form-label">Role</label>
                    <select class="form-select" id="role" name="role" required>
                        <option value="" disabled selected>Select your role</option>
                        <option value="student">Student</option>
                        <option value="teacher">Teacher</option>
                    </select>
                    <div class="invalid-feedback" id="role-error"></div>
                </div>
                <div class="mb-3" id="gradeLevelContainer" style="display: none;">
                    <label for="gradeLevel" class="form-label">Grade Level</label>
                    <select class="form-select" id="gradeLevel" name="gradeLevel">
                        <option value="" disabled selected>Select your grade level</option>
                        <option value="grade_11">Grade 11</option>
                        <option value="grade_12">Grade 12</option>
                    </select>
                    <div class="invalid-feedback" id="gradeLevel-error"></div>
                </div>

                <button id="registerBtn" type="submit" class="btn btn-primary w-100">
                    <span class="btn-text">Register</span>
                    <span class="spinner-border spinner-border-sm text-light ms-2 spinner" role="status" style="display:none;"></span>
                </button>
            </form>
            <p class="text-center mt-3">
                Already registered? <a href="login.php">Log In</a>
            </p>
        </div>

    </main>

    <div class="modal fade" id="successModal" tabindex="-1" ...>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Show/hide grade level based on role selection
        document.getElementById('role').addEventListener('change', function() {
            const gradeLevelContainer = document.getElementById('gradeLevelContainer');
            const gradeLevelSelect = document.getElementById('gradeLevel');

            if (this.value === 'student') {
                gradeLevelContainer.style.display = 'block';
                gradeLevelSelect.setAttribute('required', 'required');
            } else {
                gradeLevelContainer.style.display = 'none';
                gradeLevelSelect.removeAttribute('required');
                gradeLevelSelect.value = ''; // Clear selection
            }
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Find the register form container
            const registerContainer = document.querySelector('.register-container');

            // Find the "Log In" link at the bottom (I'm using a very specific selector)
            const loginLink = document.querySelector('.register-container p a[href="login.php"]');

            if (registerContainer && loginLink) {
                loginLink.addEventListener('click', function(e) {
                    // 1. Stop the browser from navigating instantly
                    e.preventDefault();

                    // 2. Get the destination URL
                    const destination = this.href;

                    // 3. Add the animation class
                    registerContainer.classList.add('fade-out-right');

                    // 4. Wait 500ms (for the animation) then go to the new page
                    setTimeout(() => {
                        window.location.href = destination;
                    }, 500); // This MUST match your CSS animation time (0.5s)
                });
            }
        });
    </script>
</body>

</html>