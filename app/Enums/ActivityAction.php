<?php

namespace App\Enums;

enum ActivityAction: string
{
    case CREATED = 'created';
    case UPDATED = 'updated';
    case DELETED = 'deleted';
    case VIEWED = 'viewed';
    case EXPORTED = 'exported';
    case NRS_VALIDATE = 'nrs_validate';
    case NRS_SIGN = 'nrs_sign';
    case NRS_TRANSMIT = 'nrs_transmit';
    case NRS_CONFIRM = 'nrs_confirm';
    case INVOICE_CREATED = 'invoice_created';
    case PAYMENT_UPDATED = 'payment_updated';
    case LOGIN = 'login';
    case LOGOUT = 'logout';
}
