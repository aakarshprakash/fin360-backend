# Roles & Permissions — API Specification
> Share this with the Laravel backend team.

---

## Overview

The CRM uses a **dynamic role-based access control (RBAC)** system with:
- Custom roles (create/edit/delete via API)
- Per-user permission overrides on top of role defaults
- A **Super Admin** role that bypasses all permission checks server-side
- Data scope permissions: `view_own_*` vs `view_all_*`

---

## Database Schema (suggested)

```sql
-- roles table
CREATE TABLE roles (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(50) UNIQUE NOT NULL,       -- "Super Admin", "Manager", "Sales Agent"
  description   VARCHAR(255) NULL,
  is_system     BOOLEAN DEFAULT FALSE,             -- system roles cannot be deleted
  is_super_admin BOOLEAN DEFAULT FALSE,            -- bypasses ALL permission checks
  created_at    TIMESTAMP,
  updated_at    TIMESTAMP
);

-- permissions table (master list)
CREATE TABLE permissions (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  key         VARCHAR(100) UNIQUE NOT NULL,        -- "view_all_leads", "view_own_leads"
  label       VARCHAR(100) NOT NULL,
  description VARCHAR(255) NULL,
  category    VARCHAR(100) NOT NULL,               -- "Leads", "Customers", etc.
  created_at  TIMESTAMP,
  updated_at  TIMESTAMP
);

-- pivot: which permissions belong to a role
CREATE TABLE role_permissions (
  role_id       BIGINT UNSIGNED,
  permission_id BIGINT UNSIGNED,
  PRIMARY KEY (role_id, permission_id),
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

-- users table additions
ALTER TABLE users
  ADD COLUMN role_id       BIGINT UNSIGNED NULL REFERENCES roles(id) ON DELETE SET NULL,
  ADD COLUMN is_super_admin BOOLEAN DEFAULT FALSE;

-- pivot: per-user permission overrides (overrides role defaults)
CREATE TABLE user_permissions (
  user_id       BIGINT UNSIGNED,
  permission_id BIGINT UNSIGNED,
  PRIMARY KEY (user_id, permission_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);
```

---

## Permission Keys (Master List)

The frontend uses these exact keys — seed them into the `permissions` table.

### Leads
| Key | Label | Description |
|-----|-------|-------------|
| `view_leads` | View Leads | Can view lead information and details |
| `view_own_leads` | View Own Leads | Can only view leads assigned to themselves |
| `view_all_leads` | View All Leads | Can view leads assigned to any user |
| `create_leads` | Create Leads | Can create new leads |
| `edit_leads` | Edit Leads | Can modify any lead |
| `edit_own_leads` | Edit Own Leads | Can only edit leads assigned to themselves |
| `delete_leads` | Delete Leads | Can permanently remove leads |
| `assign_leads` | Assign Leads | Can assign leads to team members |
| `export_leads` | Export Leads | Can export leads as CSV/Excel |

### Customers
| Key | Label | Description |
|-----|-------|-------------|
| `view_customers` | View Customers | Can view all customer information |
| `view_own_customers` | View Own Customers | Can only view customers they created |
| `manage_customers` | Manage Customers | Can edit and manage all customers |

### Follow-ups & Communication
| Key | Label | Description |
|-----|-------|-------------|
| `manage_followups` | Manage Follow-ups | Can create, edit and complete all follow-ups |
| `manage_own_followups` | Manage Own Follow-ups | Can only manage their own follow-ups |
| `manage_call_logs` | Manage Call Logs | Can log calls, emails and communication |

### Reports & Analytics
| Key | Label | Description |
|-----|-------|-------------|
| `view_reports` | View Reports | Can access analytics and reports |
| `view_own_reports` | View Own Reports | Can only view their own performance reports |
| `view_dashboard` | View Dashboard | Can view the dashboard with KPIs |
| `export_reports` | Export Reports | Can export data and reports |

### User Management
| Key | Label | Description |
|-----|-------|-------------|
| `view_users` | View Users | Can view user list and profiles |
| `manage_users` | Manage Users | Can create, edit, and manage user accounts |
| `manage_roles` | Manage Roles | Can create, edit, and delete roles |

### Settings & Configuration
| Key | Label | Description |
|-----|-------|-------------|
| `manage_settings` | Manage Settings | Can modify system settings |
| `manage_lead_sources` | Manage Lead Sources | Can add, edit and delete lead sources |

### Accounting
| Key | Label | Description |
|-----|-------|-------------|
| `view_accounting` | View Accounting | Can access the accounting module |
| `manage_accounting` | Manage Accounting | Can create invoices, bills, transactions |
| `accounting_settings` | Accounting Settings | Can manage accounting configuration |

---

## Default Role Presets (seed data)

```php
// DatabaseSeeder.php or RolesSeeder.php
$roles = [
    [
        'name'           => 'Super Admin',
        'description'    => 'Unrestricted access to everything',
        'is_system'      => true,
        'is_super_admin' => true,
        'permissions'    => '*',  // all permissions
    ],
    [
        'name'        => 'Admin',
        'description' => 'Full CRM access',
        'is_system'   => true,
        'permissions' => '*',
    ],
    [
        'name'        => 'Manager',
        'description' => 'Team oversight and reporting',
        'is_system'   => false,
        'permissions' => [
            'view_leads','view_all_leads','create_leads','edit_leads','assign_leads','export_leads',
            'view_customers','manage_customers',
            'manage_followups','manage_call_logs',
            'view_reports','view_dashboard','export_reports',
            'view_users',
            'view_accounting','manage_accounting',
        ],
    ],
    [
        'name'        => 'Sales Agent',
        'description' => 'Basic CRM operations on own data',
        'is_system'   => false,
        'permissions' => [
            'view_leads','view_own_leads','create_leads','edit_own_leads',
            'view_own_customers',
            'manage_own_followups','manage_call_logs',
            'view_dashboard','view_own_reports',
            'view_accounting',
        ],
    ],
];
```

---

## API Endpoints

### Base URL: `https://leadcrm.vpsappserverone.in/api`
### Auth: `Authorization: Bearer {token}` on all requests

---

### 1. GET /permissions
Returns the master list of all permissions, grouped by category.

**Response 200:**
```json
{
  "permissions": [
    {
      "category": "Leads",
      "permissions": [
        { "key": "view_leads",     "label": "View Leads",     "description": "Can view lead information" },
        { "key": "view_own_leads", "label": "View Own Leads", "description": "Can only view own leads" },
        { "key": "view_all_leads", "label": "View All Leads", "description": "Can view all leads" }
      ]
    }
  ]
}
```

---

### 2. GET /roles
Returns all roles with their permissions.

**Response 200:**
```json
{
  "roles": [
    {
      "id": 1,
      "name": "Super Admin",
      "description": "Unrestricted access",
      "is_system": true,
      "is_super_admin": true,
      "users_count": 1,
      "permissions": ["view_leads", "create_leads", "...all keys"]
    },
    {
      "id": 2,
      "name": "Admin",
      "description": "Full CRM access",
      "is_system": true,
      "is_super_admin": false,
      "users_count": 2,
      "permissions": ["view_leads", "create_leads", "..."]
    },
    {
      "id": 3,
      "name": "Sales Agent",
      "description": "Basic CRM operations",
      "is_system": false,
      "is_super_admin": false,
      "users_count": 5,
      "permissions": ["view_leads", "view_own_leads", "create_leads"]
    }
  ]
}
```

---

### 3. POST /roles
Create a new role.

**Request body:**
```json
{
  "name": "Team Lead",
  "description": "Manages a small team of agents",
  "permissions": ["view_leads", "view_all_leads", "create_leads", "edit_leads", "assign_leads", "view_reports"]
}
```

**Response 201:**
```json
{
  "role": {
    "id": 5,
    "name": "Team Lead",
    "description": "Manages a small team of agents",
    "is_system": false,
    "is_super_admin": false,
    "users_count": 0,
    "permissions": ["view_leads", "view_all_leads", "create_leads", "edit_leads", "assign_leads", "view_reports"]
  }
}
```

**Validation errors 422:**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "name": ["The name has already been taken."],
    "permissions": ["Select at least one permission."]
  }
}
```

---

### 4. PUT /roles/{id}
Update an existing role.

**Request body:** same as POST /roles

**Response 200:**
```json
{
  "role": { "id": 5, "name": "Team Lead", "...": "..." }
}
```

**Notes:**
- System roles (`is_system: true`) — allow editing permissions but **not** the name.
- Return `403` if trying to rename a system role.

---

### 5. DELETE /roles/{id}
Delete a custom role.

**Response 200:**
```json
{ "message": "Role deleted successfully." }
```

**Error 422 (users still assigned):**
```json
{ "message": "Cannot delete role. 3 users are assigned to this role." }
```

**Error 403 (system role):**
```json
{ "message": "System roles cannot be deleted." }
```

---

### 6. PUT /users/{id}/permissions  ← **KEY ENDPOINT**
Update a user's role assignment AND their individual permission overrides.

**Request body:**
```json
{
  "role": "Sales Agent",
  "role_id": 3,
  "permissions": [
    "view_leads",
    "view_own_leads",
    "create_leads",
    "edit_own_leads",
    "manage_own_followups",
    "manage_call_logs",
    "view_dashboard",
    "view_own_reports"
  ]
}
```

**Response 200:**
```json
{
  "message": "Permissions updated successfully.",
  "user": {
    "id": 5,
    "name": "John Doe",
    "role": "Sales Agent",
    "role_id": 3,
    "permissions": ["view_leads", "view_own_leads", "..."],
    "all_permissions": ["view_leads", "view_own_leads", "..."]
  }
}
```

**Notes:**
- `permissions` in the response = the user's **effective** permission set (merged role + overrides)
- `all_permissions` = same as permissions (effective list)
- Store overrides in `user_permissions` pivot table
- The `role_id` field updates `users.role_id`

---

### 7. GET /users/{id}  (updated fields)
The user detail response must include:

```json
{
  "id": 1,
  "name": "Admin",
  "email": "admin@crm.com",
  "role": "Admin",
  "role_id": 1,
  "role_data": {
    "id": 1,
    "name": "Admin",
    "is_super_admin": true
  },
  "is_super_admin": false,
  "permissions": ["view_leads", "create_leads", "..."],
  "all_permissions": ["view_leads", "create_leads", "..."]
}
```

---

## Middleware / Authorization Logic (Laravel)

```php
// app/Http/Middleware/CheckPermission.php

public function handle($request, Closure $next, $permission)
{
    $user = $request->user();

    // Super Admin bypasses everything
    if ($user->is_super_admin || $user->role?->is_super_admin) {
        return $next($request);
    }

    // Legacy Admin role bypass
    if ($user->role?->name === 'Admin') {
        return $next($request);
    }

    // Check user's effective permissions
    $permissions = $user->permissions->pluck('key')
        ->merge($user->role?->permissions->pluck('key') ?? [])
        ->unique();

    if (!$permissions->contains($permission)) {
        return response()->json(['message' => 'Forbidden.'], 403);
    }

    return $next($request);
}
```

```php
// Data scope: filter queries based on view_own vs view_all
// In LeadController.php

public function index(Request $request)
{
    $user = $request->user();

    $query = Lead::query();

    // Super admin / Admin / has view_all_leads → see everything
    if (!$user->hasPermission('view_all_leads') && !$user->is_super_admin) {
        // Only own leads
        $query->where('assigned_to', $user->id);
    }

    return LeadResource::collection($query->paginate(20));
}
```

```php
// User model helper
public function hasPermission(string $key): bool
{
    if ($this->is_super_admin || $this->role?->is_super_admin) return true;
    if ($this->role?->name === 'Admin') return true;

    return $this->permissions->pluck('key')
        ->merge($this->role?->permissions->pluck('key') ?? [])
        ->contains($key);
}
```

---

## Summary of Data Scope Permissions

| Permission | What it allows |
|------------|---------------|
| `view_own_leads` | User sees only leads where `assigned_to = auth()->id()` |
| `view_all_leads` | User sees all leads regardless of assignment |
| `view_own_customers` | User sees only customers they created |
| `manage_own_followups` | User can only CRUD their own follow-ups |
| `view_own_reports` | Dashboard/reports show only their own stats |

**Priority rule on backend:** if user has `view_all_leads`, they can see everything even if `view_own_leads` is also set.

---

## Frontend ↔ Backend Field Mapping

The frontend axios interceptor **auto-converts** snake_case ↔ camelCase. So:

| Backend sends | Frontend receives |
|---------------|------------------|
| `role_id` | `roleId` |
| `is_super_admin` | `isSuperAdmin` |
| `users_count` | `usersCount` |
| `all_permissions` | `allPermissions` |
| `is_system` | `isSystem` |

When the frontend sends data, it is also auto-converted back to snake_case for the backend.

