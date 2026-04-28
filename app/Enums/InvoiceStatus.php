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
}
