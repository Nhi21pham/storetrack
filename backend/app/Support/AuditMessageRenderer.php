<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Lang;

/**
 * Rebuilds an audit log's human sentence in the active locale from its
 * structured object_type + action + metadata — the PHP counterpart of the
 * frontend's auditMessages renderer, used by the audit-log Excel export.
 * Falls back to the stored English message for shapes it doesn't recognize.
 */
class AuditMessageRenderer
{
    public function render(AuditLog $log): string
    {
        $key = $this->templateKey($log);
        if ($key === null || ! Lang::has("audit.msg.{$key}")) {
            return (string) $log->message;
        }

        return __("audit.msg.{$key}", $this->params($log));
    }

    private function templateKey(AuditLog $log): ?string
    {
        $object = (string) $log->object_type;
        $action = (string) $log->action;
        $metadata = $log->metadata ?? [];

        if ($object === 'tag' && isset($metadata['value'], $metadata['tag_value_id'])) {
            return match ($action) {
                'created' => 'tag.value_added',
                'updated' => 'tag.value_updated',
                'deleted' => 'tag.value_deleted',
                default   => null,
            };
        }

        if ($object === 'payment' && ! empty($metadata['invoices'])) {
            return "payment.{$action}_for";
        }

        if ($action === 'exported') {
            if ($object === 'store') {
                return 'store.exported_store';
            }
            if ($object === 'business') {
                return 'business.exported_business';
            }

            return $object.'.exported_'.(! empty($metadata['store_id']) ? 'store' : 'business');
        }

        return "{$object}.{$action}";
    }

    private function params(AuditLog $log): array
    {
        $metadata = $log->metadata ?? [];

        if (isset($metadata['amount'])) {
            $metadata['amount'] = Money::vnd($metadata['amount']);
        }

        $type = $metadata['type'] ?? null;
        if ($type) {
            $metadata['invoiceType'] = $this->lookup('audit.invoice_type.'.$type, (string) $type);
        }
        $metadata['invoiceLabel'] = __('audit.invoice_label.'.($type ?: 'all'));
        $isPdf = str_ends_with(strtolower((string) ($metadata['filename'] ?? '')), '.zip');
        $metadata['exportKind'] = __('audit.export_kind.'.($isPdf ? 'pdf' : 'list'));

        foreach (['role', 'old_role', 'new_role'] as $roleKey) {
            if (isset($metadata[$roleKey])) {
                $metadata[$roleKey] = $this->lookup('audit.role.'.strtolower((string) $metadata[$roleKey]), (string) $metadata[$roleKey]);
            }
        }
        if (isset($metadata['party_type'])) {
            $metadata['partyType'] = $this->lookup('audit.party_type.'.$metadata['party_type'], (string) $metadata['party_type']);
        }
        if (isset($metadata['report'])) {
            $metadata['report'] = $this->lookup('audit.report_label.'.$metadata['report'], (string) $metadata['report']);
        }

        // Laravel's replacer needs scalar values; drop arrays (e.g. filters).
        return array_map(
            fn ($value) => is_scalar($value) ? (string) $value : '',
            $metadata
        );
    }

    private function lookup(string $key, string $fallback): string
    {
        return Lang::has($key) ? __($key) : $fallback;
    }
}
