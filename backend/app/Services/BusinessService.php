<?php

namespace App\Services;

use App\Enums\PermissionEnum;
use App\Models\Business;
use App\Models\User;
use App\Repositories\BusinessRepository;
use Illuminate\Support\Facades\DB;

class BusinessService
{
    public function __construct(
        private BusinessRepository $businessRepository,
        private PermissionService $permissionService
    ) {}

    public function createBusiness(User $user, array $data): Business
    {
        return $this->businessRepository->create([
            'owner_id' => $user->id,
            ...$data,
        ]);
    }

    public function updateBusiness(User $user, int $businessId, array $data): Business
    {
        $this->permissionService->authorizeBusiness($user, PermissionEnum::UpdateBusiness, $businessId);
        $business = $this->mustFind($businessId);
        return $this->businessRepository->update($business, $data);
    }

    public function deleteBusiness(User $user, int $businessId): void
    {
        $this->permissionService->authorizeBusiness($user, PermissionEnum::DeleteBusiness, $businessId);
        $business = $this->mustFind($businessId);

        DB::transaction(function () use ($business) {
            foreach ($business->stores as $store) {
                $store->users()->detach();
            }
            $business->stores()->delete();
            $business->delete();
        });
    }

    public function getUserBusinesses(User $user)
    {
        return $user->businesses()->with('stores')->get();
    }

    private function mustFind(int $id): Business
    {
        $business = $this->businessRepository->findById($id);
        if (!$business) {
            throw new \Exception('Business not found.');
        }
        return $business;
    }
}