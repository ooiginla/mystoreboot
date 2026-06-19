<?php

declare(strict_types=1);

namespace Modules\Customers\Enums;

enum TicketType: string
{
    case Enquiry = 'enquiry';
    case Complaint = 'complaint';
    case ServiceRequest = 'service_request';
    case InternalIssue = 'internal_issue';

    public function label(): string
    {
        return match ($this) {
            self::Enquiry => 'Enquiry',
            self::Complaint => 'Complaint',
            self::ServiceRequest => 'Service request',
            self::InternalIssue => 'Internal issue',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
