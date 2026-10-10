const forms = document.querySelectorAll(
  "#registerForm, #loginForm, #forgotPasswordForm, #resetPasswordForm, #profileForm"
);

const statusMessages = {
  registered: "Account created. You can now log in.",
  invalid_credentials: "Incorrect email or password.",
  email_exists: "An account with this email already exists.",
  invalid_data: "Please check the entered data.",
  password_reset: "Password changed. You can now log in.",
  database_error: "The service is temporarily unavailable. Please try again later."
};

const status = new URLSearchParams(window.location.search).get("status");
const statusElement = document.querySelector(".form-status");

if (status && statusElement && statusMessages[status]) {
  statusElement.textContent = statusMessages[status];
  statusElement.hidden = false;
}

forms.forEach((form) => {
  // Отключаем стандартные всплывающие подсказки браузера
  form.noValidate = true;

  const inputs = form.querySelectorAll(
    "input:not([type='submit']):not([type='hidden'])"
  );

  const password = form.querySelector("input[name='password']");
  const repeatPassword = form.querySelector("input[name='repeatPassword']");

  function getErrorMessage(input) {
    if (input.required && input.value.trim() === "") {
      return "This field is required.";
    }

    if (input.validity.typeMismatch) {
      return "Please enter a valid email address.";
    }

    if (input.validity.tooShort) {
      return `Please use at least ${input.minLength} characters.`;
    }

    if (
      input === repeatPassword &&
      password &&
      password.value !== repeatPassword.value
    ) {
      return "Passwords do not match.";
    }

    return "";
  }

  function showError(input) {
    let error = input.nextElementSibling;

    if (!error?.classList.contains("field-error")) {
      error = document.createElement("small");
      error.classList.add("field-error");
      input.insertAdjacentElement("afterend", error);
    }

    const message = getErrorMessage(input);

    error.textContent = message;
    input.setAttribute("aria-invalid", String(Boolean(message)));

    return Boolean(message);
  }

  form.addEventListener("submit", (event) => {
    const invalidInputs = [];

    inputs.forEach((input) => {
      if (showError(input)) {
        invalidInputs.push(input);
      }
    });

    if (invalidInputs.length > 0) {
      event.preventDefault();
      invalidInputs[0].focus();
    }
  });

  inputs.forEach((input) => {
    input.addEventListener("input", () => {
      // Обновляем все сообщения, поскольку пароли связаны
      inputs.forEach(showError);
    });
  });
});
