# DMS — ডেভেলপমেন্ট হিস্টোরি

এই প্রজেক্টে এখনো git ছিল না। তাই নিচের টাইমলাইন বানানো হয়েছে ফাইলের শেষ পরিবর্তনের তারিখ, migration-এর তারিখ আর আগের ডকুমেন্ট (`DELIVERY_INVOICE_GUIDE.md`, `DELIVERY_INVOICE_SETUP.md`) দেখে। কোনো ফাইল পরে আবার এডিট হলে সেটা শেষ পরিবর্তনের তারিখের ঘরে দেখাবে, তাই তারিখগুলো আনুমানিক।

> সর্বশেষ আপডেট: ২১ নভেম্বর ২০২৬

---

## টাইমলাইন

### ২৪ ফেব্রুয়ারি ২০২৫: Laravel skeleton
- `laravel/laravel` 12 skeleton (README, artisan, bootstrap, phpunit.xml, vite.config.js)।

### ২৬ জুলাই ২০২৬: শুরু
- `spatie/laravel-permission` install করা হয়, permission টেবিলের migration আর `config/permission.php` যোগ হয়।
- Livewire 4 যোগ হয় (`composer.json`)।

### ২৭ জুলাই ২০২৬: মূল কাঠামো (সবচেয়ে বড় দিন, ~৯০টা ফাইল)
- ৯টা migration: settings, master data, inventory, people, purchase, sales, delivery/collection, return/warranty, payroll।
- সব Eloquent model (Master, Inventory, Purchase, Sales, Delivery, Returns, Warranty, Payroll, Settings)।
- `AuthController`, `DashboardController`, `NotificationController`।
- Livewire-এর প্রধান ফ্লো: Purchase Order ও Goods Receipt, Sales Order, Delivery, Collection, Return receive, Company return, Warranty claim, Payroll generate।
- প্রথম ৫টা রিপোর্ট: Sales, Stock, Collections, Returns, Representatives।
- Settings: Company profile, Numbering।
- Node dependency (Tailwind 4, Vite 6, Chart.js) install।

### ২৮ জুলাই ২০২৬: CRUD ও Settings
- Master data-র CRUD পেজ: Brands, Categories, Units, Products, Areas, Warehouses।
- Company ও Product-এর CSV export।
- Representatives ও Retailers-এর index ও details পেজ।
- Purchase Return, Payroll Advances/Bonuses।
- Settings: Users, Warranty policies, Login history, Audit log, Change password।
- `NumberGeneratorService`, `DemoTransactionsSeeder`।
- `return_request_items.unit_price` কলাম যোগ, `notifications` টেবিল তৈরি।
- PWA: `manifest.json` ও আইকন।
- কম্পোনেন্ট: `icon-action`, `pagination`।

### ২৯ জুলাই ২০২৬: মোবাইল UX ও Index পেজ
- মোবাইলের bottom-nav হাব পেজ: `Business/Index`, `MasterData/Index`, `More/Index`, `More/About`, `More/BackupRestore`।
- Backup ডাউনলোড (`BackupController`)।
- Account profile ও Notification settings। `users.notification_preferences` কলাম যোগ হয়।
- প্রায় সব মডিউলের Index পেজ নতুন করে বানানো হয়: responsive টেবিল আর stat card।
- `InventoryService`-এ FIFO stock consumption ঠিক করা হয়।
- `<x-icon>` কম্পোনেন্টে নতুন আইকন।

### ১৩ আগস্ট ২০২৬: রিপোর্ট বাড়ানো ও পুরো UI পলিশ
- নতুন ৯টা রিপোর্ট: Purchase, Companies, Products, Retailers, Warranty, Payroll summary, Profit & Loss, Expiry, Areas। এতে মোট রিপোর্ট হয় ১৪টা।
- সব রিপোর্টের CSV export (`Reports/ExportController`)।
- `layouts/app.blade.php` নতুন করে ডিজাইন: ভাঁজ করা যায় এমন সাইডবার গ্রুপ, ব্রেডক্রাম্ব, নোটিফিকেশন ড্রপডাউন, ইউজার মেনু।
- প্রায় সব Blade view-এ UI-এর মান একরকম করা হয়।
- `stat-card` কম্পোনেন্টকে responsive করা হয়।

### ১৪ আগস্ট ২০২৬: Delivery Invoice (প্রথম ধাপ)
- `DeliveryInvoice` ও `DeliveryInvoiceItem` model, `DeliveryInvoiceController` (PDF print ও batch print)।
- PDF view: `pdfs/delivery-invoice`, `pdfs/delivery-invoices-batch`।
- তিনটা ড্যাশবোর্ড আপডেট করা হয়, Sales order create ও show আপডেট।
- ডকুমেন্ট লেখা হয়: `DELIVERY_INVOICE_GUIDE.md`, `DELIVERY_INVOICE_SETUP.md`, আর ফাঁকা `IMPLEMENTATION_SUMMARY.md`।

### ১৮ আগস্ট ২০২৬: Delivery Invoice (দ্বিতীয় ধাপ)
- `DeliveryInvoiceService`: আংশিক ডেলিভারির হিসাব, finalize করা, printed চিহ্নিত করা।
- Livewire: `Delivery/Invoices/Index`, `Create`, `Show`।
- `delivery_invoices` ও `delivery_invoice_items` টেবিলের migration।
- `routes/web.php`-এ ইনভয়েসের রুট যোগ।
- `YearlyDemoSeeder`: এক বছরের ডেমো ডেটা।

### ২৯–৩০ সেপ্টেম্বর ২০২৬
- লোকাল SQLite ডাটাবেস রিফ্রেশ করা হয় (migrate ও seed)।
- `AppServiceProvider`-এ `Gate::before`: Dealer রোল সব পারমিশন পায়।
- `RoleSeeder` আপডেট (Manager ও Sales Representative-এর পারমিশন)।

### ২০ নভেম্বর ২০২৬: ডকুমেন্টেশন
- GitHub রিপো `snsbd247/DMSbd247` রিভিউ করা হয়। দেখা যায় সেটা আলাদা একটা প্রজেক্ট (Lovable, TanStack + Supabase), তাই লোকালে কিছু বদলানো হয়নি।
- `plan.md`, `database.md`, `ui.md`, `chat_history.md`, `development.md` তৈরি করা হয়।

### ২১ নভেম্বর ২০২৬: Delivery Invoice সম্পূর্ণ ও সার্ভারে সেটআপ
**বাগ ঠিক করা:**
- `barryvdh/laravel-dompdf` (^3.1) install করা হলো, এখন PDF প্রিন্ট কাজ করে।
- `DeliveryInvoiceService` নতুন করে লেখা হলো। ভুল কলামে লেখা `logStockDelivery()` আর সেলস অর্ডারে অবৈধ স্ট্যাটাস (`delivered`, `partially_delivered`) লেখা `updateDeliveryNoteStatus()` বাদ দেওয়া হলো। স্টক, লেজার আর স্ট্যাটাস আগের মতোই `Delivery/Create` সামলায়।
- ইনভয়েস নম্বর এখন `NumberGeneratorService` থেকে আসে (`DI-YYYY-NNNNNN`), তাই duplicate হওয়ার ঝুঁকি নেই।
- **প্রতিটা নতুন ডেলিভারিতে ইনভয়েস নিজে থেকে তৈরি হয়** (একই transaction-এ)। আগের ৩টা ডেলিভারির ইনভয়েসও তৈরি করে দেওয়া হলো।
- Invoices-এর Index, Show আর Create পেজ নতুন করে লেখা হলো: সঠিক route প্যারামিটার, `delivery.manage` পারমিশন চেক, Bootstrap বাদ দিয়ে Tailwind।
- ভুল route নাম (`delivery-invoices.*`, `delivery-invoice.print`) ঠিক করা হলো।
- PDF-এর ভুল কলাম ঠিক করা হলো (`batch_number` → `batch_no`, `location` → `address`), duplicate "Amount" কলাম সরানো হলো, আর কোম্পানির নাম এখন Company Profile থেকে আসে। প্রিন্ট করলে ইনভয়েস `printed` হয়।
- সাইডবার আর মোবাইল Business পেজে "Delivery Invoices" লিংক যোগ হলো। `printer` আর `document-text` আইকন যোগ হলো।
- `delivery_invoices` টেবিলের migration চালানো হলো (আগে pending ছিল)।

**সার্ভার সেটআপ (XAMPP, `F:\Lovable\xampp`):**
- Apache alias `/dms` → `F:/Lovable/DMS/public` (`apache/conf/extra/httpd-dms.conf`, httpd.conf-এ Include)।
- `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=http://localhost/dms`, `SESSION_PATH=/dms`, `SESSION_COOKIE=dms_session`।
- `manifest.json`-এর পাথ relative করা হলো, যাতে PWA `/dms`-এর ভেতরে কাজ করে।
- `public/.htaccess`-এ dotfile, `.sql`, `.log` জাতীয় ফাইল ব্লক করা হলো।
- `npm run build`, `config:cache`, `view:cache` চালানো হলো।
- ব্যাকআপ: `storage/backups/database-before-dms-setup-20261121.sqlite`, `storage/backups/env-before-dms-setup-20261121`, `xampp/apache/conf/httpd.conf.bak-before-dms-20261121`।
- টেস্ট: তিনটা ঠিকানাতেই লগইন আর ৬০টার বেশি পেজ 200 দিয়েছে। PDF আর batch PDF তৈরি হয়। ডেলিভারি দিলে ইনভয়েস তৈরি হয় (rollback-সহ টেস্ট করা)।

### ২১ নভেম্বর ২০২৬ (দ্বিতীয় ধাপ): নিরাপত্তা ও রক্ষণাবেক্ষণ
- **পাসওয়ার্ড:** সব ইউজারের পাসওয়ার্ড র‍্যান্ডম করা হলো আর সব session বাতিল করা হলো। Seeder থেকে hard-coded `ChangeMe123!` সরানো হলো। এখন seeder `.env`-এর `DEMO_PASSWORD` নেয়, না থাকলে র‍্যান্ডম পাসওয়ার্ড দেয়।
- **HTTPS:** নতুন self-signed সার্টিফিকেট বসানো হলো। RSA 2048, মেয়াদ ২০৩৬ পর্যন্ত, SAN-এ আছে localhost, 127.0.0.1, 192.168.0.171 আর 160.25.9.132। এটা XAMPP-এর port 443-এর সব অ্যাপে প্রযোজ্য। পুরনো সার্টিফিকেট (মেয়াদ শেষ হয়েছিল ২০১৯-এ) `server.crt.bak-20261121` আর `server.key.bak-20261121` নামে রাখা আছে। http-ও চালু রাখা হয়েছে।
- **প্যাকেজ আপডেট:** `composer update` চালানো হলো (major version বদলায়নি)। Laravel 12.64 থেকে 12.73, Livewire 4.3 থেকে 4.4, সাথে symfony, guzzle, commonmark। এখন `composer audit`-এ কোনো সতর্কবার্তা নেই। পুরনো lock ফাইল: `storage/backups/composer.lock.before-update-20261121`।
- **অটো-ব্যাকআপ:** নতুন কমান্ড `php artisan dms:backup --keep=30` ([BackupDatabase.php](app/Console/Commands/BackupDatabase.php))। এটা `VACUUM INTO` দিয়ে consistent কপি নেয়, তারপর ডাটাবেস আর `.env` zip করে `storage/backups/auto/`-এ রাখে। ৩০ দিনের পুরনো ব্যাকআপ নিজে থেকে মুছে যায়। Windows Task Scheduler-এর "DMS Daily Backup" task এটা প্রতিদিন রাত ২টায় SYSTEM অ্যাকাউন্টে চালায়, লগ যায় `storage/logs/backup.log`-এ।
- **Git:** লোকালে git চালু করে প্রথম commit করা হলো (branch `main`)। `.env`, ডাটাবেস, ব্যাকআপ, `vendor` আর `node_modules` বাদ রাখা হয়েছে। এখনো কোনো remote নেই।

### ২১ নভেম্বর ২০২৬ (তৃতীয় ধাপ): নতুন ফিচার
- **Stock Adjustment:** নতুন পেজ `/inventory/adjustments` ([StockAdjustments.php](app/Livewire/Inventory/StockAdjustments.php)) আর `InventoryService::adjustStock()`। স্টক যোগ করলে বেছে নেওয়া batch-এ বাড়ে, batch না দিলে নতুন `ADJ-…` batch তৈরি হয়। বাদ দিলে বেছে নেওয়া batch থেকে, না দিলে FIFO-তে কাটে। স্টক, batch আর লেজার (`type=adjustment`, চিহ্নসহ পরিমাণ) একসাথে আপডেট হয়।
- **Backup ও Restore:** নতুন [BackupService.php](app/Services/BackupService.php)। কমান্ড, ডাউনলোড আর restore তিনটাই এটা ব্যবহার করে। ডাউনলোড এখন JSON-এর বদলে zip (ডাটাবেস + `.env`)। Restore-এর ধাপ: integrity check, তারপর schema মেলানো (নতুন ভার্সনের ব্যাকআপ হলে আটকায়), তারপর safety backup, তারপর এক transaction-এ সব টেবিলের ডাটা কপি, শেষে foreign key check। `sessions`, `cache`, `jobs` আর `migrations` টেবিল restore হয় না। আপলোডের সীমা Livewire-এর ডিফল্ট ১২ MB।
- **Forgot password:** [PasswordResetController.php](app/Http/Controllers/PasswordResetController.php)। মেইল থাকলে Laravel-এর স্ট্যান্ডার্ড রিসেট লিংক যায়। `MAIL_MAILER=log` থাকলে Dealer আর Manager-দের কাছে অ্যাপের ভেতরে নোটিফিকেশন যায় (একই ইউজারের জন্য ৩০ মিনিটে একবার)। লগইনে প্রতি মিনিটে ১০টা চেষ্টা, রিসেটে ৫টা।
- **Retailer পোর্টাল:** `app/Livewire/Portal/*`, route `/portal/*`, পারমিশন `portal.access`। সব query `ForCurrentRetailer` trait দিয়ে লগইন করা রিটেইলারের নিজের ডাটায় সীমিত। অর্ডারের দাম সবসময় product master থেকে নেওয়া হয়। গুদাম হিসেবে central warehouse নেয়। রিটেইলার pending অর্ডার নিজে বাতিল করতে পারে। ইনভয়েস PDF: `DeliveryInvoiceController::printForRetailer()`।
- **Global search:** [GlobalSearch.php](app/Livewire/GlobalSearch.php), হেডারে লাইভ ড্রপডাউন আর মোবাইলে `/search`। পারমিশন অনুযায়ী Sales Orders, Retailers, Products, Companies, Representatives, Delivery Invoices, Purchase Orders, Returns আর Warranty খোঁজে। রিটেইলার শুধু নিজের অর্ডার পায়। Products পেজ এখন `?search=` বোঝে।
- **রোলের পারমিশন ঠিক করা হলো:** লাইভ ডাটাবেসে চারটা রোলেরই সব পারমিশন ছিল। আগে ব্যাকআপ নিয়ে PermissionSeeder আর RoleSeeder চালানো হলো। এখন Dealer ২৭টা, Manager ২৩টা, Sales Rep ৯টা আর Retailer ১টা পারমিশন পায়।
- **টেস্ট:** চার রোল দিয়ে HTTPS-এ access matrix যাচাই করা হলো। portal isolation (অন্যের অর্ডারে 404, তালিকা বা সার্চে দেখা যায় না) আর রিটেইলারের অর্ডার দেওয়া ও বাতিল করা rollback-সহ টেস্ট হলো। restore ডাটাবেসের কপিতে টেস্ট হলো (৫৫টা টেবিল, ৪০২টা রেকর্ড ফিরেছে, foreign key ঠিক)। পুরো forgot ও reset ফ্লো আর সব পেজের smoke test-ও করা হলো।

### ২১ নভেম্বর ২০২৬ (চতুর্থ ধাপ): Help, Audit, ডিসকাউন্ট, লোগো, Email ও SMS
- **Help:** [Help.php](app/Livewire/Help.php), route `/help`। বাংলায় ১৬টা অংশ, প্রতিটা অংশে পারমিশন বা রোল দেওয়া আছে, তাই ইউজার শুধু নিজের কাজের অংশ দেখে। পেজে সার্চ (Alpine) আছে। হেডারের ? বাটন আর More মেনু এখানে আনে।
- **Audit log:** [Auditable.php](app/Models/Concerns/Auditable.php) trait ২৫টা মডেলে (Sales, Purchase, Delivery, Collection, Returns, Warranty, Payroll, Master data, StockAdjustment, Retailer, Representative ইত্যাদি)। action হয় `<model>.created|updated|deleted`। update-এ শুধু বদলানো ফিল্ড থাকে (পুরনো ও নতুন মান)। ইউজার ছাড়া console থেকে চালালে (seeder, ব্যাকআপ) লগ হয় না। লগ লিখতে না পারলেও মূল কাজ থামে না। Audit Log পেজে module, action, ইউজার আর তারিখের ফিল্টার আছে, আর বদলগুলো "ফিল্ড: পুরনো → নতুন" আকারে দেখায়।
- **ডিসকাউন্ট:** Sales order পেজে approver অনুমোদনের আগে ৳ বা % দিতে পারেন। `SalesOrder::discountForDelivery()` প্রতিটা ডেলিভারিকে মূল্যের অনুপাতে ভাগ দেয়, আর শেষ ডেলিভারি বাকিটা নেয়। Sales invoice, delivery invoice, রিটেইলারের due আর লেজার সব জায়গায় ডিসকাউন্টের পরের মূল্য বসে। টেস্টে ১০% ডিসকাউন্ট দুই ডেলিভারিতে ৯.৬০ + ১২.৮০ = ২২.৪০ হয়ে ঠিক মিলেছে।
- **লোগো ও footer:** Company Profile-এ লোগো আপলোড (PNG বা JPG, ১ MB পর্যন্ত, `storage/app/public/company/`)। PDF-এ লোগো base64 হিসেবে যায়, সাথে ঠিকানা, ফোন আর invoice footer। partial: `resources/views/pdfs/partials/`।
- **Email ও SMS:** নতুন টেবিল `app_settings` (key/value; পাসওয়ার্ড আর API key APP_KEY দিয়ে encrypted) আর `sms_logs`। `MailSettings::apply()` boot-এ SMTP config বসায়। নোটিফিকেশনে `AlsoSendsEmail` trait আর `SafeMailChannel` আছে, তাই SMTP নষ্ট থাকলেও কাজ আটকায় না। `.local` ইমেইলে মেইল যায় না। ইউজার Notification Settings-এ "Also send by email" চালু বা বন্ধ করতে পারে। `SmsService` যেকোনো HTTP gateway-এর সাথে কাজ করে (`{api_key} {sender_id} {to} {message}`)। নম্বর 8801… বা 01… ফরম্যাটে বদলায়। SMS যায় অর্ডার approve, ডেলিভারি আর পেমেন্টে। ব্যর্থ হলেও কাজ থামে না, আর লগে লেখা থাকে।
- **টেস্ট:** audit entry, SMS (এই সার্ভারের `/dms/up`-কে নকল gateway বানিয়ে, API key লগে লুকানো), ভাঙা SMTP-তেও নোটিফিকেশন জমা হয়, লোগোসহ PDF, রোলভিত্তিক access আর সব পেজের smoke test করা হলো, সবই rollback-সহ।

### ২১ নভেম্বর ২০২৬ (পঞ্চম ধাপ): Test, Timezone, Route cache, MySQL প্রস্তুতি, বাংলা
- **Automated tests:** `tests/Feature/*`-এ ৪৫টা টেস্ট, ১৬৪টা assertion (Inventory FIFO ও adjustment, Numbering, পুরো বিক্রয় ফ্লো ডিসকাউন্ট ভাগসহ, পেমেন্ট, পোর্টাল isolation, রোলের access matrix, Backup ও Restore, Password reset, Audit, SMS)। চালাতে: `F:\Lovable\xampp\php\php.exe vendor\bin\phpunit`।
  - **নিরাপত্তা:** phpunit.xml জোর করে in-memory ডাটাবেস আর আলাদা config/route cache ব্যবহার করে। `tests/TestCase.php` ডাটাবেস `:memory:` না হলে migration চালানোর আগেই থেমে যায়, আর storage যায় অস্থায়ী ফোল্ডারে। আগে phpunit.xml-এ `:memory:` লাইন comment করা ছিল, তখন টেস্ট চালালে লাইভ ডাটাবেস মুছে যেত।
- **Timezone:** `APP_TIMEZONE=Asia/Dhaka`। migration `2026_11_21_000002` আগের সব সময়ের কলাম +৬ ঘণ্টা সরিয়েছে, আর রোলব্যাক করলে −৬ ঘণ্টা হয়। `NumberGeneratorService` এখন `now()` ব্যবহার করে।
- **Route cache:** লগইন পেজ এখন `/login`-এ। `/` হলো `AuthController@home` (ড্যাশবোর্ড বা লগইনে নেয়)। `public/.htaccess`-এর `RewriteRule ^$ %{REQUEST_URI}login [R=302,L]` খালি `/dms/`-কে Laravel পর্যন্ত পৌঁছানোর আগেই `/dms/login`-এ পাঠায়। কারণ: Laravel-এর `CompiledRouteCollection` শেষের `/` কেটে দেয়, আর তাতে subfolder-এর root `/dms/` ভুল base path পায়। এখন `php artisan route:cache` চালানো যায়, প্রতিবার route বদলালে আবার চালাতে হবে।
- **MySQL প্রস্তুতি:** `php artisan dms:copy-to-mysql --database=dms [--fresh]` ([CopyToMysql.php](app/Console/Commands/CopyToMysql.php))। এটা ডাটাবেস বানায়, migration চালায়, সব ডাটা কপি করে, আর প্রতিটা টেবিলের row গুনে মেলায়। পরীক্ষামূলক `dms_copy_test`-এ ৫৮টা টেবিলে ৩৭৩টা row ঠিকঠাক কপি হয়েছে, তারপর সেটা মুছে দেওয়া হয়েছে। raw SQL-গুলো যাচাই করা হয়েছে (`COALESCE`, `SUM`, `CASE`, `substr`, `instr`), সবই MySQL-এ চলে। **যাওয়ার ধাপ:** (১) `dms:backup` (২) `dms:copy-to-mysql --database=dms` (৩) `.env`-এ `DB_CONNECTION=mysql` আর `DB_*` (৪) `config:cache` (৫) Backup/Restore MySQL-এর জন্য বদলানো।
  - সতর্কতা: কমান্ড চালানোর সময় লাইভ SQLite-এর md5 বদলেছিল। ব্যাকআপের সাথে টেবিল ধরে মিলিয়ে দেখা গেছে শুধু `cache` টেবিল আলাদা, ব্যবসার কোনো ডাটা না।
- **বাংলা:** ৮৮টা view-এ ১৩৮৫টা লেখা স্ক্রিপ্ট দিয়ে `__()`-এ মোড়ানো হয়েছে, সাথে মেনু, টাইটেল, stat card আর স্ট্যাটাসের লেখা। `lang/bn.json`-এ ৯১৯টা অনুবাদ আর `lang/bn/validation.php`। `users.locale` কলাম, `SetLocale` middleware, `POST /locale/{bn|en}`, `<x-locale-switch>` (হেডার, Profile আর লগইন পেজে), Hind Siliguri ফন্ট। ডিফল্ট `APP_LOCALE=bn`, টেস্টে `en`। বিস্তারিত `ui.md` §৯-এ।

### ২১ নভেম্বর ২০২৬ (ষষ্ঠ ধাপ): বাকি সব কাজ
- **পোর্টালে ফেরত ও ওয়ারেন্টি:** `Portal/Returns` (`/portal/returns`) আর `Portal/Warranty` (`/portal/warranty`)। রিটেইলার শুধু নিজের ইনভয়েস করা পণ্য বাছতে পারে। ফেরতের পরিমাণ কেনা পরিমাণের বেশি হতে পারে না (আগের খোলা বা গৃহীত অনুরোধ বাদ দিয়ে)। দাম হয় শেষ ইনভয়েসের দাম, browser থেকে নেওয়া হয় না। অন্য রিটেইলারের ইনভয়েস দিলে ভুল দেখায়। এরপর ডিলার আগের মতোই approve বা receive করেন।
- **SMS-এর লেখা:** `SmsService::TEMPLATES` হলো ডিফল্ট লেখা, `SmsService::message($event, [...])` placeholder বসায় (`{retailer} {order} {invoice} {amount} {due}`)। বদলানো লেখা `app_settings`-এ `sms_template_<event>` key-তে থাকে। ডিফল্টের সমান হলে null রাখা হয়।
- **PDF:** `App\Services\PdfService::stream()` দিয়ে সব PDF তৈরি হয়। ভাষা ইংরেজি হলে dompdf, বাংলা হলে mPDF (dompdf বাংলা যুক্তবর্ণ জোড়া দিতে পারে না)। ফন্ট `resources/fonts/HindSiliguri-*.ttf` (OFL লাইসেন্স)। mPDF-এর temp ফোল্ডার `storage/framework/mpdf`। সব PDF শুধু table দিয়ে তৈরি, যাতে দুই ইঞ্জিনেই একই রকম দেখায়: `pdfs/layout`, `pdfs/partials/head`, `signatures`। dompdf-এ font subsetting চালু, ফলে ফাইল ~৮৮০ KB থেকে ~২৫ KB-এ নেমেছে।
  - নতুন: Sales Invoice (`sales.invoices.print`), Money Receipt (`collection.receipt`; এক পাতায় রিটেইলার আর অফিস কপি, কথায় টাকার অঙ্ক, পেমেন্টের পরের বকেয়া), পোর্টালে নিজের ইনভয়েস আর রিসিট।
  - রিপোর্টের PDF: `Reports\PdfController` রিপোর্টের CSV export চালিয়ে সেটাকেই টেবিল বানায়। ফলে PDF আর CSV সবসময় মেলে, আর রিপোর্টের logic এক জায়গাতেই থাকে। সংখ্যার কলামে যোগফল দেখায় (দাম, লক্ষ্য, % বাদে)। ৬টার বেশি কলাম হলে landscape।
  - কথায় টাকার অঙ্ক: `PdfService::amountInWords()`, কোটি-লক্ষ-হাজার পদ্ধতিতে, বাংলা আর ইংরেজি।
- **ডার্ক মোড:** Tailwind 4-এর রঙগুলো CSS variable, তাই `html.dark`-এর নিচে পুরো palette নতুন করে map করা হয় ([resources/css/dark.css](resources/css/dark.css), তৈরি করে [scripts/make-dark-css.php](scripts/make-dark-css.php))। এতে ১০০+ view না ছুঁয়েই সব পেজ ডার্ক হয়। যেগুলো আগে থেকেই গাঢ় (সাইডবার, gradient ব্যানার) সেগুলোতে `.dark-keep` থাকে, যা আসল রঙ ফিরিয়ে দেয়। পছন্দ থাকে browser-এর `localStorage` (`dms_theme`)-এ। পেজ আঁকার আগেই `<head>`-এর script class বসায়, তাই সাদা ঝলক দেখা যায় না। Chart.js-এর লেখা আর গ্রিডের রঙও বদলায়।
- **ঠিক করা বাগ:**
  - রিপোর্টের CSV লিংক `request()->query()` ব্যবহার করত, যা Livewire update-এর পরে ফাঁকা থাকে। তাই ফিল্টার বদলালে CSV-তে পুরনো ফিল্টার যেত। এখন ক্লিকের সময় পেজের URL থেকে ফিল্টার নেয়।
  - স্টাফের Warranty পেজে status-এর কোনো পাহারা ছিল না: বাতিল দাবিকেও request বানিয়ে "repaired" করা যেত। এখন শুধু pending → approved/rejected আর approved → replaced/repaired।
  - মোবাইলে টেবিলের লেবেল (`data-label`, ৩৪৬টা) ইংরেজি থেকে গিয়েছিল। এখন অনুবাদ হয়। লেজারের "Type" কলামও অনুবাদ হয়।
- **টেস্ট:** নতুন ২১টা, মোট ৬৬টা টেস্টে ৩৬২টা assertion।
  - `BusinessFlowsTest`: ক্রয়ের আংশিক ও পুরো গ্রহণ; purchase return শুধু send-এ স্টক কমায় আর দুবার কমায় না; ফেরত গ্রহণে restock আর রিটেইলার ক্রেডিট; company return; rejected অনুরোধ; ওয়ারেন্টির ধাপ; বেতনের হিসাব; ১৪টা রিপোর্ট পেজ আর CSV।
  - `PortalReturnsWarrantyTest`।
  - `PrintAndSmsTemplateTest`: দুই ভাষায় PDF, ১৪টা রিপোর্ট PDF, অন্যের ডকুমেন্টে 404, কথায় অঙ্ক, SMS-এর লেখা।
  - প্রতিবার আগে-পরে লাইভ ডাটাবেসের md5 এক ছিল।
- **হাতে যাচাই:** headless Chrome দিয়ে ডার্ক আর লাইট মোড, ডেস্কটপ আর মোবাইলে স্ক্রিনশট দেখা হয়েছে। প্রতিটা PDF দুই ভাষায় তৈরি করে দেখা হয়েছে। স্ক্রিনশটের জন্য বানানো অস্থায়ী session পরে মুছে ফেলা হয়েছে।

---

## জানা সমস্যা (Known issues)

| # | সমস্যা | প্রভাব / করণীয় |
|---|---|---|
| 1 | SSL সার্টিফিকেট self-signed | ব্রাউজার প্রথমবার সতর্কতা দেখায়। ডোমেইন হলে Let's Encrypt দিয়ে আসল সার্টিফিকেট নেওয়া যাবে |
| 2 | ব্যাকআপ একই ডিস্কে (F:) থাকে | ডিস্ক নষ্ট হলে ব্যাকআপও যাবে। অফসাইট কপি দরকার |
| 3 | ২১ নভেম্বরের আগে `today()` UTC-তে চলত | সন্ধ্যা ৬টা থেকে রাত ১২টার (বাংলাদেশ সময়) মধ্যে তৈরি হওয়া পুরনো রেকর্ডের তারিখ (`order_date` ইত্যাদি) এক দিন আগের দেখাতে পারে। সময়ের কলাম ঠিক করা হয়েছে, কিন্তু শুধু-তারিখের কলাম না |
| 4 | Backup/Restore শুধু SQLite-এ কাজ করে | MySQL-এ গেলে mysqldump-ভিত্তিক ব্যাকআপ লাগবে |
| 5 | লগইনে প্রতি মিনিটে ১০টা চেষ্টার সীমা (প্রতি IP) | অফিসের সবাই একই পাবলিক IP থেকে একসাথে লগইন করলে সীমায় পড়তে পারে। দরকার হলে `routes/web.php`-এ `throttle:10,1` বাড়ান |
| 6 | `IMPLEMENTATION_SUMMARY.md` ফাঁকা ফাইল | মুছে ফেলা যায় |
| 7 | PDF-এ তারিখ আর সংখ্যা ইংরেজি অঙ্কে (24 Jul 2026, 3,900.00) | ইচ্ছা করে, হিসাবের কাগজে ইংরেজি অঙ্ক সহজে পড়া যায়। বাংলা অঙ্ক চাইলে PdfService-এ বদলানো যায় |
| 8 | ডার্ক মোড প্রতিটা ব্রাউজারে আলাদা মনে থাকে (ইউজার অ্যাকাউন্টে না) | ইচ্ছা করে: একই ইউজার অফিসের পিসিতে লাইট আর মোবাইলে ডার্ক রাখতে পারে |

---

## সার্ভার (XAMPP) সেটআপ ও আপডেট

ঠিকানা: `http://localhost/dms`, `http://192.168.0.171/dms`, `http://160.25.9.132/dms`

এই মেশিনের PATH-এ PHP নেই, তাই XAMPP-এর PHP ব্যবহার করতে হবে: `F:\Lovable\xampp\php\php.exe`

কোড বদলানোর পর যা চালাতে হবে:

```bash
F:\Lovable\xampp\php\php.exe artisan migrate --force
npm run build                                   # CSS/JS বদলালে
F:\Lovable\xampp\php\php.exe artisan optimize:clear
F:\Lovable\xampp\php\php.exe artisan config:cache
F:\Lovable\xampp\php\php.exe artisan route:cache
F:\Lovable\xampp\php\php.exe artisan view:cache
F:\Lovable\xampp\php\php.exe vendor\bin\phpunit   # সব টেস্ট পাস হওয়া চাই (লাইভ ডাটাবেসে হাত দেয় না)
```

Apache কনফিগ বদলালে: `F:\Lovable\xampp\apache\bin\httpd.exe -t`, তারপর `httpd.exe -k restart -n Apache2.4`।

## নতুন মেশিনে লোকাল ডেভেলপমেন্ট

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed      # Permission, Role, Demo data, Yearly demo
composer run dev                # server + queue + logs + vite একসাথে
```

## নিয়ম ও কনভেনশন

- নতুন ডকুমেন্ট নম্বর বানাতে সবসময় `NumberGeneratorService::generate('<type>')` ব্যবহার করতে হবে।
- স্টক বাড়ানো বা কমানো শুধু `InventoryService` দিয়ে করতে হবে, যাতে batch, stock আর ledger একসাথে আপডেট হয়।
- রিটেইলারের due বা লেজারের হিসাব `PaymentAllocationService` দিয়ে করতে হবে।
- নতুন পেজ বানালে `routes/web.php`-এ ঠিক `can:` middleware দিতে হবে, আর `layouts/app.blade.php`-এর `$navGroups`-এ লিংক যোগ করতে হবে।
- মোবাইলে টেবিল ঠিকমতো দেখাতে `.rtable` ক্লাস আর `data-label` ব্যবহার করতে হবে।
- নতুন কাজ শেষ হলে এই ফাইলের টাইমলাইনে একটা এন্ট্রি যোগ করতে হবে।
