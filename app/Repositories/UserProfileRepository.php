<?php

namespace App\Repositories;

use App\Interfaces\UserProfileRepositoryInterface;
use App\Models\UserProfile;

class UserProfileRepository implements UserProfileRepositoryInterface
{
    public function findByUserId(int $userId): ?UserProfile
    {
        return UserProfile::where('user_id', $userId)->first();
    }

    public function create(array $data): UserProfile
    {
        return UserProfile::create($data);
    }

    public function update(UserProfile $profile, array $data): bool
    {
        return $profile->update($data);
    }
}