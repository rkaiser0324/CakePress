<?php

/**
 * Cake Embedded Dispatcher.
 * 
 * @author		Mariano Iglesias - mariano@cricava.com, no maintained at https://github.com/rkaiser0324/CakePress
 * @package		cif
 * @subpackage	dispatcher
 */
class CakeEmbeddedDispatcher {
    /*     * #@+
     * @access private
     */

    /**
     * Path to CakePHP's webroot.
     * 
     * @since 1.0
     * @var string
     */
    var $cakePath;

    /**
     * Which parameters received via $_GET are not for CakePHP to take
     *
     * @since 1.0
     * @var array
     */
    var $ignoreParameters;

    /**
     * Tells if contents should only have its references changed (true), or also parsed (false).
     * 
     * @since 1.0
     * @var bool
     */
    var $cleanOutput = false;

    /**
     * Set to true if session should be closed before running CakePHP, then restored.
     * 
     * @since 1.0
     * @var bool
     */
    var $restoreSession = true;

    /**
     * Backup session data (variables stored in session, session module, and session id)
     *
     * @since 1.0
     * @var array
     */
    var $backSession;

    /**
     * Default content array
     * 
     * @var array           
     */
    var $_defaultContentArray = array(
        'http_status_code' => 200,
        'head' => array(
            'meta' => array(),
            'title' => '',
            'script' => array(),
            'stylesheets' => array(),
            'custom' => array()
        ),
        'body' => ''    // The innerHTML of the body tag
    );

    /**
     * Set path to CakePHP application.
     * 
     * @param string $cakePath	Path to application's webroot
     * 
     * @access public
     * @since 1.0
     */
    function setCakePath($cakePath) {
        $this->cakePath = $cakePath;
    }

    /**
     * Parameters that are property of integrator component (and as such not relevant to CakePHP application)
     * 
     * @param array $ignoreParameters	Ignore parameters
     * 
     * @access public
     * @since 1.0
     */
    function setIgnoreParameters($ignoreParameters) {
        $this->ignoreParameters = $ignoreParameters;
    }

    /**
     * Specify if output should have only references changed, and not parsed (for AJAX returns)
     * 
     * @param bool $cleanOutput	true for clean output, false otherwise
     * 
     * @access public
     * @since 1.0 
     */
    function setCleanOutput($cleanOutput) {
        $this->cleanOutput = $cleanOutput;
    }

    /**
     * Set if we should close session before running CakePHP, then restore.
     * 
     * @param bool $restoreSession	Should session be restored
     * 
     * @access public
     * @since 1.0
     */
    function setRestoreSession($restoreSession) {
        $this->restoreSession = $restoreSession;
    }

    /**
     * Execute the specified CakePHP action and get its modified contents
     * 
     * @param string $url	CakePHP url (e.g: controller/action)
     * 
     * @return array $result	Resulting contents (with modified links and references).  See _finish() for the format.
     * 
     * @access public
     * @since 1.0
     */
    function get($url) {
        $_url = $url;
        $this->_start($_url);

        try {
            // Remove the slashes that get added here: See http://mantis.digipowers.com/view.php?id=1974
            $_POST = $this->_strip_deep2($_POST);
            // Enable output buffering
            ob_start();
            require_once($this->cakePath . DIRECTORY_SEPARATOR . 'index.php');
            $html = ob_get_clean();

            // Commit any $_SESSION changes
            session_write_close();

            $result = $this->_finish($html);
        } catch (exception $e) {
            // Unusual HTTP response codes, such as hard 404's, will throw exceptions.  Show the details if we're in WP_DEBUG
            $result = $this->_defaultContentArray;
            if (WP_DEBUG) {
                $result['body'] = sprintf("<div style='border:1px solid #999;padding:10px;background:#eee'><p style='color:red;font-weight:bold'>%s</p> 
                        <p>URL: %s</p> 
                        <p>File: %s:%s</p> 
                        <pre>%s</pre></div>", $e->getMessage(), $_url, $e->getFile(), $e->getLine(), $e->getTraceAsString());
            } else {
                // http://wordpress.stackexchange.com/questions/73738/how-do-i-programmatically-generate-a-404
                add_action('wp', function () {
                    global $wp_query;
                    $wp_query->set_404();
                    header("HTTP/1.0 404 Not Found");
                    // Didn't see this way documented but it seems to do the trick
                    $wp_query->post_count = 0;
                });
            }
        }
        return $result;
    }

    /**
     * Fast function to strip the slashes from the $_POST array.  Too bad strip_json() mangles Unicode.
     * http://php.net/manual/en/function.get-magic-quotes-gpc.php#109859
     * 
     * @param array             $d
     * @return array
     */
    private function _strip_deep2($d) {
        $d = is_array($d) ? array_map(array($this, '_strip_deep2'), $d) : stripslashes($d);
        return $d;
    }

    /**
     * Starts a CakePHP application call
     * 
     * @param string $url	CakePHP url (e.g: controller/action)
     * 
     * @access private
     * @since 1.0
     */
    function _start($url) {

        $parts = @parse_url($url);
        // If URL has a query part, set its values as standard $_GET

        if ($parts !== false && isset($parts['query']) && !empty($parts['query'])) {
            $pairs = explode('&', $parts['query']);


            foreach ($pairs as $pair) {
                if (strpos($pair, '=')) {
                    list($name, $value) = explode('=', $pair);

                    $_GET[$name] = $value;
                    $_REQUEST[$name] = $value;
                }
            }
        }

        // Remove unnecessary parameters for CakePHP
        if (isset($this->ignoreParameters)) {
            foreach ($this->ignoreParameters as $parameter) {
                unset($_REQUEST[$parameter]);
                unset($_GET[$parameter]);
            }
        }

        // Backup session
        if ($this->restoreSession && isset($_SESSION)) {
            $this->backSession = array(
                'id' => session_id(),
                'module' => session_module_name(),
                'data' => array()
            );

            foreach ($_SESSION as $parameter => $value) {
                $this->backSession['data'][$parameter] = $value;
            }

            session_module_name('files');
        }
    }

    /**
     * Ends a CakePHP application call, getting its contents into an array.
     * 
     * @param string $html	
     * 
     * @return array $contents	Array formatted as _defaultContentArray, with modified links and references
     */
    private function _finish($html) {
        global $cakepress_response_stats;
        
        $contents = $this->_defaultContentArray;

        $contents['http_status_code'] = $cakepress_response_stats['status_code'];

        if ($this->cleanOutput) {
            $contents['body'] = $html;
        } else {
            // Load the DOM_Query class, first looking in the usual Composer locations (for either standard and Bedrock layouts).  
            // Sadly the composer autoloader doesn't work here.
            $loader_path = dirname(__FILE__) . '/vendor/rkaiser0324/dom-query/vendor/Loader.php';
            if (!file_exists($loader_path))
                $loader_path = ABSPATH . '/../../vendor/rkaiser0324/dom-query/vendor/Loader.php';
            $loader_path = apply_filters('cakepress_dom_query_loader_path', $loader_path);
            if (!file_exists($loader_path))
                throw new exception("Cannot load rkaiser0324/dom-query/vendor/Loader.php.  Ensure the cakepress_dom_query_loader_path filter is set properly.");
                
            require $loader_path;
            \Loader::init(array(dirname($loader_path)), false);

            $H = new \PowerTools\DOM_Query($html);

            // Get elements within head
            $contents['head'] = $this->_parseHead($H);

            // Get the body
            // This avoids mangling HTML by auto-closing <div>s - a big no-no 
            $body = $H->select('body');
            $body_inner_html = empty($body->nodes[0]) ? '' : preg_replace('@<body(.*)>(.+)</body>@msiU', '$2', $body->DOM->saveHTML($body->nodes[0]));
            $contents['body'] = $body_inner_html;
            if (WP_DEBUG)
                $contents['body'] = sprintf('<!-- CakePress start -->%s<!-- CakePress end -->', $contents['body']);
        }
        return $contents;
    }

    /**
     * Parse the HEAD portion of contents and get array of elements, sorted by type (meta, title, script, stylesheets, and custom).
     * 
     * @param \PowerTools\DOM_Query     $H	DOM_Query object
     * 
     * @return array	Associative array of elements by type
     */
    private function _parseHead($H) {
        $result = array(
            'meta' => array(),
            'title' => '',
            'script' => array(),
            'stylesheets' => array(),
            'custom' => array()
        );

        $H->select('head > meta')->each(function($index, $el) use (&$result) {
            $node = $el->nodes[0];
            $result['meta'][] = $node->ownerDocument->saveXML($node);
        });

        $H->select('head > title')->each(function($index, $el) use (&$result) {
            $node = $el->nodes[0];
            $result['title'] = $node->textContent;
        });

        $H->select('head > script')->each(function($index, $el) use (&$result) {
            $node = $el->nodes[0];
            if ($node->hasAttribute('src')) {
                $result['script'][] = array(
                    'tag' => $node->ownerDocument->saveXML($node),
                    'type' => $node->hasAttribute('type') ? $node->getAttribute('type') : 'text/javascript',
                    'src' => $node->getAttribute('src')
                );
            } else {
                // http://stackoverflow.com/questions/6399924/getting-nodes-text-in-php-dom
                foreach ($node->childNodes as $child) {
                    if ($child->nodeType == XML_TEXT_NODE) {
                        $result['script'][] = array(
                            'tag' => $node->ownerDocument->saveXML($node),
                            'type' => $node->hasAttribute('type') ? $node->getAttribute('type') : 'text/javascript',
                            'body' => $child->textContent
                        );
                        break;
                    }
                }
            }
        });

        $H->select('head > link')->each(function($index, $el) use (&$result) {
            $node = $el->nodes[0];
            if ($node->hasAttribute('rel') && $node->getAttribute('rel') == 'stylesheet' && $node->hasAttribute('href')) {
                $result['stylesheets'][] = array(
                    'tag' => $node->ownerDocument->saveXML($node),
                    'rel' => 'stylesheet',
                    'type' => 'text/css',
                    'href' => $node->getAttribute('href')
                );
            } else {
                // Some other tag so add it to the custom array
                $result['custom'][] = $node->ownerDocument->saveXML($node);
            }
        });

        // Notably, remove() doesn't seem to work, so do it this way instead
        $H->select('head > *')->each(function($index, $el) use (&$result) {
            $node = $el->nodes[0];
            if (!in_array($node->tagName, array('meta', 'title', 'script', 'link')))
                $result['custom'][] = $node->ownerDocument->saveXML($node);
        });

        // For some reason the ordering gets reversed in the above, so fix it for the ones that matter
        $result['script'] = array_reverse($result['script']);
        $result['stylesheets'] = array_reverse($result['stylesheets']);
        $result['custom'] = array_reverse($result['custom']);

        return $result;
    }

}
