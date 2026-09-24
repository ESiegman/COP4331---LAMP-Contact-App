/* ============================================================
   Contacts CRUD (contacts.html)
   Field names match the API exactly: First_Name, Last_Name,
   Email, Phone_Number, ID, User_ID.
   ============================================================ */

// In-memory cache of the last fetched contacts, keyed by ID, so an
// in-progress edit can be cancelled without a round trip to the server.
let contactsCache = [];

async function addContact() {
  let firstNameInput = document.getElementById("contactFirstName");
  let lastNameInput = document.getElementById("contactLastName");
  let emailInput = document.getElementById("contactEmail");
  let phoneInput = document.getElementById("contactPhone");
  let resultEl = document.getElementById("contactAddResult");
  resultEl.innerHTML = "";

  let First_Name = firstNameInput.value.trim();
  let Last_Name = lastNameInput.value.trim();
  let Email = emailInput.value.trim();
  let Phone_Number = phoneInput.value.trim();

  if (!First_Name || !Last_Name || !Email || !Phone_Number) {
    resultEl.className = "text-warning small fw-semibold";
    resultEl.innerHTML =
      "<i class='bi bi-exclamation-triangle-fill me-1'></i> First name, last name, email, and phone are all required";
    return;
  }

  try {
    await Api.contacts.create({ First_Name, Last_Name, Email, Phone_Number });
    resultEl.className = "text-success-wcag small fw-semibold";
    resultEl.innerHTML =
      "<i class='bi bi-check-circle-fill me-1'></i> Contact successfully added!";
    firstNameInput.value = "";
    lastNameInput.value = "";
    emailInput.value = "";
    phoneInput.value = "";
    searchContacts();
  } catch (err) {
    resultEl.className = "text-danger-wcag small fw-semibold";
    resultEl.innerHTML = escapeHtml(err.message || "Error adding contact");
  }
}

async function searchContacts() {
  let srchInput = document.getElementById("searchText");
  let srch = srchInput ? srchInput.value.trim() : "";
  let resultSpan = document.getElementById("contactSearchResult");
  resultSpan.innerHTML = "";

  try {
    let contacts = (await Api.contacts.search(srch)) || [];
    resultSpan.className = "text-info-wcag small fw-semibold";
    resultSpan.innerHTML = "<i class='bi bi-check-circle me-1'></i> Results updated";
    contactsCache = contacts;
    renderContacts(contacts);
  } catch (err) {
    resultSpan.className = "text-danger-wcag small fw-semibold";
    resultSpan.innerHTML = escapeHtml(err.message || "Error loading contacts");
    contactsCache = [];
    renderContacts([]);
  }
}

function renderContacts(contacts) {
  let tbody = document.getElementById("contactList");
  if (!tbody) return;

  if (!contacts || contacts.length === 0) {
    tbody.innerHTML = `<tr><td colspan="5" class="text-secondary-contrast small fst-italic py-3">
      <i class="bi bi-info-circle me-1"></i> No matching contacts found.
    </td></tr>`;
    return;
  }

  let rows = "";
  for (let i = 0; i < contacts.length; i++) {
    rows += buildContactRow(contacts[i]);
  }
  tbody.innerHTML = rows;
}

function buildContactRow(c) {
  let id = c.ID;
  return `<tr id="contact-row-${id}">
      <td class="contact-cell">${escapeHtml(c.First_Name)}</td>
      <td class="contact-cell">${escapeHtml(c.Last_Name)}</td>
      <td class="contact-cell">${escapeHtml(c.Email)}</td>
      <td class="contact-cell">${escapeHtml(c.Phone_Number)}</td>
      <td class="text-end text-nowrap">
        <button type="button" class="btn btn-sm btn-outline-light me-1" title="Edit Contact" onclick="editContact(${id});">
          <i class="bi bi-pencil-fill"></i>
        </button>
        <button type="button" class="btn btn-sm btn-outline-danger" title="Delete Contact" onclick="deleteContact(${id});">
          <i class="bi bi-trash-fill"></i>
        </button>
      </td>
    </tr>`;
}

function findCachedContact(id) {
  for (let i = 0; i < contactsCache.length; i++) {
    if (String(contactsCache[i].ID) === String(id)) return contactsCache[i];
  }
  return null;
}

function editContact(id) {
  let c = findCachedContact(id);
  let row = document.getElementById("contact-row-" + id);
  if (!c || !row) return;

  row.innerHTML = `
      <td><input type="text" class="form-control form-control-sm" id="edit-first-${id}" value="${escapeHtml(c.First_Name)}" /></td>
      <td><input type="text" class="form-control form-control-sm" id="edit-last-${id}" value="${escapeHtml(c.Last_Name)}" /></td>
      <td><input type="email" class="form-control form-control-sm" id="edit-email-${id}" value="${escapeHtml(c.Email)}" /></td>
      <td><input type="tel" class="form-control form-control-sm" id="edit-phone-${id}" value="${escapeHtml(c.Phone_Number)}" /></td>
      <td class="text-end text-nowrap">
        <button type="button" class="btn btn-sm btn-success me-1" title="Save" onclick="saveContact(${id});">
          <i class="bi bi-check-lg"></i>
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary" title="Cancel" onclick="cancelEditContact(${id});">
          <i class="bi bi-x-lg"></i>
        </button>
      </td>
    `;
}

function cancelEditContact(id) {
  let c = findCachedContact(id);
  let row = document.getElementById("contact-row-" + id);
  if (!row || !c) return;
  row.outerHTML = buildContactRow(c);
}

async function saveContact(id) {
  let updated = {
    First_Name: document.getElementById("edit-first-" + id).value.trim(),
    Last_Name: document.getElementById("edit-last-" + id).value.trim(),
    Email: document.getElementById("edit-email-" + id).value.trim(),
    Phone_Number: document.getElementById("edit-phone-" + id).value.trim(),
  };

  try {
    await Api.contacts.update(id, updated);
    let cached = findCachedContact(id);
    if (cached) Object.assign(cached, updated);
    let row = document.getElementById("contact-row-" + id);
    if (row) row.outerHTML = buildContactRow({ ID: id, ...updated });
  } catch (err) {
    alert(err.message || "Failed to update contact. Please try again.");
  }
}

async function deleteContact(id) {
  if (!id && id !== 0) return;
  if (!confirm("Delete this contact? This cannot be undone.")) return;

  try {
    await Api.contacts.delete(id);
    searchContacts();
  } catch (err) {
    alert(err.message || "Failed to delete contact.");
  }
}
