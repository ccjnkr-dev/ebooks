<?php

namespace App\Services;

use App\Models\AuditEvent;

final class Audit
{
    public static function record(string $action, string $subject, array $metadata = []): void
    {
        AuditEvent::create(['user_id' => auth()->id(), 'action' => $action, 'subject' => $subject, 'metadata' => $metadata]);
    }
}
