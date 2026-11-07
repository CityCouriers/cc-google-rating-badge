<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('cc_google_rating_badge_options');
delete_option('cc_google_rating_badge_last_good');
delete_transient('cc_google_rating_badge_data');
delete_transient('cc_google_rating_badge_attribution_image');
delete_transient('cc_google_rating_badge_attribution_image_lock');