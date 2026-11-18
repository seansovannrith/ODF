# ODF – Organization Document Flow

Track an incoming document as it moves from person to person.
Each reviewer adds a comment in turn. The **Boss** is last: they review, complete and sign it electronically.

* **Frontend:** Vue 3 + Vite + Pinia + vue-i18n + Bootstrap 5 (AdminLTE-style layout). It has a **ខ្មែរ / English** switch.
* **Backend:** Laravel 12 REST API with Sanctum token login.
* **Database:** MySQL 8 in Docker. You can browse it with **phpMyAdmin**.

```
odf/
├── docker-compose.yml     mysql + phpmyadmin + backend + frontend
├── backend/               Laravel API
└── frontend/              Vue app
```

---

## 1. Start everything with Docker

Requirements: **Docker Desktop**, running. It works on Apple Silicon (M1–M4) and Intel Macs.

```bash
cd odf
docker compose down -v          # first time after an earlier failed try: removes the old, half-created database
docker compose up -d --build    # build and start (the first build takes 3–8 minutes)
docker compose logs -f backend  # watch until you see "API ready on http://localhost:8000"
```

During the build, Docker installs all the PHP packages inside the image, so a problem shows up as a clear build error. When the backend container starts, it creates the app key, waits for MySQL, and then creates the tables and demo data by itself.

After you change **backend** code, run `docker compose up -d --build backend`. Frontend code reloads by itself.

| What | URL |
|---|---|
| App (Vue) | http://localhost:5173 |
| API (Laravel) | http://localhost:8000/api |
| phpMyAdmin | http://localhost:8080 (server `mysql`, user `root` / `root_secret`, or `odf` / `odf_secret`) |
| MySQL from your PC | `127.0.0.1:3307`, database `odf` |

To watch the backend logs: `docker compose logs -f backend`

### If something does not start

| You see | Fix |
|---|---|
| `Cannot connect to the Docker daemon` | Open Docker Desktop and wait until it says *Running*. |
| `port is already allocated` (8000, 3307, 8080 or 5173) | Another program uses that port. Stop it (for example a local `php artisan serve`, MAMP or MySQL), or change the **left** number in `docker-compose.yml`, e.g. `"8001:8000"`. |
| `Table '…' already exists` or another migrate error | The database is left over from an earlier failed try. Run `docker compose down -v` and then `docker compose up -d --build`. |
| `!! MySQL is not reachable` | Run `docker compose logs mysql`. Usually `docker compose down -v` and starting again fixes it. |
| The build stops at `composer install` or `npm install` | Your internet dropped during the download. Run `docker compose build --no-cache` and try again. |
| The frontend shows `Failed to resolve import "qrcode"` | Run `docker compose up -d --build -V frontend`. |
| Anything else | Send the output of `docker compose logs backend --tail=100`. |

### Demo accounts (password: `password`)

You can log in with the email or the username (the part before `@`).

| Email | Role | What they do |
|---|---|---|
| admin@odf.local | Admin | Everything, including User management and Settings |
| secretary@odf.local | Moderator | Registers documents, sets up the flow, sees every document and comment |
| mao.reasey@odf.local | User | Reviewer, step 1 |
| sam.leak@odf.local | User | Reviewer, step 2 (it's his turn in the demo document) |
| chhom.sakborey@odf.local | User | Reviewer |
| hun.chamroeun@odf.local | User | Reviewer (Deputy Director) |
| boss@odf.local | Direction | Final approver who completes and signs |

**Change these passwords before real use.**

---

## 2. How the flow works

```
Secretary registers the document
   │  picks commenters in order + the Boss
   ▼
Reviewer 1 ──comment──► Reviewer 2 ──comment──► … ──► BOSS
   ▲                         │                        │ review, complete,
   └──── "send back" ────────┘                        │ e-sign, (close)
                                                      ▼
                                                 Completed ✔
```

1. A **secretary or admin** opens *Documents → Add Document*. They fill in the letter fields, upload attachments, choose the **commenters in order** (using the ↑ ↓ buttons) and choose the **Boss**.
2. The first person gets a 🔔 alert. On the document page they see a **"It is your turn"** box. They write their comment and press **Submit & send to next**.
3. The next person in the list then gets the alert, and so on down the list. The *My Inbox* menu and the dashboard show every document that is waiting for you.
4. Anyone whose turn it is can **send the document back** to a person who already commented.
5. When it reaches the Boss, they write the final decision and tick **Sign electronically**. Before that, the Boss must upload a signature image under *Change password*. Then they press **Complete**. They can also choose to close the document.
6. **Print** opens an A4 page with the Kingdom header, the hospital box, every comment, and the Boss's signature. Use your browser's print dialog to print it or save it as a PDF.

The icons under each comment work as follows:

* ✕ removes a person from the flow (admin and secretary only).
* ✎ edits your own comment. You can do this within `edit_hour` hours, which is set in *App Settings*.
* ↺ re-opens that step (admin and secretary only).

---

## 3. User management: roles, permissions and logs

Everything a user can see or do comes from **permissions**. Permissions are grouped into **roles**, and each user gets one or more roles. Admins manage this under *User management*:

| Screen | What it does |
|---|---|
| **Permissions** | The list of permission names, such as `document_create`. You only need to add new ones if you also add code that checks them. |
| **Roles** | A title plus a set of permissions, picked from checkboxes grouped by area. The default roles are Admin, Direction, Moderator, Creator, Document Print and User. |
| **Users** | Name, username, email, email verified, two-factor status, roles, and the linked Staff record and staff code. |
| **User Information** | Personal details: gender, date of birth, place of birth, ID card, phone and address. Title, position and department are edited here too. |
| **Audit Logs** | Every create, update or delete of any record. It shows who made the change, from which IP address, and the old and new values. |
| **User Logs** | User activity: login, logout, create, edit and delete. You can filter by user, type, table and date range. A button deletes entries older than 30 days. |

The permissions that control the document flow:

| Permission | Meaning |
|---|---|
| `document_access` / `document_show` | Open the Documents menu and view a document |
| `document_create` / `document_edit` / `document_delete` / `document_delete_all` | Register, edit, delete, or bulk-delete documents |
| `document_grand_access` | See every document. Without it, a user only sees the documents they registered or take part in. |
| `document_approve_access` | Can be chosen as the **Boss**, who gives final approval and signs |
| `document_complete_access` / `document_complete_edit` | See the Closed tab / close and reopen documents |
| `document_print_access` | Print a document |
| `comment_create` | Write a comment when it's your turn |
| `comment_edit` | Edit your own comment within `edit_hour` |
| `comment_grand_edit` | Add Comment, edit any comment, re-open a step |
| `comment_delete` | Remove a person from the flow |
| `comment_grand_access` | See every comment in the Comments list |
| `comment_status_view` | See who is waiting and whose turn it is |
| `<setting>_access/create/edit/show/delete` | One set for each Settings screen (department, title, …) |

**Two-factor login:** each user can turn it on under *Change password → Two factor authentication*. They scan the QR code with Google Authenticator, Microsoft Authenticator or Authy. From then on, login asks for the 6-digit code after the password. If someone loses their phone, an admin can untick *Two-Factor Auth* on the user to switch it off.

## 4. Language switch

The **🇰🇭 ខ្មែរ / 🇬🇧 EN** buttons are in the top bar and on the login page. Your choice is saved in the browser.

* The interface text lives in `frontend/src/i18n/km.js` and `en.js`. To translate a new label, add the same key to both files.
* Data records have both `name` and `name_kh` columns. The `ln()` helper shows the Khmer value when Khmer is selected.

---

## 5. Running without Docker for the app (MySQL still in Docker)

```bash
docker compose up -d mysql phpmyadmin

# backend
cd backend
cp .env.example .env          # set DB_HOST=127.0.0.1 and DB_PORT=3307
composer install
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve             # http://localhost:8000

# frontend (new terminal)
cd frontend
npm install
npm run dev                   # http://localhost:5173  (proxies /api to :8000)
```

---

## 6. Main API endpoints

| Method | URL | Who |
|---|---|---|
| POST | `/api/login` | everyone |
| GET | `/api/dashboard`, `/api/lookups`, `/api/alerts/mine` | logged in |
| GET | `/api/documents?tab=active\|inbox\|closed\|completed\|all` | logged in (only documents they take part in) |
| GET | `/api/documents/{id}` | people in the flow |
| POST | `/api/documents/{id}/submit` `{comment, esign, close}` | the person whose turn it is |
| POST | `/api/documents/{id}/return` `{comment_id, note}` | the person whose turn it is |
| POST | `/api/documents`, `/api/documents/{id}/update`, `/api/documents/{id}/close` | admin, secretary |
| CRUD | `/api/permissions`, `/api/roles`, `/api/users`, `/api/user-infos`, `/api/user-alerts` | matching `*_access/create/edit/show/delete` permission |
| CRUD | `/api/settings/{departments\|titles\|positions\|categories\|organisations\|document-types\|staff\|app-settings}` | `setting_access` + matching permission |
| GET | `/api/audit-logs`, `/api/user-logs` · POST `/api/user-logs/purge` | `audit_log_access`, `user_log_access`, `user_log_delete` |
| POST | `/api/comments` (Add Comment), `/api/comments/bulk-delete` | `comment_grand_edit`, `comment_delete` |

### Database tables

`users`, `departments`, `titles`, `positions`, `categories`, `organisations`, `document_types`, `staff`, `app_settings`,
`documents`, `document_attachments`, `comments` (one row per person in the flow: `step_order`, `status` = waiting/current/done, `is_final`, `is_esign`),
`document_histories`, `user_alerts`, `user_alert_user`, `personal_access_tokens`,
`roles`, `permissions`, `role_user`, `permission_role`, `user_infos`, `audit_logs`, `user_logs`.

Attachments and signatures are stored on Laravel's private `local` disk (under `backend/storage/app`). They are only served to logged-in users who take part in that document. The logo is public.

---

## 7. Not included yet

The *Messages*, *Asset management* and *Expense management* menus from the old system are not built. They are separate modules and can be added later using the same CRUD pattern (`SettingsController` + `CrudPage.vue`).
# ODF
