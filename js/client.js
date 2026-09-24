"use strict";

/**
 * Shared API client for the Contacts Manager frontend.
 * Talks to api/public/index.php using the action-based convention
 * documented in api/README.md.
 */

const API_BASE = (() => {
  const host = window.location.hostname;

  // Local dev: frontend is served by Live Server (or similar) on its own port,
  // separate from the API container. Always point straight at the Docker API,
  // matching docker-compose.yml ("web" maps to localhost:8080) and api/README.md.
  if (host === "localhost" || host === "127.0.0.1") {
    return "http://localhost:8080/index.php";
  }

  // Deployed: frontend and API share the domain, API reachable under /api.
  if (window.location.origin.includes("esiegman")) {
    return "/api/index.php";
  }

  return "https://lamp.esiegman.dev/api/index.php";
})();

class ApiError extends Error {
  constructor(message, status) {
    super(message);
    this.status = status;
  }
}

function cleanParams(params) {
  const out = {};
  Object.keys(params || {}).forEach((key) => {
    const value = params[key];
    if (value !== undefined && value !== null && value !== "") {
      out[key] = value;
    }
  });
  return out;
}

/**
 * @param {string} action     e.g. "contacts.search"
 * @param {string} method     GET | POST | PUT | DELETE
 * @param {object} params     query params (GET/DELETE) or body fields (POST/PUT)
 */
async function apiCall(action, method = "GET", params = {}) {
  const opts = { method, credentials: "include" };
  let url = API_BASE;

  if (method === "GET" || method === "DELETE") {
    const qs = new URLSearchParams({ action, ...cleanParams(params) });
    url += "?" + qs.toString();
  } else {
    opts.headers = { "Content-Type": "application/json" };
    opts.body = JSON.stringify({ action, ...params });
  }

  let response;
  try {
    response = await fetch(url, opts);
  } catch (networkErr) {
    throw new ApiError("Could not reach the server. Check your connection and try again.", 0);
  }

  let body = {};
  try {
    body = await response.json();
  } catch (parseErr) {
    // No JSON body (e.g. empty 204) — leave body as {}
  }

  if (!response.ok || body.success === false) {
    throw new ApiError(body.error || "Something went wrong.", response.status);
  }

  return body.data;
}

/** Returns the current session's user, or null if not signed in. */
async function getCurrentUser() {
  try {
    return await apiCall("auth.me", "GET");
  } catch (err) {
    return null;
  }
}

/**
 * Guards a page: redirects to the sign-in page if not authenticated,
 * or to the correct dashboard if the role doesn't match `role`.
 * Returns the user object, or null (after redirecting).
 */
async function requireAuth(role = null) {
  const user = await getCurrentUser();
  if (!user) {
    window.location.href = "index.html";
    return null;
  }
  if (role && user.role !== role) {
    window.location.href = user.role === "Admin" ? "admin.html" : "contacts.html";
    return null;
  }
  return user;
}

/** For the landing page: bounce a signed-in user straight to their dashboard. */
async function redirectIfSignedIn() {
  const user = await getCurrentUser();
  if (user) {
    window.location.href = user.role === "Admin" ? "admin.html" : "contacts.html";
  }
}

async function signOut() {
  try {
    await apiCall("auth.logout", "POST");
  } finally {
    window.location.href = "index.html";
  }
}

function escapeHtml(value) {
  const div = document.createElement("div");
  div.textContent = value ?? "";
  return div.innerHTML;
}

function debounce(fn, delay = 300) {
  let timer;
  return (...args) => {
    clearTimeout(timer);
    timer = setTimeout(() => fn(...args), delay);
  };
}

function initials(firstName, lastName) {
  const a = (firstName || "").trim().charAt(0);
  const b = (lastName || "").trim().charAt(0);
  return (a + b).toUpperCase() || "?";
}
