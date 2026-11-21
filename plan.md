# DMS — প্রজেক্ট প্ল্যান

**Dealer Management System (DMS)**: একজন ডিস্ট্রিবিউটর বা ডিলারের পুরো ব্যবসা চালানোর ওয়েব অ্যাপ। কোম্পানি থেকে মাল কেনা, গুদামে স্টক রাখা, রিটেইলারদের কাছে বিক্রি ও ডেলিভারি, টাকা আদায়, রিটার্ন/ওয়ারেন্টি, সেলস রিপ্রেজেন্টেটিভদের বেতন-কমিশন আর রিপোর্ট সবকিছু এক জায়গায় হয়।

> সর্বশেষ আপডেট: ২০ নভেম্বর ২০২৬

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
| PDF | barryvdh/laravel-dompdf (কোডে ব্যবহার আছে, **কিন্তু এখনো install করা হয়নি**, development.md দেখুন) |
| Database | SQLite (`database/database.sqlite`), MySQL-এও চলবে |
| Mobile | PWA (`public/manifest.json`, অ্যাপ আইকন) |

## ২. ইউজার রোল

| রোল | কী করতে পারে |
|---|---|
| **Dealer** | সবকিছু (`Gate::before` দিয়ে সব পারমিশন পায়) |
| **Manager** | Payroll ও Settings বাদে সব মডিউল, সাথে Sales approve |
| **Sales Representative** | রিটেইলার দেখা, Sales order তৈরি, Delivery, Collection, Return request |
| **Retailer** | শুধু নিজের পোর্টাল: ড্যাশবোর্ড, নিজের অর্ডার দেওয়া ও দেখা, ইনভয়েস PDF, হিসাব-বিবরণী (`portal.access`) |

পারমিশন তালিকা: `master-data.*`, `purchase.*`, `inventory.*`, `retailers.*`, `representatives.*`, `sales.view/create/approve`, `delivery.*`, `collection.*`, `returns.view/create/manage`, `warranty.*`, `payroll.*`, `reports.view`, `settings.manage`, `portal.access`।

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
| 20 | Retailer Portal | `/portal`: নিজের অর্ডার দেওয়া, দেখা আর বাতিল করা, ইনভয়েস PDF, হিসাব-বিবরণী | ✅ সম্পন্ন |
| 21 | Help | হেডারের হেল্প বাটন | ⏳ বাকি |
| 22 | Automated tests | Feature/Unit test | ⏳ বাকি (শুধু Laravel-এর example test আছে) |

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

**অসম্পূর্ণ ফিচার**
1. **Help পেজ**।
2. Audit log-এ Sales, Purchase, Collection আর Stock-এর পরিবর্তন যোগ করা।
3. ইনভয়েস PDF-এ কোম্পানির লোগো আর footer দেখানো, Delivery Invoice-এ ডিসকাউন্ট।
4. SMS বা Email নোটিফিকেশন (SMTP দিলে Forgot password-এর ইমেইল লিংকও চালু হবে)।
5. Retailer পোর্টালে Return request আর Warranty claim।

**মান ও স্থায়িত্ব**
6. **Automated tests**: Inventory (FIFO, adjustment), Payment allocation, Numbering, Sales credit hold, Delivery invoice, Portal isolation, Restore।
7. Timezone `Asia/Dhaka` করা।
8. GitHub-এ push করা (রিপোর লিংক পেলে) আর অফসাইট ব্যাকআপ।
9. ডোমেইন নিয়ে আসল SSL সার্টিফিকেট (এখন self-signed)।
10. MySQL-এ নেওয়া (দরকার হলে)।

**ঐচ্ছিক**
11. বাংলা ভাষা, ডার্ক মোড, রিপোর্টের PDF এক্সপোর্ট, Sales Invoice আর মানি রিসিট প্রিন্ট।

## ৭. সার্ভার

XAMPP-এর Apache-এ `/dms` alias দিয়ে চলে। http আর https দুটোই চালু:
- https://localhost/dms
- https://192.168.0.171/dms (লোকাল নেটওয়ার্ক)
- https://160.25.9.132/dms (পাবলিক IP)

বিস্তারিত `development.md`-এ আছে।
