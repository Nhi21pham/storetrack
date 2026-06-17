<?php

namespace App\Repositories;

use App\Models\Import;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ImportRepository
{
    public function create(array $attributes): Import
    {
        return Import::create($attributes);
    }

    public function find(int $id): ?Import
    {
        return Import::find($id);
    }

    public function findForUser(int $id, int $userId): ?Import
    {
        return Import::where('id', $id)->where('user_id', $userId)->first();
    }

    public function update(Import $import, array $attributes): Import
    {
        $import->update($attributes);

        return $import->refresh();
    }

    /**
     * A pending/processing import for the same user, type, scope and row-content
     * signature — used to collapse a double-submit (e.g. the same file fired
     * from two browser tabs) into the already-running import instead of starting
     * a second job. Deliberately browser-agnostic: identical content is one
     * import regardless of which tab sent it.
     */
    public function findInProgressDuplicate(
        int $userId,
        string $type,
        int $scopeId,
        string $contentSignature
    ): ?Import {
        return Import::query()
            ->where('user_id', $userId)
            ->where('type', $type)
            ->where('metadata->scope_id', $scopeId)
            ->where('metadata->content_signature', $contentSignature)
            ->whereIn('status', [Import::STATUS_PENDING, Import::STATUS_PROCESSING])
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Newest-first history for a scope (store/business), optionally filtered by
     * entity type. Powers the import history board.
     */
    public function paginateForScope(int $scopeId, ?string $type, ?string $startDate, ?string $endDate, int $perPage = 20): LengthAwarePaginator
    {
        $query = Import::query()
            ->with('user')
            ->where('metadata->scope_id', $scopeId)
            ->orderByDesc('id');

        if ($type !== null && $type !== '') {
            $query->where('type', $type);
        }
        if ($startDate !== null && $startDate !== '') {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate !== null && $endDate !== '') {
            $query->whereDate('created_at', '<=', $endDate);
        }

        return $query->paginate($perPage);
    }

    /**
     * Imports whose working file is still on disk but older than the retention
     * cutoff — the file is removed while the history record is kept.
     *
     * @return Collection<int, Import>
     */
    public function staleWithFiles(DateTimeInterface $before): Collection
    {
        return Import::query()
            ->whereNotNull('disk')
            ->whereNotNull('path')
            ->where('created_at', '<=', $before)
            ->get();
    }
}
