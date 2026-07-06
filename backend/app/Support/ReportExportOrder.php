<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class ReportExportOrder
{
    // Order rows to match the exact sequence of the given ids — the client's
    // on-screen order — replacing any default ordering. No-op for an empty list.
    // $column is an internal, caller-supplied column name (never user input).
    public static function byIds(Builder $query, string $column, array $ids): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $query->reorder()->orderByRaw("FIELD({$column}, {$placeholders})", $ids);
    }
}
