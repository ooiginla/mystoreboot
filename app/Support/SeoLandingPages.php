<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

final class SeoLandingPages
{
    /** @return list<string> */
    public static function slugs(): array
    {
        return array_keys(self::pages());
    }

    /** @return array<string, mixed> */
    public static function get(string $slug): array
    {
        $page = self::pages()[$slug] ?? null;

        if ($page === null) {
            throw new InvalidArgumentException("Unknown SEO landing page [{$slug}].");
        }

        return ['slug' => $slug, ...$page];
    }

    /** @return array<string, array<string, mixed>> */
    public static function pages(): array
    {
        return [
            'online-store' => [
                'title' => 'Create an Online Store in Nigeria | Storeboot',
                'h1' => 'Create an Online Store in Nigeria',
                'meta_description' => 'Build a professional Nigerian online store with products, secure payments, inventory, orders, delivery details and SEO tools in one simple platform.',
                'eyebrow' => 'Online store builder for Nigeria',
                'hero' => 'Turn your Instagram, WhatsApp or physical-shop audience into customers on a storefront you control. Add products quickly, accept online orders and manage every sale from the same place.',
                'image' => 'media/seo/online-store.webp',
                'image_alt' => 'Nigerian fashion entrepreneur preparing products beside her laptop and phone',
                'proof' => ['Free store and subdomain', 'Unlimited products and orders', 'Nigerian payment flow'],
                'problem_title' => 'Social media gets attention. It should not be your entire shop.',
                'problems' => [
                    'Many Nigerian sellers begin on WhatsApp, Instagram or TikTok because that is where their first customers already spend time. It works—until product questions, bank-transfer screenshots, delivery addresses and “is this still available?” messages become a full-time administrative job. Orders disappear inside chats, two people promise the same last item, and the owner cannot confidently say what sold or what remains.',
                    'A generic website does not automatically solve that problem. If the site is difficult to update, disconnected from stock, or built around payment methods your customers do not use, it becomes an expensive brochure. The useful version of ecommerce is an operating workflow: a customer discovers an item, understands it, pays or places an order, receives confirmation, and your team sees exactly what to fulfil.',
                    'You also need independence. Algorithms change, social accounts can be restricted, and a feed is not organised like a catalogue. Your own store gives every product a stable link, gives Google pages it can index, and gives returning customers one dependable place to shop.',
                ],
                'solution_title' => 'Storeboot connects the storefront to the work behind it',
                'solutions' => [
                    'Storeboot gives your business a mobile-friendly storefront and a back office in the same system. Create categories, products, variants, prices and images once; the information customers see is the information your team manages. When an order arrives, it is not another message to interpret—it becomes a record you can process, fulfil and analyse.',
                    'You can begin with a Storeboot subdomain and connect a custom domain when you are ready. AI-assisted product setup can turn photos into draft names, descriptions and categories, while editable store pages help you publish shipping, returns, privacy and other policies without staring at a blank page.',
                    'Because orders, customers, inventory and reporting live together, growing beyond a one-person business does not require rebuilding everything. Staff can work with controlled access, a physical branch can use the POS, and management can see online and in-store activity from one account.',
                ],
                'features' => [
                    ['title' => 'A storefront that feels like your brand', 'body' => 'Publish a clean, responsive catalogue with your colours, logo, product images and business information. Customers can browse comfortably on the phones they already use, while a custom domain gives the store a memorable home.'],
                    ['title' => 'Faster product publishing', 'body' => 'Organise products with categories, variants, SKUs, images and flexible pricing. Upload a product photo and use AI to draft useful catalogue details, then review every field before publishing.'],
                    ['title' => 'Orders without chat confusion', 'body' => 'Keep customer details, line items, totals, payment state and fulfilment progress in one order record. Your team can work from the same source instead of reconstructing purchases from screenshots.'],
                    ['title' => 'Payments made for Nigerian commerce', 'body' => 'Use Storeboot Payments for an integrated checkout experience and keep the payment attached to the order it belongs to. Customers get clarity and you spend less time matching alerts to names.'],
                    ['title' => 'Stock that follows sales', 'body' => 'Track quantities, variants and stock movements from the back office. If you also sell at a counter, Storeboot helps you manage the catalogue and inventory as one business rather than two separate worlds.'],
                    ['title' => 'Search-ready product information', 'body' => 'Create descriptive product pages and store policies, with SEO metadata and structured product information that help search engines understand what you sell. Good photography and original descriptions remain under your control.'],
                ],
                'workflow' => [
                    ['title' => 'Set up your identity', 'body' => 'Choose your store address, add your logo, brand colours and contact details, then explain what makes the business different.'],
                    ['title' => 'Build the catalogue', 'body' => 'Add products manually or start from photos, organise categories and variants, set prices and confirm available quantities.'],
                    ['title' => 'Configure selling rules', 'body' => 'Connect payment details and publish clear shipping, returns, privacy and terms pages so customers know what to expect.'],
                    ['title' => 'Share, fulfil and learn', 'body' => 'Put product links in social posts and customer chats, process incoming orders, and use sales data to decide what to restock or promote.'],
                ],
                'examples' => [
                    ['title' => 'Fashion and accessories', 'body' => 'Show colour or size variants, group new arrivals into collections and send a customer directly to the exact item discussed on WhatsApp.'],
                    ['title' => 'Beauty and personal care', 'body' => 'Explain ingredients and usage clearly, keep related products together, and maintain an order history for customers who return to replenish favourites.'],
                    ['title' => 'Pre-order and dropshipping', 'body' => 'Present an organised catalogue, collect complete customer information and separate each order from the conversation that introduced it.'],
                ],
                'comparison_intro' => 'A social page is valuable for discovery, but it is not designed to be your order database. A custom-built ecommerce site can be powerful, but it usually requires a larger budget and ongoing technical maintenance. Storeboot sits between those extremes: quick enough to launch, structured enough to operate, and connected to the wider business tools you may need later.',
                'comparison' => [
                    ['need' => 'Products and discovery', 'storeboot' => 'Structured catalogue and shareable product pages', 'alternative' => 'Posts and chat albums become difficult to search'],
                    ['need' => 'Order records', 'storeboot' => 'Orders, customers and payments stay connected', 'alternative' => 'Manual spreadsheet or message reconstruction'],
                    ['need' => 'Stock visibility', 'storeboot' => 'Inventory can move with recorded sales', 'alternative' => 'Separate counts that drift from reality'],
                    ['need' => 'Room to grow', 'storeboot' => 'Add POS, staff, branches and finance in one platform', 'alternative' => 'Replace or integrate several disconnected tools'],
                ],
                'faqs' => [
                    ['q' => 'Can I create an online store in Nigeria for free?', 'a' => 'Yes. Storeboot Basic includes a free online store, free subdomain, unlimited products and unlimited orders. You can start without a card and upgrade when you need advanced in-house operations such as branches and POS.'],
                    ['q' => 'Do I need a web developer?', 'a' => 'No. Storeboot is designed for business owners and teams. You add your business information and products through guided screens; hosting and the storefront structure are handled for you.'],
                    ['q' => 'Can I use my own domain name?', 'a' => 'Yes. You can begin on a free Storeboot subdomain and connect a custom domain for a more branded address.'],
                    ['q' => 'Can customers pay online?', 'a' => 'Yes. Storeboot Payments supports an integrated payment flow so the payment and order can be managed together. Availability and onboarding requirements may depend on your business details.'],
                    ['q' => 'Will my Storeboot shop appear on Google?', 'a' => 'Storeboot provides search-friendly pages, editable metadata and structured product information. Indexing and ranking are ultimately decided by search engines and also depend on original content, product demand, links and ongoing promotion.'],
                    ['q' => 'Can I manage physical-store sales too?', 'a' => 'Yes. When you need counter sales, till management, staff controls or multiple branches, the Enterprise plan adds POS and broader back-office tools to the same account.'],
                ],
                'related' => ['inventory-management', 'pos', 'small-business-software'],
            ],

            'inventory-management' => [
                'title' => 'Inventory Management Software in Nigeria | Storeboot',
                'h1' => 'Inventory Management Software for Nigerian Businesses',
                'meta_description' => 'Track stock, variants, transfers, adjustments, suppliers and purchasing across Nigerian business locations with Storeboot inventory software.',
                'eyebrow' => 'Know what you have, where it is and why it moved',
                'hero' => 'Replace uncertain shelf counts and scattered spreadsheets with a live stock record connected to sales, purchasing, branches and reporting.',
                'image' => 'media/seo/inventory-management.webp',
                'image_alt' => 'Nigerian shop owner scanning stock in an organised storeroom',
                'proof' => ['Multi-location stock', 'Low-stock visibility', 'Movement history'],
                'problem_title' => 'Inventory losses rarely announce themselves',
                'problems' => [
                    'A business can be busy and still lose money through stock. Items arrive without a complete receiving record, cashiers sell from memory, damaged units are never adjusted, and transfers between branches are agreed in chat but not reconciled. By month end, the spreadsheet says one thing, the shelf says another, and nobody can explain the difference.',
                    'The problem becomes expensive in both directions. Under-counting leads to avoidable purchases and cash trapped in slow-moving goods. Over-counting creates stock-outs, disappointed customers and emergency buying at poor prices. If the system only stores a final quantity, managers still cannot tell whether a change came from a sale, receipt, transfer, return or correction.',
                    'Useful inventory management is therefore more than a list of numbers. It needs product identity, locations, traceable movements and connections to the transactions that caused them. It should help staff record work as it happens and help owners investigate exceptions without standing in every branch.',
                ],
                'solution_title' => 'A stock ledger tied to the rest of the business',
                'solutions' => [
                    'Storeboot organises products, variants and SKUs, then keeps stock by branch and location. Sales can reduce available stock, purchase receipts can increase it, transfers can move it, and adjustments can document the reality found during a count. The result is not merely “12 units”; it is a history of how the business reached 12 units.',
                    'Procurement connects the buying side. Maintain suppliers, raise purchase orders, receive goods and record vendor payments. That creates a cleaner hand-off between the person who orders, the person who receives and the person who pays—an important control even in a small team.',
                    'Dashboards and reports make the record actionable. Identify low stock, review movement patterns and compare branches without merging files by hand. As the business grows, staff roles and permissions help separate responsibilities while management keeps a consolidated view.',
                ],
                'features' => [
                    ['title' => 'Products, variants and SKUs', 'body' => 'Keep sizes, colours, packs or other variations distinct so a general product count does not hide which exact option is available. Use SKUs to make receiving, counting and selling more consistent.'],
                    ['title' => 'Branch and location balances', 'body' => 'See stock where it physically belongs. A warehouse, shop floor, branch store or service area can have its own balance, making replenishment decisions more precise.'],
                    ['title' => 'Traceable stock movements', 'body' => 'Record receipts, sales, transfers, returns and adjustments as movements with context. When a quantity looks wrong, investigate the path instead of overwriting the number.'],
                    ['title' => 'Purchasing and suppliers', 'body' => 'Store vendor details, create purchase orders, receive the delivered quantities and follow supplier payments. Buying activity becomes part of the operating record.'],
                    ['title' => 'Low-stock decisions', 'body' => 'Use current balances and sales information to spot replenishment needs before the shelf is empty. Focus purchasing cash on items the business actually needs.'],
                    ['title' => 'Recipes and production', 'body' => 'Food businesses can define ingredient relationships and record production runs. Selling a recipe item can consume its ingredients, helping stock reflect what the kitchen or bakery really used.'],
                ],
                'workflow' => [
                    ['title' => 'Standardise the catalogue', 'body' => 'Create clean product and variant records with units, SKUs and selling prices so everyone identifies items the same way.'],
                    ['title' => 'Place opening stock', 'body' => 'Count what is physically present and assign it to the correct branch or location as a reliable starting point.'],
                    ['title' => 'Record every cause', 'body' => 'Receive purchases, complete sales, transfer goods and document adjustments instead of editing balances without explanation.'],
                    ['title' => 'Review and count', 'body' => 'Use low-stock and movement information for daily decisions, then run regular physical counts to find and correct exceptions.'],
                ],
                'examples' => [
                    ['title' => 'A three-branch supermarket', 'body' => 'See which branch has excess stock, transfer before buying again, and compare recorded movements when a count produces a variance.'],
                    ['title' => 'A fashion retailer', 'body' => 'Separate the same design by size and colour, so staff can answer a customer accurately and purchasing can reorder the variants that actually move.'],
                    ['title' => 'A restaurant or bakery', 'body' => 'Connect menu or finished items to ingredients, record production and understand why flour, oil, proteins or packaging changed.'],
                ],
                'comparison_intro' => 'Spreadsheets are flexible and familiar, but they depend on perfect manual updates and struggle with simultaneous activity. Inventory-only software can count stock well while leaving sales, purchasing and finance elsewhere. Storeboot is intended for operators who want inventory to share the same transaction trail as the rest of the business.',
                'comparison' => [
                    ['need' => 'Current quantities', 'storeboot' => 'Updated by recorded operational transactions', 'alternative' => 'Depends on someone editing the right cell'],
                    ['need' => 'Multiple locations', 'storeboot' => 'Balances and transfers by branch/location', 'alternative' => 'Separate files or manually added columns'],
                    ['need' => 'Audit trail', 'storeboot' => 'Movement type, context and transaction history', 'alternative' => 'A changed number may have no explanation'],
                    ['need' => 'Purchasing', 'storeboot' => 'Suppliers, POs, receipts and payments connected', 'alternative' => 'Buying records live in another tool or notebook'],
                ],
                'faqs' => [
                    ['q' => 'Can Storeboot track inventory across multiple branches?', 'a' => 'Yes. Enterprise supports unlimited branches, location-level inventory and transfers, with a consolidated management view.'],
                    ['q' => 'Does stock reduce when I make a sale?', 'a' => 'For stock-tracked products, completed sales can create the relevant inventory movement. Recipe-based food items can consume defined ingredients rather than a finished-goods balance.'],
                    ['q' => 'Can I record damaged, expired or missing stock?', 'a' => 'Yes. Adjustments let the team align the system with a verified physical count while preserving a movement record instead of silently changing a total.'],
                    ['q' => 'Does Storeboot manage suppliers and purchase orders?', 'a' => 'Yes. The procurement workflow covers supplier records, purchase orders, goods received and vendor payments.'],
                    ['q' => 'Can it handle product variants?', 'a' => 'Yes. Products can have variants such as size, colour or pack, each with its own identifying and stock information.'],
                    ['q' => 'Is inventory included in the free plan?', 'a' => 'Basic includes inventory management for getting started. Enterprise is designed for operations that need branches, POS, suppliers, procurement, finance and more advanced controls.'],
                ],
                'related' => ['retail-management', 'pos', 'bakery-management'],
            ],

            'pos' => [
                'title' => 'POS Software for Nigerian Retail Businesses | Storeboot',
                'h1' => 'POS Software for Nigerian Retail Businesses',
                'meta_description' => 'Run faster counter sales, cashier tills, receipts, returns, payments, customers, stock and branch reporting with Storeboot POS software.',
                'eyebrow' => 'A faster till with a complete back office',
                'hero' => 'Keep the queue moving, keep cashiers accountable and keep every sale connected to stock, customers and business reports—even when connectivity is unreliable.',
                'image' => 'media/seo/pos.webp',
                'image_alt' => 'Nigerian cashier accepting payment at a modern retail POS counter',
                'proof' => ['Offline-first selling', 'Till sessions and receipts', 'Returns and part-payments'],
                'problem_title' => 'A POS should do more than print a receipt',
                'problems' => [
                    'At a busy counter, every extra tap matters. Cashiers need to find the right product, confirm quantities, accept the customer’s payment method and finish the sale without turning a short queue into a long one. When the network drops, the business should not have to choose between stopping sales and recording them later from memory.',
                    'The owner’s questions begin after checkout. How much cash should be in this till? Which cashier processed a return? Did a card payment cover the whole order? Why is the shelf balance different? A stand-alone billing app may answer what was sold while leaving inventory, customer balances and accounting disconnected.',
                    'Controls also need to fit real operations. Staff should have appropriate permissions, till sessions need clear opening and closing figures, and exceptions such as refunds, returns or credit sales should leave a trail. Speed without accountability creates a different kind of cost.',
                ],
                'solution_title' => 'Sell quickly at the counter and keep the transaction useful',
                'solutions' => [
                    'Storeboot POS gives cashiers a focused selling flow and gives management the connected record behind it. Products, variants, prices and stock come from the shared catalogue. A completed sale can update inventory, payments remain attached to the order, and receipts give the customer a clear record.',
                    'Offline-first support helps the till keep working through unstable connectivity and sync safely when access returns. Till sessions make each cashier’s shift easier to reconcile, while roles and permissions reduce unnecessary access to sensitive business actions.',
                    'Because Storeboot is more than a checkout screen, you can follow the same transaction into returns, customer history, branch performance, expenses and financial reporting. That reduces repeated data entry and gives an owner one operating picture.',
                ],
                'features' => [
                    ['title' => 'Fast product selection', 'body' => 'Search a shared product catalogue, select the correct variant and build the basket with fewer distractions. Clear prices and totals help the cashier confirm the order.'],
                    ['title' => 'Flexible payments', 'body' => 'Record cash and supported non-cash payment methods, handle part-payments and retain the payment history against the sale for easier reconciliation.'],
                    ['title' => 'Offline-first workflow', 'body' => 'Continue essential checkout work when the connection is unreliable, then sync with the cloud when connectivity returns instead of rebuilding a day of sales.'],
                    ['title' => 'Till and cashier control', 'body' => 'Open and close till sessions, associate activity with staff and use permissions to decide who can perform sensitive actions.'],
                    ['title' => 'Returns, refunds and credit', 'body' => 'Process after-sale situations through recorded workflows so stock, customer balances and financial entries can follow the business event.'],
                    ['title' => 'Branch-wide reporting', 'body' => 'Review sales and performance by branch or across the business. The same platform also tracks inventory, expenses, purchasing and customer history.'],
                ],
                'workflow' => [
                    ['title' => 'Prepare the till', 'body' => 'Assign staff access, select the branch, open a till session and confirm the opening position before trading starts.'],
                    ['title' => 'Build the basket', 'body' => 'Find products, choose variants and quantities, attach a known customer when useful, and confirm the total.'],
                    ['title' => 'Take payment', 'body' => 'Record the payment method or part-payments, complete the sale and print or send the receipt.'],
                    ['title' => 'Close with evidence', 'body' => 'Reconcile the till session and review exceptions, returns and branch performance from the recorded transaction trail.'],
                ],
                'examples' => [
                    ['title' => 'Neighbourhood supermarket', 'body' => 'Move quickly through repeat purchases, keep cashier shifts separate and connect each completed basket to the stock leaving the shelves.'],
                    ['title' => 'Fashion boutique', 'body' => 'Sell the correct size and colour variant, add a returning customer and manage a later exchange or return with an order record.'],
                    ['title' => 'Electronics retailer', 'body' => 'Maintain accurate item identity and prices, record split payment where needed, and let managers review sales without waiting for an end-of-week spreadsheet.'],
                ],
                'comparison_intro' => 'A calculator and receipt book are quick until the owner needs a trustworthy report. A bank terminal confirms a payment but does not manage the sale around it. Storeboot POS combines the checkout flow with inventory and back-office records, so one transaction can answer both the cashier’s immediate need and management’s later questions.',
                'comparison' => [
                    ['need' => 'Checkout', 'storeboot' => 'Products, totals, payments and receipt in one flow', 'alternative' => 'Price calculation and payment proof remain separate'],
                    ['need' => 'Network interruption', 'storeboot' => 'Offline-first selling and later sync', 'alternative' => 'Stop trading or write transactions down'],
                    ['need' => 'Cashier accountability', 'storeboot' => 'Staff access and till sessions', 'alternative' => 'Shared logins and end-of-day guesswork'],
                    ['need' => 'Business reporting', 'storeboot' => 'Sales connect to stock, customers and finance', 'alternative' => 'Re-enter totals into another system'],
                ],
                'faqs' => [
                    ['q' => 'Can Storeboot POS work without internet?', 'a' => 'Yes. The till is designed with offline-first support so selling can continue during connectivity problems and data can sync when the connection returns.'],
                    ['q' => 'Does it work for multiple branches?', 'a' => 'Yes. Enterprise includes unlimited branches, branch-aware inventory, staff controls and consolidated reporting.'],
                    ['q' => 'Can I manage cashier shifts?', 'a' => 'Yes. Till sessions provide a structured opening and closing workflow and keep activity associated with the relevant cashier context.'],
                    ['q' => 'Can the POS handle returns and refunds?', 'a' => 'Yes. Storeboot includes recorded sales-return and refund workflows so after-sale activity is not hidden in manual notes.'],
                    ['q' => 'Does a POS sale update inventory?', 'a' => 'Stock-tracked products can create inventory movements as sales complete, keeping the sales and stock records connected.'],
                    ['q' => 'How much does Storeboot POS cost?', 'a' => 'POS, branches, offline app sync and the wider back office are included in Enterprise, listed at ₦5,000 monthly or ₦4,000 per month on yearly billing. Confirm current billing terms during signup.'],
                ],
                'related' => ['inventory-management', 'retail-management', 'small-business-software'],
            ],

            'restaurant-management' => [
                'title' => 'Restaurant Management Software in Nigeria | Storeboot',
                'h1' => 'Restaurant Management Software for Nigerian Restaurants',
                'meta_description' => 'Manage restaurant tables, checks, kitchen orders, modifiers, recipes, ingredients, payments and reporting with Storeboot restaurant software.',
                'eyebrow' => 'From table to kitchen to paid bill',
                'hero' => 'Coordinate the floor, kitchen, stock and accounts from one restaurant system built for the pace and realities of Nigerian hospitality.',
                'image' => 'media/seo/restaurant-management.webp',
                'image_alt' => 'Nigerian restaurant manager coordinating service with a tablet',
                'proof' => ['Tables and service areas', 'Kitchen display workflow', 'Recipes and ingredient usage'],
                'problem_title' => 'Busy service can hide expensive gaps',
                'problems' => [
                    'Restaurant work crosses several teams in minutes. A server takes an order, the kitchen prepares it, the bar supplies a drink, a guest changes a side, and the cashier settles the bill. Paper tickets and spoken instructions can work on a quiet afternoon, then fail under Friday-night pressure. Missed modifiers, duplicate items and unclear tables damage both margin and guest experience.',
                    'Stock is harder because restaurants sell finished experiences but buy ingredients. Ten plates of a dish do not simply reduce “ten dishes” from a shelf; they consume defined quantities of protein, oil, vegetables, seasoning and packaging. Without recipe-level discipline, food cost becomes an estimate and purchasing happens after shortages are already visible.',
                    'Managers also need control over exceptions. Who voided an item after it reached the kitchen? Which table requested a split bill? What revenue came from the terrace compared with the main room? A generic retail till rarely represents these service moments clearly.',
                ],
                'solution_title' => 'One operational thread from seating to settlement',
                'solutions' => [
                    'Storeboot lets you define restaurant service areas and tables, open checks, add covers and build orders with courses, seats and modifiers. Servers can send rounds toward the kitchen workflow, while the active check remains the commercial record for the table.',
                    'Kitchen display support gives preparation teams a clearer queue than handwritten slips. Recipe and modifier definitions connect menu items to ingredients, and you can choose when ingredients leave stock based on the restaurant workflow. Voids can reverse appropriate consumption and retain the reason for review.',
                    'At settlement, teams can print or reprint bills, record payments, apply configured service charges and split or merge checks before payment where appropriate. Management then sees sales, ingredient movements, branch activity and finance in the broader Storeboot back office.',
                ],
                'features' => [
                    ['title' => 'Floor plan and table state', 'body' => 'Create areas such as Main Restaurant, Terrace, Pool Bar or Room Service, add tables and see which are available, occupied or awaiting cleaning.'],
                    ['title' => 'Open checks built for service', 'body' => 'Track covers, seats, courses and unsent versus fired items. Keep additions on the same table check and preserve the history through service.'],
                    ['title' => 'Kitchen display workflow', 'body' => 'Send prepared rounds to a kitchen queue so the back of house can see what is due without interpreting handwriting or repeated verbal calls.'],
                    ['title' => 'Menu modifiers', 'body' => 'Offer structured choices such as spice level, extras or removals. A modifier can affect price and ingredient consumption where relevant.'],
                    ['title' => 'Recipes and ingredients', 'body' => 'Define what a menu item consumes and deduct ingredient quantities when the operational event occurs, producing a more realistic stock and cost trail.'],
                    ['title' => 'Bills, service charge and payments', 'body' => 'Prepare the guest bill, handle supported split or merged checks, record payments and keep service-charge revenue identifiable in the accounts.'],
                ],
                'workflow' => [
                    ['title' => 'Seat the table', 'body' => 'Choose the service area and table, open a check, add the cover count and assign the service context.'],
                    ['title' => 'Take a precise order', 'body' => 'Add menu items by seat or course and capture required modifiers so the kitchen receives the intended request.'],
                    ['title' => 'Fire and prepare', 'body' => 'Send a round to the kitchen display, track prepared work and consume recipe ingredients according to configured operations.'],
                    ['title' => 'Bill and close', 'body' => 'Review items, split or merge before payment where required, take payment, close the check and release the table.'],
                ],
                'examples' => [
                    ['title' => 'Fast-casual restaurant', 'body' => 'Move orders quickly from counter or table to kitchen while recipes translate plates sold into ingredient usage.'],
                    ['title' => 'Fine dining venue', 'body' => 'Use seats, courses and modifiers to preserve service detail, then present a clear bill and process table-specific changes with a trail.'],
                    ['title' => 'Hotel food and beverage', 'body' => 'Separate restaurant, terrace, pool bar and room-service areas while management reviews the operation from one business account.'],
                ],
                'comparison_intro' => 'A retail POS is optimised for immediate basket-and-payment transactions. Restaurant service keeps a check open while several people add, prepare and change items. Storeboot’s restaurant workflow represents tables, rounds, recipes and bill changes directly, while still connecting the final sale to inventory and finance.',
                'comparison' => [
                    ['need' => 'Guest context', 'storeboot' => 'Areas, tables, covers, seats and courses', 'alternative' => 'A flat retail basket with a note'],
                    ['need' => 'Kitchen communication', 'storeboot' => 'Fired rounds and kitchen display queue', 'alternative' => 'Paper tickets or verbal calls'],
                    ['need' => 'Food stock', 'storeboot' => 'Recipe and modifier ingredient depletion', 'alternative' => 'Manual end-of-day estimates'],
                    ['need' => 'Bill changes', 'storeboot' => 'Recorded void, split and merge workflows', 'alternative' => 'Recalculate or recreate the order'],
                ],
                'faqs' => [
                    ['q' => 'Does Storeboot support restaurant tables and floor plans?', 'a' => 'Yes. You can create service areas and tables, track operational table state and open checks against tables.'],
                    ['q' => 'Is there a kitchen display system?', 'a' => 'Yes. Fired restaurant rounds can enter a kitchen display workflow so preparation teams can manage their queue.'],
                    ['q' => 'Can menu items use recipes?', 'a' => 'Yes. A recipe can connect a menu item to ingredient quantities, and modifiers can also add or remove ingredient consumption.'],
                    ['q' => 'Can staff split or merge bills?', 'a' => 'Storeboot supports controlled split and merge check workflows before payment, helping teams handle common group-dining situations without losing the record.'],
                    ['q' => 'Can I add a service charge?', 'a' => 'Yes. Restaurant settings include a configurable service charge for new checks, with the amount retained distinctly in the transaction and accounting flow.'],
                    ['q' => 'Can I manage more than one restaurant branch?', 'a' => 'Yes. Enterprise includes unlimited branches and consolidated management, with branch and location context throughout the wider platform.'],
                ],
                'related' => ['lounge-management', 'inventory-management', 'small-business-software'],
            ],

            'bakery-management' => [
                'title' => 'Bakery Management Software | Storeboot Nigeria',
                'h1' => 'Bakery Management Software',
                'meta_description' => 'Manage bakery recipes, ingredients, production runs, counter sales, orders, purchasing, expenses and reports with Storeboot bakery software.',
                'eyebrow' => 'Control ingredients, production and sales',
                'hero' => 'Know what each batch should consume, what the bakery produced, what was sold and where margin is being lost—without stitching together notebooks and spreadsheets.',
                'image' => 'media/seo/bakery-management.webp',
                'image_alt' => 'Nigerian baker reviewing production on a tablet beside fresh bread and pastries',
                'proof' => ['Recipes and batch production', 'Ingredient stock control', 'POS and online orders'],
                'problem_title' => 'A full display cabinet does not guarantee a healthy margin',
                'problems' => [
                    'Bakeries buy ingredients in bulk, transform them in batches and sell finished goods through several channels. Flour is measured in bags or kilograms, recipes use smaller units, and output varies with portioning and waste. If purchasing, production and sales are tracked separately, the owner can see revenue without understanding what it cost to produce.',
                    'Freshness adds pressure. Producing too little means lost demand; producing too much means markdowns or waste. Custom cakes and pre-orders introduce deposits, due dates and customer expectations, while counter sales still need to move quickly during morning and evening peaks.',
                    'The information usually exists, but in fragments: a production book, a cashier’s total, supplier chats and bank alerts. Bakery management improves when those events form one trail from ingredients received to finished items produced and sold.',
                ],
                'solution_title' => 'Make every batch part of the business record',
                'solutions' => [
                    'Storeboot lets a bakery maintain ingredient and finished-product records, define recipes and record production runs. A run can consume component ingredients and add the output to finished stock, creating a more useful basis for availability and cost than a handwritten “baked today” total.',
                    'The same finished products can be sold through POS or an online storefront. Orders, customer details and payments remain connected, while stock movements reflect the chosen operating model. For made-to-order menu items, recipe depletion can also happen as sales are fired or completed.',
                    'Purchasing, suppliers, expenses and accounting provide the commercial context. Management can compare sales with ingredient use and operating costs, monitor branches, and give production or cashier staff only the access required for their roles.',
                ],
                'features' => [
                    ['title' => 'Recipe definitions', 'body' => 'Describe the flour, sugar, butter, filling, packaging and other components required for a product using practical units and quantities.'],
                    ['title' => 'Production runs', 'body' => 'Record a batch so ingredients move out and finished items move in. Production becomes a traceable event instead of an unexplained stock adjustment.'],
                    ['title' => 'Ingredient and finished stock', 'body' => 'Keep raw materials distinct from sellable output, review balances by location and investigate movements when physical counts differ.'],
                    ['title' => 'Counter and online sales', 'body' => 'Use POS for walk-in customers and a storefront for pre-orders or delivery enquiries, with products and business records in the same platform.'],
                    ['title' => 'Suppliers and purchasing', 'body' => 'Raise purchase orders for ingredients and packaging, receive delivered quantities and track vendor payments instead of relying on chat history.'],
                    ['title' => 'Costs and reporting', 'body' => 'Record expenses and let ingredient costs contribute to a clearer view of production economics, sales performance and business profit.'],
                ],
                'workflow' => [
                    ['title' => 'Set ingredients and units', 'body' => 'Create the raw materials the bakery buys and choose consistent base units for measurement and stock control.'],
                    ['title' => 'Define the recipe', 'body' => 'Attach component quantities to bread, cakes, pastries or other output and verify the expected batch yield.'],
                    ['title' => 'Record production', 'body' => 'Post the quantity actually produced so the corresponding ingredients and finished stock move with a traceable reference.'],
                    ['title' => 'Sell and review', 'body' => 'Complete counter or online sales, watch replenishment needs and compare output, sales and remaining stock.'],
                ],
                'examples' => [
                    ['title' => 'Bread bakery', 'body' => 'Record daily loaves by batch, monitor flour and packaging, and compare production output with counter and wholesale sales.'],
                    ['title' => 'Cake studio', 'body' => 'Manage product options and customer orders while keeping core ingredients, expenses and payments visible to the owner.'],
                    ['title' => 'Multi-outlet pastry brand', 'body' => 'Produce centrally or by branch, move finished goods to outlets, and see which location sells through fastest.'],
                ],
                'comparison_intro' => 'A recipe spreadsheet explains what should happen, while a sales app explains what customers bought. Neither alone explains the transformation between them. Storeboot connects ingredients, recipes, production output, sales and costs so the bakery can investigate yield and margin with fewer manual reconciliations.',
                'comparison' => [
                    ['need' => 'Recipe control', 'storeboot' => 'Components, units and expected consumption', 'alternative' => 'Static recipe document without stock impact'],
                    ['need' => 'Batch record', 'storeboot' => 'Production consumes inputs and creates output', 'alternative' => 'Handwritten total or unexplained adjustment'],
                    ['need' => 'Sales channels', 'storeboot' => 'POS and online store share business data', 'alternative' => 'Counter and online orders reconciled later'],
                    ['need' => 'Commercial view', 'storeboot' => 'Purchasing, expenses, inventory and sales together', 'alternative' => 'Margin estimated from several files'],
                ],
                'faqs' => [
                    ['q' => 'Can Storeboot calculate ingredient usage from recipes?', 'a' => 'Yes. Recipes define component quantities, and production or recipe-based sales can create ingredient movements using the configured units.'],
                    ['q' => 'Can I record bakery production batches?', 'a' => 'Yes. Production runs can consume ingredients and create finished-goods stock, preserving a traceable production record.'],
                    ['q' => 'Can I sell at the counter and online?', 'a' => 'Yes. Storeboot provides an online store in Basic and adds POS and broader in-house operations with Enterprise.'],
                    ['q' => 'Can I manage ingredient suppliers?', 'a' => 'Yes. Supplier records, purchase orders, receiving and vendor payments are part of the procurement workflow.'],
                    ['q' => 'Does it support more than one bakery outlet?', 'a' => 'Yes. Enterprise supports unlimited branches and inventory by branch/location, including stock transfers.'],
                    ['q' => 'Can Storeboot eliminate bakery waste?', 'a' => 'Software cannot remove waste by itself, but accurate recipes, production records, sales history and stock counts make variance visible so managers can take informed action.'],
                ],
                'related' => ['restaurant-management', 'inventory-management', 'online-store'],
            ],

            'lounge-management' => [
                'title' => 'Lounge & Bar Management Software | Storeboot Nigeria',
                'h1' => 'Lounge & Bar Management Software',
                'meta_description' => 'Manage bar tabs, tables, drinks stock, service areas, kitchen orders, bills, cashier tills and reports with Storeboot lounge software.',
                'eyebrow' => 'Faster service with tighter beverage control',
                'hero' => 'Run tables, bar orders, open checks, stock locations and settlements from one system designed for high-volume hospitality service.',
                'image' => 'media/seo/lounge-management.webp',
                'image_alt' => 'Nigerian lounge manager checking operations at an elegant bar',
                'proof' => ['Open table checks', 'Bar and kitchen coordination', 'Location-level stock'],
                'problem_title' => 'Night service moves quickly—and so can unrecorded value',
                'problems' => [
                    'A lounge may operate several environments at once: bar counter, VIP area, terrace, kitchen and store. Guests add drinks over time, move seats, order food, request split payments and sometimes question the final bill. If orders are carried in memory or on loose paper, missed lines and disputed totals become normal.',
                    'Beverage stock is high value and easy to move. Bottles can shift from the main store to a service bar, full bottles become measured serves, and one bar may borrow from another during a rush. A single end-of-night sales total cannot explain whether variance came from transfers, complimentary items, voids, breakage or inaccurate pours.',
                    'Management needs service speed without giving every employee unrestricted control. The right system records who performed important actions, where stock was sourced and how a check changed before payment.',
                ],
                'solution_title' => 'Keep the guest experience smooth and the operation visible',
                'solutions' => [
                    'Storeboot adapts its restaurant service model to lounges and bars. Define areas and tables, open guest checks, add drinks or food in rounds and keep unsent additions distinct from items already sent for preparation. Service staff work from the active check instead of recreating a running tab.',
                    'Locations help represent the physical operation: main store, lounge bar, pool bar or kitchen. Staff can choose the appropriate source for physical products and authorised transfers can document movement between locations. Recipe and modifier logic can represent cocktails, mixers or food ingredients where detailed consumption is useful.',
                    'Bills, part-payments, service charges, split or merged checks and cashier till sessions help close the loop. Owners can review sales, stock movements, expenses and branch results rather than relying only on cash counted after closing.',
                ],
                'features' => [
                    ['title' => 'Areas, tables and guest checks', 'body' => 'Map VIP, terrace, indoor lounge or pool areas, open checks by table and keep the running order available throughout the visit.'],
                    ['title' => 'Rounds and preparation', 'body' => 'Add items as the guest orders and fire appropriate rounds toward the kitchen or preparation workflow without losing earlier items.'],
                    ['title' => 'Bar-location stock', 'body' => 'Track physical goods by location, identify the source used for a line and record transfers when one service point replenishes another.'],
                    ['title' => 'Cocktail and food recipes', 'body' => 'Connect selected menu items and modifiers to ingredients so recorded sales can create a more realistic consumption trail.'],
                    ['title' => 'Controlled changes', 'body' => 'Use permissions and recorded reasons around voids, cancellations and sensitive actions. Split or merge checks through explicit workflows before payment.'],
                    ['title' => 'Till, payment and reporting', 'body' => 'Manage cashier sessions, service charges and supported payment methods, then review activity alongside expenses, inventory and finance.'],
                ],
                'workflow' => [
                    ['title' => 'Open the guest check', 'body' => 'Select the area and table, record the guest context and begin one running commercial record for the visit.'],
                    ['title' => 'Send service rounds', 'body' => 'Add food and drinks as requested, capture modifiers and send prepared work without closing the tab.'],
                    ['title' => 'Control exceptions', 'body' => 'Record void reasons, source stock from the correct location and handle table or check changes with appropriate staff access.'],
                    ['title' => 'Settle and reconcile', 'body' => 'Present the bill, split before payment if needed, record settlement and reconcile the cashier’s till at close.'],
                ],
                'examples' => [
                    ['title' => 'Neighbourhood bar', 'body' => 'Run open tabs, track bottled drinks by service location and close the cashier shift with recorded sales instead of a notebook total.'],
                    ['title' => 'Premium lounge', 'body' => 'Manage VIP and general areas, service charges, bottle service and kitchen rounds while preserving a detailed guest bill.'],
                    ['title' => 'Hotel pool bar', 'body' => 'Separate the pool location from the main restaurant store, transfer stock when necessary and let management view both inside one business.'],
                ],
                'comparison_intro' => 'A basic POS closes each basket immediately, while lounge guests often build a tab across several rounds and service points. Storeboot retains the open check and table context, then connects settlement to location-level stock and the wider back office.',
                'comparison' => [
                    ['need' => 'Running tabs', 'storeboot' => 'Open checks with added and fired rounds', 'alternative' => 'Loose slips or repeated separate sales'],
                    ['need' => 'Service locations', 'storeboot' => 'Areas, tables and stock source locations', 'alternative' => 'One undifferentiated counter'],
                    ['need' => 'Drink consumption', 'storeboot' => 'Physical items plus recipe-based cocktails', 'alternative' => 'Only finished-menu sales totals'],
                    ['need' => 'Exceptions', 'storeboot' => 'Permissions and recorded check changes', 'alternative' => 'Manual corrections with little context'],
                ],
                'faqs' => [
                    ['q' => 'Can Storeboot keep an open bar tab?', 'a' => 'Yes. A guest check can remain open while staff add items and send rounds, then be billed and paid at the end of the visit.'],
                    ['q' => 'Can I track different bars or service points?', 'a' => 'Yes. Service areas represent the guest floor, while inventory locations can represent places such as the main store, lounge bar or pool bar.'],
                    ['q' => 'Can it handle split bills?', 'a' => 'Yes. Staff can move selected quantities to a new check before payment, subject to the operational rules and permissions.'],
                    ['q' => 'Can it track cocktails and mixers?', 'a' => 'Yes. Recipe items and modifier ingredient adjustments can represent drinks assembled from component stock.'],
                    ['q' => 'Does it support food orders and a kitchen?', 'a' => 'Yes. Lounge food orders can use the same fired-round, modifier, kitchen display and recipe capabilities as restaurant service.'],
                    ['q' => 'Can owners see more than sales totals?', 'a' => 'Yes. Storeboot also brings together stock movements, purchases, expenses, cashier sessions, branch results and accounting reports.'],
                ],
                'related' => ['restaurant-management', 'inventory-management', 'pos'],
            ],

            'retail-management' => [
                'title' => 'Retail Management Software in Nigeria | Storeboot',
                'h1' => 'Retail Management Software for Nigerian Businesses',
                'meta_description' => 'Manage POS, products, inventory, branches, purchasing, customers, staff, expenses and reports with Storeboot retail management software.',
                'eyebrow' => 'Connect the shop floor to the back office',
                'hero' => 'Give cashiers a fast checkout and give owners one accurate view of stock, branches, suppliers, customers, staff and money.',
                'image' => 'media/seo/retail-management.webp',
                'image_alt' => 'Nigerian retail owner and team in a modern organised store',
                'proof' => ['POS and online store', 'Branches and procurement', 'Finance and payroll'],
                'problem_title' => 'Retail gets harder when every function keeps its own truth',
                'problems' => [
                    'A growing retailer often adds tools one problem at a time: a till app for sales, a sheet for stock, supplier chats for purchasing, a notebook for expenses and another file for payroll. Each tool may work alone, but the owner becomes the integration—copying totals, resolving mismatches and answering questions that should have had one source.',
                    'Branches multiply the difficulty. An item can be unavailable in one shop and overstocked in another. Managers submit daily figures in different formats. Customer credit, returns and transfers create legitimate changes that look suspicious when the supporting transaction is elsewhere.',
                    'The cost is not only administration. Slow information leads to late reordering, excess purchases, weak cash control and decisions based on revenue rather than profit. Retail management software should make daily recording easier for staff and management review more dependable for owners.',
                ],
                'solution_title' => 'One operating system for the whole retail cycle',
                'solutions' => [
                    'Storeboot connects the activities that begin with buying stock and end with understanding the result. Suppliers and purchase orders support replenishment; inventory tracks goods by branch and location; POS and online orders record sales; returns and payments preserve after-sale events.',
                    'Customer history, invoicing and receivables help when the relationship extends beyond one checkout. Expenses, accounting and dashboards add the cost and cash context that sales totals alone cannot provide. HR and payroll help the same business manage the people doing the work.',
                    'Roles and permissions let each staff member focus on the correct part of the system. Branch managers can operate their location while an owner reviews consolidated performance, reducing the need for manually assembled end-of-day reports.',
                ],
                'features' => [
                    ['title' => 'POS and omnichannel sales', 'body' => 'Serve walk-in customers through POS and online customers through a branded storefront while keeping products, orders and business records in one platform.'],
                    ['title' => 'Inventory by branch', 'body' => 'Track product variants and quantities by location, transfer stock between outlets and investigate receipts, sales, returns and adjustments.'],
                    ['title' => 'Procurement and suppliers', 'body' => 'Create purchase orders, receive goods and record vendor payments so replenishment is visible from request to settlement.'],
                    ['title' => 'Customers and receivables', 'body' => 'Maintain customer records, purchase history, invoices, payments and balances for businesses that sell on account or build repeat relationships.'],
                    ['title' => 'Staff, roles and payroll', 'body' => 'Give staff role-appropriate access, maintain employee records and support payroll and deductions without exposing every management function.'],
                    ['title' => 'Finance and analytics', 'body' => 'Record expenses and accounting entries, then review sales, stock and financial KPIs across a branch or the entire retail business.'],
                ],
                'workflow' => [
                    ['title' => 'Buy deliberately', 'body' => 'Use current stock and sales information to prepare purchase orders with the right supplier and receiving destination.'],
                    ['title' => 'Receive and distribute', 'body' => 'Record delivered quantities into a location and transfer goods to branches with an auditable movement.'],
                    ['title' => 'Sell everywhere', 'body' => 'Use the counter POS or online store, capture payments and customers, and let completed transactions update the relevant records.'],
                    ['title' => 'Review the whole result', 'body' => 'Compare branches, monitor low stock, reconcile tills, record expenses and use financial reports for the next decision.'],
                ],
                'examples' => [
                    ['title' => 'Fashion chain', 'body' => 'Track size and colour variants by outlet, move slow stock to a stronger branch and sell the same catalogue online.'],
                    ['title' => 'Supermarket', 'body' => 'Process high-volume baskets, reconcile cashier tills, manage suppliers and monitor replenishment across product categories.'],
                    ['title' => 'Electronics and appliances', 'body' => 'Keep item and customer records precise, handle deposits or credit balances and connect purchasing with branch availability.'],
                ],
                'comparison_intro' => 'Separate best-of-breed tools may suit a large company with an integration team. Many Nigerian SMEs need a coherent system their existing team can actually maintain. Storeboot reduces hand-offs by keeping retail operations in one platform, while preserving transaction detail and role controls.',
                'comparison' => [
                    ['need' => 'Daily selling', 'storeboot' => 'Online store and offline-first POS', 'alternative' => 'Channels maintain separate products and totals'],
                    ['need' => 'Branch stock', 'storeboot' => 'Location balances and recorded transfers', 'alternative' => 'Branch sheets sent to head office'],
                    ['need' => 'Back office', 'storeboot' => 'Procurement, expenses, HR and finance included', 'alternative' => 'Several subscriptions and repeated entry'],
                    ['need' => 'Management view', 'storeboot' => 'Consolidated operational and financial reports', 'alternative' => 'Owner merges reports manually'],
                ],
                'faqs' => [
                    ['q' => 'What types of retailers can use Storeboot?', 'a' => 'Storeboot can support supermarkets, fashion shops, beauty retailers, electronics stores, pharmacies, wholesalers and other product-led businesses. Configuration depends on how each business sells and controls stock.'],
                    ['q' => 'Can I run both a physical shop and an online store?', 'a' => 'Yes. The online storefront and POS can use the same broader product and operating platform, reducing duplicate catalogue work.'],
                    ['q' => 'Can I manage multiple outlets?', 'a' => 'Yes. Enterprise includes unlimited branches, location-level stock, staff access and consolidated reporting.'],
                    ['q' => 'Does Storeboot include purchasing?', 'a' => 'Yes. It supports supplier records, purchase orders, goods receiving and vendor payment records.'],
                    ['q' => 'Can it manage employees and payroll?', 'a' => 'Enterprise includes HR and payroll support, including employee records, payroll, deductions, payslips and branch transfers.'],
                    ['q' => 'Will Storeboot replace my accountant?', 'a' => 'No. Storeboot can improve transaction records, accounting workflows and reports, but professional judgement, compliance and tax advice should still come from a qualified adviser.'],
                ],
                'related' => ['pos', 'inventory-management', 'online-store'],
            ],

            'small-business-software' => [
                'title' => 'Small Business Management Software Nigeria | Storeboot',
                'h1' => 'All-in-One Small Business Management Software in Nigeria',
                'meta_description' => 'Run sales, inventory, customers, suppliers, expenses, payroll, branches, accounting and reports with Storeboot small business software.',
                'eyebrow' => 'One place to run the business you are building',
                'hero' => 'Replace disconnected apps, sheets and notebooks with a practical operating system for selling, stock, people, suppliers and money.',
                'image' => 'media/seo/small-business-software.webp',
                'image_alt' => 'Nigerian small business team reviewing operations together on a laptop and tablet',
                'proof' => ['Free plan to start', 'Built for Nigerian SMEs', 'Grow into branches and finance'],
                'problem_title' => 'The owner should not be the only connection between every part of the business',
                'problems' => [
                    'Small businesses rarely lack effort. They lack one dependable operating picture. Sales happen in a till app or chat, expenses live in a notebook, customer balances sit in someone’s memory, stock is counted in a sheet and payroll is prepared from last month’s file. The owner spends evenings collecting information before any real analysis can begin.',
                    'This creates key-person risk. When the person who understands the files is absent, work slows down. When staff use different naming or timing, reports disagree. Adding another branch, sales channel or employee increases coordination faster than it increases control.',
                    'An all-in-one system is useful only if each daily workflow remains simple. Staff should not need accounting expertise to record a sale or receive stock, but those events should still create structured information management can trust.',
                ],
                'solution_title' => 'Start with what you need and keep one foundation as you grow',
                'solutions' => [
                    'Storeboot brings together products and services, online selling, POS, customers, invoices, inventory, suppliers, procurement, expenses, HR, payroll, accounting and analytics. The modules share the business, branch and transaction context, reducing repeated entry and mismatched totals.',
                    'A new seller can begin with the free Basic plan: publish an online store, add products, accept orders, maintain customers and inventory, and see an analytics dashboard. A business with physical operations can move to Enterprise for branches, tills, offline POS, suppliers, finance, payroll and deeper control.',
                    'AI-assisted setup reduces blank-page work around product catalogues, images, store pages and metadata. Roles and permissions help owners delegate daily work without giving every team member access to everything.',
                ],
                'features' => [
                    ['title' => 'Sell products and services', 'body' => 'Publish an online store, create invoices or use POS for physical checkout. Keep products, customers, orders and payments within one operating environment.'],
                    ['title' => 'Control inventory and buying', 'body' => 'Track variants and locations, record movements, transfer stock, manage suppliers, raise purchase orders and receive goods.'],
                    ['title' => 'Understand customers and credit', 'body' => 'Maintain customer history, follow invoices and payments, and see balances when the business offers credit or receives deposits.'],
                    ['title' => 'Record expenses and accounts', 'body' => 'Capture operating expenses and use a real chart of accounts, journal entries and financial reports for a more complete picture than revenue alone.'],
                    ['title' => 'Manage people and access', 'body' => 'Create staff accounts, apply roles and permissions, maintain employee details and support payroll, deductions and payslips.'],
                    ['title' => 'See performance clearly', 'body' => 'Review dashboards and reports for sales trends, best sellers, branch performance, stock signals and the movement of money.'],
                ],
                'workflow' => [
                    ['title' => 'Create the business workspace', 'body' => 'Add the business identity, currency, team and first branch, then choose the modules relevant to current operations.'],
                    ['title' => 'Bring in the essentials', 'body' => 'Set up products or services, opening stock, customers, suppliers and staff responsibilities using consistent records.'],
                    ['title' => 'Run daily work', 'body' => 'Process sales, payments, purchases, stock movements and expenses where they happen instead of compiling them later.'],
                    ['title' => 'Review and improve', 'body' => 'Use dashboards and financial reports to spot exceptions, protect cash, plan buying and decide where growth deserves investment.'],
                ],
                'examples' => [
                    ['title' => 'A solo social seller', 'body' => 'Launch a free storefront, organise products and customer orders, then add staff and POS when a physical outlet opens.'],
                    ['title' => 'A growing service business', 'body' => 'Maintain services, customers, invoices, payments, expenses and staff information without building a system from unrelated apps.'],
                    ['title' => 'A multi-branch SME', 'body' => 'Standardise products and controls across locations while head office reviews stock, sales, payroll, procurement and accounts together.'],
                ],
                'comparison_intro' => 'A spreadsheet is inexpensive and endlessly flexible, but accuracy depends on manual discipline. Separate apps can be excellent at one function while creating integration and subscription overhead. Enterprise software is often too heavy for a growing SME. Storeboot aims for the practical middle: broad operational coverage, local relevance and a starting point that does not require a large implementation project.',
                'comparison' => [
                    ['need' => 'Setup', 'storeboot' => 'Guided, modular and free to begin', 'alternative' => 'Build templates or commission integrations'],
                    ['need' => 'Shared data', 'storeboot' => 'Sales, stock, people and money share context', 'alternative' => 'Copy and reconcile between systems'],
                    ['need' => 'Delegation', 'storeboot' => 'Staff accounts, roles and permissions', 'alternative' => 'Shared passwords or full spreadsheet access'],
                    ['need' => 'Growth', 'storeboot' => 'Add branches, POS, procurement and finance', 'alternative' => 'Replace the original setup as complexity rises'],
                ],
                'faqs' => [
                    ['q' => 'What is small business management software?', 'a' => 'It is a system that brings important daily workflows—such as sales, inventory, customers, purchasing, staff, expenses and reporting—into one organised operating environment.'],
                    ['q' => 'Is Storeboot made for Nigerian businesses?', 'a' => 'Yes. Storeboot is built for African SMEs first, with Nigerian business realities such as Naira pricing, local payment workflows, uneven connectivity and multi-branch operations in mind.'],
                    ['q' => 'Can I start using Storeboot for free?', 'a' => 'Yes. Basic is free forever and includes an online store, products, orders, customers, invoicing, inventory, analytics and other essentials.'],
                    ['q' => 'What is included in Enterprise?', 'a' => 'Enterprise adds unlimited branches, POS and till support, offline app sync, expenses, HR and payroll, roles, suppliers, procurement, payables, receivables, accounting and priority support.'],
                    ['q' => 'Do I have to activate every feature?', 'a' => 'No. A business can focus on the workflows it currently needs and add more operational depth as the team and complexity grow.'],
                    ['q' => 'Is my business data protected?', 'a' => 'Storeboot uses cloud backups, encrypted connections and role-based access controls. Businesses should also use strong credentials and give staff only the access required for their work.'],
                ],
                'related' => ['online-store', 'retail-management', 'inventory-management'],
            ],
        ];
    }
}
