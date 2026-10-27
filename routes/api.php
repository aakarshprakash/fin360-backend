<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\UserActivityLogController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\JobTitleController;
use App\Http\Controllers\Api\CompanySettingController;

// Accounting Module Controllers
use App\Http\Controllers\Api\Accounting\AccountController;
use App\Http\Controllers\Api\Accounting\AccountingCustomerController;
use App\Http\Controllers\Api\Accounting\VendorController;
use App\Http\Controllers\Api\Accounting\InvoiceController;
use App\Http\Controllers\Api\Accounting\BillController;
use App\Http\Controllers\Api\Accounting\IncomeController;
use App\Http\Controllers\Api\Accounting\ExpenseController;
use App\Http\Controllers\Api\Accounting\IncomeExpenseController;
use App\Http\Controllers\Api\Accounting\BankAccountController;
use App\Http\Controllers\Api\Accounting\JournalEntryController;
use App\Http\Controllers\Api\Accounting\TaxController;
use App\Http\Controllers\Api\Accounting\CurrencyController;
use App\Http\Controllers\Api\Accounting\AccountingDashboardController;
use App\Http\Controllers\Api\Accounting\AccountingSettingsController;
use App\Http\Controllers\Api\Accounting\RecurringTransactionController;
use App\Http\Controllers\Api\Accounting\FinancialYearController;
use App\Http\Controllers\Api\Accounting\AuditLogController;
use App\Http\Controllers\Api\Accounting\BulkOperationController;
use App\Http\Controllers\Api\Accounting\InvoiceSettingsController;
use App\Http\Controllers\Api\Accounting\PaymentMethodController;
use App\Http\Controllers\Api\Accounting\BranchController;
use App\Http\Controllers\Api\Accounting\QuotationController;
use App\Http\Controllers\Api\Accounting\ProformaInvoiceController;
use App\Http\Controllers\Api\Accounting\ProductController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Health check (public)
Route::get('/', function () {
    return response()->json([
        'app'       => 'Fin360 API',
        'status'    => 'running',
        'version'   => '2.1.0',
        'timestamp' => now()->toDateTimeString(),
    ]);
});

// Ping (public — use this to confirm the API is reachable)
Route::get('/ping', function () {
    return response()->json(['message' => 'pong', 'timestamp' => now()->toDateTimeString()]);
});

// Auth check (protected — use this to confirm your token is valid)
Route::middleware('auth:sanctum')->get('/auth-check', function (Request $request) {
    return response()->json([
        'message'       => 'Token is valid',
        'authenticated' => true,
        'user_id'       => $request->user()->id,
        'user_name'     => $request->user()->name,
        'user_email'    => $request->user()->email,
        'user_role'     => $request->user()->role,
    ]);
});

// ─── Public Auth ─────────────────────────────────────────────────────────────
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login'])->middleware('throttle:10,1');

// ─── Password Reset (Public) ─────────────────────────────────────────────────
Route::post('/password/forgot', [PasswordResetController::class, 'forgot'])->middleware('throttle:5,1');
Route::post('/password/reset',  [PasswordResetController::class, 'reset'])->middleware('throttle:5,1');

// ─── Company Settings (Public — login page needs branding) ────────────────────
Route::get('/settings/company', [CompanySettingController::class, 'show']);

// ─── Protected Routes ─────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // ── Auth ───────────────────────────────────────────────────────────────────
    Route::post('/logout',            [AuthController::class, 'logout']);
    Route::post('/logout-everywhere', [AuthController::class, 'logoutEverywhere']);
    Route::post('/refresh-token',     [AuthController::class, 'refreshToken']);
    Route::get('/me',                 [AuthController::class, 'me']);

    // ── Roles (manage_roles permission) ───────────────────────────────────────
    Route::middleware('permission:manage_roles')->group(function () {
        Route::get('/roles',         [RoleController::class, 'index']);
        Route::get('/roles/{id}',    [RoleController::class, 'show']);
        Route::post('/roles',        [RoleController::class, 'store']);
        Route::put('/roles/{id}',    [RoleController::class, 'update']);
        Route::delete('/roles/{id}', [RoleController::class, 'destroy']);
    });

    // ── Permissions list (manage_roles OR manage_users — needed by role/user editing UI)
    Route::middleware('permission:manage_roles,manage_users,manage_permissions')->group(function () {
        Route::get('/permissions', [PermissionController::class, 'index']);
    });

    // ── Permissions CRUD (manage_permissions — admin only) ─────────────────────
    Route::middleware('permission:manage_permissions')->group(function () {
        Route::get('/permissions/{id}',     [PermissionController::class, 'show']);
        Route::post('/permissions',         [PermissionController::class, 'store']);
        Route::put('/permissions/{id}',     [PermissionController::class, 'update']);
        Route::delete('/permissions/{id}',  [PermissionController::class, 'destroy']);
    });

    // ── Activity Logs (Admin only) ─────────────────────────────────────────────
    Route::middleware('permission:view_activity_logs')->group(function () {
        Route::get('/activity-logs',       [UserActivityLogController::class, 'index']);
        Route::get('/activity-logs/stats', [UserActivityLogController::class, 'stats']);
    });

    // ── Users (Admin) ─────────────────────────────────────────────────────────
    // Profile/password routes before {id} param
    Route::put('/users/profile',             [UserController::class, 'updateProfile']);
    Route::put('/users/password',            [UserController::class, 'changePassword']);

    Route::get('/users',                     [UserController::class, 'index']);
    Route::post('/users',                    [UserController::class, 'store']);
    Route::get('/users/{id}',                [UserController::class, 'show']);
    Route::put('/users/{id}',                [UserController::class, 'update']);
    Route::delete('/users/{id}',             [UserController::class, 'destroy']);

    // User role & permission management
    Route::post('/users/{id}/assign-role',   [UserController::class, 'assignRole']);
    Route::get('/users/{id}/permissions',    [UserController::class, 'getUserPermissions']);
    Route::put('/users/{id}/permissions',    [UserController::class, 'updateUserPermissions']);

    // ── Notifications ─────────────────────────────────────────────────────────
    Route::get('/notifications',             [NotificationController::class, 'index']);

    // ── Settings ──────────────────────────────────────────────────────────────
    Route::get('/settings',                  [SettingsController::class, 'index']);
    Route::put('/settings',                  [SettingsController::class, 'update']);

    // ── Company Settings (Admin) ──────────────────────────────────────────────
    Route::put('/settings/company', [CompanySettingController::class, 'update'])
        ->middleware('permission:manage_company_settings');

    // ── Departments ───────────────────────────────────────────────────────────
    Route::get('/departments',                      [DepartmentController::class, 'index']);
    Route::post('/departments',                     [DepartmentController::class, 'store']);
    Route::get('/departments/{id}',                 [DepartmentController::class, 'show']);
    Route::put('/departments/{id}',                 [DepartmentController::class, 'update']);
    Route::delete('/departments/{id}',              [DepartmentController::class, 'destroy']);
    Route::patch('/departments/{id}/toggle',        [DepartmentController::class, 'toggle']);
    Route::get('/departments/{id}/job-titles',      [DepartmentController::class, 'jobTitles']);

    // ── Job Titles ────────────────────────────────────────────────────────────
    Route::get('/job-titles',                       [JobTitleController::class, 'index']);
    Route::post('/job-titles',                      [JobTitleController::class, 'store']);
    Route::put('/job-titles/{id}',                  [JobTitleController::class, 'update']);
    Route::delete('/job-titles/{id}',               [JobTitleController::class, 'destroy']);
    Route::patch('/job-titles/{id}/toggle',         [JobTitleController::class, 'toggle']);

    // ═══════════════════════════════════════════════════════════════════════════
    // ACCOUNTING MODULE — /api/accounting/*
    // ═══════════════════════════════════════════════════════════════════════════
    Route::prefix('accounting')->group(function () {

        // ── Products (catalog for invoice/bill/quotation line-item picker) ──────
        Route::middleware('permission:view_accounting,manage_accounting')->group(function () {
            Route::get('/products',              [ProductController::class, 'index']);
            Route::get('/products/categories',   [ProductController::class, 'categories']);
            Route::get('/products/{id}',         [ProductController::class, 'show']);
            Route::get('/products/{id}/stock-movements', [ProductController::class, 'stockMovements']);
        });
        Route::middleware('permission:manage_accounting')->group(function () {
            Route::post('/products',             [ProductController::class, 'store']);
            Route::put('/products/{id}',         [ProductController::class, 'update']);
            Route::delete('/products/{id}',      [ProductController::class, 'destroy']);
            Route::post('/products/{id}/image',  [ProductController::class, 'uploadImage']);
            Route::delete('/products/{id}/image', [ProductController::class, 'deleteImage']);
            Route::post('/products/{id}/stock',  [ProductController::class, 'adjustStock']);
        });

        // ── Chart of Accounts ──────────────────────────────────────────────────
        Route::middleware('permission:view_accounting,manage_accounting')->group(function () {
            Route::get('/accounts',                     [AccountController::class, 'index']);
            Route::get('/accounts/{id}',                [AccountController::class, 'show']);
            Route::get('/accounts/{id}/ledger',         [AccountController::class, 'ledger']);
            Route::get('/accounts/export',              [BulkOperationController::class, 'exportAccounts']);
        });
        Route::middleware('permission:manage_accounting')->group(function () {
            Route::post('/accounts/bulk-delete',        [BulkOperationController::class, 'bulkDeleteAccounts']);
            Route::post('/accounts/import',             [BulkOperationController::class, 'importAccounts']);
            Route::post('/accounts',                    [AccountController::class, 'store']);
            Route::put('/accounts/{id}',                [AccountController::class, 'update']);
            Route::delete('/accounts/{id}',             [AccountController::class, 'destroy']);
        });

        // ── Accounting Customers ───────────────────────────────────────────────
        Route::middleware('permission:view_accounting,manage_accounting')->group(function () {
            Route::get('/customers',                     [AccountingCustomerController::class, 'index']);
            Route::get('/customers/{id}',                [AccountingCustomerController::class, 'show']);
            Route::get('/customers/{id}/ledger',         [AccountingCustomerController::class, 'ledger']);
            Route::get('/customers/{id}/invoices',       [AccountingCustomerController::class, 'invoices']);
            Route::get('/customers/{id}/statement',      [AccountingCustomerController::class, 'statement']);
        });
        Route::middleware('permission:manage_accounting')->group(function () {
            Route::post('/customers',                    [AccountingCustomerController::class, 'store']);
            Route::put('/customers/{id}',                [AccountingCustomerController::class, 'update']);
            Route::delete('/customers/{id}',             [AccountingCustomerController::class, 'destroy']);
        });

        // ── Vendors ────────────────────────────────────────────────────────────
        Route::middleware('permission:view_accounting,manage_accounting')->group(function () {
            Route::get('/vendors',                       [VendorController::class, 'index']);
            Route::get('/vendors/{id}',                  [VendorController::class, 'show']);
            Route::get('/vendors/{id}/ledger',           [VendorController::class, 'ledger']);
            Route::get('/vendors/{id}/bills',            [VendorController::class, 'bills']);
            Route::get('/vendors/{id}/statement',        [VendorController::class, 'statement']);
        });
        Route::middleware('permission:manage_accounting')->group(function () {
            Route::post('/vendors',                      [VendorController::class, 'store']);
            Route::put('/vendors/{id}',                  [VendorController::class, 'update']);
            Route::delete('/vendors/{id}',               [VendorController::class, 'destroy']);
        });

        // ── Invoices / Sales ───────────────────────────────────────────────────
        Route::middleware('permission:view_invoices,manage_accounting')->group(function () {
            Route::get('/invoices',                      [InvoiceController::class, 'index']);
            Route::get('/invoices/{id}',                 [InvoiceController::class, 'show']);
            Route::get('/invoices/{id}/payments',        [InvoiceController::class, 'listPayments']);
            Route::get('/invoices/{id}/pdf',             [InvoiceController::class, 'pdf']);
            Route::get('/credit-notes',                  [InvoiceController::class, 'listCreditNotes']);
        });
        Route::middleware('permission:create_invoices,manage_accounting')->group(function () {
            Route::post('/invoices',                     [InvoiceController::class, 'store']);
        });
        Route::middleware('permission:manage_invoices,manage_accounting')->group(function () {
            Route::post('/invoices/bulk-status',         [BulkOperationController::class, 'bulkUpdateInvoiceStatus']);
            Route::put('/invoices/{id}',                 [InvoiceController::class, 'update']);
            Route::delete('/invoices/{id}',              [InvoiceController::class, 'destroy']);
            Route::patch('/invoices/{id}/status',        [InvoiceController::class, 'updateStatus']);
            Route::post('/invoices/{id}/payments',       [InvoiceController::class, 'addPayment']);
            Route::delete('/invoices/{id}/payments/{paymentId}', [InvoiceController::class, 'deletePayment']);
            Route::post('/invoices/{id}/credit-notes',   [InvoiceController::class, 'storeCreditNote']);
            Route::delete('/invoices/{id}/credit-notes/{creditNoteId}', [InvoiceController::class, 'deleteCreditNote']);
            Route::post('/invoices/{id}/send',           [InvoiceController::class, 'send']);
            Route::post('/credit-notes',                 [InvoiceController::class, 'createCreditNote']);
        });

        // ── Bills / Purchases (no dedicated permission — gated by manage_accounting) ──
        Route::middleware('permission:view_accounting,manage_accounting')->group(function () {
            Route::get('/bills',                         [BillController::class, 'index']);
            Route::get('/bills/{id}',                    [BillController::class, 'show']);
            Route::get('/bills/{id}/payments',           [BillController::class, 'listPayments']);
            Route::get('/debit-notes',                   [BillController::class, 'listDebitNotes']);
        });
        Route::middleware('permission:manage_accounting')->group(function () {
            Route::post('/bills',                        [BillController::class, 'store']);
            Route::put('/bills/{id}',                    [BillController::class, 'update']);
            Route::delete('/bills/{id}',                 [BillController::class, 'destroy']);
            Route::patch('/bills/{id}/status',           [BillController::class, 'updateStatus']);
            Route::post('/bills/{id}/payments',          [BillController::class, 'addPayment']);
            Route::post('/debit-notes',                  [BillController::class, 'createDebitNote']);
        });

        // ── Income ─────────────────────────────────────────────────────────────
        Route::middleware('permission:view_accounting,manage_accounting')->group(function () {
            Route::get('/income/categories',             [IncomeController::class, 'categories']);
            Route::get('/income',                        [IncomeController::class, 'index']);
            Route::get('/income/{id}',                   [IncomeController::class, 'show']);
        });
        Route::middleware('permission:manage_accounting')->group(function () {
            Route::post('/income',                       [IncomeController::class, 'store']);
            Route::put('/income/{id}',                   [IncomeController::class, 'update']);
            Route::delete('/income/{id}',                [IncomeController::class, 'destroy']);
        });

        // ── Expenses ───────────────────────────────────────────────────────────
        Route::middleware('permission:view_expenses,manage_accounting')->group(function () {
            Route::get('/expenses/categories',           [ExpenseController::class, 'categories']);
            Route::get('/expenses',                      [ExpenseController::class, 'index']);
            Route::get('/expenses/{id}',                 [ExpenseController::class, 'show']);
        });
        Route::middleware('permission:manage_expenses,manage_accounting')->group(function () {
            Route::post('/expenses',                     [ExpenseController::class, 'store']);
            Route::put('/expenses/{id}',                 [ExpenseController::class, 'update']);
            Route::delete('/expenses/{id}',              [ExpenseController::class, 'destroy']);
        });

        // ── Income & Expense Summary ───────────────────────────────────────────
        Route::middleware('permission:view_accounting,manage_accounting')->group(function () {
            Route::get('/income-expenses/summary',       [IncomeExpenseController::class, 'summary']);
        });

        // ── Banking ────────────────────────────────────────────────────────────
        Route::middleware('permission:view_accounting,manage_accounting')->group(function () {
            Route::get('/bank-accounts',                                    [BankAccountController::class, 'index']);
            Route::get('/bank-accounts/{id}',                               [BankAccountController::class, 'show']);
            Route::get('/bank-accounts/{id}/transactions',                  [BankAccountController::class, 'transactions']);
            Route::get('/bank-accounts/{id}/reconciliation',                [BankAccountController::class, 'reconciliation']);
        });
        Route::middleware('permission:manage_accounting')->group(function () {
            Route::post('/bank-accounts',                                   [BankAccountController::class, 'store']);
            Route::put('/bank-accounts/{id}',                               [BankAccountController::class, 'update']);
            Route::delete('/bank-accounts/{id}',                            [BankAccountController::class, 'destroy']);
            Route::post('/bank-accounts/{id}/transactions',                 [BankAccountController::class, 'createTransaction']);
            Route::put('/bank-accounts/{bankId}/transactions/{txnId}',      [BankAccountController::class, 'updateTransaction']);
            Route::delete('/bank-accounts/{bankId}/transactions/{txnId}',   [BankAccountController::class, 'deleteTransaction']);
            Route::post('/bank-accounts/{id}/reconcile',                    [BankAccountController::class, 'reconcile']);
        });

        // ── Journal Entries ────────────────────────────────────────────────────
        Route::middleware('permission:view_accounting,manage_accounting')->group(function () {
            Route::get('/journal-entries',               [JournalEntryController::class, 'index']);
            Route::get('/journal-entries/{id}',          [JournalEntryController::class, 'show']);
        });
        Route::middleware('permission:manage_accounting')->group(function () {
            Route::post('/journal-entries',              [JournalEntryController::class, 'store']);
            Route::put('/journal-entries/{id}',          [JournalEntryController::class, 'update']);
            Route::delete('/journal-entries/{id}',       [JournalEntryController::class, 'destroy']);
            Route::patch('/journal-entries/{id}/post',   [JournalEntryController::class, 'post']);
            Route::post('/journal-entries/{id}/reverse', [JournalEntryController::class, 'reverse']);
        });

        // ── Taxes ──────────────────────────────────────────────────────────────
        Route::middleware('permission:accounting_settings,manage_accounting')->group(function () {
            Route::get('/taxes',                         [TaxController::class, 'index']);
            Route::post('/taxes',                        [TaxController::class, 'store']);
            Route::get('/taxes/{id}',                    [TaxController::class, 'show']);
            Route::put('/taxes/{id}',                    [TaxController::class, 'update']);
            Route::delete('/taxes/{id}',                 [TaxController::class, 'destroy']);
        });

        // ── Currencies ─────────────────────────────────────────────────────────
        Route::middleware('permission:accounting_settings,manage_accounting')->group(function () {
            Route::post('/currencies/refresh-rates',     [CurrencyController::class, 'refreshRates']);
            Route::get('/currencies',                    [CurrencyController::class, 'index']);
            Route::post('/currencies',                   [CurrencyController::class, 'store']);
            Route::get('/currencies/{id}',               [CurrencyController::class, 'show']);
            Route::put('/currencies/{id}',               [CurrencyController::class, 'update']);
            Route::delete('/currencies/{id}',            [CurrencyController::class, 'destroy']);
        });

        // ── Dashboard & Reports ────────────────────────────────────────────────
        Route::middleware('permission:view_accounting,manage_accounting')->group(function () {
            Route::get('/dashboard',                     [AccountingDashboardController::class, 'index']);
            Route::get('/dashboard/overview',            [AccountingDashboardController::class, 'overview']);
            Route::get('/dashboard/charts',              [AccountingDashboardController::class, 'charts']);
            Route::get('/dashboard/recent-transactions', [AccountingDashboardController::class, 'recentTransactions']);
        });
        Route::middleware('permission:view_reports,view_accounting,manage_accounting')->group(function () {
            Route::get('/reports/{type}/export',         [AccountingDashboardController::class, 'reportExport']);
            Route::get('/reports/{type}',                [AccountingDashboardController::class, 'report']);
        });

        // ── Settings (organisation-wide) ───────────────────────────────────────
        // Anyone who prints documents can read the letterhead, template and
        // payment methods; only accounting_settings may change them.
        Route::middleware('permission:accounting_settings,manage_accounting,view_accounting,view_invoices,view_quotations')->group(function () {
            Route::get('/settings',                      [AccountingSettingsController::class, 'index']);
            Route::get('/invoice-settings',              [InvoiceSettingsController::class, 'index']);
            Route::get('/payment-methods',               [PaymentMethodController::class, 'index']);
            Route::get('/payment-methods/{id}',          [PaymentMethodController::class, 'show']);
        });
        Route::middleware('permission:accounting_settings')->group(function () {
            Route::put('/settings',                      [AccountingSettingsController::class, 'update']);
            Route::post('/settings/signature',            [AccountingSettingsController::class, 'uploadSignature']);
            Route::delete('/settings/signature',          [AccountingSettingsController::class, 'deleteSignature']);
            Route::post('/settings/logo',                 [AccountingSettingsController::class, 'uploadLogo']);
            Route::delete('/settings/logo',                [AccountingSettingsController::class, 'deleteLogo']);
            Route::post('/settings/quotation-image/{slot}',   [AccountingSettingsController::class, 'uploadQuotationImage'])->where('slot', 'header|footer');
            Route::delete('/settings/quotation-image/{slot}', [AccountingSettingsController::class, 'deleteQuotationImage'])->where('slot', 'header|footer');
        });

        // ── Recurring Transactions ─────────────────────────────────────────────
        Route::middleware('permission:view_accounting,manage_accounting')->group(function () {
            Route::get('/recurring',                     [RecurringTransactionController::class, 'index']);
            Route::get('/recurring/{id}',                [RecurringTransactionController::class, 'show']);
        });
        Route::middleware('permission:manage_accounting')->group(function () {
            Route::post('/recurring',                    [RecurringTransactionController::class, 'store']);
            Route::put('/recurring/{id}',                [RecurringTransactionController::class, 'update']);
            Route::delete('/recurring/{id}',             [RecurringTransactionController::class, 'destroy']);
            Route::patch('/recurring/{id}/pause',        [RecurringTransactionController::class, 'pause']);
            Route::patch('/recurring/{id}/resume',       [RecurringTransactionController::class, 'resume']);
        });

        // ── Financial Years ────────────────────────────────────────────────────
        Route::middleware('permission:accounting_settings,manage_accounting')->group(function () {
            Route::get('/financial-years',                    [FinancialYearController::class, 'index']);
            Route::post('/financial-years',                   [FinancialYearController::class, 'store']);
            Route::get('/financial-years/{id}',               [FinancialYearController::class, 'show']);
            Route::put('/financial-years/{id}',               [FinancialYearController::class, 'update']);
            Route::post('/financial-years/{id}/close',        [FinancialYearController::class, 'close']);
            Route::patch('/financial-years/{id}/set-default', [FinancialYearController::class, 'setDefault']);
            Route::get('/financial-years/{id}/balances',      [FinancialYearController::class, 'balances']);
        });

        // ── Invoice Template Settings & Payment Methods (writes) ───────────────
        Route::middleware('permission:accounting_settings')->group(function () {
            Route::put('/invoice-settings',              [InvoiceSettingsController::class, 'update']);
            Route::post('/payment-methods',              [PaymentMethodController::class, 'store']);
            Route::put('/payment-methods/{id}',          [PaymentMethodController::class, 'update']);
            Route::delete('/payment-methods/{id}',       [PaymentMethodController::class, 'destroy']);
        });

        // ── Branches / Multi-Location ──────────────────────────────────────────
        Route::middleware('permission:accounting_settings,manage_accounting')->group(function () {
            Route::get('/branches',                           [BranchController::class, 'index']);
            Route::post('/branches',                          [BranchController::class, 'store']);
            Route::get('/branches/{id}',                      [BranchController::class, 'show']);
            Route::put('/branches/{id}',                      [BranchController::class, 'update']);
            Route::delete('/branches/{id}',                   [BranchController::class, 'destroy']);
            Route::get('/branches/{id}/reports/{type}',       [BranchController::class, 'report']);
        });

        // ── Quotations ─────────────────────────────────────────────────────────
        Route::middleware('permission:view_quotations,manage_accounting')->group(function () {
            Route::get('/quotations',                           [QuotationController::class, 'index']);
            Route::get('/quotations/{id}',                      [QuotationController::class, 'show']);
            Route::get('/quotations/{id}/pdf',                  [QuotationController::class, 'pdf']);
        });
        Route::middleware('permission:manage_quotations,manage_accounting')->group(function () {
            Route::post('/quotations',                          [QuotationController::class, 'store']);
            Route::put('/quotations/{id}',                      [QuotationController::class, 'update']);
            Route::delete('/quotations/{id}',                   [QuotationController::class, 'destroy']);
            Route::patch('/quotations/{id}/status',             [QuotationController::class, 'updateStatus']);
            Route::post('/quotations/{id}/duplicate',           [QuotationController::class, 'duplicate']);
            Route::post('/quotations/{id}/convert-to-invoice',  [QuotationController::class, 'convertToInvoice']);
            Route::post('/quotations/{id}/convert-to-proforma', [QuotationController::class, 'convertToProforma']);
            Route::post('/quotations/{id}/send',                [QuotationController::class, 'send']);
        });

        // ── Proforma Invoices ──────────────────────────────────────────────
        Route::middleware('permission:view_quotations,manage_accounting')->group(function () {
            Route::get('/proforma-invoices',                            [ProformaInvoiceController::class, 'index']);
            Route::get('/proforma-invoices/{id}',                       [ProformaInvoiceController::class, 'show']);
            Route::get('/proforma-invoices/{id}/pdf',                   [ProformaInvoiceController::class, 'pdf']);
        });
        Route::middleware('permission:manage_quotations,manage_accounting')->group(function () {
            Route::post('/proforma-invoices',                           [ProformaInvoiceController::class, 'store']);
            Route::put('/proforma-invoices/{id}',                       [ProformaInvoiceController::class, 'update']);
            Route::delete('/proforma-invoices/{id}',                    [ProformaInvoiceController::class, 'destroy']);
            Route::patch('/proforma-invoices/{id}/status',              [ProformaInvoiceController::class, 'updateStatus']);
            Route::post('/proforma-invoices/{id}/duplicate',            [ProformaInvoiceController::class, 'duplicate']);
            Route::post('/proforma-invoices/{id}/convert-to-invoice',   [ProformaInvoiceController::class, 'convertToInvoice']);
            Route::post('/proforma-invoices/{id}/send',                 [ProformaInvoiceController::class, 'send']);
        });

        // ── Audit Logs ─────────────────────────────────────────────────────
        Route::middleware('permission:view_accounting,manage_accounting')->group(function () {
            Route::get('/audit-logs',                    [AuditLogController::class, 'index']);
        });
    });
});
