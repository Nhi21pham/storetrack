<?php

namespace App\Support;

use Illuminate\Database\QueryException;

class DatabaseError
{
    // MySQL driver error codes, as surfaced through PDO errorInfo[1].
    public const DUPLICATE_ENTRY = 1062;
    public const FK_CANNOT_ADD_CHILD = 1452;
    public const FK_ROW_IS_REFERENCED = 1451;

    public static function code(QueryException $e): ?int
    {
        return $e->errorInfo[1] ?? null;
    }

    // A row could not be deleted because another table still references it
    // through a RESTRICT foreign key.
    public static function isRowReferenced(QueryException $e): bool
    {
        return self::code($e) === self::FK_ROW_IS_REFERENCED;
    }
}
