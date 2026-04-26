<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Enums\PartyTypeEnum;
use App\Enums\PermissionEnum;
use App\Models\Business;
use App\Models\User;
use App\Repositories\BusinessRepository;
use App\Repositories\PartyRepository;
use Illuminate\Support\Facades\DB;
use App\Exceptions\BusinessException;

class BusinessService
{
    public function __construct(
        private BusinessRepository $businessRepository,
        private PermissionService $permissionService,
        private AuditLogService $auditLogService,
        private PartyRepository $partyRepository
    ) {}

    public function createBusiness(User $user, array $data): Business
    {
        return DB::transaction(function () use ($user, $data) {
            $party = $this->partyRepository->create(PartyTypeEnum::BUSINESS);
            return $this->businessRepository->create(array_merge($data, [
                'owner_id' => $user->id,
                'party_id' => $party->id,
            ]));
        });
    }

    public function updateBusiness(User $user, int $businessId, array $data): Business
    {
        $this->permissionService->authorizeBusiness($user, PermissionEnum::UPDATE_BUSINESS, $businessId);
        $business = $this->mustFind($businessId);

        if (!empty($data['tax_code']) && $data['tax_code'] !== $business->tax_code) {
            $exists = Business::where('tax_code', $data['tax_code'])
                    ->where('id', '!=', $business->id)
                    ->exists();
            if ($exists) {
                throw new BusinessException(ErrorCode::TAX_CODE_TAKEN, 'This tax code is already in use.');
            }
        }

        $business = $this->businessRepository->update($business, $data);
        $this->auditLogService->businessUpdated($user, $business);
        return $business;
    }

    public function deleteBusiness(User $user, int $businessId): void
    {
        $this->permissionService->authorizeBusiness($user, PermissionEnum::DELETE_BUSINESS, $businessId);

        DB::transaction(function () use ($businessId) {
            $business = Business::lockForUpdate()->find($businessId);
            if (!$business) {
                throw new BusinessException(ErrorCode::BUSINESS_NOT_FOUND, 'Business not found.');
            }

            $stores   = $business->stores()->lockForUpdate()->get();
            $storeIds = $stores->pluck('id')->all();
            $partyIds = $stores->pluck('party_id')
                ->push($business->party_id)
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (!empty($storeIds)) {
                DB::table('store_user')->whereIn('store_id', $storeIds)->delete();
            }
            $business->stores()->delete();
            $business->delete();

            $this->partyRepository->deleteMany($partyIds);
        });
    }

    public function getUserBusinesses(User $user)
    {
        return $user->businesses()->with(['stores' => fn($q) => $q->latest()])->latest()->get();
    }

    private function mustFind(int $id): Business
    {
        $business = $this->businessRepository->findById($id);
        if (!$business) {
            throw new BusinessException(ErrorCode::BUSINESS_NOT_FOUND, 'Business not found.');
        }
        return $business;
    }

    public function getAccessibleBusinesses(User $user): array
    {
        $ownedBusinesses = $this->getOwnedBusinesses($user);
        $assignedBusinesses = $this->getAssignedBusinesses($user);

        return array_merge($ownedBusinesses, $assignedBusinesses);
    }

    public function getOwnedBusinesses(User $user): array
    {
        $businesses = $user->businesses()->with(['stores' => fn($q) => $q->latest()])->latest()->get();
        $result = [];

        foreach ($businesses as $business) {
            $stores = [];

            foreach ($business->stores as $store) {
                if (!$store->is_active) continue;

                $stores[] = [
                    'id' => (string) $store->id,
                    'name' => $store->name,
                    'is_active' => $store->is_active,
                    'my_role' => 'owner',
                ];
            }

            $result[] = [
                'id' => (string) $business->id,
                'name' => $business->name,
                'tax_code' => $business->tax_code,
                'address' => $business->address,
                'email' => $business->email,
                'phone' => $business->phone,
                'role' => 'owner',
                'stores' => $stores,
            ];
        }

        return $result;
    }

    public function getAssignedBusinesses(User $user): array
    {
        $stores = $user->stores()->with('business')->where('is_active', true)->latest()->get();
        $grouped = [];

        foreach ($stores as $store) {
            if ($store->business->owner_id === $user->id) continue;

            $bizId = $store->business_id;

            if (!isset($grouped[$bizId])) {
                $grouped[$bizId] = [
                    'id' => (string) $store->business->id,
                    'name' => $store->business->name,
                    'tax_code' => $store->business->tax_code,
                    'address' => $store->business->address,
                    'email' => $store->business->email,
                    'phone' => $store->business->phone,
                    'role' => 'member',
                    'stores' => [],
                ];
            }

            $grouped[$bizId]['stores'][] = [
                'id' => (string) $store->id,
                'name' => $store->name,
                'is_active' => $store->is_active,
                'my_role' => $store->pivot->role,
            ];
        }

        return array_values($grouped);
    }
    public function getBusiness(User $user, int $businessId): Business
    {
        $business = $this->businessRepository->findById($businessId);

        if (!$business || $business->owner_id !== $user->id) {
            throw new BusinessException(ErrorCode::BUSINESS_NOT_FOUND, 'Business not found.');
        }

        return $business;
    }
}