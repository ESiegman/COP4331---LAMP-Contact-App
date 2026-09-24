"use strict";

document.addEventListener("DOMContentLoaded", () => {
  redirectIfSignedIn();

  document.getElementById("signInForm").addEventListener("submit", handleSignIn);
  document.getElementById("registerForm").addEventListener("submit", handleRegister);
});

function showMessage(elementId, message, type) {
  const el = document.getElementById(elementId);
  el.className = "small fw-semibold " + (type === "error" ? "text-danger-wcag" : "text-success-wcag");
  el.innerHTML = message;
}

async function handleSignIn(event) {
  event.preventDefault();

  const login = document.getElementById("signInLogin").value.trim();
  const password = document.getElementById("signInPassword").value;
  const button = document.getElementById("signInButton");

  document.getElementById("signInResult").innerHTML = "";

  if (!login || !password) {
    showMessage("signInResult", "<i class='bi bi-exclamation-circle-fill me-1'></i> Enter your username and password.", "error");
    return;
  }

  button.disabled = true;
  try {
    const user = await apiCall("auth.login", "POST", { Login: login, Password: password });
    window.location.href = user.role === "Admin" ? "admin.html" : "contacts.html";
  } catch (err) {
    showMessage("signInResult", "<i class='bi bi-exclamation-circle-fill me-1'></i> " + escapeHtml(err.message), "error");
  } finally {
    button.disabled = false;
  }
}

async function handleRegister(event) {
  event.preventDefault();

  const firstName = document.getElementById("regFirstName").value.trim();
  const lastName = document.getElementById("regLastName").value.trim();
  const login = document.getElementById("regLogin").value.trim();
  const password = document.getElementById("regPassword").value;
  const confirm = document.getElementById("regPasswordConfirm").value;
  const button = document.getElementById("registerButton");

  document.getElementById("registerResult").innerHTML = "";

  if (!firstName || !lastName || !login || !password) {
    showMessage("registerResult", "<i class='bi bi-exclamation-circle-fill me-1'></i> All fields are required.", "error");
    return;
  }

  if (password !== confirm) {
    showMessage("registerResult", "<i class='bi bi-exclamation-circle-fill me-1'></i> Passwords don't match.", "error");
    return;
  }

  button.disabled = true;
  try {
    await apiCall("auth.register", "POST", {
      First_Name: firstName,
      Last_Name: lastName,
      Login: login,
      Password: password,
    });

    // Registration doesn't start a session server-side, so sign in right after.
    const user = await apiCall("auth.login", "POST", { Login: login, Password: password });
    window.location.href = user.role === "Admin" ? "admin.html" : "contacts.html";
  } catch (err) {
    showMessage("registerResult", "<i class='bi bi-exclamation-circle-fill me-1'></i> " + escapeHtml(err.message), "error");
  } finally {
    button.disabled = false;
  }
}
