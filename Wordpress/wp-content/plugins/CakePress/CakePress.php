<?php

/*
  Plugin Name: CakePress Plugin
  Version: 1.1.1
  Description: Allows WordPress to host a CakePHP web application.
  Author: Rolf Kaiser
  Author URI: https://github.com/rkaiser0324/CakePress
 */

class CakePressPlugin {

    private $_contents = array();
    private $_url = '';

    function __construct() {

        if (preg_match('/^\/app\//', $_SERVER['REQUEST_URI']))
            $this->_url = str_replace('/app', '', $_SERVER['REQUEST_URI']);
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
        $cakeDispatcher->setCleanOutput(false);
        $cakeDispatcher->setIgnoreParameters(array('url'));
        $cakeDispatcher->setRestoreSession(true);

        $this->_contents = $cakeDispatcher->get($this->_url);

        // Enqueue the CakePHP JS and CSS assets last
        add_action('wp_enqueue_scripts', array($this, 'enqueueCakephpAssets'), 100);
        
        add_filter('404_template', array($this, 'custom404Template'));
        add_filter('wp_title', array($this, 'replacePageHeadTitle'), 100, 3);
    }
    
    /**
     * Enqueue the CakePHP JS and CSS assets.
     */
    function enqueueCakephpAssets()
    {
        if (!empty($this->_contents['head']['stylesheets'])) {
            for ($i = 0; $i < count($this->_contents['head']['stylesheets']); $i++) {
                wp_enqueue_style('cake_stylesheet_' . $i, $this->_contents['head']['stylesheets'][$i]['href']);
            }
        }
        if (!empty($this->_contents['head']['script'])) {
            for ($i = 0; $i < count($this->_contents['head']['script']); $i++) {
                wp_enqueue_script('cake_script_' . $i, $this->_contents['head']['script'][$i]['src']);
            }
        }
    }

    // Inspired by https://wordpress.org/plugins/custom-404-error-page/
    // http://www.blogseye.com/creating-fake-wordpress-posts-on-the-fly/
    function custom404Template($template) {
        global $wp_query, $post;

        $post = new stdClass();
        $post->ID = 99999999;
        $post->post_author = '';
        $post->post_date = '';
        $post->ancestors = array();
        $post->post_category = array('uncategorized');
        $post->post_excerpt = ''; //For all your post excerpt needs.
        $post->post_status = 'publish'; //Set the status of the new post.
        $post->post_title = $this->_contents['head']['title']; //The title of your post.
        $post->post_type = 'page'; //Sometimes you might want to post a page.
        $post->post_content = $this->_contents['body'];

        // Populate the posts array with our 404 page object
        $wp_query->posts = array($post);

        // Set the query object to enable support for custom page templates
        $wp_query->queried_object_id = $post->ID;
        $wp_query->queried_object = $post;

        // Set post counters to avoid loop errors
        $wp_query->post_count = 1;
        $wp_query->found_posts = 1;
        $wp_query->max_num_pages = 0;
        
	// See http://wordpress.stackexchange.com/questions/66331/how-does-one-suppress-a-404-status-code-in-a-wordpress-page
        status_header(200);
//die(get_page_template());
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
            $content = $this->_contents['body'];
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
        return $this->_contents['head']['title'];
    }

}

new CakePressPlugin();

add_filter('the_content', 'modify_content');
function modify_content($content) {
    global $post;
    if ($post->ID != -99)
        return $content;

    $modified_content = 'new page';
    return $modified_content;
}
