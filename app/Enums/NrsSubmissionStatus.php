<?php

namespace App\Enums;

enum NrsSubmissionStatus: string
{
    case PENDING = 'pending';
    case SUCCESS = 'success';
    case FAILED = 'failed';
}
