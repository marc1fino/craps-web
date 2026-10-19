// PHP sends a code in the URL; select the corresponding message here.
const errorMessages = new Map([
    ['invalid_credentials', 'Incorrect username or password.'],
    ['login_unavailable', 'Login is currently unavailable. Please try again later.'],
    ['invalid_data', 'The submitted data is invalid. Please fill out the form again.'],
    ['empty_fields', 'Enter your username and both passwords.'],
    ['username_too_long', 'Your username must be no longer than 30 characters.'],
    ['password_mismatch', 'The passwords do not match.'],
    ['user_exists', 'This username already exists. Log in or sign up with a different username.'],
    ['server_error', 'Registration could not be completed. Please try again later.'],
    ['record_not_created', 'Your account was created, but the win and loss counters could not be initialized.'],
]);

const errorCode = new URLSearchParams(window.location.search).get('error');
const errorMessage = document.getElementById('error-message');

if (errorMessage && errorMessages.has(errorCode)) {
    errorMessage.textContent = errorMessages.get(errorCode);
    errorMessage.hidden = false;
}
