<?php

namespace App\Backend\Repositories;

use App\Backend\Interfaces\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use App\Models\UserProfile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

class UserRepository implements UserRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->filtered($filters)->with(['roles', 'profile'])->latest()->paginate($perPage)->withQueryString();
    }

    /** The list's search and status filters as a query: the screen and the CSV export share it, so they can never disagree. */
    public function filtered(array $filters): Builder
    {
        return User::query()
            // grouped: an ungrouped OR would let the e-mail match escape the status filter below
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            })
            // compared with null on purpose: when(0, ...) is falsy and would silently drop the "inactive" filter
            ->when($this->validStatus($filters['status'] ?? null) !== null, fn ($q) => $q->where('status', $this->validStatus($filters['status'])));
    }

    /** A status filter is only applied when it is exactly one of the known numbers ("abc" must not silently mean 0). */
    private function validStatus(mixed $value): ?int
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        return preg_match('/^\d+$/', (string) $value) === 1 && array_key_exists((int) $value, User::statusLabels()) ? (int) $value : null;
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
