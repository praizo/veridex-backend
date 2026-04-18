<x-mail::message>
# Invoice Confirmed

Dear {{ $customerName }},

Your tax-compliant invoice **#{{ $invoiceNumber }}** has been processed and confirmed by the National Revenue Service (NRS).

**Invoice Details:**
- **Amount Due:** {{ $currency }} {{ number_format($amount, 2) }}
- **Status:** Confirmed & Transmitted

Please find the official A4 PDF version of your invoice attached to this email. This document contains the IRN and QR code for verification.

<x-mail::button :url="config('app.url')">
View in Dashboard
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
