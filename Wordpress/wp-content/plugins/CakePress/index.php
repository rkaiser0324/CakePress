<?php

/*
  Plugin Name: CakePress Plugin
  Version: 1.0.0
  Description: Allows WordPress to host a CakePHP web application.
  Author: Rolf Kaiser
  Author URI: https://github.com/rkaiser0324/CakePress
 */

class CakePressPlugin {

    var $_output = '';

    /**
     * Constructor. Initializes this class.
     */
    function __construct() {

        define('JAKE', 1);
        add_action('init', array($this, 'render'));
    }

    function render() {
        $url = null;
        if (preg_match('/^\/app/', $_SERVER['REQUEST_URI']))
            $url = str_replace('/app', '', $_SERVER['REQUEST_URI']);
        elseif (preg_match('/\?option=com_jake&jrun=/', $_SERVER['REQUEST_URI']))
            $url = str_replace('/?option=com_jake&jrun=', '', $_SERVER['REQUEST_URI']);

        if (!empty($url)) {
            require 'cake_embedded_dispatcher.class.php';
            $cakeDispatcher = new CakeEmbeddedDispatcher();
            $cakeDispatcher->setCakePath(dirname(ABSPATH) . DIRECTORY_SEPARATOR . 'cakephp');
            $cakeDispatcher->setCakeUrlBase('/app');

            // Set the SEF URL to match the rewrite rule in /.htaccess
            $cakeDispatcher->setSefCakeApplicationBase('/app');
            $cakeDispatcher->setComponent('http://localhost:98//index.php?option=com_jake&jrun=$CAKE_ACTION');
            $cakeDispatcher->setCleanOutput(false);
            $cakeDispatcher->setCleanOutputParameter('task', 'clean');
            $cakeDispatcher->setIgnoreParameters(array('option', 'jrun', 'task', 'url'));
            $cakeDispatcher->setRestoreSession(true);

            $arr = $cakeDispatcher->get($url);

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
        }
    }

    // Simple helper function to aid in debugging
    private function debug($s) {
        echo '<pre style="padding:20px;background-color:yellow;font-size:larger">';
        print_r($s);
        echo "</pre>";
    }

    function getOutput() {
        return $this->_output;
    }

}

// Load the plugin hooks, etc.
$cakepress_plugin = new CakePressPlugin();
