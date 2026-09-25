# API Reference

Base URL (local dev): `http://localhost:8080/index.php`

## Health check

`GET /index.php?ping=1` → `{"success":true,"data":{"service":"contacts-app-api"}}`. No auth, no `action` needed.

## How requests work

- `GET` requests pass `action` as a query string param: `?action=contacts.search&query=jane`
- `POST`/`PUT`/`DELETE` requests pass `action` inside a JSON body, along with any other fields that request needs

`Content-Type: application/json` on everything.

### Example (fetch)

```js
fetch('http://localhost:8080/index.php', {
  method: 'POST',
  credentials: 'include',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ action: 'auth.login', Login: 'jsmith', Password: 'hunter2' }),
});
```

`credentials: 'include'` required on every request after login.

```js
fetch('http://localhost:8080/index.php?action=contacts.search&query=jane', {
  credentials: 'include',
});
```

## CORS and cookies

Cross-port (`localhost:5500` → `localhost:8080`) works out of the box. Cross-domain won't work as-is — session cookie needs `SameSite=None; Secure` added, not configured yet.

## Response shape

```json
{ "success": true, "data": { ... } }
```
```json
{ "success": false, "error": "message here" }
```

## Auth

### POST `auth.register`

| Field | Required |
|---|---|
| `First_Name` | yes |
| `Last_Name` | yes |
| `Login` | yes |
| `Password` | yes |

Defaults: `Role: "User"`, `Active: true`.

Responses:
- `201` success, `{id, login}`
- `400` missing fields
- `409` login already taken

### POST `auth.login`

| Field | Required |
|---|---|
| `Login` | yes |
| `Password` | yes |

Responses:
- `200` success, `{id, login, role}`, sets session cookie
- `400` missing fields
- `401` wrong login or password
- `403` account disabled

### POST `auth.logout`

No fields.

Responses:
- `200` success

### GET `auth.me`

Requires an active session.

No fields.

Responses:
- `200` success, `{id, login, role}`
- `401` not logged in

### PUT `auth.password`

Requires an active session. Changes the caller's own password.

| Field | Required |
|---|---|
| `CurrentPassword` | yes |
| `NewPassword` | yes |

Responses:
- `200` success
- `400` missing fields
- `401` current password incorrect

## Contacts

All require an active session (`401` otherwise). Scoped to the caller's own contacts. Someone else's contact ID returns `404`, not `403`.

### GET `contacts.search`

| Param | Required |
|---|---|
| `query` | no, omit to list all of the caller's contacts |
| `sortBy` | no, one of `First_Name`, `Last_Name`, `Email`, `Phone_Number`, `Date_Created`, `Is_Favorite`. Invalid/omitted falls back to `Last_Name, First_Name` |
| `sortDir` | no, `ASC` or `DESC`, defaults to `ASC` |
| `favoritesOnly` | no, `1` to only return contacts with `Is_Favorite = 1` |

Matches `First_Name`, `Last_Name`, `Email`, `Phone_Number`. Real DB query per call, don't preload the full list client-side.

Responses:
- `200` success, `data` is an array of contact rows

### GET `contacts.get`

| Param | Required |
|---|---|
| `id` | yes |

Responses:
- `200` success, `data` is the contact row
- `404` contact doesn't exist or isn't yours

### POST `contacts.create`

| Field | Required |
|---|---|
| `First_Name` | yes |
| `Last_Name` | yes |
| `Email` | yes |
| `Phone_Number` | yes |

Responses:
- `201` success, `{id}`
- `400` missing fields

### PUT `contacts.update`

| Field | Required |
|---|---|
| `id` | yes |
| `First_Name`, `Last_Name`, `Email`, `Phone_Number`, `Is_Favorite` | any subset, partial updates allowed |

`ID`, `User_ID`, timestamps not editable through this endpoint. `Is_Favorite` is a boolean (`true`/`false`).

Responses:
- `200` success, `{id}`
- `404` contact doesn't exist or isn't yours

### DELETE `contacts.delete`

| Field | Required |
|---|---|
| `id` | yes |

Responses:
- `200` success
- `404` contact doesn't exist or isn't yours

## Admin

All require an active session and `Role: "Admin"` (`403` otherwise). Disabled admin accounts can't log in, same as any disabled account.

### GET `admin.users.search`

| Param | Required |
|---|---|
| `query` | no, omit to list every user |

Matches `Login`, `First_Name`, `Last_Name`. No password hash in the response.

Responses:
- `200` success, `data` is an array of user rows

### POST `admin.users.create`

| Field | Required |
|---|---|
| `First_Name` | yes |
| `Last_Name` | yes |
| `Login` | yes |
| `Password` | yes |
| `Role` | no, `User` or `Admin`, defaults to `User` |

Responses:
- `201` success, `{id, login, role}`
- `400` missing fields or invalid `Role`
- `409` login already taken

### GET `admin.users.contacts`

| Param | Required |
|---|---|
| `userId` | yes |
| `query` | no |

Responses:
- `200` success, `data` is an array of contact rows
- `400` missing `userId`

### PUT `admin.users.disable`

| Field | Required |
|---|---|
| `id` | yes |

Sets `Active` to false. Works on any user, including other Admins. Never deletes anything.

Responses:
- `200` success
- `404` user doesn't exist

### PUT `admin.users.password`

| Field | Required |
|---|---|
| `id` | yes |
| `Password` | yes, hashed server-side |

Responses:
- `200` success
- `400` missing password
- `404` user doesn't exist
