<?php

/*
  Plugin Name: CakePress Plugin
  Version: 1.1.0
  Description: Allows WordPress to host a CakePHP web application.
  Author: Rolf Kaiser
  Author URI: https://github.com/rkaiser0324/CakePress
 */

class CakePressPlugin {

    var $_output = '';
    var $_title = '';
    var $_url = '';

    function __construct() {

        if (preg_match('/^\/app\//', $_SERVER['REQUEST_URI']))
            $this->_url = str_replace('/app', '', $_SERVER['REQUEST_URI']);
        elseif (preg_match('/\?option=com_jake&jrun=/', $_SERVER['REQUEST_URI']))
            $this->_url = str_replace('/?option=com_jake&jrun=', '', $_SERVER['REQUEST_URI']);
        elseif ($_SERVER['REQUEST_URI'] == '/app')
            $this->_url = '/';

        if (!empty($this->_url)) {
            define('JAKE', 1);
            // Handle redirects as per http://www.dev4press.com/2012/tutorials/wordpress/practical/canonical-redirect-problem-and-solutions/
            remove_filter('template_redirect', 'redirect_canonical');
            add_filter('redirect_canonical', function ($redirect_url, $requested_url) {
                return strstr($requested_url, '/app/') != null ? $requested_url : $redirect_url;
            }, -1, 2);
            add_filter('template_redirect', 'redirect_canonical');

            add_action('init', array($this, 'onInit'));
        }
    }

    function onInit() {
        require 'cake_embedded_dispatcher.class.php';
        $cakeDispatcher = new CakeEmbeddedDispatcher();
        $cakeDispatcher->setCakePath(dirname(ABSPATH) . DIRECTORY_SEPARATOR . 'cakephp');
        $cakeDispatcher->setCakeUrlBase('/app');

        // Set the SEF URL to match the rewrite rule in /.htaccess
        $cakeDispatcher->setSefCakeApplicationBase('/app');
        $cakeDispatcher->setComponent('/index.php?option=com_jake&jrun=$CAKE_ACTION');
        $cakeDispatcher->setCleanOutput(false);
        $cakeDispatcher->setCleanOutputParameter('task', 'clean');
        $cakeDispatcher->setIgnoreParameters(array('option', 'jrun', 'task', 'url'));
        $cakeDispatcher->setRestoreSession(true);

        //logfile('before get');
        $arr = $cakeDispatcher->get($this->_url);
        //logfile('after get');

        if (!empty($arr['head']['stylesheets'])) {
            for ($i = 0; $i < count($arr['head']['stylesheets']); $i++) {
                wp_enqueue_style('cake_stylesheet_' . $i, $arr['head']['stylesheets'][$i]['href']);
            }
        }
        if (!empty($arr['head']['script'])) {
            for ($i = 0; $i < count($arr['head']['script']); $i++) {
                wp_enqueue_script('cake_script_' . $i, $arr['head']['script'][$i]['src']);
            }
        }
        $this->_output = $arr['body'];
        $this->_title = $arr['head']['title'];

        add_filter('404_template', array($this, 'maybe_use_custom_404_template'));
        add_filter('wp_title', array($this, 'replacePageHeadTitle'), 100, 3);
    }

    // Inspired by https://wordpress.org/plugins/custom-404-error-page/
    // http://www.blogseye.com/creating-fake-wordpress-posts-on-the-fly/
    function maybe_use_custom_404_template($template) {
        global $wp_query, $post;

        $post = new stdClass();
        $post->ID = -1;
        $post->post_author = '';
        $post->post_date = '';
        $post->ancestors = array();
        $post->post_category = array('uncategorized');
        $post->post_excerpt = ''; //For all your post excerpt needs.
        $post->post_status = 'publish'; //Set the status of the new post.
        $post->post_title = $this->_title; //The title of your post.
        $post->post_type = 'page'; //Sometimes you might want to post a page.
        $post->post_content = $this->_output;

        // Populate the posts array with our 404 page object
        $wp_query->posts = array($post);

        // Set the query object to enable support for custom page templates
        $wp_query->queried_object_id = $post->ID;
        $wp_query->queried_object = $post;

        // Set post counters to avoid loop errors
        $wp_query->post_count = 1;
        $wp_query->found_posts = 1;
        $wp_query->max_num_pages = 0;

        return get_page_template();
    }

    // Simple helper function to aid in debugging
    private function debug($s) {
        echo '<pre style="padding:20px;background-color:yellow;font-size:larger">';
        print_r($s);
        echo "</pre>";
    }

    /**
     * Replace the page content. See https://pippinsplugins.com/playing-nice-with-the-content-filter/
     * 
     * @param string $content
     * @return string
     */
    function replacePageContent($content) {
        if (is_singular() && is_main_query()) {
            $content = $this->_output;
        }
        return $content;
    }

    /**
     * Replace the page title used in the <HEAD> tag, called via the wp_title() filter
     * 
     * @param string $title
     * @param string $sep
     * @param string $sep_location
     * @return string
     */
    function replacePageHeadTitle($title, $sep, $sep_location) {
        return $this->_title;
    }

}

$cakepress_plugin = new CakePressPlugin();
