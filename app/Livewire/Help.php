<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * In-app user guide (Bangla text, English menu names as they appear in the
 * app). Each section is shown only to users who can use that module.
 */
#[Layout('layouts.app')]
#[Title('Help')]
class Help extends Component
{
    /**
     * @return array<int, array{id: string, title: string, icon: string, can: string|array|null, role?: string, steps: array<int, string>, tips?: array<int, string>}>
     */
    private function sections(): array
    {
        return [
            [
                'id' => 'start', 'title' => 'শুরু করা', 'icon' => 'grid', 'can' => null,
                'steps' => [
                    'ইমেইল আর পাসওয়ার্ড দিয়ে লগইন করুন। পাসওয়ার্ড ভুলে গেলে লগইন পেজের <b>Forgot password?</b> চাপুন।',
                    '<b>Dashboard</b>-এ আজকের বিক্রি, বকেয়া, স্টক আর অপেক্ষমাণ কাজের সারাংশ দেখা যায়।',
                    'উপরের <b>সার্চ বক্সে</b> অর্ডার নম্বর, রিটেইলারের নাম, পণ্যের নাম বা SKU লিখলেই ফলাফল দেখায়। মোবাইলে 🔍 আইকন চাপুন।',
                    '🔔 ঘণ্টায় নতুন নোটিফিকেশন দেখা যায়, যেমন অনুমোদনের অপেক্ষায় থাকা অর্ডার বা কম স্টক।',
                    'মোবাইলে ব্রাউজারের মেনু থেকে <b>Add to Home screen</b> চাপলে DMS অ্যাপের মতো ইনস্টল হয়।',
                ],
                'tips' => ['ডেস্কটপে বাম দিকের ☰ বাটন চেপে সাইডবার ছোট-বড় করা যায়।'],
            ],
            [
                'id' => 'portal', 'title' => 'আমার অর্ডার ও হিসাব (রিটেইলার)', 'icon' => 'building-storefront', 'can' => 'portal.access', 'role' => 'Retailer',
                'steps' => [
                    '<b>My Account → New Order</b>-এ পণ্যে চাপ দিয়ে কার্টে যোগ করুন, পরিমাণ ঠিক করুন, তারপর <b>Place order</b> চাপুন।',
                    'অর্ডার ডিলারের অনুমোদনের অপেক্ষায় থাকে। অনুমোদনের আগ পর্যন্ত <b>Cancel order</b> দিয়ে বাতিল করা যায়।',
                    '<b>My Orders</b>-এ প্রতিটা অর্ডারের অবস্থা, কতটুকু ডেলিভারি হয়েছে, আর ইনভয়েসের PDF পাওয়া যায়।',
                    '<b>My Statement</b>-এ সব ইনভয়েস, পেমেন্ট আর বর্তমান বকেয়া দেখা যায়।',
                ],
                'tips' => ['ক্রেডিট লিমিটের বেশি অর্ডার দিলেও তা জমা হয়, তবে ডিলারকে আলাদা করে অনুমোদন দিতে হয়।'],
            ],
            [
                'id' => 'dealer-panel', 'title' => 'ডিলার প্যানেল', 'icon' => 'building-storefront', 'can' => 'portal.access', 'role' => 'Dealer',
                'steps' => [
                    'এই প্যানেলে শুধু আপনার নিজের গ্রাহক, গুদাম, মার্কেটিং স্টাফ, অর্ডার, ডেলিভারি আর আদায় দেখা যায়। অন্য ডিলার বা Agro Tech-এর নিজের ডাটা দেখা যায় না।',
                    '<b>Buy from Agro Tech → New Order</b> থেকে Agro Tech-এর কাছে মাল চান। Agro Tech অনুমোদন দিয়ে ডেলিভারি দিলে মাল একই ব্যাচ নম্বর আর মেয়াদসহ আপনার গুদামে ঢোকে।',
                    '<b>Buy from Agro Tech → My Statement</b>-এ Agro Tech-এর কাছে আপনার বকেয়া, ইনভয়েস আর পেমেন্ট দেখা যায়।',
                    'নিজের গ্রাহক যোগ করুন <b>Retailers</b>-এ, মার্কেটিংয়ের লোক যোগ করুন <b>Representatives</b>-এ। তারপর <b>Sales Order</b> → অনুমোদন → <b>Delivery</b> → <b>Collection</b>, ঠিক আগের মতোই, আপনার নিজের গুদাম থেকে।',
                ],
                'tips' => ['Agro Tech যে মার্কেটিং কর্মী আপনার এলাকায় বসিয়েছে, তারাও আপনার স্টাফের তালিকায় দেখা যায় আর আপনার গ্রাহকের অর্ডার নিতে পারে।'],
            ],
            [
                'id' => 'dealers', 'title' => 'ডিলার (হেড অফিস)', 'icon' => 'building-storefront', 'can' => 'dealers.view',
                'steps' => [
                    '<b>Dealers → + Add Dealer</b>: কোড, নাম, ফোন, এলাকা আর ক্রেডিট লিমিট দিন। চাইলে ডিলারের লগইনও তখনই খুলুন।',
                    'নতুন ডিলারের জন্য নিজে থেকে তৈরি হয়: Agro Tech-এর খাতায় তার গ্রাহক অ্যাকাউন্ট (গ্রাহক তালিকায় <i>Dealer</i> চিহ্ন দেওয়া) আর তার একটা গুদাম।',
                    'ডিলারকে মাল দিতে সাধারণ <b>Sales Order</b> খুলুন, গ্রাহক হিসেবে ডিলারের অ্যাকাউন্ট বাছুন। ডেলিভারি দিলে আমাদের গুদাম থেকে মাল কমে আর ডিলারের গুদামে একই ব্যাচ হিসেবে ঢোকে। ডিলারের বকেয়া বাড়ে।',
                    'ডিলারের পাতায় দেখা যায় Agro Tech থেকে কেনা আর পরিশোধ, ডিলারের নিজের বিক্রি, তার গুদামের মজুদ আর স্টাফ। সেখান থেকে Agro Tech-এর মার্কেটিং কর্মী ডিলারের কাছে বসানো যায়, আর নতুন লগইন যোগ করা যায়।',
                    '<b>Reports → Dealer Sales Report</b>-এ সব ডিলারের প্রাইমারি (আমাদের থেকে কেনা) আর সেকেন্ডারি (তাদের নিজের বিক্রি) পাশাপাশি দেখা যায়।',
                ],
                'tips' => ['ডিলার মাল ফেরত দিলে, ফেরত গ্রহণের সময় সেই পরিমাণ ডিলারের মজুদ থেকে কমে যায়। ডিলারের মজুদে না থাকলে ফেরত নেওয়া যায় না।'],
            ],
            [
                'id' => 'master', 'title' => 'Master Data (কোম্পানি, পণ্য)', 'icon' => 'database', 'can' => 'master-data.view',
                'steps' => [
                    'প্রথমে <b>Units</b> (পিস, কার্টন…), <b>Categories</b>, <b>Companies</b> আর <b>Brands</b> যোগ করুন।',
                    '<b>Products</b>-এ পণ্য যোগ করুন: কোম্পানি, ক্যাটাগরি, ইউনিট, SKU, ক্রয়মূল্য, বিক্রয়মূল্য আর reorder level।',
                    'দাম বদলালে আগের দাম হিস্টোরিতে থেকে যায়। নতুন অর্ডার নতুন দামে হয়।',
                    'Companies আর Products-এর তালিকা CSV ফাইলে export করা যায়।',
                ],
                'tips' => ['Reorder level দিলে স্টক সেই সীমার নিচে নামলে নিজে থেকে নোটিফিকেশন আসে।'],
            ],
            [
                'id' => 'purchase', 'title' => 'Purchase (কোম্পানি থেকে মাল কেনা)', 'icon' => 'shopping-cart', 'can' => 'purchase.view',
                'steps' => [
                    '<b>Purchase Orders → New</b>: কোম্পানি, গুদাম আর পণ্য বেছে অর্ডার তৈরি করুন।',
                    'মাল এলে অর্ডার খুলে <b>Receive</b> চাপুন। প্রতিটা পণ্যের batch নম্বর, মেয়াদ (expiry) আর প্রকৃত পরিমাণ দিন। এতে স্টক বাড়ে।',
                    'আংশিক মাল এলে যতটুকু এসেছে ততটুকু receive করুন। অর্ডার <i>Partial</i> থাকবে।',
                    'কোম্পানিকে মাল ফেরত দিতে <b>Purchase Returns → New</b> ব্যবহার করুন।',
                ],
            ],
            [
                'id' => 'inventory', 'title' => 'Inventory ও Stock Adjustment', 'icon' => 'archive-box', 'can' => 'inventory.view',
                'steps' => [
                    '<b>Warehouses</b>-এ গুদাম (central/branch) আর <b>Areas</b>-এ এলাকা যোগ করুন। কারখানার স্টোর হলে গুদামের "কাজের ধরন" (কাঁচামাল, উৎপাদন ফ্লোর, তৈরি পণ্য, ড্যামেজ) আর কারখানা বেছে দিন।',
                    '<b>Factories</b>-এ কারখানা যোগ করুন (শুধু হেড অফিস)। প্রতিটা কারখানার কার্ডে তার স্টোরগুলো দেখা যায়।',
                    '<b>Master Data → Units</b>-এর নিচে সাধারণ রূপান্তর দিন (১ টন = ১০০০ কেজি)। কোনো পণ্যের নিজস্ব প্যাক (১ বস্তা = ৫০ কেজি) পণ্যের ফর্মে দিন।',
                    'স্টক নষ্ট, মেয়াদোত্তীর্ণ বা হারিয়ে গেলে, অথবা গণনায় গরমিল পেলে <b>Stock Adjustments → New Adjustment</b>-এ যান।',
                    'গুদাম আর পণ্য বাছুন। বর্তমান স্টক দেখাবে। <b>Remove</b> বা <b>Add</b> বেছে পরিমাণ, কারণ আর তারিখ দিয়ে Save করুন।',
                    'নির্দিষ্ট batch বেছে না নিলে কমানোর সময় সবচেয়ে পুরনো batch থেকে কাটে।',
                ],
                'tips' => ['প্রতিটা adjustment স্টক লেজারে আর Audit Log-এ থাকে, তাই কে কবে কী বদলেছে পরে দেখা যায়।', 'স্টক দেখতে: Reports → Stock Report, মেয়াদের জন্য Expiry Report।'],
            ],
            [
                'id' => 'sales', 'title' => 'Sales Order (বিক্রির অর্ডার)', 'icon' => 'shopping-bag', 'can' => 'sales.view',
                'steps' => [
                    '<b>Sales Orders → New</b>: রিটেইলার আর গুদাম বাছুন, তারপর পণ্যে চাপ দিয়ে অর্ডার বানান। দাম পণ্যের বিক্রয়মূল্য থেকে বসে।',
                    'রিটেইলারের বকেয়া আর এই অর্ডার মিলে ক্রেডিট লিমিট ছাড়ালে অর্ডার <i>Credit hold</i>-এ যায়। ডিলার চাইলে তবুও অনুমোদন দিতে পারেন।',
                    'অনুমোদনকারী অর্ডার খুলে <b>Discount</b> (৳ বা %) দিয়ে <b>Apply</b> করতে পারেন, তারপর <b>Approve</b> চাপুন।',
                    'অনুমোদনের পরে <b>Deliver</b> বাটন আসে।',
                ],
                'tips' => ['ডিসকাউন্ট একাধিক ডেলিভারিতে মালের মূল্যের অনুপাতে ভাগ হয়, আর শেষ ডেলিভারিতে বাকিটা মিলিয়ে নেয়।'],
            ],
            [
                'id' => 'delivery', 'title' => 'Delivery ও Delivery Invoice', 'icon' => 'truck', 'can' => 'delivery.view',
                'steps' => [
                    'অনুমোদিত অর্ডার খুলে <b>Deliver</b> চাপুন। প্রতিটা পণ্যের কতটুকু দিচ্ছেন লিখে Save করুন। আংশিক ডেলিভারিও দেওয়া যায়।',
                    'Save করলে স্টক কমে (পুরনো batch আগে), সেলস ইনভয়েস তৈরি হয়, রিটেইলারের বকেয়া বাড়ে, আর একটা <b>Delivery Invoice</b> তৈরি হয়।',
                    '<b>Delivery → Delivery Invoices</b> থেকে ইনভয়েস খুলে <b>Print PDF</b> চাপুন। একটা অর্ডারের সব ইনভয়েস একসাথে প্রিন্ট করতে অর্ডারের পেজে <b>Print All</b> চাপুন।',
                ],
                'tips' => ['ইনভয়েসের PDF-এ লোগো আর নিচের লেখা আসে Settings → Company Profile থেকে।'],
            ],
            [
                'id' => 'collection', 'title' => 'Collection (টাকা আদায়)', 'icon' => 'banknotes', 'can' => 'collection.view',
                'steps' => [
                    '<b>Collections → New</b>: রিটেইলার বাছুন, তারিখ, টাকার পরিমাণ আর মাধ্যম (cash, bank বা mobile) দিন।',
                    'টাকা নিজে থেকে সবচেয়ে পুরনো বকেয়া ইনভয়েসে জমা হয়, আর রিটেইলারের লেজার আপডেট হয়।',
                    'রিটেইলারের পুরো হিসাব দেখতে: <b>Retailers</b> → রিটেইলার খুলুন → <b>Ledger</b>।',
                ],
            ],
            [
                'id' => 'returns', 'title' => 'Return ও Warranty', 'icon' => 'shield-check', 'can' => ['returns.view', 'warranty.view'],
                'steps' => [
                    '<b>Return Requests → New</b>: রিটেইলার কোন মাল কেন ফেরত দিচ্ছে লিখুন।',
                    'অনুমোদনের পরে মাল এলে <b>Receive</b> চাপুন। প্রতিটা পণ্যের জন্য বাছুন: আবার বিক্রয়যোগ্য (restock), নষ্ট (damage), নাকি কোম্পানিকে ফেরত (company return)।',
                    'কোম্পানিকে ফেরত যাওয়া মাল <b>Company Returns</b>-এ জমা হয়।',
                    '<b>Warranty Claims</b>-এ পণ্যের সমস্যা লিখে claim করুন। পরে অনুমোদন, বাতিল, বদলে দেওয়া বা মেরামতের অবস্থা আপডেট করুন।',
                ],
            ],
            [
                'id' => 'people', 'title' => 'Retailers ও Representatives', 'icon' => 'user-group', 'can' => ['retailers.view', 'representatives.view'],
                'steps' => [
                    'রিটেইলার যোগ করার সময় এলাকা, দায়িত্বপ্রাপ্ত রিপ্রেজেন্টেটিভ আর <b>ক্রেডিট লিমিট</b> দিন।',
                    'রিপ্রেজেন্টেটিভের বেস বেতন, কমিশনের হার আর মাসিক টার্গেট দিন। তার পারফরম্যান্স রিপোর্টে দেখা যায়।',
                    'নতুন রিটেইলার বা রিপ্রেজেন্টেটিভ যোগ করার সময় <b>Create a login for this …</b> টিক দিয়ে ইমেইল আর পাসওয়ার্ড দিলে তার লগইনও তৈরি হয়ে যায়, সঠিক রোলসহ।',
                ],
            ],
            [
                'id' => 'payroll', 'title' => 'Payroll (বেতন)', 'icon' => 'wallet', 'can' => 'payroll.view',
                'steps' => [
                    'মাসের মধ্যে দেওয়া অগ্রিম <b>Advances</b>-এ আর বোনাস <b>Bonuses</b>-এ লিখে রাখুন।',
                    'মাস শেষে <b>Payroll Runs → Generate</b>: মাস বাছুন। বেস বেতন + কমিশন + বোনাস − অগ্রিম, এভাবে নিজে থেকে হিসাব হয়।',
                    'বেতন দেওয়ার পরে এন্ট্রি খুলে <b>Mark as Paid</b> চাপুন।',
                ],
            ],
            [
                'id' => 'reports', 'title' => 'Reports', 'icon' => 'chart-bar', 'can' => 'reports.view',
                'steps' => [
                    '১৪টা রিপোর্ট আছে: Sales, Purchase, Stock, Expiry, Collections &amp; Due, Return, Warranty, Representative, Retailer, Company, Area, Product, Payroll আর Profit &amp; Loss।',
                    'উপরে তারিখ আর অন্যান্য ফিল্টার দিন। প্রতিটা রিপোর্ট <b>Export</b> দিয়ে CSV (Excel-এ খোলা যায়) করা যায়।',
                ],
            ],
            [
                'id' => 'settings', 'title' => 'Settings ও ইউজার', 'icon' => 'cog', 'can' => 'settings.manage',
                'steps' => [
                    '<b>Company Profile</b>: কোম্পানির নাম, ঠিকানা, লোগো আর ইনভয়েসের নিচের লেখা।',
                    '<b>Users</b>: নতুন ইউজার তৈরি, রোল দেওয়া (Dealer, Manager, Sales Representative, Retailer) আর পাসওয়ার্ড বদলানো।',
                    'কেউ "Forgot password" চাপলে আপনি নোটিফিকেশন পাবেন। তখন Users থেকে তার নতুন পাসওয়ার্ড দিন।',
                    '<b>Email &amp; SMS</b>: SMTP দিলে পাসওয়ার্ড রিসেটের লিংক আর নোটিফিকেশন ইমেইলে যায়। SMS gateway দিলে রিটেইলার অর্ডার অনুমোদন, ডেলিভারি আর পেমেন্টের SMS পায়। প্রথমে <b>Send test</b> দিয়ে পরীক্ষা করুন।',
                    '<b>Audit Log</b>: কে কখন কোন রেকর্ড তৈরি, বদল বা মুছেছে, আর কোন ঘর কী থেকে কী হয়েছে। মডিউল, ইউজার আর তারিখ দিয়ে ফিল্টার করা যায়।',
                    '<b>Login History</b>: কে কখন কোথা থেকে লগইন করেছে বা ব্যর্থ হয়েছে।',
                ],
            ],
            [
                'id' => 'backup', 'title' => 'Backup ও Restore', 'icon' => 'archive-box', 'can' => 'settings.manage',
                'steps' => [
                    'প্রতিদিন রাত ২টায় নিজে থেকে ব্যাকআপ হয়, আর ৩০ দিনের ব্যাকআপ রাখা থাকে।',
                    '<b>More → Backup &amp; Restore → Download Backup Now</b> দিয়ে এখনকার ব্যাকআপ নামিয়ে USB বা Google Drive-এ রাখুন।',
                    'Restore করতে তালিকা থেকে একটা ব্যাকআপে <b>Restore</b> চাপুন, অথবা ফাইল আপলোড করুন। তারপর নিজের পাসওয়ার্ড আর <b>RESTORE</b> লিখে নিশ্চিত করুন।',
                ],
                'tips' => ['Restore-এর আগে বর্তমান ডাটার একটা ব্যাকআপ নিজে থেকে নেওয়া হয় ("Before restore"), তাই ভুল হলে আবার আগের অবস্থায় ফেরা যায়।'],
            ],
            [
                'id' => 'account', 'title' => 'আমার অ্যাকাউন্ট', 'icon' => 'user', 'can' => null,
                'steps' => [
                    'নিজের পাসওয়ার্ড বদলাতে: উপরে ডানে নিজের নাম → <b>Change Password</b>।',
                    'কোন নোটিফিকেশন পাবেন, আর তা ইমেইলেও যাবে কিনা: <b>Account → Notification Settings</b>।',
                ],
            ],
            [
                'id' => 'faq', 'title' => 'সমস্যা ও সমাধান', 'icon' => 'question-mark-circle', 'can' => null,
                'steps' => [
                    '<b>"Insufficient stock" দেখাচ্ছে:</b> গুদামে ওই পণ্যের স্টক কম। Purchase থেকে মাল receive করুন, অথবা গণনায় ভুল থাকলে Stock Adjustment করুন।',
                    '<b>অর্ডার Credit hold-এ আটকে আছে:</b> রিটেইলারের বকেয়া বেশি। আগে টাকা আদায় করুন, অথবা ডিলার অনুমোদন দিন।',
                    '<b>ব্রাউজার "Not secure" দেখাচ্ছে:</b> সার্ভারের সার্টিফিকেট নিজস্ব (self-signed)। <b>Advanced → Proceed</b> চাপলে নিরাপদে চলবে।',
                    '<b>কোনো পেজ খুলছে না (403):</b> আপনার রোলে ওই পেজের অনুমতি নেই। ডিলারের সাথে যোগাযোগ করুন।',
                ],
            ],
        ];
    }

    public function render()
    {
        $user = Auth::user();

        $sections = collect($this->sections())->filter(function ($s) use ($user) {
            if (isset($s['role'])) {
                return $user->hasRole($s['role']);
            }

            if ($s['can'] === null) {
                return true;
            }

            return collect((array) $s['can'])->contains(fn ($p) => $user->can($p));
        })->values()->all();

        return view('livewire.help', compact('sections'));
    }
}
