<?php

namespace App\Backend\Services;

use App\Backend\Interfaces\UserRepositoryInterface;
use App\Backend\Interfaces\UserServiceInterface;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class UserService implements UserServiceInterface
{
    public function __construct(
        protected UserRepositoryInterface $userRepository
    ) {}

    public function listUsers(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->userRepository->paginate($filters, $perPage);
    }

    public function getRoles(): Collection
    {
        return Role::all();
    }

    public function createUser(array $data): User
    {
        return $this->userRepository->create($data);
    }

    public function updateUser(User $user, array $data): void
    {
        $this->userRepository->update($user, $data);
    }

    public function deleteUser(User $user): void
    {
        $this->userRepository->delete($user);
    }

    public function findWithRelations(User $user): User
    {
        return $this->userRepository->findWithRelations($user);
    }
}
