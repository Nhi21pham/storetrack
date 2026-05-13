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
     * A pending or processing export for the same user, type, scope, filter
     * signature and client — used to dedupe spam clicks before the first
     * job has finished. The client_id keeps two browsers/devices for the
     * same user from colliding on a single in-flight export.
     */
    public function findInProgressDuplicate(
        int $userId,
        string $type,
        int $scopeId,
        string $filterSignature,
        ?string $clientId
    ): ?Export {
        return $this->scopeQuery($userId, $type, $scopeId, $clientId)
            ->where('metadata->filter_signature', $filterSignature)
            ->whereIn('status', [Export::STATUS_PENDING, Export::STATUS_PROCESSING])
            ->orderByDesc('id')
            ->first();
    }

    /**
     * A previously-completed export for the same user, type, scope, filter
     * signature and client whose file is still on disk and not expired —
     * the caller can hand its id back to the client instead of rebuilding
     * the same file.
     */
    public function findCompletedDuplicateWithFile(
        int $userId,
        string $type,
        int $scopeId,
        string $filterSignature,
        ?string $clientId
    ): ?Export {
        return $this->scopeQuery($userId, $type, $scopeId, $clientId)
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
     * All exports for the same (user, type, scope, client) that still have
     * a file on disk — used at queue time to wipe stale orphans before
     * producing a fresh file for the same scope. Scoped by client_id so
     * one browser's queue-time cleanup doesn't nuke another browser's file.
     *
     * @return Collection<int, Export>
     */
    public function findExistingFilesForScope(
        int $userId,
        string $type,
        int $scopeId,
        ?string $clientId
    ): Collection {
        return $this->scopeQuery($userId, $type, $scopeId, $clientId)
            ->whereNotNull('disk')
            ->whereNotNull('path')
            ->get();
    }

    /**
     * Shared base query for the dedup / cleanup lookups. Matches on user,
     * type, scope and client_id (treating a missing client_id as its own
     * bucket so legacy/non-browser callers don't collide with browsers).
     */
    private function scopeQuery(int $userId, string $type, int $scopeId, ?string $clientId)
    {
        $query = Export::query()
            ->where('user_id', $userId)
            ->where('type', $type)
            ->where('metadata->scope_id', $scopeId);

        if ($clientId === null) {
            $query->whereNull('metadata->client_id');
        } else {
            $query->where('metadata->client_id', $clientId);
        }

        return $query;
    }
}
