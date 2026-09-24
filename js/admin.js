"use strict";

let adminUser = null;
let usersById = new Map();
let pendingDisableId = null;
let pendingPasswordUserId = null;
let activeContactsUserId = null;

const createUserModal = () => bootstrap.Modal.getOrCreateInstance(document.getElementById("createUserModal"));
const disableModal = () => bootstrap.Modal.getOrCreateInstance(document.getElementById("disableModal"));
const resetPasswordModal = () => bootstrap.Modal.getOrCreateInstance(document.getElementById("resetPasswordModal"));
const userContactsModal = () => bootstrap.Modal.getOrCreateInstance(document.getElementById("userContactsModal"));

document.addEventListener("DOMContentLoaded", async () => {
  adminUser = await requireAuth("Admin");
  if (!adminUser) return;

  document.getElementById("userName").innerHTML =
    `<i class="bi bi-person-circle me-1 text-primary"></i> ${escapeHtml(adminUser.login)}`;

  document.getElementById("logoutButton").addEventListener("click", signOut);
  document.getElementById("userSearchInput").addEventListener("input", debounce(loadUsers, 300));
  document.getElementById("createUserForm").addEventListener("submit", createUser);
  document.getElementById("confirmDisableButton").addEventListener("click", confirmDisable);
  document.getElementById("resetPasswordForm").addEventListener("submit", submitResetPassword);
  document.getElementById("userContactsSearch").addEventListener("input", debounce(loadUserContacts, 300));
  document.getElementById("usersTableBody").addEventListener("click", handleUsersTableClick);

  document.getElementById("createUserModal").addEventListener("hidden.bs.modal", () => {
    document.getElementById("createUserForm").reset();
    document.getElementById("createUserResult").innerHTML = "";
  });
  document.getElementById("resetPasswordModal").addEventListener("hidden.bs.modal", () => {
    document.getElementById("resetPasswordForm").reset();
    document.getElementById("resetPasswordResult").innerHTML = "";
  });

  loadUsers();
});

function handleUsersTableClick(event) {
  const btn = event.target.closest("button[data-action]");
  if (!btn) return;

  const user = usersById.get(Number(btn.dataset.id));
  if (!user) return;
  const name = `${user.First_Name} ${user.Last_Name}`;

  if (btn.dataset.action === "view-contacts") {
    openUserContacts(user.ID, name);
  } else if (btn.dataset.action === "reset-password") {
    openResetPassword(user.ID, name);
  } else if (btn.dataset.action === "disable") {
    openDisableUser(user.ID, name);
  }
}

async function loadUsers() {
  const query = document.getElementById("userSearchInput").value.trim();
  const tbody = document.getElementById("usersTableBody");
  const emptyState = document.getElementById("usersEmptyState");

  try {
    const users = await apiCall("admin.users.search", "GET", { query });
    usersById = new Map(users.map((u) => [Number(u.ID), u]));
    renderUsers(users);
    emptyState.classList.toggle("d-none", users.length > 0);
  } catch (err) {
    tbody.innerHTML = "";
    emptyState.classList.remove("d-none");
    emptyState.innerHTML = `<i class="bi bi-exclamation-triangle display-6 d-block mb-2"></i> ${escapeHtml(err.message)}`;
  }
}

function renderUsers(users) {
  const tbody = document.getElementById("usersTableBody");
  tbody.innerHTML = users
    .map((u) => {
      const active = Number(u.Active) === 1;
      const roleBadge = u.Role === "Admin"
        ? `<span class="badge role-badge-admin"><i class="bi bi-shield-lock me-1"></i>Admin</span>`
        : `<span class="badge role-badge-user">User</span>`;
      const statusBadge = active
        ? `<span class="badge status-badge-active">Active</span>`
        : `<span class="badge status-badge-disabled">Disabled</span>`;

      return `
        <tr>
          <td class="fw-semibold">${escapeHtml(u.First_Name)} ${escapeHtml(u.Last_Name)}</td>
          <td class="text-secondary-contrast">${escapeHtml(u.Login)}</td>
          <td>${roleBadge}</td>
          <td>${statusBadge}</td>
          <td class="text-end text-nowrap">
            <button type="button" class="btn btn-sm btn-outline-light me-1" data-action="view-contacts" data-id="${u.ID}" title="View contacts">
              <i class="bi bi-eye"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-light me-1" data-action="reset-password" data-id="${u.ID}" title="Reset password">
              <i class="bi bi-key"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-danger" data-action="disable" data-id="${u.ID}" ${active ? "" : "disabled"} title="Disable account">
              <i class="bi bi-slash-circle"></i>
            </button>
          </td>
        </tr>
      `;
    })
    .join("");
}

async function createUser(event) {
  event.preventDefault();

  const data = {
    First_Name: document.getElementById("newUserFirstName").value.trim(),
    Last_Name: document.getElementById("newUserLastName").value.trim(),
    Login: document.getElementById("newUserLogin").value.trim(),
    Password: document.getElementById("newUserPassword").value,
    Role: document.getElementById("newUserRole").value,
  };
  const resultEl = document.getElementById("createUserResult");

  try {
    await apiCall("admin.users.create", "POST", data);
    createUserModal().hide();
    loadUsers();
  } catch (err) {
    resultEl.className = "small fw-semibold text-danger-wcag";
    resultEl.innerHTML = "<i class='bi bi-exclamation-circle-fill me-1'></i> " + escapeHtml(err.message);
  }
}

function openDisableUser(id, name) {
  pendingDisableId = id;
  document.getElementById("disableUserName").textContent = name;
  disableModal().show();
}

async function confirmDisable() {
  if (!pendingDisableId) return;
  const button = document.getElementById("confirmDisableButton");
  button.disabled = true;

  try {
    await apiCall("admin.users.disable", "PUT", { id: pendingDisableId });
    disableModal().hide();
    loadUsers();
  } catch (err) {
    alert(err.message);
  } finally {
    button.disabled = false;
    pendingDisableId = null;
  }
}

function openResetPassword(id, name) {
  pendingPasswordUserId = id;
  document.getElementById("resetPasswordUserName").textContent = name;
  resetPasswordModal().show();
}

async function submitResetPassword(event) {
  event.preventDefault();
  if (!pendingPasswordUserId) return;

  const password = document.getElementById("resetPasswordValue").value;
  const resultEl = document.getElementById("resetPasswordResult");

  try {
    await apiCall("admin.users.password", "PUT", { id: pendingPasswordUserId, Password: password });
    resultEl.className = "small fw-semibold text-success-wcag";
    resultEl.innerHTML = "<i class='bi bi-check-circle-fill me-1'></i> Password updated.";
    setTimeout(() => resetPasswordModal().hide(), 900);
  } catch (err) {
    resultEl.className = "small fw-semibold text-danger-wcag";
    resultEl.innerHTML = "<i class='bi bi-exclamation-circle-fill me-1'></i> " + escapeHtml(err.message);
  }
}

function openUserContacts(id, name) {
  activeContactsUserId = id;
  document.getElementById("userContactsName").textContent = name;
  document.getElementById("userContactsSearch").value = "";
  userContactsModal().show();
  loadUserContacts();
}

async function loadUserContacts() {
  if (!activeContactsUserId) return;

  const query = document.getElementById("userContactsSearch").value.trim();
  const tbody = document.getElementById("userContactsTableBody");
  const emptyState = document.getElementById("userContactsEmptyState");

  try {
    const contacts = await apiCall("admin.users.contacts", "GET", { userId: activeContactsUserId, query });
    tbody.innerHTML = contacts
      .map((c) => `
        <tr>
          <td class="fw-semibold">${escapeHtml(c.First_Name)} ${escapeHtml(c.Last_Name)}</td>
          <td class="text-secondary-contrast">${escapeHtml(c.Email)}</td>
          <td class="text-secondary-contrast">${escapeHtml(c.Phone_Number)}</td>
        </tr>
      `)
      .join("");
    emptyState.classList.toggle("d-none", contacts.length > 0);
  } catch (err) {
    tbody.innerHTML = "";
    emptyState.classList.remove("d-none");
    emptyState.innerHTML = `<i class="bi bi-exclamation-triangle display-6 d-block mb-2"></i> ${escapeHtml(err.message)}`;
  }
}
