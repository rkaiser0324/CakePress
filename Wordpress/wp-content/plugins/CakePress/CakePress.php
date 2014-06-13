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

        if (preg_match('/^\/app/', $_SERVER['REQUEST_URI']))
            $this->_url = str_replace('/app', '', $_SERVER['REQUEST_URI']);
        elseif (preg_match('/\?option=com_jake&jrun=/', $_SERVER['REQUEST_URI']))
            $this->_url = str_replace('/?option=com_jake&jrun=', '', $_SERVER['REQUEST_URI']);

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

        logfile('before get');
        $arr = $cakeDispatcher->get($this->_url);
        //print_r($arr);die();

        logfile('after get');

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
        var_dump($arr);

        //add_filter('the_content', array($this, 'pippin_filter_content_sample'));
        // See http://wordpress.org/support/topic/how-do-i-create-a-new-page-with-the-plugin-im-building#post-1557249
        //add_filter( 'the_posts', array($this, 'my_plugin_page_filter' ));
        add_filter('the_content', array($this, 'replace_page_content'));
        //add_filter('the_title', array($this, 'replace_page_title'), 100);
        //add_filter('wp_title', array($this, 'replace_page_head_title'), 100, 3);
        //add_filter('the_content', array($this, 'getOutput'));
//            add_filter('the_content', function () {
//                return $arr['body'];
//            });
        //$this->_output = $arr['body'];
    }

//    function pippin_filter_content_sample($content) {
//        if (is_singular() && is_main_query()) {
//            $new_content = '<p>This is added to the bottom of all post and page content, as well as custom post types.</p>';
//            $content .= $new_content;
//        }
//        return $content;
//    }
//    function my_plugin_page_filter($posts) {
//
//        global $wp_query;
//
//        //if ($wp_query->get('my_plugin_page_is_called')) {
//
//        $posts[0]->post_title = 'The Page Title';
//        $posts[0]->post_content = $this->_output;
//        // }
//
//        return $posts;
//    }
    // Simple helper function to aid in debugging
    private function debug($s) {
        echo '<pre style="padding:20px;background-color:yellow;font-size:larger">';
        print_r($s);
        echo "</pre>";
    }

    function getOutput() {
        //return 'hi';
        return $this->_output;
    }

    /**
     * Replace the page content.
     * 
     * @param string $content
     * @return string
     */
    function replace_page_content($content) {
        return $this->_output;
    }

    /**
     * Replace the page title used in the <HEAD> tag, called via the wp_title() filter
     * 
     * @param string $title
     * @param string $sep
     * @param string $sep_location
     * @return string
     */
    function replace_page_head_title($title, $sep, $sep_location) {
        return $this->_title;
    }

    /**
     * Replace the page title used in the banner, body and the breadcrumbs.
     * 
     * http://stackoverflow.com/questions/7878187/changing-wp-title-from-inside-my-wordpress-plugin
     * http://codex.wordpress.org/Plugin_API/Filter_Reference/the_title
     * 
     * @param string $content
     * @return string
     */
    function replace_page_title($content) {
        return $this->_title;
    }

}

//
//            function my_the_post_action( $post_object ) {
//	var_dump($post_object);
//        //die();
//}
//add_action( 'the_post', 'my_the_post_action' );
// Load the plugin hooks, etc.
$cakepress_plugin = new CakePressPlugin();


