<?php

namespace App\Frontend\Interfaces;

use App\Models\UserProfile;

interface UserProfileRepositoryInterface
{
    public function findByUserId(int $userId): ?UserProfile;

    public function create(array $data): UserProfile;

    public function update(UserProfile $profile, array $data): bool;

    /**
     * Update the user's profile, creating it first if it doesn't exist yet
     * (e.g. accounts created via the admin panel don't get one automatically).
     */
    public function updateOrCreateForUser(int $userId, array $data): UserProfile;
}
