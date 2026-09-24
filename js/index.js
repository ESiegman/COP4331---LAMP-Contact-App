"use strict";

document.addEventListener("DOMContentLoaded", () => {
  redirectIfSignedIn();

  document.getElementById("signInForm").addEventListener("submit", handleSignIn);
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
