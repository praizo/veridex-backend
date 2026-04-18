<?php

namespace App\Enums;

enum DocReferenceType: string
{
    case BILLING = 'billing';
    case DISPATCH = 'dispatch';
    case RECEIPT = 'receipt';
    case ORIGINATOR = 'originator';
    case CONTRACT = 'contract';
    case ADDITIONAL = 'additional';
}
