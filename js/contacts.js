"use strict";

let currentUser = null;
let pendingDeleteId = null;
let contactsById = new Map();

let sortBy = null; // null = server default (Last_Name, First_Name)
let sortDir = "ASC";
let favoritesOnly = false;

// Sensible default direction per column when first clicked.
const DEFAULT_SORT_DIR = {
  Is_Favorite: "DESC", // show favorites first
  First_Name: "ASC",
  Last_Name: "ASC",
  Email: "ASC",
  Phone_Number: "ASC",
};

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
  document.getElementById("favoritesOnlyToggle").addEventListener("click", toggleFavoritesOnly);

  document.querySelectorAll(".sortable-header").forEach((btn) => {
    btn.addEventListener("click", () => handleSortClick(btn.dataset.sort));
  });

  loadContacts();
});

function handleSortClick(column) {
  const defaultDir = DEFAULT_SORT_DIR[column] || "ASC";
  const flippedDir = defaultDir === "ASC" ? "DESC" : "ASC";

  if (sortBy !== column) {
    // First click on this column: sort by it, in its default direction.
    sortBy = column;
    sortDir = defaultDir;
  } else if (sortDir === defaultDir) {
    // Second click: flip direction.
    sortDir = flippedDir;
  } else {
    // Third click: clear back to the server's default sort.
    sortBy = null;
    sortDir = "ASC";
  }

  updateSortIndicators();
  loadContacts();
}

function updateSortIndicators() {
  document.querySelectorAll(".sortable-header").forEach((btn) => {
    const icon = btn.querySelector(".sort-icon");
    const isStarHeader = btn.dataset.sort === "Is_Favorite";
    const isActive = btn.dataset.sort === sortBy;

    btn.classList.toggle("active-sort", isActive);

    if (isStarHeader) {
      const starIcon = btn.querySelector("i");
      starIcon.className = isActive ? "bi bi-star-fill" : "bi bi-star";
      return;
    }

    if (!icon) return;
    if (!isActive) {
      icon.className = "bi bi-arrow-down-up sort-icon";
    } else {
      icon.className = sortDir === "ASC" ? "bi bi-sort-up sort-icon" : "bi bi-sort-down sort-icon";
    }
  });
}

function toggleFavoritesOnly() {
  favoritesOnly = !favoritesOnly;
  const btn = document.getElementById("favoritesOnlyToggle");
  btn.setAttribute("aria-pressed", String(favoritesOnly));
  btn.classList.toggle("btn-outline-light", !favoritesOnly);
  btn.classList.toggle("btn-primary", favoritesOnly);
  btn.innerHTML = favoritesOnly
    ? '<i class="bi bi-star-fill me-1"></i> Favorites only'
    : '<i class="bi bi-star me-1"></i> Favorites only';
  loadContacts();
}

function handleTableClick(event) {
  const editBtn = event.target.closest("[data-action='edit']");
  const deleteBtn = event.target.closest("[data-action='delete']");
  const favoriteBtn = event.target.closest("[data-action='favorite']");

  if (editBtn) {
    const contact = contactsById.get(Number(editBtn.dataset.id));
    if (contact) openEditContact(contact);
  } else if (deleteBtn) {
    const contact = contactsById.get(Number(deleteBtn.dataset.id));
    if (contact) openDeleteContact(contact);
  } else if (favoriteBtn) {
    const contact = contactsById.get(Number(favoriteBtn.dataset.id));
    if (contact) toggleFavorite(contact);
  }
}

async function loadContacts() {
  const query = document.getElementById("searchInput").value.trim();
  const tbody = document.getElementById("contactsTableBody");
  const emptyState = document.getElementById("emptyState");
  const emptyStateText = document.getElementById("emptyStateText");

  const params = { query };
  if (sortBy) {
    params.sortBy = sortBy;
    params.sortDir = sortDir;
  }
  if (favoritesOnly) {
    params.favoritesOnly = "1";
  }

  try {
    const contacts = await apiCall("contacts.search", "GET", params);
    if (!Array.isArray(contacts)) {
      throw new Error("Unexpected response from server. Check the server logs for an error.");
    }
    contactsById = new Map(contacts.map((c) => [Number(c.ID), c]));
    renderContacts(contacts);
    emptyState.classList.toggle("d-none", contacts.length > 0);
    emptyStateText.textContent = favoritesOnly
      ? "No favorite contacts found."
      : "No contacts found. Try a different search, or add a new one.";
  } catch (err) {
    tbody.innerHTML = "";
    emptyState.classList.remove("d-none");
    emptyStateText.innerHTML =
      `<i class="bi bi-exclamation-triangle display-6 d-block mb-2"></i> ${escapeHtml(err.message)}`;
  }
}

function renderContacts(contacts) {
  const tbody = document.getElementById("contactsTableBody");
  tbody.innerHTML = contacts.map(buildContactRow).join("");
}

function buildContactRow(c) {
  const isFavorite = Number(c.Is_Favorite) === 1;
  return `
    <tr>
      <td class="text-center">
        <button type="button" class="favorite-star ${isFavorite ? "active" : ""}" data-action="favorite" data-id="${c.ID}" title="${isFavorite ? "Remove from favorites" : "Add to favorites"}">
          <i class="bi ${isFavorite ? "bi-star-fill" : "bi-star"}"></i>
        </button>
      </td>
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
  `;
}

async function toggleFavorite(contact) {
  const newValue = Number(contact.Is_Favorite) !== 1;

  try {
    await apiCall("contacts.update", "PUT", { id: contact.ID, Is_Favorite: newValue });
    // Reload rather than patch in place: this keeps sort order and the
    // favorites-only filter correct (a row may need to disappear or move).
    loadContacts();
  } catch (err) {
    alert(err.message || "Failed to update favorite status.");
  }
}

function openAddContact() {
  document.getElementById("contactModalTitle").textContent = "Add Contact";
  document.getElementById("contactId").value = "";
  document.getElementById("contactIsFavorite").checked = false;
}

function openEditContact(contact) {
  document.getElementById("contactModalTitle").textContent = "Edit Contact";
  document.getElementById("contactId").value = contact.ID;
  document.getElementById("contactFirstName").value = contact.First_Name;
  document.getElementById("contactLastName").value = contact.Last_Name;
  document.getElementById("contactEmail").value = contact.Email;
  document.getElementById("contactPhone").value = contact.Phone_Number;
  document.getElementById("contactIsFavorite").checked = Number(contact.Is_Favorite) === 1;
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
    Is_Favorite: document.getElementById("contactIsFavorite").checked,
  };
  const resultEl = document.getElementById("contactFormResult");
  const button = document.getElementById("contactSaveButton");

  resultEl.innerHTML = "";
  button.disabled = true;

  try {
    if (id) {
      await apiCall("contacts.update", "PUT", { id, ...data });
    } else {
      // contacts.create only accepts the four core fields; set favorite
      // as a follow-up update if the checkbox was ticked on a new contact.
      const { Is_Favorite, ...createFields } = data;
      const created = await apiCall("contacts.create", "POST", createFields);
      if (Is_Favorite) {
        await apiCall("contacts.update", "PUT", { id: created.id, Is_Favorite: true });
      }
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
