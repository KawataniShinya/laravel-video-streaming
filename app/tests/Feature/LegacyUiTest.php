<?php

namespace Tests\Feature;

use App\Models\Favorite;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LegacyUiTest extends TestCase
{
    use RefreshDatabase;

    private const TUNER = 'Mozilla/5.0 (Linux; PASTB; rv:34.0) Gecko/20100101 Firefox/34.0 SmartTV';
    private const MODERN = 'Mozilla/5.0 Chrome/154.0.0.0 Safari/537.36';

    public function test_tuner_and_old_desktop_browsers_receive_a_real_login_form(): void
    {
        foreach ([self::TUNER, 'Mozilla/5.0 Chrome/51.0.2704.100 Safari/537.36', 'Mozilla/5.0 Firefox/34.0'] as $ua) {
            $this->withHeader('User-Agent', $ua)->get('/login')->assertOk()
                ->assertViewIs('legacy.auth.login')->assertSee('name="password"', false)
                ->assertCookie('ui_mode', 'legacy')
                ->assertSee('name="_token"', false)->assertDontSee('type="module"', false);
        }
    }

    public function test_modern_browsers_keep_inertia_and_can_force_legacy(): void
    {
        $this->withHeader('User-Agent', self::MODERN)->get('/login')->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
        $this->withCookie('ui_mode', 'legacy')->get('/login')->assertViewIs('legacy.auth.login');
    }

    public function test_manual_modern_cookie_overrides_tuner_detection(): void
    {
        $this->withHeader('User-Agent', self::TUNER)->withCookie('ui_mode', 'modern')->get('/login')
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'))
            ->assertSee('data-forced-modern="1"', false);
    }

    public function test_switch_saves_cookie_and_only_accepts_local_redirects(): void
    {
        $this->get('/ui/legacy?return=%2Flogin')->assertRedirect('/login')->assertCookie('ui_mode', 'legacy');
        foreach (['https://example.org', '//example.org', '/\\example.org', '/%2Fexample.org', "/\nexample.org"] as $target) {
            $this->get('/ui/legacy?' . http_build_query(['return' => $target]))->assertRedirect('/');
        }
        $this->get('/ui/auto')->assertCookieExpired('ui_mode');
        $this->get('/ui/unknown')->assertNotFound();
    }

    public function test_inertia_visits_become_full_document_navigations(): void
    {
        $version = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
        $this->withHeader('X-Inertia-Version', $version);
        $this->withHeader('User-Agent', self::TUNER)->withHeader('X-Inertia', 'true')->get('/login')
            ->assertStatus(409)->assertHeader('X-Inertia-Location', url('/login'));
        $this->get('/ui/legacy?return=%2Flogin')->assertStatus(409)->assertHeader('X-Inertia-Location', '/login');
    }

    public function test_legacy_login_validation_authentication_and_logout(): void
    {
        $user = User::factory()->create();
        $this->withHeader('User-Agent', self::TUNER)->from('/login')->post('/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->get('/login')->assertSee('role="alert"', false);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->get('/dashboard')->assertViewIs('legacy.dashboard');
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_legacy_invalid_email_is_reported_in_the_document_without_native_validation(): void
    {
        $this->withHeader('User-Agent', self::TUNER)->get('/login')
            ->assertSee('class="login" novalidate', false);
        $this->from('/login')->post('/login', ['email' => 'not-an-email', 'password' => 'unused'])
            ->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->get('/login')->assertSee('role="alert"', false)
            ->assertSee('メールアドレスの形式で入力してください')
            ->assertSee('value="not-an-email"', false)
            ->assertSee('aria-invalid="true"', false);
        $this->assertGuest();
    }

    public function test_legacy_empty_fields_have_visible_required_messages(): void
    {
        $this->withHeader('User-Agent', self::TUNER)->from('/login')->post('/login', ['email' => '', 'password' => ''])
            ->assertRedirect('/login')->assertSessionHasErrors(['email', 'password']);
        $this->get('/login')->assertSee('メールアドレスを入力してください。')
            ->assertSee('パスワードを入力してください。');
        $this->assertGuest();
    }

    public function test_modern_login_keeps_its_existing_validation(): void
    {
        $this->withHeader('User-Agent', self::MODERN)->get('/login')->assertDontSee('novalidate');
        $this->from('/login')->post('/login', ['email' => 'not-an-email', 'password' => 'unused'])
            ->assertSessionHasErrors(['email' => 'The email field must be a valid email address.']);
        $this->assertGuest();
    }

    public function test_guest_video_routes_still_require_authentication(): void
    {
        foreach (['/dashboard', '/videos', '/watch/movies/main.mp4', '/favorites', '/history', '/hls/hash/index.m3u8'] as $url) {
            $this->withHeader('User-Agent', self::TUNER)->get($url)->assertRedirect('/login');
        }
    }

    public function test_all_viewing_screens_render_with_encoded_paths_and_existing_data(): void
    {
        $user = User::factory()->create();
        $user->allowedPaths()->create(['path' => 'movies']);
        $path = 'movies/日本語 #1.mp4';
        $this->makeVideoFile($path);
        $video = $this->getOrCreateVideo($path, 'file');
        Favorite::create(['user_id' => $user->id, 'video_id' => $video->id, 'type' => 'file']);
        VideoView::create(['user_id' => $user->id, 'video_id' => $video->id, 'last_position' => 35]);
        $this->actingAs($user)->withHeader('User-Agent', self::TUNER);
        $this->get('/dashboard')->assertViewIs('legacy.dashboard');
        $this->get('/videos/movies')->assertViewIs('legacy.videos.index')
            ->assertSee('/watch/movies/' . rawurlencode('日本語 #1.mp4'), false)->assertSee('お気に入り解除');
        $this->get('/favorites')->assertViewIs('legacy.favorites.index')->assertSee('00:00:35');
        $this->get('/history')->assertViewIs('legacy.videos.history')->assertSee('00:00:35');
        $this->get('/watch/movies/' . rawurlencode('日本語 #1.mp4'))->assertViewIs('legacy.videos.watch')
            ->assertSee('data-kind="mp4"', false)->assertSee('data-position="35"', false)
            ->assertSee('/legacy/player.js')->assertDontSee('/legacy/hls.min.js');
    }

    public function test_hls_watch_reuses_existing_cache_and_loads_es5_library(): void
    {
        $user = User::factory()->create();
        $user->allowedPaths()->create(['path' => 'movies']);
        $path = 'movies/archive.m2ts';
        $this->makeVideoFile($path);
        $this->makeHlsCache(md5($path));
        $this->actingAs($user)->withHeader('User-Agent', self::TUNER)->get('/watch/' . $path)
            ->assertViewIs('legacy.videos.watch')->assertSee('/hls/' . md5($path) . '/index.m3u8')
            ->assertSee('/legacy/hls.min.js')->assertSee('data-kind="hls"', false);
    }

    public function test_legacy_forms_and_progress_reuse_existing_mutations(): void
    {
        $user = User::factory()->create();
        $user->allowedPaths()->create(['path' => 'movies']);
        $video = $this->getOrCreateVideo('movies/main.mp4', 'file');
        $this->actingAs($user)->withHeader('User-Agent', self::TUNER)->from('/videos/movies');
        $this->post('/favorites/toggle', ['path' => $video->path, 'type' => 'file'])->assertRedirect('/videos/movies');
        $this->assertDatabaseHas('favorites', ['user_id' => $user->id, 'video_id' => $video->id]);
        $this->post('/videos/watched/toggle', ['path' => $video->path])->assertRedirect('/videos/movies');
        $this->postJson('/videos/progress', ['path' => $video->path, 'time' => 120])->assertOk()->assertJson(['status' => 'success']);
        $this->assertDatabaseHas('video_views', ['user_id' => $user->id, 'video_id' => $video->id, 'last_position' => 120]);
        $this->post('/favorites/toggle', ['path' => $video->path, 'type' => 'file']);
        $this->assertDatabaseMissing('favorites', ['user_id' => $user->id, 'video_id' => $video->id]);
    }

    public function test_legacy_mode_does_not_grant_video_access(): void
    {
        $user = User::factory()->create();
        $user->allowedPaths()->create(['path' => 'movies']);
        $this->makeVideoFile('private/secret.mp4');
        $this->actingAs($user)->withHeader('User-Agent', self::TUNER)->get('/watch/private/secret.mp4')->assertRedirect('/dashboard');
        $this->postJson('/videos/progress', ['path' => 'private/secret.mp4', 'time' => 12])->assertForbidden();
    }

    public function test_unsupported_screens_return_to_legacy_home_instead_of_a_blank_page(): void
    {
        $this->withHeader('User-Agent', self::TUNER)->get('/forgot-password')->assertRedirect('/login')->assertSessionHas('status');
        $this->actingAs(User::factory()->create())->get('/profile')->assertRedirect('/dashboard');
    }
}
