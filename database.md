# DMS — ডাটাবেজ স্কেমা

উৎস: `database/migrations/`-এর সব migration। Laravel-এর `id()` মানে `BIGINT UNSIGNED` primary key। `timestamps()` মানে `created_at` আর `updated_at`। নিচের টেবিলগুলোতে এই দুটো আলাদা করে লেখা হয়নি।

টাকার কলাম `DECIMAL(14,2)`, পরিমাণের কলাম `DECIMAL(14,3)`, কমিশন রেট `DECIMAL(5,2)`।

FK-এর পাশের চিহ্ন: **null** = parent মুছলে NULL হয়, **cascade** = parent মুছলে row-ও মোছে, কিছু না লেখা থাকলে restrict।

> সর্বশেষ আপডেট: ২১ নভেম্বর ২০২৬ (সর্বশেষ migration: `2026_11_21_000001_create_app_settings_and_sms_logs_tables`)

---

## সূচি

1. [Core / Laravel](#১-core--laravel)
2. [Role ও Permission](#২-role-ও-permission)
3. [Settings](#৩-settings)
4. [Master Data](#৪-master-data)
5. [Inventory](#৫-inventory)
6. [People](#৬-people-representative-ও-retailer)
7. [Purchase](#৭-purchase)
8. [Sales](#৮-sales)
9. [Delivery ও Collection](#৯-delivery-ও-collection)
10. [Return ও Warranty](#১০-return-ও-warranty)
11. [Payroll](#১১-payroll)
12. [সম্পর্কের ডায়াগ্রাম](#১২-সম্পর্কের-ডায়াগ্রাম)

---

## ১. Core / Laravel

### users
| কলাম | টাইপ | নোট |
|---|---|---|
| id | bigint PK | |
| name | string | |
| email | string | unique |
| email_verified_at | timestamp | nullable |
| password | string | hashed |
| remember_token | string | nullable |
| notification_preferences | json | nullable (migration `2026_07_29`) |

Laravel-এর ডিফল্ট টেবিলও আছে: `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`।

### notifications
| কলাম | টাইপ | নোট |
|---|---|---|
| id | uuid PK | |
| type | string | Notification class |
| notifiable_type, notifiable_id | morphs | সাধারণত User |
| data | json | `title`, `message`, লিংক |
| read_at | timestamp | nullable |

## ২. Role ও Permission

spatie/laravel-permission-এর টেবিল: `permissions`, `roles`, `model_has_permissions`, `model_has_roles`, `role_has_permissions`। রোল আর পারমিশনের তালিকা `plan.md`-তে আছে।

## ৩. Settings

### company_profiles
| কলাম | টাইপ | নোট |
|---|---|---|
| name | string | |
| address, phone, email | string | nullable |
| logo_path | string | nullable |
| invoice_footer | text | nullable |

### numbering_sequences
| কলাম | টাইপ | নোট |
|---|---|---|
| key | string | যেমন `sales_order` |
| year | smallint unsigned | |
| last_number | int unsigned | default 0 |

Unique: (`key`, `year`)

### audit_logs
| কলাম | টাইপ | নোট |
|---|---|---|
| user_id | FK users | null |
| action | string | |
| auditable_type, auditable_id | string, bigint | nullable, index |
| old_values, new_values | json | nullable |
| ip_address | string | nullable |

### login_histories
| কলাম | টাইপ | নোট |
|---|---|---|
| user_id | FK users | null |
| ip_address, user_agent | string | nullable |
| login_at, logout_at | timestamp | nullable |
| status | string | default `success` |

## ৪. Master Data

### companies
| কলাম | টাইপ | নোট |
|---|---|---|
| name | string | |
| code | string | unique |
| phone, email, address | string | nullable |
| active | boolean | default true |

### brands
| কলাম | টাইপ | নোট |
|---|---|---|
| company_id | FK companies | nullable, null |
| name | string | |
| code | string | unique |
| active | boolean | default true |

### categories
| কলাম | টাইপ | নোট |
|---|---|---|
| parent_id | FK categories | nullable, null (sub-category) |
| name | string | |
| code | string | unique |
| active | boolean | default true |

### units
| কলাম | টাইপ | নোট |
|---|---|---|
| name | string | |
| short_code | string | unique |

### products
| কলাম | টাইপ | নোট |
|---|---|---|
| company_id | FK companies | |
| brand_id | FK brands | nullable, null |
| category_id | FK categories | |
| unit_id | FK units | |
| sku | string | unique |
| barcode | string | nullable |
| name | string | |
| description | text | nullable |
| image_path | string | nullable |
| purchase_price, sale_price | decimal(14,2) | default 0 |
| reorder_level | decimal(14,3) | default 0 |
| active | boolean | default true |

### product_prices (দামের হিস্টোরি)
| কলাম | টাইপ | নোট |
|---|---|---|
| product_id | FK products | cascade |
| purchase_price, sale_price | decimal(14,2) | |
| effective_from | date | |
| created_by | FK users | null |

## ৫. Inventory

### warehouses
| কলাম | টাইপ | নোট |
|---|---|---|
| name | string | |
| type | enum | `central`, `branch` |
| address | string | nullable |
| active | boolean | default true |

### areas
| কলাম | টাইপ | নোট |
|---|---|---|
| name | string | |
| code | string | unique |

### batches
| কলাম | টাইপ | নোট |
|---|---|---|
| product_id | FK products | |
| warehouse_id | FK warehouses | |
| batch_no | string | |
| expiry_date | date | nullable |
| cost_price | decimal(14,2) | |
| quantity_received | decimal(14,3) | |
| quantity_available | decimal(14,3) | FIFO-তে কমে |

### stocks
| কলাম | টাইপ | নোট |
|---|---|---|
| warehouse_id | FK warehouses | |
| product_id | FK products | |
| quantity | decimal(14,3) | default 0 |
| reorder_level | decimal(14,3) | default 0 |

Unique: (`warehouse_id`, `product_id`)

### stock_ledger
| কলাম | টাইপ | নোট |
|---|---|---|
| product_id | FK products | |
| warehouse_id | FK warehouses | |
| batch_id | FK batches | nullable, null |
| type | enum | `in`, `out`, `adjustment` |
| quantity | decimal(14,3) | |
| reference_type, reference_id | string, bigint | nullable, index (polymorphic) |
| balance_after | decimal(14,3) | |

### stock_adjustments
| কলাম | টাইপ | নোট |
|---|---|---|
| warehouse_id | FK warehouses | |
| product_id | FK products | |
| batch_id | FK batches | nullable, null |
| quantity_change | decimal(14,3) | ধনাত্মক বা ঋণাত্মক |
| reason | string | |
| adjusted_by | FK users | null |
| adjustment_date | date | |

## ৬. People (Representative ও Retailer)

### representatives
| কলাম | টাইপ | নোট |
|---|---|---|
| user_id | FK users | nullable, null (লগইন অ্যাকাউন্ট) |
| employee_code | string | unique |
| name | string | |
| phone | string | nullable |
| area_id | FK areas | nullable, null |
| salary_base | decimal(14,2) | default 0 |
| commission_rate | decimal(5,2) | % , default 0 |
| target_amount | decimal(14,2) | default 0 |
| active | boolean | default true |

### retailers
| কলাম | টাইপ | নোট |
|---|---|---|
| user_id | FK users | nullable, null |
| representative_id | FK representatives | nullable, null |
| area_id | FK areas | |
| code | string | unique |
| name | string | |
| shop_name | string | nullable |
| phone | string | |
| address | string | nullable |
| credit_limit | decimal(14,2) | default 0 |
| current_due | decimal(14,2) | default 0 |
| active | boolean | default true |

### retailer_ledger
| কলাম | টাইপ | নোট |
|---|---|---|
| retailer_id | FK retailers | cascade |
| type | string | invoice / collection / return ইত্যাদি |
| reference_type, reference_id | string, bigint | nullable, index |
| debit, credit | decimal(14,2) | default 0 |
| balance_after | decimal(14,2) | |
| entry_date | date | |

## ৭. Purchase

### purchase_orders
| কলাম | টাইপ | নোট |
|---|---|---|
| number | string | unique (`PO-…`) |
| company_id | FK companies | |
| warehouse_id | FK warehouses | |
| status | enum | `draft`, `sent`, `partial`, `completed`, `cancelled` (default draft) |
| order_date | date | |
| expected_date | date | nullable |
| total_amount | decimal(14,2) | |
| created_by | FK users | null |

### purchase_order_items
| কলাম | টাইপ | নোট |
|---|---|---|
| purchase_order_id | FK | cascade |
| product_id | FK products | |
| quantity | decimal(14,3) | |
| unit_price | decimal(14,2) | |
| received_quantity | decimal(14,3) | default 0 |

### goods_receipts
| কলাম | টাইপ | নোট |
|---|---|---|
| number | string | unique (`GRN-…`) |
| purchase_order_id | FK | nullable, null |
| warehouse_id | FK warehouses | |
| receipt_date | date | |
| total_amount | decimal(14,2) | |
| created_by | FK users | null |

### goods_receipt_items
| কলাম | টাইপ | নোট |
|---|---|---|
| goods_receipt_id | FK | cascade |
| product_id | FK products | |
| batch_no | string | |
| expiry_date | date | nullable |
| quantity | decimal(14,3) | |
| unit_cost | decimal(14,2) | |

### purchase_returns
| কলাম | টাইপ | নোট |
|---|---|---|
| number | string | unique (`PRT-…`) |
| company_id | FK companies | |
| warehouse_id | FK warehouses | |
| return_date | date | |
| reason | string | nullable |
| status | enum | `draft`, `sent`, `completed` |
| total_amount | decimal(14,2) | |

### purchase_return_items
| কলাম | টাইপ | নোট |
|---|---|---|
| purchase_return_id | FK | cascade |
| product_id | FK products | |
| batch_id | FK batches | nullable, null |
| quantity | decimal(14,3) | |
| unit_cost | decimal(14,2) | |

## ৮. Sales

### sales_orders
| কলাম | টাইপ | নোট |
|---|---|---|
| number | string | unique (`SO-…`) |
| order_type | enum | `retailer`, `representative` |
| retailer_id | FK retailers | |
| representative_id | FK representatives | nullable, null |
| warehouse_id | FK warehouses | |
| status | enum | `pending`, `credit_hold`, `approved`, `partial`, `completed`, `cancelled` |
| order_date | date | |
| subtotal, discount, grand_total | decimal(14,2) | |
| approved_by | FK users | null |
| approved_at | timestamp | nullable |

`current_due + grand_total > credit_limit` হলে অর্ডারটি `credit_hold` অবস্থায় তৈরি হয়।

### sales_order_items
| কলাম | টাইপ | নোট |
|---|---|---|
| sales_order_id | FK | cascade |
| product_id | FK products | |
| quantity | decimal(14,3) | |
| unit_price | decimal(14,2) | |
| delivered_quantity | decimal(14,3) | default 0 |

### sales_invoices
| কলাম | টাইপ | নোট |
|---|---|---|
| number | string | unique (`INV-…`) |
| sales_order_id | FK | nullable, null |
| retailer_id | FK retailers | |
| invoice_date | date | |
| subtotal, discount, grand_total | decimal(14,2) | |
| paid_amount, due_amount | decimal(14,2) | |
| status | enum | `unpaid`, `partial`, `paid` |

### sales_invoice_items
| কলাম | টাইপ | নোট |
|---|---|---|
| sales_invoice_id | FK | cascade |
| product_id | FK products | |
| batch_id | FK batches | nullable, null |
| quantity | decimal(14,3) | |
| unit_price, line_total | decimal(14,2) | |

## ৯. Delivery ও Collection

### delivery_notes
| কলাম | টাইপ | নোট |
|---|---|---|
| number | string | unique (`DEL-…`) |
| sales_order_id | FK sales_orders | |
| delivery_type | enum | `dealer`, `representative` |
| delivered_by | FK users | null |
| delivery_date | date | |
| status | enum | `pending`, `in_transit`, `delivered`, `partial` |

### delivery_note_items
| কলাম | টাইপ | নোট |
|---|---|---|
| delivery_note_id | FK | cascade |
| sales_order_item_id | FK | |
| product_id | FK products | |
| batch_id | FK batches | nullable, null |
| quantity | decimal(14,3) | |

### delivery_invoices (migration `2026_08_14`)
| কলাম | টাইপ | নোট |
|---|---|---|
| number | string | unique (`DI-YYYY-NNNNNN`) |
| sales_order_id | FK | index |
| delivery_note_id | FK | index |
| retailer_id | FK retailers | |
| invoice_date | date | |
| subtotal, discount, grand_total | decimal(14,2) | |
| status | enum | `pending`, `finalized`, `printed` |

### delivery_invoice_items
| কলাম | টাইপ | নোট |
|---|---|---|
| delivery_invoice_id | FK | cascade, index |
| sales_order_item_id | FK | |
| product_id | FK products | index |
| batch_id | FK batches | nullable, null |
| quantity | decimal(14,3) | |
| unit_price, total_price | decimal(14,2) | |

### collections (Model: `PaymentCollection`)
| কলাম | টাইপ | নোট |
|---|---|---|
| number | string | unique (`COL-…`) |
| retailer_id | FK retailers | |
| collected_by | FK users | null |
| collection_date | date | |
| amount | decimal(14,2) | |
| method | string | default `cash` |
| reference_no | string | nullable |

## ১০. Return ও Warranty

### return_requests
| কলাম | টাইপ | নোট |
|---|---|---|
| number | string | unique (`RET-…`) |
| retailer_id | FK retailers | |
| requested_by | FK users | null |
| request_date | date | |
| status | enum | `pending`, `approved`, `rejected`, `received` |
| reason | string | nullable |

### return_request_items
| কলাম | টাইপ | নোট |
|---|---|---|
| return_request_id | FK | cascade |
| product_id | FK products | |
| quantity | decimal(14,3) | |
| unit_price | decimal(14,2) | nullable (migration `2026_07_28`) |
| reason | string | nullable |

### return_receives
| কলাম | টাইপ | নোট |
|---|---|---|
| number | string | unique (`RCV-…`) |
| return_request_id | FK | |
| received_by | FK users | null |
| warehouse_id | FK warehouses | |
| receive_date | date | |

### return_receive_items
| কলাম | টাইপ | নোট |
|---|---|---|
| return_receive_id | FK | cascade |
| product_id | FK products | |
| batch_id | FK batches | nullable, null |
| quantity | decimal(14,3) | |
| disposition | enum | `restock`, `damage`, `company_return` |

### company_returns
| কলাম | টাইপ | নোট |
|---|---|---|
| number | string | unique (`CRT-…`) |
| company_id | FK companies | |
| warehouse_id | FK warehouses | |
| return_date | date | |
| status | enum | `draft`, `sent`, `completed` |

### company_return_items
| কলাম | টাইপ | নোট |
|---|---|---|
| company_return_id | FK | cascade |
| product_id | FK products | |
| quantity | decimal(14,3) | |

### warranty_policies
| কলাম | টাইপ | নোট |
|---|---|---|
| product_id | FK products | nullable, null |
| category_id | FK categories | nullable, null |
| duration_months | int unsigned | |
| terms | text | nullable |

### warranty_claims
| কলাম | টাইপ | নোট |
|---|---|---|
| number | string | unique (`WAR-…`) |
| retailer_id | FK retailers | |
| product_id | FK products | |
| sales_invoice_id | FK | nullable, null |
| claim_date | date | |
| issue_description | text | nullable |
| status | enum | `pending`, `approved`, `rejected`, `replaced`, `repaired` |

### warranty_tracking
| কলাম | টাইপ | নোট |
|---|---|---|
| warranty_claim_id | FK | cascade |
| status | string | |
| note | text | nullable |
| changed_by | FK users | null |
| changed_at | timestamp | nullable |

## ১১. Payroll

### representative_targets
| কলাম | টাইপ | নোট |
|---|---|---|
| representative_id | FK | cascade |
| period | string | যেমন `2026-07` |
| target_amount, achieved_amount | decimal(14,2) | |

Unique: (`representative_id`, `period`)

### commissions
| কলাম | টাইপ | নোট |
|---|---|---|
| representative_id | FK | cascade |
| sales_invoice_id | FK | nullable, null |
| period | string | |
| delivered_amount | decimal(14,2) | |
| commission_rate | decimal(5,2) | |
| commission_amount | decimal(14,2) | |
| return_adjustment | decimal(14,2) | |

### advances
| কলাম | টাইপ | নোট |
|---|---|---|
| representative_id | FK | cascade |
| amount | decimal(14,2) | |
| advance_date | date | |
| reason | string | nullable |
| deducted | boolean | default false |

### bonuses
| কলাম | টাইপ | নোট |
|---|---|---|
| representative_id | FK | cascade |
| amount | decimal(14,2) | |
| reason | string | nullable |
| bonus_date | date | |

### payroll_entries
| কলাম | টাইপ | নোট |
|---|---|---|
| number | string | unique (`PAY-…`) |
| representative_id | FK | |
| period | string | |
| base_salary, commission_amount, bonus_amount | decimal(14,2) | |
| advance_deduction, other_deduction | decimal(14,2) | |
| net_salary | decimal(14,2) | |
| status | enum | `pending`, `paid` |
| paid_at | timestamp | nullable |

## ১২. সম্পর্কের ডায়াগ্রাম

```mermaid
erDiagram
    companies ||--o{ brands : has
    companies ||--o{ products : supplies
    categories ||--o{ categories : parent
    categories ||--o{ products : groups
    units ||--o{ products : measures
    products ||--o{ product_prices : history
    products ||--o{ batches : stocked_as
    warehouses ||--o{ batches : holds
    warehouses ||--o{ stocks : holds
    products ||--o{ stocks : in
    batches ||--o{ stock_ledger : moves
    areas ||--o{ representatives : covers
    areas ||--o{ retailers : located
    representatives ||--o{ retailers : manages
    retailers ||--o{ retailer_ledger : ledger
    companies ||--o{ purchase_orders : from
    purchase_orders ||--o{ purchase_order_items : contains
    purchase_orders ||--o{ goods_receipts : received_by
    goods_receipts ||--o{ goods_receipt_items : contains
    retailers ||--o{ sales_orders : places
    sales_orders ||--o{ sales_order_items : contains
    sales_orders ||--o{ delivery_notes : delivered_by
    delivery_notes ||--o{ delivery_note_items : contains
    delivery_notes ||--o{ delivery_invoices : billed_as
    delivery_invoices ||--o{ delivery_invoice_items : contains
    sales_orders ||--o{ sales_invoices : invoiced
    sales_invoices ||--o{ sales_invoice_items : contains
    retailers ||--o{ collections : pays
    retailers ||--o{ return_requests : requests
    return_requests ||--o{ return_receives : received
    companies ||--o{ company_returns : returned_to
    retailers ||--o{ warranty_claims : claims
    warranty_claims ||--o{ warranty_tracking : tracked
    representatives ||--o{ payroll_entries : paid
    representatives ||--o{ commissions : earns
    representatives ||--o{ advances : takes
    representatives ||--o{ bonuses : gets
```

## ১৩. Settings (অ্যাপ থেকে বদলানো যায়)

### app_settings (migration `2026_11_21`)
| কলাম | টাইপ | নোট |
|---|---|---|
| key | string | unique, যেমন `mail_host`, `sms_url`, `sms_event_delivered` |
| value | text | nullable; `mail_password` আর `sms_api_key` APP_KEY দিয়ে encrypted |

### sms_logs (migration `2026_11_21`)
| কলাম | টাইপ | নোট |
|---|---|---|
| to | string(30) | নম্বর (8801… বা 01…) |
| message | text | |
| event | string(50) | `order_approved`, `delivered`, `payment_received`, `test` |
| status | string(20) | `sent` বা `failed` |
| response | text | gateway-এর উত্তর (API key লুকানো) |

> `audit_logs` এখন ২৫টা মডেল থেকে নিজে থেকে ভরে (`App\Models\Concerns\Auditable`)। `stock_adjustments` টেবিল এখন Inventory → Stock Adjustments পেজ থেকে ব্যবহার হয়।
