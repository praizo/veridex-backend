<?php

namespace App\Enums;

enum InvoiceTypeCode: string
{
    case COMMERCIAL = '380';
    case CREDIT_NOTE = '381';
    case DEBIT_NOTE = '383';
    case PREPAYMENT = '386';
    case FACTORED = '396';
}
