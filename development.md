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

### ২২ নভেম্বর ২০২৬: ERP ধাপ ০ (ভিত)
পরিকল্পনা আছে [plan.md](plan.md) §৮-এ। migration: `2026_11_22_000001_add_manufacturing_foundation`।
- **ইউনিট:**
  - `units.kind`: ওজন, আয়তন, সংখ্যা বা দৈর্ঘ্য।
  - `unit_conversions`: ১ from = factor × to।
  - [UnitConverter](app/Services/UnitConverter.php):
    - পণ্যের হিসাবে আগে পণ্যের নিজস্ব প্যাক (`product_units`) দেখে, তারপর সাধারণ রূপান্তর।
    - সাধারণ রূপান্তর দুই দিকেই চলে, আর একটা মাঝের ইউনিট পার হয়েও যেতে পারে (গ্রাম → কেজি → টন)।
    - মজুদ সবসময় পণ্যের মূল ইউনিটে থাকে।
  - Units পেজে রূপান্তর যোগ আর মোছা যায়। শুধু একই ধরনের ইউনিটের মধ্যে রূপান্তর চলে, আর উল্টো দিকের একই জোড়া আবার দেওয়া যায় না।
  - `StandardUnitsSeeder`: কেজি, গ্রাম, টন, লিটার, মিলি, পিস, বস্তা, প্যাকেট, বোতল, বক্স, কার্টন, আর তাদের রূপান্তর। বারবার চালালেও কোনো ক্ষতি নেই।
- **পণ্য:**
  - `type`: `raw_material`, `packaging`, `semi_finished`, `finished_good`, `trading`। আগের পণ্যগুলো `trading` হয়েছে।
  - `shelf_life_days`: কারখানায় তৈরি ব্যাচের সাধারণ মেয়াদ।
  - প্যাক (`product_units`) পণ্যের ফর্ম থেকে যোগ, বদল আর মোছা যায়।
  - বিক্রির অর্ডারে (স্টাফ আর পোর্টাল) শুধু `Product::sellable()`। সার্ভারেও যাচাই হয়।
  - ক্রয়ের অর্ডারে শুধু `Product::purchasable()`। validation-এও আছে।
- **কারখানা:**
  - `factories` টেবিল। Inventory → Factories পেজে কার্ড আকারে দেখায়, প্রতিটা কারখানার স্টোরসহ।
  - স্টোর থাকা অবস্থায় কারখানা মোছা যায় না।
  - `warehouses.factory_id` আর `purpose`: `general`, `raw_material`, `production`, `finished_goods`, `damage`।
- **অর্থবছর:** [FiscalYear](app/Support/FiscalYear.php) সাপোর্ট ক্লাস। Company Profile থেকে শুরুর মাস বাছা যায় (ডিফল্ট জুলাই)। লেবেল হয় "2026-27" ধরনের।
- **ব্যাকআপ দুই ডাটাবেসেই:**
  - ফরম্যাট আগের মতোই: zip-এর ভেতরে `database.sqlite` আর `env.txt`।
  - MySQL-এ ব্যাকআপ নিলে migration দিয়ে একটা ফাঁকা SQLite বানানো হয়। তারপর REPEATABLE READ transaction-এর ভেতরে সব টেবিল কপি হয়, ফলে কাজ চলার মধ্যেও ব্যাকআপ সামঞ্জস্যপূর্ণ থাকে।
  - Restore এখন PDO দিয়ে ব্যাকআপ ফাইল পড়ে চালু connection-এ row লেখে, আগের মতো SQLite-এর `ATTACH` লাগে না। তাই পুরনো SQLite ব্যাকআপ MySQL-এও restore হয়।
  - ভাঙা লিংক (foreign key) আছে কিনা, সেটা এখন ব্যাকআপ ফাইলেই আগে যাচাই হয়।
- **MySQL-এ টেস্ট:**
  - কনফিগ [phpunit.mysql.xml](phpunit.mysql.xml), ডাটাবেস `dms_test`। ইউজার আর পাসওয়ার্ড `.env` থেকে নেয়।
  - guard শুধু `_test` দিয়ে শেষ হওয়া MySQL ডাটাবেস মানে, লাইভ `dms` কখনো না।
  - দুই স্যুটের storage ফোল্ডার আলাদা।
  - চালানোর কমান্ড: `php vendor/bin/phpunit -c phpunit.mysql.xml`। Windows-এর MariaDB-তে স্কিমা বানাতে সময় লাগে, তাই পুরো স্যুটে ১৫–২০ মিনিট লাগে।
- **লোকাল অ্যাপ MySQL-এ সরানো হয়েছে:**
  1. প্রথমে SQLite ফাইলের কপি রাখা হয়েছে: `storage/backups/database-before-mysql.sqlite`।
  2. তারপর `dms:copy-to-mysql --database=dms --username=dms`। ৫৮টা টেবিলে ৩৮২টা row কপি হয়েছে, প্রতিটা টেবিলের গোনা মিলেছে।
  3. `.env`-এ `DB_CONNECTION=mysql`, ডাটাবেস `dms`, ইউজার `dms`। এই ইউজার শুধু `dms` আর `dms_test` ডাটাবেস দেখতে পায়, XAMPP-এর অন্য প্রজেক্টের ডাটাবেস না। পাসওয়ার্ড শুধু `.env`-এ আছে।
  4. শেষে `StandardUnitsSeeder` চালানো হয়েছে।
  - যাচাই: তিনটা ঠিকানাতেই ১০৪টা পেজ খোলা হয়েছে। MySQL থেকে নেওয়া ব্যাকআপ zip-এর SQLite-এর integrity check "ok" এসেছে। MySQL-এ পুরো স্যুট চালানো হয়েছে: ৬৫টা পাস, আর একটা টেস্ট নিজেই SQLite-এর `PRAGMA` ব্যবহার করত, সেটা ঠিক করার পর ব্যাকআপ আর ধাপ ০-এর ১১টা টেস্ট আবার চালিয়ে সব পাস।
  - SQLite-এ ফিরতে চাইলে: `.env`-এ `DB_CONNECTION=sqlite` করে `DB_*` লাইনগুলো মুছুন, তারপর `config:cache`। তবে SQLite ফাইলটা ২২ নভেম্বরের অবস্থায় থেমে আছে।
- **ঠিক করা বাগ:** পণ্যের তালিকায় সার্চের `orWhere` একটা গ্রুপের মধ্যে ছিল না, তাই সার্চ দিলে ক্যাটাগরি, ব্র্যান্ড আর কোম্পানির ফিল্টার কাজ করত না।

### ২২ নভেম্বর ২০২৬: ERP ধাপ ১ (ডিলার কাঠামো)
migration: `2026_11_22_000002_add_dealer_structure`, `2026_11_22_000003_remove_orphan_role_assignments`।
- **রোল:**
  - পুরনো "Dealer" (সব ক্ষমতা) রোলের নাম এখন **Admin**। migration-এ নাম বদলানো হয়েছে, তাই আগের ইউজার আর পারমিশন যেমন ছিল তেমনই আছে।
  - নতুন **Dealer** রোল মানে একজন ডিলারের লগইন, যার `users.dealer_id` সেট করা থাকে।
  - নতুন পারমিশন `dealers.view/manage`। বদলের পর `db:seed --class=PermissionSeeder` আর `--class=RoleSeeder` চালাতে হয়।
- **মালিকানা (tenant):**
  - `dealer_id` থাকে users, retailers, warehouses আর representatives টেবিলে (null মানে হেড অফিস)। বাকি সব রেকর্ডের মালিক ঠিক হয় তার গ্রাহক বা গুদাম দেখে, তাই ওগুলোতে আলাদা কলাম লাগে না।
  - [BelongsToTenant](app/Models/Concerns/BelongsToTenant.php) ২৫টা মডেলে global scope বসায়। প্রতিটা মডেলের `TENANT_PATH` বলে মালিক কোথায় খুঁজতে হবে: `dealer_id`, `retailer_id`, `warehouse_id`, `sales_order_id`, বা `representative_id` (বেতনের ক্ষেত্রে নিয়োগকর্তা দেখে)।
  - কে কী দেখবে ঠিক করে [Tenant](app/Support/Tenant.php)। ব্যবহারকারী না থাকলে (console, queue, seeder) বা `Tenant::bypass()`-এর ভেতরে কোনো সীমা থাকে না।
  - ডিলারের ইউজার যে গ্রাহক, গুদাম বা প্রতিনিধি তৈরি করে, তাতে নিজে থেকেই তার `dealer_id` বসে। ডিলারের প্রতিনিধির লগইনেও।
- **ডিলারের অ্যাকাউন্ট:**
  - হেড অফিসের খাতায় ডিলার একজন গ্রাহক: রিটেইলার টেবিলে একটা সারি, যেখানে `account_dealer_id` সেট করা আর `dealer_id` null।
  - ক্রেডিট লিমিট, বকেয়া, অর্ডার, ইনভয়েস আর আদায় আগের স্ক্রিনগুলো দিয়েই চলে।
  - [DealerService](app/Services/DealerService.php) এক transaction-এ ডিলার, অ্যাকাউন্ট, গুদাম আর (চাইলে) লগইন তৈরি করে।
- **পোর্টাল:**
  - ডিলারের লগইন `User::customerAccount()` দিয়ে নিজের অ্যাকাউন্ট পায়।
  - পোর্টালের সব পেজ নিজের অ্যাকাউন্টের মধ্যেই সীমিত, তাই সেখানে ডিলার-ফিল্টার বন্ধ থাকে। সাধারণ রুটে `tenant.off` middleware (route binding-এর আগে চলে), আর Livewire কম্পোনেন্টে `ForCurrentRetailer`-এর boot আর dehydrate।
- **মজুদের চলাচল** ([DealerStockService](app/Services/DealerStockService.php)):
  - হেড অফিস ডিলারকে ডেলিভারি দিলে একই ব্যাচ নম্বর আর মেয়াদসহ মাল ডিলারের প্রধান গুদামে ঢোকে। খরচ ধরা হয় ডিলার যে দামে কিনেছে (ডিসকাউন্টের পর)।
  - ডিলার মাল ফেরত দিলে ফেরত গ্রহণের সময় ডিলারের মজুদ থেকে FIFO অনুযায়ী কমে। মজুদে না থাকলে ফর্মে বার্তা দেখায়, কিছু বদলায় না।
- **নিয়ম:** বিক্রির অর্ডার আর ফেরত গ্রহণে গুদাম আর গ্রাহক একই মালিকের হতে হবে। পোর্টালের অর্ডার নিজে থেকে বিক্রেতার general গুদাম নেয়।
- **নোটিফিকেশন:** [Staff::approvers()](app/Support/Staff.php) রেকর্ডের মালিক অনুযায়ী পাঠায়: হেড অফিসের রেকর্ডে Admin আর Manager, ডিলারের রেকর্ডে সেই ডিলারের লগইন।
- **পেজ:**
  - Dealers (তালিকা আর বিস্তারিত: অ্যাকাউন্ট, ডিলারের নিজের বিক্রি, গুদাম ও মজুদ, স্টাফ বসানো, লগইন)।
  - Reports → Dealer Sales Report (পেজ, CSV, PDF; [DealerSalesReport](app/Services/Reports/DealerSalesReport.php))।
  - ডিলারের ড্যাশবোর্ডে কেনার দিকের কার্ড।
  - গ্রাহক তালিকায় ডিলার অ্যাকাউন্টে "Dealer" চিহ্ন। ডিলারের সাইডবারে ডিলারের নাম।
- **নিরাপত্তার বাগ ঠিক করা হয়েছে:**
  - পুরনো SQLite-এ মুছে যাওয়া ইউজার ৫-এর Manager রোলের সারি রয়ে গিয়েছিল। MySQL-এ নতুন ইউজার id ৫ পেতেই সে চুপচাপ Manager-এর ক্ষমতা পেয়ে যেত।
  - একটা migration এখন এমন অনাথ সারি মুছে দেয়।
  - নতুন ইউজার তৈরির চার জায়গাতেই এখন `syncRoles` ব্যবহার হয়।
- **লোকাল ডেমো:** ডিলার "Rahman Agro Traders" (D-001), লগইন `dealer@dms.local`।
- **টেস্ট (ধাপ ১ শেষে):** মোট ৮৮টা।
  - `DealerStructureTest` (৮টা): ডিলার তৈরি, ডাটা আলাদা থাকা, প্রাইমারি → সেকেন্ডারি বিক্রিতে ব্যাচ যাওয়া, পোর্টালে অর্ডার, ফেরত, রিপোর্ট, প্রতিনিধি বসানো আর নোটিফিকেশন, পুরনো রোলের সারি।
  - `AccessTest`-এ এখন পাঁচটা রোল আর ১৭টা পেজ।

### ২২ নভেম্বর ২০২৬: ERP ধাপ ২ (Accounts)
migration: `2026_11_22_000004_create_accounting_tables` (ডিফল্ট হিসাবের তালিকাও বসায়)।
- **মূল কাঠামো:**
  - [LedgerService](app/Services/Accounts/LedgerService.php) সব ভাউচার লেখে। ডেবিট আর ক্রেডিট সমান হতে হয়, গ্রুপ হিসাবে এন্ট্রি হয় না, `ar`/`ap` লাইনে পক্ষ লাগে, আর বন্ধ বছরে হাতে লেখা ভাউচার নেওয়া হয় না।
  - পোস্ট হওয়া ভাউচার কখনো বদলায় না, ভুল হলে `reverse()` (বিপরীত ভাউচার)।
  - খসড়া হিসাবে ধরা হয় না। অনুমোদনের পর ধরা হয়।
- **স্বয়ংক্রিয় এন্ট্রি** ([AutoPosting](app/Services/Accounts/AutoPosting.php)), প্রতিটা স্ক্রিনের transaction-এর ভেতরে:
  - বিক্রয় চালান: প্রাপ্য/বিক্রয়/বাট্টা, আর ব্যাচের দামে বিক্রীত পণ্যের ব্যয় বনাম মজুদ।
  - আদায়: নগদ/ব্যাংক/মোবাইল, পদ্ধতি অনুযায়ী।
  - মাল গ্রহণ: মজুদ বনাম কোম্পানির দেনা।
  - ক্রয় ফেরত।
  - বিক্রয় ফেরত: পাওনা কমে, আবার তাকে ওঠা মাল মজুদে ফেরে।
  - স্টক সমন্বয়: ক্ষতি বা বৃদ্ধি।
  - বেতন পরিশোধ: বেতন, কমিশন আর বোনাস খরচ; অগ্রিম কাটা যায়; বাকিটা নগদে।
  - কর্মচারীর অগ্রিম।
  - হেড অফিস যখন ডিলারকে বিক্রি করে, ডিলারের খাতায় উল্টো দিকটাও ওঠে: মজুদ বনাম Agro Tech-এর কাছে দেনা। ডিলারের পরিশোধ আর ফেরতেও তাই।
  - একই ঘটনা দুবার পোস্ট হয় না (source unique)।
- **পুরনো লেনদেন:** `php artisan accounts:backfill` এগুলো হিসাবে তোলে। ডেমো seed-এর শেষে `AccountingBackfillSeeder`-ও এটাই চালায়। লোকালে চালানোর পর রেওয়ামিল মিলেছে (১,৬৪,৪৪৫ = ১,৬৪,৪৪৫), আর খাতার প্রাপ্য (২২,৩৯৫) গ্রাহকদের মোট বকেয়ার সমান।
- **খাতা আলাদা:** প্রতি ব্যবসার আলাদা খাতা (`journal_entries.dealer_id`)। রিপোর্ট সবসময় ঠিক একটা খাতা থেকে আসে: ব্যবহারকারীর নিজের।
- **পেজ:** Accounts মেনু।
  - Vouchers (তালিকা/নতুন/বিস্তারিত/PDF)।
  - Expenses।
  - Supplier Payments (শুধু হেড অফিস)।
  - Chart of Accounts: গাছের মতো সাজানো, জেরসহ। ডিলার নিজের হিসাব যোগ করতে পারে।
  - Opening Balances: গ্রাহকের পাওনা বিক্রির দিকের বকেয়া আর খাতাতেও যায়।
  - Bank Reconciliation।
  - Year Closing।
  - রিপোর্ট: খতিয়ান, রেওয়ামিল, লাভ-ক্ষতি, উদ্বৃত্তপত্র, নগদ প্রবাহ, প্রাপ্য/প্রদেয় (FIFO বয়স)। [ReportData](app/Services/Accounts/ReportData.php) থেকে পেজ আর PDF একই সংখ্যা পায়।
- **পারমিশন:**
  - নতুন: `accounts.view/manage/approve`।
  - নতুন রোল **Accountant**।
  - Manager শুধু দেখতে পারেন। ডিলার নিজের খাতায় সব করতে পারে।
- **টেস্ট:** মোট ১০৪টা।
  - `AccountsTest` (৭টা): বিক্রি-আদায়ের পর রেওয়ামিল, লাভ-ক্ষতি আর উদ্বৃত্তপত্র মেলে; ভুল ভাউচার আটকায়; উল্টানো; বছর সমাপনী আর লক; ডিলারের আলাদা খাতা; FIFO বয়স; backfill দুবার চালালেও দ্বিগুণ হয় না।
  - `AccountsScreensTest` (৬টা)।
- **জেনে রাখুন:**
  - স্টক সমন্বয়ের মূল্য ধরা হয় ব্যাচের দামে, ব্যাচ না থাকলে পণ্যের ক্রয়মূল্যে।
  - ডিলারের কাছ থেকে ফেরত আসা মালের দাম ধরা হয় ফেরতের দামে।

### ২২ নভেম্বর ২০২৬: ERP ধাপ ৩ (HR)
migration: `2026_11_22_000005_create_hr_tables`। এটা ডিফল্ট ছুটির ধরন, শিফট আর বিভাগ বসায়, হেড অফিসের প্রতিনিধিদের কর্মচারী বানায়, আর হিসাবের তালিকায় gratuity আর overtime হিসাব যোগ করে।
- **কর্মচারী:** `App\Models\Hr\Employee`, অফিস, কারখানা বা মার্কেটিং।
  - মার্কেটিং কর্মী `representative_id` দিয়ে প্রতিনিধি প্রোফাইলের সাথে যুক্ত থাকেন।
  - নতুন হেড অফিস প্রতিনিধি নিজে থেকেই কর্মচারী হন (Representatives স্ক্রিন)।
- **হাজিরা** ([AttendanceService](app/Services/Hr/AttendanceService.php)):
  - শিফট শুরু + ছাড়ের পরে এলে দেরি।
  - শিফটের অর্ধেকের কম কাজ করলে অর্ধদিবস।
  - ওভারটাইম = শিফট শেষের পরের সময় (যারা ওভারটাইম পান)।
  - ইমপোর্ট দুই রকম: (কোড, তারিখ, আসা, যাওয়া) অথবা ZKTeco-র পাঞ্চ লগ (আইডি, তারিখ-সময়)। লগে প্রথম পাঞ্চ আসা, শেষটা যাওয়া। আইডি মেলানো হয় কর্মচারীর কোড বা `device_user_id` দিয়ে।
  - `monthSummary()`: যোগদান আর চাকরি ছাড়ার দিনের মধ্যে কর্মদিবস (সাপ্তাহিক ছুটি আর সরকারি ছুটি বাদে), উপস্থিতি, বেতনসহ বা বিনা বেতনে ছুটি, অনুপস্থিতি।
  - হাজিরা না রাখা কর্মী (যেমন মাঠকর্মী) ছুটি বাদে পুরো মাস উপস্থিত ধরা হন।
- **বেতন** ([PayrollService](app/Services/Hr/PayrollService.php)):
  - যত দিন চাকরি করেছেন তার আনুপাতিক বেতন।
  - অনুপস্থিতি আর বিনা বেতনে ছুটিতে প্রতি দিন মূল ÷ ৩০ কাটা।
  - ওভারটাইম প্রতি ঘণ্টা মূল ÷ ২০৮ × ২ (শ্রম আইন ধারা ১০৮)।
  - কমিশন ([CommissionService](app/Services/Hr/CommissionService.php), পুরনো প্রতিনিধি-বেতনের একই নিয়ম) আর বোনাস।
  - অগ্রিম পুরো কাটা, ঋণ কিস্তিতে, কিন্তু বেতনের বেশি কখনো না।
  - খসড়ায় বোনাস আর অন্যান্য কর্তন বদলানো যায়। "আবার হিসাব" করলে হাতে দেওয়া বোনাস আর কর্তন থেকে যায়।
  - অনুমোদন → পরিশোধ: অগ্রিম আর ঋণ আদায় হিসেবে চিহ্নিত হয়, আর একটা ভাউচার ওঠে (বেতন, ওভারটাইম, কমিশন, বোনাস বনাম কর্মচারীর অগ্রিম, অন্যান্য আয় আর নগদ/ব্যাংক)।
  - একই মাসে একটাই বেতন শিট হয়।
  - পুরনো প্রতিনিধি-বেতন এখন HR-এর কর্মচারী হওয়া প্রতিনিধিদের বাদ দেয়। ওই মাসে পুরনো বেতনে আগে টাকা পেয়ে থাকলে HR শিটেও বাদ পড়েন, তাই কেউ দুবার বেতন পান না।
- **চূড়ান্ত পাওনা** ([SettlementService](app/Services/Hr/SettlementService.php)):
  - শেষ মাসের কাজ করা দিনের বেতন, যদি সে মাসের শিট এখনো না হয়ে থাকে।
  - না নেওয়া অর্জিত ছুটি × মূল ÷ ৩০।
  - গ্র্যাচুইটি: প্রতি পূর্ণ বছরে ৩০ দিনের মূল বেতন।
  - বকেয়া অগ্রিম আর ঋণ বাদ যায়।
  - সেভ করলে কর্মচারীর অবস্থা আর চাকরি ছাড়ার তারিখ বদলায়। পরিশোধ করলে ভাউচার ওঠে।
- **পেজ:** HR মেনু (Employees, Attendance, Leave, Advances & Loans, Salary Sheets, HR Setup), শুধু হেড অফিস। PDF: পে-স্লিপ (একজন বা সবাই), বেতন শিট (স্বাক্ষরের ঘরসহ), চূড়ান্ত পাওনা।
- **পারমিশন:** `hr.view/manage/payroll`। নতুন রোল **HR Manager**। Accountant বেতন দিতে পারেন (`hr.payroll`), Manager শুধু দেখতে পারেন।
- **টেস্ট:** `HrTest` (৬টা), মোট ১১০টা।
  - হাজিরার নিয়ম আর দুই রকম ইমপোর্ট।
  - সেপ্টেম্বর ২০২৬-এর বেতন হাতে হিসাব করা সংখ্যার সাথে মিলিয়ে দেখা: ২৫ কর্মদিবস, ৯০০ টাকা অনুপস্থিতি কর্তন, ১৭৩.০৮ টাকা ওভারটাইম, অগ্রিম আর কিস্তি কাটা, মাঝ-মাসে যোগ দেওয়া কর্মীর অর্ধেক বেতন।
  - কমিশন, আর পুরনো বেতন থেকে বাদ পড়া।
  - ছুটি আর ঋণ।
  - গ্র্যাচুইটিসহ চূড়ান্ত পাওনা।
  - ডিলার HR-এ ঢুকতে পারে না।

### ২২ নভেম্বর ২০২৬: ERP ধাপ ৪ (Factory ও উৎপাদন)
migration: `2026_11_22_000006_create_manufacturing_tables`। এটা আগে ChartOfAccountsSeeder চালায়, ফলে নতুন হিসাব `1155 Work in Process` (`wip`) আর `5110 Production Labour & Overhead Applied` (`production_applied`) যোগ হয়।
- **ফর্মুলা** (`App\Models\Production\Bom`): সেভ করলে সবসময় নতুন ভার্সন, পুরনো ব্যাচ পুরনো ফর্মুলা রাখে। `requirements($output)` অপচয় %-সহ প্রয়োজন দেয়; `standardCost()` বর্তমান দামে (হেড অফিসের সর্বশেষ ব্যাচের খরচ, না থাকলে ক্রয়মূল্য)।
- **উৎপাদন** ([ProductionService](app/Services/Production/ProductionService.php)):
  - `create`: ফ্যাক্টরির কাঁচামাল আর তৈরি পণ্যের স্টোর লাগে (গুদামের `purpose`)। ব্যাচ নং `SKU-yymmdd-NN`, মেয়াদ = উৎপাদন তারিখ + শেলফ লাইফ।
  - `issue`: আগে মেয়াদ শেষ হবে এমন ব্যাচ আগে (মেয়াদ না থাকলে শেষে), প্রতিটি ব্যাচ আলাদা লাইনে `production_issues`-এ। স্টক কম পড়লে কিছুই ইস্যু হয় না। ভাউচার: Dr WIP / Cr Inventory।
  - `qc`: পাস/ফেল/পুনরায় কাজ। সর্বশেষ QC পাস না হলে ভালো পণ্য স্টকে ওঠে না (একই সেকেন্ডের দুটো QC-তে `id` দিয়ে সর্বশেষ ঠিক হয়)।
  - `complete`: ভালো পণ্য তৈরি পণ্যের স্টোরে নতুন ব্যাচ, খরচ = (কাঁচামাল + শ্রম + ওভারহেড) ÷ ভালো পরিমাণ (ব্যাচে ২ দশমিক পর্যন্ত)। বাতিল পণ্য ড্যামেজ স্টোরে `-REJ` ব্যাচ, খরচ ০। ভাউচার: Dr Inventory (ভালো পরিমাণ ০ হলে Stock Loss) / Cr WIP (কাঁচামাল) / Cr Production Applied (শ্রম + ওভারহেড)।
  - `cancel`: শুধু কাঁচামাল ইস্যু না হওয়া পরিকল্পিত ব্যাচ।
- **ট্রান্সফার** ([StockTransferService](app/Services/Production/StockTransferService.php)): একই মালিকের গুদামের মধ্যে, `InventoryService::moveBatch` দিয়ে, ব্যাচ নং/মেয়াদ/খরচ সাথে যায়। হিসাবের এন্ট্রি নেই। ডিলাররাও নিজেদের গুদামে ব্যবহার করতে পারে (`inventory.manage`)।
- **ব্যাচ ট্রেস** ([BatchTrace](app/Services/Production/BatchTrace.php)): টেন্যান্ট বাইপাস করে (রিকলের জন্য সব ডিলারের ডেলিভারিও দেখায়), তাই পেজটা শুধু হেড অফিসের।
- **কাঁচামাল পরিকল্পনা:** চলমান ব্যাচের বাকি প্রয়োজন + সামনে পরিকল্পিত উৎপাদন (ডিফল্ট ফর্মুলায়) − কাঁচামাল স্টোরের মজুদ − খোলা ক্রয় অর্ডারের বাকি = কিনতে হবে।
- **পেজ:** Production মেনু (Production Batches, Formulas, Material Planning, Batch Trace, Machines & Lines, Production Report), শুধু হেড অফিস; Inventory → Stock Transfers। PDF: ব্যাচ রেকর্ড।
- **পারমিশন:** `production.view/manage/qc`। নতুন রোল **Factory Manager** (মাস্টার ডাটা দেখা, ইনভেন্টরি, উৎপাদন, রিপোর্ট)। Manager আর Accountant শুধু দেখতে পারেন।
- **টেস্ট:** `ProductionTest` (৭টা), মোট ১২৪টা। ফর্মুলা ভার্সন, FIFO-মেয়াদ ইস্যু, QC বাধা, খরচ আর ভাউচারের সংখ্যা হাতে হিসাব করে মেলানো, ০ ভালো পরিমাণে ক্ষতি, ট্রান্সফারে ব্যাচ ঠিক থাকা, দুই দিকে ট্রেস, অন্য মালিকের গুদামে ট্রান্সফার আটকানো, Factory Manager-এর অ্যাক্সেস।

---

## জানা সমস্যা (Known issues)

| # | সমস্যা | প্রভাব / করণীয় |
|---|---|---|
| 1 | SSL সার্টিফিকেট self-signed | ব্রাউজার প্রথমবার সতর্কতা দেখায়। ডোমেইন হলে Let's Encrypt দিয়ে আসল সার্টিফিকেট নেওয়া যাবে |
| 2 | ব্যাকআপ একই ডিস্কে (F:) থাকে | ডিস্ক নষ্ট হলে ব্যাকআপও যাবে। অফসাইট কপি দরকার |
| 3 | ২১ নভেম্বরের আগে `today()` UTC-তে চলত | সন্ধ্যা ৬টা থেকে রাত ১২টার (বাংলাদেশ সময়) মধ্যে তৈরি হওয়া পুরনো রেকর্ডের তারিখ (`order_date` ইত্যাদি) এক দিন আগের দেখাতে পারে। সময়ের কলাম ঠিক করা হয়েছে, কিন্তু শুধু-তারিখের কলাম না |
| 4 | ~~Backup/Restore শুধু SQLite-এ কাজ করে~~ | ২২ নভেম্বর ২০২৬-এ ঠিক হয়েছে: দুই ডাটাবেসেই চলে, ফরম্যাট একই |
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

## লাইভ সার্ভার: https://dms.agatbd.com (সরানো হয়েছে)

> **২২ নভেম্বর ২০২৬:** ব্যবহারকারীর কথায় cPanel থেকে পুরো DMS সরানো হয়েছে: সাবডোমেইন, DNS রেকর্ড, `~/dms.agatbd.com`, docroot symlink আর DMS-এর cron লাইন। agatbd.com-এ হাত দেওয়া হয়নি। শেষ ব্যাকআপ (ডেমো ডাটাসহ) আর ডেমোর আগের খালি ব্যাকআপ রাখা আছে এই পিসির `storage/backups/live-final/`-এ (git-এ যায় না)। আবার চালু করতে হলে নিচের ধাপগুলোই প্রযোজ্য: প্রথমে সাবডোমেইন বানাতে হবে, তারপর clone, composer, `.env`, symlink আর cron।

একটা cPanel অ্যাকাউন্টে চলে (LiteSpeed, CloudLinux)। একই অ্যাকাউন্টে আরেকটা প্রজেক্টও আছে, agatbd.com (`~/public_html`)। ওটার কোনো ফাইলে, cron লাইনে বা `~/agatbd-deploy` ফোল্ডারে হাত দেওয়া যাবে না।

| কী | কোথায় |
|---|---|
| অ্যাপের কোড (git clone) | `~/dms.agatbd.com` |
| Web root | `~/public_html/dms.agatbd.com/public`। এটা আসলে `~/dms.agatbd.com/public`-এর symlink, কারণ cPanel শুধু `public_html`-এর ভেতরে web root দিতে দেয়। |
| PHP | `/opt/cpanel/ea-php83/root/usr/bin/php` (CLI-র ডিফল্ট `php` 8.2, ওটা ব্যবহার করবেন না) |
| ডাটাবেস | `~/dms.agatbd.com/database/database.sqlite` (SQLite 3.26, তাই ব্যাকআপ `VACUUM INTO` ছাড়া লক করে ফাইল কপি করে) |
| SSL | cPanel AutoSSL (Let's Encrypt), নিজে থেকে নবায়ন হয় |
| Cron | crontab-এ `# >>> dms.agatbd.com` ব্লক: scheduler, queue, আর প্রতিদিন রাত ২টায় `dms:backup --keep=30` |

সার্ভারে `public/.htaccess`-এর শেষে cPanel একটা `ea-php83` handler লাইন যোগ করেছে। এটা git-এ commit করবেন না, কারণ ওই লাইন XAMPP-এ PHP চলা বন্ধ করে দেয়। git pull করলে লাইনটা থেকে যায়।

সার্ভারে Node নেই, তাই `public/build` এখানে বানিয়ে পাঠাতে হয়। কোড আপডেটের নিয়ম:

```bash
# লোকালে
npm run build && git push
tar -czf build.tgz -C public build && scp build.tgz sabagatbd@169.40.116.146:dms-build.tgz

# সার্ভারে (ssh)
cd ~/dms.agatbd.com && P=/opt/cpanel/ea-php83/root/usr/bin/php
git pull
rm -rf public/build && tar -xzf ~/dms-build.tgz -C public && rm ~/dms-build.tgz
$P ~/agatbd-deploy/composer.phar install --no-dev --optimize-autoloader   # composer.lock বদলালে
$P artisan migrate --force
$P artisan optimize
```

**ডেমো ডাটা (২১ নভেম্বর ২০২৬):** লাইভে পরীক্ষার জন্য এক বছরের ডেমো ডাটা দেওয়া আছে, ডিসেম্বর ২০২৫ থেকে নভেম্বর ২০২৬। এটা এসেছে `DemoDataSeeder` → `YearlyDemoSeeder` → `YearlyExtrasSeeder` থেকে। ডেমো ইউজারদের ইমেইল `@dms.local` দিয়ে শেষ হয়। ডাটা দেওয়ার আগের খালি অবস্থার ব্যাকআপ আছে `storage/backups/auto/dms-before-demo-data.zip`-এ। আসল কাজ শুরুর আগে More → Backup & Restore থেকে ওটা restore করলে ডেমো ডাটা মুছে যাবে। রোজকার `dms:backup --keep=30` ৩০ দিনের পুরনো ব্যাকআপ মুছে দেয়, তাই এই ফাইলটাও ২১ ডিসেম্বর ২০২৬-এর পর থাকবে না। রাখতে চাইলে তার আগেই ডাউনলোড করে নিন।

crontab বদলাতে হলে প্রথমে `crontab -l > file` করে ফাইলটা এডিট করুন, তারপর `crontab file` চালান। `crontab -l | ... | crontab -` পাইপ ব্যবহার করবেন না। এই সার্ভারে একবার এভাবে agatbd.com-এর cron লাইন মুছে গিয়েছিল।

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
