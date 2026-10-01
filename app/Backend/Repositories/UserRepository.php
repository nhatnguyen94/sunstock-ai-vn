<?php

namespace App\Backend\Repositories;

use App\Backend\Interfaces\UserRepositoryInterface;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

class UserRepository implements UserRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return User::with(['roles', 'profile'])
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findWithRelations(User $user): User
    {
        return $user->load(['roles', 'profile', 'portfolios']);
    }

    public function create(array $data): User
    {
        $user = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
        // status is not mass-assignable; applyStatus also keeps the e-mail confirmation consistent with it
        $user->applyStatus((int) ($data['status'] ?? User::STATUS_ACTIVE));
        $user->save();

        if (! empty($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        // Every user needs a profile row — self-registration and the admin
        // seeder both create one, this was the one path that didn't
        // (ProfileController::update() would crash for these accounts).
        UserProfile::create(['user_id' => $user->id]);

        return $user;
    }

    public function update(User $user, array $data): void
    {
        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => isset($data['password']) ? Hash::make($data['password']) : $user->password,
        ]);
        $user->applyStatus((int) $data['status']);
        $user->save();

        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
        }
    }

    public function delete(User $user): void
    {
        $user->delete();
    }
}
