<?php

/*
  Plugin Name: CakePress
  Plugin URI: https://github.com/rkaiser0324/CakePress
  Version: 2.1.2
  Description: Allows WordPress to host a CakePHP web application.
  Author: Rolf Kaiser
  Author URI: http://blog.echothis.com
 */

class CakePressPlugin {

    private $_contents = array();
    private $_url = '';

    function __construct() {
        $this->_url = $_SERVER['REQUEST_URI'];
        // Give the theme a chance to set the filters
        add_action('init', array($this, 'onInit'), 99);
    }

    function onInit() {
        $regex_without_leading_slash = apply_filters('cakepress_url_regex', '');

        if (!empty($regex_without_leading_slash)) {
            add_rewrite_rule($regex_without_leading_slash, 'index.php?pagename=cakepress', 'top');

            $regex_with_leading_slash = preg_replace('@^(\^(.+))@', '^/$2', $regex_without_leading_slash);

            if (preg_match("@$regex_with_leading_slash@", $this->_url)) {
                if ($this->_checkAcl($this->_url)) {

                    // Prevent the redirection back to /cakepress/*
                    remove_filter('template_redirect', 'redirect_canonical');

                    add_shortcode('cakepress', array($this, 'onShortcode'));

                    define('JAKE', 1);
                    require 'cake_embedded_dispatcher.class.php';
                    $cakeDispatcher = new CakeEmbeddedDispatcher();
                    $cakeDispatcher->setCakePath(apply_filters('cakepress_cakephp_path', dirname(ABSPATH) . DIRECTORY_SEPARATOR . 'cakephp'));
                    define('CAKEPRESS_CLEAN_OUTPUT', apply_filters('cakepress_clean_output', false, $this->_url));
                    $cakeDispatcher->setCleanOutput(CAKEPRESS_CLEAN_OUTPUT);
                    $cakeDispatcher->setRestoreSession(true);

                    $this->_contents = apply_filters('cakepress_filter_contents_array', $cakeDispatcher->get($this->_url), $this->_url);

                    // Strip any other parsed query_vars so that the request will be processed by the plugin
                    add_action('parse_request', function($query) {
                        $query->query_vars = ['pagename' => 'cakepress'];
                        return $query;
                    }, 99);

                    // Change the template; see http://stackoverflow.com/questions/8793304/change-template-in-wordpress-plugin 
                    // and http://codex.wordpress.org/Plugin_API/Filter_Reference/page_template
                    add_filter('page_template', function() {
                        return dirname(__FILE__) . '/cakepress-page-template.php';
                    });

                    // Enqueue the CakePHP JS and CSS assets last
                    add_action('wp_enqueue_scripts', array($this, 'enqueueCakephpAssets'), 100);

                    add_filter('wp_title', array($this, 'replacePageHeadTitleLegacy'), 9, 2);

                    // New for WordPress v4.4 - https://developer.wordpress.org/reference/hooks/document_title_parts/
                    add_filter('document_title_parts', array($this, 'replacePageHeadTitle'), 100);

                    // Enqueue the CakePHP <head> tags last
                    add_action('wp_head', function() {
                        // Append the meta and custom tags, as well as any inline Javascript
                        echo implode("\n", $this->_contents['head']['meta']) . implode("\n", $this->_contents['head']['custom']);
                        foreach ($this->_contents['head']['script'] as $el)
                            if (!empty($el['body']))
                                echo $el['tag'] . "\n";
                    }, 100);
                }
                else {
                    // Access is not allowed to this URL, so return a 404
                    add_filter('parse_query', function($query) {
                        $query->set('pagename', '');
                        $query->set_404();
                    }, 1000, 1);
                }
            }
        }
    }

    /**
     * Check the ACL - by default, it is disallowed if it contains "wc-ajax=get_refreshed_fragments".
     *
     * @param string $url
     * 
     * @return string
     */
    private function _checkAcl($url) {
        $is_allowed = !preg_match('@wc-ajax=get_refreshed_fragments@', $url);
        return apply_filters('cakepress_check_acl', $is_allowed, $url);
    }

    /**
     * Return the contents of the Cake view.  It may additionally execute any shortcodes contained within.
     *
     * @return string
     */
    function onShortcode() {
        $html = $this->_contents['body'];
        if (apply_filters('cakepress_execute_shortcodes', true, $this->_url))
            $html = do_shortcode($html);
        return $html;
    }

    /**
     * Enqueue the CakePHP JS and CSS assets.  For JS assets, if the script src matches a script already registered by wp_register_script() then
     * it is enqueued via that handle, to maintain positioning and dependencies.
     */
    function enqueueCakephpAssets() {

        global $wp_scripts;

        if (!empty($this->_contents['head']['stylesheets'])) {
            for ($i = 0; $i < count($this->_contents['head']['stylesheets']); $i++) {
                wp_enqueue_style('cake_stylesheet_' . $i, $this->_contents['head']['stylesheets'][$i]['href']);
            }
        }

        // For reference, this is the only way to add the "id" attribute to a script; seems like a bug to me.
        // As per http://wordpress.stackexchange.com/questions/38319/how-to-add-defer-defer-tag-in-plugin-javascripts
//        add_filter('script_loader_tag', function ( $tag, $handle ) {
//            if (preg_match('/^cake_/', $handle))
//                $tag = str_replace(" src", " id='" . $handle . "' src", $tag);
//
//            return $tag;
//        }, 10, 2);

        $domain = (is_ssl() ? 'https://' : 'http://') . $_SERVER['SERVER_NAME'];
        if (!empty($this->_contents['head']['script'])) {
            for ($i = 0; $i < count($this->_contents['head']['script']); $i++) {
                $script_handle = null;
                foreach ($wp_scripts->registered as $registered_script_object) {
                    if ($registered_script_object->src == $this->_contents['head']['script'][$i]['src'] ||
                            $registered_script_object->src == $domain . $this->_contents['head']['script'][$i]['src']
                    ) {
                        $script_handle = $registered_script_object->handle;
                        break;
                    }
                }

                if (!empty($script_handle))
                    wp_enqueue_script($script_handle);
                else
                    wp_enqueue_script('cake_script_' . $i, $this->_contents['head']['script'][$i]['src']);
            }
        }
    }

    /**
     * Replace the page title used in the <HEAD> tag, called via the document_title_parts filter (replacing wp_title in WordPress 4.4)
     * https://developer.wordpress.org/reference/hooks/document_title_parts/
     *
     * @param array $title
     * @return array
     */
    function replacePageHeadTitle($title) {
        $title['title'] = $this->_contents['head']['title'];
        return $title;
    }

    /**
     * Replace the page title used in the <HEAD> tag.  Only used by the deprecated wp_title filter.
     *
     * @param string $title
     * @param string $sep
     * @return string
     */
    function replacePageHeadTitleLegacy($title, $sep) {
        $title = preg_replace('@^CakePress@', $this->_contents['head']['title'], $title);
        return $title;
    }

}

new CakePressPlugin();
