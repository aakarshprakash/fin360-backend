# Lead Management CRM - API Documentation

## Base URL
```
http://localhost:8000/api
```

## Authentication
This API uses **Laravel Sanctum** for authentication. After login/register, you'll receive a bearer token that must be included in subsequent requests.

### Headers for Protected Routes
```
Authorization: Bearer {your-token-here}
Accept: application/json
Content-Type: application/json
```

---

## Authentication Endpoints

### 1. Register
Create a new user account.

**Endpoint:** `POST /api/register`

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "phone": "+1-555-123-4567",
  "role": "Sales Agent",
  "department": "Sales"
}
```

**Response (201):**
```json
{
  "message": "Registration successful",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "+1-555-123-4567",
    "role": "Sales Agent",
    "department": "Sales",
    "status": "Active",
    "permissions": ["view_leads", "create_leads", "edit_leads"]
  },
  "token": "1|abc123xyz..."
}
```

---

### 2. Login
Authenticate and receive a token.

**Endpoint:** `POST /api/login`

**Request Body:**
```json
{
  "email": "admin@crm.com",
  "password": "password"
}
```

**Response (200):**
```json
{
  "message": "Login successful",
  "user": {
    "id": 1,
    "name": "Admin User",
    "email": "admin@crm.com",
    "phone": "+1-555-100-0001",
    "role": "Admin",
    "department": "Management",
    "status": "Active",
    "permissions": [
      "view_leads",
      "create_leads",
      "edit_leads",
      "delete_leads",
      "manage_users",
      "view_reports"
    ],
    "avatar": null
  },
  "token": "1|abc123xyz..."
}
```

**Error Response (422):**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The provided credentials are incorrect."]
  }
}
```

---

### 3. Get Current User
Get the authenticated user's information.

**Endpoint:** `GET /api/me`

**Headers:**
```
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "user": {
    "id": 1,
    "name": "Admin User",
    "email": "admin@crm.com",
    "phone": "+1-555-100-0001",
    "role": "Admin",
    "department": "Management",
    "status": "Active",
    "permissions": ["view_leads", "create_leads", "edit_leads", "delete_leads", "manage_users", "view_reports"],
    "avatar": null,
    "last_active": "2026-03-04T10:30:00.000000Z",
    "created_at": "2026-03-04T09:15:00.000000Z"
  }
}
```

---

### 4. Logout
Revoke the current access token.

**Endpoint:** `POST /api/logout`

**Headers:**
```
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "message": "Logout successful"
}
```

---

## Test Credentials

Use these credentials to test the API:

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@crm.com | password |
| Manager | sarah@crm.com | password |
| Sales Agent | john@crm.com | password |
| Sales Agent | emily@crm.com | password |

---

## Testing with cURL

### Login Example
```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "admin@crm.com",
    "password": "password"
  }'
```

### Get Current User Example
```bash
curl -X GET http://localhost:8000/api/me \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Accept: application/json"
```

### Logout Example
```bash
curl -X POST http://localhost:8000/api/logout \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Accept: application/json"
```

---

## Testing with Postman

1. **Create a new collection** called "Lead CRM API"
2. **Set up environment variables:**
   - `base_url`: `http://localhost:8000/api`
   - `token`: (will be set after login)

3. **Login Request:**
   - Method: POST
   - URL: `{{base_url}}/login`
   - Body (JSON):
     ```json
     {
       "email": "admin@crm.com",
       "password": "password"
     }
     ```
   - In Tests tab, add:
     ```javascript
     pm.environment.set("token", pm.response.json().token);
     ```

4. **Protected Routes:**
   - Add to Headers:
     - Key: `Authorization`
     - Value: `Bearer {{token}}`

---

## Error Responses

### 401 Unauthorized
```json
{
  "message": "Unauthenticated."
}
```

### 403 Forbidden
```json
{
  "message": "Your account is not active. Please contact administrator."
}
```

### 422 Validation Error
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field is required."],
    "password": ["The password field is required."]
  }
}
```

### 500 Server Error
```json
{
  "message": "Server Error"
}
```

---

## Next Endpoints to Implement

The following endpoints will be added in the next phase:

### Leads API
- `GET /api/leads` - List all leads (with filters, pagination)
- `POST /api/leads` - Create new lead
- `GET /api/leads/{id}` - Get single lead details
- `PUT /api/leads/{id}` - Update lead
- `DELETE /api/leads/{id}` - Delete lead (soft delete)
- `POST /api/leads/{id}/convert` - Convert lead to customer

### Follow-ups API
- `GET /api/followups` - List all follow-ups
- `POST /api/followups` - Create new follow-up
- `PUT /api/followups/{id}` - Update follow-up
- `DELETE /api/followups/{id}` - Delete follow-up
- `POST /api/followups/{id}/complete` - Mark as completed

### Call Logs API
- `GET /api/call-logs` - List all call logs
- `POST /api/call-logs` - Create new call log
- `GET /api/call-logs/{id}` - Get call log details

### Notes API
- `GET /api/notes` - List all notes
- `POST /api/notes` - Create new note
- `PUT /api/notes/{id}` - Update note
- `DELETE /api/notes/{id}` - Delete note

### Customers API
- `GET /api/customers` - List all customers
- `GET /api/customers/{id}` - Get customer details
- `PUT /api/customers/{id}` - Update customer

### Dashboard API
- `GET /api/dashboard/stats` - Get dashboard statistics
- `GET /api/dashboard/recent-leads` - Get recent leads
- `GET /api/dashboard/pending-followups` - Get pending follow-ups

### Users API (Admin only)
- `GET /api/users` - List all users
- `POST /api/users` - Create new user
- `PUT /api/users/{id}` - Update user
- `DELETE /api/users/{id}` - Delete user

### Lead Sources API
- `GET /api/lead-sources` - List all lead sources
- `POST /api/lead-sources` - Create new source
- `PUT /api/lead-sources/{id}` - Update source
- `DELETE /api/lead-sources/{id}` - Delete source

---

## Notes

- All dates are in ISO 8601 format: `YYYY-MM-DDTHH:mm:ss.ssssssZ`
- All timestamps are in UTC
- Soft deletes are enabled on users, leads, invoices, bills, vendors, accounting customers, bank accounts
- Pagination default: 15 items per page
- Maximum page size: 100 items

---

## 📊 Accounting Module API

> **Base Path:** `/api/accounting/`
> **Auth:** All endpoints require `Authorization: Bearer {token}`
> **Migration:** Run `php artisan migrate` to create all 23 accounting tables

### Quick Reference — All Accounting Endpoints

| Group | Endpoints | Base Path |
|-------|-----------|-----------|
| Chart of Accounts | 6 + 3 bulk | `/api/accounting/accounts` |
| Customers | 8 | `/api/accounting/customers` |
| Vendors | 8 | `/api/accounting/vendors` |
| Invoices | 11 | `/api/accounting/invoices` |
| Credit Notes | 2 | `/api/accounting/credit-notes` |
| Bills | 8 | `/api/accounting/bills` |
| Debit Notes | 2 | `/api/accounting/debit-notes` |
| Income | 6 | `/api/accounting/income` |
| Expenses | 6 | `/api/accounting/expenses` |
| Income/Expense Summary | 1 | `/api/accounting/income-expenses/summary` |
| Banking | 10 | `/api/accounting/bank-accounts` |
| Journal Entries | 7 | `/api/accounting/journal-entries` |
| Taxes | 5 | `/api/accounting/taxes` |
| Currencies | 6 | `/api/accounting/currencies` |
| Dashboard & Reports | 5 | `/api/accounting/dashboard` + `/reports/{type}` |
| Settings | 2 | `/api/accounting/settings` |
| Recurring | 7 | `/api/accounting/recurring` |
| Financial Years | 3 | `/api/accounting/financial-years` |
| Audit Logs | 1 | `/api/accounting/audit-logs` |

### Auto Journal Entries

**On Invoice Create:**
```
Debit:  Accounts Receivable (1100)  = total
Credit: Sales Revenue (4100)        = subtotal - discount
Credit: GST Payable (2200)          = tax
```

**On Bill Create:**
```
Debit:  Purchases (5100)            = subtotal
Debit:  Input GST (1300)            = tax
Credit: Accounts Payable (2100)     = total
```

### Report Types
`GET /api/accounting/reports/{type}` supports:
- `trial-balance` — Trial Balance
- `profit-loss` — Profit & Loss / Income Statement
- `balance-sheet` — Balance Sheet
- `general-ledger` — General Ledger
- `cash-flow` — Cash Flow Statement
- `income-report` — Income Report
- `expense-report` — Expense Report
- `gst-summary` — GST Summary

### Scheduled Commands
- `php artisan accounting:process-recurring` — Processes due recurring transactions (runs daily via scheduler)

---

## Support

For issues or questions, refer to:
- API_LIST_FOR_BACKEND.md (complete endpoint reference with request/response shapes)
- DEPLOYMENT_GUIDE.md
- SETUP_COMPLETE.md

