# ODF API Reference

This document describes the Laravel API implemented in this project. It is a
human-readable reference, not an OpenAPI specification.

## Base URL and conventions

| Environment | Base URL |
|---|---|
| Docker | `http://localhost:8000/api` |
| Frontend dev server | Use relative `/api` requests; Vite proxies them to the backend |

- Send and receive JSON unless an endpoint accepts a file upload or returns a file.
- Protected routes require a Sanctum bearer token:

  ```http
  Authorization: Bearer <token>
  Accept: application/json
  ```

- Use `multipart/form-data` for upload endpoints.
- IDs in path parameters are database record IDs.
- Date/time inputs should be values Laravel can parse as dates; responses use
  Laravel's serialized date values.
- A `204 No Content` response has no JSON body.

## Authentication and common responses

### Login

`POST /login` is public and limited to 10 attempts per minute.

```json
{
  "email": "secretary@odf.local",
  "password": "password",
  "remember": false,
  "code": null
}
```

`email` accepts either an email address or username. `remember` is optional and
defaults to false. When two-factor authentication is enabled, submit credentials
without a code first:

```json
{ "two_factor_required": true }
```

Then submit the same login request with the authenticator's six-digit `code`.
On success, the response contains a token and user record:

```json
{
  "token": "<sanctum-token>",
  "user": {
    "id": 1,
    "name": "Example User",
    "email": "user@example.test",
    "roles": [],
    "permissions": []
  }
}
```

The token expires after 12 hours by default, or 30 days when `remember` is true.

### Current user and logout

| Method | Path | Access |
|---|---|---|
| `GET` | `/me` | Authenticated |
| `POST` | `/logout` | Authenticated |

`GET /me` returns the authenticated user with title, position, department,
roles, and permission names. `POST /logout` revokes the current token and
returns `{"message":"Logged out"}`.

### Error response conventions

Validation errors return `422` with a message and field errors, for example:

```json
{
  "message": "The email field is required.",
  "errors": {
    "email": ["The email field is required."]
  }
}
```

Common statuses:

| Status | Meaning |
|---|---|
| `200` | Successful read or update |
| `201` | Resource created |
| `204` | Successful delete or action with no response body |
| `401` | Missing or invalid authentication |
| `403` | Authenticated but not permitted, or record not visible |
| `404` | Record, route, or stored file not found |
| `422` | Validation or workflow rule rejected the request |
| `429` | Login rate limit exceeded |

Laravel may also return `500` for an unexpected server error.

## Public endpoints

| Method | Path | Description |
|---|---|---|
| `POST` | `/login` | Authenticate and issue a token |
| `GET` | `/branding` | Public app and organization branding metadata |
| `GET` | `/branding/logo` | Stream the configured public logo |

`GET /branding` returns `app_name`, `app_full_name`, `logo_url`,
`org_name_kh`, `org_name_en`, and `ministry_kh`. `logo_url` is `null` when
no uploaded logo is configured.

## Profile and account

All endpoints in this section require authentication.

| Method | Path | Body / result |
|---|---|---|
| `PUT` | `/profile` | JSON: `name` (required), `email` (required, unique), optional `name_kh`, `phone` |
| `PUT` | `/profile/password` | JSON: `current_password`, `password` (min 8), `password_confirmation`; requires `profile_password_edit` |
| `POST` | `/profile/photo` | Multipart: `photo` (required image, max 2 MB) |
| `DELETE` | `/profile/photo` | Remove own profile photo |
| `POST` | `/profile/signature` | Multipart: `signature` (required image, max 2 MB) |
| `DELETE` | `/profile/signature` | Remove own signature |
| `POST` | `/profile/two-factor/setup` | Start enrollment; returns `secret` and `otpauth_url` |
| `POST` | `/profile/two-factor/confirm` | JSON: `code`; confirms authenticator setup |
| `POST` | `/profile/two-factor/disable` | JSON: `password`; disables own two-factor authentication |
| `DELETE` | `/profile` | JSON body: `password`; delete own account and tokens |
| `GET` | `/users/{user}/photo` | Stream a user's stored profile photo |
| `GET` | `/users/{user}/signature` | Stream a stored signature; access is limited to its owner or a user who can view a document in the signer's flow |

Successful profile, photo, signature, and two-factor changes return the updated
user payload. Photo and signature files are stored on Laravel's private local
disk and served through authenticated routes.

## Dashboard, lookups, and alerts

All endpoints require authentication.

| Method | Path | Access | Description |
|---|---|---|---|
| `GET` | `/dashboard` | Authenticated | Counts and lists for the user's visible documents |
| `GET` | `/lookups` | Authenticated | Dropdown data for forms; includes roles and permissions for user managers |
| `GET` | `/alerts/mine` | Authenticated | Current user's unread count and latest 20 alerts |
| `POST` | `/alerts/read-all` | Authenticated | Mark all own alerts as read |
| `POST` | `/alerts/{alert}/read` | Authenticated | Mark an alert as read for the current user |

Dashboard fields include `total`, `active`, `closed`, `completed`, `in_review`,
`my_turn`, `overdue`, `due_soon`, `my_turn_list`, and `recent`. Counts and
documents respect the caller's document visibility.

`GET /lookups` returns `departments`, `titles`, `positions`, `categories`,
`organisations`, `document_types`, active `users`, and `approver_ids`.

### Alert administration

| Method | Path | Access | Body / result |
|---|---|---|---|
| `GET` | `/user-alerts` | `user_alert_access` | Paginated alert list |
| `POST` | `/user-alerts` | `user_alert_create` | JSON: `alert_text`, optional `alert_link`, `user_ids` (one or more existing user IDs); returns `201` |
| `POST` | `/user-alerts/bulk-delete` | `user_alert_delete` | JSON: `ids` array |
| `DELETE` | `/user-alerts/{alert}` | `user_alert_delete` | Delete one alert |

## Documents

Every document route requires authentication. Document list access requires
`document_access`; the caller sees documents they created or participate in,
unless they also have `document_grand_access`.

| Method | Path | Access / purpose |
|---|---|---|
| `GET` | `/documents` | `document_access`; list documents |
| `POST` | `/documents` | `document_create`; create document and review flow |
| `GET` | `/documents/{document}` | `document_show` and document visibility; detailed record |
| `PUT` or `POST` | `/documents/{document}/update` | `document_edit`; update fields and, until completion, the flow |
| `DELETE` | `/documents/{document}` | `document_delete` or `document_delete_all` |
| `POST` | `/documents/bulk-delete` | `document_delete_all`; JSON: `ids` |
| `POST` | `/documents/{document}/close` | `document_complete_edit`; JSON: `{"closed":true}` to close or `false` to reopen |
| `POST` | `/documents/{document}/submit` | The current reviewer or final approver; submit comment |
| `POST` | `/documents/{document}/return` | The current reviewer; return to a previous completed step |
| `POST` | `/documents/{document}/attachments` | Document editor or current reviewer; upload files |

The create and update endpoints accept JSON or `multipart/form-data` when
including attachments. Fields:

| Field | Type / validation |
|---|---|
| `letter_no` | Optional string, max 255 |
| `incoming_code` | Optional string, max 255; generated when omitted on create |
| `document_code` | Optional string, max 255 |
| `from_source` | Optional string, max 255 |
| `description` | Optional string / rich text; sanitized by the API |
| `document_type_id` | Required on create; must exist |
| `category_id` | Optional existing category ID |
| `organisation_id` | Optional existing organisation ID |
| `dateline` | Optional date/time |
| `reviewers` | Optional array of existing user IDs, in review order |
| `final_approver_id` | Required on create; existing user with `document_approve_access` |
| `attachments[]` | Optional files, max 50 MB each |

Example create request:

```json
{
  "letter_no": "12/2026",
  "document_code": "DOC-2026-014",
  "from_source": "Finance Department",
  "description": "<p>Review the attached request.</p>",
  "document_type_id": 1,
  "category_id": 2,
  "organisation_id": 1,
  "dateline": "2026-11-25 17:00:00",
  "reviewers": [3, 5],
  "final_approver_id": 7
}
```

The flow is stored as ordered comment rows. Reviewers are activated in order;
the final approver is appended as the final step. The final approver must have
`document_approve_access`.

Completion state, closure state, and `date_complete` are workflow-managed:
completion is recorded by the final-step submit endpoint, while closing or
reopening uses `POST /documents/{document}/close`.

`GET /documents?tab=...` supports:

| `tab` | Result |
|---|---|
| `active` (default) | Not closed documents |
| `inbox` | Not closed documents where it is the caller's current turn |
| `completed` | Completed documents |
| `closed` | Closed documents; requires `document_complete_access` |
| `all` | All visible documents |

The standard table query parameters described below can also be used.

`GET /documents/{document}` returns:

```json
{
  "document": {},
  "permissions": {
    "is_my_turn": false,
    "is_final_step": false,
    "can_edit": false,
    "can_close": false,
    "can_print": false,
    "can_manage_flow": false,
    "can_remove_step": false,
    "can_status_view": false,
    "can_upload": false,
    "return_targets": [],
    "edit_hour": 24
  }
}
```

`POST /documents/{document}/submit` body:

```json
{ "comment": "<p>Reviewed.</p>", "esign": false, "close": false }
```

`comment` may be omitted by the final approver but is required for a normal
review step. Set `esign: true` only when the caller has uploaded a signature.
`close` optionally closes the document when the final approver completes it.

`POST /documents/{document}/return` body:

```json
{ "comment_id": 42, "note": "Please verify the amount." }
```

`comment_id` must identify a completed step in this document's flow.

## Attachments

| Method | Path | Access / body |
|---|---|---|
| `POST` | `/documents/{document}/attachments` | Multipart: `attachments[]`, file max 50 MB; editor or current reviewer |
| `GET` | `/attachments/{attachment}` | Authenticated document participant; streams file |
| `DELETE` | `/attachments/{attachment}` | Document editor or uploader |

Attachment responses are always downloads with a generic binary content type
and `nosniff` protection; the API does not render uploaded content inline.

## Comments and review steps

| Method | Path | Access / body |
|---|---|---|
| `GET` | `/comments` | `comment_access`; own comments unless `comment_grand_access` |
| `POST` | `/comments` | `comment_grand_edit`; add a reviewer step |
| `GET` | `/comments/{comment}` | `comment_show` and document visibility |
| `PUT` | `/comments/{comment}` | Own submitted comment within configured edit window, or `comment_grand_edit` |
| `POST` | `/comments/{comment}/reset` | `comment_grand_edit`; reopen a step |
| `DELETE` | `/comments/{comment}` | `comment_delete`; remove a non-final reviewer |
| `POST` | `/comments/bulk-delete` | `comment_delete`; JSON: `ids` |

Add-comment request:

```json
{
  "document_id": 14,
  "user_id": 5,
  "comment": "<p>For review.</p>",
  "is_submit": false,
  "is_esign": false
}
```

The new step is inserted before the final approver. Comment editing accepts
`comment`; users with `comment_grand_edit` may also change `is_submit` and
`is_esign`. The regular owner edit window is configured by `edit_hour` (default
24 hours). A final approver step cannot be removed.

## User management

These are standard CRUD routes and require the matching permission:

| Resource | Collection | Item parameter | Permission prefix |
|---|---|---|---|
| Permissions | `/permissions` | `{permission}` | `permission` |
| Roles | `/roles` | `{role}` | `role` |
| Users | `/users` | `{user}` | `user` |
| User information | `/user-infos` | `{user_info}` | `user_info` |

For each resource:

| Method | Path | Required permission |
|---|---|---|
| `GET` | `/{resource}` | `<prefix>_access` |
| `POST` | `/{resource}` | `<prefix>_create` |
| `GET` | `/{resource}/{id}` | `<prefix>_show` |
| `PUT` | `/{resource}/{id}` | `<prefix>_edit` |
| `DELETE` | `/{resource}/{id}` | `<prefix>_delete` |
| `POST` | `/{resource}/bulk-delete` | `<prefix>_delete`; JSON: `ids` |

Resource-specific fields:

- **Permissions:** `title` (required, unique, alpha-dash).
- **Roles:** `title` (required, unique), optional `permissions` array of
  permission IDs. The built-in `Admin` role cannot be deleted.
- **Users:** `name`, `email`, and `roles` are required on create/update;
  optional `name_kh`, `username`, `password`, `title_id`, `position_id`,
  `department_id`, `phone`, `active`, `email_verified`, and
  `two_factor_enabled`. Password is required on create and optional on update.
  Admins can turn off two-factor but cannot enable it for another user.
  `GET /users?role_id={id}` filters by role.
- **User information:** `user_id` is required and unique; optional
  `title_id`, `position_id`, `department_id`, `gender` (`Male` or `Female`),
  `date_of_birth`, `place_of_birth`, `id_card_no`, `phone`, and `address`.
  Title, position, and department are updated on the related user record.

Users cannot delete their own account through `/users/{user}`. Use
`DELETE /profile` to delete the current account after password confirmation.

## Settings resources

All settings CRUD routes require `setting_access` and a matching
`<resource>_access/create/show/edit/delete` permission.

| Resource path | Permission prefix | Main fields |
|---|---|---|
| `/settings/departments` | `department` | `name` required; optional `name_kh`, `abbr`, `abbr_kh`, `code`, `description` |
| `/settings/titles` | `title` | `name` required; optional `name_kh`, `abbr`, `abbr_kh`, `description` |
| `/settings/positions` | `position` | `name` required; optional `name_kh`, `description`, `active` |
| `/settings/categories` | `category` | `name` required; optional `name_kh`, `description` |
| `/settings/organisations` | `organisation` | `name` required; optional `name_kh`, `description` |
| `/settings/document-types` | `document_type` | `value` and `name` required; optional `name_kh`, `description` |
| `/settings/staff` | `staff` | `staff_code`, `name`, `gender` required; optional names, title/position/department/user IDs and phone |
| `/settings/app-settings` | `app_setting` | `name` required; optional `value`, `description`, `photo` image (max 2 MB) |

Each resource uses the same collection/item/bulk-delete route pattern documented
under User management. Creation returns `201`; delete returns `204`.

## Audit and user activity logs

| Method | Path | Access | Query / body |
|---|---|---|---|
| `GET` | `/audit-logs` | `audit_log_access` | Standard table query parameters |
| `GET` | `/audit-logs/{auditLog}` | `audit_log_show` | Returns one audit event with user |
| `GET` | `/user-logs` | `user_log_access` | `user`, `log_type`, `table`, `from`, `to`, `page`, `per_page` |
| `GET` | `/user-logs/{userLog}` | `user_log_access` | Returns one activity record |
| `POST` | `/user-logs/purge` | `user_log_delete` | Optional JSON `{"days":30}`; returns deleted row count |

User log types include `login`, `logout`, `create`, `edit`, and `delete`.
`from` and `to` are inclusive calendar dates.

## Standard table query parameters

Used by table/list endpoints backed by the shared table query helper:

| Parameter | Example | Behavior |
|---|---|---|
| `search` | `?search=finance` | Global text search across supported fields |
| `filters[field]` | `?filters[status]=current` | Filter a supported field; selected fields use exact match |
| `sort` | `?sort=created_at` | Sort by an endpoint-supported field |
| `dir` | `?dir=asc` | `asc` or `desc` (default `desc`) |
| `page` | `?page=2` | Page number |
| `per_page` | `?per_page=25` | Page size, capped at 500 |
| `per_page=0` | `?per_page=0` | Return up to 5,000 rows for exports |

The response shape is:

```json
{
  "data": [],
  "total": 0
}
```

Supported search and sort columns depend on the endpoint. Unknown filter or sort
columns are ignored. `/user-logs` has its own filters and caps `per_page` at
100.

## Database relationship summary

- `documents` belongs to its creator and reference records
  (`document_types`, `categories`, `organisations`).
- `comments` represents ordered workflow steps and belongs to both a document
  and a user.
- `document_attachments` and `document_histories` belong to a document.
- `users` can have many roles through `role_user`; roles can have many
  permissions through `permission_role`.
- `user_infos` is one-to-one with a user. `staff.user_id` is optional.
- `user_alerts` are assigned to users through `user_alert_user`, which stores
  each recipient's `read_at` time.
- Profile photos, signatures, and document attachments are file paths in the
  database; their binary data is stored on Laravel storage disks.
