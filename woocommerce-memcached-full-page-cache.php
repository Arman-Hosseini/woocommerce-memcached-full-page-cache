<?php
/*
Plugin Name: WooCommerce Memcached Full Page Cache
Plugin URI: https://github.com/agaleski/woocommerce-memcached-full-page-cache
Description: WooCommerce full page cache plugin based on Memcached.
Version: 0.1
Author: Achim Galeski <achim@invinciblebrands.com>
Author URI: https://achim-galeski.de/
License: GPLv3
Text Domain: wc-mfpc
Domain Path: /languages/
*/

/*
    WooCommerce Memcached Full Page Cache - FPC the WooCommerece way via PHP-Memcached.
    Copyright (C)  2019 Achim Galeski ( achim@invinciblebrands.com )

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 3, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA02110-1301USA
*/

if (! defined('ABSPATH')) { exit; }

include_once 'vendor/autoload.php';

use InvincibleBrands\WcMfpc\Config;
use InvincibleBrands\WcMfpc\Data;
use InvincibleBrands\WcMfpc\WcMfpc;
use InvincibleBrands\WcMfpc\Admin;
use InvincibleBrands\WcMfpc\AdminView;

/**
 * @var Config $wcMfpcConfig
 */
$wcMfpcConfig = null;

add_action('init',           'wc_mfpc_init_plugin');
add_action('admin_init',     'wc_mfpc_admin_init_plugin');
add_action('admin_bar_init', 'wc_mfpc_admin_bar_init');

/**
 * Initializes the plugin.
 *
 * @return void
 */
function wc_mfpc_init_plugin()
{
    error_log('init_plugin');

    global $wcMfpcConfig;

    $wcMfpcConfig = new Config();
    $wcMfpcConfig->load();

    define('WC_MFPC_PLUGIN_DIR',  __DIR__ . '/');
    define('WC_MFPC_PLUGIN_URL',  plugin_dir_url(__FILE__));
    define('WC_MFPC_PLUGIN_FILE', basename(__FILE__) . '/' . Data::pluginConstant . '.php');


    register_activation_hook(WC_MFPC_PLUGIN_FILE, [ WcMfpc::class, 'pluginActivate' ]);
    register_deactivation_hook(WC_MFPC_PLUGIN_FILE, [ WcMfpc::class, 'pluginDeactivate' ]);

    /*
     * if WP_CACHE is not set or false - abort here and safe your time.
     */
    if (! defined('WP_CACHE') || empty(WP_CACHE)) {

        return;
    }

    /*
     * comments invalidation hooks
     */
    if (! empty($wcMfpcConfig->isCommentsInvalidate())) {

        add_action('comment_post', [ WcMfpc::class, 'clearPostCache' ], 0);
        add_action('edit_comment', [ WcMfpc::class, 'clearPostCache' ], 0);
        add_action('trashed_comment', [ WcMfpc::class, 'clearPostCache' ], 0);
        add_action('pingback_post', [ WcMfpc::class, 'clearPostCache' ], 0);
        add_action('trackback_post', [ WcMfpc::class, 'clearPostCache' ], 0);
        add_action('wp_insert_comment', [ WcMfpc::class, 'clearPostCache' ], 0);

    }

    /*
     * invalidation on some other occasions as well
     */
    add_action('switch_theme', [ WcMfpc::class, 'clearPostCache' ], 0);
    add_action('deleted_post', [ WcMfpc::class, 'clearPostCache' ], 0);

    /*
     * add filter for catching canonical redirects
     */
    add_filter('redirect_canonical', 'wc_mfpc_redirect_callback', 10, 2);
}

/**
 * Initializes the plugins admin.
 *
 * @return void
 */
function wc_mfpc_admin_init_plugin()
{
    error_log('admin_init_plugin');

    add_filter("network_admin_plugin_action_links_" . WC_MFPC_PLUGIN_FILE, [ Admin::class, 'addSettingsLink' ]);
    add_filter("plugin_action_links_" . WC_MFPC_PLUGIN_FILE, [ Admin::class, 'addSettingsLink' ]);
    add_action('admin_post_' . Data::buttonSave, [ Admin::class, 'processSave' ]);
    add_action('admin_post_' . Data::buttonFlush, [ Admin::class, 'processFlush' ]);
    add_action('admin_post_' . Data::buttonReset, [ Admin::class, 'processReset' ]);
    add_action('admin_enqueue_scripts', [ Admin::class, 'enqueAdminCss' ]);

    /*
     * WooCommerce settings tab
     */
    add_filter('woocommerce_settings_tabs_array', [ Admin::class, 'addWooCommerceSettingsTab' ], 50);
    add_action('woocommerce_settings_tabs_full_page_cache', [ AdminView::class, 'render' ]);

    /*
     * In case of major issues => abort here and set no more action hooks.
     */
    if (! Admin::validateEnvironment()) {

        return;
    }

    /*
     * Add hooks necessary for the "Cache control" box.
     */
    add_action('add_meta_boxes', [ Admin::class, 'addCacheControlMetaBox' ], 2);
    add_action('product_cat_edit_form_fields', [ Admin::class, 'showCategoryBox' ]);
    add_action('wp_ajax_' . Data::cacheControlClearAction, [ Admin::class, 'processCacheControlAjax' ]);
    add_action('wp_ajax_' . Data::cacheControlRefreshAction, [ Admin::class, 'processCacheControlAjax' ]);

    /*
     * Add hooks necessary for Bulk deletion of cache entries.
     */
    add_filter('bulk_actions-edit-product', [ Admin::class, 'addBulkAction' ]);
    add_filter('bulk_actions-edit-post', [ Admin::class, 'addBulkAction' ]);
    add_filter('bulk_actions-edit-page', [ Admin::class, 'addBulkAction' ]);
    add_filter('bulk_actions-edit-product_cat', [ Admin::class, 'addBulkAction' ]);
    add_filter('handle_bulk_actions-edit-product', [ Admin::class, 'handleBulkAction' ], 10, 3);
    add_filter('handle_bulk_actions-edit-post', [ Admin::class, 'handleBulkAction' ], 10, 3);
    add_filter('handle_bulk_actions-edit-page', [ Admin::class, 'handleBulkAction' ], 10, 3);
    add_filter('handle_bulk_actions-edit-product_cat', [ Admin::class, 'handleBulkAction' ], 10, 3);
}

/**
 * Sets the no-cache cookie to avoid caching of views including the admin bar.
 *
 * @return void
 */
function wc_mfpc_admin_bar_init()
{
    error_log('admin_bar_init');

    if (empty($_COOKIE[ 'wc-mfpc-nocache' ])) {

        setcookie('wc-mfpc-nocache', 1, time() + 604800);

    }
}