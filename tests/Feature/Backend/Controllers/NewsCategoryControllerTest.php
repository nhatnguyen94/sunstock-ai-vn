<?php

namespace Tests\Feature\Backend\Controllers;

use App\Models\News;
use App\Models\NewsCategory;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Real end-to-end (RefreshDatabase) — genuinely needed for unique-name/slug
 * validation and the destroy guard's real `news()->exists()` check.
 *
 * @see \Tests\Feature\Backend\Controllers\RoleControllerTest::actingAsUserWithPermissions()
 *      for why the acting role's name matters (must be a backend-access role).
 */
class NewsCategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createNews(int $categoryId): News
    {
        static $counter = 0;
        $counter++;

        return News::create([
            'title' => "Test news {$counter}",
            'url' => "https://example.com/news-{$counter}",
            'url_hash' => md5("https://example.com/news-{$counter}"),
            'source' => 'Test',
            'category_id' => $categoryId,
            'published_at' => now(),
            'synced_at' => now(),
        ]);
    }

    private function actingAsUserWithPermissions(array $permissionNames): User
    {
        $role = Role::create(['name' => Role::WEBADMIN, 'display_name' => 'Web Admin']);

        if (!empty($permissionNames)) {
            $ids = collect($permissionNames)->map(
                fn ($name) => Permission::create(['name' => $name, 'display_name' => $name])->id
            );
            $role->permissions()->sync($ids);
        }

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $this->actingAs($user);

        return $user;
    }

    #[Group('newsCategories')]
    public function test_user_without_manage_features_permission_is_forbidden(): void
    {
        $this->actingAsUserWithPermissions([]);

        $response = $this->get('/admin/news-categories');

        $response->assertForbidden();
    }

    #[Group('newsCategories')]
    public function test_index_lists_categories_with_news_count(): void
    {
        $this->actingAsUserWithPermissions(['manage-features']);
        $category = NewsCategory::create(['name' => 'Bất động sản', 'slug' => 'bat-dong-san']);
        $this->createNews($category->id);
        $this->createNews($category->id);

        $response = $this->get('/admin/news-categories');

        $response->assertOk();
        $response->assertSee('Bất động sản');
        $response->assertSee('bat-dong-san');
    }

    #[Group('newsCategories')]
    public function test_store_creates_a_category_with_auto_generated_slug(): void
    {
        $this->actingAsUserWithPermissions(['manage-features']);

        $response = $this->post('/admin/news-categories', ['name' => 'Công nghệ']);

        $response->assertRedirect(route('admin.news-categories.index'));
        $this->assertDatabaseHas('news_categories', ['name' => 'Công nghệ', 'slug' => 'cong-nghe']);
    }

    #[Group('newsCategories')]
    public function test_store_rejects_a_duplicate_name(): void
    {
        $this->actingAsUserWithPermissions(['manage-features']);
        NewsCategory::create(['name' => 'Công nghệ', 'slug' => 'cong-nghe']);

        $response = $this->post('/admin/news-categories', ['name' => 'Công nghệ']);

        $response->assertSessionHasErrors('name');
    }

    #[Group('newsCategories')]
    public function test_update_changes_name_and_slug(): void
    {
        $this->actingAsUserWithPermissions(['manage-features']);
        $category = NewsCategory::create(['name' => 'Old', 'slug' => 'old']);

        $response = $this->put("/admin/news-categories/{$category->id}", [
            'name' => 'New Name',
            'slug' => 'new-slug',
        ]);

        $response->assertRedirect(route('admin.news-categories.index'));
        $this->assertDatabaseHas('news_categories', ['id' => $category->id, 'name' => 'New Name', 'slug' => 'new-slug']);
    }

    #[Group('newsCategories')]
    public function test_destroy_deletes_an_unused_category(): void
    {
        $this->actingAsUserWithPermissions(['manage-features']);
        $category = NewsCategory::create(['name' => 'Chưa dùng', 'slug' => 'chua-dung']);

        $response = $this->delete("/admin/news-categories/{$category->id}");

        $response->assertRedirect(route('admin.news-categories.index'));
        $this->assertDatabaseMissing('news_categories', ['id' => $category->id]);
    }

    #[Group('newsCategories')]
    public function test_cannot_destroy_a_category_that_has_news(): void
    {
        $this->actingAsUserWithPermissions(['manage-features']);
        $category = NewsCategory::create(['name' => 'Có bài viết', 'slug' => 'co-bai-viet']);
        $this->createNews($category->id);

        $response = $this->delete("/admin/news-categories/{$category->id}");

        $response->assertRedirect(route('admin.news-categories.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('news_categories', ['id' => $category->id]);
    }

    #[Group('newsCategories')]
    public function test_cannot_destroy_a_category_used_by_the_rss_sync_sources(): void
    {
        $this->actingAsUserWithPermissions(['manage-features']);
        // The create_news_categories_table migration itself seeds 4 rows
        // (id 1-4, "Kinh doanh" first) — these are exactly the ids
        // NewsService::SOURCES hardcodes, so id=1 is already in use with no
        // news rows attached yet. Confirms the guard checks SOURCES, not just
        // "has news".
        $category = NewsCategory::find(1);
        $this->assertNotNull($category, 'Expected the migration to have seeded a category with id=1');
        $this->assertFalse($category->news()->exists(), 'This test is only meaningful if the category has no news yet');

        $response = $this->delete("/admin/news-categories/{$category->id}");

        $response->assertRedirect(route('admin.news-categories.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('news_categories', ['id' => $category->id]);
    }
}
