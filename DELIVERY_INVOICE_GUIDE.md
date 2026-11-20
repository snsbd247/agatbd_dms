# Delivery Invoice System - Implementation Guide

## Overview
একটি সম্পূর্ণ পার্শিয়াল ডেলিভারি ম্যানেজমেন্ট সিস্টেম তৈরি করা হয়েছে যেখানে:

- প্রতিটি সেলস অর্ডারের জন্য অনেকগুলো আলাদা আলাদা ডেলিভারি করা যায়
- প্রতিটি ডেলিভারির জন্য আলাদা আলাদা ডেলিভারি ইনভয়েস তৈরি হয়
- প্রতিটি ডেলিভারি ইনভয়েস PDF তে প্রিন্ট করা যায়
- একটি সেলস অর্ডারের সব ডেলিভারি ইনভয়েস একসাথে প্রিন্ট করা যায়

---

## মডেলস এবং ডেটাবেস টেবিল

### 1. **DeliveryInvoice** মডেল
- `app/Models/Delivery/DeliveryInvoice.php`
- টেবিলে সংরক্ষণ হয়: `delivery_invoices`
- প্রতিটি ডেলিভারির জন্য একটি ইনভয়েস
- Relationships:
  - `salesOrder()` - সম্পর্কিত সেলস অর্ডার
  - `deliveryNote()` - সম্পর্কিত ডেলিভারি নোট
  - `retailer()` - খুচরা বিক্রেতা
  - `items()` - ইনভয়েস আইটেম

### 2. **DeliveryInvoiceItem** মডেল  
- `app/Models/Delivery/DeliveryInvoiceItem.php`
- টেবিলে সংরক্ষণ হয়: `delivery_invoice_items`
- প্রতিটি ইনভয়েসের পণ্য এবং পরিমাণ
- Relationships:
  - `deliveryInvoice()` - পিতামাতা ইনভয়েস
  - `product()` - পণ্য
  - `batch()` - ব্যাচ নম্বর
  - `salesOrderItem()` - মূল অর্ডার আইটেম

### 3. ডেটাবেস মাইগ্রেশন
- `database/migrations/2026_08_14_000001_create_delivery_invoices_table.php`
- টেবিলগুলি তৈরি করতে `php artisan migrate` চালান

---

## সার্ভিস

### DeliveryInvoiceService
- `app/Services/DeliveryInvoiceService.php`
- ভূমিকা:
  - ডেলিভারি ইনভয়েস তৈরি করা
  - ইনভয়েস নম্বর স্বয়ংক্রিয়ভাবে জেনারেট করা
  - স্টক লেজার আপডেট করা
  - ডেলিভারি স্ট্যাটাস আপডেট করা

#### প্রধান মেথড:
```php
// ডেলিভারি নোট থেকে ইনভয়েস তৈরি করুন
createDeliveryInvoice(DeliveryNote $deliveryNote)

// ইনভয়েস ফাইনালাইজ করুন
finalizeInvoice(DeliveryInvoice $invoice)

// প্রিন্ট করা হিসেবে চিহ্নিত করুন
markAsPrinted(DeliveryInvoice $invoice)

// পার্শিয়াল ডেলিভারি তথ্য পান
getPartialDeliveryInfo(SalesOrder $salesOrder)
```

---

## Livewire কম্পোনেন্ট

### 1. Delivery Invoice Index
- `app/Livewire/Delivery/Invoices/Index.php`
- মার্গ: `/deliveries/invoices`
- সব ডেলিভারি ইনভয়েস দেখান
- সার্চ এবং ফিল্টার সুবিধা
- প্রিন্ট এবং ফাইনালাইজ অ্যাকশন

### 2. Delivery Invoice Create
- `app/Livewire/Delivery/Invoices/Create.php`
- মার্গ: `/deliveries/notes/{id}/invoice/create`
- ডেলিভারি নোট থেকে ইনভয়েস তৈরি
- সারসংক্ষেপ এবং পূর্বরূপ দেখান

### 3. Delivery Invoice Show
- `app/Livewire/Delivery/Invoices/Show.php`
- মার্গ: `/deliveries/invoices/{id}`
- ইনভয়েস বিস্তারিত তথ্য দেখান
- প্রিন্ট অপশন এবং স্ট্যাটাস আপডেট

---

## কন্ট্রোলার

### DeliveryInvoiceController
- `app/Http/Controllers/DeliveryInvoiceController.php`
- ভূমিকা:
  - একক ইনভয়েস PDF প্রিন্ট করা
  - একটি সেলস অর্ডারের সব ইনভয়েস একসাথে প্রিন্ट করা

#### রুট:
```php
GET /deliveries/invoices/{id}/print           // একটি ইনভয়েস প্রিন্ট
GET /deliveries/sales-order/{id}/invoices/print // সব ইনভয়েস প্রিন্ট
```

---

## রুট

সব নতুন রুটস `routes/web.php` এ যোগ করা হয়েছে:

```php
Route::middleware('can:delivery.view')->prefix('deliveries')->name('delivery.')->group(function () {
    Route::get('/', DeliveryIndex::class)->name('index');
    Route::get('/invoices', DeliveryInvoiceIndex::class)->name('invoices.index');
    Route::get('/invoices/{deliveryInvoice}', DeliveryInvoiceShow::class)->name('invoices.show');
    Route::get('/invoices/{deliveryInvoice}/print', [DeliveryInvoiceController::class, 'printInvoice'])->name('invoices.print');
    Route::get('/notes/{deliveryNote}/invoice/create', DeliveryInvoiceCreate::class)->middleware('can:delivery.manage')->name('invoices.create');
    Route::get('/sales-order/{salesOrder}/invoices/print', [DeliveryInvoiceController::class, 'printSalesOrderInvoices'])->name('sales-order.invoices.print');
});
```

---

## ভিউ টেমপ্লেট

### Blade ভিউ
- `resources/views/livewire/delivery/invoices/index.blade.php` - ইনভয়েস তালিকা
- `resources/views/livewire/delivery/invoices/create.blade.php` - ইনভয়েস তৈরি ফর্ম
- `resources/views/livewire/delivery/invoices/show.blade.php` - ইনভয়েস বিবরণ

### PDF ভিউ
- `resources/views/pdfs/delivery-invoice.blade.php` - একক ইনভয়েস PDF
- `resources/views/pdfs/delivery-invoices-batch.blade.php` - মাল্টিপেজ PDF

---

## ওয়ার্কফ্লো ধাপ দ্বারা ধাপ

### ১. ডেলিভারি তৈরি
```
Sales Order (অনুমোদিত) 
    → "Deliver" বাটন ক্লিক
    → ডেলিভারি ফর্ম খুলে 
    → পরিমাণ নির্ধারণ করুন
    → ডেলিভারি সেভ করুন
```

### ২. ডেলিভারি ইনভয়েস তৈরি
```
ডেলিভারি নোট সফলভাবে তৈরি
    → `/deliveries/invoices` যান
    → "Create Invoice" বাটন ক্লিক করুন (ডেলিভারি থেকে)
    → সংক্ষিপ্ত পরীক্ষা করুন
    → "Create Invoice" বাটন ক্লিক
```

### ৩. ইনভয়েস প্রিন্ট করা
```
ডেলিভারি ইনভয়েস তৈরি
    → ইনভয়েস বিবরণ পৃষ্ঠায় যান
    → "Print" বাটন ক্লিক করুন
    → PDF ডাউনলোড এবং প্রিন্ট করুন
```

### ৪. সেলস অর্ডার থেকে সমস্ত ইনভয়েস প্রিন্ট
```
Sales Order বিবরণ পৃষ্ঠা
    → "Delivery Invoices" সেকশন স্ক্রল করুন
    → "Print All" বাটন ক্লিক করুন
    → সব ইনভয়েসের একটি বড় PDF পান
```

---

## পার্শিয়াল ডেলিভারি উদাহরণ

### পরিস্থিতি:
- **Sales Order** #SO-001: ১০০ ইউনিট পণ্য A
- **অর্ডারের মূল্য**: ৳ ১০,০০০

### ধাপ ১: প্রথম ডেলিভারি
- ৫০ ইউনিট ডেলিভার করুন
- অটো-জেনারেটেড ডেলিভারি ইনভয়েস #DI-000001 (৳ ৫,০০০)

### ধাপ ২: দ্বিতীয় ডেলিভারি  
- ৩০ ইউনিট ডেলিভার করুন
- অটো-জেনারেটেড ডেলিভারি ইনভয়েস #DI-000002 (৳ ৩,০০০)

### ধাপ ৩: তৃতীয় ডেলিভারি
- অবশিষ্ট ২০ ইউনিট ডেলিভার করুন
- অটো-জেনারেটেড ডেলিভারি ইনভয়েস #DI-000003 (৳ ২,০০০)

**ফলাফল:**
- তিনটি আলাদা ডেলিভারি ইনভয়েস
- তিনটি আলাদা PDF রিপোর্ট
- Sales Order স্ট্যাটাস "Partial" থেকে "Delivered" এ পরিবর্তন

---

## মূল বৈশিষ্ট্য

✅ **পার্শিয়াল ডেলিভারি সাপোর্ট** - একাধিক ডেলিভারি ট্র্যাকিং
✅ **স্বয়ংক্রিয় ইনভয়েস জেনারেশন** - প্রতিটি ডেলিভারির জন্য ইউনিক ইনভয়েস
✅ **PDF প্রিন্টিং** - পেশাদার রিপোর্ট জেনারেশন
✅ **স্টক ট্র্যাকিং** - স্বয়ংক্রিয় স্টক লেজার আপডেট
✅ **ব্যাচ ট্র্যাকিং** - ব্যাচ নম্বর সংরক্ষণ
✅ **স্ট্যাটাস ম্যানেজমেন্ট** - Pending → Finalized → Printed
✅ **সার্চ এবং ফিল্টার** - সহজ ডেটা খুঁজে পাওয়া
✅ **বাল্ক প্রিন্টিং** - একসাথে সব ইনভয়েস প্রিন্ট

---

## পারমিশন/অনুমতি প্রয়োজন

- `delivery.view` - ডেলিভারি ইনভয়েস দেখার অনুমতি
- `delivery.manage` - ডেলিভারি ইনভয়েস তৈরির অনুমতি

---

## পরবর্তী পদক্ষেপ (ঐচ্ছিক)

1. **Email Notification** - ডেলিভারি ইনভয়েস তৈরি হলে নোটিফিকেশন পাঠান
2. **SMS Integration** - হোলসেলারদের এসএমএস আপডেট পাঠান
3. **Advanced Reporting** - পার্শিয়াল ডেলিভারি রিপোর্ট জেনারেট করুন
4. **Payment Tracking** - প্রতিটি ডেলিভারি ইনভয়েসের জন্য পেমেন্ট ট্র্যাক করুন

---

## Troubleshooting

### সমস্যা: ডেলিভারি ইনভয়েস তৈরি হয় না
**সমাধান:**
1. ডেলিভারি নোটে আইটেম আছে কিনা চেক করুন
2. পারমিশন চেক করুন (`delivery.manage`)
3. ডেটাবেস মাইগ্রেশন চালান: `php artisan migrate`

### সমস্যা: PDF প্রিন্ট করা যায় না
**সমাধান:**
1. `barryvdh/laravel-dompdf` প্যাকেজ ইনস্টল করুন
2. `config/app.php` এ provider যোগ করুন

### সমস্যা: স্টক লেজার আপডেট হয় না
**সমাধান:**
1. `DeliveryInvoiceService::logStockDelivery()` পদ্ধতি চেক করুন
2. ওয়্যারহাউস ID সঠিক কিনা যাচাই করুন

---

## নিরাপত্তা বিবেচনা

- সব ডেলিভারি অপারেশন লেনদেন সুরক্ষিত
- কোয়ান্টিটি ভ্যালিডেশন চেক করা হয়
- পারমিশন বেসড এক্সেস কন্ট্রোল
- অডিট লগিং স্বয়ংক্রিয়ভাবে রেকর্ড করা হয়

---

**তৈরি: ২ আগস্ট ২০২৬**
**সংস্করণ: ১.০**
