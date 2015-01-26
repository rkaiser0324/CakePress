<?php
/*
  Plugin Name: CakePress
  Version: 1.1.3
  Description: Allows WordPress to host a CakePHP web application.
  Author: Rolf Kaiser
  Author URI: https://github.com/rkaiser0324/CakePress
 */

class CakePressPlugin {

    private $_contents = array();
    private $_url = '';

    function __construct() {
        add_action('init', array($this, 'onInit'));
    }

    function onInit() {

        if (strstr($_SERVER['QUERY_STRING'], 'pagename=cakepress') != null) {

            $this->_url = $_SERVER['REQUEST_URI'];

            if ($this->_checkAcl($this->_url)) {

                $this->_checkCacheBlacklist($this->_url);

                // Prevent the redirection back to /cakepress/*
                remove_filter('template_redirect', 'redirect_canonical');

                add_shortcode('cakepress', array($this, 'onShortcode'));

                define('JAKE', 1);
                require 'cake_embedded_dispatcher.class.php';
                $cakeDispatcher = new CakeEmbeddedDispatcher();
                $cakeDispatcher->setCakePath(dirname(ABSPATH) . DIRECTORY_SEPARATOR . 'cakephp');
                $cakeDispatcher->setCleanOutput(false);
                $cakeDispatcher->setIgnoreParameters(array('url'));
                $cakeDispatcher->setRestoreSession(true);

                $original_query_string = $_SERVER['QUERY_STRING'];
                $_SERVER['QUERY_STRING'] = str_replace('post_type=page&pagename=cakepress', '', $original_query_string);
                // $_SERVER['REDIRECT_QUERY_STRING'] is set by Apache, but not by nginx/php-fpm
                $original_redirect_query_string = empty($_SERVER['REDIRECT_QUERY_STRING']) ? '' : $_SERVER['REDIRECT_QUERY_STRING'];
                $_SERVER['REDIRECT_QUERY_STRING'] = str_replace('post_type=page&pagename=cakepress', '', $original_redirect_query_string);
                unset($_GET['post_type']);
                unset($_GET['pagename']);
                $this->_contents = $cakeDispatcher->get($this->_url);
                $_SERVER['QUERY_STRING'] = $original_query_string;
                $_SERVER['REDIRECT_QUERY_STRING'] = $original_redirect_query_string;
                $_GET['post_type'] = 'page';
                $_GET['pagename'] = 'cakepress';

                /* If you want to change the template on a per-URI basis, do this:
                if (preg_match('@^/(uri1|uri2)@', $this->_url)) {
                    // Change the template; see http://stackoverflow.com/questions/8793304/change-template-in-wordpress-plugin and http://codex.wordpress.org/Plugin_API/Filter_Reference/page_template
                    add_filter('page_template', function() {
                        return get_theme_root() . '/path/to/template.php';
                    });
                }
                 */

                // Enqueue the CakePHP JS and CSS assets (assume last)
                add_action('wp_enqueue_scripts', array($this, 'enqueueCakephpAssets'), 100);

                // Enqueue the CakePHP <head> tags (assume last)
                add_action('wp_head', function() {
                    // Remove the wpautop filter, so the Cake content displays as-is.  See http://wordpress.org/support/topic/removing-wpautop-filter
                    remove_filter('the_content', 'wpautop');
                    remove_filter('the_excerpt', 'wpautop');
                    
                    // Append the meta and custom tags, as well as any inline Javascript
                    echo implode("\n", $this->_contents['head']['meta']) . implode("\n", $this->_contents['head']['custom']);
                    foreach ($this->_contents['head']['script'] as $el)
                        if (!empty($el['body']))
                            echo $el['tag'] . "\n";
                }, 100);
            }
        }
    }

    /**
     * Verify that that URL isn't on the cache blacklist, and if it is, set the page to not be cached.  This is only needed if you
     * are using a caching plugin, like Quick Cache.  Other plugins probably use a different constant flag from DONOTCACHEPAGE.
     * 
     * Quick Cache, for example, will automatically skip caching for:
     * 1) URLs with query strings 
     * 2) responses without "</html>"
     * 3) reponses in a Wordpress error body tag
     * 
     * @param string $url
     * @return void
     */
    private function _checkCacheBlacklist($url) {

        $blacklist = false;
        // Add logic to check URLs here, e.g.:
//        if (preg_match('@/controller/action/var1@'))
//            $blacklist = true;

        if ($blacklist)
            define('DONOTCACHEPAGE', 1);
    }

    /**
     * Verify that this Cake-handled URL doesn't require Editor access.  
     * If it does, we'll treat it as a 404 by adding the parse_query filter.
     * 
     * @param string $url
     * @return bool $success
     */
    private function _checkAcl($url) {

        $success = true;
        /*
         * Modify this logic as needed
         
        if (!current_user_can('edit_posts')) {

            // Modify this regex as needed
            if (preg_match('@/(add|edit/|delete/|fragment1|fragment2)@', $url)) {
                // Recommended here: http://wordpress-hackers.1065353.n5.nabble.com/Throw-404-error-via-plugin-tp12507p12518.html
                add_filter('parse_query', function($query) {
                    $query->set('pagename', '');
                    $query->set_404();
                }, 1000, 1);
            }
        }
         */
        return $success;
    }

    /**
     * Return the contents of the Cake view.
     * 
     * @return string
     */
    function onShortcode() {
        return $this->_contents['body'];
    }

    /**
     * Enqueue the CakePHP JS and CSS assets.
     */
    function enqueueCakephpAssets() {

        if (!empty($this->_contents['head']['stylesheets'])) {
            for ($i = 0; $i < count($this->_contents['head']['stylesheets']); $i++) {
                wp_enqueue_style('cake_stylesheet_' . $i, $this->_contents['head']['stylesheets'][$i]['href']);
            }
        }
        if (!empty($this->_contents['head']['script'])) {
            for ($i = 0; $i < count($this->_contents['head']['script']); $i++) {
                if (empty($el['body']))
                    wp_enqueue_script('cake_script_' . $i, $this->_contents['head']['script'][$i]['src']);
            }
        }
    }
}

new CakePressPlugin();