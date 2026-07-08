<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;

final class MarketingController extends Controller
{
    public function home(): View
    {
        return view('marketing.home', [
            'businessTypes' => $this->businessTypes(),
            'features' => $this->features(),
            'testimonials' => $this->testimonials(),
            'plans' => $this->plans(),
            'faqs' => $this->faqs(),
        ]);
    }

    public function about(): View
    {
        return view('marketing.about');
    }

    /** @return list<array{label: string, icon: string}> */
    private function businessTypes(): array
    {
        $i = fn (string $path): string => '<svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="'.$path.'"/></svg>';

        return [
            ['label' => 'Retail shops', 'icon' => $i('M4 7h16l-1 4H5L4 7Zm1 4v8h14v-8M9 21v-5h6v5')],
            ['label' => 'Supermarkets', 'icon' => $i('M3 3h2l2 12h11l2-8H6M9 21a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm8 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z')],
            ['label' => 'Pharmacies', 'icon' => $i('M12 8v8m-4-4h8M6 4h12v4H6zM7 8h10v12H7z')],
            ['label' => 'Restaurants', 'icon' => $i('M4 3v7a2 2 0 0 0 2 2h0v9m0-18v6m3-6v6M16 3c-1 2-1 4-1 6s0 2 2 2v10')],
            ['label' => 'Fashion stores', 'icon' => $i('M8 4 4 8l2 2 1-1v11h10V9l1 1 2-2-4-4-2 2a2 2 0 0 1-4 0L8 4Z')],
            ['label' => 'Electronics', 'icon' => $i('M4 5h16v10H4zM2 19h20M9 9h6v2H9z')],
            ['label' => 'Wholesalers', 'icon' => $i('M3 9 12 4l9 5-9 5-9-5Zm0 6 9 5 9-5')],
            ['label' => 'Service businesses', 'icon' => $i('M14 7a3 3 0 0 0-4 4l-6 6 2 2 6-6a3 3 0 0 0 4-4l-2 2-2-2 2-2Z')],
            ['label' => 'Multi-branch SMEs', 'icon' => $i('M3 21V9l6-4 6 4v12M9 21v-4h2v4m6 0V13l4-2v10')],
        ];
    }

    /** @return list<array{icon: string, title: string, body: string}> */
    private function features(): array
    {
        $i = fn (string $path): string => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="'.$path.'"/></svg>';

        return [
            ['icon' => $i('M4 7h16M4 7l1-3h14l1 3M4 7v12a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V7M9 11h6'), 'title' => 'Point of Sale', 'body' => 'A fast, friendly till for your counter. Ring up sales, take part-payments and print or send receipts — online or off.'],
            ['icon' => $i('M20 7 12 3 4 7m16 0-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'), 'title' => 'Products & Catalog', 'body' => 'Organise products and services with categories, variants, SKUs, images and flexible pricing that fits how you sell.'],
            ['icon' => $i('M9 3v18m6-18v18M3 9h18M3 15h18'), 'title' => 'Inventory', 'body' => 'Track stock across every branch, record movements and adjustments, and get low-stock alerts before you run out.'],
            ['icon' => $i('M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.5L19 8.5V19a2 2 0 0 1-2 2Z'), 'title' => 'Sales & Invoicing', 'body' => 'Create invoices and receipts, record payments, handle refunds and returns, and always know who owes you.'],
            ['icon' => $i('M15 19v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m14-6h6m-3-3v6M9.5 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z'), 'title' => 'Customers & CRM', 'body' => 'Keep customer records, groups, purchase history and follow-ups — plus simple support tickets to stay on top of care.'],
            ['icon' => $i('M3 9 12 4l9 5-9 5-9-5Zm0 6 9 5 9-5M12 14v7'), 'title' => 'Vendors & Procurement', 'body' => 'Manage suppliers, raise purchase orders, receive goods and record vendor payments — the buying side, handled.'],
            ['icon' => $i('M12 3v18m5-14H9.5a2.5 2.5 0 0 0 0 5h5a2.5 2.5 0 0 1 0 5H6'), 'title' => 'Expenses & Finance', 'body' => 'Log expenses, run a real chart of accounts and journal entries, and see profit and cash flow at a glance.'],
            ['icon' => $i('M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0Zm-2 6H10a5 5 0 0 0-5 5v3h14v-3a5 5 0 0 0-5-5Z'), 'title' => 'HR & Payroll', 'body' => 'Keep staff records, run payroll, handle deductions and branch transfers, and generate payslips in a few clicks.'],
            ['icon' => $i('M3 3v18h18M7 14l3-3 3 3 5-6'), 'title' => 'Analytics', 'body' => 'Dashboards that turn your daily activity into clear KPIs — best-sellers, revenue trends and where the money goes.'],
        ];
    }

    /** @return list<array{quote: string, name: string, role: string, initials: string, photo: string}> */
    private function testimonials(): array
    {
        return [
            ['quote' => 'Before Storeboot I never really knew my profit. Now I open the app and everything is there — sales, stock, expenses. It changed how I run my shop.', 'name' => 'Amaka Obi', 'role' => 'Owner, FreshMart Grocery', 'initials' => 'AO', 'photo' => 'media/photos/portrait-amaka.jpg'],
            ['quote' => 'The offline till is a lifesaver. Even when the network is down, my cashiers keep selling and everything syncs later. My three branches finally feel like one business.', 'name' => 'Tunde Balogun', 'role' => 'Owner, Balogun Provisions', 'initials' => 'TB', 'photo' => 'media/photos/portrait-tunde.jpg'],
            ['quote' => 'Setup took one afternoon. We moved off spreadsheets and notebooks, and now my team records every sale without calling me every hour.', 'name' => 'Ibrahim Sanni', 'role' => 'Owner, Sanni & Sons Store', 'initials' => 'IS', 'photo' => 'media/photos/portrait-ibrahim.jpg'],
        ];
    }

    /** @return list<array{name: string, tagline: string, monthly: string, yearly: string, unit: string, cta: string, featured: bool, features: list<string>}> */
    private function plans(): array
    {
        return [
            [
                'name' => 'Starter', 'tagline' => 'For a single shop finding its feet.',
                'monthly' => '₦0', 'yearly' => '₦0', 'unit' => '/forever', 'cta' => 'Start for free', 'featured' => false,
                'features' => ['Point of Sale', 'Products & inventory', 'Up to 1 branch', 'Basic sales reports', '2 team members'],
            ],
            [
                'name' => 'Growth', 'tagline' => 'For growing businesses that need more.',
                'monthly' => '₦12,000', 'yearly' => '₦9,600', 'unit' => '/month', 'cta' => 'Start free trial', 'featured' => true,
                'features' => ['Everything in Starter', 'Up to 3 branches', 'Customers & invoicing', 'Procurement & finance', 'Analytics dashboard', 'Unlimited team members'],
            ],
            [
                'name' => 'Scale', 'tagline' => 'For multi-branch operations at full speed.',
                'monthly' => '₦30,000', 'yearly' => '₦24,000', 'unit' => '/month', 'cta' => 'Talk to sales', 'featured' => false,
                'features' => ['Everything in Growth', 'Unlimited branches', 'HR & payroll', 'Offline POS sync', 'Roles & permissions', 'Priority support'],
            ],
        ];
    }

    /** @return list<array{q: string, a: string}> */
    private function faqs(): array
    {
        return [
            ['q' => 'Do I need any technical knowledge to use Storeboot?', 'a' => 'Not at all. Storeboot is designed to be as easy as the apps you already use every day. If you can send a WhatsApp message, you can run your shop on Storeboot.'],
            ['q' => 'Can I use the point of sale without internet?', 'a' => 'Yes. Our till is built offline-first — you can keep selling even when the network drops, and your data syncs safely to the cloud once you are back online.'],
            ['q' => 'Does it work for more than one branch?', 'a' => 'Absolutely. Manage every branch from one account, move stock between them, set roles for your staff, and see each branch or the whole business at a glance.'],
            ['q' => 'What does it cost to get started?', 'a' => 'You can start completely free for 14 days with no card required. After that, choose a plan that fits your size — and only pay for the modules you actually switch on.'],
            ['q' => 'Is my business data safe?', 'a' => 'Your data is encrypted and backed up in the cloud, with role-based access so your team only sees what they should. You stay in full control of your business information.'],
            ['q' => 'Which currency and country does it support?', 'a' => 'Storeboot supports Naira and several other currencies, with the right timezone set automatically when you sign up. It is built for African businesses first.'],
        ];
    }
}
