<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryInvoiceController;
use App\Http\Controllers\DocumentPrintController;
use App\Http\Controllers\MasterData\CompanyExportController;
use App\Http\Controllers\MasterData\ProductExportController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\Reports\ExportController as ReportExportController;
use App\Http\Controllers\Reports\PdfController as ReportPdfController;
use App\Http\Controllers\Settings\BackupController;
use App\Livewire\Account\ChangePassword;
use App\Livewire\Account\NotificationSettings;
use App\Livewire\Account\Profile as AccountProfile;
use App\Livewire\Business\Index as BusinessIndex;
use App\Livewire\Collection\Create as CollectionCreate;
use App\Livewire\Collection\Index as CollectionIndex;
use App\Livewire\Dashboard\Dealer as DealerDashboard;
use App\Livewire\Dashboard\Representative as RepresentativeDashboard;
use App\Livewire\Dashboard\Retailer as RetailerDashboard;
use App\Livewire\Delivery\Create as DeliveryCreate;
use App\Livewire\Delivery\Index as DeliveryIndex;
use App\Livewire\Delivery\Invoices\Index as DeliveryInvoiceIndex;
use App\Livewire\Delivery\Invoices\Create as DeliveryInvoiceCreate;
use App\Livewire\Delivery\Invoices\Show as DeliveryInvoiceShow;
use App\Livewire\Dealers\Index as DealersIndex;
use App\Livewire\Dealers\Show as DealersShow;
use App\Livewire\Inventory\Areas;
use App\Livewire\Inventory\Factories;
use App\Livewire\Inventory\StockAdjustments;
use App\Livewire\Inventory\Warehouses;
use App\Livewire\MasterData\Brands;
use App\Livewire\MasterData\Categories;
use App\Livewire\MasterData\Companies;
use App\Livewire\MasterData\CompanyShow;
use App\Livewire\MasterData\Index as MasterDataIndex;
use App\Livewire\MasterData\Products;
use App\Livewire\MasterData\Units;
use App\Livewire\More\About as MoreAbout;
use App\Livewire\More\BackupRestore as MoreBackupRestore;
use App\Livewire\More\Index as MoreIndex;
use App\Livewire\Purchase\Orders\Create as PurchaseOrderCreate;
use App\Livewire\Purchase\Orders\Index as PurchaseOrderIndex;
use App\Livewire\Purchase\Orders\Show as PurchaseOrderShow;
use App\Livewire\Purchase\Returns\Create as PurchaseReturnsCreate;
use App\Livewire\Purchase\Returns\Index as PurchaseReturnsIndex;
use App\Livewire\Purchase\Returns\Show as PurchaseReturnsShow;
use App\Livewire\Notifications\Index as NotificationsIndex;
use App\Livewire\Payroll\Advances\Index as PayrollAdvancesIndex;
use App\Livewire\Portal\OrderCreate as PortalOrderCreate;
use App\Livewire\Portal\Orders as PortalOrders;
use App\Livewire\Portal\OrderShow as PortalOrderShow;
use App\Livewire\Portal\Returns as PortalReturns;
use App\Livewire\Portal\Statement as PortalStatement;
use App\Livewire\Portal\Warranty as PortalWarranty;
use App\Livewire\Payroll\Bonuses\Index as PayrollBonusesIndex;
use App\Livewire\Payroll\Entries\Create as PayrollEntryCreate;
use App\Livewire\Payroll\Entries\Index as PayrollEntryIndex;
use App\Livewire\Payroll\Entries\Show as PayrollEntryShow;
use App\Livewire\Purchase\Receipts\Create as GoodsReceiptCreate;
use App\Livewire\Purchase\Receipts\Index as GoodsReceiptIndex;
use App\Livewire\Representatives\Index as RepresentativesIndex;
use App\Livewire\Representatives\Show as RepresentativesShow;
use App\Livewire\Reports\Areas as ReportsAreas;
use App\Livewire\Reports\Dealers as ReportsDealers;
use App\Livewire\Reports\Collections as ReportsCollections;
use App\Livewire\Reports\Companies as ReportsCompanies;
use App\Livewire\Reports\Expiry as ReportsExpiry;
use App\Livewire\Reports\Index as ReportsIndex;
use App\Livewire\Reports\PayrollSummary as ReportsPayrollSummary;
use App\Livewire\Reports\Products as ReportsProducts;
use App\Livewire\Reports\Profit as ReportsProfit;
use App\Livewire\Reports\Purchase as ReportsPurchase;
use App\Livewire\Reports\Representatives as ReportsRepresentatives;
use App\Livewire\Reports\Retailers as ReportsRetailers;
use App\Livewire\Reports\Returns as ReportsReturns;
use App\Livewire\Reports\Sales as ReportsSales;
use App\Livewire\Reports\Stock as ReportsStock;
use App\Livewire\Reports\Warranty as ReportsWarranty;
use App\Livewire\Retailers\Index as RetailersIndex;
use App\Livewire\Retailers\Ledger as RetailersLedger;
use App\Livewire\Retailers\Show as RetailersShow;
use App\Livewire\Returns\CompanyReturns\Index as CompanyReturnIndex;
use App\Livewire\Returns\CompanyReturns\Show as CompanyReturnShow;
use App\Livewire\Returns\Receives\Create as ReturnReceiveCreate;
use App\Livewire\Returns\Requests\Create as ReturnRequestCreate;
use App\Livewire\Returns\Requests\Index as ReturnRequestIndex;
use App\Livewire\Returns\Requests\Show as ReturnRequestShow;
use App\Livewire\Sales\Orders\Create as SalesOrderCreate;
use App\Livewire\Sales\Orders\Index as SalesOrderIndex;
use App\Livewire\Sales\Orders\Show as SalesOrderShow;
use App\Livewire\Settings\AuditLogs as SettingsAuditLogs;
use App\Livewire\Settings\CompanyProfile as SettingsCompanyProfile;
use App\Livewire\Settings\LoginHistories as SettingsLoginHistories;
use App\Livewire\Settings\Numbering as SettingsNumbering;
use App\Livewire\Settings\Users\Index as SettingsUsersIndex;
use App\Livewire\Settings\WarrantyPolicies\Index as SettingsWarrantyPoliciesIndex;
use App\Livewire\Warranty\Claims\Create as WarrantyClaimCreate;
use App\Livewire\Warranty\Claims\Index as WarrantyClaimIndex;
use App\Livewire\Warranty\Claims\Show as WarrantyClaimShow;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.attempt');

    Route::get('/forgot-password', [PasswordResetController::class, 'showRequest'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendRequest'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');
});

// Site root: logged-in users go to their dashboard, everyone else to the login page.
// (public/.htaccess also sends a bare "/dms/" to /login so route caching works under a sub-folder.)
Route::get('/', [AuthController::class, 'home'])->name('home');
Route::post('/locale/{locale}', \App\Http\Controllers\LocaleController::class)->name('locale.switch');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/dashboard/dealer', DealerDashboard::class)->name('dashboard.dealer');
    Route::get('/dashboard/factory', \App\Livewire\Dashboard\Factory::class)->name('dashboard.factory');
    Route::get('/dashboard/accounts', \App\Livewire\Dashboard\Accounts::class)->name('dashboard.accounts');
    Route::get('/dashboard/hr', \App\Livewire\Dashboard\Hr::class)->name('dashboard.hr');
    Route::get('/dashboard/representative', RepresentativeDashboard::class)->name('dashboard.representative');
    Route::get('/dashboard/retailer', RetailerDashboard::class)->middleware('tenant.off')->name('dashboard.retailer');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/search', \App\Livewire\GlobalSearch::class)->name('search');
    Route::get('/help', \App\Livewire\Help::class)->name('help');
    // Print (PDF) of orders, receipts, challans, returns, transfers ... (permission checked per type)
    Route::get('/print/{type}/{id}', \App\Http\Controllers\DocumentPdfController::class)->whereNumber('id')->name('documents.print');
    Route::get('/notifications', NotificationsIndex::class)->name('notifications.index');
    Route::get('/notifications/{notification}/open', [NotificationController::class, 'open'])->name('notifications.open');

    Route::get('/account/change-password', ChangePassword::class)->name('account.change-password');
    Route::get('/account/notification-settings', NotificationSettings::class)->name('account.notification-settings');
    Route::get('/account/profile', AccountProfile::class)->name('account.profile');

    Route::middleware(['can:portal.access', 'tenant.off'])->prefix('portal')->name('portal.')->group(function () {
        Route::get('/orders', PortalOrders::class)->name('orders');
        Route::get('/orders/new', PortalOrderCreate::class)->name('orders.create');
        Route::get('/orders/{salesOrder}', PortalOrderShow::class)->name('orders.show');
        Route::get('/statement', PortalStatement::class)->name('statement');
        Route::get('/returns', PortalReturns::class)->middleware('module:returns')->name('returns');
        Route::get('/warranty', PortalWarranty::class)->middleware('module:warranty')->name('warranty');
        Route::get('/invoices/{deliveryInvoice}/pdf', [DeliveryInvoiceController::class, 'printForRetailer'])->name('invoices.print');
        Route::get('/sales-invoices/{salesInvoice}/pdf', [DocumentPrintController::class, 'salesInvoiceForRetailer'])->name('sales-invoices.print');
        Route::get('/receipts/{collection}/pdf', [DocumentPrintController::class, 'moneyReceiptForRetailer'])->name('receipts.print');
    });

    Route::get('/business', BusinessIndex::class)->name('business.index');
    Route::get('/more', MoreIndex::class)->name('more.index');
    Route::get('/more/about', MoreAbout::class)->name('more.about');

    Route::middleware('can:settings.manage')->group(function () {
        Route::get('/more/backup-restore', MoreBackupRestore::class)->name('more.backup-restore');
        Route::get('/more/backup-restore/download', [BackupController::class, 'download'])->name('more.backup.download');
        Route::get('/more/backup-restore/stored/{name}', [BackupController::class, 'downloadStored'])->name('more.backup.stored');
    });

    Route::middleware('can:master-data.view')->prefix('master-data')->name('master-data.')->group(function () {
        Route::get('/', MasterDataIndex::class)->name('index');
        Route::get('/companies', Companies::class)->name('companies');
        Route::get('/companies/export', CompanyExportController::class)->name('companies.export');
        Route::get('/companies/{company}', CompanyShow::class)->name('companies.show');
        Route::get('/brands', Brands::class)->name('brands');
        Route::get('/categories', Categories::class)->name('categories');
        Route::get('/units', Units::class)->name('units');
        Route::get('/products', Products::class)->name('products');
        Route::get('/products/export', ProductExportController::class)->name('products.export');
    });

    Route::middleware('can:accounts.view')->prefix('accounts')->name('accounts.')->group(function () {
        Route::get('/chart', \App\Livewire\Accounts\ChartOfAccounts::class)->name('chart');
        Route::get('/vouchers', \App\Livewire\Accounts\Vouchers\Index::class)->name('vouchers.index');
        Route::get('/vouchers/new', \App\Livewire\Accounts\Vouchers\Create::class)->name('vouchers.create');
        Route::get('/vouchers/{entry}', \App\Livewire\Accounts\Vouchers\Show::class)->name('vouchers.show');
        Route::get('/vouchers/{entry}/pdf', [\App\Http\Controllers\AccountsPdfController::class, 'voucher'])->name('vouchers.pdf');
        Route::get('/expenses', \App\Livewire\Accounts\Expenses::class)->name('expenses');
        Route::get('/supplier-payments', \App\Livewire\Accounts\SupplierPayments::class)->middleware('head.office')->name('supplier-payments');
        Route::get('/opening-balances', \App\Livewire\Accounts\OpeningBalances::class)->name('opening');
        Route::get('/fixed-assets', \App\Livewire\Accounts\FixedAssets::class)->name('assets');
        Route::get('/bank-reconciliation', \App\Livewire\Accounts\BankReconciliation::class)->name('bank');
        Route::get('/year-closing', \App\Livewire\Accounts\YearClosings::class)->name('year-closing');
        Route::get('/ledger', \App\Livewire\Accounts\Reports::class)->defaults('report', 'ledger')->name('ledger');
        Route::get('/reports/{report}', \App\Livewire\Accounts\Reports::class)->name('reports');
        Route::get('/reports/{report}/pdf', [\App\Http\Controllers\AccountsPdfController::class, 'report'])->name('reports.pdf');
    });

    Route::middleware(['can:hr.view', 'head.office'])->prefix('hr')->name('hr.')->group(function () {
        Route::get('/employees', \App\Livewire\Hr\Employees\Index::class)->name('employees.index');
        Route::get('/employees/{employee}', \App\Livewire\Hr\Employees\Show::class)->name('employees.show');
        Route::get('/attendance', \App\Livewire\Hr\Attendance::class)->name('attendance');
        Route::get('/attendance-machines', \App\Livewire\Hr\Devices::class)->middleware('module:attendance_devices')->name('devices');
        Route::get('/leave', \App\Livewire\Hr\Leaves::class)->name('leaves');
        Route::get('/advances-loans', \App\Livewire\Hr\Loans::class)->name('loans');
        Route::get('/salary', \App\Livewire\Hr\Payroll\Index::class)->name('payroll.index');
        Route::get('/salary/{run}', \App\Livewire\Hr\Payroll\Show::class)->name('payroll.show');
        Route::get('/salary/{run}/sheet.pdf', [\App\Http\Controllers\HrPdfController::class, 'sheet'])->name('payroll.pdf');
        Route::get('/salary/{run}/payslips.pdf', [\App\Http\Controllers\HrPdfController::class, 'payslips'])->name('payslips.pdf');
        Route::get('/payslips/{payslip}.pdf', [\App\Http\Controllers\HrPdfController::class, 'payslip'])->name('payslip.pdf');
        Route::get('/settlements/{settlement}.pdf', [\App\Http\Controllers\HrPdfController::class, 'settlement'])->name('settlement.pdf');
        Route::get('/setup', \App\Livewire\Hr\Setup::class)->name('setup');
    });

    Route::middleware(['can:production.view', 'head.office'])->prefix('production')->name('production.')->group(function () {
        Route::get('/batches', \App\Livewire\Production\Orders\Index::class)->name('orders.index');
        Route::get('/batches/{order}', \App\Livewire\Production\Orders\Show::class)->name('orders.show');
        Route::get('/batches/{order}/record.pdf', [\App\Http\Controllers\ProductionPdfController::class, 'batch'])->name('orders.pdf');
        Route::get('/formulas', \App\Livewire\Production\Formulas::class)->name('formulas');
        Route::get('/machines', \App\Livewire\Production\Machines::class)->name('machines');
        Route::get('/planning', \App\Livewire\Production\Planning::class)->name('planning');
        Route::get('/trace', \App\Livewire\Production\Trace::class)->name('trace');
        Route::get('/report', \App\Livewire\Production\Report::class)->name('report');
        Route::get('/maintenance', \App\Livewire\Production\Maintenance::class)->name('maintenance');
    });

    Route::middleware(['can:costing.view', 'head.office'])->prefix('costing')->name('costing.')->group(function () {
        Route::get('/', \App\Livewire\Costing\Index::class)->name('index');
        Route::get('/heads', \App\Livewire\Costing\Heads::class)->name('heads');
        Route::get('/new', \App\Livewire\Costing\Sheet::class)->name('sheets.create');
        Route::get('/{sheet}', \App\Livewire\Costing\Sheet::class)->whereNumber('sheet')->name('sheets.show');
        Route::get('/{sheet}/pdf', [\App\Http\Controllers\CostingPdfController::class, 'sheet'])->whereNumber('sheet')->name('sheets.pdf');
    });

    Route::middleware(['can:import.view', 'head.office'])->prefix('import')->name('import.')->group(function () {
        Route::get('/', \App\Livewire\Import\Index::class)->name('index');
        Route::get('/{shipment}', \App\Livewire\Import\Show::class)->whereNumber('shipment')->name('show');
    });

    Route::middleware(['can:kits.view', 'head.office'])->prefix('kits')->name('kits.')->group(function () {
        Route::get('/', \App\Livewire\Kits\Index::class)->name('index');
    });

    Route::middleware('can:damage.view')->prefix('damage')->name('damage.')->group(function () {
        Route::get('/', \App\Livewire\Damage\Index::class)->name('index');
        Route::get('/repairs', \App\Livewire\Damage\Repairs::class)->name('repairs');
        Route::get('/{record}', \App\Livewire\Damage\Show::class)->name('show');
    });

    Route::middleware('can:dealers.view')->prefix('dealers')->name('dealers.')->group(function () {
        Route::get('/', DealersIndex::class)->name('index');
        Route::get('/targets', \App\Livewire\Dealers\Targets::class)->name('targets');
        Route::get('/{dealer}', DealersShow::class)->name('show');
    });

    Route::middleware('can:inventory.view')->prefix('inventory')->name('inventory.')->group(function () {
        Route::get('/factories', Factories::class)->middleware(['head.office', 'module:production'])->name('factories');
        Route::get('/warehouses', Warehouses::class)->name('warehouses');
        Route::get('/areas', Areas::class)->middleware('head.office')->name('areas');
        Route::get('/adjustments', StockAdjustments::class)->name('adjustments');
        Route::get('/transfers', \App\Livewire\Production\Transfers::class)->name('transfers');
    });

    Route::middleware('can:purchase.view')->prefix('purchase')->name('purchase.')->group(function () {
        Route::get('/orders', PurchaseOrderIndex::class)->name('orders.index');
        Route::get('/orders/create', PurchaseOrderCreate::class)->name('orders.create');
        Route::get('/orders/{purchaseOrder}', PurchaseOrderShow::class)->name('orders.show');
        Route::get('/orders/{purchaseOrder}/edit', PurchaseOrderCreate::class)->middleware('can:purchase.manage')->name('orders.edit');
        Route::get('/orders/{purchaseOrder}/receive', GoodsReceiptCreate::class)->name('receipts.create');
        Route::get('/receipts', GoodsReceiptIndex::class)->name('receipts.index');
        Route::get('/returns', PurchaseReturnsIndex::class)->name('returns.index');
        Route::get('/returns/create', PurchaseReturnsCreate::class)->name('returns.create');
        Route::get('/returns/{purchaseReturn}', PurchaseReturnsShow::class)->name('returns.show');
    });

    Route::middleware('can:sales.view')->prefix('sales')->name('sales.')->group(function () {
        Route::get('/orders', SalesOrderIndex::class)->name('orders.index');
        Route::get('/schemes', \App\Livewire\Sales\Schemes::class)->name('schemes');
        Route::get('/orders/create', SalesOrderCreate::class)->name('orders.create');
        Route::get('/orders/{salesOrder}', SalesOrderShow::class)->name('orders.show');
        Route::get('/orders/{salesOrder}/edit', SalesOrderCreate::class)->middleware('can:sales.create')->name('orders.edit');
        Route::get('/orders/{salesOrder}/deliver', DeliveryCreate::class)->middleware('can:delivery.manage')->name('orders.deliver');
        Route::get('/invoices/{salesInvoice}/print', [DocumentPrintController::class, 'salesInvoice'])->name('invoices.print');
        Route::get('/invoices/{salesInvoice}/mushak', [DocumentPrintController::class, 'mushak'])->name('invoices.mushak');
    });

    Route::middleware('can:delivery.view')->prefix('deliveries')->name('delivery.')->group(function () {
        Route::get('/', DeliveryIndex::class)->name('index');
        Route::get('/invoices', DeliveryInvoiceIndex::class)->name('invoices.index');
        Route::get('/invoices/{deliveryInvoice}', DeliveryInvoiceShow::class)->name('invoices.show');
        Route::get('/invoices/{deliveryInvoice}/print', [DeliveryInvoiceController::class, 'printInvoice'])->name('invoices.print');
        Route::get('/notes/{deliveryNote}/invoice/create', DeliveryInvoiceCreate::class)->middleware('can:delivery.manage')->name('invoices.create');
        Route::get('/sales-order/{salesOrder}/invoices/print', [DeliveryInvoiceController::class, 'printSalesOrderInvoices'])->name('sales-order.invoices.print');
    });

    Route::middleware('can:collection.view')->prefix('collections')->name('collection.')->group(function () {
        Route::get('/', CollectionIndex::class)->name('index');
        Route::get('/create', CollectionCreate::class)->name('create');
        Route::get('/cheques', \App\Livewire\Collection\Cheques::class)->name('cheques');
        Route::get('/{collection}/receipt', [DocumentPrintController::class, 'moneyReceipt'])->name('receipt');
    });

    Route::middleware('can:retailers.view')->prefix('retailers')->name('retailers.')->group(function () {
        Route::get('/', RetailersIndex::class)->name('index');
        Route::get('/{retailer}/ledger', RetailersLedger::class)->name('ledger');
        Route::get('/{retailer}', RetailersShow::class)->name('show');
    });

    Route::middleware('can:representatives.view')->prefix('representatives')->name('representatives.')->group(function () {
        Route::get('/', RepresentativesIndex::class)->name('index');
        Route::get('/{representative}', RepresentativesShow::class)->name('show');
    });

    Route::middleware('can:returns.view')->prefix('returns')->name('returns.')->group(function () {
        Route::get('/', ReturnRequestIndex::class)->name('index');
        Route::get('/create', ReturnRequestCreate::class)->middleware('can:returns.create')->name('create');
        Route::get('/company-returns', CompanyReturnIndex::class)->name('company.index');
        Route::get('/company-returns/{companyReturn}', CompanyReturnShow::class)->name('company.show');
        Route::get('/{returnRequest}', ReturnRequestShow::class)->name('show');
        Route::get('/{returnRequest}/edit', ReturnRequestCreate::class)->middleware('can:returns.create')->name('edit');
        Route::get('/receives/{returnReceive}/mushak', [DocumentPrintController::class, 'creditNote'])->whereNumber('returnReceive')->name('credit-note');
        Route::get('/{returnRequest}/receive', ReturnReceiveCreate::class)->middleware('can:returns.manage')->name('receive');
    });

    Route::middleware('can:warranty.view')->prefix('warranty')->name('warranty.')->group(function () {
        Route::get('/', WarrantyClaimIndex::class)->name('index');
        Route::get('/create', WarrantyClaimCreate::class)->middleware('can:warranty.manage')->name('create');
        Route::get('/{warrantyClaim}', WarrantyClaimShow::class)->name('show');
        Route::get('/{warrantyClaim}/edit', WarrantyClaimCreate::class)->middleware('can:warranty.manage')->name('edit');
    });

    Route::middleware('can:reports.view')->prefix('reports')->name('reports.')->group(function () {
        Route::get('/', ReportsIndex::class)->name('index');
        Route::get('/sales', ReportsSales::class)->name('sales');
        Route::get('/stock', ReportsStock::class)->name('stock');
        Route::get('/collections', ReportsCollections::class)->name('collections');
        Route::get('/returns', ReportsReturns::class)->middleware('module:returns')->name('returns');
        Route::get('/representatives', ReportsRepresentatives::class)->middleware('module:representatives')->name('representatives');
        Route::get('/purchase', ReportsPurchase::class)->middleware('head.office')->name('purchase');
        Route::get('/companies', ReportsCompanies::class)->middleware('head.office')->name('companies');
        Route::get('/products', ReportsProducts::class)->name('products');
        Route::get('/retailers', ReportsRetailers::class)->name('retailers');
        Route::get('/warranty', ReportsWarranty::class)->middleware('module:warranty')->name('warranty');
        Route::get('/payroll', ReportsPayrollSummary::class)->middleware(['head.office', 'module:representatives'])->name('payroll');
        Route::get('/profit', ReportsProfit::class)->name('profit');
        Route::get('/vat', \App\Livewire\Reports\Vat::class)->middleware(['head.office', 'module:vat'])->name('vat');
        Route::get('/expiry', ReportsExpiry::class)->name('expiry');
        Route::get('/areas', ReportsAreas::class)->name('areas');
        Route::get('/dealers', ReportsDealers::class)->middleware('can:dealers.view')->name('dealers');

        Route::get('/pdf/{report}', [ReportPdfController::class, 'show'])->name('pdf');

        Route::prefix('export')->name('export.')->group(function () {
            Route::get('/sales', [ReportExportController::class, 'sales'])->name('sales');
            Route::get('/stock', [ReportExportController::class, 'stock'])->name('stock');
            Route::get('/collections', [ReportExportController::class, 'collections'])->name('collections');
            Route::get('/returns', [ReportExportController::class, 'returns'])->middleware('module:returns')->name('returns');
            Route::get('/representatives', [ReportExportController::class, 'representatives'])->middleware('module:representatives')->name('representatives');
            Route::get('/purchase', [ReportExportController::class, 'purchase'])->middleware('head.office')->name('purchase');
            Route::get('/companies', [ReportExportController::class, 'companies'])->middleware('head.office')->name('companies');
            Route::get('/products', [ReportExportController::class, 'products'])->name('products');
            Route::get('/retailers', [ReportExportController::class, 'retailers'])->name('retailers');
            Route::get('/warranty', [ReportExportController::class, 'warranty'])->middleware('module:warranty')->name('warranty');
            Route::get('/payroll', [ReportExportController::class, 'payroll'])->middleware(['head.office', 'module:representatives'])->name('payroll');
            Route::get('/profit', [ReportExportController::class, 'profit'])->name('profit');
            Route::get('/expiry', [ReportExportController::class, 'expiry'])->name('expiry');
            Route::get('/areas', [ReportExportController::class, 'areas'])->name('areas');
            Route::get('/dealers', [ReportExportController::class, 'dealers'])->middleware('can:dealers.view')->name('dealers');
        });
    });

    Route::middleware('can:payroll.view')->prefix('payroll')->name('payroll.')->group(function () {
        Route::get('/', PayrollEntryIndex::class)->name('index');
        Route::get('/generate', PayrollEntryCreate::class)->middleware('can:payroll.manage')->name('generate');
        Route::get('/advances', PayrollAdvancesIndex::class)->name('advances.index');
        Route::get('/bonuses', PayrollBonusesIndex::class)->name('bonuses.index');
        Route::get('/{payrollEntry}', PayrollEntryShow::class)->name('show');
    });

    Route::middleware('can:settings.manage')->prefix('settings')->name('settings.')->group(function () {
        Route::get('/company-profile', SettingsCompanyProfile::class)->name('company-profile');
        Route::get('/modules', \App\Livewire\Settings\ModuleSettings::class)->name('modules');
        Route::get('/setup', \App\Livewire\Setup\Wizard::class)->name('setup');
        Route::get('/numbering', SettingsNumbering::class)->name('numbering');
        Route::get('/data-import', \App\Livewire\Settings\DataImport::class)->name('data-import');
        Route::get('/data-import/template/{type}', \App\Http\Controllers\DataImportTemplateController::class)->name('data-import.template');
        Route::get('/email-sms', \App\Livewire\Settings\Messaging::class)->name('messaging');
        Route::get('/warranty-policies', SettingsWarrantyPoliciesIndex::class)->middleware('module:warranty')->name('warranty-policies');
        Route::get('/users', SettingsUsersIndex::class)->name('users');
        Route::get('/login-history', SettingsLoginHistories::class)->name('login-history');
        Route::get('/audit-log', SettingsAuditLogs::class)->name('audit-log');
    });
});
