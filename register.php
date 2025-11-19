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

    <main class="container d-flex justify-content-center align-items-center min-vh-100">
        <div class="register-container">
            <header>
                <h1 class="text-center register-title">Create Account</h1>
            </header>

            <form id="registerForm" novalidate>
                <div id="general-error" class="alert alert-danger text-center shadow-sm border-0 rounded-3 small" style="display: none;"></div>

                <div class="row row-gap-3 mb-3">
                    <div class="col-md-6">
                        <label for="fname" class="form-label">First Name</label>
                        <input type="text" class="form-control" id="fname" name="fname" placeholder="e.g. Juan" required>
                        <div class="invalid-feedback" id="fname-error"></div>
                    </div>
                    <div class="col-md-6">
                        <label for="lname" class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="lname" name="lname" placeholder="e.g. Dela Cruz" required>
                        <div class="invalid-feedback" id="lname-error"></div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="address" class="form-label">Address</label>
                    <input type="text" class="form-control" id="address" name="address" placeholder="House No., Street, City" required>
                    <div class="invalid-feedback" id="address-error"></div>
                </div>

                <div class="row row-gap-3 mb-3">
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" required>
                        <div class="invalid-feedback" id="email-error"></div>
                    </div>
                    <div class="col-md-6">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" id="phone" name="phone" placeholder="0912 345 6789" required>
                        <div class="invalid-feedback" id="phone-error"></div>
                    </div>
                </div>

                <div class="row row-gap-3 mb-3">
                    <div class="col-md-6">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" placeholder="Min. 6 characters" required>
                        <div class="invalid-feedback" id="password-error"></div>
                    </div>
                    <div class="col-md-6">
                        <label for="confirmPassword" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" placeholder="Re-enter password" required>
                        <div class="invalid-feedback" id="confirmPassword-error"></div>
                    </div>
                </div>

                <hr class="my-4 opacity-10">

                <div class="mb-3">
                    <label for="role" class="form-label">I am a...</label>
                    <select class="form-select" id="role" name="role" required>
                        <option value="" disabled selected>Select your role</option>
                        <option value="student">Student</option>
                        <option value="teacher">Teacher</option>
                    </select>
                    <div class="invalid-feedback" id="role-error"></div>
                </div>

                <div class="row row-gap-3 mb-4">
                    <div class="col-md-6" id="lrnContainer" style="display: none;">
                        <label for="lrn" class="form-label">LRN (12 Digits)</label>
                        <input type="text" class="form-control" id="lrn" name="lrn"
                            placeholder="12-digit LRN"
                            minlength="12" maxlength="12" pattern="[0-9]{12}"
                            oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                        <div class="invalid-feedback" id="lrn-error"></div>
                    </div>

                    <div class="col-md-6" id="gradeLevelContainer" style="display: none;">
                        <label for="gradeLevel" class="form-label">Grade Level</label>
                        <select class="form-select" id="gradeLevel" name="gradeLevel">
                            <option value="" disabled selected>Select Grade Level</option>
                            <option value="grade_11">Grade 11</option>
                            <option value="grade_12">Grade 12</option>
                        </select>
                        <div class="invalid-feedback" id="gradeLevel-error"></div>
                    </div>
                </div>

                <button id="registerBtn" type="submit" class="btn btn-primary w-100 btn-register">
                    <span class="btn-text">Create Account</span>
                    <span class="spinner-border spinner-border-sm text-light ms-2 spinner" role="status" style="display:none;"></span>
                </button>
            </form>

            <p class="text-center mt-4 mb-0 text-muted small">
                Already registered? <a href="login.php" class="text-primary text-decoration-none fw-semibold">Log In</a>
            </p>
        </div>

    </main>

    <div class="modal fade" id="successModal" tabindex="-1">
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Show/hide grade level AND LRN based on role selection
        document.getElementById('role').addEventListener('change', function() {
            const gradeLevelContainer = document.getElementById('gradeLevelContainer');
            const gradeLevelSelect = document.getElementById('gradeLevel');

            // New LRN elements
            const lrnContainer = document.getElementById('lrnContainer');
            const lrnInput = document.getElementById('lrn');

            if (this.value === 'student') {
                // Show both for students
                gradeLevelContainer.style.display = 'block';
                gradeLevelSelect.setAttribute('required', 'required');

                lrnContainer.style.display = 'block';
                lrnInput.setAttribute('required', 'required');
            } else {
                // Hide both for teachers
                gradeLevelContainer.style.display = 'none';
                gradeLevelSelect.removeAttribute('required');
                gradeLevelSelect.value = '';

                lrnContainer.style.display = 'none';
                lrnInput.removeAttribute('required');
                lrnInput.value = '';
            }
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const registerContainer = document.querySelector('.register-container');
            const loginLink = document.querySelector('.register-container p a[href="login.php"]');

            if (registerContainer && loginLink) {
                loginLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    const destination = this.href;
                    registerContainer.classList.add('fade-out-right');
                    setTimeout(() => {
                        window.location.href = destination;
                    }, 500);
                });
            }
        });
    </script>
</body>

</html>