# DMS — প্রজেক্ট প্ল্যান

**Dealer Management System (DMS)**: একজন ডিস্ট্রিবিউটর বা ডিলারের পুরো ব্যবসা চালানোর ওয়েব অ্যাপ। কোম্পানি থেকে মাল কেনা, গুদামে স্টক রাখা, রিটেইলারদের কাছে বিক্রি ও ডেলিভারি, টাকা আদায়, রিটার্ন/ওয়ারেন্টি, সেলস রিপ্রেজেন্টেটিভদের বেতন-কমিশন আর রিপোর্ট সবকিছু এক জায়গায় হয়।

> সর্বশেষ আপডেট: ২১ নভেম্বর ২০২৬

---

## ১. টেক স্ট্যাক

| স্তর | প্রযুক্তি |
|---|---|
| Backend | PHP 8.2+, Laravel 12 |
| UI / Reactivity | Livewire 4, Alpine.js (Livewire-এর সাথে আসে) |
| CSS | Tailwind CSS 4 (Vite plugin) |
| Chart | Chart.js 4 |
| Build | Vite 6, laravel-vite-plugin |
| Role/Permission | spatie/laravel-permission 6 |
| PDF | barryvdh/laravel-dompdf (ইংরেজি PDF), mpdf/mpdf + Hind Siliguri ফন্ট (বাংলা PDF); দুটোই `App\Services\PdfService` দিয়ে |
| Database | MySQL/MariaDB (XAMPP-এর MariaDB 10.4, ডাটাবেস `dms`, ইউজার `dms`; ২২ নভেম্বর ২০২৬ থেকে)। SQLite-এও চলে, টেস্ট দুটোতেই চলে |
| Mobile | PWA (`public/manifest.json`, অ্যাপ আইকন) |

## ২. ইউজার রোল

| রোল | কী করতে পারে |
|---|---|
| **Admin** | Agro Tech হেড অফিস, সবকিছু করতে পারে (`Gate::before` দিয়ে সব পারমিশন পায়)। ২২ নভেম্বর ২০২৬-এর আগে এই রোলের নাম ছিল "Dealer" |
| **Manager** | হেড অফিস। Payroll ও Settings বাদে সব মডিউল, সাথে Sales approve আর Dealers |
| **Dealer** | একজন ডিলারের লগইন (`users.dealer_id`)। নিজের প্যানেলে শুধু নিজের গ্রাহক, গুদাম, স্টাফ, বিক্রি, ডেলিভারি, আদায়, ফেরত আর রিপোর্ট। সাথে পোর্টাল থেকে Agro Tech-এ অর্ডার আর নিজের হিসাব (`portal.access`) |
| **Sales Representative** | রিটেইলার দেখা, Sales order তৈরি, Delivery, Collection, Return request |
| **Retailer** | শুধু নিজের পোর্টাল: ড্যাশবোর্ড, নিজের অর্ডার দেওয়া ও দেখা, ইনভয়েস PDF, হিসাব-বিবরণী (`portal.access`) |

পারমিশন তালিকা: `master-data.*`, `purchase.*`, `inventory.*`, `retailers.*`, `representatives.*`, `sales.view/create/approve`, `delivery.*`, `collection.*`, `returns.view/create/manage`, `warranty.*`, `payroll.*`, `reports.view`, `dealers.view/manage`, `settings.manage`, `portal.access`।

**কে কার ডাটা দেখে** ([Tenant](app/Support/Tenant.php), [BelongsToTenant](app/Models/Concerns/BelongsToTenant.php)):
- হেড অফিসের লগইন দেখে শুধু হেড অফিসের ডাটা।
- ডিলারের লগইন দেখে শুধু নিজের ডাটা।
- কোনো ডিলারের কাছে বসানো হেড অফিসের প্রতিনিধি দেখে হেড অফিস আর ওই ডিলারদের ডাটা।
- রিটেইলারের লগইন শুধু নিজের পোর্টাল দেখে।
- কিছু পেজ শুধু হেড অফিসের, যেমন কারখানা, এলাকা, ক্রয়, কোম্পানি আর বেতনের রিপোর্ট। এগুলো `head.office` middleware দিয়ে বন্ধ।

> ২১ নভেম্বর ২০২৬ পর্যন্ত লাইভ ডাটাবেসে চারটা রোলেরই সব পারমিশন ছিল, কারণ RoleSeeder বদলানোর পর আর চালানো হয়নি। এখন seeder অনুযায়ী ঠিক করা হয়েছে। রোলের পারমিশন বদলালে `php artisan db:seed --class=RoleSeeder --force` আর `php artisan permission:cache-reset` চালাতে হবে।

## ৩. মডিউল ও অবস্থা

| # | মডিউল | মূল ফিচার | অবস্থা |
|---|---|---|---|
| 1 | Auth | লগইন, লগআউট, লগইন হিস্টোরি, Forgot password (মেইল থাকলে ইমেইলে লিংক, না থাকলে অ্যাডমিনকে নোটিফিকেশন) | ✅ সম্পন্ন |
| 2 | Dashboard | Dealer / Representative / Retailer আলাদা ড্যাশবোর্ড | ✅ সম্পন্ন |
| 3 | Master Data | Company, Brand, Category (parent-child), Unit, Product, প্রাইস হিস্টোরি, CSV export | ✅ সম্পন্ন |
| 4 | Inventory | Warehouse (central/branch), Area, Batch, Stock, Stock ledger, Adjustment | ✅ সম্পন্ন |
| 5 | Purchase | Purchase Order → Goods Receipt (batch + expiry) → Purchase Return | ✅ সম্পন্ন |
| 6 | Sales | Sales Order, credit limit চেক (`credit_hold`), approve/cancel | ✅ সম্পন্ন |
| 7 | Delivery | Delivery note (আংশিক ডেলিভারিসহ), FIFO-তে স্টক কাটা | ✅ সম্পন্ন |
| 8 | Delivery Invoice | প্রতিটা ডেলিভারিতে নিজে থেকে ইনভয়েস তৈরি, PDF প্রিন্ট, ব্যাচ প্রিন্ট | ✅ সম্পন্ন (২১ নভেম্বর ২০২৬) |
| 9 | Collection | রিটেইলারের পেমেন্ট আদায়, ইনভয়েসে অ্যালোকেশন, রিটেইলার লেজার | ✅ সম্পন্ন |
| 10 | Returns | Return request → approve → receive (restock/damage/company return) → Company return | ✅ সম্পন্ন |
| 11 | Warranty | Warranty policy, claim, স্ট্যাটাস ট্র্যাকিং | ✅ সম্পন্ন |
| 12 | Representatives | প্রোফাইল, টার্গেট, পারফরম্যান্স | ✅ সম্পন্ন |
| 13 | Payroll | বেস স্যালারি + কমিশন + বোনাস − অ্যাডভান্স = নেট বেতন | ✅ সম্পন্ন |
| 14 | Reports | ১৪টা রিপোর্ট (Sales, Purchase, Stock, Expiry, Collection, Return, Warranty, Representative, Retailer, Company, Area, Product, Payroll, Profit & Loss), CSV export | ✅ সম্পন্ন |
| 15 | Notifications | Low stock, pending sales order, return, warranty-র নোটিফিকেশন, ইউজার প্রেফারেন্স | ✅ সম্পন্ন |
| 16 | Settings | Company profile, Numbering, Warranty policy, Users, Login history, Audit log | ✅ সম্পন্ন |
| 17 | More | About, Backup ডাউনলোড, সার্ভারের ব্যাকআপের তালিকা, Restore (পাসওয়ার্ড আর `RESTORE` লিখে নিশ্চিত করতে হয়, আগে safety backup নেয়) | ✅ সম্পন্ন |
| 18 | Global search | হেডারে লাইভ সার্চ (মোবাইলে `/search`), পারমিশন অনুযায়ী ৯ ধরনের রেকর্ড | ✅ সম্পন্ন |
| 19 | Stock Adjustment | নষ্ট, হারানো, গণনার ভুল বা পাওয়া স্টক ঠিক করা, batch বা FIFO অনুযায়ী, লেজারে লেখা হয় | ✅ সম্পন্ন |
| 20 | Retailer Portal | `/portal`: নিজের অর্ডার দেওয়া, দেখা আর বাতিল করা, ইনভয়েস PDF, হিসাব-বিবরণী (ইনভয়েস আর রিসিট প্রিন্টসহ), ফেরতের অনুরোধ আর ওয়ারেন্টি দাবি | ✅ সম্পন্ন |
| 21 | Help | `/help`: বাংলায় ব্যবহারের নির্দেশিকা, রোল অনুযায়ী অংশ দেখায়, সার্চ করা যায় | ✅ সম্পন্ন |
| 22 | Audit log | ২৫টা মূল মডেলের তৈরি, বদল আর মোছা নিজে থেকে লগ হয় (কোন ফিল্ড কী থেকে কী), module, ইউজার আর তারিখ দিয়ে ফিল্টার | ✅ সম্পন্ন |
| 23 | Discount | approve-এর আগে অর্ডারে ৳ বা % ডিসকাউন্ট, ডেলিভারিগুলোতে অনুপাতে ভাগ হয়, ইনভয়েস আর লেজারে ডিসকাউন্টের পরের মূল্য | ✅ সম্পন্ন |
| 24 | Email ও SMS | Settings → Email & SMS: SMTP আর যেকোনো HTTP SMS gateway, test বাটন, SMS লগ, SMS-এর লেখা বদলানো যায় (বাংলাও); রিটেইলার approve, ডেলিভারি আর পেমেন্টে SMS পায়; স্টাফ নোটিফিকেশন ইমেইলেও পায় | ✅ সম্পন্ন (SMTP আর gateway-এর তথ্য দেওয়া বাকি) |
| 25 | PDF | Delivery Invoice, Sales Invoice, Money Receipt (দুই কপি, কথায় টাকার অঙ্ক), আর ১৪টা রিপোর্টের PDF; লোগো, ঠিকানা আর footer; ইউজারের ভাষা অনুযায়ী বাংলা বা ইংরেজি | ✅ সম্পন্ন |
| 26 | Automated tests | ৮৮টা Feature test (SQLite আর MySQL দুটোতেই চলে) (বিক্রয়, ক্রয়, ফেরত, ওয়ারেন্টি, বেতন, রিপোর্ট, পোর্টাল, PDF, ব্যাকআপ, পারমিশন…) | ✅ সম্পন্ন |
| 27 | বাংলা / English | পুরো UI, ভুল-বার্তা আর PDF; প্রত্যেক ইউজারের নিজের ভাষা | ✅ সম্পন্ন |
| 28 | ডার্ক মোড | হেডারের চাঁদ/সূর্য বোতাম; প্রতিটা ডিভাইসে আলাদা মনে রাখে, প্রথমবার ডিভাইসের সেটিং মেনে চলে | ✅ সম্পন্ন |
| 29 | ERP ধাপ ০: ভিত | ইউনিটের ধরন আর রূপান্তর, পণ্যের ধরন/মেয়াদ/প্যাক, কারখানা, গুদামের কাজ, অর্থবছর, MySQL, দুই ডাটাবেসে ব্যাকআপ | ✅ সম্পন্ন (২২ নভেম্বর ২০২৬) |
| 30 | ERP ধাপ ১: ডিলার কাঠামো | Dealers পেজ (অ্যাকাউন্ট, গুদাম, লগইন), ডিলার প্যানেল আর ডাটা আলাদা রাখা, হেড অফিস → ডিলার বিক্রিতে একই ব্যাচ ডিলারের গুদামে, ডিলারের ফেরতে ডিলারের মজুদ কমা, Agro Tech-এর প্রতিনিধি ডিলারের কাছে বসানো, নোটিফিকেশন মালিকের কাছে, Dealer Sales Report (প্রাইমারি/সেকেন্ডারি) | ✅ সম্পন্ন (২২ নভেম্বর ২০২৬) |

## ৪. মূল ব্যবসায়িক ফ্লো

```
Purchase:  Purchase Order ──► Goods Receipt ──► Batch + Stock বাড়ে (stock_ledger: in)
                                   └──► Purchase Return ──► Stock কমে

Sales:     Sales Order ──► (credit limit পার হলে credit_hold) ──► Approve
               ──► Delivery Note (আংশিক হতে পারে, FIFO batch থেকে স্টক কাটে)
               ──► Delivery Invoice ──► PDF প্রিন্ট
               ──► Sales Invoice ──► Retailer due বাড়ে (retailer_ledger: debit)

Collection: Payment ──► পুরনো ইনভয়েস থেকে অ্যালোকেট ──► due কমে (retailer_ledger: credit)

Return:    Return Request ──► Approve ──► Receive ──► restock / damage / company_return
               └──► Company Return (কোম্পানিকে ফেরত)

Payroll:   Base salary + Commission + Bonus − Advance − Other = Net salary ──► Paid
```

## ৫. ডকুমেন্ট নম্বরিং

ফরম্যাট `PREFIX-YYYY-NNNNNN`, যেমন `SO-2026-000123`। প্রতি বছর নম্বর নতুন করে শুরু হয় (`numbering_sequences` টেবিল)।

| Prefix | ডকুমেন্ট |
|---|---|
| SO | Sales Order |
| INV | Sales Invoice |
| DEL | Delivery Note |
| DI | Delivery Invoice |
| PO | Purchase Order |
| GRN | Goods Receipt |
| PRT | Purchase Return |
| COL | Collection |
| RET | Return Request |
| RCV | Return Receive |
| CRT | Company Return |
| WAR | Warranty Claim |
| PAY | Payroll Entry |

## ৬. পরবর্তী কাজ (অগ্রাধিকার অনুযায়ী)

২১ নভেম্বর ২০২৬-এ শেষ হয়েছে: পাসওয়ার্ড বদলানো, HTTPS (self-signed), প্যাকেজের নিরাপত্তা আপডেট, প্রতিদিনের অটো-ব্যাকআপ, লোকাল git।

২১ নভেম্বর ২০২৬-এ আরও শেষ হয়েছে: Stock Adjustment, Backup Restore, Forgot password, Retailer পোর্টাল, Global search, আর রোলের পারমিশন ঠিক করা।

২১ নভেম্বর ২০২৬-এ আরও শেষ হয়েছে: Help পেজ, Audit log বাড়ানো, PDF-এ লোগো আর footer, ডিসকাউন্ট, Email ও SMS।

২১ নভেম্বর ২০২৬-এ আরও শেষ হয়েছে: ৪৫টা automated test, timezone Asia/Dhaka, route cache চালু, MySQL-এ যাওয়ার কমান্ড (`dms:copy-to-mysql`), আর পুরো অ্যাপ বাংলায় (ভাষা বদলানোর সুইচসহ)।

২১ নভেম্বর ২০২৬-এ আরও শেষ হয়েছে (ষষ্ঠ ধাপ): পোর্টালে ফেরত আর ওয়ারেন্টি দাবি, SMS-এর লেখা বদলানো, টেস্ট বাড়িয়ে ৬৬টা, ডার্ক মোড, রিপোর্টের PDF, Sales Invoice আর মানি রিসিট প্রিন্ট, বাংলা PDF। কোডে করার মতো সব কাজ শেষ।

**যা বাকি, সবই ইউজারের তথ্য বা সিদ্ধান্তের অপেক্ষায়**
1. SMTP-এর তথ্য (যেমন Gmail app password) আর SMS gateway-এর URL ও API key → Settings → Email & SMS-এ বসিয়ে Send test।
2. Company Profile-এ কোম্পানির নাম, ঠিকানা, লোগো আর invoice footer (PDF-এ এখন "DMS" দেখায়)।
3. GitHub-এ push করা (রিপোর লিংক পেলে)।
4. অফসাইট ব্যাকআপ: কোথায় রাখা হবে (Google Drive, অন্য পিসি, USB…) জানালে ব্যবস্থা করা যাবে। এখন ব্যাকআপ শুধু এই সার্ভারে থাকে।
5. ডোমেইন পেলে আসল SSL সার্টিফিকেট (এখন self-signed, ব্রাউজার সতর্কবার্তা দেখায়)।
6. ~~MySQL-এ যাওয়া~~: ২২ নভেম্বর ২০২৬-এ সম্পন্ন।

## ৭. সার্ভার

XAMPP-এর Apache-এ `/dms` alias দিয়ে চলে। http আর https দুটোই চালু:
- https://localhost/dms
- https://192.168.0.171/dms (লোকাল নেটওয়ার্ক)
- https://160.25.9.132/dms (পাবলিক IP)

বিস্তারিত `development.md`-এ আছে।

## ৮. সম্প্রসারণ: Agro Tech ERP (পরিকল্পনা, ২২ নভেম্বর ২০২৬)

### সিদ্ধান্ত (ইউজারের উত্তর)

| বিষয় | সিদ্ধান্ত |
|---|---|
| ব্যবসা | Agro Tech নিজে পণ্য বানায় (সার, বীজ, কীটনাশক, পটিং মিক্স)। নিজে সরাসরি বিক্রি করে, আবার ডিলারকেও মাল দেয়। |
| ডিলার | পুরো ডিলার প্যানেল থাকবে: Agro Tech-এর কাছে অর্ডার দেওয়া, নিজের মজুদ, মার্কেটিংয়ের লোক দিয়ে রিটেইলার বা কৃষকের কাছে বিক্রি, আদায় আর নিজের হিসাব। Agro Tech দুই ধরনের বিক্রিই দেখবে: নিজের বিক্রি (primary) আর ডিলারের বিক্রি (secondary)। |
| মার্কেটিংয়ের লোক | দুই ধরনের থাকবে। Agro Tech-এর কর্মচারী (বেতন Agro Tech-এর HR থেকে, এক বা একাধিক ডিলারের এলাকায় বসানো) আর ডিলারের নিজের লোক (শুধু ডিলারের ইউজার)। |
| উৎপাদন | মেশানো/প্রসেস ধরনের: কেজি বা লিটারে মিশ্রণ, তারপর প্যাকেটে ভরা। |
| কারখানা ও গুদাম | একাধিক কারখানা, একাধিক গুদাম। |
| VAT | মূসক চালান থাকবে, Settings থেকে চালু বা বন্ধ করা যাবে। |
| হাজিরা | এখন হাতে এন্ট্রি। পরে ZKTeco মেশিন যোগ করা যাবে, তাই ইমপোর্টের জায়গা রাখা হবে। শ্রমিকদের মজুরি মাসিক। |
| হিসাব | অর্থবছর জুলাই–জুন। Opening balance (ক্যাশ, ব্যাংক, পাওনা, দেনা) অ্যাডমিন নিজে দেবেন। |
| ডাটাবেস | MySQL (XAMPP-এর MariaDB)। |

### ধাপগুলো

প্রতিটা ধাপ শেষ হলে টেস্ট, ডেমো ডাটা আর বাংলা অনুবাদসহ ইউজারকে দেখানো হবে, তারপর পরের ধাপ শুরু হবে।

| ধাপ | নাম | মূল কাজ |
|---|---|---|
| ০ | ভিত | MySQL-এ যাওয়া। পণ্যের ধরন (কাঁচামাল, প্যাকেজিং, আধা-তৈরি, তৈরি)। ইউনিট রূপান্তর (বস্তা↔কেজি, লিটার↔মিলি)। কারখানা, আর গুদামের ধরন (কাঁচামাল, উৎপাদন ফ্লোর, তৈরি পণ্য, ড্যামেজ)। অর্থবছরের সেটিং। নতুন রোলের কাঠামো। |
| ১ | ডিলার কাঠামো | রোল নতুন করে সাজানো: হেড অফিসের Admin (সব ক্ষমতা) আর Dealer (নিজের প্যানেল)। ডিলার রেকর্ড। এখনকার রিটেইলার, প্রতিনিধি, গুদাম, অর্ডার, ডেলিভারি আর আদায় ডিলার অনুযায়ী আলাদা করা (প্রত্যেকে শুধু নিজেরটা দেখবে)। Agro Tech → ডিলার বিক্রি (primary)। সরাসরি বিক্রি। ডিলার → রিটেইলার বিক্রি (secondary)। মার্কেটিংয়ের লোকের দুই ধরন। প্রাইমারি আর সেকেন্ডারি বিক্রির রিপোর্ট। |
| ২ | Accounts | হিসাবের খাতা (Chart of Accounts)। Journal, Payment, Receipt আর Contra ভাউচার, অনুমোদনসহ। প্রতিটা লেনদেন নিজে থেকে হিসাবে লেখা হবে। সাপ্লায়ারের দেনা আর পেমেন্ট। খরচ। ক্যাশ ও ব্যাংক, ব্যাংক রিকনসিলিয়েশন। Opening balance। General Ledger, Trial Balance, P&L, Balance Sheet, Cash Flow, পাওনা আর দেনার বয়স অনুযায়ী তালিকা। অর্থবছর বন্ধ করা। ডিলারের নিজের সহজ হিসাব। |
| ৩ | HR | কর্মচারী (অফিস, কারখানা, মার্কেটিং), বিভাগ আর পদবি। শিফট, হাজিরা (হাতে বা CSV; ZKTeco-র জন্য জায়গা রাখা), ছুটি। বেতনের কাঠামো, ওভারটাইম, অগ্রিম আর লোন। মাসিক বেতন, পে-স্লিপ, চূড়ান্ত হিসাব। মার্কেটিংয়ের লোকের টার্গেট আর কমিশন। বেতন হিসাবে উঠে যাবে। |
| ৪ | Factory ও Manufacturing | কারখানা, লাইন, মেশিন। ফর্মুলা (BOM/Recipe), কেজি বা লিটারে, yield আর স্বাভাবিক অপচয়সহ, ভার্সন রেখে। উৎপাদনের পরিকল্পনা আর কাঁচামালের চাহিদা। প্রোডাকশন অর্ডার: রিকুইজিশন → ব্যাচ ধরে কাঁচামাল দেওয়া → মিশ্রণ (WIP) → QC → প্যাকেজিং → তৈরি পণ্যের ব্যাচ (উৎপাদনের তারিখ আর মেয়াদসহ)। কোন কাঁচামালের ব্যাচ কোন তৈরি ব্যাচে গেছে তার পুরো খোঁজ, বাজার থেকে তুলে নেওয়ার রিপোর্টসহ। খরচ (কাঁচামাল + শ্রম + অন্যান্য)। কারখানা থেকে গুদামে স্থানান্তর। |
| ৫ | Damage ও Repair | ড্যামেজ রেজিস্টার (গুদাম, ফেরত, QC, পরিবহন), অনুমোদন আর ড্যামেজ স্টোর। সিদ্ধান্ত: মেরামত, আবার প্রসেস করা, কম দামে বিক্রি বা নষ্ট করা। মেরামতের কাজ (নিজেরা বা বাইরে), খরচসহ। মেশিন রক্ষণাবেক্ষণ। সব হিসাবে উঠবে। |
| ৬ | শেষ কাজ | মূসক ৬.৩ চালান (চালু/বন্ধের সুইচসহ)। ZKTeco সংযোগের জায়গা। কারখানা, হিসাব, HR আর ডিলারের জন্য ড্যাশবোর্ড। এক বছরের ডেমো ডাটা। Help আর ডকুমেন্টেশন। |

**ধাপ ১ কেন ধাপ ২-এর আগে:** হিসাবে কে বিক্রেতা আর কে ক্রেতা (হেড অফিস, ডিলার, রিটেইলার), সেটা আগে ঠিক না হলে হিসাবের এন্ট্রি পরে আবার বদলাতে হবে।
