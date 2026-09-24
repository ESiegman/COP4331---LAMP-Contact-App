"use strict";

let currentUser = null;
let pendingDeleteId = null;
let contactsById = new Map();

const contactModal = () => bootstrap.Modal.getOrCreateInstance(document.getElementById("contactModal"));
const deleteModal = () => bootstrap.Modal.getOrCreateInstance(document.getElementById("deleteModal"));
const passwordModal = () => bootstrap.Modal.getOrCreateInstance(document.getElementById("passwordModal"));

document.addEventListener("DOMContentLoaded", async () => {
  currentUser = await requireAuth();
  if (!currentUser) return;

  document.getElementById("userName").innerHTML =
    `<i class="bi bi-person-circle me-1 text-primary"></i> ${escapeHtml(currentUser.login)}`;

  if (currentUser.role === "Admin") {
    document.getElementById("adminLink").classList.remove("d-none");
  }

  document.getElementById("logoutButton").addEventListener("click", signOut);
  document.getElementById("searchInput").addEventListener("input", debounce(loadContacts, 300));
  document.getElementById("addContactButton").addEventListener("click", openAddContact);
  document.getElementById("contactForm").addEventListener("submit", saveContact);
  document.getElementById("confirmDeleteButton").addEventListener("click", confirmDelete);
  document.getElementById("passwordForm").addEventListener("submit", changePassword);
  document.getElementById("contactModal").addEventListener("hidden.bs.modal", resetContactForm);
  document.getElementById("passwordModal").addEventListener("hidden.bs.modal", resetPasswordForm);
  document.getElementById("contactsTableBody").addEventListener("click", handleTableClick);

  loadContacts();
});

function handleTableClick(event) {
  const editBtn = event.target.closest("[data-action='edit']");
  const deleteBtn = event.target.closest("[data-action='delete']");

  if (editBtn) {
    const contact = contactsById.get(Number(editBtn.dataset.id));
    if (contact) openEditContact(contact);
  } else if (deleteBtn) {
    const contact = contactsById.get(Number(deleteBtn.dataset.id));
    if (contact) openDeleteContact(contact);
  }
}

async function loadContacts() {
  const query = document.getElementById("searchInput").value.trim();
  const tbody = document.getElementById("contactsTableBody");
  const emptyState = document.getElementById("emptyState");

  try {
    const contacts = await apiCall("contacts.search", "GET", { query });
    contactsById = new Map(contacts.map((c) => [Number(c.ID), c]));
    renderContacts(contacts);
    emptyState.classList.toggle("d-none", contacts.length > 0);
  } catch (err) {
    tbody.innerHTML = "";
    emptyState.classList.remove("d-none");
    emptyState.innerHTML =
      `<i class="bi bi-exclamation-triangle display-6 d-block mb-2"></i> ${escapeHtml(err.message)}`;
  }
}

function renderContacts(contacts) {
  const tbody = document.getElementById("contactsTableBody");
  tbody.innerHTML = contacts
    .map((c) => `
      <tr>
        <td>
          <div class="d-flex align-items-center gap-2">
            <span class="avatar-badge">${escapeHtml(initials(c.First_Name, c.Last_Name))}</span>
            <span class="fw-semibold">${escapeHtml(c.First_Name)} ${escapeHtml(c.Last_Name)}</span>
          </div>
        </td>
        <td class="text-secondary-contrast">${escapeHtml(c.Email)}</td>
        <td class="text-secondary-contrast">${escapeHtml(c.Phone_Number)}</td>
        <td class="text-end">
          <button type="button" class="btn btn-sm btn-outline-light me-1" data-action="edit" data-id="${c.ID}" title="Edit">
            <i class="bi bi-pencil"></i>
          </button>
          <button type="button" class="btn btn-sm btn-outline-danger" data-action="delete" data-id="${c.ID}" title="Delete">
            <i class="bi bi-trash3"></i>
          </button>
        </td>
      </tr>
    `)
    .join("");
}

function openAddContact() {
  document.getElementById("contactModalTitle").textContent = "Add Contact";
  document.getElementById("contactId").value = "";
}

function openEditContact(contact) {
  document.getElementById("contactModalTitle").textContent = "Edit Contact";
  document.getElementById("contactId").value = contact.ID;
  document.getElementById("contactFirstName").value = contact.First_Name;
  document.getElementById("contactLastName").value = contact.Last_Name;
  document.getElementById("contactEmail").value = contact.Email;
  document.getElementById("contactPhone").value = contact.Phone_Number;
  contactModal().show();
}

function resetContactForm() {
  document.getElementById("contactForm").reset();
  document.getElementById("contactId").value = "";
  document.getElementById("contactFormResult").innerHTML = "";
}

async function saveContact(event) {
  event.preventDefault();

  const id = document.getElementById("contactId").value;
  const data = {
    First_Name: document.getElementById("contactFirstName").value.trim(),
    Last_Name: document.getElementById("contactLastName").value.trim(),
    Email: document.getElementById("contactEmail").value.trim(),
    Phone_Number: document.getElementById("contactPhone").value.trim(),
  };
  const resultEl = document.getElementById("contactFormResult");
  const button = document.getElementById("contactSaveButton");

  resultEl.innerHTML = "";
  button.disabled = true;

  try {
    if (id) {
      await apiCall("contacts.update", "PUT", { id, ...data });
    } else {
      await apiCall("contacts.create", "POST", data);
    }
    contactModal().hide();
    loadContacts();
  } catch (err) {
    resultEl.className = "small fw-semibold text-danger-wcag";
    resultEl.innerHTML = "<i class='bi bi-exclamation-circle-fill me-1'></i> " + escapeHtml(err.message);
  } finally {
    button.disabled = false;
  }
}

function openDeleteContact(contact) {
  pendingDeleteId = contact.ID;
  document.getElementById("deleteContactName").textContent = `${contact.First_Name} ${contact.Last_Name}`;
  deleteModal().show();
}

async function confirmDelete() {
  if (!pendingDeleteId) return;
  const button = document.getElementById("confirmDeleteButton");
  button.disabled = true;

  try {
    await apiCall("contacts.delete", "DELETE", { id: pendingDeleteId });
    deleteModal().hide();
    loadContacts();
  } catch (err) {
    alert(err.message);
  } finally {
    button.disabled = false;
    pendingDeleteId = null;
  }
}

function resetPasswordForm() {
  document.getElementById("passwordForm").reset();
  document.getElementById("passwordFormResult").innerHTML = "";
}

async function changePassword(event) {
  event.preventDefault();

  const current = document.getElementById("currentPassword").value;
  const next = document.getElementById("newPassword").value;
  const confirm = document.getElementById("newPasswordConfirm").value;
  const resultEl = document.getElementById("passwordFormResult");

  if (next !== confirm) {
    resultEl.className = "small fw-semibold text-danger-wcag";
    resultEl.innerHTML = "<i class='bi bi-exclamation-circle-fill me-1'></i> New passwords don't match.";
    return;
  }

  try {
    await apiCall("auth.password", "PUT", { CurrentPassword: current, NewPassword: next });
    resultEl.className = "small fw-semibold text-success-wcag";
    resultEl.innerHTML = "<i class='bi bi-check-circle-fill me-1'></i> Password updated.";
    setTimeout(() => passwordModal().hide(), 900);
  } catch (err) {
    resultEl.className = "small fw-semibold text-danger-wcag";
    resultEl.innerHTML = "<i class='bi bi-exclamation-circle-fill me-1'></i> " + escapeHtml(err.message);
  }
}
