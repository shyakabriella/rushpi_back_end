<?php

declare(strict_types=1);

namespace Tests\Feature\Homepage;

use App\Models\HomepageCampaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HomepageCampaignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        Role::findOrCreate(
            'admin',
            config('auth.defaults.guard')
        );
    }

    public function test_admin_can_create_homepage_campaign(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->post(
                '/api/admin/homepage-campaigns',
                [
                    'title' => 'Save on selected laptops',
                    'subtitle' => 'Find business laptops at better prices.',
                    'desktop_image' => UploadedFile::fake()->image(
                        'laptops-desktop.jpg',
                        1200,
                        800
                    ),
                    'mobile_image' => UploadedFile::fake()->image(
                        'laptops-mobile.jpg',
                        600,
                        800
                    ),
                    'background_color' => '#f1f5f9',
                    'text_color' => '#0f172a',
                    'button_text' => 'Shop now',
                    'link_type' => 'category',
                    'link_value' => 'computers-laptops',
                    'card_size' => 'large',
                    'position' => 1,
                    'is_active' => true,
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.title',
                'Save on selected laptops'
            )
            ->assertJsonPath(
                'data.card_size',
                'large'
            );

        $campaign = HomepageCampaign::query()
            ->firstOrFail();

        Storage::disk('public')->assertExists(
            $campaign->desktop_image_path
        );

        Storage::disk('public')->assertExists(
            $campaign->mobile_image_path
        );
    }

    public function test_public_endpoint_returns_only_visible_campaigns(): void
    {
        HomepageCampaign::query()->create([
            'title' => 'Visible campaign',
            'desktop_image_path' => 'homepage-campaigns/visible.jpg',
            'link_type' => 'none',
            'card_size' => 'large',
            'position' => 1,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'is_active' => true,
        ]);

        HomepageCampaign::query()->create([
            'title' => 'Future campaign',
            'desktop_image_path' => 'homepage-campaigns/future.jpg',
            'link_type' => 'none',
            'card_size' => 'small',
            'position' => 2,
            'starts_at' => now()->addDay(),
            'is_active' => true,
        ]);

        HomepageCampaign::query()->create([
            'title' => 'Inactive campaign',
            'desktop_image_path' => 'homepage-campaigns/inactive.jpg',
            'link_type' => 'none',
            'card_size' => 'medium',
            'position' => 3,
            'is_active' => false,
        ]);

        $response = $this->getJson(
            '/api/catalog/homepage-campaigns'
        );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.title',
                'Visible campaign'
            );
    }

    public function test_admin_can_update_campaign(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $campaign = HomepageCampaign::query()->create([
            'title' => 'Old campaign',
            'desktop_image_path' => 'homepage-campaigns/original.jpg',
            'link_type' => 'none',
            'card_size' => 'small',
            'position' => 4,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/homepage-campaigns/{$campaign->public_id}",
                [
                    'title' => 'Updated campaign',
                    'position' => 2,
                    'card_size' => 'medium',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.title',
                'Updated campaign'
            )
            ->assertJsonPath(
                'data.card_size',
                'medium'
            );

        $this->assertDatabaseHas(
            'homepage_campaigns',
            [
                'id' => $campaign->id,
                'title' => 'Updated campaign',
                'position' => 2,
            ]
        );
    }

    public function test_admin_can_delete_campaign_and_images(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        Storage::disk('public')->put(
            'homepage-campaigns/test.jpg',
            'image'
        );

        $campaign = HomepageCampaign::query()->create([
            'title' => 'Campaign to delete',
            'desktop_image_path' => 'homepage-campaigns/test.jpg',
            'link_type' => 'none',
            'card_size' => 'small',
            'position' => 1,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->deleteJson(
                "/api/admin/homepage-campaigns/{$campaign->public_id}"
            );

        $response
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing(
            'homepage_campaigns',
            [
                'id' => $campaign->id,
            ]
        );

        Storage::disk('public')->assertMissing(
            'homepage-campaigns/test.jpg'
        );
    }

    public function test_non_admin_cannot_manage_campaigns(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);

        $response = $this
            ->actingAs($seller, 'sanctum')
            ->getJson('/api/admin/homepage-campaigns');

        $response->assertForbidden();
    }
}
