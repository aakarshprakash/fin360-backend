# 📡 Complete API List — Lead CRM Frontend → Backend

> Auto-generated from all `api.get/post/put/patch/delete` calls in the frontend codebase.
> Last updated: **March 12, 2026**

---

## 🔐 Authentication & Session (6 endpoints)

| # | Method | Endpoint | Used In | Purpose |
|---|--------|----------|---------|---------|
| 1 | **POST** | `/api/login` | `Login.jsx` | Login with email + password → returns `{ token, refresh_token, user }` |
| 2 | **POST** | `/api/logout` | `auth.js` | Revoke current token (Sanctum) |
| 3 | **POST** | `/api/logout-everywhere` | `auth.js` | Revoke ALL user tokens |
| 4 | **POST** | `/api/refresh-token` | `auth.js` | Refresh expired token → returns `{ token, refresh_token }` |
| 5 | **GET** | `/api/auth-check` | `auth.js` | Validate current token is still valid |
| 6 | **GET** | `/api/me` | `auth.js` | Get current authenticated user profile with permissions |

---

## 🔑 Password Reset (2 endpoints)

| # | Method | Endpoint | Used In | Purpose |
|---|--------|----------|---------|---------|
| 7 | **POST** | `/api/password/forgot` | `ForgotPassword.jsx`, `UserList.jsx` | Send password reset email |
| 8 | **POST** | `/api/password/reset` | `ResetPassword.jsx` | Reset password with token |

---

## 📊 Dashboard (4 endpoints)

| # | Method | Endpoint | Used In | Purpose |
|---|--------|----------|---------|---------|
| 9 | **GET** | `/api/dashboard/stats` | `Dashboard.jsx` | KPI stats (total leads, conversions, etc.) |
| 10 | **GET** | `/api/dashboard/recent-leads` | `Dashboard.jsx` | Recent leads list for dashboard |
| 11 | **GET** | `/api/dashboard/pending-followups` | `Dashboard.jsx` | Pending follow-up tasks |
| 12 | **GET** | `/api/dashboard/calendar-events` | `Dashboard.jsx` | Calendar events for dashboard |

---

## 📋 Leads (7 endpoints)

| # | Method | Endpoint | Used In | Purpose |
|---|--------|----------|---------|---------|
| 13 | **GET** | `/api/leads` | `LeadList.jsx`, `TopNav.jsx` | List/search leads (supports `?search=`, `?status=`, pagination) |
| 14 | **POST** | `/api/leads` | `CreateLead.jsx` | Create a new lead |
| 15 | **GET** | `/api/leads/:id` | `LeadDetail.jsx` | Get single lead details |
| 16 | **PUT** | `/api/leads/:id` | `LeadDetail.jsx` | Update lead (full edit or priority change) |
| 17 | **PATCH** | `/api/leads/:id/status` | `LeadDetail.jsx` | Quick-update lead status |
| 18 | **POST** | `/api/leads/:id/assign` | `LeadList.jsx`, `LeadDetail.jsx` | Assign/reassign lead to a user |
| 19 | **POST** | `/api/leads/bulk-assign` | `LeadList.jsx` | Bulk assign multiple leads |

---

## 📅 Follow-ups (3 endpoints)

| # | Method | Endpoint | Used In | Purpose |
|---|--------|----------|---------|---------|
| 20 | **GET** | `/api/leads/:id/follow-ups` | `LeadDetail.jsx` | List follow-ups for a lead |
| 21 | **POST** | `/api/leads/:id/follow-ups` | `LeadDetail.jsx` | Create a follow-up for a lead |
| 22 | **PATCH** | `/api/follow-ups/:id/complete` | `LeadDetail.jsx` | Mark a follow-up as completed |

---

## 📞 Call Logs (2 endpoints)

| # | Method | Endpoint | Used In | Purpose |
|---|--------|----------|---------|---------|
| 23 | **GET** | `/api/leads/:id/call-logs` | `LeadDetail.jsx` | List call logs for a lead |
| 24 | **POST** | `/api/leads/:id/call-logs` | `LeadDetail.jsx` | Log a call for a lead |

---

## 📝 Notes (2 endpoints)

| # | Method | Endpoint | Used In | Purpose |
|---|--------|----------|---------|---------|
| 25 | **GET** | `/api/leads/:id/notes` | `LeadDetail.jsx` | List notes for a lead |
| 26 | **POST** | `/api/leads/:id/notes` | `LeadDetail.jsx` | Add a note to a lead |

---

## 🔀 Transfer History (1 endpoint)

| # | Method | Endpoint | Used In | Purpose |
|---|--------|----------|---------|---------|
| 27 | **GET** | `/api/leads/:id/transfer-history` | `LeadDetail.jsx` | Get assignment/transfer history |

---

## 👥 Customers (1 endpoint)

| # | Method | Endpoint | Used In | Purpose |
|---|--------|----------|---------|---------|
| 28 | **GET** | `/api/customers` | `CustomerList.jsx` | List/search customers |

---

## 🧑‍💼 Users (6 endpoints)

| # | Method | Endpoint | Used In | Purpose |
|---|--------|----------|---------|---------|
| 29 | **GET** | `/api/users` | `UserList.jsx`, `UserModal.jsx` | List all users |
| 30 | **POST** | `/api/users` | `UserModal.jsx` | Create a new user |
| 31 | **GET** | `/api/users/:id` | `UserDetail.jsx` | Get single user details |
| 32 | **PUT** | `/api/users/:id` | `UserModal.jsx`, `UserList.jsx` | Update user (profile, status toggle) |
| 33 | **DELETE** | `/api/users/:id` | `UserList.jsx` | Delete a user |
| 34 | **PUT** | `/api/users/:id/permissions` | `RolePermissions.jsx` | Update user-level permission overrides |

---

## 👤 Profile & Password (2 endpoints)

| # | Method | Endpoint | Used In | Purpose |
|---|--------|----------|---------|---------|
| 35 | **PUT** | `/api/users/profile` | `Settings.jsx` | Update own profile (name, email, phone) |
| 36 | **PUT** | `/api/users/password` | `Settings.jsx` | Change own password |

---

## 🏷️ Lead Sources (5 endpoints)

| # | Method | Endpoint | Used In | Purpose |
|---|--------|----------|---------|---------|
| 37 | **GET** | `/api/lead-sources` | `Settings.jsx`, `CreateLead.jsx` | List all lead sources |
| 38 | **POST** | `/api/lead-sources` | `Settings.jsx` | Create a lead source |
| 39 | **PUT** | `/api/lead-sources/:id` | `Settings.jsx` | Update a lead source |
| 40 | **PATCH** | `/api/lead-sources/:id/toggle` | `Settings.jsx` | Toggle active/inactive |
| 41 | **DELETE** | `/api/lead-sources/:id` | `Settings.jsx` | Delete a lead source |

---

## 📊 Lead Statuses (5 endpoints)

| # | Method | Endpoint | Used In | Purpose |
|---|--------|----------|---------|---------|
| 42 | **GET** | `/api/lead-statuses` | `Settings.jsx`, `CreateLead.jsx` | List all lead statuses |
| 43 | **POST** | `/api/lead-statuses` | `Settings.jsx` | Create a lead status |
| 44 | **PUT** | `/api/lead-statuses/:id` | `Settings.jsx` | Update a lead status |
| 45 | **PATCH** | `/api/lead-statuses/:id/toggle` | `Settings.jsx` | Toggle active/inactive |
| 46 | **DELETE** | `/api/lead-statuses/:id` | `Settings.jsx` | Delete a lead status |

---

## 🎭 Roles & Permissions (5 endpoints)

| # | Method | Endpoint | Used In | Purpose |
|---|--------|----------|---------|---------|
| 47 | **GET** | `/api/roles` | `Settings.jsx`, `UserModal.jsx`, `RolePermissions.jsx` | List all roles |
| 48 | **POST** | `/api/roles` | `Settings.jsx` | Create a role (with permissions array) |
| 49 | **PUT** | `/api/roles/:id` | `Settings.jsx` | Update a role (with permissions array) |
| 50 | **DELETE** | `/api/roles/:id` | `Settings.jsx` | Delete a role |
| 51 | **GET** | `/api/permissions` | `RolePermissions.jsx` | List all available permissions |

---

## 💼 Job Titles (5 endpoints)

| # | Method | Endpoint | Used In | Purpose |
|---|--------|----------|---------|---------|
| 52 | **GET** | `/api/job-titles` | `Settings.jsx`, `UserModal.jsx` | List job titles (supports `?department_id=`) |
| 53 | **POST** | `/api/job-titles` | `Settings.jsx` | Create a job title |
| 54 | **PUT** | `/api/job-titles/:id` | `Settings.jsx` | Update a job title |
| 55 | **PATCH** | `/api/job-titles/:id/toggle` | `Settings.jsx` | Toggle active/inactive |
| 56 | **DELETE** | `/api/job-titles/:id` | `Settings.jsx` | Delete a job title |

---

## 🏢 Departments (5 endpoints)

| # | Method | Endpoint | Used In | Purpose |
|---|--------|----------|---------|---------|
| 57 | **GET** | `/api/departments` | `Settings.jsx`, `UserModal.jsx` | List all departments |
| 58 | **POST** | `/api/departments` | `Settings.jsx` | Create a department |
| 59 | **PUT** | `/api/departments/:id` | `Settings.jsx` | Update a department |
| 60 | **PATCH** | `/api/departments/:id/toggle` | `Settings.jsx` | Toggle active/inactive |
| 61 | **DELETE** | `/api/departments/:id` | `Settings.jsx` | Delete a department |

---

## 🔄 Round Robin (2 endpoints)

| # | Method | Endpoint | Used In | Purpose |
|---|--------|----------|---------|---------|
| 62 | **GET** | `/api/settings/round-robin` | `Settings.jsx` | Get round-robin configuration |
| 63 | **PUT** | `/api/settings/round-robin` | `Settings.jsx` | Update round-robin config (enabled, participant_ids) |

---

## 💰 Accounting — Chart of Accounts (7 endpoints)

| # | Method | Endpoint | Used In | Purpose |
|---|--------|----------|---------|---------|
| 64 | **GET** | `/api/accounting/accounts` | `accountingApi.js` | List accounts |
| 65 | **GET** | `/api/accounting/accounts/:id` | `accountingApi.js` | Get single account |
| 66 | **POST** | `/api/accounting/accounts` | `accountingApi.js` | Create account |
| 67 | **PUT** | `/api/accounting/accounts/:id` | `accountingApi.js` | Update account |
| 68 | **DELETE** | `/api/accounting/accounts/:id` | `accountingApi.js` | Delete account |
| 69 | **GET** | `/api/accounting/accounts/:id/ledger` | `accountingApi.js` | Account ledger |
| 70 | **POST** | `/api/accounting/accounts/bulk-delete` | `accountingApi.js` | Bulk delete accounts |

---

## 💰 Accounting — Customers (6 endpoints)

| # | Method | Endpoint | Used In | Purpose |
|---|--------|----------|---------|---------|
| 71 | **GET** | `/api/accounting/customers` | `accountingApi.js` | List accounting customers |
| 72 | **GET** | `/api/accounting/customers/:id` | `accountingApi.js` | Get customer detail |
| 73 | **POST** | `/api/accounting/customers` | `accountingApi.js` | Create customer |
| 74 | **PUT** | `/api/accounting/customers/:id` | `accountingApi.js` | Update customer |
| 75 | **DELETE** | `/api/accounting/customers/:id` | `accountingApi.js` | Delete customer |
| 76 | **GET** | `/api/accounting/customers/:id/ledger` | `accountingApi.js` | Customer ledger |
| 77 | **GET** | `/api/accounting/customers/:id/invoices` | `accountingApi.js` | Customer invoices |
| 78 | **GET** | `/api/accounting/customers/:id/statement` | `accountingApi.js` | Customer statement |

---

## 💰 Accounting — Vendors (8 endpoints)

| # | Method | Endpoint | Used In | Purpose |
|---|--------|----------|---------|---------|
| 79 | **GET** | `/api/accounting/vendors` | `accountingApi.js` | List vendors |
| 80 | **GET** | `/api/accounting/vendors/:id` | `accountingApi.js` | Get vendor detail |
| 81 | **POST** | `/api/accounting/vendors` | `accountingApi.js` | Create vendor |
| 82 | **PUT** | `/api/accounting/vendors/:id` | `accountingApi.js` | Update vendor |
| 83 | **DELETE** | `/api/accounting/vendors/:id` | `accountingApi.js` | Delete vendor |
| 84 | **GET** | `/api/accounting/vendors/:id/ledger` | `accountingApi.js` | Vendor ledger |
| 85 | **GET** | `/api/accounting/vendors/:id/bills` | `accountingApi.js` | Vendor bills |
| 86 | **GET** | `/api/accounting/vendors/:id/statement` | `accountingApi.js` | Vendor statement |

---

## 💰 Accounting — Invoices (12 endpoints)

| # | Method | Endpoint | Used In | Purpose |
|---|--------|----------|---------|---------|
| 87 | **GET** | `/api/accounting/invoices` | `accountingApi.js` | List invoices |
| 88 | **GET** | `/api/accounting/invoices/:id` | `accountingApi.js` | Get invoice detail |
| 89 | **POST** | `/api/accounting/invoices` | `accountingApi.js` | Create invoice |
| 90 | **PUT** | `/api/accounting/invoices/:id` | `accountingApi.js` | Update invoice |
| 91 | **DELETE** | `/api/accounting/invoices/:id` | `accountingApi.js` | Delete invoice |
| 92 | **PATCH** | `/api/accounting/invoices/:id/status` | `accountingApi.js` | Update invoice status |
| 93 | **POST** | `/api/accounting/invoices/:id/payments` | `accountingApi.js` | Record payment |
| 94 | **GET** | `/api/accounting/invoices/:id/payments` | `accountingApi.js` | Get invoice payments |
| 95 | **POST** | `/api/accounting/invoices/:id/send` | `accountingApi.js` | Send invoice email |
| 96 | **GET** | `/api/accounting/invoices/:id/pdf` | `accountingApi.js` | Download invoice PDF |
| 97 | **POST** | `/api/accounting/invoices/bulk-status` | `accountingApi.js` | Bulk update invoice status |
| 98 | **POST** | `/api/accounting/credit-notes` | `accountingApi.js` | Create credit note |
| 99 | **GET** | `/api/accounting/credit-notes` | `accountingApi.js` | List credit notes |

---

## 💰 Accounting — Quotations (9 endpoints)

| # | Method | Endpoint | Used In | Purpose |
|---|--------|----------|---------|---------|
| 100 | **GET** | `/api/accounting/quotations` | `accountingApi.js` | List quotations |
| 101 | **GET** | `/api/accounting/quotations/:id` | `accountingApi.js` | Get quotation detail |
| 102 | **POST** | `/api/accounting/quotations` | `accountingApi.js` | Create quotation |
| 103 | **PUT** | `/api/accounting/quotations/:id` | `accountingApi.js` | Update quotation |
| 104 | **DELETE** | `/api/accounting/quotations/:id` | `accountingApi.js` | Delete quotation |
| 105 | **PATCH** | `/api/accounting/quotations/:id/status` | `accountingApi.js` | Update quotation status |
| 106 | **POST** | `/api/accounting/quotations/:id/duplicate` | `accountingApi.js` | Duplicate quotation |
| 107 | **POST** | `/api/accounting/quotations/:id/convert-to-invoice` | `accountingApi.js` | Convert quotation → invoice |
| 108 | **POST** | `/api/accounting/quotations/:id/send` | `accountingApi.js` | Send quotation email |
| 109 | **GET** | `/api/accounting/quotations/:id/pdf` | `accountingApi.js` | Download quotation PDF |

---

## 💰 Accounting — Bills (10 endpoints)

| # | Method | Endpoint | Used In | Purpose |
|---|--------|----------|---------|---------|
| 110 | **GET** | `/api/accounting/bills` | `accountingApi.js` | List bills |
| 111 | **GET** | `/api/accounting/bills/:id` | `accountingApi.js` | Get bill detail |
| 112 | **POST** | `/api/accounting/bills` | `accountingApi.js` | Create bill |
| 113 | **PUT** | `/api/accounting/bills/:id` | `accountingApi.js` | Update bill |
| 114 | **DELETE** | `/api/accounting/bills/:id` | `accountingApi.js` | Delete bill |
| 115 | **PATCH** | `/api/accounting/bills/:id/status` | `accountingApi.js` | Update bill status |
| 116 | **POST** | `/api/accounting/bills/:id/payments` | `accountingApi.js` | Record bill payment |
| 117 | **GET** | `/api/accounting/bills/:id/payments` | `accountingApi.js` | Get bill payments |
| 118 | **POST** | `/api/accounting/debit-notes` | `accountingApi.js` | Create debit note |
| 119 | **GET** | `/api/accounting/debit-notes` | `accountingApi.js` | List debit notes |

---

## 💰 Accounting — Income (6 endpoints)

| # | Method | Endpoint | Used In | Purpose |
|---|--------|----------|---------|---------|
| 120 | **GET** | `/api/accounting/income` | `accountingApi.js` | List income |
| 121 | **GET** | `/api/accounting/income/:id` | `accountingApi.js` | Get income detail |
| 122 | **POST** | `/api/accounting/income` | `accountingApi.js` | Create income entry |
| 123 | **PUT** | `/api/accounting/income/:id` | `accountingApi.js` | Update income |
| 124 | **DELETE** | `/api/accounting/income/:id` | `accountingApi.js` | Delete income |
| 125 | **GET** | `/api/accounting/income/categories` | `accountingApi.js` | Get income categories |

---

## 💰 Accounting — Expenses (6 endpoints)

| # | Method | Endpoint | Used In | Purpose |
|---|--------|----------|---------|---------|
| 126 | **GET** | `/api/accounting/expenses` | `accountingApi.js` | List expenses |
| 127 | **GET** | `/api/accounting/expenses/:id` | `accountingApi.js` | Get expense detail |
| 128 | **POST** | `/api/accounting/expenses` | `accountingApi.js` | Create expense |
| 129 | **PUT** | `/api/accounting/expenses/:id` | `accountingApi.js` | Update expense |
| 130 | **DELETE** | `/api/accounting/expenses/:id` | `accountingApi.js` | Delete expense |
| 131 | **GET** | `/api/accounting/expenses/categories` | `accountingApi.js` | Get expense categories |

---

## 💰 Accounting — Income & Expense Dashboard (1 endpoint)

| # | Method | Endpoint | Used In | Purpose |
|---|--------|----------|---------|---------|
| 132 | **GET** | `/api/accounting/income-expenses/summary` | `accountingApi.js` | Summary data for income vs expense dashboard |

---

## 💰 Accounting — Banking (10 endpoints)

| # | Method | Endpoint | Used In | Purpose |
|---|--------|----------|---------|---------|
| 133 | **GET** | `/api/accounting/bank-accounts` | `accountingApi.js` | List bank accounts |
| 134 | **GET** | `/api/accounting/bank-accounts/:id` | `accountingApi.js` | Get bank account detail |
| 135 | **POST** | `/api/accounting/bank-accounts` | `accountingApi.js` | Create bank account |
| 136 | **PUT** | `/api/accounting/bank-accounts/:id` | `accountingApi.js` | Update bank account |
| 137 | **DELETE** | `/api/accounting/bank-accounts/:id` | `accountingApi.js` | Delete bank account |
| 138 | **GET** | `/api/accounting/bank-accounts/:id/transactions` | `accountingApi.js` | List bank transactions |
| 139 | **POST** | `/api/accounting/bank-accounts/:id/transactions` | `accountingApi.js` | Create bank transaction |
| 140 | **PUT** | `/api/accounting/bank-accounts/:bankId/transactions/:txnId` | `accountingApi.js` | Update bank transaction |
| 141 | **DELETE** | `/api/accounting/bank-accounts/:bankId/transactions/:txnId` | `accountingApi.js` | Delete bank transaction |
| 142 | **POST** | `/api/accounting/bank-accounts/:id/reconcile` | `accountingApi.js` | Reconcile bank account |
| 143 | **GET** | `/api/accounting/bank-accounts/:id/reconciliation` | `accountingApi.js` | Get reconciliation data |

---

## 💰 Accounting — Journal Entries (7 endpoints)

| # | Method | Endpoint | Used In | Purpose |
|---|--------|----------|---------|---------|
| 144 | **GET** | `/api/accounting/journal-entries` | `accountingApi.js` | List journal entries |
| 145 | **GET** | `/api/accounting/journal-entries/:id` | `accountingApi.js` | Get journal entry detail |
| 146 | **POST** | `/api/accounting/journal-entries` | `accountingApi.js` | Create journal entry |
| 147 | **PUT** | `/api/accounting/journal-entries/:id` | `accountingApi.js` | Update journal entry |
| 148 | **DELETE** | `/api/accounting/journal-entries/:id` | `accountingApi.js` | Delete journal entry |
| 149 | **PATCH** | `/api/accounting/journal-entries/:id/post` | `accountingApi.js` | Post (finalize) journal entry |
| 150 | **POST** | `/api/accounting/journal-entries/:id/reverse` | `accountingApi.js` | Reverse journal entry |

---

## 💰 Accounting — Taxes (5 endpoints)

| # | Method | Endpoint | Used In | Purpose |
|---|--------|----------|---------|---------|
| 151 | **GET** | `/api/accounting/taxes` | `accountingApi.js` | List taxes |
| 152 | **GET** | `/api/accounting/taxes/:id` | `accountingApi.js` | Get tax detail |
| 153 | **POST** | `/api/accounting/taxes` | `accountingApi.js` | Create tax |
| 154 | **PUT** | `/api/accounting/taxes/:id` | `accountingApi.js` | Update tax |
| 155 | **DELETE** | `/api/accounting/taxes/:id` | `accountingApi.js` | Delete tax |

---

## 💰 Accounting — Currencies (6 endpoints)

| # | Method | Endpoint | Used In | Purpose |
|---|--------|----------|---------|---------|
| 156 | **GET** | `/api/accounting/currencies` | `accountingApi.js` | List currencies |
| 157 | **GET** | `/api/accounting/currencies/:id` | `accountingApi.js` | Get currency detail |
| 158 | **POST** | `/api/accounting/currencies` | `accountingApi.js` | Create currency |
| 159 | **PUT** | `/api/accounting/currencies/:id` | `accountingApi.js` | Update currency |
| 160 | **DELETE** | `/api/accounting/currencies/:id` | `accountingApi.js` | Delete currency |
| 161 | **POST** | `/api/accounting/currencies/refresh-rates` | `accountingApi.js` | Refresh exchange rates |

---

## 💰 Accounting — Dashboard (3 endpoints)

| # | Method | Endpoint | Used In | Purpose |
|---|--------|----------|---------|---------|
| 162 | **GET** | `/api/accounting/dashboard` | `accountingApi.js` | Main accounting dashboard data |
| 163 | **GET** | `/api/accounting/dashboard/charts` | `accountingApi.js` | Chart data |
| 164 | **GET** | `/api/accounting/dashboard/recent-transactions` | `accountingApi.js` | Recent transactions |

---

## 💰 Accounting — Reports (2 endpoints)

| # | Method | Endpoint | Used In | Purpose |
|---|--------|----------|---------|---------|
| 165 | **GET** | `/api/accounting/reports/:type` | `accountingApi.js` | Get report (profit-loss, balance-sheet, etc.) |
| 166 | **GET** | `/api/accounting/reports/:type/export` | `accountingApi.js` | Export report as PDF/CSV |

---

## 💰 Accounting — Settings (4 endpoints)

| # | Method | Endpoint | Used In | Purpose |
|---|--------|----------|---------|---------|
| 167 | **GET** | `/api/accounting/settings` | `accountingApi.js` | Get accounting settings |
| 168 | **PUT** | `/api/accounting/settings` | `accountingApi.js` | Update accounting settings |
| 169 | **GET** | `/api/accounting/invoice-settings` | `accountingApi.js` | Get invoice template settings |
| 170 | **PUT** | `/api/accounting/invoice-settings` | `accountingApi.js` | Update invoice template settings |

---

## 💰 Accounting — Recurring Transactions (6 endpoints)

| # | Method | Endpoint | Used In | Purpose |
|---|--------|----------|---------|---------|
| 171 | **GET** | `/api/accounting/recurring` | `accountingApi.js` | List recurring transactions |
| 172 | **GET** | `/api/accounting/recurring/:id` | `accountingApi.js` | Get recurring transaction detail |
| 173 | **POST** | `/api/accounting/recurring` | `accountingApi.js` | Create recurring transaction |
| 174 | **PUT** | `/api/accounting/recurring/:id` | `accountingApi.js` | Update recurring transaction |
| 175 | **DELETE** | `/api/accounting/recurring/:id` | `accountingApi.js` | Delete recurring transaction |
| 176 | **PATCH** | `/api/accounting/recurring/:id/pause` | `accountingApi.js` | Pause recurring transaction |
| 177 | **PATCH** | `/api/accounting/recurring/:id/resume` | `accountingApi.js` | Resume recurring transaction |

---

## 💰 Accounting — Financial Years (7 endpoints)

| # | Method | Endpoint | Used In | Purpose |
|---|--------|----------|---------|---------|
| 178 | **GET** | `/api/accounting/financial-years` | `accountingApi.js` | List financial years |
| 179 | **GET** | `/api/accounting/financial-years/:id` | `accountingApi.js` | Get financial year |
| 180 | **POST** | `/api/accounting/financial-years` | `accountingApi.js` | Open new financial year |
| 181 | **PUT** | `/api/accounting/financial-years/:id` | `accountingApi.js` | Update financial year |
| 182 | **POST** | `/api/accounting/financial-years/:id/close` | `accountingApi.js` | Close financial year |
| 183 | **PATCH** | `/api/accounting/financial-years/:id/set-default` | `accountingApi.js` | Set default financial year |
| 184 | **GET** | `/api/accounting/financial-years/:id/balances` | `accountingApi.js` | Get year-end balances |

---

## 💰 Accounting — Payment Methods (5 endpoints)

| # | Method | Endpoint | Used In | Purpose |
|---|--------|----------|---------|---------|
| 185 | **GET** | `/api/accounting/payment-methods` | `accountingApi.js` | List payment methods |
| 186 | **GET** | `/api/accounting/payment-methods/:id` | `accountingApi.js` | Get payment method |
| 187 | **POST** | `/api/accounting/payment-methods` | `accountingApi.js` | Create payment method |
| 188 | **PUT** | `/api/accounting/payment-methods/:id` | `accountingApi.js` | Update payment method |
| 189 | **DELETE** | `/api/accounting/payment-methods/:id` | `accountingApi.js` | Delete payment method |

---

## 💰 Accounting — Branches (6 endpoints)

| # | Method | Endpoint | Used In | Purpose |
|---|--------|----------|---------|---------|
| 190 | **GET** | `/api/accounting/branches` | `accountingApi.js` | List branches |
| 191 | **GET** | `/api/accounting/branches/:id` | `accountingApi.js` | Get branch |
| 192 | **POST** | `/api/accounting/branches` | `accountingApi.js` | Create branch |
| 193 | **PUT** | `/api/accounting/branches/:id` | `accountingApi.js` | Update branch |
| 194 | **DELETE** | `/api/accounting/branches/:id` | `accountingApi.js` | Delete branch |
| 195 | **GET** | `/api/accounting/branches/:id/reports/:type` | `accountingApi.js` | Branch-specific report |

---

## 💰 Accounting — Audit Trail (1 endpoint)

| # | Method | Endpoint | Used In | Purpose |
|---|--------|----------|---------|---------|
| 196 | **GET** | `/api/accounting/audit-logs` | `accountingApi.js` | List audit logs |

---

## 💰 Accounting — Bulk & Import/Export (2 endpoints)

| # | Method | Endpoint | Used In | Purpose |
|---|--------|----------|---------|---------|
| 197 | **POST** | `/api/accounting/accounts/import` | `accountingApi.js` | Import accounts |
| 198 | **GET** | `/api/accounting/accounts/export` | `accountingApi.js` | Export accounts |

---

## 📊 Summary

| Category | Endpoints |
|----------|-----------|
| Authentication & Session | 6 |
| Password Reset | 2 |
| Dashboard | 4 |
| Leads | 7 |
| Follow-ups | 3 |
| Call Logs | 2 |
| Notes | 2 |
| Transfer History | 1 |
| Customers (CRM) | 1 |
| Users | 6 |
| Profile & Password | 2 |
| Lead Sources | 5 |
| Lead Statuses | 5 |
| Roles & Permissions | 5 |
| Job Titles | 5 |
| Departments | 5 |
| Round Robin | 2 |
| **Accounting (all)** | **135** |
| **TOTAL** | **~198** |

---

## ⚠️ Notes for Backend Team

1. **Lead Assignment** — Frontend sends `POST /api/leads/:id/assign` (changed from PATCH due to 405 error). Ensure the route is registered as POST or accept both POST and PATCH.

2. **PATCH endpoints** — Several endpoints use PATCH (toggle, status updates, etc.). Ensure your Laravel routes use `Route::patch()` or `Route::match(['patch', 'post'], ...)` for these.

3. **All endpoints must be prefixed with `/api/`** — The frontend's axios baseURL is `https://leadcrm.vpsappserverone.in/api`.

4. **Authentication** — All endpoints except login, password reset, and public routes require `Authorization: Bearer {token}` header.

5. **Request/Response format** — Frontend auto-converts camelCase ↔ snake_case. Backend should use snake_case.

6. **Pagination** — List endpoints should support `?page=`, `?per_page=`, `?search=`, and return `{ data: [], meta: { current_page, last_page, total } }`.

7. **🔒 `view_own_leads` Permission — CRITICAL** — When a user has only `view_own_leads` (without `view_leads` or `view_all_leads`):
   - The frontend sends `?scope=own` on `GET /api/leads` requests.
   - **The backend MUST filter**: `Lead::where('assigned_to', auth()->id())` when this parameter is sent OR when the user only has the `view_own_leads` permission.
   - **Do NOT rely solely on the frontend `scope` param** — always check the user's permissions server-side.
   - The `GET /api/leads/:id` endpoint should also return **403 Forbidden** if the user has only `view_own_leads` and the lead is not assigned to them.
   - The `GET /api/dashboard/recent-leads` and `GET /api/dashboard/stats` endpoints should also be scoped to the user's own leads when they only have `view_own_leads`.

   **Example Laravel implementation:**
   ```php
   // In LeadController@index
   public function index(Request $request)
   {
       $query = Lead::query();

       // Scope to own leads if user lacks view_all_leads / view_leads
       if (!$request->user()->hasAnyPermission(['view_leads', 'view_all_leads'])) {
           $query->where('assigned_to', $request->user()->id);
       }

       // ... search, filter, paginate ...
       return LeadResource::collection($query->paginate());
   }

   // In LeadController@show
   public function show(Request $request, Lead $lead)
   {
       if (!$request->user()->hasAnyPermission(['view_leads', 'view_all_leads'])
           && $lead->assigned_to !== $request->user()->id) {
           abort(403, 'You can only view leads assigned to you.');
       }
       return new LeadResource($lead);
   }
   ```

