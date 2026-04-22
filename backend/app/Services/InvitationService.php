<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Enums\InvitationStatusEnum;
use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Exceptions\InvitationException;
use App\Mail\InvitationMail;
use App\Mail\InvitationResponseMail;
use App\Models\Invitation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvitationService
{
    public function __construct(
        private PermissionService $permissionService
    ) {}

    public function sendInvitation(User $inviter, int $storeId, string $email, RoleEnum $role): Invitation
    {
        $this->permissionService->authorizeStore($inviter, PermissionEnum::AssignStoreUser, $storeId);

        $store = Store::findOrFail($storeId);
        $email = strtolower(trim($email));

        if ($role === RoleEnum::Owner) {
            throw new InvitationException(ErrorCode::INVITATION_CANNOT_INVITE_OWNER, 'Cannot invite a user as Owner.');
        }

        if ($inviter->email === $email) {
            throw new InvitationException(ErrorCode::INVITATION_CANNOT_INVITE_SELF, 'You cannot invite yourself.');
        }

        $alreadyMember = $store->users()->whereRaw('LOWER(users.email) = ?', [$email])->exists();
        if ($alreadyMember) {
            throw new InvitationException(ErrorCode::INVITATION_ALREADY_MEMBER, 'This user is already a member of this store.');
        }

        $invitation = DB::transaction(function () use ($store, $inviter, $email, $role, $storeId) {
            $pendingExists = Invitation::where('store_id', $storeId)
                ->where('invitee_email', $email)
                ->where('status', InvitationStatusEnum::Pending->value)
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->exists();

            if ($pendingExists) {
                throw new InvitationException(ErrorCode::INVITATION_PENDING_EXISTS, 'A pending invitation already exists for this email.');
            }

            try {
                return Invitation::create([
                    'store_id'      => $store->id,
                    'inviter_id'    => $inviter->id,
                    'invitee_email' => $email,
                    'role'          => $role->value,
                    'token'         => Str::random(64),
                    'status'        => InvitationStatusEnum::Pending,
                    'expires_at'    => now()->addDay(),
                ]);
            } catch (QueryException $e) {
                // unique_active_invitation constraint: concurrent request won the race
                if (($e->errorInfo[1] ?? null) === 1062) {
                    throw new InvitationException(ErrorCode::INVITATION_PENDING_EXISTS, 'A pending invitation already exists for this email.');
                }
                throw $e;
            }
        });

        Log::info('[Invitation] link for ' . $email . ': ' . config('app.frontend_url', 'http://localhost:5173') . '/invite/' . $invitation->token);

        Mail::to($email)->queue(new InvitationMail(
            inviterName: $inviter->name,
            inviteeEmail: $email,
            storeName: $store->name,
            role: $role->value,
            token: $invitation->token,
        ));

        return $invitation->load(['store', 'inviter']);
    }

    public function cancelInvitation(User $actor, int $invitationId): void
    {
        $invitation = Invitation::find($invitationId);

        if (!$invitation) {
            throw new InvitationException(ErrorCode::INVITATION_NOT_FOUND, 'Invitation not found.');
        }

        $this->permissionService->authorizeStore($actor, PermissionEnum::AssignStoreUser, $invitation->store_id);

        $updated = Invitation::whereKey($invitationId)
            ->where('status', InvitationStatusEnum::Pending->value)
            ->update(['status' => InvitationStatusEnum::Cancelled->value]);

        if ($updated === 0) {
            throw new InvitationException(ErrorCode::INVITATION_NOT_CANCELLABLE, 'This invitation can no longer be cancelled.');
        }
    }

    public function getStorePendingInvitations(User $actor, int $storeId): Collection
    {
        $this->permissionService->authorizeStore($actor, PermissionEnum::AssignStoreUser, $storeId);

        return Invitation::with('inviter')
            ->where('store_id', $storeId)
            ->where('status', InvitationStatusEnum::Pending->value)
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->get();
    }

    public function getStoreAllInvitations(User $actor, int $storeId): Collection
    {
        $this->permissionService->authorizeStore($actor, PermissionEnum::AssignStoreUser, $storeId);

        $invitations = Invitation::with(['store', 'inviter'])
            ->where('store_id', $storeId)
            ->orderByDesc('created_at')
            ->get();

        $namesByEmail = User::whereIn('email', $invitations->pluck('invitee_email')->unique())
            ->get(['email', 'name'])
            ->mapWithKeys(fn(User $user) => [strtolower($user->email) => $user->name]);

        $invitations->each(function (Invitation $inv) use ($namesByEmail) {
            $inv->invitee_name = $namesByEmail->get(strtolower($inv->invitee_email));
        });

        return $invitations;
    }

    public function getInvitationPreview(string $token): array
    {
        $invitation = Invitation::with(['store', 'inviter'])->where('token', $token)->first();

        if (!$invitation) {
            throw new InvitationException(ErrorCode::INVITATION_NOT_FOUND, 'Invitation not found.');
        }

        return [
            'inviter_name'       => $invitation->inviter->name,
            'store_name'         => $invitation->store->name,
            'role'               => $invitation->role,
            'is_expired'         => $invitation->isExpired() || $invitation->isTimedOut(),
            'is_already_accepted' => $invitation->status === InvitationStatusEnum::Accepted,
            'status'             => $invitation->status->value,
        ];
    }

    public function acceptInvitation(User $user, string $token): Store
    {
        $result = DB::transaction(function () use ($user, $token) {
            $invitation = Invitation::where('token', $token)->lockForUpdate()->first();

            if (!$invitation) {
                throw new InvitationException(ErrorCode::INVITATION_NOT_FOUND, 'Invitation not found.');
            }

            if (strtolower($user->email) !== strtolower($invitation->invitee_email)) {
                throw new InvitationException(ErrorCode::INVITATION_WRONG_EMAIL, 'This invitation was sent to a different email address.');
            }

            if ($invitation->status === InvitationStatusEnum::Accepted) {
                throw new InvitationException(ErrorCode::INVITATION_ALREADY_ACCEPTED, 'This invitation has already been accepted.');
            }

            if ($invitation->status !== InvitationStatusEnum::Pending) {
                throw new InvitationException(ErrorCode::INVITATION_EXPIRED, 'This invitation has expired or is no longer valid.');
            }

            if ($invitation->isTimedOut()) {
                $invitation->update(['status' => InvitationStatusEnum::Expired]);
                return (object) ['timedOut' => true];
            }

            $store = $invitation->store;

            $alreadyMember = $store->users()->where('user_id', $user->id)->exists();
            if ($alreadyMember) {
                throw new InvitationException(ErrorCode::INVITATION_ALREADY_MEMBER, 'You are already a member of this store.');
            }

            try {
                $store->users()->attach($user->id, ['role' => $invitation->role]);
            } catch (QueryException $e) {
                // Another request added this user to the store between the exists() check and attach().
                if (($e->errorInfo[0] ?? null) === '23000') {
                    throw new InvitationException(ErrorCode::INVITATION_ALREADY_MEMBER, 'You are already a member of this store.');
                }
                throw $e;
            }
            $invitation->update(['status' => InvitationStatusEnum::Accepted, 'accepted_at' => now()]);

            return (object) [
                'store'        => $store,
                'inviterEmail' => $invitation->inviter->email,
                'inviteeEmail' => $invitation->invitee_email,
                'storeName'    => $store->name,
            ];
        });

        if ($result->timedOut ?? false) {
            throw new InvitationException(ErrorCode::INVITATION_EXPIRED, 'This invitation has expired or is no longer valid.');
        }

        Mail::to($result->inviterEmail)->queue(new InvitationResponseMail(
            inviteeEmail: $result->inviteeEmail,
            storeName: $result->storeName,
            accepted: true,
        ));

        return $result->store->fresh('users', 'business');
    }

    public function expirePendingInvitations(): int
    {
        return Invitation::where('status', InvitationStatusEnum::Pending->value)
            ->where('expires_at', '<=', now())
            ->update(['status' => InvitationStatusEnum::Expired->value]);
    }

    public function declineInvitation(User $user, string $token): void
    {
        $result = DB::transaction(function () use ($user, $token) {
            $invitation = Invitation::where('token', $token)->lockForUpdate()->first();

            if (!$invitation) {
                throw new InvitationException(ErrorCode::INVITATION_NOT_FOUND, 'Invitation not found.');
            }

            if (strtolower($user->email) !== strtolower($invitation->invitee_email)) {
                throw new InvitationException(ErrorCode::INVITATION_WRONG_EMAIL, 'This invitation was sent to a different email address.');
            }

            if ($invitation->status !== InvitationStatusEnum::Pending) {
                throw new InvitationException(ErrorCode::INVITATION_EXPIRED, 'This invitation has expired or is no longer valid.');
            }

            if ($invitation->isTimedOut()) {
                $invitation->update(['status' => InvitationStatusEnum::Expired]);
                return (object) ['timedOut' => true];
            }

            $invitation->update(['status' => InvitationStatusEnum::Declined]);

            return (object) [
                'inviterEmail' => $invitation->inviter->email,
                'inviteeEmail' => $invitation->invitee_email,
                'storeName'    => $invitation->store->name,
            ];
        });

        if ($result->timedOut ?? false) {
            throw new InvitationException(ErrorCode::INVITATION_EXPIRED, 'This invitation has expired or is no longer valid.');
        }

        Mail::to($result->inviterEmail)->queue(new InvitationResponseMail(
            inviteeEmail: $result->inviteeEmail,
            storeName: $result->storeName,
            accepted: false,
        ));
    }
}
