# Accounting Management System
## Software Requirements Specification (SRS)

Version: 1.0
Target Users: Businesses in India
Primary Currency: INR
Architecture: Modular Accounting System with Multi-Currency Support

---

## ✅ Implementation Status (Updated: March 9, 2026)

| # | Module | Status | Frontend Files |
|---|--------|--------|---------------|
| 5 | Dashboard | ✅ Done | `AccountingDashboard.jsx` |
| 6 | Chart of Accounts | ✅ Done | `ChartOfAccounts.jsx`, `AccountModal.jsx` |
| 7 | Customers Module | ✅ Done | `AccountingCustomers.jsx`, `AccountingCustomerDetail.jsx`, `CustomerModal.jsx` |
| 8 | Vendors / Suppliers | ✅ Done | `VendorList.jsx`, `VendorDetail.jsx`, `VendorModal.jsx` |
| 9 | Sales Module | ✅ Done | `InvoiceList.jsx`, `CreateInvoice.jsx`, `InvoiceDetail.jsx`, `InvoicePrintView.jsx` |
| 10 | Purchase Module | ✅ Done | `BillList.jsx`, `CreateBill.jsx` |
| 11.1 | Income Manager | ✅ Done | `IncomeList.jsx`, `IncomeModal.jsx` |
| 11.2 | Expense Manager | ✅ Done | `ExpenseList.jsx`, `ExpenseModal.jsx` |
| 11.3 | Recurring Transactions | ⏳ Backend only | (fields defined in API, UI pending) |
| 11.4 | Income & Expense Dashboard | ✅ Done | `IncomeExpenseDashboard.jsx` |
| 12 | Banking Module | ✅ Done | `BankingList.jsx`, `BankAccountDetail.jsx`, `BankAccountModal.jsx`, `BankTransactionModal.jsx` |
| 13 | Journal Entries | ✅ Done | `JournalEntryList.jsx`, `CreateJournalEntry.jsx` |
| 14 | Multi Currency Support | ✅ Done | `CurrencyManagement.jsx`, `CurrencyModal.jsx` |
| 15 | Tax Management (GST) | ✅ Done | `TaxManagement.jsx`, `TaxModal.jsx` |
| 16 | Financial Reports | ✅ Done | `ReportsHub.jsx`, `ReportViewer.jsx` |
| 17 | Settings Module | ✅ Done | `AccountingSettings.jsx` |
| 18 | Security & Permissions | ⏳ Backend | Role-based routes wrapped in `ProtectedRoute` |
| 19 | Audit Trail | ⏳ Backend | Backend implementation needed |
| 20 | Data Validation | ✅ Partial | Journal entry balance validation done; backend rules pending |
| 23.6 | Invoice Template Settings | ✅ Done | `InvoiceSettings.jsx` (template + payment methods) |
| 23.13 | Multi-Branch Support | ✅ Done | `BranchList.jsx` |
| 25 | Financial Year Management | ✅ Done | `FinancialYearManagement.jsx` |
| 26 | Quotation Management | ✅ Backend Done | API built (10 endpoints), frontend pending |

**Design System (Updated March 9, 2026):**
- All emojis replaced with **inline SVG icons** via shared `AccountingIcons.jsx`
- All hardcoded hex colors replaced with **CSS variables** from `:root` in `index.css`
- `Accounting.css` rewritten to use minimal flat design with CSS variables
- Standard system font stack: `-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif`

**Shared assets:**
- `src/components/accounting/AccountingIcons.jsx` — Shared SVG icon constants (Lucide/Feather style)
- `src/components/accounting/InvoicePrintView.jsx` — Professional printable invoice template (company header, line items, totals, amount in words, payment methods, terms, signatures, watermark)
- `src/utils/accountingApi.js` — All API endpoint functions (incl. Invoice Settings, Payment Methods, Branches, Financial Years)
- `src/styles/Accounting.css` — Shared accounting module styles (CSS variables, minimal theme, print media query for A4 output)
- Navigation integrated in `TopNav.jsx` (desktop dropdown + mobile panel)
- All routes registered in `App.jsx`

**Invoice Print & Save PDF Features:**
- Print Preview modal with full invoice rendering
- Print button (browser print dialog)
- Save PDF button (browser "Save as PDF" print destination)
- Professional layout: company branding, GSTIN, PAN, line items with zebra striping
- Amount in words (Indian numbering: Lakh/Crore)
- Payment methods section (Bank Transfer, UPI, Cash) from Invoice Settings
- Terms & Conditions, Notes, Signature blocks
- Status watermark (PAID / CANCELLED) on printed invoices
- Responsive A4 print with forced color accuracy

---

# 1. Project Overview

## 1.1 Purpose

The Accounting Management System is designed to provide a **professional business accounting platform** that supports:

- Double-entry accounting
- Multi-currency transactions
- GST taxation for India
- Financial reporting
- Income & expense tracking
- Banking and reconciliation
- Easy-to-use interface for non-accountants

The system should provide functionality similar to:

- Tally
- Zoho Books
- QuickBooks

---

# 2. Core Accounting Principle

The system must implement **double-entry accounting**.

For every transaction:

Debit Total = Credit Total

Example:

Customer Payment ₹10,000

| Account | Debit | Credit |
|--------|-------|--------|
| Bank | 10,000 | |
| Accounts Receivable | | 10,000 |

Transactions must never violate this rule.

---

# 3. User Roles

## Admin
Full system access.

## Accountant
Manage accounting entries and financial reports.

## Manager
View reports and dashboards.

## Staff
Limited operational access.

---

# 4. System Modules

The system should contain the following modules.

Dashboard
Accounting
Sales
Purchases
Income & Expense Manager
Banking
Reports
Settings

---

# 5. Dashboard

Displays key financial information.

Features:

- Total revenue
- Total expenses
- Net profit
- Outstanding invoices
- Bank balances
- Financial charts
- Recent transactions

---

# 6. Chart of Accounts

The system must maintain a hierarchical chart of accounts.

Account Types:

Assets
Liabilities
Equity
Income
Expenses

Default accounts:

Cash
Bank
Accounts Receivable
Accounts Payable
Sales
Purchases
GST Payable

Requirements:

- Accounts can be grouped
- Accounts cannot be deleted if transactions exist
- Account codes supported

---

# 7. Customers Module

Stores customer information.

Fields:

Name
Email
Phone
GST Number
Address
Currency Preference
Credit Limit

Features:

Create customer
Edit customer
View customer transactions
Customer ledger

---

# 8. Vendors / Suppliers

Stores supplier details.

Fields:

Vendor Name
GST Number
Address
Phone
Email
Payment Terms

Features:

Add vendor
Edit vendor
Track vendor payments

---

# 9. Sales Module

Handles invoicing.

Invoice Fields:

Invoice Number
Customer
Currency
Exchange Rate
Items
Quantity
Price
Tax
Discount
Total

System must auto-create accounting entries.

Accounting Entry Example:

Debit: Accounts Receivable
Credit: Sales
Credit: GST Payable

---

# 10. Purchase Module

Handles purchase bills.

Fields:

Bill Number
Vendor
Items
Tax
Total Amount

Accounting Entry:

Debit: Expense / Inventory
Credit: Accounts Payable

---

# 11. Income & Expense Manager

This module provides a simplified way to track business income and expenses.

It is designed for **non-accountant users**.

The system automatically generates accounting entries in the background.

---

## 11.1 Income Manager

Tracks all incoming money.

Examples:

Cash sales
Service income
Commission income
Interest income

Fields:

Income Category
Amount
Currency
Exchange Rate
Payment Method
Customer (optional)
Reference
Notes
Attachment
Date

Accounting Entry Example:

Debit: Bank / Cash
Credit: Income Account

---

## 11.2 Expense Manager

Tracks business expenses.

Examples:

Office rent
Electricity bills
Travel expenses
Software subscriptions
Office supplies

Fields:

Expense Category
Amount
Currency
Exchange Rate
Payment Method
Vendor
Reference
Notes
Attachment
Date

Accounting Entry Example:

Debit: Expense Account
Credit: Cash / Bank

---

## 11.3 Recurring Transactions

The system must support recurring income and expenses.

Examples:

Monthly rent
Software subscriptions
Salaries

Fields:

Frequency
Start Date
End Date
Auto create transactions

---

## 11.4 Income & Expense Dashboard

Displays:

Total Income (Daily / Monthly / Yearly)

Total Expenses

Net Profit

Expense breakdown by category

Income trend charts

---

# 12. Banking Module

Manages bank accounts.

Fields:

Bank Name
Account Number
Currency
Opening Balance

Features:

Record deposits
Record withdrawals
Internal transfers
Bank reconciliation

---

# 13. Journal Entries

Manual accounting entries.

Fields:

Date
Reference
Description
Debit Account
Credit Account
Amount
Currency

Validation Rule:

Debit Total must equal Credit Total.

---

# 14. Multi Currency Support

The system must support multiple currencies.

Examples:

INR
USD
EUR
AED

The base currency for the system will be **INR**.

Transactions must store:

Original Currency
Exchange Rate
Converted Base Amount

Example:

Invoice Amount = 100 USD
Exchange Rate = 83

Base Amount = 8300 INR

---

# 15. Tax Management (India)

System must support GST taxation.

Tax Types:

CGST
SGST
IGST

Fields:

Tax Name
Tax Rate
Tax Type

Taxes should be automatically calculated on invoices and purchases.

---

# 16. Financial Reports

The system must generate the following reports.

Balance Sheet

Profit & Loss

Trial Balance

General Ledger

Cash Flow Statement

Income Report

Expense Report

GST Summary Report

Reports must support filters:

Date Range
Account
Customer
Vendor
Currency

---

# 17. Settings Module

## Company Settings

Fields:

Company Name
Address
GST Number
PAN
Financial Year Start
Base Currency

---

## Accounting Settings

Options:

Fiscal Year
Accounting Method (Cash / Accrual)
Decimal Precision
Default Accounts

---

## Numbering Settings

Auto numbering for:

Invoices
Bills
Journal Entries

Example format:

INV-0001
INV-0002

---

# 18. Security & Permissions

Role based access control must be implemented.

Admin → full access
Accountant → accounting access
Manager → report access
Staff → limited access

---

# 19. Audit Trail

The system must track all changes.

Fields:

User ID
Action Type
Affected Table
Old Value
New Value
Timestamp

Transactions should never be permanently deleted.

---

# 20. Data Validation Rules

The system must enforce:

Debit must equal Credit

Transactions cannot be deleted once posted

Closed financial periods cannot be edited

Invoice numbers must be unique

Currency conversion must be stored permanently

---

# 21. Performance Requirements

The system must support:

100,000+ transactions

Fast report generation

Secure API endpoints

Scalable architecture

---


---

---

# 23.6 Invoice Template Settings (Enhanced)

Users should be able to customize invoice appearance and payment information.

### Default Invoice Settings

Fields:

Invoice Prefix  
Invoice Footer Text  
Terms & Conditions  
Default Payment Terms  
Invoice Logo  
Invoice Theme  

---

## Multiple Payment Methods

The system must allow **multiple payment methods to be configured and displayed on invoices**.

This allows businesses to provide customers with different payment options.

### Example Payment Methods

1. Bank Transfer  
2. UPI Payment  
3. Cash  
4. Credit Card (future integration)  
5. Payment Gateway (future integration)

---

### Payment Method Fields

Payment Method Name  
Account Holder Name  
Bank Name (if applicable)  
Account Number (if applicable)  
IFSC Code (if applicable)  
UPI ID (if applicable)  
QR Code Image (optional)  
Notes / Instructions  

---

### Example Configuration

**Payment Method 1 – Bank Transfer**

Bank Name: HDFC Bank  
Account Name: ABC Pvt Ltd  
Account Number: 1234567890  
IFSC: HDFC000123  

**Payment Method 2 – UPI**

UPI ID: abc@upi  
QR Code: Uploaded image  

---

### Invoice Display

Invoices should show **all enabled payment methods**, for example:

Payment Options:

Bank Transfer  
Account Name: ABC Pvt Ltd  
Account Number: 1234567890  
IFSC: HDFC000123  

UPI Payment  
UPI ID: abc@upi  
QR Code  

---

# 23.13 Multi-Branch Support

The accounting system must support **multiple business branches / locations**.

This is critical for businesses operating in multiple cities or offices.

---

## Branch Fields

Branch Name  
Branch Code  
Branch Address  
City  
State  
Country  
Phone Number  
Email Address  
GST Number (optional)  
Default Currency  

---

## Branch Features

Each branch should be able to manage:

Invoices  
Expenses  
Customers  
Vendors  
Bank accounts  
Reports  

---

## Branch-Based Accounting

Transactions must include a **branch reference**.

Example:

| Transaction | Branch |
|-------------|--------|
| Invoice | Kochi |
| Expense | Bangalore |
| Purchase | Mumbai |

---

## Branch Filtering

Reports must support filtering by:

Branch  
Date Range  
Account  

Example:

Profit & Loss by Branch  
Sales by Branch  
Expenses by Branch  

---

## Branch Bank Accounts

Each branch should be able to maintain **its own bank accounts and payment details**.

Example:

Branch: Kochi  
Bank: HDFC Bank  

Branch: Bangalore  
Bank: ICICI Bank  

---

## Branch Invoice Customization

Invoices may include:

Branch Name  
Branch Address  
Branch GST Number  

This ensures correct tax compliance.

---

## Branch Permissions

Users can be assigned to specific branches.

Example:

Admin → All branches  
Accountant → Selected branches  
Staff → Single branch  

---
---

# 25. Master Financial Year Management

The system must include a **Master Financial Year configuration module** that controls the accounting period used for all financial transactions and reports.

This is especially important for businesses operating in India, where the standard financial year is:

Start: 1 April  
End: 31 March

Example:

FY 2025–2026  
Start Date: 01-04-2025  
End Date: 31-03-2026

---

## 25.1 Financial Year Fields

Financial Year Name  
Start Date  
End Date  
Status (Open / Closed)  
Created By  
Created Date

Example record:

Financial Year: FY 2025–2026  
Start: 01 Apr 2025  
End: 31 Mar 2026  
Status: Open

---

## 25.2 Financial Year Features

The system must allow administrators to:

Create financial year  
Edit financial year  
Close financial year  
Set default financial year  
View historical financial years

---

## 25.3 Default Financial Year

One financial year must always be marked as the **active financial year**.

All new transactions will automatically use the active financial year.

---

## 25.4 Financial Year Locking

When a financial year is **closed**, the system must prevent:

Editing transactions  
Deleting transactions  
Creating new transactions in that year

This ensures accounting data integrity.

---

## 25.5 Financial Year Closing Process

At the end of the financial year, the system should support a **year closing process**.

Closing actions include:

Finalize all transactions  
Lock the financial year  
Carry forward balances to next year

Example:

Closing FY 2025–2026  
↓

Opening balances automatically created in FY 2026–2027

---

## 25.6 Financial Year Reports

Reports must support filtering by financial year.

Examples:

Profit & Loss for FY 2025–2026  
Balance Sheet for FY 2024–2025

---

## 25.7 Branch-Based Financial Year (Optional)

If multi-branch support is enabled, branches may share the same financial year or optionally maintain separate accounting periods.

---

## 25.8 Financial Year Validation Rules

The system must enforce:

Transactions cannot be created outside the financial year range

Closed financial years cannot be edited

Only administrators can close financial years

Financial years cannot overlap

---



## 25.9 Automatic Financial Year Suggestion

When creating a new financial year, the system should automatically suggest the next period.

Example:

Existing: FY 2025–2026  
Suggested: FY 2026–2027

Settings
Company
Financial Year
Currency
Taxes
Invoice Settings
Branches
Advanced Settings

FY 2025–2026 ▼
# End of Additional Update

---

# 26. Simple Quotation Management

The system must provide a **simple quotation feature** that allows users to quickly prepare price offers for customers before creating invoices.

The module should be designed to be **easy for non-accountant users**.

---

# 26.1 Workflow

The quotation process should follow a simple workflow:

Customer → Quotation → Convert to Invoice

Users should be able to create a quotation and later convert it directly into an invoice.

---

# 26.2 Quotation Fields

Quotation Number  
Customer  
Quotation Date  
Valid Until  
Branch  
Items / Services  
Quantity  
Unit Price  
Tax  
Discount  
Total Amount  
Notes  
Terms & Conditions  

---

# 26.3 Quotation Status

Each quotation should have a status.

Draft  
Sent  
Accepted  
Rejected  
Expired  
Converted to Invoice

---

# 26.4 Quotation Features

Users should be able to:

Create quotation  
Edit quotation  
Duplicate quotation  
Convert quotation to invoice  
Export quotation as PDF  
Send quotation via email  

---

# 26.5 Convert to Invoice

When a quotation is accepted, the user should be able to convert it into an invoice with one click.

Button Example:

[ Convert to Invoice ]

The system should automatically copy:

Customer  
Items  
Prices  
Tax  
Discount  
Total  

---

# 26.6 Quotation Validity

Each quotation must include a **validity period**.

Example:

Valid Until: 30 Days

After the validity date, the quotation should automatically be marked as **Expired**.

---

# 26.7 Quotation Layout

The quotation layout should be similar to the invoice layout and include:

Company logo  
Branch details  
Customer details  
Items table  
Total amount  
Payment instructions  
Authorized signatory  

---

# 26.8 Simple User Interface

The quotation interface must be simple and clear.

Example menu:

Sales  
  Customers  
  Quotations  
  Invoices  

---

# End of Simple Quotation Section

# End of SRS
