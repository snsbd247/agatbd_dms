# DMS — UI ডিজাইন

UI বানানো হয়েছে Blade + Livewire 4 + Alpine.js + Tailwind CSS 4 দিয়ে। ডিজাইনটা **mobile-first**: মোবাইলে PWA অ্যাপের মতো চলে, ডেস্কটপে সাইডবার-সহ অ্যাডমিন প্যানেলের মতো দেখায়।

> সর্বশেষ আপডেট: ২১ নভেম্বর ২০২৬

---

## ১. ডিজাইন টোকেন

| জিনিস | মান |
|---|---|
| ফন্ট | Instrument Sans, তারপর system-ui |
| পেজ ব্যাকগ্রাউন্ড | `slate-100` |
| মূল টেক্সট | `slate-900` |
| Primary / Accent | `indigo-600`, hover-এ `indigo-500` |
| সাইডবার | `slate-950` ব্যাকগ্রাউন্ড, `slate-200`/`slate-400` টেক্সট, `slate-800` বর্ডার |
| কার্ড | `bg-white rounded-xl shadow` |
| ইনপুট | `border rounded-lg` |
| Danger | `red-500` / `red-600` (নোটিফিকেশন ব্যাজ, লগআউট) |
| Success | `green-500` (অনলাইন ডট) |
| Theme color (PWA) | `#0f172a` |

Stat card-এর আইকনের রং: blue, green, orange, purple, red, indigo, teal, cyan, pink।

## ২. লেআউট (`resources/views/layouts/app.blade.php`)

### ডেস্কটপ (≥ `lg`, 1024px)

```
┌───────────┬──────────────────────────────────────────────┐
│  [■] DMS  │ ☰  Page Title                🔍 ⟳ 🔔 ? 👤 ▾ │ ← sticky header
│ Dealer Mgmt│   Group > Page Title                         │
├───────────┼──────────────────────────────────────────────┤
│ Dashboard │                                              │
│ Notif.    │                                              │
│ ▸ Master  │            main (max-w-7xl, p-6)             │
│ ▸ Purchase│                                              │
│ ▸ ...     │                                              │
│ [Need Help│                                              │
│  Contact] │                                              │
│ 👤 Name   │                                              │
└───────────┴──────────────────────────────────────────────┘
```

- **সাইডবার**: চওড়া অবস্থায় `w-64`, ছোট করলে `w-20`। কোন অবস্থায় আছে তা `localStorage`-এর `dms_sidebar_collapsed`-এ মনে রাখা হয়।
- **মেনু গ্রুপ**: প্রতিটা গ্রুপ ভাঁজ করা যায় (Alpine `x-data="{open}"`)। যে গ্রুপের পেজ খোলা আছে সেটা নিজে থেকেই খোলা থাকে। ইউজারের পারমিশন না থাকলে গ্রুপ বা লিংক দেখায় না।
- **হেডার**: পেজের টাইটেল আর ব্রেডক্রাম্ব, সার্চ (এখন disabled), প্রতিটা পেজের নিজস্ব `$headerActions` স্লট, রিফ্রেশ, নোটিফিকেশন ড্রপডাউন (না-পড়া ৫টা আর সংখ্যার ব্যাজ), হেল্প, ইউজার মেনু।

### মোবাইল (< `lg`)

```
┌──────────────────────────────┐
│ Page Title          🔔 👤    │ ← sticky, safe-area top
├──────────────────────────────┤
│                              │
│        main (p-4)            │
│                              │
├──────────────────────────────┤
│ Home  Master  Business  Reports  More │ ← fixed bottom nav
└──────────────────────────────┘
```

- সাইডবারের বদলে নিচে **৫টা ট্যাবের bottom navigation** থাকে: Home, Master, Business, Reports, More। কোনো ট্যাবের পারমিশন না থাকলে সেটা দেখায় না।
- **Business** ট্যাবে যায় Purchase, Inventory, Sales, Delivery, Collection আর Stock report।
- **More** ট্যাবে বাকি সব থাকে: Returns, Warranty, Retailers, Representatives, Payroll, Settings, Account, About, Backup। না-পড়া নোটিফিকেশনের ব্যাজও এখানে দেখায়।
- মোবাইলে ইউজার আইকনে চাপলে সোজা `/account/profile` খোলে।
- iPhone notch-এর জন্য `pt-safe`, `pb-safe`, `pb-safe-nav` ইউটিলিটি আছে।

## ৩. নেভিগেশন ম্যাপ

| গ্রুপ | পেজ |
|---|---|
| Dashboard | রোল অনুযায়ী Dealer / Representative / Retailer |
| Notifications | তালিকা |
| Master Data | Companies (+ details), Brands, Categories, Units, Products |
| Purchase | Purchase Orders, Goods Receipts, Purchase Returns |
| Inventory | Warehouses, Areas |
| Sales | Sales Orders |
| Delivery | Deliveries, Delivery Invoices |
| Collection | Collections |
| Return & Warranty | Return Requests, Company Returns, Warranty Claims |
| Retailer | Retailers (+ details, ledger) |
| Representative | Representatives (+ details) |
| Reports | Sales, Purchase, Stock, Expiry, Collections & Due, Return, Warranty, Representative Performance, Retailer, Company, Area, Product Performance, Payroll & Commission, Profit & Loss |
| Payroll | Payroll Runs, Advances, Bonuses |
| Settings | Company Profile, Numbering, Warranty Policies, Users, Login History, Audit Log, Email & SMS |
| My Account (রিটেইলার) | My Orders, New Order, My Statement, My Returns, My Warranty Claims |
| Account | Profile, Change Password, Notification Settings |
| More | About, Backup & Restore |

## ৪. পেজের প্যাটার্ন

প্রায় সব মডিউলে একই ধরনের পেজ আছে:

| ধরন | গঠন |
|---|---|
| **Index** | উপরে stat card (মোট, স্ট্যাটাস অনুযায়ী গণনা), তারপর সার্চ আর ফিল্টার, তারপর responsive টেবিল, তারপর pagination। হেডারে "New" বাটন। |
| **Create** | কার্ডের ভেতরে ফর্ম। লাইন-আইটেমের টেবিলে row যোগ বা মোছা যায় আর মোট লাইভ হিসাব হয় (Livewire)। |
| **Show** | ডকুমেন্টের হেডার (নম্বর, স্ট্যাটাস ব্যাজ, তারিখ), আইটেমের টেবিল, অ্যাকশন বাটন (Approve, Cancel, Deliver, Print…)। |
| **Master CRUD** | একই পেজে তালিকা, আর মোডাল বা ইনলাইন ফর্মে যোগ ও এডিট। |
| **Report** | তারিখ ও ফিল্টার বার, সারাংশের stat card, Chart.js চার্ট, টেবিল, CSV export বাটন। |

## ৫. কম্পোনেন্ট (`resources/views/components/`)

| কম্পোনেন্ট | কাজ |
|---|---|
| `<x-icon name="..." />` | inline SVG আইকন সেট (heroicons স্টাইল: grid, bell, database, truck, banknotes, wallet, cog…) |
| `<x-stat-card icon color label value sublabel />` | রঙিন আইকন বক্স আর সংখ্যার KPI কার্ড |
| `<x-icon-action />` | টেবিলের row-এর আইকন বাটন (view/edit/delete) |
| `<x-pagination />` | কাস্টম pagination |
| `<x-coming-soon />` | যে ফিচার এখনো তৈরি হয়নি তার placeholder |

## ৬. Responsive টেবিল (`.rtable`)

`resources/css/app.css`-এ আছে। `sm` (640px)-এর নিচে টেবিলটা **কার্ডের তালিকা** হয়ে যায়:

- `<td data-label="Amount">`: মোবাইলে বামে লেবেল, ডানে মান।
- `data-label` ছাড়া `<td>`: ডানে সরানো থাকে (সাধারণত Action কলাম)।
- `.rtable-title`: কার্ডের হেডার row (আইকন, টাইটেল, ব্যাজ), হালকা ধূসর ব্যাকগ্রাউন্ডে।
- `.rtable-block`: লম্বা লেখার জন্য লেবেল উপরে, মান নিচে।
- `.rtable-hide-mobile`: মোবাইলে লুকানো থাকে।

## ৭. PDF / প্রিন্ট

সব PDF তৈরি হয় `App\Services\PdfService` দিয়ে, ইউজারের ভাষায়: ইংরেজি হলে dompdf, বাংলা হলে mPDF আর Hind Siliguri ফন্ট। সব ডকুমেন্ট একই layout-এ: উপরে বামে কোম্পানির লোগো আর নাম, ডানে ডকুমেন্টের নাম, নম্বর আর তারিখ; নিচে স্বাক্ষরের লাইন আর footer। লেআউট শুধু table দিয়ে (flexbox নয়), যাতে দুই ইঞ্জিনেই এক রকম দেখায়।

| ডকুমেন্ট | view | কোথায় বোতাম |
|---|---|---|
| Delivery Invoice | `pdfs/delivery-invoice`, batch: `pdfs/delivery-invoices-batch` (body: `partials/delivery-invoice-body`) | Delivery Invoice পেজ, Sales Order পেজ, পোর্টালের অর্ডার |
| Sales Invoice | `pdfs/sales-invoice` (পরিশোধিত আর বকেয়াসহ) | Sales Order-এর "চালান" অংশ, Retailer-এর Invoices ট্যাব, পোর্টালের হিসাব |
| Money Receipt | `pdfs/money-receipt` (এক পাতায় দুই কপি) | Collections তালিকা (প্রিন্টার আইকন), পোর্টালের হিসাব |
| রিপোর্ট | `pdfs/report` (যেকোনো রিপোর্ট; CSV-র সারিগুলোই টেবিল হয়) | প্রতিটা রিপোর্টের উপরে "PDF" বোতাম, "CSV ডাউনলোড"-এর পাশে |

Company profile-এর নাম, লোগো আর `invoice_footer` সব PDF-এ ব্যবহার হয়।

## ৮. PWA

- `public/manifest.json`, `icon-192.png`, `icon-512.png`, `apple-touch-icon.png`, `icon.svg`।
- `viewport-fit=cover`, `apple-mobile-web-app-capable`, `overscroll-behavior: contain`, tap-highlight বন্ধ, `touch-action: manipulation`: সব মিলিয়ে নেটিভ অ্যাপের মতো অনুভূতি দেয়।

## ৯. ভাষা (বাংলা / English)

- ডিফল্ট ভাষা বাংলা (`APP_LOCALE=bn`)। প্রত্যেক ইউজার হেডার, Profile বা লগইন পেজের **বাংলা / EN** সুইচ দিয়ে নিজের ভাষা বাছতে পারে। পছন্দ `users.locale`-এ সেভ থাকে।
- `App\Http\Middleware\SetLocale` প্রতিটা web আর Livewire request-এ ভাষা বসায়।
- অনুবাদ থাকে `lang/bn.json`-এ (ইংরেজি লেখাটাই key), আর validation বার্তা `lang/bn/validation.php`-এ।
- ফন্ট: Instrument Sans, তারপর Hind Siliguri (Google Fonts)।
- **নতুন পেজ বা বাটন যোগ করলে:** লেখা `{{ __('English text') }}` দিয়ে লিখুন আর `lang/bn.json`-এ বাংলা যোগ করুন। ডাটা-সহ লেখার জন্য `__('Delivered :v', ['v' => $date])`।
- ইচ্ছা করে ইংরেজি রাখা হয়েছে: ইনভয়েস PDF, তারিখের মাসের নাম, সংখ্যা (০-৯ না, 0-9), SMS, নোটিফিকেশনের ভেতরের বার্তা (শিরোনাম অনুবাদ হয়), আর অল্প কয়েকটা জটিল লাইন। Help পেজ সব ভাষাতেই বাংলা।

## ১০. ডার্ক মোড

- হেডারে চাঁদ/সূর্য বোতাম (`<x-theme-toggle />`)। পছন্দ এই browser-এ মনে থাকে (`localStorage` → `dms_theme`)। প্রথমবার ডিভাইসের সেটিং (`prefers-color-scheme`) মেনে চলে।
- কীভাবে কাজ করে: `html.dark`-এর নিচে Tailwind-এর রঙগুলো নতুন করে map হয়। হালকা ধূসর গাঢ় হয়, হালকা রঙিন ব্যাকগ্রাউন্ড স্বচ্ছ হয়, গাঢ় লেখা হালকা হয়। তাই নতুন পেজে আলাদা `dark:` class লাগে না, সাধারণ class লিখলেই ডার্ক মোডে চলে।
- যে অংশ আগে থেকেই গাঢ় (সাইডবার, gradient ব্যানার), তাতে `dark-keep` class দিন, তাহলে রং বদলাবে না।
- রং বদলাতে [scripts/make-dark-css.php](scripts/make-dark-css.php) এডিট করে চালান, তারপর `npm run build`।
- লগইন পেজ সবসময় আগের মতো থাকে (এটা আগে থেকেই গাঢ়)।

## ১১. বাকি UI কাজ

- নেই। কোম্পানির নাম আর লোগো Company Profile-এ বসালে PDF আর হেডারে দেখাবে।
