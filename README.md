# WooCommerce Memcached Full Page Cache

[![License: GPL v3](./assets/badge-gplv3.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![WooCommerce v3.5+](./assets/badge-wc.svg)](https://wordpress.org)
[![WordPress v4.9+](./assets/badge-wp4.svg)](https://wordpress.org/news/category/releases/)
[![WordPress v5.x+](./assets/badge-wp5.svg)](https://wordpress.org)
[![PHP v7.x](./assets/badge-php7.svg)](https://php.net)
[![PHP Memcached](./assets/badge-memcached.svg)](https://www.php.net/manual/de/book.memcached.php)

WooCommerce full page cache plugin using Memcached.

__CREDITS:__ This plugin is the spiritual successor of [WP-FFPC](https://github.com/petermolnar/wp-ffpc) 
by [Peter Molnar](https://github.com/petermolnar).


## Table of contents

- [Copyright](#copyright)
- [License](#license)
- [Requirements](#requirements)
- [Installation](#installation)
- [Settings](#settings)
- [Customization](#customization)
  - [Constants](#constants)
  - [Globals](#globals)
  - [Filter Hooks](#filter-hooks)
  - [Action Hooks](#action-hooks)
- [Drop-In 'advanced-cache.php'](#example-default-advanced-cachephp)


## Copyright

```
WooCommerce Memcached Full Page Cache - FPC specialized for WooCommerece via PHP-Memcached.
Copyright (C)  2019 Achim Galeski ( achim@invinciblebrands.com )

Based on: WP-FFPC - A fast, memory based full page cache plugin supporting APC or memcached.
Copyright (C)  2010-2017 Peter Molnar ( hello@petermolnar.eu )

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
```

## License

GPL v3 - Please view [LICENSE](LICENSE.txt) document.

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)

## Requirements

__Requried:__
- A server running Memcached-Server.
- `libmemcached` on the Server that runs WordPress/WooCommerce.
- `php-memcached` on the Server that runs WordPress/WooCommerce.
- `PHP 7.x` any version 7 of PHP will do. I have not tested 5.6 yet.

__Optional:__
- `SASL` For authentication [SASL](https://github.com/memcached/memcached/wiki/SASLHowto) must be enabled.

## Installation

1. Upload contents of `woocommerce-memcached-full-page-cache.zip` (_OR clone this repository_) to the 
`/wp-content/plugins/` directory of your wordpress installation.
2. Enable WordPress caching via adding `define('WP_CACHE', true);` in the file `wp-config.php`
3. Activate the plugin in WordPress
4. Check the settings in `WooCommerce` => `Full Page Cache` menu in your Wordpress admin backend.
5. __(!) Save the settings (!)__ to generate `/wp-content/advanced-cache.php` and activate caching.

As a side note: [How to install Memcached](https://www.techrepublic.com/article/how-to-install-and-enable-memcached-on-ubuntu-and-centos/)

## Settings
__Settings link:__ yourdomain.xyz/wp-admin/admin.php?page=wc-mfpc-settings

OR

__WP Admin__ => __WooCommerce__ => Full Page Cache

Defaults:
```php
[
  'hosts'                   => '127.0.0.1:11211',
  'memcached_binary'        => 'yes',
  'authpass'                => '',
  'authuser'                => '',
  'expire'                  => '86400',
  'browsercache'            => '14400',
  'prefix_meta'             => 'meta-',
  'prefix_data'             => 'data-',
  'charset'                 => 'utf-8',
  'cache_loggedin'          => 'yes',
  'nocache_cookies'         => '',
  'nocache_woocommerce_url' => '^/DYNAMIC/|^/DYNAMIC/|^/DYNAMIC/|^/wc\\-api|^/\\?wc\\-api=',
  'nocache_url'             => '^/wc\\-|^/wp\\-|addons|removed|gdsr|wp_rg|wp_session​|wc_session​',
  'response_header'         => 'yes',
  'comments_invalidate'     => 'yes',
  'pingback_header'         => '0',
]
```

__hosts__ 

_Memcached server list Ip:Port,Ip2:Port2,Ip3:Port_

__memcached_binary__

_Enables binary connection mode (faster). However, in some cases this is not possible to use. Unchecked the plugin will
fall back to a ASCII mode (slower)._

__authpass__

_Stores the password for authentication with all hosts in the list. (!) SASL must be enabled._

__authuser__

_Stores the user for authentication with all hosts in the list. (!) SASL must be enabled._

__expire__

_The expire time of the cached entries in Memcached._

__browsercache__

_Used to determine modified since and when the Browser-cache of a given page should expire._

__prefix_meta__

_The prefix for meta-data keys in memcached. (!) Must be different than the data prefix or entries will be overwritten._

__prefix_data__

_The prefix for page content keys in memcached. (!) Must be different than the meta prefix or entries will be overwritten._

__charset__

_Used to set the charset header when a page is loaded from cache._

__cache_loggedin__

_Enable to load cached pages even for logged in customers/users. (!) Does NOT use cache for Admins._

__nocache_cookies__

_The place to define custom cookies which should prevent caching for users with the cookies provided here._

__nocache_woocommerce_url__

_Contains the regex to exclude WooCommerce API & default dynamic pages like checkout and cart. (!) Generated on save._

__nocache_url__

_Enter your own regex to exclude matches from caching._

__response_header__

_Enable to add the X-Cache response header if cache is loaded._

__comments_invalidate__

_Enable to trigger cache clearing on comment actions._

__pingback_header__

_Enable to preserve the PingBack header for cached pages._

## Customization

You can use hooks to customize the behaviour of this plugin.

### Constants:
- `WC_MFPC_PLUGIN_DIR`
- `WC_MFPC_PLUGIN_URL`
- `WC_MFPC_PLUGIN_FILE`

### Globals:
- `$wc_mfpc_config_array`

  _Config array loaded via advanced-cache.php_
  
- `$wc_mfpc_memcached`

  _Instance of [Memcached::class](Memcached.php) initiated in [wc-mfpc-advanced-cache.php](wc-mfpc-advanced-cache.php)_
  
- `$wcMfpcConfig`

  _Instance of [WcMfpcConfig::class](WcMfpcConfig.php) initiated in 
  [woocommerce-memcached-full-page-cache.php](woocommerce-memcached-full-page-cache.php)_

### Filter Hooks:

[wc-mfpc-advanced-cache.php](wc-mfpc-advanced-cache.php) :
- [wc_mfpc_custom_skip_load_from_cache](#hook-custom-skiploadfromcache)
- [wc_mfpc_custom_skip_caching](#hook-custom-skipcaching)
- [wc_mfpc_custom_cache_content](#hook-custom-cachecontent)
- [wc_mfpc_custom_cache_meta](#hook-custom-cachemeta)
        
[Memcached::class](src/Memcached.php) :
- wc_mfpc_custom_build_url
- wc_mfpc_custom_build_key
- wc_mfpc_custom_build_keys
- [wc_mfpc_custom_expire](#hook-custom-expire)
        
[Admin::class](src/Admin.php) :
- wc_mfpc_custom_advanced_cache_config

### Action Hooks:

[AdminView::class](src/AdminView.php) :
- wc_mfpc_settings_form_top
- wc_mfpc_settings_form_bottom
- wc_mfpc_settings_form_memcached_connection
- wc_mfpc_settings_form_cache
- wc_mfpc_settings_form_exception
- wc_mfpc_settings_form_debug


#### Hook: Custom SkipLoadFromCache

* `wc_mfpc_custom_skip_load_from_cache`

This hook gives you control if a given uri should be processed by the advanced-cache or rather not.

Example #1:
```php
/**
 * Function to customize whether a page is processed by wp-ffpc at all.
 *
 * @param bool   $skip    Default: false - return TRUE for skipping
 * @param array  $config  Array with config from advanced-cache.php
 * @param string $uri     Requested URI string
 *
 * @return bool $skip
 */
function cust_wc_mfpc_set_skip_load_from_cache($skip = false, $config = [], $uri = '')
{
    /*
     * If you do somehting like this, don't forget to add this cookie also to your 
     * cache exclude list in nginx.
     */
    if (! empty($_COOKIE[ 'SUPER_SPECIAL_COOKIE' ]) {

        $skip = true;

    }
    
    return $skip
}
add_filter('wc_mfpc_custom_skip_load_from_cache', 'cust_wc_mfpc_set_skip_load_from_cache')
```
Example #2:
 ```php
if (! empty($_COOKIE[ 'SUPER_SPECIAL_COOKIE' ]) {
    
    add_filter('wc_mfpc_custom_skip_load_from_cache', '__return_true');

}
 ```

---

#### Hook: Custom SkipCaching

* `wc_mfpc_custom_skip_caching`

This hook gives you control if a given uri should be processed and stored in cache or rather not.
The content of the page is already known at this point and can be analysed for consideration.

Example #1:
```php
/**
 * Function to custom skip storing data in cache.
 *
 * @param bool $skip       Set TRUE to skip caching.
 * @param string $content  The page content.
 *
 * @return bool $skip
 */
function cust_wc_mfpc_set_skip_caching($skip = false, $content = '')
{
    if (! empty(stripos($content, '<div class="skip-me-from-cache">'))) {

        $skip = true;

    }

    return $skip;
}
add_filter('wc_mfpc_custom_skip_caching', 'cust_wc_mfpc_set_skip_caching')
```
Example #2:
```php
/*
 * Somewhere in your plugin / theme when rendering a special, individual & dynamic page
 * which should never be cached.
 */
add_filter('wc_mfpc_custom_skip_caching', '__return_true');
```

---

#### Hook: Custom CacheContent

* `wc_mfpc_custom_cache_content`

This hook lets you customize the raw content of a page, before it gets stored in cache.

It can be found here: [wc-mfpc-advanced-cache.php](wc-mfpc-advanced-cache.php)

Example:
```php
/**
 * Function to customize the html content of any page.
 *
 * @param string $cacheContent  The content to be stored in cache.
 *
 * @return string $cacheContent
 */
function cust_wc_mfpc_set_cache_content($cacheContent = '')
{    
    /*
     * Add generation date info, but only to HTML.
     */
    if (stripos($cacheContent, '</body>')) {

        $insertion    = "\n<!-- WC-MFPC cache created date: " . date('c') . " -->\n";
        $index        = stripos($buffer, '</body>');
        $cacheContent = substr_replace($buffer, $insertion, $index, 0);
    }
    
    return $cacheContent;
}
add_filter('wc_mfpc_custom_cache_content', 'cust_wc_mfpc_set_cache_content');
```

---

#### Hook: Custom CacheMeta

* `wc_mfpc_custom_cache_meta`

This hook lets you customize the cache meta data of a page, before it gets stored in cache.

It can be found here: [wc-mfpc-advanced-cache.php](wc-mfpc-advanced-cache.php)

Example:
```php
/**
 * Function to customize the cached meta data array of any page.
 *
 * @param array $cacheMeta  The meta data array to be stored in cache.
 *
 * @return array $cacheMeta
 */
function cust_wc_mfpc_set_cache_meta($cacheMeta = '')
{
    /*
     * Example:
     * Change browsercache expire time for certain page types before storing meta data.
     */
    if (is_home() || is_feed) {

        $cacheMeta[ 'expire' ] = time() + 1800;

    } elseif (is_archive()) {

        $cacheMeta[ 'expire' ] = time() + 3600;

    } elseif (is_product()) {

        $cacheMeta[ 'expire' ] = time() + 7200;

    }
    
    return $cacheMeta;
}
add_filter('wc_mfpc_custom_cache_meta', 'cust_wc_mfpc_set_cache_meta');
```

---

#### Hook: Custom Expire

* `wc_mfpc_custom_expire`

This hook lets you customize the Memcached cache expiration time before setting the entry.

Example:
```php
/**
 * Function to customize cache expriration time in seconds.
 *
 * @param int $expire  Lifetime of the cache entry in seconds.
 *
 * @return int
 */
function cust_wc_mfpc_set_expire($expire = 0)
{
    if ($this->config[ 'expire_home' ] !== '' && (is_home() || is_feed())) {
    
        $expire = (int) $this->config[ 'expire_home' ];
    
    } elseif (
              $this->config[ 'expire_taxonomy' ] !== '' 
              && (is_tax() || is_category() || is_tag() || is_archive())
    ) {
    
        $expire = (int) $this->config[ 'expire_taxonomy' ];
        
    }
    
    return $expire;
}
add_filter('wc_mfpc_custom_expire', 'cust_wc_mfpc_set_expire');
```

---

#### Hook:

* ` `

...

Example:
```php

```

## Example: default advanced-cache.php

```php
<?php
/*
Plugin Name: WooCommerce Memcached Full Page Cache (Drop-In: advanced-cache.php)
Plugin URI: https://github.com/agaleski/woocommerce-memcached-full-page-cache/
Description: WooCommerce full page cache plugin based on Memcached.
Version: 0.1
Author: Achim Galeski <achim@invinciblebrands.com>
License: GPLv3
*/

global $wc_mfpc_config_array;

$wc_mfpc_config_array = array (
  'naturalmojo.de.local' => 
  array (
    'hosts' => '127.0.0.1:11211',
    'memcached_binary' => 'yes',
    'authpass' => '',
    'authuser' => '',
    'expire' => '86400',
    'browsercache' => '14400',
    'prefix_meta' => 'meta-',
    'prefix_data' => 'data-',
    'charset' => 'utf-8',
    'cache_loggedin' => 'yes',
    'nocache_cookies' => '',
    'nocache_woocommerce_url' => '^/kasse/|^/mein\\-konto/|^/warenkorb/|^/wc\\-api|^/\\?wc\\-api=',
    'nocache_url' => '^/wc\\-|^/wp\\-|addons|removed|gdsr|wp_rg|wp_session​|wc_session​',
    'response_header' => 'yes',
    'comments_invalidate' => 'yes',
    'pingback_header' => '0',
  ),
);

include_once ('/var/www/html/wp-content/plugins/woocommerce-memcached-full-page-cache/wc-mfpc-advanced-cache.php');
```

---

[TOP](#woocommerce-memcached-full-page-cache)
