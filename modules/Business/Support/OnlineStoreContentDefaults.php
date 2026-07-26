<?php

declare(strict_types=1);

namespace Modules\Business\Support;

final class OnlineStoreContentDefaults
{
    /**
     * @return array{terms_of_use: string, return_policy: string, privacy_policy: string, shipping_information: string}
     */
    public static function pages(string $storeName, ?string $contactEmail = null): array
    {
        $storeName = trim($storeName) ?: 'this store';
        $contact = trim((string) $contactEmail) ?: 'the contact details provided on our Contact page';
        $updated = now()->format('F j, Y');

        return [
            'terms_of_use' => <<<TEXT
Last updated: {$updated}

1. Acceptance of these terms
By accessing or using {$storeName}, you agree to these Terms of Use. If you do not agree, please do not use the website or place an order.

2. Store information
We aim to keep product descriptions, prices, images, availability, and other information accurate. Minor differences may occur, and we may correct errors or update information without prior notice.

3. Orders and payment
An order is an offer to purchase. We may accept, decline, or cancel an order where an item is unavailable, information is incorrect, payment cannot be confirmed, or fraud is suspected. Available payment methods are shown during checkout. You are responsible for providing complete and accurate billing, delivery, and contact information.

4. Pricing and promotions
Prices are displayed in the currency shown on the website. Delivery fees, taxes, discounts, or other charges will be shown before an order is confirmed where applicable. Promotions are subject to their stated conditions and availability.

5. Acceptable use
You must not misuse this website, interfere with its operation, attempt unauthorized access, submit false information, or use the website for unlawful activity.

6. Intellectual property
The website content, branding, text, graphics, and images belong to {$storeName} or their respective owners and may not be copied or commercially reused without permission.

7. Liability
To the extent permitted by applicable law, {$storeName} is not liable for indirect or consequential loss arising from use of the website. Nothing in these terms excludes rights or remedies that cannot legally be excluded.

8. Changes and contact
We may update these terms from time to time. The version published here applies from its stated update date. Questions may be sent through {$contact}.
TEXT,
            'return_policy' => <<<TEXT
Last updated: {$updated}

We want you to be satisfied with your purchase from {$storeName}. Please review this policy before requesting a return, exchange, or refund.

1. Return eligibility
Unless a different condition is stated on the product page, a return request should be made promptly after delivery. Items must be unused, in their original condition, and returned with their packaging, accessories, and proof of purchase.

2. Non-returnable items
For hygiene, safety, customization, or perishability reasons, certain items may not be returnable. This may include opened personal-care products, perishable goods, made-to-order items, digital products, gift cards, and items marked as final sale, except where required by law.

3. Damaged, defective, or incorrect orders
If an item arrives damaged, defective, incomplete, or different from what you ordered, contact us as soon as possible with your order details and clear photos where relevant. We will assess the issue and offer an appropriate resolution.

4. Return process
Contact us through {$contact} before sending an item back. Returns sent without approval may be delayed or refused. Customers are responsible for securely packaging approved returns and following the return instructions provided.

5. Refunds
Approved refunds are issued to the original payment method where possible. Original delivery charges and return shipping costs may be non-refundable unless the item was defective, damaged, or supplied incorrectly. Processing time can vary by payment provider.

6. Exchanges and cancellations
Exchanges depend on stock availability. Cancellation requests are considered only before an order has been processed or dispatched. Statutory consumer rights remain unaffected.
TEXT,
            'privacy_policy' => <<<TEXT
Last updated: {$updated}

{$storeName} respects your privacy. This policy explains how information is collected, used, and protected when you browse our website, contact us, or place an order.

1. Information we collect
We may collect your name, phone number, email address, billing and delivery address, order details, payment status, messages, and device or usage information. Payment card details may be processed directly by an authorized payment provider and may not be stored by us.

2. How we use information
We use personal information to process and deliver orders, confirm payments, provide customer support, prevent fraud, improve our services, meet legal obligations, and send marketing communications where you have consented.

3. Sharing information
We may share only the information necessary with delivery partners, payment providers, technology providers, professional advisers, or authorities where required by law. We do not sell your personal information.

4. Cookies and analytics
The website may use essential cookies and similar technologies to keep the store working, remember preferences, understand usage, and improve performance. Your browser may allow you to control cookies.

5. Data retention and security
We retain information only for as long as reasonably necessary for the purposes described above or as required by law. We use reasonable administrative and technical measures to protect it, although no internet service can guarantee absolute security.

6. Your choices and rights
Depending on applicable law, you may request access to, correction of, or deletion of your personal information, or object to certain uses. We may need to verify your identity before completing a request.

7. Contact
For privacy questions or requests, contact us through {$contact}.
TEXT,
            'shipping_information' => <<<TEXT
Last updated: {$updated}

{$storeName} offers delivery to the locations made available during checkout. Available options, charges, and estimated delivery times may vary by destination, order size, product availability, and delivery partner.

1. Order processing
Orders are processed after payment or order confirmation, depending on the selected payment method. Orders placed outside business hours, on weekends, or on public holidays may begin processing on the next working day.

2. Delivery estimates
Any delivery timeframe shown at checkout is an estimate rather than a guarantee. Weather, traffic, public holidays, remote locations, high order volumes, or carrier delays may affect delivery.

3. Delivery charges
Applicable shipping fees are displayed before you confirm your order. Additional charges may apply to remote areas, oversized items, redelivery, or special handling, but we will communicate these where possible before dispatch.

4. Address and contact details
Please provide a complete delivery address and an active phone number. {$storeName} is not responsible for delays or additional costs caused by incorrect or incomplete information supplied by the customer.

5. Receiving an order
You may be asked to confirm receipt. Inspect the package as soon as possible and report visible damage, missing items, or an incorrect order promptly through {$contact}.

6. Failed delivery and collection
If a delivery attempt is unsuccessful, the carrier or our team may contact you to arrange redelivery or collection. Additional fees may apply where repeated delivery is required.

7. Questions
For delivery questions, updates, or special arrangements, contact us through {$contact} and include your order reference.
TEXT,
        ];
    }

    /**
     * @return list<array{question: string, answer: string}>
     */
    public static function faqs(string $storeName): array
    {
        $storeName = trim($storeName) ?: 'our store';

        return [
            [
                'question' => 'How do I place an order?',
                'answer' => 'Browse the available products, add your preferred items to the cart, and proceed to checkout. Enter your contact and delivery information, review the order, then choose an available payment method to complete it.',
            ],
            [
                'question' => 'What payment methods do you accept?',
                'answer' => 'The payment methods currently supported by '.$storeName.' are displayed during checkout. Depending on availability, these may include online payment, bank transfer, payment on delivery, or placing an order for confirmation.',
            ],
            [
                'question' => 'How long will delivery take?',
                'answer' => 'Estimated delivery times depend on your location, product availability, and the selected delivery option. The available delivery details and charges are shown during checkout, and unexpected carrier delays may occasionally occur.',
            ],
            [
                'question' => 'Can I change or cancel my order?',
                'answer' => 'Contact us as soon as possible with your order reference. We will try to help, but an order may no longer be changed or cancelled after it has been processed or dispatched.',
            ],
            [
                'question' => 'How do I request a return or refund?',
                'answer' => 'Contact us with your order reference and the reason for your request. Do not send an item back until return instructions have been provided. Eligibility and refund processing are subject to the Return Policy published on this website.',
            ],
        ];
    }
}
