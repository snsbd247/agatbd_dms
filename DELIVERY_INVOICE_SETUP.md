# দ্রুত সেটআপ গাইড - Delivery Invoice System

## ১. ডেটাবেস মাইগ্রেশন চালান
```bash
php artisan migrate
```

এটি নতুন টেবিল তৈরি করবে:
- `delivery_invoices`
- `delivery_invoice_items`

---

## ২. Required Package চেক করুন

**PDF জেনারেশনের জন্য প্রয়োজনীয়:**
```bash
composer require barryvdh/laravel-dompdf
```

যদি ইতিমধ্যে ইনস্টল থাকে, এটি স্কিপ করুন।

---

## ৩. মেনু লিংক যোগ করুন (Optional)

আপনার মেনুতে এটি যোগ করুন:
```
Deliveries
├── All Deliveries (existing)
└── Delivery Invoices (নতুন)
```

---

## ৪. পারমিশন সেটআপ (যদি প্রয়োজন হয়)

আপনার রোল-পারমিশন ম্যানেজারে নিশ্চিত করুন:
- `delivery.view` - আছে?
- `delivery.manage` - আছে?

যদি না থাকে, সেটিংসে যোগ করুন।

---

## ৫. ব্যবহার শুরু করুন

### ডেলিভারি ইনভয়েস তালিকা দেখুন:
```
URL: /deliveries/invoices
```

### একটি সেলস অর্ডারে যান:
```
Sales Orders → সেলস অর্ডার খুলুন → Delivery Invoices সেকশন দেখবেন
```

---

## ৬. সম্পূর্ণ ওয়ার্কফ্লো

### A. নতুন ডেলিভারি তৈরি করুন
1. Sales Order বিবরণ পৃষ্ঠায় যান
2. **"Deliver"** বাটন ক্লিক করুন
3. ডেলিভার করার পণ্য এবং পরিমাণ নির্ধারণ করুন
4. ডেলিভারি সেভ করুন

### B. ডেলিভারি ইনভয়েস তৈরি করুন
1. `/deliveries/invoices` এ যান
2. নতুন ডেলিভারির জন্য **"Create Invoice"** অপশন খুঁজুন
   (বা ডেলিভারি নোট থেকে সরাসরি: `/deliveries/notes/{id}/invoice/create`)
3. ইনভয়েস প্রিভিউ দেখুন
4. **"Create Invoice"** ক্লিক করুন

### C. ইনভয়েস প্রিন্ট করুন
#### একটি ইনভয়েস:
1. Delivery Invoices তালিকায় যান
2. একটি ইনভয়েসের **"Print"** বাটন ক্লিক করুন
3. PDF ডাউনলোড হবে

#### একটি সেলস অর্ডারের সব ইনভয়েস:
1. Sales Order বিবরণ পৃষ্ঠায় যান
2. "Delivery Invoices" সেকশন খুঁজুন
3. **"Print All"** বাটন ক্লিক করুন
4. সব ইনভয়েসের একটি বড় PDF পাবেন

---

## ৭. ফাইল লোকেশন

**নতুন ফাইলগুলি যেখানে যোগ করা হয়েছে:**

### মডেল:
- `app/Models/Delivery/DeliveryInvoice.php` 
- `app/Models/Delivery/DeliveryInvoiceItem.php`

### সার্ভিস:
- `app/Services/DeliveryInvoiceService.php`

### কন্ট্রোলার:
- `app/Http/Controllers/DeliveryInvoiceController.php`

### Livewire:
- `app/Livewire/Delivery/Invoices/Index.php`
- `app/Livewire/Delivery/Invoices/Create.php`
- `app/Livewire/Delivery/Invoices/Show.php`

### ভিউ:
- `resources/views/livewire/delivery/invoices/index.blade.php`
- `resources/views/livewire/delivery/invoices/create.blade.php`
- `resources/views/livewire/delivery/invoices/show.blade.php`

### PDF টেমপ্লেট:
- `resources/views/pdfs/delivery-invoice.blade.php`
- `resources/views/pdfs/delivery-invoices-batch.blade.php`

### মাইগ্রেশন:
- `database/migrations/2026_08_14_000001_create_delivery_invoices_table.php`

---

## ৮. ট্রাবলশুটিং টিপস

**"Route not found" ত্রুটি?**
```bash
php artisan route:clear
php artisan view:clear
```

**PDF প্রিন্ট করা যায় না?**
```bash
composer require barryvdh/laravel-dompdf
```

**স্টক আপডেট হয় না?**
- নিশ্চিত করুন `StockLedger` টেবিল আছে
- `InventoryService` সঠিকভাবে ইনস্ট্যালড

---

## ৯. কনফিগারেশন (Optional)

### Invoice Number Format পরিবর্তন করতে:
`app/Services/DeliveryInvoiceService.php` খুলুন এবং এটি খুঁজুন:
```php
$invoiceNumber = 'DI-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
```

পরিবর্তন করুন যেমন আপনি চান, যেমন:
```php
$invoiceNumber = 'DLV-' . strtoupper(date('YmdHis'));
```

---

## ১০. পরীক্ষা করুন (Testing)

একটি সম্পূর্ণ টেস্ট চালান:

1. একটি Sales Order তৈরি করুন
2. অনুমোদন করুন
3. একটি ডেলিভারি তৈরি করুন
4. একটি ডেলিভারি ইনভয়েস তৈরি করুন
5. PDF প্রিন্ট করুন

সবকিছু সফল হলে, সিস্টেম প্রস্তুত! ✅

---

**আরও সাহায্যের জন্য: `DELIVERY_INVOICE_GUIDE.md` দেখুন**
