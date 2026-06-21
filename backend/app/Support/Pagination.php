<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class Pagination
{
    /**
     * Shape a paginator into the standard history payload consumed by the
     * frontend (data + total + current_page + last_page + per_page). Pass a
     * mapper to project each row; omit it to return the items unchanged.
     *
     * @param  (callable(mixed): mixed)|null  $mapItem
     * @return array{data: list<mixed>, total: int, current_page: int, last_page: int, per_page: int}
     */
    public static function present(LengthAwarePaginator $paginator, ?callable $mapItem = null): array
    {
        $items = $paginator->items();

        return [
            'data'         => $mapItem === null ? array_values($items) : array_map($mapItem, $items),
            'total'        => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'per_page'     => $paginator->perPage(),
        ];
    }
}
