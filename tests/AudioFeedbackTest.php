<?php

use Blemli\AudioFeedback\AudioFeedbackPlugin;
use Blemli\AudioFeedback\Models\AudioFeedbackSetting;
use Blemli\AudioFeedback\MuteTogglePosition;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\PanelRegistry;
use Filament\View\PanelsRenderHook;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cookie;

it('ships sensible default sounds', function () {
    expect(config('audiofeedback.enabled'))->toBeTrue()
        ->and(config('audiofeedback.mute_toggle'))->toBeTrue()
        ->and(config('audiofeedback.sounds'))->toMatchArray([
            'notification.success' => 'success',
            'notification.danger' => 'error',
            'toggle' => 'toggle',
            'toggle-buttons' => 'tick',
            'slider' => 'tick',
            'nav.hover' => 'whisper',
            'form.submit' => 'loading',
            'login' => 'ready',
            'logout' => 'droplet',
            'drag' => 'press',
            'drop' => 'release',
        ]);
});

it('normalizes the volume to a fraction', function () {
    expect(AudioFeedbackPlugin::make()->getVolume())->toBe(0.5)
        ->and(AudioFeedbackPlugin::make()->volume(25)->getVolume())->toBe(0.25)
        ->and(AudioFeedbackPlugin::make()->volume(0.8)->getVolume())->toBe(0.8)
        ->and(AudioFeedbackPlugin::make()->volume(250)->getVolume())->toBe(1.0);

    config()->set('audiofeedback.volume', 10);

    expect(AudioFeedbackPlugin::make()->getVolume())->toBe(0.1);
});

it('positions the mute toggle via config or fluently', function () {
    expect(AudioFeedbackPlugin::make()->getMuteTogglePosition())
        ->toBe(MuteTogglePosition::UserMenuBefore);

    config()->set('audiofeedback.mute_toggle_position', 'topbar-end');

    expect(AudioFeedbackPlugin::make()->getMuteTogglePosition())
        ->toBe(MuteTogglePosition::TopbarEnd)
        ->and(AudioFeedbackPlugin::make()->muteToggle(position: 'user-menu')->getMuteTogglePosition())
        ->toBe(MuteTogglePosition::UserMenu)
        ->and(AudioFeedbackPlugin::make()->muteTogglePosition(MuteTogglePosition::TopbarStart)->getMuteTogglePosition())
        ->toBe(MuteTogglePosition::TopbarStart);
});

it('maps every mute toggle position to a panel render hook', function () {
    expect(MuteTogglePosition::TopbarStart->getRenderHook())->toBe(PanelsRenderHook::TOPBAR_START)
        ->and(MuteTogglePosition::TopbarEnd->getRenderHook())->toBe(PanelsRenderHook::TOPBAR_END)
        ->and(MuteTogglePosition::UserMenuBefore->getRenderHook())->toBe(PanelsRenderHook::USER_MENU_BEFORE)
        ->and(MuteTogglePosition::UserMenu->getRenderHook())->toBe(PanelsRenderHook::USER_MENU_PROFILE_AFTER)
        ->and(MuteTogglePosition::UserMenu->isMenuItem())->toBeTrue()
        ->and(MuteTogglePosition::TopbarEnd->isMenuItem())->toBeFalse();
});

it('lets the plugin override config sounds fluently', function () {
    $plugin = AudioFeedbackPlugin::make()
        ->sound('toggle', 'tick')
        ->disable('form.submit');

    expect($plugin->getSounds())
        ->toMatchArray(['toggle' => 'tick', 'form.submit' => false])
        ->and($plugin->getSounds()['login'])->toBe('ready');
});

it('applies configureUsing callbacks from a service provider', function () {
    AudioFeedbackPlugin::configureUsing(
        fn (AudioFeedbackPlugin $plugin) => $plugin->sound('login', 'bloom'),
    );

    expect(AudioFeedbackPlugin::make()->getSounds()['login'])->toBe('bloom');

    AudioFeedbackPlugin::flushConfigurators();
});

it('respects the config master switch and mute toggle setting', function () {
    config()->set('audiofeedback.enabled', false);
    config()->set('audiofeedback.mute_toggle', false);

    $plugin = AudioFeedbackPlugin::make();

    expect($plugin->isEnabled())->toBeFalse()
        ->and($plugin->hasMuteToggle())->toBeFalse()
        ->and($plugin->enabled()->isEnabled())->toBeTrue()
        ->and($plugin->muteToggle()->hasMuteToggle())->toBeTrue();
});

it('queues a notification sound override cookie', function () {
    $notification = Notification::make('my-notification')->sound('sparkle');

    expect($notification)->toBeInstanceOf(Notification::class);

    $cookie = collect(Cookie::getQueuedCookies())->firstWhere(
        fn ($cookie) => $cookie->getName() === 'audiofeedback_notification_sounds',
    );

    expect($cookie)->not->toBeNull()
        ->and(json_decode($cookie->getValue(), associative: true))
        ->toMatchArray(['my-notification' => 'sparkle']);
});

it('routes delete action notifications through the delete event', function () {
    Notification::make('event-notification')->soundEvent('delete');

    $cookie = collect(Cookie::getQueuedCookies())->firstWhere(
        fn ($cookie) => $cookie->getName() === 'audiofeedback_notification_sounds',
    );

    expect(config('audiofeedback.sounds.delete'))->toBe('droplet')
        ->and(json_decode($cookie->getValue(), associative: true))
        ->toMatchArray(['event-notification' => 'event:delete']);
});

it('marks silent notifications as off', function () {
    Notification::make('quiet-notification')->silent();

    $cookie = collect(Cookie::getQueuedCookies())->firstWhere(
        fn ($cookie) => $cookie->getName() === 'audiofeedback_notification_sounds',
    );

    expect(json_decode($cookie->getValue(), associative: true))
        ->toMatchArray(['quiet-notification' => 'off']);
});

it('honors reduced motion unless told otherwise', function () {
    expect(AudioFeedbackPlugin::make()->ignoresReducedMotion())->toBeFalse()
        ->and(AudioFeedbackPlugin::make()->ignoreReducedMotion()->ignoresReducedMotion())->toBeTrue();

    config()->set('audiofeedback.ignore_reduced_motion', true);

    expect(AudioFeedbackPlugin::make()->ignoresReducedMotion())->toBeTrue();
});

it('keeps the breezy profile section opt-in', function () {
    expect(AudioFeedbackPlugin::make()->hasBreezyProfileSection())->toBeFalse()
        ->and(AudioFeedbackPlugin::make()->breezyProfileSection()->hasBreezyProfileSection())->toBeTrue();

    config()->set('audiofeedback.breezy_profile_section', true);

    expect(AudioFeedbackPlugin::make()->hasBreezyProfileSection())->toBeTrue();
});

it('guards the breezy profile section per user', function () {
    app(PanelRegistry::class)->register(Panel::make()->id('testing')->default());

    $plugin = AudioFeedbackPlugin::make()->breezyProfileSection(
        fn (?Authenticatable $user): bool => $user?->getAuthIdentifier() === 1,
    );

    // A closure counts as enabled for registration, decided per user later.
    expect($plugin->hasBreezyProfileSection())->toBeTrue()
        ->and($plugin->shouldShowBreezyProfileSection())->toBeFalse(); // guest

    $this->loadLaravelMigrations();

    $user = User::forceCreate([
        'name' => 'Dr. Mausiavelli',
        'email' => 'mouse@example.com',
        'password' => bcrypt('cheese'),
    ]);

    $this->actingAs($user);

    expect($plugin->shouldShowBreezyProfileSection())->toBeTrue()
        ->and(AudioFeedbackPlugin::make()->breezyProfileSection(false)->shouldShowBreezyProfileSection())->toBeFalse()
        ->and(AudioFeedbackPlugin::make()->breezyProfileSection()->shouldShowBreezyProfileSection())->toBeTrue();
});

it('persists per-user settings through the endpoint', function () {
    $this->loadLaravelMigrations();
    $this->artisan('migrate');

    $this->postJson(route('audiofeedback.settings'), [])->assertUnauthorized();

    $user = User::forceCreate([
        'name' => 'Dr. Mausiavelli',
        'email' => 'mouse@example.com',
        'password' => bcrypt('cheese'),
    ]);

    $this->actingAs($user)
        ->postJson(route('audiofeedback.settings'), [
            'muted' => false,
            'volume' => 40,
            'overrides' => ['toggle' => 'tick', 'nav.hover' => 'off'],
        ])
        ->assertSuccessful();

    expect(AudioFeedbackSetting::for($user->getKey()))->toMatchArray([
        'muted' => false,
        'volume' => 40,
        'overrides' => ['toggle' => 'tick', 'nav.hover' => 'off'],
    ]);

    $this->actingAs($user)
        ->postJson(route('audiofeedback.settings'), [
            'muted' => true,
            'volume' => 40,
            'overrides' => ['toggle' => 'not-a-sound'],
        ])
        ->assertUnprocessable();
});

it('queues a cue cookie on login', function () {
    event(new Login('web', new User, false));

    $cookie = collect(Cookie::getQueuedCookies())->firstWhere(
        fn ($cookie) => $cookie->getName() === 'audiofeedback_cue',
    );

    expect($cookie)->not->toBeNull()
        ->and($cookie->getValue())->toBe('login');
});

it('registers custom samples next to the cuelume cues', function () {
    config()->set('audiofeedback.custom_sounds', ['bell' => '/audio/bell.mp3']);

    $plugin = AudioFeedbackPlugin::make()
        ->customSound('shutter', '/audio/shutter.mp3')
        ->sound('notification.success', 'shutter');

    expect($plugin->getCustomSounds())->toBe(['bell' => '/audio/bell.mp3', 'shutter' => '/audio/shutter.mp3'])
        ->and($plugin->getSoundNames())->toContain('chime', 'bell', 'shutter')
        ->and($plugin->getSounds()['notification.success'])->toBe('shutter');
});

it('accepts custom sample names in per-user overrides', function () {
    $this->loadLaravelMigrations();
    $this->artisan('migrate');

    app(PanelRegistry::class)->register(
        Panel::make()->id('custom')->default()->plugin(AudioFeedbackPlugin::make()->customSound('shutter', '/audio/shutter.mp3')),
    );

    $user = User::forceCreate([
        'name' => 'Dr. Mausiavelli',
        'email' => 'mouse@example.com',
        'password' => bcrypt('cheese'),
    ]);

    $this->actingAs($user)
        ->postJson(route('audiofeedback.settings'), [
            'muted' => false,
            'volume' => 40,
            'overrides' => ['notification.success' => 'shutter'],
        ])
        ->assertSuccessful();

    expect(AudioFeedbackSetting::for($user->getKey())['overrides'])->toBe(['notification.success' => 'shutter']);
});

it('takes the sidebar shape next to the user menu in the sidebar, the icon button elsewhere', function () {
    app(PanelRegistry::class)->register(Panel::make()->id('shape')->default()->topbar(false)->plugin(AudioFeedbackPlugin::make()));

    $sidebar = Blade::render('<x-audiofeedback::mute-toggle :sidebar="true" />');
    $topbar = Blade::render('<x-audiofeedback::mute-toggle />');
    $menu = Blade::render('<x-audiofeedback::mute-toggle :menu-item="true" />');

    expect($sidebar)->toContain('fi-audiofeedback-sidebar-btn')->not->toContain('fi-icon-btn')
        ->and($sidebar)->toContain('fi-audiofeedback-sidebar-btn-label')
        ->and($topbar)->toContain('class="fi-icon-btn"')->not->toContain('fi-audiofeedback-sidebar-btn')
        ->and($menu)->toContain('fi-dropdown-list-item')->not->toContain('fi-icon-btn');
});
