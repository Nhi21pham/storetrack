<?php

namespace App\Repositories;

use App\Models\Export;
use Illuminate\Support\Collection;

class ExportRepository
{
    public function create(array $attributes): Export
    {
        return Export::create($attributes);
    }

    public function find(int $id): ?Export
    {
        return Export::find($id);
    }

    public function findForUser(int $id, int $userId): ?Export
    {
        return Export::where('id', $id)->where('user_id', $userId)->first();
    }

    public function update(Export $export, array $attributes): Export
    {
        $export->update($attributes);

        return $export->refresh();
    }

    /**
     * Exports that still have a file recorded but should be cleaned up:
     * either failed, or past their expiry.
     *
     * @return Collection<int, Export>
     */
    public function staleWithFiles(): Collection
    {
        return Export::query()
            ->whereNotNull('path')
            ->whereNotNull('disk')
            ->where(function ($q) {
                $q->where('status', Export::STATUS_FAILED)
                    ->orWhere(function ($q2) {
                        $q2->whereNotNull('expires_at')
                            ->where('expires_at', '<=', now());
                    });
            })
            ->get();
    }

    /**
     * A pending or processing export for the same user, type, scope and
     * filter signature — used to dedupe spam clicks before the first job
     * has finished.
     */
    public function findInProgressDuplicate(
        int $userId,
        string $type,
        int $scopeId,
        string $filterSignature
    ): ?Export {
        return Export::query()
            ->where('user_id', $userId)
            ->where('type', $type)
            ->where('metadata->scope_id', $scopeId)
            ->where('metadata->filter_signature', $filterSignature)
            ->whereIn('status', [Export::STATUS_PENDING, Export::STATUS_PROCESSING])
            ->orderByDesc('id')
            ->first();
    }

    /**
     * A previously-completed export with the same user, type, scope and
     * filter signature whose file is still on disk and not expired — the
     * caller can hand its id back to the client instead of rebuilding the
     * same file.
     */
    public function findCompletedDuplicateWithFile(
        int $userId,
        string $type,
        int $scopeId,
        string $filterSignature
    ): ?Export {
        return Export::query()
            ->where('user_id', $userId)
            ->where('type', $type)
            ->where('metadata->scope_id', $scopeId)
            ->where('metadata->filter_signature', $filterSignature)
            ->where('status', Export::STATUS_COMPLETED)
            ->whereNotNull('disk')
            ->whereNotNull('path')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('id')
            ->first();
    }

    /**
     * All exports for the same (user, type, scope) that still have a file
     * on disk — used at queue time to wipe stale orphans before producing
     * a fresh file for the same scope.
     *
     * @return Collection<int, Export>
     */
    public function findExistingFilesForScope(
        int $userId,
        string $type,
        int $scopeId
    ): Collection {
        return Export::query()
            ->where('user_id', $userId)
            ->where('type', $type)
            ->where('metadata->scope_id', $scopeId)
            ->whereNotNull('disk')
            ->whereNotNull('path')
            ->get();
    }
}
