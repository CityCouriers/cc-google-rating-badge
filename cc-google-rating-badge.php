<?php
/**
 * Plugin Name: CC Google Rating Badge
 * Plugin URI: https://github.com/CityCouriers/cc-google-rating-badge
 * Description: Lightweight CityCouriers badge showing Google aggregate rating and review count without review quotes or external frontend scripts.
 * Version: 0.4.0
 * Requires PHP: 7.4
 * Author: CityCouriers
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

final class CC_Google_Rating_Badge
{
    private const OPTION_KEY = 'cc_google_rating_badge_options';
    private const TRANSIENT_KEY = 'cc_google_rating_badge_data';
    private const LAST_GOOD_KEY = 'cc_google_rating_badge_last_good';
    private const ATTRIBUTION_TRANSIENT_KEY = 'cc_google_rating_badge_attribution_image';
    private const ATTRIBUTION_LOCK_TRANSIENT_KEY = 'cc_google_rating_badge_attribution_image_lock';
    private const ATTRIBUTION_MAX_BYTES = 102400;

    private static bool $style_added = false;

    public static function boot(): void
    {
        add_shortcode('cc_google_rating_badge', [self::class, 'render_shortcode']);
        add_action('template_redirect', [self::class, 'serve_attribution_image']);
        add_action('admin_menu', [self::class, 'add_settings_page']);
        add_action('admin_post_cc_google_rating_badge_save', [self::class, 'save_settings']);
        add_action('admin_post_cc_google_rating_badge_refresh', [self::class, 'refresh_cache']);
    }

    public static function add_settings_page(): void
    {
        add_options_page(
            'CC Google Rating',
            'CC Google Rating',
            'manage_options',
            'cc-google-rating-badge',
            [self::class, 'render_settings_page']
        );
    }

    public static function render_settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień.', '', ['response' => 403]);
        }

        $options = self::get_options();
        $data = self::get_rating_data(false);
        ?>
        <div class="wrap">
            <h1>CC Google Rating</h1>
            <?php if (isset($_GET['updated'])) : ?>
                <div class="notice notice-success is-dismissible"><p>Ustawienia zostały zapisane.</p></div>
            <?php endif; ?>
            <?php if (isset($_GET['refreshed'])) : ?>
                <div class="notice notice-success is-dismissible"><p>Dane z Google zostały odświeżone albo zastąpione fallbackiem.</p></div>
            <?php endif; ?>
            <?php if (!self::is_configured($options, $data)) : ?>
                <div class="notice notice-warning"><p>Badge nie wyświetla się na stronie: uzupełnij link do profilu Google oraz klucz API z Place ID albo ręczny fallback z liczbą opinii większą od zera.</p></div>
            <?php endif; ?>
            <p>Badge pokazuje tylko agregat Google: średnią ocenę i liczbę opinii. Nie pobiera cytatów, nazwisk ani zdjęć recenzentów.</p>

            <h2>Aktualny wynik</h2>
            <p>
                Ocena: <strong><?php echo esc_html(self::format_rating($data['rating'])); ?>/5</strong>,
                opinie: <strong><?php echo esc_html((string) $data['review_count']); ?></strong>,
                źródło: <strong><?php echo esc_html($data['source']); ?></strong>,
                ostatnia aktualizacja: <strong><?php echo esc_html($data['updated_at'] ?: 'brak'); ?></strong>
            </p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('cc_google_rating_badge_save'); ?>
                <input type="hidden" name="action" value="cc_google_rating_badge_save">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="cc_api_key">Google Places API key</label></th>
                        <td>
                            <input id="cc_api_key" name="api_key" type="password" class="regular-text" value="" autocomplete="new-password" placeholder="<?php echo $options['api_key'] ? esc_attr('zapisany - zostaw puste, aby nie zmieniać') : esc_attr('wklej klucz API'); ?>">
                            <p class="description">Klucz jest zapisywany w bazie WordPress. Nie wpisuj go do repo. Pole puste nie zmienia istniejącego klucza.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cc_place_id">Google Place ID</label></th>
                        <td><input id="cc_place_id" name="place_id" type="text" class="regular-text" value="<?php echo esc_attr($options['place_id']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cc_profile_url">Link do profilu Google</label></th>
                        <td><input id="cc_profile_url" name="profile_url" type="url" class="regular-text" value="<?php echo esc_url($options['profile_url']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cc_review_url">Link do wystawienia opinii</label></th>
                        <td><input id="cc_review_url" name="review_url" type="url" class="regular-text" value="<?php echo esc_url($options['review_url']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cc_cache_hours">Cache danych z Google</label></th>
                        <td><input id="cc_cache_hours" name="cache_hours" type="number" min="1" max="168" value="<?php echo esc_attr((string) $options['cache_hours']); ?>"> godzin</td>
                    </tr>
                    <tr>
                        <th scope="row">Fallback, gdy API nie odpowiada</th>
                        <td>
                            <label>Ocena <input name="fallback_rating" type="number" min="0" max="5" step="0.1" value="<?php echo esc_attr((string) $options['fallback_rating']); ?>"></label>
                            <label style="margin-left:16px;">Liczba opinii <input name="fallback_count" type="number" min="0" step="1" value="<?php echo esc_attr((string) $options['fallback_count']); ?>"></label>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Zapisz ustawienia'); ?>
            </form>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:12px;">
                <?php wp_nonce_field('cc_google_rating_badge_refresh'); ?>
                <input type="hidden" name="action" value="cc_google_rating_badge_refresh">
                <?php submit_button('Odśwież dane z Google teraz', 'secondary', 'submit', false); ?>
            </form>

            <h2>Shortcode</h2>
            <code>[cc_google_rating_badge]</code>
            <p>Wariant zaufania bez przycisku wystawienia opinii: <code>[cc_google_rating_badge mode="trust"]</code></p>
            <p>Wariant kompaktowy: <code>[cc_google_rating_badge variant="compact"]</code></p>
        </div>
        <?php
    }

    public static function save_settings(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień.', '', ['response' => 403]);
        }

        check_admin_referer('cc_google_rating_badge_save');

        $current = self::get_options();
        $api_key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : '';

        $options = [
            'api_key' => $api_key !== '' ? $api_key : $current['api_key'],
            'place_id' => isset($_POST['place_id']) ? sanitize_text_field(wp_unslash($_POST['place_id'])) : $current['place_id'],
            'profile_url' => isset($_POST['profile_url']) ? esc_url_raw(wp_unslash($_POST['profile_url'])) : $current['profile_url'],
            'review_url' => isset($_POST['review_url']) ? esc_url_raw(wp_unslash($_POST['review_url'])) : $current['review_url'],
            'cache_hours' => isset($_POST['cache_hours']) ? max(1, min(168, absint($_POST['cache_hours']))) : $current['cache_hours'],
            'fallback_rating' => isset($_POST['fallback_rating']) ? min(5, max(0, (float) wp_unslash($_POST['fallback_rating']))) : $current['fallback_rating'],
            'fallback_count' => isset($_POST['fallback_count']) ? max(0, absint($_POST['fallback_count'])) : $current['fallback_count'],
        ];

        update_option(self::OPTION_KEY, $options, false);
        delete_transient(self::TRANSIENT_KEY);
        if (self::should_clear_last_good_cache($current, $options)) {
            delete_option(self::LAST_GOOD_KEY);
        }

        wp_safe_redirect(admin_url('options-general.php?page=cc-google-rating-badge&updated=1'));
        exit;
    }

    public static function refresh_cache(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień.', '', ['response' => 403]);
        }

        check_admin_referer('cc_google_rating_badge_refresh');
        delete_transient(self::TRANSIENT_KEY);
        self::get_rating_data(true);

        wp_safe_redirect(admin_url('options-general.php?page=cc-google-rating-badge&refreshed=1'));
        exit;
    }

    public static function render_shortcode($atts = []): string
    {
        $atts = is_array($atts) ? $atts : [];

        $atts = shortcode_atts([
            'mode' => 'full',
            'variant' => 'default',
            'show_updated' => 'false',
        ], $atts, 'cc_google_rating_badge');

        $options = self::get_options();
        $data = self::get_rating_data(false);

        // Bez konfiguracji badge nie renderuje niczego (brak domyślnych danych firmy w kodzie).
        if (!self::is_configured($options, $data)) {
            return '';
        }

        self::enqueue_style();

        $mode = $atts['mode'] === 'trust' ? 'trust' : 'full';
        $variant = $atts['variant'] === 'compact' ? 'compact' : 'default';
        $rating = self::format_rating($data['rating']);
        $review_label = self::review_count_label((int) $data['review_count']);
        $stars_label = sprintf('Ocena Google %s na 5 na podstawie %s', $rating, $review_label);

        ob_start();
        ?>
        <div class="cc-google-rating cc-google-rating--<?php echo esc_attr($variant); ?> cc-google-rating--<?php echo esc_attr($mode); ?>" aria-label="<?php echo esc_attr($stars_label); ?>">
            <div class="cc-google-rating__main">
                <span class="cc-google-rating__source">Ocena Google</span>
                <span class="cc-google-rating__stars" aria-hidden="true">★★★★★</span>
                <strong class="cc-google-rating__score"><?php echo esc_html($rating); ?> / 5</strong>
                <span class="cc-google-rating__count"><?php echo esc_html($review_label); ?></span>
            </div>
            <div class="cc-google-rating__actions">
                <a href="<?php echo esc_url($options['profile_url']); ?>" target="_blank" rel="noopener" data-cc-event="google_reviews_view_click">Zobacz opinie</a>
                <?php if ('full' === $mode && '' !== $options['review_url']) : ?>
                    <a href="<?php echo esc_url($options['review_url']); ?>" target="_blank" rel="noopener" data-cc-event="google_review_cta_click">Zostaw opinię</a>
                <?php endif; ?>
            </div>
            <div class="cc-google-rating__meta">
                <?php if ($data['source'] !== 'fallback') : ?>
                    <img src="<?php echo esc_url(self::attribution_image_url()); ?>" alt="Powered by Google" loading="lazy" decoding="async">
                <?php endif; ?>
                <?php if ($atts['show_updated'] === 'true' && $data['updated_at']) : ?>
                    <span>Aktualizacja: <?php echo esc_html($data['updated_at']); ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Badge jest skonfigurowany, gdy ma link do profilu Google oraz dane do pokazania:
     * wynik z Google (API albo ostatni poprawny) albo ręczny fallback z liczbą opinii powyżej zera.
     */
    private static function is_configured(array $options, array $data): bool
    {
        if ('' === $options['profile_url']) {
            return false;
        }

        return 'fallback' !== $data['source'] || (int) $data['review_count'] > 0;
    }

    private static function get_options(): array
    {
        $defaults = [
            'api_key' => '',
            'place_id' => '',
            'profile_url' => '',
            'review_url' => '',
            'cache_hours' => 24,
            'fallback_rating' => 0.0,
            'fallback_count' => 0,
        ];

        $saved = get_option(self::OPTION_KEY, []);
        return array_merge($defaults, is_array($saved) ? $saved : []);
    }

    private static function get_rating_data(bool $force_refresh): array
    {
        $options = self::get_options();

        if (!$force_refresh) {
            $cached = get_transient(self::TRANSIENT_KEY);
            if (is_array($cached) && ($cached['place_id'] ?? '') === $options['place_id']) {
                return $cached;
            }
        }

        $data = self::fetch_from_google($options);

        if ($data === null) {
            $last_good = get_option(self::LAST_GOOD_KEY, null);
            if (is_array($last_good) && ($last_good['place_id'] ?? '') === $options['place_id']) {
                $last_good['source'] = 'last_good_cache';
                set_transient(self::TRANSIENT_KEY, $last_good, HOUR_IN_SECONDS);
                return $last_good;
            }

            $fallback = [
                'rating' => (float) $options['fallback_rating'],
                'review_count' => (int) $options['fallback_count'],
                'updated_at' => '',
                'source' => 'fallback',
                'place_id' => $options['place_id'],
            ];

            set_transient(self::TRANSIENT_KEY, $fallback, HOUR_IN_SECONDS);
            return $fallback;
        }

        set_transient(self::TRANSIENT_KEY, $data, HOUR_IN_SECONDS * (int) $options['cache_hours']);
        update_option(self::LAST_GOOD_KEY, $data, false);
        return $data;
    }

    private static function should_clear_last_good_cache(array $current, array $options): bool
    {
        return ($options['place_id'] ?? '') !== ($current['place_id'] ?? '');
    }

    private static function fetch_from_google(array $options): ?array
    {
        if ($options['api_key'] === '' || $options['place_id'] === '') {
            return null;
        }

        $url = add_query_arg([
            'place_id' => $options['place_id'],
            'fields' => 'rating,user_ratings_total',
            'key' => $options['api_key'],
            'language' => 'pl',
        ], 'https://maps.googleapis.com/maps/api/place/details/json');

        $response = wp_remote_get($url, [
            'timeout' => 8,
            'redirection' => 2,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body) || ($body['status'] ?? '') !== 'OK' || !isset($body['result'])) {
            return null;
        }

        $result = $body['result'];
        if (!isset($result['rating'], $result['user_ratings_total'])) {
            return null;
        }

        return [
            'rating' => (float) $result['rating'],
            'review_count' => (int) $result['user_ratings_total'],
            'updated_at' => current_time('Y-m-d H:i'),
            'source' => 'google_places_api',
            'place_id' => $options['place_id'],
        ];
    }

    private static function format_rating(float $rating): string
    {
        return number_format_i18n(round($rating, 1), 1);
    }

    private static function review_count_label(int $count): string
    {
        $last_two = $count % 100;
        $last_one = $count % 10;
        $noun = 'opinii';

        if ($count === 1) {
            $noun = 'opinia';
        } elseif ($last_one >= 2 && $last_one <= 4 && !($last_two >= 12 && $last_two <= 14)) {
            $noun = 'opinie';
        }

        return sprintf('%s %s', number_format_i18n($count), $noun);
    }

    private static function enqueue_style(): void
    {
        if (self::$style_added) {
            return;
        }

        self::$style_added = true;
        wp_register_style('cc-google-rating-badge', false, [], '0.4.0');
        wp_enqueue_style('cc-google-rating-badge');
        wp_add_inline_style('cc-google-rating-badge', self::css());
    }

    private static function css(): string
    {
        return '.cc-google-rating{box-sizing:border-box;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;width:100%;max-width:100%;border:1px solid #e5e7eb;border-radius:8px;background:#fff;padding:18px 20px;color:#111827}.cc-google-rating *{box-sizing:border-box}.cc-google-rating__main{display:flex;align-items:center;gap:10px;flex-wrap:wrap;min-width:0}.cc-google-rating__source{font-weight:800}.cc-google-rating__stars{color:#f59e0b;letter-spacing:1px;white-space:nowrap}.cc-google-rating__score{font-size:20px;white-space:nowrap}.cc-google-rating__count{color:#4b5563;white-space:nowrap}.cc-google-rating__actions{display:flex;gap:10px;flex-wrap:wrap}.cc-google-rating__actions a{display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:8px 13px;border-radius:6px;text-decoration:none;font-weight:800;border:1px solid #d1d5db;color:#111827;background:#fff;text-align:center}.cc-google-rating__actions a:last-child{background:#111827;color:#fff;border-color:#111827}.cc-google-rating__meta{width:100%;display:flex;align-items:center;gap:12px;flex-wrap:wrap;font-size:12px;color:#6b7280}.cc-google-rating__meta img{display:block;width:auto;max-width:120px;height:18px}.cc-google-rating--compact{display:inline-flex;padding:10px 12px}.cc-google-rating--compact .cc-google-rating__meta{display:none}@media(max-width:720px){.cc-google-rating{display:block;padding:16px;margin-left:0;margin-right:0}.cc-google-rating__main{display:grid;grid-template-columns:1fr;gap:6px;align-items:start}.cc-google-rating__source,.cc-google-rating__stars,.cc-google-rating__score,.cc-google-rating__count{display:block}.cc-google-rating__score{font-size:19px}.cc-google-rating__actions{display:grid;grid-template-columns:1fr;gap:8px;width:100%;margin-top:14px}.cc-google-rating__actions a{width:100%;min-height:44px;padding:10px 12px}.cc-google-rating__meta{margin-top:12px}.cc-google-rating__meta img{height:14px;max-width:120px}}';
    }

    private static function attribution_image_url(): string
    {
        return add_query_arg('cc_google_rating_attribution', '1', home_url('/'));
    }

    public static function serve_attribution_image(): void
    {
        if (!isset($_GET['cc_google_rating_attribution'])) {
            return;
        }

        $image = get_transient(self::ATTRIBUTION_TRANSIENT_KEY);
        if (!self::is_valid_png_image($image)) {
            delete_transient(self::ATTRIBUTION_TRANSIENT_KEY);

            if (get_transient(self::ATTRIBUTION_LOCK_TRANSIENT_KEY) !== false) {
                self::send_no_content_response();
            }

            set_transient(self::ATTRIBUTION_LOCK_TRANSIENT_KEY, '1', 30);
            $response = wp_remote_get('https://maps.gstatic.com/mapfiles/api-3/images/powered-by-google-on-white3.png', [
                'timeout' => 8,
                'redirection' => 2,
            ]);

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $fetched_image = wp_remote_retrieve_body($response);
                if (self::is_valid_png_image($fetched_image)) {
                    $image = $fetched_image;
                    set_transient(self::ATTRIBUTION_TRANSIENT_KEY, $image, WEEK_IN_SECONDS);
                }
            }
            delete_transient(self::ATTRIBUTION_LOCK_TRANSIENT_KEY);
        }

        if (!self::is_valid_png_image($image)) {
            delete_transient(self::ATTRIBUTION_TRANSIENT_KEY);
            self::send_no_content_response();
        }

        header('Content-Type: image/png');
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . strlen($image));
        header('Cache-Control: public, max-age=604800');
        echo $image;
        exit;
    }

    private static function is_valid_png_image($image): bool
    {
        if (!is_string($image)) {
            return false;
        }

        $length = strlen($image);
        if ($length === 0 || $length > self::ATTRIBUTION_MAX_BYTES) {
            return false;
        }

        return strncmp($image, "\x89PNG\r\n\x1a\n", 8) === 0;
    }

    private static function send_no_content_response(): void
    {
        status_header(204);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        exit;
    }
}

CC_Google_Rating_Badge::boot();