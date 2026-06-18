<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case DRAFT = 'draft';
    case PENDING_VALIDATION = 'pending_validation';
    case VALIDATED = 'validated';
    case VALIDATION_FAILED = 'validation_failed';
    case PENDING_SIGNING = 'pending_signing';
    case SIGNED = 'signed';
    case SIGN_FAILED = 'sign_failed';
    case PENDING_TRANSMIT = 'pending_transmit';
    case TRANSMITTED = 'transmitted';
    case TRANSMIT_FAILED = 'transmit_failed';
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';

    public function isFiscalized(): bool
    {
        return in_array($this, [
            self::SIGNED,
            self::PENDING_TRANSMIT,
            self::TRANSMIT_FAILED,
            self::TRANSMITTED,
            self::CONFIRMED,
        ], true);
    }

    public function isEditable(): bool
    {
        return in_array($this, [
            self::DRAFT,
            self::VALIDATION_FAILED,
        ], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::CONFIRMED,
            self::CANCELLED,
        ], true);
    }

    /**
     * @return array<int, string>
     */
    public static function fiscalizedValues(): array
    {
        return array_values(array_map(
            fn (self $status) => $status->value,
            array_filter(self::cases(), fn (self $status) => $status->isFiscalized())
        ));
    }
}
