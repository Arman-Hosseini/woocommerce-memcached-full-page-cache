<?php

namespace InvincibleBrands\WcMfpc;


if (! defined('ABSPATH')) { exit; }

/**
 * Class Admin
 *
 * @package InvincibleBrands\WcMfpc\Admin
 */
class Admin
{

    /**
     * @var bool
     */
    private $scheduled = false;

    /**
     * @var array
     */
    private $global_config = [];

    /**
     * @var int
     */
    private $status = 0;

    /**
     * @var bool
     */
    private $global_saved = false;

    /**
     * @var array
     */
    private $select_invalidation_method = [];

    /**
     * @var array
     */
    private $select_schedules = [];

    /**
     * @var array
     */
    private $valid_cache_type = [];

    /**
     * @var array
     */
    private $list_uri_vars = [];

    /**
     * @var array
     */
    private $errors = [];

    /**
     * Initializes the Hooks necessary for the admin settings pages.
     *
     * @return void
     */
    public function setHooks()
    {
        global $wcMfpcData;

        /*
         * register settings pages & register admin init, catches $_POST and adds submenu to admin menu
         */
        if ($wcMfpcData->network) {

            add_filter("network_admin_plugin_action_links_" . $wcMfpcData->plugin_file, [ &$this, 'plugin_settings_link' ]);
            add_action('network_admin_menu', [ &$this, 'plugin_admin_init' ]);

        } else {

            add_filter("plugin_action_links_" . $wcMfpcData->plugin_file, [ &$this, 'plugin_settings_link' ]);
            add_action('admin_menu', [ &$this, 'plugin_admin_init' ]);

        }

        add_action('admin_enqueue_scripts', [ &$this, 'enqueue_admin_css_js' ]);
    }

    /**
     * @return void
     */
    public function enqueue_admin_css_js()
    {
        global $wcMfpcData;

        wp_enqueue_script("jquery-ui-tabs");
        wp_enqueue_script("jquery-ui-slider");

        wp_register_style(Data::admin_css_handle, $wcMfpcData->admin_css_url, [ 'dashicons' ], false, 'all');
        wp_enqueue_style(Data::admin_css_handle);
    }

    /**
     * init hook function runs before admin panel hook, themeing and options read
     */
    public function plugin_pre_init()
    {
        global $wcMfpcData;

        if (! isset($_SERVER[ 'HTTP_HOST' ])) {

            $_SERVER[ 'HTTP_HOST' ] = '127.0.0.1';

        }

        /* set global config key; here, because it's needed for migration */
        if ($wcMfpcData->network) {

            $wcMfpcData->global_config_key = 'network';

        } else {

            $sitedomain = parse_url(get_option('siteurl'), PHP_URL_HOST);

            if ($_SERVER[ 'HTTP_HOST' ] != $sitedomain) {

                $this->errors[ 'domain_mismatch' ] = sprintf(__("Domain mismatch: the site domain configuration (%s) does not match the HTTP_HOST (%s) variable in PHP. Please fix the incorrect one, otherwise the plugin may not work as expected.", 'wc-mfpc'), $sitedomain, $_SERVER[ 'HTTP_HOST' ]);

            }

            $wcMfpcData->global_config_key = $_SERVER[ 'HTTP_HOST' ];

        }

        /* invalidation method possible values array */
        $this->select_invalidation_method = [
            0 => __('flush cache', 'wc-mfpc'),
            1 => __('only modified post', 'wc-mfpc'),
            2 => __('modified post and all related taxonomies', 'wc-mfpc'),
        ];

        /* map of possible key masks */
        $this->list_uri_vars = [
            '$scheme'           => __('The HTTP scheme (i.e. http, https).', 'wc-mfpc'),
            '$host'             => __('Host in the header of request or name of the server processing the request if the Host header is not available.', 'wc-mfpc'),
            '$request_uri'      => __('The *original* request URI as received from the client including the args', 'wc-mfpc'),
            '$remote_user'      => __('Name of user, authenticated by the Auth Basic Module', 'wc-mfpc'),
            '$cookie_PHPSESSID' => __('PHP Session Cookie ID, if set ( empty if not )', 'wc-mfpc'),
            '$accept_lang'      => __('First HTTP Accept Lang set in the HTTP request', 'wc-mfpc'),
        ];

        /* get current wp_cron schedules */
        $wp_schedules = wp_get_schedules();
        /* add 'null' to switch off timed precache */
        $schedules[ 'null' ] = __('do not use timed precache');

        foreach ($wp_schedules as $interval => $details) {

            $schedules[ $interval ] = $details[ 'display' ];

        }

        $this->select_schedules = $schedules;
    }

    /**
     * admin init called by WordPress add_action, needs to be public
     */
    public function plugin_admin_init()
    {
        global $wcMfpcData;

        /* save parameter updates, if there are any */
        if (isset($_POST[ Data::button_save ]) && check_admin_referer('wc-mfpc')) {

            $this->plugin_options_save();
            $this->status = 1;
            header("Location: " . $wcMfpcData->settings_link . $wcMfpcData->slug_save);

        }

        /* delete parameters if requested */
        if (isset($_POST[ Data::button_delete ]) && check_admin_referer('wc-mfpc')) {

            self::plugin_options_delete();
            $this->status = 2;
            header("Location: " . $wcMfpcData->settings_link . $wcMfpcData->slug_delete);

        }

        /* load additional moves */
        $this->plugin_extend_admin_init();

        /* add submenu to settings pages */
        add_submenu_page(
            $wcMfpcData->settings_slug,
            Data::plugin_name . ' options',
            Data::plugin_name,
            Data::capability,
            Data::plugin_settings_page,
            [ &$this, 'plugin_admin_panel' ]
        );


        /* link on to settings for plugins page */
        $settings_link = ' &raquo; <a href="' . $wcMfpcData->settings_link . '">' . __('WC-MFPC Settings', 'wc-mfpc') . '</a>';

        /* look for WP_CACHE */
        if (! WP_CACHE) {

            $this->errors[ 'no_wp_cache' ] = __("WP_CACHE is disabled. Without that, cache plugins, like this, will not work. Please add `define ( 'WP_CACHE', true );` to the beginning of wp-config.php.", 'wc-mfpc');

        }

        /* look for global settings array */
        if (! $this->global_saved) {

            $this->errors[ 'no_global_saved' ] = sprintf(__('This site was reached as %s ( according to PHP HTTP_HOST ) and there are no settings present for this domain in the WC-MFPC configuration yet. Please save the %s for the domain or fix the webserver configuration!', 'wc-mfpc'),
                $_SERVER[ 'HTTP_HOST' ], $settings_link
            );

        }

        /* look for writable acache file */
        if (file_exists($wcMfpcData->acache) && ! is_writable($wcMfpcData->acache)) {

            $this->errors[ 'no_acache_write' ] = sprintf(__('Advanced cache file (%s) is not writeable!<br />Please change the permissions on the file.', 'wc-mfpc'), $wcMfpcData->acache);

        }

        /* look for acache file */
        if (! file_exists($wcMfpcData->acache)) {

            $this->errors[ 'no_acache_saved' ] = sprintf(__('Advanced cache file is yet to be generated, please save %s', 'wc-mfpc'), $settings_link);

        }

        if (class_exists('Memcached') ? true : false) {

            $this->errors[ 'no_backend' ] = 'Memcached activated but no PHP %s extension was found.<br />Please activate the module!';

        }

        if ($this->errors && php_sapi_name() != "cli") {

            foreach ($this->errors as $e => $msg) {

                self::alert($msg, LOG_WARNING, $wcMfpcData->network);

            }

        }
    }

    /**
     * extending admin init
     */
    public function plugin_extend_admin_init()
    {
        global $wcMfpc, $wcMfpcData;

        /* save parameter updates, if there are any */
        if (isset($_POST[ Data::button_flush ]) && check_admin_referer('wc-mfpc')) {

            /* remove precache log entry */
            self::_delete_option(Data::precache_log);
            /* remove precache timestamp entry */
            self::_delete_option($wcMfpcData->precache_timestamp);


            /* remove precache logfile */
            if (@file_exists($wcMfpcData->precache_logfile)) {

                unlink($wcMfpcData->precache_logfile);

            }

            /* remove precache PHP worker */
            if (@file_exists($wcMfpcData->precache_phpfile)) {

                unlink($wcMfpcData->precache_phpfile);

            }


            /* flush backend */
            $wcMfpc->backend->clear(false, true);
            $this->status = 3;
            header("Location: " . $wcMfpcData->settings_link . $wcMfpcData->slug_flush);

        }

        /* save parameter updates, if there are any */
        if (isset($_POST[ Data::button_precache ]) && check_admin_referer('wc-mfpc')) {

            /* is no shell function is possible, fail */
            if ($wcMfpcData->shell_function === false) {

                $this->status = 5;
                header("Location: " . $wcMfpcData->settings_link . $wcMfpcData->slug_precache_disabled);

            } else {

                #$this->precache_message = $wcMfpc->precache_coldrun(); // ToDo: check this - method returns void!
                $wcMfpc->precache_coldrun();
                $this->status           = 4;
                header("Location: " . $wcMfpcData->settings_link . $wcMfpcData->slug_precache);

            }

        }
    }

    /**
     * deletes saved options from database
     */
    public static function plugin_options_delete()
    {
        global $wcMfpcData;

        self::_delete_option(Data::plugin_constant, $wcMfpcData->network);
        /* additional moves */
        self::plugin_extend_options_delete();
    }

    /**
     * clear option; will handle network wide or standalone site options
     *
     * @param      $optionID
     * @param bool $network
     */
    public static function _delete_option($optionID, $network = false)
    {
        if ($network) {
          
            delete_site_option($optionID);
            
        } else {
          
            delete_option($optionID);
            
        }
    }

    /**
     * options delete hook; needs to be implemented
     */
    public static function plugin_extend_options_delete()
    {
        delete_site_option(Data::global_option);
    }

    /**
     * used on update and to save current options to database
     *
     * @param boolean $activating [optional] true on activation hook
     */
    protected function plugin_options_save($activating = false)
    {
        global $wcMfpcData, $wcMfpcConfig;

        /* only try to update defaults if it's not activation hook, $_POST is not empty and the post is ours */
        if (! $activating && ! empty ($_POST) && isset($_POST[ Data::button_save ])) {

            /* we'll only update those that exist in the defaults array */
            $options = $wcMfpcData->defaults;

            foreach ($options as $key => $default) {

                /* $_POST element is available */
                if (! empty($_POST[ $key ])) {

                    $update = $_POST[ $key ];

                    /* get rid of slashes in strings, just in case */
                    if (is_string($update)) {

                        $update = stripslashes($update);

                    }

                    $options[ $key ] = $update;

                /*
                 * empty $_POST element: when HTML form posted, empty checkboxes a 0 input
                 * values will not be part of the $_POST array, thus we need to check
                 * if this is the situation by checking the types of the elements,
                 * since a missing value means update from an integer to 0
                 */
                } elseif (empty($_POST[ $key ]) && (is_bool($default) || is_int($default))) {

                    $options[ $key ] = 0;

                } elseif (empty($_POST[ $key ]) && is_array($default)) {

                    $options[ $key ] = [];

                }

            }

            /* update the options entity */
            $wcMfpcConfig->setConfig($options);

        }

        /* call hook function for additional moves before saving the values */
        $this->plugin_extend_options_save($activating);
        /* save options to database */
        self::_update_option(Data::plugin_constant, $wcMfpcConfig->getConfig(), $wcMfpcData->network);
    }

    /**
     * extending options_save
     *
     * @param $activating
     */
    public function plugin_extend_options_save($activating)
    {
        global $wcMfpcData;

        /* schedule cron if posted */
        $schedule = wp_get_schedule($wcMfpcData->precache_id);

        global $wcMfpcConfig;

        if ($wcMfpcConfig->getPrecacheSchedule() != 'null') {

            /* clear all other schedules before adding a new in order to replace */
            wp_clear_scheduled_hook($wcMfpcData->precache_id);
            $this->scheduled = wp_schedule_event(time(), $wcMfpcConfig->getPrecacheSchedule(), $wcMfpcData->precache_id);

        } elseif (! empty($wcMfpcConfig->getPrecacheSchedule()) && ! empty($schedule)) {

            wp_clear_scheduled_hook($wcMfpcData->precache_id);

        }

        /* flush the cache when new options are saved, not needed on activation */
        if (! $activating) {

            global $wcMfpc;

            $wcMfpc->backend->clear(null, true);

        }

        /* create the to-be-included configuration for advanced-cache.php */
        $this->update_global_config();

        /* create advanced cache file, needed only once or on activation, because there could be lefover advanced-cache.php from different plugins */
        if (! $activating) {

            $this->deploy_advanced_cache();

        }
    }

    /**
     * option update; will handle network wide or standalone site options
     *
     * @param      $optionID
     * @param      $data
     * @param bool $network
     */
    public static function _update_option($optionID, $data, $network = false)
    {
        if ($network) {

            update_site_option($optionID, $data);

        } else {

            update_option($optionID, $data);

        }
    }

    /**
     * reads options stored in database and reads merges them with default values
     */
    public function plugin_options_read()
    {
        global $wcMfpcConfig, $wcMfpcData;

        $options = self::_get_option(Data::plugin_constant, $wcMfpcData->network);

        /* map missing values from default */
        foreach ($wcMfpcData->defaults as $key => $default) {

            if (! @array_key_exists($key, $options)) {

                $options[ $key ] = $default;

            }

        }

        /* removed unused keys, rare, but possible */
        foreach (@array_keys($options) as $key) {

            if (! @array_key_exists($key, $wcMfpcData->defaults)) {

                unset ($options[ $key ]);

            }

        }

        /* any additional read hook */
        $this->plugin_extend_options_read($options);
        $wcMfpcConfig->setConfig($options);
    }

    /**
     * read option; will handle network wide or standalone site options
     *
     * @param      $optionID
     * @param bool $network
     *
     * @return mixed
     */
    public static function _get_option($optionID, $network = false)
    {
        if ($network) {

            $options = get_site_option($optionID);

        } else {

            $options = get_option($optionID);

        }

        return $options;
    }

    /**
     * read hook; needs to be implemented
     *
     * @param $options
     */
    public function plugin_extend_options_read(&$options)
    {
        global $wcMfpcData;

        $this->global_config = get_site_option(Data::global_option);

        if (! empty ($this->global_config[ $wcMfpcData->global_config_key ])) {

            $this->global_saved = true;

        }

        $this->global_config[ $wcMfpcData->global_config_key ] = $options;
    }

    /**
     * function to update global configuration
     *
     * @param boolean $remove_site Bool to remove or add current config to global
     */
    public function update_global_config($remove_site = false)
    {
        global $wcMfpcConfig, $wcMfpcData;

        /* remove or add current config to global config */
        if ($remove_site) {

            unset ($this->global_config[ $wcMfpcData->global_config_key ]);

        } else {

            $this->global_config[ $wcMfpcData->global_config_key ] = $wcMfpcConfig->getConfig();

        }

        /* deploy advanced-cache.php */
        $this->deploy_advanced_cache();
        /* save options to database */
        update_site_option(Data::global_option, $this->global_config);
    }

    /**
     * advanced-cache.php creator function
     */
    private function deploy_advanced_cache()
    {
        global $wcMfpcData;

        if (! touch($wcMfpcData->acache)) {

            error_log('Generating advanced-cache.php failed: ' . $this->acache . ' is not writable');

            return false;
        }

        /* if no active site left no need for advanced cache :( */
        if (empty ($this->global_config)) {

            error_log('Generating advanced-cache.php failed: Global config is empty');

            return false;
        }

        /* add the required includes and generate the needed code */
        $string[] = "<?php";
        $string[] = $wcMfpcData->global_config_var . ' = ' . var_export($this->global_config, true) . ';';
        $string[] = "include_once ('" . $wcMfpcData->acache_worker . "');";

        /* write the file and start caching from this point */

        return file_put_contents($wcMfpcData->acache, join("\n", $string));
    }

    /**
     * @return mixed
     */
    private function plugin_admin_panel_get_tabs()
    {
        $default_tabs = [
            'type'       => __('Cache type', 'wc-mfpc'),
            'debug'      => __('Debug & in-depth', 'wc-mfpc'),
            'exceptions' => __('Cache exceptions', 'wc-mfpc'),
            'servers'    => __('Backend settings', 'wc-mfpc'),
            'precache'   => __('Precache & precache log', 'wc-mfpc'),
        ];

        return apply_filters('wc_mfpc_admin_panel_tabs', $default_tabs);
    }

    /**
     * display formatted alert message
     *
     * @param string $msg     Error message
     * @param int    $level   "level" of error
     *
     * @return bool
     */
    static public function alert($msg, $level = LOG_WARNING)
    {
        if (empty($msg)) {

            return false;
        }

        switch ($level) {

            case LOG_ERR:
            case LOG_WARNING:
                $css = "error";
                break;
            default:
                $css = "updated";
                break;

        }

        $r = '<div class="' . $css . '"><p>' . sprintf(__('%s', 'PluginUtils'), $msg) . '</p></div>';

        if (version_compare(phpversion(), '5.3.0', '>=')) {

            add_action('admin_notices', function () use ($r) {
                echo $r;
            }, 10);

        } else {

            global $tmp;

            $tmp = $r;
            $f   = create_function('', 'global $tmp; echo $tmp;');

            add_action('admin_notices', $f);

        }
    }

    /**
     * Select options field processor
     *
     * @param array $elements  Array to build <option> values of
     * @param mixed $current   The current active element
     * @param bool  $valid
     * @param bool  $print     Is true, the options will be printed, otherwise the string will be returned
     *
     * @return mixed $opt      Prints or returns the options string
     */
    protected function print_select_options($elements, $current, $valid = false, $print = true)
    {
        $opt = '';

        foreach ($elements as $value => $name) {

            $opt .= '<option value="' . $value . '" ';
            $opt .= selected($value, $current);

            // ugly tree level valid check to prevent array warning messages
            if (is_array($valid) && isset ($valid [ $value ]) && $valid [ $value ] == false) {

                $opt .= ' disabled="disabled"';

            }

            $opt .= '>';
            $opt .= $name;
            $opt .= "</option>\n";
        }

        if ($print) {

            echo $opt;

        } else {

            return $opt;
        }
    }

    /**
     * callback function to add settings link to plugins page
     *
     * @param array $links Current links to add ours to
     *
     * @return array
     */
    public function plugin_settings_link($links)
    {
        global $wcMfpcData;

        $settings_link = '<a href="' . $wcMfpcData->settings_link . '">' . __('Settings', 'wc-mfpc') . '</a>';
        array_unshift($links, $settings_link);

        return $links;
    }

    /**
     * admin panel, the admin page displayed for plugin settings
     */
    public function plugin_admin_panel()
    {
        /*
         * security, if somehow we're running without WordPress security functions
         */
        if (! function_exists('current_user_can') || ! current_user_can('manage_options')) {

            die();

        }

        global $wcMfpcConfig, $wcMfpcData;

        ?>

        <script>
          jQuery(document).ready(function ($) {
            jQuery("#<?php echo Data::plugin_constant ?>-settings").tabs();
          });
        </script>
        <div class="wrap">

            <?php $this->renderMessages(); ?>

            <h2><?php echo Data::plugin_name . ' settings'; ?></h2>

            <?php $this->renderActionButtons(); ?>

            <form autocomplete="off" method="post" action="#" id="<?php echo Data::plugin_constant ?>-settings" class="plugin-admin">
                <?php wp_nonce_field('wc-mfpc'); ?>
                <?php $switcher_tabs = $this->plugin_admin_panel_get_tabs(); ?>
                <ul class="tabs">
                    <?php foreach ($switcher_tabs AS $tab_section => $tab_label) { ?>

                        <li><a href="#<?php echo Data::plugin_constant . '-' . $tab_section ?>" class="wp-switch-editor"><?= $tab_label ?></a></li>

                    <?php } ?>
                </ul>

                <fieldset id="<?php echo Data::plugin_constant; ?>-type">
                    <legend><?php _e('Set cache type', 'wc-mfpc'); ?></legend>
                    <dl>
                        <dt>
                            <label for="expire"><?php _e('Expiration time for posts', 'wc-mfpc'); ?></label>
                        </dt>
                        <dd>
                            <input type="number" name="expire" id="expire" value="<?php echo $wcMfpcConfig->getExpire(); ?>"/>
                            <span class="description"><?php _e('Sets validity time of post entry in seconds, including custom post types and pages.', 'wc-mfpc'); ?></span>
                        </dd>

                        <dt>
                            <label for="browsercache"><?php _e('Browser cache expiration time of posts', 'wc-mfpc'); ?></label>
                        </dt>
                        <dd>
                            <input type="number" name="browsercache" id="browsercache" value="<?php echo $wcMfpcConfig->getBrowsercache(); ?>"/>
                            <span class="description"><?php _e('Sets validity time of posts/pages/singles for the browser cache.', 'wc-mfpc'); ?></span>
                        </dd>

                        <dt>
                            <label for="expire_taxonomy"><?php _e('Expiration time for taxonomy', 'wc-mfpc'); ?></label>
                        </dt>
                        <dd>
                            <input type="number" name="expire_taxonomy" id="expire_taxonomy" value="<?php echo $wcMfpcConfig->getExpireTaxonomy(); ?>"/>
                            <span class="description"><?php _e('Sets validity time of taxonomy entry in seconds, including custom taxonomy.', 'wc-mfpc'); ?></span>
                        </dd>

                        <dt>
                            <label for="browsercache_taxonomy"><?php _e('Browser cache expiration time of taxonomy', 'wc-mfpc'); ?></label>
                        </dt>
                        <dd>
                            <input type="number" name="browsercache_taxonomy" id="browsercache_taxonomy" value="<?php echo $wcMfpcConfig->getBrowsercacheTaxonomy(); ?>"/>
                            <span class="description"><?php _e('Sets validity time of taxonomy for the browser cache.', 'wc-mfpc'); ?></span>
                        </dd>

                        <dt>
                            <label for="expire_home"><?php _e('Expiration time for home', 'wc-mfpc'); ?></label>
                        </dt>
                        <dd>
                            <input type="number" name="expire_home" id="expire_home" value="<?php echo $wcMfpcConfig->getExpireHome() ?>"/>
                            <span class="description"><?php _e('Sets validity time of home on server side.', 'wc-mfpc'); ?></span>
                        </dd>

                        <dt>
                            <label for="browsercache_home"><?php _e('Browser cache expiration time of home', 'wc-mfpc'); ?></label>
                        </dt>
                        <dd>
                            <input type="number" name="browsercache_home" id="browsercache_home" value="<?php echo $wcMfpcConfig->getBrowsercacheHome(); ?>"/>
                            <span class="description"><?php _e('Sets validity time of home for the browser cache.', 'wc-mfpc'); ?></span>
                        </dd>

                        <dt>
                            <label for="charset"><?php _e('Charset', 'wc-mfpc'); ?></label>
                        </dt>
                        <dd>
                            <input type="text" name="charset" id="charset" value="<?php echo $wcMfpcConfig->getCharset(); ?>"/>
                            <span class="description"><?php _e('Charset of HTML and XML (pages and feeds) data.', 'wc-mfpc'); ?></span>
                        </dd>

                        <dt>
                            <label for="invalidation_method"><?php _e('Cache invalidation method', 'wc-mfpc'); ?></label>
                        </dt>
                        <dd>
                            <select name="invalidation_method" id="invalidation_method">
                                <?php $this->print_select_options($this->select_invalidation_method, $wcMfpcConfig->getInvalidationMethod()) ?>
                            </select>
                            <div class="description"><?php _e('Select cache invalidation method.', 'wc-mfpc'); ?>
                                <ol>
                                    <?php
                                    $invalidation_method_description = [
                                        'clears everything in storage, <strong>including values set by other applications</strong>',
                                        'clear only the modified posts entry, everything else remains in cache',
                                        'unvalidates post and the taxonomy related to the post',
                                    ];

                                    foreach ($this->select_invalidation_method AS $current_key => $current_invalidation_method) {

                                        printf('<li><em>%1$s</em> - %2$s</li>', $current_invalidation_method, $invalidation_method_description[ $current_key ]);

                                    }

                                    ?>
                                </ol>
                            </div>
                        </dd>

                        <dt>
                            <label for="comments_invalidate"><?php _e('Invalidate on comment actions', 'wc-mfpc'); ?></label>
                        </dt>
                        <dd>
                            <input type="checkbox" name="comments_invalidate" id="comments_invalidate" value="1" <?php checked($wcMfpcConfig->isCommentsInvalidate(), true); ?> />
                            <span class="description"><?php _e('Trigger cache invalidation when a comments is posted, edited, trashed. ', 'wc-mfpc'); ?></span>
                        </dd>

                        <dt>
                            <label for="prefix_data"><?php _e('Data prefix', 'wc-mfpc'); ?></label>
                        </dt>
                        <dd>
                            <input type="text" name="prefix_data" id="prefix_data" value="<?php echo $wcMfpcConfig->getPrefixData(); ?>"/>
                            <span
                                class="description"><?php _e('Prefix for HTML content keys, can be used in nginx.<br /><strong>WARNING</strong>: changing this will result the previous cache to becomes invalid!<br />If you are caching with nginx, you should update your nginx configuration and reload nginx after changing this value.',
                                    'wc-mfpc'
                                ); ?></span>
                        </dd>

                        <dt>
                            <label for="prefix_meta"><?php _e('Meta prefix', 'wc-mfpc'); ?></label>
                        </dt>
                        <dd>
                            <input type="text" name="prefix_meta" id="prefix_meta" value="<?php echo $wcMfpcConfig->getPrefixMeta(); ?>"/>
                            <span class="description"><?php _e('Prefix for meta content keys, used only with PHP processing.<br /><strong>WARNING</strong>: changing this will result the previous cache to becomes invalid!', 'wc-mfpc'); ?></span>
                        </dd>

                        <dt>
                            <label for="key"><?php _e('Key scheme', 'wc-mfpc'); ?></label>
                        </dt>
                        <dd>
                            <input type="text" name="key" id="key" value="<?php echo $wcMfpcConfig->getKey(); ?>"/>
                            <span class="description"><?php _e('Key layout; <strong>use the guide below to change it</strong>.<br /><strong>WARNING</strong>: changing this will result the previous cache to becomes invalid!<br />If you are caching with nginx, you should update your nginx configuration and reload nginx after changing this value.', 'wc-mfpc'); ?></span>
                            <dl class="description"><?php
                                foreach ($this->list_uri_vars as $uri => $desc) {
                                    echo '<dt>' . $uri . '</dt><dd>' . $desc . '</dd>';
                                }
                                ?></dl>
                        </dd>

                        <dt>
                            <label for="hashkey"><?php _e('SHA1 hash key', 'wc-mfpc'); ?></label>
                        </dt>
                        <dd>
                            <input type="checkbox" name="hashkey" id="hashkey" value="1" <?php checked($wcMfpcConfig->isHashkey(), true); ?> />
                            <span
                                class="description"><?php _e('Occasionally URL can be too long to be used as key for the backend storage, especially with memcached. Turn on this feature to use SHA1 hash of the URL as key instead. Please be aware that you have to add ( or uncomment ) a line and a <strong>module</strong> in nginx if you want nginx to fetch the data directly; for details, please see the nginx example tab.',
                                    'wc-mfpc'
                                ); ?>
                        </dd>


                    </dl>
                </fieldset>

                <fieldset id="<?php echo Data::plugin_constant; ?>-debug">
                    <legend><?php _e('Debug & in-depth settings', 'wc-mfpc'); ?></legend>
                    <h3><?php _e('Notes', 'wc-mfpc'); ?></h3>
                    <p><?php _e('The former method of debug logging flag has been removed. In case you need debug log from WC-MFPC please set both the <a href="http://codex.wordpress.org/WP_DEBUG">WP_DEBUG</a> and the WC_MFPC__DEBUG_MODE constants `true` in wp-config.php.<br /> This will enable NOTICE level messages apart from the WARNING level ones which are always displayed.', 'wc-mfpc'); ?></p>

                    <dl>
                        <dt>
                            <label for="pingback_header"><?php _e('Enable X-Pingback header preservation', 'wc-mfpc'); ?></label>
                        </dt>
                        <dd>
                            <input type="checkbox" name="pingback_header" id="pingback_header" value="1" <?php checked($wcMfpcConfig->isPingbackHeader(), true); ?> />
                            <span class="description"><?php _e('Preserve X-Pingback URL in response header.', 'wc-mfpc'); ?></span>
                        </dd>

                        <dt>
                            <label for="response_header"><?php _e("Add X-Cache-Engine header", 'wc-mfpc'); ?></label>
                        </dt>
                        <dd>
                            <input type="checkbox" name="response_header" id="response_header" value="1" <?php checked($wcMfpcConfig->isResponseHeader(), true); ?> />
                            <span class="description"><?php _e('Add X-Cache-Engine HTTP header to HTTP responses.', 'wc-mfpc'); ?></span>
                        </dd>

                        <dt>
                            <label for="generate_time"><?php _e("Add HTML debug comment", 'wc-mfpc'); ?></label>
                        </dt>
                        <dd>
                            <input type="checkbox" name="generate_time" id="generate_time" value="1" <?php checked($wcMfpcConfig->isGenerateTime(), true); ?> />
                            <span class="description"><?php _e('Adds comment string including plugin name, cache engine and page generation time to every generated entry before closing <body> tag.', 'wc-mfpc'); ?></span>
                        </dd>

                    </dl>

                </fieldset>

                <fieldset id="<?php echo Data::plugin_constant ?>-exceptions">
                    <legend><?php _e('Set cache additions/excepions', 'wc-mfpc'); ?></legend>
                    <dl>
                        <dt>
                            <label for="cache_loggedin"><?php _e('Enable cache for logged in users', 'wc-mfpc'); ?></label>
                        </dt>
                        <dd>
                            <input type="checkbox" name="cache_loggedin" id="cache_loggedin" value="1" <?php checked($wcMfpcConfig->isCacheLoggedin(), true); ?> />
                            <span class="description"><?php _e('Cache pages even if user is logged in.', 'wc-mfpc'); ?></span>
                        </dd>

                        <dt>
                            <label><?php _e("Excludes", 'wc-mfpc'); ?></label>
                        </dt>
                        <dd>
                            <table style="width:100%">
                                <thead>
                                <tr>
                                    <th style="width:13%; text-align:left"><label for="nocache_home"><?php _e("Exclude home", 'wc-mfpc'); ?></label></th>
                                    <th style="width:13%; text-align:left"><label for="nocache_feed"><?php _e("Exclude feeds", 'wc-mfpc'); ?></label></th>
                                    <th style="width:13%; text-align:left"><label for="nocache_archive"><?php _e("Exclude archives", 'wc-mfpc'); ?></label></th>
                                    <th style="width:13%; text-align:left"><label for="nocache_page"><?php _e("Exclude pages", 'wc-mfpc'); ?></label></th>
                                    <th style="width:13%; text-align:left"><label for="nocache_single"><?php _e("Exclude singulars", 'wc-mfpc'); ?></label></th>
                                    <th style="width:17%; text-align:left"><label for="nocache_dyn"><?php _e("Dynamic requests", 'wc-mfpc'); ?></label></th>
                                    <th style="width:18%; text-align:left"><label for="nocache_woocommerce"><?php _e("WooCommerce", 'wc-mfpc'); ?></label></th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="nocache_home" id="nocache_home" value="1" <?php checked($wcMfpcConfig->isNocacheHome(), true); ?> />
                                        <span class="description"><?php _e('Never cache home.', 'wc-mfpc'); ?>
                                    </td>
                                    <td>
                                        <input type="checkbox" name="nocache_feed" id="nocache_feed" value="1" <?php checked($wcMfpcConfig->isNocacheFeed(), true); ?> />
                                        <span class="description"><?php _e('Never cache feeds.', 'wc-mfpc'); ?>
                                    </td>
                                    <td>
                                        <input type="checkbox" name="nocache_archive" id="nocache_archive" value="1" <?php checked($wcMfpcConfig->isNocacheArchive(), true); ?> />
                                        <span class="description"><?php _e('Never cache archives.', 'wc-mfpc'); ?>
                                    </td>
                                    <td>
                                        <input type="checkbox" name="nocache_page" id="nocache_page" value="1" <?php checked($wcMfpcConfig->isNocachePage(), true); ?> />
                                        <span class="description"><?php _e('Never cache pages.', 'wc-mfpc'); ?>
                                    </td>
                                    <td>
                                        <input type="checkbox" name="nocache_single" id="nocache_single" value="1" <?php checked($wcMfpcConfig->isNocacheSingle(), true); ?> />
                                        <span class="description"><?php _e('Never cache singulars.', 'wc-mfpc'); ?>
                                    </td>
                                    <td>
                                        <input type="checkbox" name="nocache_dyn" id="nocache_dyn" value="1" <?php checked($wcMfpcConfig->isNocacheDyn(), true); ?> />
                                        <span class="description"><?php _e('Exclude every URL with "?" in it.', 'wc-mfpc'); ?></span>
                                    </td>
                                    <td>
                                        <input type="hidden" name="nocache_woocommerce_url" id="nocache_woocommerce_url"
                                               value="<?php echo $wcMfpcConfig->getNocacheWoocommerceUrl(); ?>"
                                        />
                                        <input type="checkbox" name="nocache_woocommerce" id="nocache_woocommerce" value="1" <?php checked($wcMfpcConfig->isNocacheWoocommerce(), true); ?> />
                                        <span class="description">
                                            <?php _e('Exclude dynamic WooCommerce page.', 'wc-mfpc'); ?>
                                            <?php echo "<br />Url:" . $wcMfpcConfig->getNocacheWoocommerceUrl(); ?>
                                        </span>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </dd>
                        <dt>
                            <label for="nocache_cookies"><?php _e("Exclude based on cookies", 'wc-mfpc'); ?></label>
                        </dt>
                        <dd>
                            <input type="text" name="nocache_cookies" id="nocache_cookies"
                                   value="<?php echo $wcMfpcConfig->isNocacheCookies(); ?>"
                            />
                            <span class="description">
                              Exclude content based on cookies names starting with this from caching. Separate multiple
                              cookies names with commas.<br />If you are caching with nginx, you should update your
                              nginx configuration and reload nginx after changing this value.
                            </span>
                        </dd>

                        <dt>
                            <label for="nocache_url"><?php _e("Don't cache following URL paths - use with caution!", 'wc-mfpc'); ?></label>
                        </dt>
                        <dd>
					                <textarea name="nocache_url" id="nocache_url" rows="3" cols="100" class="large-text code">
                            <?php echo $wcMfpcConfig->getNocacheUrl(); ?>
                          </textarea>
                          <span class="description"><?php _e('Regular expressions use you must! e.g. <em>pattern1|pattern2|etc</em>', 'wc-mfpc'); ?></span>
                        </dd>

                        <dt>
                            <label for="nocache_comment"><?php _e("Exclude from cache based on content", 'wc-mfpc'); ?></label>
                        </dt>
                        <dd>
                            <input name="nocache_comment" id="nocache_comment" type="text"
                                   value="<?php echo $wcMfpcConfig->getNocacheComment(); ?>"
                            />
                            <span class="description">
                              Enter a regex pattern that will trigger excluding content from caching. Eg. <!--nocache-->.
                              Regular expressions use you must! e.g. <em>pattern1|pattern2|etc</em><br />
                              <strong>WARNING:</strong>
                              be careful where you display this, because it will apply to any content, including
                              archives, collection pages, singles, anything. If empty, this setting will be ignored.
                            </span>
                        </dd>

                    </dl>
                </fieldset>

                <fieldset id="<?php echo Data::plugin_constant ?>-servers">
                    <legend><?php _e('Backend server settings', 'wc-mfpc'); ?></legend>
                    <dl>
                        <dt>
                            <label for="hosts"><?php _e('Hosts', 'wc-mfpc'); ?></label>
                        </dt>
                        <dd>
                            <input type="text" name="hosts" id="hosts" value="<?php echo $wcMfpcConfig->getHosts(); ?>"/>
                            <span class="description">
					        <?php _e('List of backends, with the following syntax: <br />- in case of TCP based connections, list the servers as host1:port1,host2:port2,... . Do not add trailing , and always separate host and port with : .<br />- for a unix socket enter: unix://[socket_path]', 'wc-mfpc'); ?>
                </span>
                        </dd>

                        <h3><?php _e('Authentication ( only for SASL enabled Memcached)') ?></h3>
                        <?php if (! ini_get('memcached.use_sasl') && (! empty($wcMfpcConfig->getAuthuser()) || ! empty($wcMfpcConfig->getAuthpass()))) { ?>
                            <div class="error">
                              <p>
                                <strong>
                                  WARNING: you\'ve entered username and/or password for memcached authentication ( or
                                  your browser\'s autocomplete did ) which will not work unless you enable memcached
                                  sasl in the PHP settings: add `memcached.use_sasl=1` to php.ini', 'wc-mfpc'); ?>
                                </strong>
                              </p>
                            </div>
                        <?php } ?>
                        <dt>
                            <label for="authuser"><?php _e('Authentication: username', 'wc-mfpc'); ?></label>
                        </dt>
                        <dd>
                            <input type="text" autocomplete="off" name="authuser" id="authuser" value="<?php echo $wcMfpcConfig->getAuthuser(); ?>"/>
                            <span class="description">
					                    <?php _e('Username for authentication with backends', 'wc-mfpc'); ?>
                            </span>
                        </dd>

                        <dt>
                            <label for="authpass"><?php _e('Authentication: password', 'wc-mfpc'); ?></label>
                        </dt>
                        <dd>
                            <input type="password" autocomplete="off" name="authpass" id="authpass" value="<?php echo $wcMfpcConfig->getAuthpass(); ?>"/>
                            <span class="description">
					                    <?php _e('Password for authentication with for backends - WARNING, the password will be stored in an unsecure format!', 'wc-mfpc'); ?>
                            </span>
                        </dd>

                        <dt>
                            <label for="memcached_binary"><?php _e('Enable memcached binary mode', 'wc-mfpc'); ?></label>
                        </dt>
                        <dd>
                            <input type="checkbox" name="memcached_binary" id="memcached_binary" value="1" <?php checked($wcMfpcConfig->isMemcachedBinary(), true); ?> />
                            <span class="description">
                              <?php _e('Some memcached proxies and implementations only support the ASCII protocol.', 'wc-mfpc'); ?>
                            </span>
                        </dd>


                    </dl>
                </fieldset>

                <fieldset id="<?php echo Data::plugin_constant ?>-precache">
                    <legend><?php _e('Precache settings & log from previous pre-cache generation', 'wc-mfpc'); ?></legend>

                    <dt>
                        <label for="precache_schedule"><?php _e('Precache schedule', 'wc-mfpc'); ?></label>
                    </dt>
                    <dd>
                        <select name="precache_schedule" id="precache_schedule">
                            <?php $this->print_select_options($this->select_schedules, $wcMfpcConfig->getPrecacheSchedule()) ?>
                        </select>
                        <span class="description"><?php _e('Schedule autorun for precache with WP-Cron', 'wc-mfpc'); ?></span>
                    </dd>

                    <?php
                    global $wcMfpc;

                    $gentime = self::_get_option(Data::precache_timestamp, $wcMfpcData->network);
                    $log     = self::_get_option(Data::precache_log, $wcMfpcData->network);

                    if (@file_exists($wcMfpc->precache_logfile)) {
                        $logtime = filemtime($wcMfpcData->precache_logfile);
                        /* update precache log in DB if needed */
                        if ($logtime > $gentime) {
                            $log = file($wcMfpcData->precache_logfile);
                            self::_update_option($wcMfpcData->precache_log, $log, $wcMfpcData->network);
                            self::_update_option($wcMfpcData->precache_timestamp, $logtime, $wcMfpcData->network);
                        }
                    }
                    if (empty ($log)) {
                        _e('No precache log was found!', 'wc-mfpc');
                    } else { ?>
                        <p><strong><?php _e('Time of run: ') ?><?php echo date('r', $gentime); ?></strong></p>
                        <div style="overflow: auto; max-height: 20em;">
                            <table style="width:100%; border: 1px solid #ccc;">
                                <thead>
                                <tr>
                                    <?php $head = explode("	", array_shift($log));
                                    foreach ($head as $column) { ?>
                                        <th><?php echo $column; ?></th>
                                    <?php } ?>
                                </tr>
                                </thead>
                                <?php
                                foreach ($log as $line) { ?>
                                    <tr>
                                        <?php $line = explode("	", $line);
                                        foreach ($line as $column) { ?>
                                            <td><?php echo $column; ?></td>
                                        <?php } ?>
                                    </tr>
                                <?php } ?>
                            </table>
                        </div>
                    <?php } ?>
                </fieldset>

                <p class="clear">
                    <input class="button-primary" type="submit" name="<?php echo Data::button_save ?>"
                           id="<?php echo Data::button_save ?>"
                           value="<?php _e('Save Changes', 'wc-mfpc') ?>"
                    />
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Renders information for administrators if conditions are met.
     *
     * @return void
     */
    private function renderMessages()
    {
        global $wcMfpc, $wcMfpcConfig;

        /*
         * if options were saved, display saved message
         */
        if (isset($_GET[ Data::key_save ]) && $_GET[ Data::key_save ] == 'true' || $this->status == 1) { ?>

            <div class='updated settings-error'>
              <p>
                <strong>
                  Settings saved.
                </strong>
              </p>
            </div>

        <?php }

        /*
         * if options were delete, display delete message
         */
        if (isset($_GET[ Data::key_delete ]) && $_GET[ Data::key_delete ] == 'true' || $this->status == 2) { ?>

            <div class='error'>
              <p>
                <strong>
                  Plugin options deleted.
                </strong>
              </p>
            </div>

        <?php }

        /*
         * if options were saved
         */
        if (isset($_GET[ Data::key_flush ]) && $_GET[ Data::key_flush ] == 'true' || $this->status == 3) { ?>

            <div class='updated settings-error'>
              <p>
                <strong>
                  Cache flushed.
                </strong>
              </p>
            </div>

        <?php }

        /*
         * if options were saved, display saved message
         */
        if ((isset($_GET[ Data::key_precache ]) && $_GET[ Data::key_precache ] == 'true') || $this->status == 4) { ?>

            <div class='updated settings-error'>
              <p>
                <strong>
                  Precache process was started, it is now running in the background, please be patient, it may take a
                  very long time to finish.
                </strong>
              </p>
            </div>

        <?php } ?>

        <div class="updated">
          <p>
            <strong>
              Driver: <?php echo $wcMfpcConfig->getCacheType(); ?>
            </strong>
          </p>
          <p>
          <strong>Backend status:</strong><br />
          <?php
          /* we need to go through all servers */
          $servers = $wcMfpc->backend->status();

          if (is_array($servers) && ! empty ($servers)) {

              foreach ($servers as $server_string => $status) {

                  echo $server_string . " => ";

                  if ($status == 0) {

                      echo '<span class="error-msg">Down</span><br />';

                  } elseif ($status == 1) {

                      echo '<span class="ok-msg">Up & running</span><br />';

                  } else {

                      echo '<span class="error-msg">Unknown, please try re-saving settings!</span><br />';

                  }

              }

          }
          ?>
          </p>
        </div>
        <?php
    }

    /**
     * Renders the Form with the action buttons for "Pre-Cache", "Clear-Cache" & "Reset-Options".
     *
     * @return void
     */
    private function renderActionButtons()
    {
        global $wcMfpcData;

        $disabled = '';

        if (
            (isset($_GET[ Data::key_precache_disabled ]) && $_GET[ Data::key_precache_disabled ] == 'true')
            || $this->status == 5
            || $wcMfpcData->shell_function == false
        ) {

            $disabled = 'disabled="disabled"';

        }

        ?>
        <form method="post" action="#" id="<?php echo Data::plugin_constant ?>-commands" class="plugin-admin">
          <?php wp_nonce_field('wc-mfpc'); ?>
          <table cellpadding="5" cellspacing="5">
            <tr>
              <td>
                <input class="button button-secondary" type="submit" name="<?php echo Data::button_precache ?>"
                       id="<?php echo Data::button_precache ?>"
                       value="<?php _e('Pre-cache', 'wc-mfpc') ?>"
                       <?php echo $disabled; ?>
                />
              </td>
              <td style="padding-left: 1rem;">
                      <span class="description">
                        Start a background process that visits all permalinks of all blogs it can found thus forces
                        WordPress to generate cached version of all the pages.<br />The plugin tries to visit links
                        of taxonomy terms without the taxonomy name as well. This may generate 404 hits, please be
                        prepared for these in your logfiles if you plan to pre-cache.
                      </span>
              </td>
            </tr>
            <tr>
              <td>
                <input class="button button-secondary" type="submit" name="<?php echo Data::button_flush; ?>"
                       id="<?php echo Data::button_flush; ?>"
                       value="<?php _e('Clear cache', 'wc-mfpc') ?>"
                />
              </td>
              <td style="padding-left: 1rem;">
                      <span class="description">
                        Clear all entries in the storage, including the ones that were set by other processes.
                      </span>
              </td>
            </tr>
            <tr>
              <td>
                <input class="button button-warning" type="submit" name="<?php echo Data::button_delete; ?>"
                       id="<?php echo Data::button_delete; ?>"
                       value="<?php _e('Reset options', 'wc-mfpc') ?>"
                />
              </td>
              <td style="padding-left: 1rem;">
                <span class="description">Reset settings to defaults.</span>
              </td>
            </tr>
          </table>
        </form>
        <?php
    }

}