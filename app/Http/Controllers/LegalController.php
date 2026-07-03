<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;

final class LegalController extends Controller
{
    private const UPDATED = '3 July 2026';

    public function privacy(): View
    {
        return view('marketing.legal', [
            'eyebrow' => 'Legal',
            'pageTitle' => 'Privacy Policy',
            'updated' => self::UPDATED,
            'intro' => 'This Privacy Policy explains how The Bootup Limited ("we", "us", "our") collects, uses, and protects your information when you use Storeboot. We are committed to handling your data responsibly and in line with the Nigeria Data Protection Act, 2023.',
            'sections' => $this->privacySections(),
        ]);
    }

    public function terms(): View
    {
        return view('marketing.legal', [
            'eyebrow' => 'Legal',
            'pageTitle' => 'Terms of Service',
            'updated' => self::UPDATED,
            'intro' => 'These Terms of Service govern your access to and use of Storeboot, a product of The Bootup Limited. By creating an account or using the platform, you agree to these terms.',
            'sections' => $this->termsSections(),
        ]);
    }

    public function security(): View
    {
        return view('marketing.legal', [
            'eyebrow' => 'Trust',
            'pageTitle' => 'Security',
            'updated' => self::UPDATED,
            'intro' => 'Your business data is important to you, and to us. This page describes the measures we take to keep Storeboot and the information you store in it safe.',
            'sections' => $this->securitySections(),
        ]);
    }

    /** @return list<array{heading: string, blocks: list<string|array{list: list<string>}>}> */
    private function privacySections(): array
    {
        return [
            ['heading' => 'Information we collect', 'blocks' => [
                'We collect information you provide directly and information generated as you use the platform, including:',
                ['list' => [
                    'Account details — your name, email address, phone number, and password.',
                    'Business details — your business name, category, branches, city, and country.',
                    'Operational data — the products, sales, inventory, customers, expenses, and other records you enter.',
                    'Technical data — device, browser, IP address, and usage logs used to keep the service secure and reliable.',
                ]],
            ]],
            ['heading' => 'How we use your information', 'blocks' => [
                'We use your information to provide and improve Storeboot, specifically to:',
                ['list' => [
                    'Operate your workspace and deliver the modules on your subscription plan.',
                    'Process transactions and maintain accurate records for your business.',
                    'Communicate service updates, security notices, and support responses.',
                    'Detect, prevent, and address fraud, abuse, and technical issues.',
                ]],
            ]],
            ['heading' => 'Data ownership', 'blocks' => [
                'The business data you enter into Storeboot belongs to you. We act as a processor of that data on your behalf. We do not sell your data, and we do not use your business records for advertising.',
            ]],
            ['heading' => 'Sharing and disclosure', 'blocks' => [
                'We only share data with trusted service providers who help us run the platform (for example, cloud hosting and email delivery), and only to the extent needed to provide the service. We may disclose information where required by law or to protect the rights and safety of our users.',
            ]],
            ['heading' => 'Data retention', 'blocks' => [
                'We keep your data for as long as your account is active. If you close your account, we retain records only as long as necessary to meet legal, accounting, or reporting obligations, after which the data is deleted or anonymised.',
            ]],
            ['heading' => 'Your rights', 'blocks' => [
                'Under the Nigeria Data Protection Act, you have the right to access, correct, export, and request deletion of your personal data. To exercise any of these rights, contact us at support@storeboot.com.',
            ]],
            ['heading' => 'Contact us', 'blocks' => [
                'If you have any questions about this policy or how we handle your data, reach us at support@storeboot.com or call 07035361770.',
            ]],
        ];
    }

    /** @return list<array{heading: string, blocks: list<string|array{list: list<string>}>}> */
    private function termsSections(): array
    {
        return [
            ['heading' => 'Your account', 'blocks' => [
                'You must provide accurate information when creating an account and keep your login credentials secure. You are responsible for all activity that happens under your account, including the actions of team members you invite.',
            ]],
            ['heading' => 'Acceptable use', 'blocks' => [
                'You agree to use Storeboot lawfully and not to:',
                ['list' => [
                    'Use the platform for any illegal, fraudulent, or harmful activity.',
                    'Attempt to gain unauthorised access to the platform or other users\' data.',
                    'Interfere with or disrupt the integrity or performance of the service.',
                    'Resell or sublicense the platform without our written permission.',
                ]],
            ]],
            ['heading' => 'Subscriptions and billing', 'blocks' => [
                'Storeboot is offered on a free trial and paid subscription plans. Paid plans are billed in advance for the period you select. Modules are enabled according to your chosen plan. Fees are non-refundable except where required by law.',
            ]],
            ['heading' => 'Trial period', 'blocks' => [
                'New accounts include a 14-day free trial. At the end of the trial you may choose a paid plan to continue. If you do not subscribe, access to paid modules may be limited while your data is retained.',
            ]],
            ['heading' => 'Your data', 'blocks' => [
                'You retain ownership of the data you enter. You can export your records at any time. We handle your data in accordance with our Privacy Policy.',
            ]],
            ['heading' => 'Availability and support', 'blocks' => [
                'We work hard to keep Storeboot available and reliable, but the service is provided "as is" without warranties of uninterrupted availability. We provide support through the channels listed on our Contact page.',
            ]],
            ['heading' => 'Limitation of liability', 'blocks' => [
                'To the maximum extent permitted by law, The Bootup Limited shall not be liable for indirect, incidental, or consequential damages arising from your use of the platform.',
            ]],
            ['heading' => 'Changes and termination', 'blocks' => [
                'We may update these terms from time to time and will notify you of material changes. You may stop using the service at any time. We may suspend or terminate accounts that violate these terms.',
            ]],
            ['heading' => 'Governing law', 'blocks' => [
                'These terms are governed by the laws of the Federal Republic of Nigeria. Questions about these terms can be sent to support@storeboot.com.',
            ]],
        ];
    }

    /** @return list<array{heading: string, blocks: list<string|array{list: list<string>}>}> */
    private function securitySections(): array
    {
        return [
            ['heading' => 'Encryption', 'blocks' => [
                'All traffic between your browser and Storeboot is encrypted in transit using TLS. Sensitive information such as passwords is hashed and never stored in plain text.',
            ]],
            ['heading' => 'Access control', 'blocks' => [
                'Storeboot is multi-tenant by design. Every record is scoped to your business, and team members only see what their role allows. Platform administrators cannot access your workspace data in the normal course of operations.',
            ]],
            ['heading' => 'Backups and reliability', 'blocks' => [
                'Your data is stored in the cloud with regular automated backups, so your records are protected against accidental loss. Our point-of-sale is built offline-first, keeping a safe local copy that syncs when you reconnect.',
            ]],
            ['heading' => 'Infrastructure', 'blocks' => [
                'Storeboot runs on reputable cloud infrastructure with network isolation, monitoring, and routine security updates. We follow the principle of least privilege for internal access to systems.',
            ]],
            ['heading' => 'Responsible disclosure', 'blocks' => [
                'We welcome reports from security researchers. If you believe you have found a vulnerability, please email support@storeboot.com with the details so we can investigate promptly. Please do not publicly disclose issues before we have had a chance to address them.',
            ]],
            ['heading' => 'Your part', 'blocks' => [
                'Security is a shared responsibility. We encourage you to use a strong, unique password, keep your login details private, and give team members only the access they need.',
            ]],
        ];
    }
}
