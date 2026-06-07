<?php

namespace App\Enums;

enum NrsAction: string
{
    case VALIDATE = 'validate';
    case SIGN = 'sign';
    case TRANSMIT = 'transmit';
    case CONFIRM = 'confirm';
    case UPDATE_PAYMENT = 'update_payment';
    case VALIDATE_IRN = 'validate_irn';
    case HEALTH_CHECK = 'health_check';
    case LOOKUP_IRN = 'lookup_irn';
}
