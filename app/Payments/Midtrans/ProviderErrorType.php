<?php

namespace App\Payments\Midtrans;

enum ProviderErrorType: string
{
    case ProviderTimeout = 'provider_timeout';
    case ProviderRejected = 'provider_rejected';
    case ProviderUnavailable = 'provider_unavailable';
    case ProviderMalformed = 'provider_malformed';
}
