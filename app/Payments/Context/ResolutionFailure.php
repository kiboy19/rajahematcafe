<?php

namespace App\Payments\Context;

enum ResolutionFailure: string
{
    case UnknownReference = 'unknown_reference';
    case ContextMismatch = 'context_mismatch';
    case TypeMismatch = 'type_mismatch';
    case CustomerMismatch = 'customer_mismatch';

    public function auditToken(): string
    {
        return $this->value;
    }
}