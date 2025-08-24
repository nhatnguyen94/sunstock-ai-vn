<?php

namespace App\Interfaces;

use App\Models\UserProfile;

interface UserProfileRepositoryInterface
{
    public function findByUserId(int $userId): ?UserProfile;
    public function create(array $data): UserProfile;
    public function update(UserProfile $profile, array $data): bool;
}