<?php

declare(strict_types=1);

const ABSPATH = __DIR__ . '/wp/';
const HOUR_IN_SECONDS = 3600;
const WEEK_IN_SECONDS = 604800;

$GLOBALS['cc_wp_options'] = [];
$GLOBALS['cc_wp_transients'] = [];
$GLOBALS['cc_http_response'] = null;
$GLOBALS['cc_http_calls'] = 0;

function add_shortcode(string $tag, callable $callback): void
{
}

function add_action(string $hook, callable $callback): void
{
}

function get_option(string $key, mixed $default = false): mixed
{
    return $GLOBALS['cc_wp_options'][$key] ?? $default;
}

function update_option(string $key, mixed $value, bool $autoload = true): bool
{
    $GLOBALS['cc_wp_options'][$key] = $value;

    return true;
}

function delete_option(string $key): bool
{
    unset($GLOBALS['cc_wp_options'][$key]);

    return true;
}

function get_transient(string $key): mixed
{
    return $GLOBALS['cc_wp_transients'][$key]['value'] ?? false;
}

function set_transient(string $key, mixed $value, int $expiration = 0): bool
{
    $GLOBALS['cc_wp_transients'][$key] = [
        'value' => $value,
        'expiration' => $expiration,
    ];

    return true;
}

function delete_transient(string $key): bool
{
    unset($GLOBALS['cc_wp_transients'][$key]);

    return true;
}

function add_query_arg(array|string $key, mixed $value = null, ?string $url = null): string
{
    if (is_array($key)) {
        return ($url ?? '') . '?' . http_build_query($key);
    }

    return ($url ?? '') . '?' . rawurlencode($key) . '=' . rawurlencode((string) $value);
}

function wp_remote_get(string $url, array $args = []): mixed
{
    $GLOBALS['cc_http_calls']++;

    return $GLOBALS['cc_http_response'];
}

function is_wp_error(mixed $value): bool
{
    return $value === 'wp_error';
}

function wp_remote_retrieve_response_code(mixed $response): int
{
    return is_array($response) ? (int) ($response['code'] ?? 0) : 0;
}

function wp_remote_retrieve_body(mixed $response): string
{
    return is_array($response) ? (string) ($response['body'] ?? '') : '';
}

function current_time(string $format): string
{
    return '2026-05-26 12:34';
}

function number_format_i18n(float|int $number, int $decimals = 0): string
{
    return number_format((float) $number, $decimals, ',', ' ');
}

function shortcode_atts(array $pairs, array $atts, string $shortcode = ''): array
{
    return array_merge($pairs, $atts);
}

function esc_attr(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function esc_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function esc_url(string $value): string
{
    return esc_attr($value);
}

function home_url(string $path = ''): string
{
    return 'https://example.test' . $path;
}

function wp_register_style(string $handle, mixed $src = false, array $deps = [], string|bool|null $ver = false): bool
{
    return true;
}

function wp_enqueue_style(string $handle): bool
{
    return true;
}

function wp_add_inline_style(string $handle, string $data): bool
{
    return true;
}

require __DIR__ . '/../cc-google-rating-badge.php';

function reset_wp_state(): void
{
    $GLOBALS['cc_wp_options'] = [];
    $GLOBALS['cc_wp_transients'] = [];
    $GLOBALS['cc_http_response'] = null;
    $GLOBALS['cc_http_calls'] = 0;
}

function private_static(string $method): ReflectionMethod
{
    $reflection = new ReflectionMethod(CC_Google_Rating_Badge::class, $method);
    $reflection->setAccessible(true);

    return $reflection;
}

function assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function assert_contains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException($message . ' Missing fragment: ' . $needle);
    }
}

function assert_not_contains(string $needle, string $haystack, string $message): void
{
    if (str_contains($haystack, $needle)) {
        throw new RuntimeException($message . ' Unexpected fragment: ' . $needle);
    }
}

function assert_occurrences(int $expected, string $needle, string $haystack, string $message): void
{
    $actual = substr_count($haystack, $needle);

    if ($actual !== $expected) {
        throw new RuntimeException($message . ' Expected ' . $expected . ' occurrences of ' . $needle . ', got ' . $actual);
    }
}

function set_badge_options(array $overrides): void
{
    update_option('cc_google_rating_badge_options', array_merge([
        'api_key' => 'test-key',
        'place_id' => 'current-place',
        'profile_url' => 'https://example.test/profile',
        'review_url' => 'https://example.test/review',
        'cache_hours' => 24,
        'fallback_rating' => 4.7,
        'fallback_count' => 77,
    ], $overrides), false);
}

function google_response(float $rating = 4.9, int $count = 123): array
{
    return [
        'code' => 200,
        'body' => json_encode([
            'status' => 'OK',
            'result' => [
                'rating' => $rating,
                'user_ratings_total' => $count,
            ],
        ], JSON_THROW_ON_ERROR),
    ];
}

function test_review_count_label(): void
{
    $method = private_static('review_count_label');
    $cases = [
        1 => '1 opinia',
        2 => '2 opinie',
        5 => '5 opinii',
        12 => '12 opinii',
        14 => '14 opinii',
        21 => '21 opinii',
        22 => '22 opinie',
        24 => '24 opinie',
        101 => '101 opinii',
        102 => '102 opinie',
        112 => '112 opinii',
    ];

    foreach ($cases as $count => $expected) {
        assert_same($expected, $method->invoke(null, $count), 'Invalid Polish review label for ' . $count . '.');
    }
}

function test_get_rating_data_ignores_mismatched_cached_and_last_good_place_id(): void
{
    reset_wp_state();
    set_badge_options(['place_id' => 'current-place']);
    set_transient('cc_google_rating_badge_data', [
        'rating' => 1.1,
        'review_count' => 11,
        'updated_at' => 'old',
        'source' => 'google_places_api',
        'place_id' => 'old-place',
    ], 86400);
    update_option('cc_google_rating_badge_last_good', [
        'rating' => 2.2,
        'review_count' => 22,
        'updated_at' => 'older',
        'source' => 'google_places_api',
        'place_id' => 'old-place',
    ], false);
    $GLOBALS['cc_http_response'] = google_response(4.9, 123);

    $data = private_static('get_rating_data')->invoke(null, false);

    assert_same('google_places_api', $data['source'], 'Mismatched cached data must not be returned.');
    assert_same('current-place', $data['place_id'], 'Fetched data must use current Place ID.');
    assert_same(123, $data['review_count'], 'Fetched review count should be used.');
    assert_same(1, $GLOBALS['cc_http_calls'], 'API should be called after cached Place ID mismatch.');
}

function test_api_fail_uses_matching_last_good_cache(): void
{
    reset_wp_state();
    set_badge_options(['place_id' => 'current-place']);
    update_option('cc_google_rating_badge_last_good', [
        'rating' => 4.8,
        'review_count' => 82,
        'updated_at' => '2026-05-20 10:00',
        'source' => 'google_places_api',
        'place_id' => 'current-place',
    ], false);
    $GLOBALS['cc_http_response'] = ['code' => 500, 'body' => '{}'];

    $data = private_static('get_rating_data')->invoke(null, false);

    assert_same('last_good_cache', $data['source'], 'Matching last_good should be used after API failure.');
    assert_same(HOUR_IN_SECONDS, $GLOBALS['cc_wp_transients']['cc_google_rating_badge_data']['expiration'], 'Error path TTL should be fixed at 1h for last_good.');
}

function test_api_fail_without_matching_last_good_uses_fallback(): void
{
    reset_wp_state();
    set_badge_options([
        'place_id' => 'current-place',
        'fallback_rating' => 4.6,
        'fallback_count' => 64,
    ]);
    update_option('cc_google_rating_badge_last_good', [
        'rating' => 4.8,
        'review_count' => 82,
        'updated_at' => '2026-05-20 10:00',
        'source' => 'google_places_api',
        'place_id' => 'other-place',
    ], false);
    $GLOBALS['cc_http_response'] = ['code' => 500, 'body' => '{}'];

    $data = private_static('get_rating_data')->invoke(null, false);

    assert_same('fallback', $data['source'], 'Fallback should be used when API and matching last_good are unavailable.');
    assert_same(64, $data['review_count'], 'Fallback review count should be returned.');
    assert_same(HOUR_IN_SECONDS, $GLOBALS['cc_wp_transients']['cc_google_rating_badge_data']['expiration'], 'Error path TTL should be fixed at 1h for fallback.');
}

function test_should_clear_last_good_cache(): void
{
    $method = private_static('should_clear_last_good_cache');

    assert_same(false, $method->invoke(null, ['place_id' => 'same'], ['place_id' => 'same']), 'Unchanged Place ID should keep last_good.');
    assert_same(true, $method->invoke(null, ['place_id' => 'old'], ['place_id' => 'new']), 'Changed Place ID should clear last_good.');
}

function test_render_shortcode_fallback_source_omits_google_attribution(): void
{
    reset_wp_state();
    set_badge_options([
        'api_key' => '',
        'fallback_rating' => 4.6,
        'fallback_count' => 64,
    ]);

    $data = private_static('get_rating_data')->invoke(null, false);
    $html = CC_Google_Rating_Badge::render_shortcode();

    assert_same('fallback', $data['source'], 'Test setup should force fallback source.');
    assert_not_contains('Dane mogą być nieaktualne', $html, 'Fallback source should not render stale-data notice.');
    assert_occurrences(0, 'alt="Powered by Google"', $html, 'Fallback source should not render Google attribution image.');
}

function test_render_shortcode_last_good_source_renders_google_attribution(): void
{
    reset_wp_state();
    set_badge_options(['place_id' => 'current-place']);
    update_option('cc_google_rating_badge_last_good', [
        'rating' => 4.8,
        'review_count' => 82,
        'updated_at' => '2026-05-20 10:00',
        'source' => 'google_places_api',
        'place_id' => 'current-place',
    ], false);
    $GLOBALS['cc_http_response'] = ['code' => 500, 'body' => '{}'];

    $data = private_static('get_rating_data')->invoke(null, false);
    $html = CC_Google_Rating_Badge::render_shortcode();

    assert_same('last_good_cache', $data['source'], 'Test setup should force last-good source.');
    assert_occurrences(1, 'alt="Powered by Google"', $html, 'Last-good source should render Google attribution image.');
    assert_not_contains('Dane mogą być nieaktualne', $html, 'Last-good source should not render stale-data notice.');
}

function test_render_shortcode_show_updated_renders_update_text_when_requested(): void
{
    reset_wp_state();
    set_badge_options(['place_id' => 'current-place']);
    set_transient('cc_google_rating_badge_data', [
        'rating' => 4.9,
        'review_count' => 123,
        'updated_at' => '2026-05-26 12:34',
        'source' => 'google_places_api',
        'place_id' => 'current-place',
    ], HOUR_IN_SECONDS);

    $html = CC_Google_Rating_Badge::render_shortcode(['show_updated' => 'true']);

    assert_contains('Aktualizacja: 2026-05-26 12:34', $html, 'show_updated=true should render update timestamp.');
}

function test_render_shortcode_trust_mode_omits_review_cta(): void
{
    reset_wp_state();
    set_badge_options([
        'profile_url' => 'https://example.test/profile',
        'review_url' => 'https://example.test/review',
    ]);
    set_transient('cc_google_rating_badge_data', [
        'rating' => 4.8,
        'review_count' => 82,
        'updated_at' => '2026-05-26 12:34',
        'source' => 'google_places_api',
        'place_id' => 'current-place',
    ], HOUR_IN_SECONDS);

    $html = CC_Google_Rating_Badge::render_shortcode(['mode' => 'trust']);

    assert_contains('cc-google-rating--trust', $html, 'Trust mode should render trust class.');
    assert_contains('Zobacz opinie', $html, 'Trust mode should keep profile reviews link.');
    assert_not_contains('Zostaw opinię', $html, 'Trust mode should omit review CTA.');
    assert_not_contains('https://example.test/review', $html, 'Trust mode should omit review URL.');
}

function test_defaults_contain_no_company_data(): void
{
    reset_wp_state();

    $options = private_static('get_options')->invoke(null);

    assert_same('', $options['place_id'], 'Default Place ID must be empty.');
    assert_same('', $options['profile_url'], 'Default profile URL must be empty.');
    assert_same('', $options['review_url'], 'Default review URL must be empty.');
    assert_same(0, (int) $options['fallback_count'], 'Default fallback count must be zero.');
    assert_same(0.0, (float) $options['fallback_rating'], 'Default fallback rating must be zero.');
}

function test_render_shortcode_without_configuration_renders_nothing(): void
{
    reset_wp_state();

    $html = CC_Google_Rating_Badge::render_shortcode();

    assert_same('', $html, 'Unconfigured badge must render nothing.');
    assert_same(0, $GLOBALS['cc_http_calls'], 'Unconfigured badge must not call Google.');
}

function test_render_shortcode_without_profile_url_renders_nothing_even_with_data(): void
{
    reset_wp_state();
    set_badge_options(['profile_url' => '']);
    set_transient('cc_google_rating_badge_data', [
        'rating' => 4.8,
        'review_count' => 82,
        'updated_at' => '2026-05-26 12:34',
        'source' => 'google_places_api',
        'place_id' => 'current-place',
    ], HOUR_IN_SECONDS);

    assert_same('', CC_Google_Rating_Badge::render_shortcode(), 'Badge without profile URL must render nothing.');
}

function test_render_shortcode_fallback_with_zero_count_renders_nothing(): void
{
    reset_wp_state();
    set_badge_options(['api_key' => '', 'fallback_rating' => 4.6, 'fallback_count' => 0]);

    assert_same('', CC_Google_Rating_Badge::render_shortcode(), 'Fallback with zero reviews must render nothing.');
}

function test_render_shortcode_manual_fallback_with_count_renders_without_attribution(): void
{
    reset_wp_state();
    set_badge_options(['api_key' => '', 'fallback_rating' => 4.6, 'fallback_count' => 64]);

    $html = CC_Google_Rating_Badge::render_shortcode();

    assert_contains('64 opinie', $html, 'Manual fallback with a positive count should render.');
    assert_contains('Zobacz opinie', $html, 'Manual fallback should keep the profile link.');
    assert_occurrences(0, 'alt="Powered by Google"', $html, 'Manual fallback must not render Google attribution.');
}

function test_render_shortcode_without_review_url_hides_review_cta(): void
{
    reset_wp_state();
    set_badge_options(['review_url' => '']);
    set_transient('cc_google_rating_badge_data', [
        'rating' => 4.8,
        'review_count' => 82,
        'updated_at' => '2026-05-26 12:34',
        'source' => 'google_places_api',
        'place_id' => 'current-place',
    ], HOUR_IN_SECONDS);

    $html = CC_Google_Rating_Badge::render_shortcode();

    assert_contains('Zobacz opinie', $html, 'Profile link should remain without review URL.');
    assert_not_contains('Zostaw opinię', $html, 'Review CTA must be hidden without review URL.');
}

$tests = [
    'test_defaults_contain_no_company_data',
    'test_render_shortcode_without_configuration_renders_nothing',
    'test_render_shortcode_without_profile_url_renders_nothing_even_with_data',
    'test_render_shortcode_fallback_with_zero_count_renders_nothing',
    'test_render_shortcode_manual_fallback_with_count_renders_without_attribution',
    'test_render_shortcode_without_review_url_hides_review_cta',
    'test_review_count_label',
    'test_get_rating_data_ignores_mismatched_cached_and_last_good_place_id',
    'test_api_fail_uses_matching_last_good_cache',
    'test_api_fail_without_matching_last_good_uses_fallback',
    'test_should_clear_last_good_cache',
    'test_render_shortcode_fallback_source_omits_google_attribution',
    'test_render_shortcode_last_good_source_renders_google_attribution',
    'test_render_shortcode_show_updated_renders_update_text_when_requested',
    'test_render_shortcode_trust_mode_omits_review_cta',
];

foreach ($tests as $test) {
    $test();
}

echo count($tests) . " tests passed\n";