<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\StaffCreatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationFeedTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(['is_admin' => true]);

        $this->actingAs($user);

        return $user;
    }

    private function notify(User $user, string $instituteName): void
    {
        $user->notify(new StaffCreatedNotification(User::factory()->create(), $instituteName));
    }

    public function test_feed_requires_authentication(): void
    {
        $this->get(route('notifications.feed'))->assertRedirect();
    }

    public function test_feed_is_restricted_to_admins(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $this->get(route('notifications.feed'))->assertForbidden();
    }

    public function test_feed_returns_the_newest_notifications_first(): void
    {
        $user = $this->admin();

        $this->notify($user, 'First Academy');
        $this->notify($user, 'Second Academy');

        $response = $this->getJson(route('notifications.feed'))
            ->assertOk()
            ->assertJsonCount(2, 'notifications');

        $this->assertSame('Welcome to Second Academy', $response->json('notifications.0.data.title'));
        $this->assertSame('Welcome to First Academy', $response->json('notifications.1.data.title'));
        $this->assertNotNull($response->json('notifications.0.id'));
    }

    public function test_feed_never_exposes_another_users_notifications(): void
    {
        $admin = $this->admin();
        $other = User::factory()->create(['is_admin' => true]);

        $this->notify($other, 'Other Academy');

        $this->getJson(route('notifications.feed'))
            ->assertOk()
            ->assertJsonCount(0, 'notifications');

        $this->notify($admin, 'My Academy');

        $this->getJson(route('notifications.feed'))
            ->assertOk()
            ->assertJsonCount(1, 'notifications');
    }

    public function test_feed_caps_the_limit(): void
    {
        $user = $this->admin();

        for ($i = 0; $i < 3; $i++) {
            $this->notify($user, "Academy {$i}");
        }

        $this->getJson(route('notifications.feed', ['limit' => 5000]))
            ->assertOk()
            ->assertJsonCount(3, 'notifications');
    }
}
