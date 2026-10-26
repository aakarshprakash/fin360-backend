# 🔌 Complete API Reference — CRM + Accounting (229 Endpoints)

> **Base URL:** `https://leadcrm.vpsappserverone.in/api`  
> **Auth:** All endpoints (except login/register/password-reset) require `Authorization: Bearer {token}`  
> **Framework:** Laravel 8 + Sanctum  
> **Key Naming:** Frontend sends camelCase → auto-converted to snake_case. Responses are auto-converted back. (e.g., `unitPrice` ↔ `unit_price`, `dueDate` ↔ `due_date`)  
> **Pagination:** List endpoints support `?page=`, `?per_page=`, `?search=` and return `{ data: [], meta: { current_page, last_page, per_page, total } }`  
> **Status:** ✅ ALL 229 endpoints are BUILT and deployed  
> **Last Updated:** March 12, 2026  
> **RBAC:** Dynamic Role Based Access Control is now active. Routes protected by `permission:` middleware. Admin users bypass all permission checks.

---

## ✅ Auth (9 endpoints) — UPDATED 🔄

> **⚠️ BREAKING CHANGES:** Login & Register response shape has changed. New fields: `refresh_token`, `token_type`, `role_data`, `all_permissions`. See details below.

| # | Method | Endpoint | Purpose | Auth |
|---|--------|----------|---------|------|
| 1 | POST | `/api/register` | Register new user | Public |
| 2 | POST | `/api/login` | Login & get token + refresh token | Public (rate limited: 10/min) |
| 3 | POST | `/api/password/forgot` | Send password reset link to email | Public (rate limited: 5/min) |
| 4 | POST | `/api/password/reset` | Reset password using token | Public (rate limited: 5/min) |
| 5 | GET | `/api/me` | Get current authenticated user with role & permissions | Bearer |
| 6 | POST | `/api/logout` | Logout & revoke current token | Bearer |
| 7 | POST | `/api/logout-everywhere` | Revoke ALL tokens (logout from all devices) | Bearer |
| 8 | POST | `/api/refresh-token` | Get new auth token using current token | Bearer |
| 9 | GET | `/api/auth-check` | Verify token is valid | Bearer |

### POST `/api/login` — Response (CHANGED):
```json
{
  "message": "Login successful",
  "user": {
    "id": 1,
    "name": "Admin User",
    "email": "admin@crm.com",
    "phone": "+1-555-100-0001",
    "role": "Admin",
    "role_id": 1,
    "role_data": {
      "id": 1,
      "name": "Admin",
      "slug": "admin",
      "permissions": ["view_leads", "create_leads", "edit_leads", "delete_leads", "manage_users", "view_reports", "..."]
    },
    "department": "Management",
    "status": "Active",
    "permissions": ["view_leads", "create_leads"],
    "all_permissions": ["view_leads", "create_leads", "edit_leads", "delete_leads", "manage_users", "view_reports", "..."],
    "avatar": null,
    "email_verified_at": null,
    "last_active": "2026-03-10T10:00:00.000000Z",
    "created_at": "2026-03-04T09:10:24.000000Z"
  },
  "token": "1|abc123...",
  "refresh_token": "2|def456...",
  "token_type": "Bearer"
}
```

**New fields in user object:**
| Field | Type | Description |
|-------|------|-------------|
| `role_id` | int\|null | FK to `roles` table (dynamic RBAC role) |
| `role_data` | object\|null | Full role object with `id`, `name`, `slug`, `permissions[]` |
| `all_permissions` | string[] | **Merged** permissions from role + user-level overrides. **Use this for UI rendering.** |
| `permissions` | string[] | Per-user permission overrides (legacy, kept for backward compat) |
| `email_verified_at` | string\|null | Email verification timestamp |

**New fields in response:**
| Field | Type | Description |
|-------|------|-------------|
| `refresh_token` | string | Long-lived token (30 days) for refreshing auth tokens |
| `token_type` | string | Always `"Bearer"` |

### POST `/api/register` — Response (CHANGED):
Same user shape as login. Returns `token` and `token_type` (no `refresh_token` on register).

### POST `/api/logout-everywhere` — NEW:
Revokes ALL tokens for the user across all devices/sessions.
```json
{ "message": "Logged out from all devices successfully" }
```

### POST `/api/refresh-token` — NEW:
Revokes current token and issues new `token` + `refresh_token`.
```json
{
  "message": "Token refreshed successfully",
  "token": "3|newtoken...",
  "refresh_token": "4|newrefresh...",
  "token_type": "Bearer"
}
```

### GET `/api/me` — Response (CHANGED):
Returns same user shape as login (with `role_data`, `all_permissions`).

### POST `/api/password/forgot` — NEW:
Send a password reset link to the user's registered email address.
```json
// Request:
{ "email": "user@crm.com" }

// Success Response (200):
{ "message": "We have emailed your password reset link." }

// Error Response (422):
{ "errors": { "email": ["We can't find a user with that email address."] } }
```
**Rate limited:** 5 requests per minute.

### POST `/api/password/reset` — NEW:
Reset the user's password using the token received via email.
```json
// Request:
{
  "token": "abc123...",
  "email": "user@crm.com",
  "password": "newpassword123",
  "password_confirmation": "newpassword123"
}

// Success Response (200):
{ "message": "Your password has been reset." }

// Error Response (422):
{ "errors": { "email": ["This password reset token is invalid."] } }
```
**Rate limited:** 5 requests per minute.  
**Side effects:** All existing auth tokens for the user are revoked on successful reset (forces re-login).

---

## ✅ Roles & Permissions — Dynamic RBAC (12 endpoints) — NEW 🆕

> **Permission Required:** `view_roles`, `create_roles`, `edit_roles`, `delete_roles`, `manage_permissions`  
> **Admin users bypass all permission checks.**

### Roles APIs (5 endpoints)

| # | Method | Endpoint | Request Body | Purpose | Permission |
|---|--------|----------|-------------|---------|------------|
| 8 | **GET** | `/api/roles` | `?search=&status=active` | List all roles with permissions & user count | `view_roles` |
| 9 | **GET** | `/api/roles/{id}` | — | Get role detail with permissions & assigned users | `view_roles` |
| 10 | **POST** | `/api/roles` | `{ name, description, is_active, permissions[] }` | Create a new role | `create_roles` |
| 11 | **PUT** | `/api/roles/{id}` | `{ name, description, is_active, permissions[] }` | Update role & sync permissions | `edit_roles` |
| 12 | **DELETE** | `/api/roles/{id}` | — | Delete role (fails if users assigned or system role) | `delete_roles` |

### Role Object Shape:
```json
{
  "id": 1,
  "name": "Admin",
  "slug": "admin",
  "description": "Full system access. Can manage all modules, users, roles, and settings.",
  "is_system": true,
  "is_active": true,
  "users_count": 1,
  "permissions": [
    { "id": 1, "name": "View Leads", "slug": "view_leads", "module": "leads" },
    { "id": 2, "name": "Create Leads", "slug": "create_leads", "module": "leads" }
  ],
  "created_at": "Mar 10, 2026",
  "updated_at": "Mar 10, 2026"
}
```

### POST `/api/roles` — Request Body:
```json
{
  "name": "Team Lead",
  "description": "Can manage team leads and view reports",
  "is_active": true,
  "permissions": ["view_leads", "create_leads", "edit_leads", "assign_leads", "view_reports"]
}
```

**Validation:**
| Field | Required | Rules |
|-------|----------|-------|
| `name` | Yes | string, max:100, unique |
| `description` | No | string, max:500 |
| `is_active` | No | boolean (default: true) |
| `permissions` | No | array of permission slugs (must exist in DB) |

**Business Rules:**
- System roles (`is_system: true`) cannot be renamed or deleted
- Cannot delete a role that has users assigned (reassign them first)
- `slug` is auto-generated from `name`

### GET `/api/roles/{id}` — Extended Response:
Includes `users` array with assigned users:
```json
{
  "id": 1,
  "name": "Admin",
  "slug": "admin",
  "...": "...",
  "users": [
    { "id": 1, "name": "Admin User", "email": "admin@crm.com", "role": "Admin", "status": "Active", "avatar": null }
  ]
}
```

---

### Permissions APIs (5 endpoints)

| # | Method | Endpoint | Request Body | Purpose | Permission |
|---|--------|----------|-------------|---------|------------|
| 13 | **GET** | `/api/permissions` | `?module=leads&search=` | List all permissions (flat + grouped by module) | `manage_permissions` |
| 14 | **GET** | `/api/permissions/{id}` | — | Get permission detail with assigned roles | `manage_permissions` |
| 15 | **POST** | `/api/permissions` | `{ name, module, description }` | Create a new permission | `manage_permissions` |
| 16 | **PUT** | `/api/permissions/{id}` | `{ name, module, description, is_active }` | Update permission | `manage_permissions` |
| 17 | **DELETE** | `/api/permissions/{id}` | — | Delete permission | `manage_permissions` |

### GET `/api/permissions` — Response:
```json
{
  "data": [
    { "id": 1, "name": "View Leads", "slug": "view_leads", "module": "leads", "description": "View lead listings", "is_active": true }
  ],
  "grouped": [
    {
      "module": "leads",
      "permissions": [
        { "id": 1, "name": "View Leads", "slug": "view_leads", "module": "leads", "description": "...", "is_active": true },
        { "id": 2, "name": "Create Leads", "slug": "create_leads", "module": "leads", "description": "...", "is_active": true }
      ]
    },
    {
      "module": "users",
      "permissions": [...]
    }
  ],
  "modules": ["leads", "followups", "call_logs", "notes", "customers", "users", "roles", "dashboard", "reports", "settings", "lead_sources", "notifications", "activity_logs", "accounting"]
}
```

### POST `/api/permissions` — Request Body:
```json
{
  "name": "Archive Leads",
  "module": "leads",
  "description": "Can archive old leads"
}
```

**Default Permission Modules & Slugs (seeded):**

| Module | Permission Slugs |
|--------|-----------------|
| `leads` | `view_leads`, `create_leads`, `edit_leads`, `delete_leads`, `export_leads`, `convert_leads`, `assign_leads` |
| `followups` | `view_followups`, `create_followups`, `edit_followups`, `delete_followups` |
| `call_logs` | `view_call_logs`, `create_call_logs`, `edit_call_logs`, `delete_call_logs` |
| `notes` | `view_notes`, `create_notes`, `edit_notes`, `delete_notes` |
| `customers` | `view_customers`, `create_customers`, `edit_customers`, `delete_customers` |
| `users` | `view_users`, `create_users`, `edit_users`, `delete_users`, `manage_users` |
| `roles` | `view_roles`, `create_roles`, `edit_roles`, `delete_roles`, `manage_permissions` |
| `dashboard` | `view_dashboard` |
| `reports` | `view_reports`, `export_reports` |
| `settings` | `view_settings`, `manage_settings` |
| `lead_sources` | `view_lead_sources`, `manage_lead_sources` |
| `notifications` | `view_notifications` |
| `activity_logs` | `view_activity_logs` |
| `accounting` | `view_accounting`, `manage_accounting`, `view_invoices`, `create_invoices`, `manage_invoices`, `view_expenses`, `manage_expenses`, `view_quotations`, `manage_quotations` |

### Default Roles (seeded):

| Role | Slug | System? | Key Permissions |
|------|------|---------|-----------------|
| Admin | `admin` | Yes | ALL permissions (super-admin bypass) |
| Manager | `manager` | Yes | All lead/followup/call/note CRUD + view users + reports + accounting read |
| Sales Agent | `sales-agent` | Yes | Lead/followup/call/note CRUD (own) + dashboard |
| Support Agent | `support-agent` | No | View leads + create call logs/notes + dashboard |
| Viewer | `viewer` | No | Read-only access to leads, customers, dashboard |

---

## ✅ User Activity Logs (2 endpoints) — NEW 🆕

> **Permission Required:** `view_activity_logs`

| # | Method | Endpoint | Query Params | Purpose | Permission |
|---|--------|----------|-------------|---------|------------|
| 18 | **GET** | `/api/activity-logs` | `?user_id=&action=&module=&date_from=&date_to=&search=&per_page=50` | Paginated activity audit trail | `view_activity_logs` |
| 19 | **GET** | `/api/activity-logs/stats` | `?date_from=&date_to=` | Activity summary statistics | `view_activity_logs` |

### GET `/api/activity-logs` — Response (paginated):
```json
{
  "data": [
    {
      "id": 1,
      "user": { "id": 1, "name": "Admin User", "email": "admin@crm.com", "role": "Admin" },
      "action": "login",
      "module": "auth",
      "target_type": null,
      "target_id": null,
      "old_values": null,
      "new_values": null,
      "ip_address": "192.168.1.1",
      "user_agent": "Mozilla/5.0 ...",
      "created_at": "Mar 10, 2026 10:30 AM"
    },
    {
      "id": 2,
      "user": { "id": 1, "name": "Admin User", "email": "admin@crm.com", "role": "Admin" },
      "action": "create",
      "module": "users",
      "target_type": "User",
      "target_id": 5,
      "old_values": null,
      "new_values": { "name": "John", "email": "john@crm.com", "role": "Sales Agent" },
      "ip_address": "192.168.1.1",
      "user_agent": "Mozilla/5.0 ...",
      "created_at": "Mar 10, 2026 10:35 AM"
    }
  ],
  "current_page": 1,
  "last_page": 5,
  "per_page": 50,
  "total": 230
}
```

**Logged actions:** `login`, `logout`, `logout_everywhere`, `register`, `password_change`, `create`, `update`, `delete`, `assign_role`, `update_permissions`

**Logged modules:** `auth`, `users`, `roles`, `permissions`, `leads`, `followups`, `call_logs`, `notes`, `customers`

### GET `/api/activity-logs/stats` — Response:
```json
{
  "data": {
    "total_actions": 1250,
    "total_logins": 340,
    "total_creates": 450,
    "total_updates": 320,
    "total_deletes": 45,
    "by_module": [
      { "module": "auth", "count": 380 },
      { "module": "leads", "count": 520 },
      { "module": "users", "count": 120 }
    ],
    "top_users": [
      { "user_id": 1, "user_name": "Admin User", "count": 450 },
      { "user_id": 2, "user_name": "Sarah Miller", "count": 320 }
    ]
  }
}
```

---

## ✅ FOLLOW-UPS APIs (7 endpoints) — LIVE

| # | Method | Endpoint | Request Body | Purpose |
|---|--------|----------|-------------|---------|
| 5 | **GET** | `/api/leads/{id}/follow-ups` | — | List all follow-ups for a lead |
| 6 | **POST** | `/api/leads/{id}/follow-ups` | `{ note, date, time, type }` | Create a new follow-up for a lead |
| 7 | **PUT** | `/api/follow-ups/{id}` | `{ note, date, time, type, status }` | Update a follow-up |
| 8 | **PATCH** | `/api/follow-ups/{id}/complete` | `{ completed: true }` | Mark follow-up as completed |
| 9 | **DELETE** | `/api/follow-ups/{id}` | — | Delete a follow-up |
| 10 | **GET** | `/api/follow-ups/today` | — | Get today's follow-ups (all leads) |
| 11 | **GET** | `/api/follow-ups/overdue` | — | Get overdue follow-ups (all leads) |

### Follow-Up Object Shape:
```json
{
  "id": 1,
  "lead_id": 1,
  "date": "Feb 10, 2026",
  "time": "10:00 AM",
  "type": "call",           // call | whatsapp | email | meeting | visit
  "status": "pending",      // pending | completed | overdue
  "note": "Schedule demo call to present enterprise features.",
  "outcome": null,           // null when pending, string when completed
  "created_at": "Feb 5, 2026",
  "created_by": "Sarah Miller"
}
```

### POST `/api/leads/{id}/follow-ups` — Request Body:
```json
{
  "note": "Schedule demo call",
  "date": "2026-03-15",
  "time": "10:00",
  "type": "call"
}
```
**Validation:**
| Field | Required | Rules |
|-------|----------|-------|
| `note` | Yes | min:5, max:500 |
| `date` | Yes | date, after_or_equal:today |
| `time` | Yes | time format HH:mm |
| `type` | Yes | in: call, whatsapp, email, meeting, visit |

---

## ✅ ACTIVITY TIMELINE / CALL LOGS APIs (5 endpoints) — LIVE

| # | Method | Endpoint | Request Body | Purpose |
|---|--------|----------|-------------|---------|
| 12 | **GET** | `/api/leads/{id}/call-logs` | — | List all call logs / activity timeline for a lead |
| 13 | **POST** | `/api/leads/{id}/call-logs` | `{ type, outcome, duration, notes }` | Log a new communication activity |
| 14 | **PUT** | `/api/call-logs/{id}` | `{ type, outcome, duration, notes }` | Update a call log entry |
| 15 | **DELETE** | `/api/call-logs/{id}` | — | Delete a call log entry |
| 16 | **GET** | `/api/call-logs/stats` | `?startDate=&endDate=&userId=` | Communication statistics |

### Call Log Object Shape:
```json
{
  "id": 1,
  "lead_id": 1,
  "type": "call",           // call | whatsapp | email | meeting | sms | video_call | status_change
  "outcome": "connected",   // connected | no_answer | busy | voicemail | callback_requested | interested | not_interested | follow_up_needed | deal_closed | info
  "duration": "15",         // minutes (for call/video_call only)
  "notes": "Discussed product features. Client is interested in enterprise plan.",
  "timestamp": "Feb 5, 2026 10:30 AM",
  "created_by": "Sarah Miller"
}
```

### POST `/api/leads/{id}/call-logs` — Request Body:
```json
{
  "type": "call",
  "outcome": "connected",
  "duration": 15,
  "notes": "Discussed product features. Client interested in enterprise plan."
}
```
**Validation:**
| Field | Required | Rules |
|-------|----------|-------|
| `type` | Yes | in: call, whatsapp, email, meeting, sms, video_call |
| `outcome` | Yes | in: connected, no_answer, busy, voicemail, callback_requested, interested, not_interested, follow_up_needed, deal_closed |
| `duration` | No | integer, min:0 (only for call/video_call) |
| `notes` | Yes | min:5, max:1000 |

---

## ✅ NOTES & COMMENTS APIs (4 endpoints) — LIVE

| # | Method | Endpoint | Request Body | Purpose |
|---|--------|----------|-------------|---------|
| 17 | **GET** | `/api/leads/{id}/notes` | — | List all notes for a lead |
| 18 | **POST** | `/api/leads/{id}/notes` | `{ type, content, objection_type? }` | Add a note to a lead |
| 19 | **PUT** | `/api/notes/{id}` | `{ type, content, objection_type? }` | Update a note |
| 20 | **DELETE** | `/api/notes/{id}` | — | Delete a note |

### Note Object Shape:
```json
{
  "id": 1,
  "lead_id": 1,
  "type": "general",         // general | objection
  "content": "Client showed strong interest in analytics dashboard.",
  "objection_type": null,    // price | feature | competitor | timing (only when type=objection)
  "timestamp": "Feb 5, 2026 11:00 AM",
  "created_by": "Sarah Miller"
}
```

### POST `/api/leads/{id}/notes` — Request Body:
```json
{
  "type": "general",
  "content": "Client showed strong interest in analytics dashboard."
}
```
**For objection notes:**
```json
{
  "type": "objection",
  "content": "Client mentioned budget constraints. May need quarterly payment.",
  "objection_type": "price"
}
```
**Validation:**
| Field | Required | Rules |
|-------|----------|-------|
| `type` | No | in: general, objection (default: general) |
| `content` | Yes | min:5, max:1000 |
| `objection_type` | Only if type=objection | in: price, feature, competitor, timing |

---

## ✅ LEADS APIs (10 endpoints) — LIVE

| # | Method | Endpoint | Request Body | Purpose |
|---|--------|----------|-------------|---------|
| 21 | **GET** | `/api/leads` | `?search=&page=&per_page=&status=&priority=&source=&assigned_to_id=` | List all leads (paginated) |
| 22 | **POST** | `/api/leads` | `{ name, email, phone, company, status, source, notes }` | Create new lead |
| 23 | **GET** | `/api/leads/{id}` | — | Get single lead detail |
| 24 | **PUT** | `/api/leads/{id}` | `{ name, email, phone, ... }` | Update lead |
| 25 | **DELETE** | `/api/leads/{id}` | — | Soft delete lead |
| 26 | **PATCH** | `/api/leads/{id}/status` | `{ status }` | Quick status update |
| 27 | **POST** | `/api/leads/{id}/convert` | — | Convert lead to customer |
| 27a | **POST/PATCH** | `/api/leads/{id}/assign` | `{ assigned_to, reason? }` | Assign/reassign lead (accepts both POST & PATCH) |
| 27b | **POST** | `/api/leads/bulk-assign` | `{ lead_ids[], assigned_to, reason? }` | Bulk assign leads |
| 27c | **GET** | `/api/leads/{id}/transfer-history` | — | Get lead assignment history |

---

## ✅ CUSTOMERS APIs (2 endpoints) — LIVE

| # | Method | Endpoint | Purpose |
|---|--------|----------|---------|
| 28 | **GET** | `/api/customers` | List all customers (paginated) |
| 29 | **GET** | `/api/customers/{id}` | Get customer detail |

---

## ✅ USERS APIs — Admin (8 endpoints) — LIVE

| # | Method | Endpoint | Request Body | Purpose |
|---|--------|----------|-------------|---------|
| 30 | **GET** | `/api/users` | `?search=&page=&per_page=&role=&role_id=&status=&department=` | List all users (paginated) |
| 31 | **GET** | `/api/users/{id}` | — | Get user detail + performance |
| 32 | **POST** | `/api/users` | `{ name, email, password, phone, role, role_id, department, ... }` | Create user |
| 33 | **PUT** | `/api/users/{id}` | `{ name, email, phone, role, role_id, department_id, title_id, ... }` | Update user |
| 34 | **DELETE** | `/api/users/{id}` | — | Soft delete user |
| 34a | **POST** | `/api/users/{id}/assign-role` | `{ role_id }` | Assign role to user |
| 34b | **GET** | `/api/users/{id}/permissions` | — | Get user's resolved permissions |
| 34c | **PUT** | `/api/users/{id}/permissions` | `{ role_id?, permissions[]? }` | Update user's role & permission overrides |

---

## ✅ DASHBOARD APIs (4 endpoints) — LIVE

| # | Method | Endpoint | Purpose |
|---|--------|----------|---------|
| 35 | **GET** | `/api/dashboard/stats` | KPIs, funnel, status breakdown |
| 36 | **GET** | `/api/dashboard/recent-leads` | Latest leads list |
| 37 | **GET** | `/api/dashboard/pending-followups` | Today's tasks + overdue |
| 38 | **GET** | `/api/dashboard/calendar-events` | Calendar events for current month |

---

## ✅ LEAD SOURCES APIs (4 endpoints) — LIVE

| # | Method | Endpoint | Request Body | Purpose |
|---|--------|----------|-------------|---------|
| 39 | **GET** | `/api/lead-sources` | — | List all lead sources |
| 40 | **POST** | `/api/lead-sources` | `{ name, color, icon }` | Create lead source |
| 41 | **PUT** | `/api/lead-sources/{id}` | `{ name, color, icon }` | Update lead source |
| 42 | **DELETE** | `/api/lead-sources/{id}` | — | Delete lead source |

---

## ✅ NOTIFICATIONS API (1 endpoint) — LIVE

| # | Method | Endpoint | Purpose |
|---|--------|----------|---------|
| 43 | **GET** | `/api/notifications` | Today's follow-ups, overdue, newly assigned |

---

## ✅ SETTINGS & PROFILE APIs (4 endpoints) — LIVE

| # | Method | Endpoint | Request Body | Purpose |
|---|--------|----------|-------------|---------|
| 44 | **PUT** | `/api/users/profile` | `{ name, email, phone }` | Update own profile |
| 45 | **PUT** | `/api/users/password` | `{ currentPassword, newPassword, confirmPassword }` | Change password |
| 46 | **GET** | `/api/settings` | — | Get app settings |
| 47 | **PUT** | `/api/settings` | `{ company, appearance, notifications }` | Update app settings |

---

## ✅ EXPORT APIs (2 endpoints) — LIVE

| # | Method | Endpoint | Purpose |
|---|--------|----------|---------|
| 48 | **GET** | `/api/leads/export/csv` | Export leads as CSV |
| 49 | **GET** | `/api/leads/export/xlsx` | Export leads as Excel |

---

## ✅ DEPARTMENTS APIs (7 endpoints) — LIVE

| # | Method | Endpoint | Request Body | Purpose |
|---|--------|----------|-------------|---------|
| 50 | **GET** | `/api/departments` | `?is_active=1` | List all departments |
| 51 | **POST** | `/api/departments` | `{ name, description? }` | Create department |
| 52 | **GET** | `/api/departments/{id}` | — | Get department detail with job titles |
| 53 | **PUT** | `/api/departments/{id}` | `{ name, description?, is_active? }` | Update department |
| 54 | **DELETE** | `/api/departments/{id}` | — | Delete department (nullifies linked job titles) |
| 55 | **PATCH** | `/api/departments/{id}/toggle` | — | Toggle department active/inactive |
| 56 | **GET** | `/api/departments/{id}/job-titles` | — | List job titles for a department |

---

## ✅ JOB TITLES APIs (5 endpoints) — LIVE

| # | Method | Endpoint | Request Body | Purpose |
|---|--------|----------|-------------|---------|
| 57 | **GET** | `/api/job-titles` | `?department_id=&is_active=1` | List all job titles |
| 58 | **POST** | `/api/job-titles` | `{ name, department_id? }` | Create job title |
| 59 | **PUT** | `/api/job-titles/{id}` | `{ name, department_id?, is_active? }` | Update job title |
| 60 | **DELETE** | `/api/job-titles/{id}` | — | Delete job title |
| 61 | **PATCH** | `/api/job-titles/{id}/toggle` | — | Toggle job title active/inactive |

---

## ✅ LEAD STATUSES APIs (6 endpoints) — LIVE

| # | Method | Endpoint | Request Body | Purpose |
|---|--------|----------|-------------|---------|
| 62 | **GET** | `/api/lead-statuses` | `?is_active=1` | List all lead statuses (ordered by sort_order) |
| 63 | **POST** | `/api/lead-statuses` | `{ name, color, description? }` | Create lead status |
| 64 | **PUT** | `/api/lead-statuses/{id}` | `{ name, color, description?, is_active?, sort_order? }` | Update lead status |
| 65 | **DELETE** | `/api/lead-statuses/{id}` | — | Delete lead status (blocked if leads use it) |
| 66 | **PATCH** | `/api/lead-statuses/{id}/toggle` | — | Toggle lead status active/inactive |
| 67 | **PATCH** | `/api/lead-statuses/reorder` | `{ ids: [3, 1, 2, ...] }` | Reorder lead statuses |

---

## ✅ ROUND-ROBIN SETTINGS APIs (2 endpoints) — LIVE

| # | Method | Endpoint | Request Body | Purpose |
|---|--------|----------|-------------|---------|
| 68 | **GET** | `/api/round-robin` | — | Get round-robin configuration |
| 69 | **PUT** | `/api/round-robin` | `{ enabled, participant_ids[] }` | Update round-robin settings |

---

## 🗄️ Database Tables (37 total)

### CRM Tables (19)
| Table | Purpose |
|-------|---------|
| `users` | User accounts, roles, permissions |
| `password_resets` | Password reset tokens |
| `failed_jobs` | Failed job tracking |
| `personal_access_tokens` | Sanctum API tokens |
| `roles` | Dynamic RBAC roles |
| `permissions` | Dynamic RBAC permissions |
| `role_permissions` | Role-permission pivot |
| `user_permissions` | User-level permission overrides pivot |
| `user_activity_logs` | User activity audit trail |
| `leads` | Lead records |
| `lead_statuses` | Configurable lead statuses with sort order |
| `lead_transfers` | Lead assignment/transfer history |
| `followups` | Follow-up schedules per lead |
| `call_logs` | Call logs, WhatsApp, emails, activity timeline |
| `notes` | Notes & comments per lead |
| `lead_sources` | Configurable lead sources |
| `customers` | Converted leads / customers |
| `departments` | Department management |
| `job_titles` | Job title management (linked to departments) |
| `round_robin_settings` | Round-robin lead assignment config |
| `settings` | App settings per user |

### Accounting Tables (28) — NEW
| Table | Purpose |
|-------|---------|
| `currencies` | Multi-currency support |
| `taxes` | Tax rates (CGST/SGST/IGST/VAT) |
| `financial_years` | Financial year tracking & year-end closing |
| `accounting_settings` | Accounting config per user |
| `accounting_accounts` | Chart of Accounts |
| `accounting_customers` | Accounting customers (separate from CRM) |
| `vendors` | Vendor / supplier management |
| `bank_accounts` | Bank account tracking |
| `invoices` | Sales invoices — **must have `customer_name` column** (nullable string) in addition to `customer_id` FK |
| `invoice_items` | Invoice line items — columns: `description`, `quantity`, `unit_price`, `discount`, `tax_rate`, `tax_amount`, `total` |
| `invoice_payments` | Payment tracking |
| `credit_notes` | Credit note management |
| `bills` | Purchase bills — **must have `vendor_name` column** (nullable string) in addition to `vendor_id` FK |
| `bill_items` | Bill line items — columns: `description`, `quantity`, `unit_price`, `tax_rate`, `tax_amount`, `total` |
| `bill_payments` | Bill payment tracking |
| `debit_notes` | Debit note management |
| `income_entries` | Income records |
| `expense_entries` | Expense records |
| `bank_transactions` | Bank deposits/withdrawals/transfers |
| `journal_entries` | Double-entry journal headers |
| `journal_entry_lines` | Journal entry debit/credit lines |
| `recurring_transactions` | Recurring income/expense automation |
| `invoice_template_settings` | Invoice template & print configuration |
| `payment_methods` | Payment methods shown on invoices (bank, UPI, cash) |
| `branches` | Multi-branch / multi-location management |
| `quotations` | Sales quotations — columns: `quotation_number`, `customer_id`, `customer_name`, `date`, `valid_until`, `currency`, `exchange_rate`, `subtotal`, `discount`, `tax`, `total`, `status`, `notes`, `terms_and_conditions`, `branch`, `converted_invoice_id`, `user_id` |
| `quotation_items` | Quotation line items — columns: `quotation_id`, `description`, `quantity`, `unit_price`, `discount`, `tax_rate`, `tax_amount`, `total` |
| `audit_logs` | Full audit trail |

---

## ✅ DEPLOYMENT STATUS — ALL COMPLETE

All 229 API endpoints are fully implemented with controllers, models, migrations, and routes.

### Server Deployment Steps:
1. Upload ZIP via `updater.php` or SSH
2. Run `php artisan migrate --force` (creates all tables including accounting)
3. Run `php artisan db:seed` (seeds RBAC roles, permissions, lead statuses, departments)
4. Run `php artisan config:clear && php artisan route:clear && php artisan cache:clear`
5. All endpoints are live

### Scheduled Tasks:
- `php artisan accounting:process-recurring` — runs daily via Laravel scheduler
- `php artisan accounting:expire-quotations` — runs daily, marks expired quotations

---

**Total: 94 CRM + 135 Accounting = 229 API endpoints**  
**All endpoints are BUILT and deployed.**  
**CRM: 94 ✅** | **Accounting: 135 ✅**

---

## ✅ ACCOUNTING MODULE APIs (125 endpoints) — LIVE

> **Base Path:** `/api/accounting/`  
> **Auth:** All endpoints require `Authorization: Bearer {token}`  
> **Frontend Source:** `src/utils/accountingApi.js`  
> **Status:** ✅ ALL endpoints are BUILT — controllers, models, migrations, routes  
> **Migration:** `2026_03_09_000001_create_accounting_tables.php` (26 tables)

---

### ✅ Chart of Accounts (6 endpoints) — LIVE

| # | Method | Endpoint | Request Body | Purpose |
|---|--------|----------|-------------|---------|
| 50 | **GET** | `/api/accounting/accounts` | `?type=Assets&group=Current Assets` | List all accounts (filterable) |
| 51 | **GET** | `/api/accounting/accounts/{id}` | — | Get single account |
| 52 | **POST** | `/api/accounting/accounts` | `{ code, name, type, group, description, openingBalance }` | Create account |
| 53 | **PUT** | `/api/accounting/accounts/{id}` | `{ code, name, type, group, description }` | Update account |
| 54 | **DELETE** | `/api/accounting/accounts/{id}` | — | Delete account (fail if transactions exist) |
| 55 | **GET** | `/api/accounting/accounts/{id}/ledger` | `?dateFrom&dateTo` | Get account ledger entries |

---

### ✅ Accounting Customers (8 endpoints) — LIVE

| # | Method | Endpoint | Request Body | Purpose |
|---|--------|----------|-------------|---------|
| 56 | **GET** | `/api/accounting/customers` | `?search&currency` | List customers |
| 57 | **GET** | `/api/accounting/customers/{id}` | — | Get customer detail |
| 58 | **POST** | `/api/accounting/customers` | `{ name, email, phone, gstNumber, address, currency, creditLimit }` | Create customer |
| 59 | **PUT** | `/api/accounting/customers/{id}` | `{ ...fields }` | Update customer |
| 60 | **DELETE** | `/api/accounting/customers/{id}` | — | Delete customer |
| 61 | **GET** | `/api/accounting/customers/{id}/ledger` | `?dateFrom&dateTo` | Customer ledger (debit/credit/balance) |
| 62 | **GET** | `/api/accounting/customers/{id}/invoices` | `?status` | Customer invoices |
| 63 | **GET** | `/api/accounting/customers/{id}/statement` | `?dateFrom&dateTo` | Customer statement |

---

### ✅ Vendors (8 endpoints) — LIVE

| # | Method | Endpoint | Request Body | Purpose |
|---|--------|----------|-------------|---------|
| 64 | **GET** | `/api/accounting/vendors` | `?search` | List vendors |
| 65 | **GET** | `/api/accounting/vendors/{id}` | — | Get vendor detail |
| 66 | **POST** | `/api/accounting/vendors` | `{ name, gstNumber, address, phone, email, paymentTerms }` | Create vendor |
| 67 | **PUT** | `/api/accounting/vendors/{id}` | `{ ...fields }` | Update vendor |
| 68 | **DELETE** | `/api/accounting/vendors/{id}` | — | Delete vendor |
| 69 | **GET** | `/api/accounting/vendors/{id}/ledger` | `?dateFrom&dateTo` | Vendor ledger |
| 70 | **GET** | `/api/accounting/vendors/{id}/bills` | `?status` | Vendor bills |
| 71 | **GET** | `/api/accounting/vendors/{id}/statement` | `?dateFrom&dateTo` | Vendor statement |

---

### ✅ Invoices / Sales (12 endpoints) — LIVE

| # | Method | Endpoint | Request Body | Purpose |
|---|--------|----------|-------------|---------|
| 72 | **GET** | `/api/accounting/invoices` | `?status&customer&dateFrom&dateTo` | List invoices |
| 73 | **GET** | `/api/accounting/invoices/{id}` | — | Get invoice with items & payments |
| 74 | **POST** | `/api/accounting/invoices` | `{ customer_name, customer_id?, date, due_date, currency, exchange_rate, items[{ description, quantity, unit_price, discount, tax_rate, tax_amount, total }], notes, subtotal, tax, discount, total }` | Create invoice (auto-creates journal entry) |
| 75 | **PUT** | `/api/accounting/invoices/{id}` | `{ customer_name?, customer_id?, date?, due_date?, currency?, exchange_rate?, items[{ description, quantity, unit_price, discount, tax_rate, tax_amount, total }]?, notes?, subtotal?, tax?, discount?, total? }` | Update invoice (replaces items if provided, recalculates journal entry) |
| 76 | **DELETE** | `/api/accounting/invoices/{id}` | — | Delete invoice |
| 77 | **PATCH** | `/api/accounting/invoices/{id}/status` | `{ status: "paid"|"pending"|"overdue"|"draft" }` | Change invoice status |
| 78 | **POST** | `/api/accounting/invoices/{id}/payments` | `{ amount, date, method, reference }` | Record payment against invoice |
| 79 | **GET** | `/api/accounting/invoices/{id}/payments` | — | List payments for invoice |
| 80 | **POST** | `/api/accounting/invoices/{id}/send` | — | Send invoice via email |
| 81 | **GET** | `/api/accounting/invoices/{id}/pdf` | — | Download invoice PDF |
| 82 | **POST** | `/api/accounting/credit-notes` | `{ invoiceId, items[], reason }` | Create credit note |
| 83 | **GET** | `/api/accounting/credit-notes` | `?invoiceId` | List credit notes |

**Auto Accounting Entry on Invoice Create:**
```
Debit:  Accounts Receivable    = total
Credit: Sales Revenue          = subtotal - discount
Credit: GST Payable            = tax
```

---

### ✅ Bills / Purchases (10 endpoints) — LIVE

| # | Method | Endpoint | Request Body | Purpose |
|---|--------|----------|-------------|---------|
| 84 | **GET** | `/api/accounting/bills` | `?status&vendor&dateFrom&dateTo` | List bills |
| 85 | **GET** | `/api/accounting/bills/{id}` | — | Get bill with items |
| 86 | **POST** | `/api/accounting/bills` | `{ vendor_name, vendor_id?, date, due_date, items[{ description, quantity, unit_price, tax_rate, tax_amount, total }], notes, subtotal, tax, total }` | Create bill (auto-creates journal entry) |
| 87 | **PUT** | `/api/accounting/bills/{id}` | `{ ...fields }` | Update bill |
| 88 | **DELETE** | `/api/accounting/bills/{id}` | — | Delete bill |
| 89 | **PATCH** | `/api/accounting/bills/{id}/status` | `{ status }` | Change bill status |
| 90 | **POST** | `/api/accounting/bills/{id}/payments` | `{ amount, date, method, reference }` | Record payment for bill |
| 91 | **GET** | `/api/accounting/bills/{id}/payments` | — | List payments for bill |
| 92 | **POST** | `/api/accounting/debit-notes` | `{ billId, items[], reason }` | Create debit note |
| 93 | **GET** | `/api/accounting/debit-notes` | `?billId` | List debit notes |

**Auto Accounting Entry on Bill Create:**
```
Debit:  Expense / Inventory     = subtotal
Debit:  Input GST               = tax
Credit: Accounts Payable        = total
```

---

### ✅ Income (6 endpoints) — LIVE

| # | Method | Endpoint | Request Body | Purpose |
|---|--------|----------|-------------|---------|
| 94 | **GET** | `/api/accounting/income` | `?category&dateFrom&dateTo` | List income entries |
| 95 | **GET** | `/api/accounting/income/{id}` | — | Get single income entry |
| 96 | **POST** | `/api/accounting/income` | `{ date, category, amount, currency, exchangeRate, paymentMethod, customer, reference, notes }` | Record income (auto journal entry) |
| 97 | **PUT** | `/api/accounting/income/{id}` | `{ ...fields }` | Update income |
| 98 | **DELETE** | `/api/accounting/income/{id}` | — | Delete income |
| 99 | **GET** | `/api/accounting/income/categories` | — | List income categories |

**Auto Accounting Entry:**
```
Debit:  Bank / Cash         = amount
Credit: Income Category     = amount
```

---

### ✅ Expenses (6 endpoints) — LIVE

| # | Method | Endpoint | Request Body | Purpose |
|---|--------|----------|-------------|---------|
| 100 | **GET** | `/api/accounting/expenses` | `?category&dateFrom&dateTo` | List expense entries |
| 101 | **GET** | `/api/accounting/expenses/{id}` | — | Get single expense |
| 102 | **POST** | `/api/accounting/expenses` | `{ date, category, amount, currency, exchangeRate, paymentMethod, vendor, reference, notes }` | Record expense (auto journal entry) |
| 103 | **PUT** | `/api/accounting/expenses/{id}` | `{ ...fields }` | Update expense |
| 104 | **DELETE** | `/api/accounting/expenses/{id}` | — | Delete expense |
| 105 | **GET** | `/api/accounting/expenses/categories` | — | List expense categories |

**Auto Accounting Entry:**
```
Debit:  Expense Category    = amount
Credit: Cash / Bank         = amount
```

---

### ✅ Income & Expense Summary (1 endpoint) — LIVE

| # | Method | Endpoint | Request Body | Purpose |
|---|--------|----------|-------------|---------|
| 106 | **GET** | `/api/accounting/income-expenses/summary` | `?period=daily|monthly|yearly` | Aggregated income/expense dashboard data |

**Response:**
```json
{
  "dailyIncome": 28000, "monthlyIncome": 520000, "yearlyIncome": 2850000,
  "dailyExpense": 15000, "monthlyExpense": 268200, "yearlyExpense": 1280000,
  "incomeByCategory": [...], "expenseByCategory": [...], "incomeTrend": [...]
}
```

---

### ✅ Banking (11 endpoints) — LIVE

| # | Method | Endpoint | Request Body | Purpose |
|---|--------|----------|-------------|---------|
| 107 | **GET** | `/api/accounting/bank-accounts` | — | List bank accounts |
| 108 | **GET** | `/api/accounting/bank-accounts/{id}` | — | Get bank account with balance |
| 109 | **POST** | `/api/accounting/bank-accounts` | `{ bankName, accountNumber, accountType, currency, openingBalance }` | Add bank account |
| 110 | **PUT** | `/api/accounting/bank-accounts/{id}` | `{ ...fields }` | Update bank account |
| 111 | **DELETE** | `/api/accounting/bank-accounts/{id}` | — | Delete bank account |
| 112 | **GET** | `/api/accounting/bank-accounts/{id}/transactions` | `?type&dateFrom&dateTo` | List bank transactions |
| 113 | **POST** | `/api/accounting/bank-accounts/{id}/transactions` | `{ date, type, amount, description, reference, transferTo? }` | Record deposit/withdrawal/transfer |
| 114 | **PUT** | `/api/accounting/bank-accounts/{id}/transactions/{txnId}` | `{ ...fields }` | Update transaction |
| 115 | **DELETE** | `/api/accounting/bank-accounts/{id}/transactions/{txnId}` | — | Delete transaction |
| 116 | **POST** | `/api/accounting/bank-accounts/{id}/reconcile` | `{ statementBalance, date, matchedTxns[] }` | Bank reconciliation |
| 117 | **GET** | `/api/accounting/bank-accounts/{id}/reconciliation` | `?dateFrom&dateTo` | Get reconciliation status & history |

---

### ✅ Journal Entries (7 endpoints) — LIVE

| # | Method | Endpoint | Request Body | Purpose |
|---|--------|----------|-------------|---------|
| 118 | **GET** | `/api/accounting/journal-entries` | `?status&dateFrom&dateTo` | List journal entries |
| 119 | **GET** | `/api/accounting/journal-entries/{id}` | — | Get entry with lines |
| 120 | **POST** | `/api/accounting/journal-entries` | `{ date, reference, description, lines[{ account, debit, credit, description }] }` | Create manual entry |
| 121 | **PUT** | `/api/accounting/journal-entries/{id}` | `{ ...fields }` | Update draft entry |
| 122 | **DELETE** | `/api/accounting/journal-entries/{id}` | — | Delete draft entry |
| 123 | **PATCH** | `/api/accounting/journal-entries/{id}/post` | — | Post (lock) entry |
| 124 | **POST** | `/api/accounting/journal-entries/{id}/reverse` | — | Create reversing entry |

**Validation Rule:** `SUM(debit) == SUM(credit)` — reject if unbalanced.

---

### ✅ Taxes (5 endpoints) — LIVE

| # | Method | Endpoint | Request Body | Purpose |
|---|--------|----------|-------------|---------|
| 125 | **GET** | `/api/accounting/taxes` | — | List tax rates |
| 126 | **GET** | `/api/accounting/taxes/{id}` | — | Get single tax |
| 127 | **POST** | `/api/accounting/taxes` | `{ name, rate, type: "CGST"|"SGST"|"IGST" }` | Create tax rate |
| 128 | **PUT** | `/api/accounting/taxes/{id}` | `{ name, rate, type }` | Update tax rate |
| 129 | **DELETE** | `/api/accounting/taxes/{id}` | — | Delete tax rate |

---

### ✅ Currencies (6 endpoints) — LIVE

| # | Method | Endpoint | Request Body | Purpose |
|---|--------|----------|-------------|---------|
| 130 | **GET** | `/api/accounting/currencies` | — | List currencies |
| 131 | **GET** | `/api/accounting/currencies/{id}` | — | Get currency |
| 132 | **POST** | `/api/accounting/currencies` | `{ code, name, symbol, exchangeRate, isBase }` | Add currency |
| 133 | **PUT** | `/api/accounting/currencies/{id}` | `{ ...fields }` | Update currency / exchange rate |
| 134 | **DELETE** | `/api/accounting/currencies/{id}` | — | Delete currency (fail if base) |
| 135 | **POST** | `/api/accounting/currencies/refresh-rates` | — | Refresh all exchange rates |

---

### ✅ Dashboard & Reports (5 endpoints) — LIVE

| # | Method | Endpoint | Request Body | Purpose |
|---|--------|----------|-------------|---------|
| 136 | **GET** | `/api/accounting/dashboard` | — | Accounting dashboard KPIs + recent transactions |
| 137 | **GET** | `/api/accounting/dashboard/charts` | `?period` | Dashboard chart data |
| 138 | **GET** | `/api/accounting/dashboard/recent-transactions` | `?limit` | Recent transactions list |
| 139 | **GET** | `/api/accounting/reports/{type}` | `?dateFrom&dateTo&account&customer&vendor&currency` | Generate report |
| 140 | **GET** | `/api/accounting/reports/{type}/export` | `?format=pdf|csv|excel&dateFrom&dateTo` | Export report |

**Report types:** `balance-sheet`, `profit-loss`, `trial-balance`, `general-ledger`, `cash-flow`, `income-report`, `expense-report`, `gst-summary`

---

### ✅ Accounting Settings (2 endpoints) — LIVE

| # | Method | Endpoint | Request Body | Purpose |
|---|--------|----------|-------------|---------|
| 141 | **GET** | `/api/accounting/settings` | — | Get accounting settings |
| 142 | **PUT** | `/api/accounting/settings` | `{ company: {...}, accounting: {...}, numbering: {...} }` | Update settings |

**Settings payload:**
```json
{
  "company": { "companyName", "address", "phone", "email", "website", "gstNumber", "pan", "financialYearStart", "baseCurrency" },
  "accounting": { "fiscalYear", "accountingMethod", "decimalPrecision", "defaultReceivable", "defaultPayable", "defaultSales", "defaultPurchase" },
  "numbering": { "invoicePrefix", "invoiceNext", "billPrefix", "billNext", "journalPrefix", "journalNext" }
}
```

---

### ✅ Recurring Transactions (7 endpoints) — LIVE

| # | Method | Endpoint | Request Body | Purpose |
|---|--------|----------|-------------|---------|
| 143 | **GET** | `/api/accounting/recurring` | — | List recurring transactions |
| 144 | **GET** | `/api/accounting/recurring/{id}` | — | Get recurring detail |
| 145 | **POST** | `/api/accounting/recurring` | `{ type, category, amount, frequency, startDate, endDate, description }` | Create recurring |
| 146 | **PUT** | `/api/accounting/recurring/{id}` | `{ ...fields }` | Update recurring |
| 147 | **DELETE** | `/api/accounting/recurring/{id}` | — | Delete recurring |
| 148 | **PATCH** | `/api/accounting/recurring/{id}/pause` | — | Pause recurring |
| 149 | **PATCH** | `/api/accounting/recurring/{id}/resume` | — | Resume recurring |

---

### ✅ Financial Year (7 endpoints) — LIVE

| # | Method | Endpoint | Request Body | Purpose |
|---|--------|----------|-------------|---------|
| 150 | **GET** | `/api/accounting/financial-years` | — | List all financial years |
| 151 | **GET** | `/api/accounting/financial-years/{id}` | — | Get single financial year |
| 152 | **POST** | `/api/accounting/financial-years` | `{ name, startDate, endDate }` | Open new financial year |
| 153 | **PUT** | `/api/accounting/financial-years/{id}` | `{ name, startDate, endDate }` | Update financial year |
| 154 | **POST** | `/api/accounting/financial-years/{id}/close` | — | Close financial year (lock transactions, carry forward balances) |
| 155 | **PATCH** | `/api/accounting/financial-years/{id}/set-default` | — | Set as active/default financial year |
| 156 | **GET** | `/api/accounting/financial-years/{id}/balances` | — | Get opening/closing balances for year |

**Financial Year Object:**
```json
{
  "id": 3,
  "name": "FY 2025–2026",
  "startDate": "2025-04-01",
  "endDate": "2026-03-31",
  "status": "open",
  "isDefault": true,
  "createdBy": "Admin",
  "createdDate": "2025-03-18"
}
```

**Validation Rules:**
- Financial years cannot overlap
- Only admins can close financial years
- Closed years cannot be reopened
- Closing a year carries forward balances to next open year
- One financial year must always be marked as default/active

---

### ✅ Audit Trail (1 endpoint) — LIVE

| # | Method | Endpoint | Request Body | Purpose |
|---|--------|----------|-------------|---------|
| 157 | **GET** | `/api/accounting/audit-logs` | `?action&table&userId&dateFrom&dateTo` | Get audit trail |

---

### ✅ Bulk Operations (4 endpoints) — LIVE

| # | Method | Endpoint | Request Body | Purpose |
|---|--------|----------|-------------|---------|
| 158 | **POST** | `/api/accounting/accounts/bulk-delete` | `{ ids: [1,2,3] }` | Bulk delete accounts |
| 159 | **POST** | `/api/accounting/invoices/bulk-status` | `{ ids: [...], status }` | Bulk update invoice status |
| 160 | **POST** | `/api/accounting/accounts/import` | `FormData (CSV/Excel)` | Import accounts from file |
| 161 | **GET** | `/api/accounting/accounts/export` | `?format=csv|excel` | Export accounts to file |

---

### ✅ Invoice Template Settings (2 endpoints) — LIVE

| # | Method | Endpoint | Request Body | Purpose |
|---|--------|----------|-------------|---------|
| 162 | **GET** | `/api/accounting/invoice-settings` | — | Get invoice template settings |
| 163 | **PUT** | `/api/accounting/invoice-settings` | `{ invoicePrefix, footerText, termsAndConditions, defaultPaymentTerms, invoiceLogo, invoiceTheme }` | Update invoice template settings |

**Invoice Template Settings Object:**
```json
{
  "invoicePrefix": "INV-",
  "footerText": "Thank you for your business!",
  "termsAndConditions": "Payment is due within 30 days of the invoice date.",
  "defaultPaymentTerms": "Net 30",
  "invoiceLogo": "",
  "invoiceTheme": "default"
}
```

---

### ✅ Payment Methods (5 endpoints) — LIVE

| # | Method | Endpoint | Request Body | Purpose |
|---|--------|----------|-------------|---------|
| 164 | **GET** | `/api/accounting/payment-methods` | — | List all payment methods |
| 165 | **GET** | `/api/accounting/payment-methods/{id}` | — | Get single payment method |
| 166 | **POST** | `/api/accounting/payment-methods` | `{ name, type, accountHolderName, bankName, accountNumber, ifscCode, upiId, qrCode, notes, enabled }` | Create payment method |
| 167 | **PUT** | `/api/accounting/payment-methods/{id}` | `{ ...fields }` | Update payment method |
| 168 | **DELETE** | `/api/accounting/payment-methods/{id}` | — | Delete payment method |

**Payment Method Object:**
```json
{
  "id": 1,
  "name": "Bank Transfer",
  "type": "bank",
  "accountHolderName": "My Business Pvt Ltd",
  "bankName": "HDFC Bank",
  "accountNumber": "1234567890",
  "ifscCode": "HDFC000123",
  "upiId": "",
  "qrCode": "",
  "notes": "Preferred payment method",
  "enabled": true
}
```

**Types:** `bank`, `upi`, `cash`, `card`, `gateway`

**Used in:** Invoice print view — payment methods are displayed on printed invoices so customers know how to pay.

---

### ✅ Branches / Multi-Location (6 endpoints) — LIVE

| # | Method | Endpoint | Request Body | Purpose |
|---|--------|----------|-------------|---------|
| 169 | **GET** | `/api/accounting/branches` | `?search` | List all branches |
| 170 | **GET** | `/api/accounting/branches/{id}` | — | Get single branch |
| 171 | **POST** | `/api/accounting/branches` | `{ name, code, address, phone, email, gstNumber, isDefault }` | Create branch |
| 172 | **PUT** | `/api/accounting/branches/{id}` | `{ ...fields }` | Update branch |
| 173 | **DELETE** | `/api/accounting/branches/{id}` | — | Delete branch (fail if default) |
| 174 | **GET** | `/api/accounting/branches/{id}/reports/{type}` | `?dateFrom&dateTo` | Get branch-specific report |

**Branch Object:**
```json
{
  "id": 1,
  "name": "Mumbai Head Office",
  "code": "MUM-HQ",
  "address": "123 MG Road, Mumbai 400001",
  "phone": "+91 22 1234 5678",
  "email": "mumbai@mybusiness.com",
  "gstNumber": "27AABCU9603R1ZM",
  "isDefault": true,
  "status": "active"
}
```

**Validation Rules:**
- Branch code must be unique
- Cannot delete default branch
- At least one branch must exist

---

### ✅ Quotations / Simple Quotation Management (10 endpoints) — SRS 26

| # | Method | Endpoint | Request Body | Purpose |
|---|--------|----------|-------------|---------|
| 175 | **GET** | `/api/accounting/quotations` | `?status&search&dateFrom&dateTo` | List all quotations (filterable) |
| 176 | **GET** | `/api/accounting/quotations/{id}` | — | Get single quotation with items |
| 177 | **POST** | `/api/accounting/quotations` | `{ customerName, customerId, date, validUntil, currency, exchangeRate, notes, termsAndConditions, branch, items, subtotal, tax, discount, total }` | Create quotation |
| 178 | **PUT** | `/api/accounting/quotations/{id}` | `{ ...fields }` | Update quotation |
| 179 | **DELETE** | `/api/accounting/quotations/{id}` | — | Soft-delete quotation |
| 180 | **PATCH** | `/api/accounting/quotations/{id}/status` | `{ status }` | Change status (draft/sent/accepted/rejected/expired) |
| 181 | **POST** | `/api/accounting/quotations/{id}/duplicate` | — | Duplicate quotation (returns new quotation) |
| 182 | **POST** | `/api/accounting/quotations/{id}/convert-to-invoice` | — | Convert to invoice (copies items, marks as converted, returns invoice) |
| 183 | **POST** | `/api/accounting/quotations/{id}/send` | — | Send quotation via email |
| 184 | **GET** | `/api/accounting/quotations/{id}/pdf` | — | Download quotation as PDF (blob) |

**Quotation Object:**
```json
{
  "id": 1,
  "quotation_number": "QTN-00001",
  "customer_id": 1,
  "customer_name": "ABC Corp",
  "date": "2026-03-09T00:00:00.000000Z",
  "valid_until": "2026-04-08T00:00:00.000000Z",
  "currency": "INR",
  "exchange_rate": "1.000000",
  "subtotal": "10000.00",
  "discount": "500.00",
  "tax": "1710.00",
  "total": "11210.00",
  "status": "draft",
  "notes": "Special pricing for Q1",
  "terms_and_conditions": "Valid for 30 days",
  "branch": "Mumbai",
  "converted_invoice_id": null,
  "user_id": 1,
  "created_at": "2026-03-09T10:00:00.000000Z",
  "updated_at": "2026-03-09T10:00:00.000000Z",
  "deleted_at": null,
  "customer": {
    "id": 1,
    "name": "ABC Corp"
  },
  "items": [
    {
      "id": 1,
      "quotation_id": 1,
      "description": "Web Development",
      "quantity": "1.00",
      "unit_price": "10000.00",
      "discount": "500.00",
      "tax_rate": "18.0000",
      "tax_amount": "1710.00",
      "total": "11210.00",
      "created_at": "2026-03-09T10:00:00.000000Z",
      "updated_at": "2026-03-09T10:00:00.000000Z"
    }
  ]
}
```

**Quotation Item Fields:**
- `description` (string, required)
- `quantity` (decimal, required, min: 0.01)
- `unit_price` (decimal, required, min: 0)
- `discount` (decimal, default: 0)
- `tax_rate` (decimal, default: 0)
- `tax_amount` (decimal, calculated)
- `total` (decimal, calculated)

**Status Values:** `draft`, `sent`, `accepted`, `rejected`, `expired`, `converted`

**Validation Rules:**
- `quotation_number` auto-generated (QTN-00001 format), must be unique
- `date` required
- `valid_until` optional (default: date + 30 days)
- At least 1 item required
- Each item requires `description` and `unit_price`
- Cannot convert already-converted quotation
- Status transitions: draft→sent→accepted/rejected, accepted→converted
- Soft-deletes only (preserves audit trail)
- Auto-expire: backend cron marks quotations with `valid_until < today` as expired
