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

<body class="bg-light">
    <main class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="register-container">
                    <header>
                        <h1 class="text-center">Registration Form</h1>
                    </header>

                    <form id="registerForm" novalidate>

                        <!-- This is for general errors (e.g., "Server error") -->
                        <div id="general-error" class="alert alert-danger text-center" style="display: none;"></div>

                        <div class="mb-3">
                            <label for="fname" class="form-label">Firstname</label>
                            <input type="text" class="form-control" id="fname" name="fname" required>
                            <!-- Error placeholder for this field -->
                            <div class="invalid-feedback" id="fname-error"></div>
                        </div>
                        <div class="mb-3">
                            <label for="lname" class="form-label">Lastname</label>
                            <input type="text" class="form-control" id="lname" name="lname" required>
                            <!-- Error placeholder for this field -->
                            <div class="invalid-feedback" id="lname-error"></div>
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <input type="text" class="form-control" id="address" name="address" required>
                            <!-- Error placeholder for this field -->
                            <div class="invalid-feedback" id="address-error"></div>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                            <!-- Error placeholder for this field -->
                            <div class="invalid-feedback" id="email-error"></div>
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" required>
                            <!-- Error placeholder for this field -->
                            <div class="invalid-feedback" id="phone-error"></div>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <!-- Error placeholder for this field -->
                            <div class="invalid-feedback" id="password-error"></div>
                        </div>
                        <div class="mb-3">
                            <label for="confirmPassword" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" required>
                            <!-- Error placeholder for this field -->
                            <div class="invalid-feedback" id="confirmPassword-error"></div>
                        </div>
                        <div class="mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="" disabled selected>Select your role</option>
                                <option value="student">Student</option>
                                <option value="teacher">Teacher</option>
                            </select>
                            <!-- Error placeholder for this field -->
                            <div class="invalid-feedback" id="role-error"></div>
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
            </div>
        </div>
    </main>

    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="successModalLabel">Registration Successful</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Please check your email to verify your account.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="goHomeBtn">OK</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>