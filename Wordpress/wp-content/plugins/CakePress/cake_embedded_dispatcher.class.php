<?php
/**
 * Cake Embedded Dispatcher class file.
 *
 * Class that encapsulates the Cake Dispatcher for integration.  Based on the Jake project at https://github.com/rkaiser0324/jake.
 *
 * @filesource
 * @link			http://dev.sypad.com/projects/jake Jake
 * @package		cif
 * @subpackage	dispatcher
 * @since			1.0
 */

// Definitions

define('CAKEED_REGEX_PATTERN_HEAD', '<head[^>]*>(.*)<\/head>');
define('CAKEED_REGEX_PATTERN_HEAD_META_NAME', '<meta[^>]*name="([^"]*)"[^>]*content="([^"]*)"[^>]*\/?>');
define('CAKEED_REGEX_PATTERN_HEAD_TITLE', '<title[^>]*>(.*)<\/title>');
define('CAKEED_REGEX_PATTERN_HEAD_SCRIPT', '<script[^>]*src=("|\')([^"\']*)("|\')><\/script>');
define('CAKEED_REGEX_PATTERN_HEAD_SCRIPT_BODY', '<script[^>]*type=("|\')([^"\']*)("|\')>([^<]*)<\/script>');
define('CAKEED_REGEX_PATTERN_HEAD_STYLESHEET', '<link[^>]*rel="stylesheet"[^>]*href="([^"]*)"[^>]*?\/?>');
define('CAKEED_REGEX_PATTERN_HEAD_HTTPEQUIV', '<meta[^>]*http-equiv="([^"]*)"[^>]*content="([^"]*)"[^>]*?\/?>');
define('CAKEED_REGEX_PATTERN_HEAD_TAGS', '<([^>]*)>');
define('CAKEED_REGEX_PATTERN_BODY', '<body[^>]*>(.*)<\/body>');

/**
 * Cake Embedded Dispatcher.
 * 
 * @author		Mariano Iglesias - mariano@cricava.com
 * @package		cif
 * @subpackage	dispatcher
 */
class CakeEmbeddedDispatcher
{
	/**#@+
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
	 * CakePHP's base URL.
	 * 
	 * @since 1.0
	 * @var string
	 */
	var $cakeUrlBase = '';
	
	/**
	 * Parameters to add at the end of a transformed CakePHP link.
	 * 
	 * @since 1.0
	 * @var string
	 */
	var $cakeUrlAddParameters = '';
	
	/**
	 * URL to component integrating CakePHP.
	 * 
	 * @since 1.0
	 * @var string
	 */
	var $component = '';
	
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
	 * What to add to the URL to indicate clean output (in the form parameter=value).
	 * 
	 * @since 1.0
	 * @var string
	 */
	var $cleanOutputParameter = '';
	
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
	* Base for SEF URI's
	*
	* @since 1.1
	* @var string
	*/
	var $sefCakeApplicationBase = null;

	/**
	 * Set base path to CakePHP application, using SEF URL's.
	 * 
	 * @param string $base	Base path to Cake application in SEF URI
	 * 
	 * @access public
	 * @since 1.1
	 */
	function setSefCakeApplicationBase($base)
	{
		$this->sefCakeApplicationBase = $base;
	}		
	
	/**
	 * Set path to CakePHP application.
	 * 
	 * @param string $cakePath	Path to application's webroot
	 * 
	 * @access public
	 * @since 1.0
	 */
	function setCakePath($cakePath)
	{
		$this->cakePath = $cakePath;
	}	
	
	/**
	 * Set CakePHP application's URL.
	 * 
	 * @param string $cakeUrlBase	CakePHP application's URL
	 * 
	 * @access public
	 * @since 1.0
	 */
	function setCakeUrlBase($cakeUrlBase)
	{
		$this->cakeUrlBase = $cakeUrlBase;
	}
	
	/**
	 * Parameters to add to changed URLs.
	 * 
	 * @param string $cakeUrlAddParameters	Valid query string (param1=dummy1&param2=dummy2)
	 * 
	 * @access public
	 * @since 1.0
	 */
	function setCakeUrlAddParameters($cakeUrlAddParameters)
	{
		$this->cakeUrlAddParameters = $cakeUrlAddParameters;
	}
	
	/**
	 * Sets the URL to the component so links are maintained within the component.
	 * 
	 * @param string $component	URL to the component (use $CAKE_ACTION where you want the cake action to be referenced).
	 * 
	 * @access public
	 * @since 1.0
	 */
	function setComponent($component)
	{
		$this->component = $component;
	}
	
	/**
	 * Parameters that are property of integrator component (and as such not relevant to CakePHP application)
	 * 
	 * @param array $ignoreParameters	Ignore parameters
	 * 
	 * @access public
	 * @since 1.0
	 */
	function setIgnoreParameters($ignoreParameters)
	{
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
	function setCleanOutput($cleanOutput)
	{
		$this->cleanOutput = $cleanOutput;
	}
	
	/**
	 * What to add to the URL to indicate clean output (for AJAX returns)
	 * 
	 * @param string $parameter	Parameter name
	 * @param string $value	Parameter value ('clean' by default)
	 * 
	 * @access public
	 * @since 1.0 
	 */
	function setCleanOutputParameter($parameter, $value = 'clean')
	{
		$this->cleanOutputParameter = $parameter . '=' . urlencode($value);
	}	
	
	/**
	 * Set if we should close session before running CakePHP, then restore.
	 * 
	 * @param bool $restoreSession	Should session be restored
	 * 
	 * @access public
	 * @since 1.0
	 */
	function setRestoreSession($restoreSession)
	{
		$this->restoreSession = $restoreSession;
	}	
	
	/**
	 * Execute the specified CakePHP action and get its modified contents
	 * 
	 * @param string $url	CakePHP url (e.g: controller/action)
	 * 
	 * @return string	Resulting contents (with modified links and references)
	 * 
	 * @access public
	 * @since 1.0
	 */
	function get($url) {
            $_url = $url;
            $this->_start($url);

            try {
                require_once($this->cakePath . DIRECTORY_SEPARATOR . 'index.php');

                // Commit any $_SESSION changes
                session_write_close();

                $result = $this->_finish($_url);
            } catch (exception $e) {
                // Unusual HTTP response codes, such as 404's, will throw exceptions
                $result = array(
                    'head' => array(),
                    'body' => sprintf("<div style='border:1px solid #999;padding:10px;background:#eee'><p style='color:red;font-weight:bold'>%s</p> 
                        <p>URL: %s</p> 
                        <p>File: %s:%s</p> 
                        <pre>%s</pre></div>", 
                            $e->getMessage(), $_url, $e->getFile(), $e->getLine(), $e->getTraceAsString())
                );
            }
            return $result;
        }
	
	/**
	 * Starts a CakePHP application call
	 * 
	 * @param string $url	CakePHP url (e.g: controller/action)
	 * 
	 * @access private
	 * @since 1.0
	 */
	function _start($url)
	{

		$parts = @parse_url($url);
		// If URL has a query part, set its values as standard $_GET
		
		if ($parts !== false && isset($parts['query']) && !empty($parts['query']))
		{
			$pairs = explode('&', $parts['query']);

			foreach($pairs as $pair)
			{
			 list($name, $value) = explode('=', $pair);
			 
			 $_GET[$name] = $value;
			 $_REQUEST[$name] = $value;
			}
			
			$url = str_replace('?' . $parts['query'], '', $url);
		}
		
		// CakePHP doesn't receive starting slash
		
		if (empty($url))
		    $url = '/';
		if ($url[0] == '/')
		{
			$url = substr($url, 1);
		}
		
		// Let CakePHP pick up the URL
		
		$_GET['url'] = $url;
		
		// Remove unnecessary parameters for CakePHP
		
		if (isset($this->ignoreParameters))
		{
			foreach($this->ignoreParameters as $parameter)
			{
				unset($_REQUEST[$parameter]);
				unset($_GET[$parameter]);
			}
		}
		
		// Backup session
		if ($this->restoreSession && isset($_SESSION))
		{
		    //die('rolf');
			$this->backSession = array (
				'id' => session_id(),
				'module' => session_module_name(),
				'data' => array()
			);
			
			foreach($_SESSION as $parameter => $value)
			{
				$this->backSession['data'][$parameter] = $value;
			}
			
			session_module_name('files');
		}
		
		// Enable output buffering
		
		ob_start();
	}
	
	/**
	 * Ends a CakePHP application call, getting its contents
	 * 
	 * @param string $url	CakePHP url (e.g: controller/action)
	 * 
	 * @return array	Indexed array (head, body) with resulting contents (with modified links and references)
	 * 
	 * @access private
	 * @since 1.0
	 */
	function _finish($url)
	{
		// set a reasonable limit - my default limit, 100000, was causing this to fail silently.  Now it errors loudly.
		$backtrack_limit = ini_get('pcre.backtrack_limit');
		ini_set('pcre.backtrack_limit', 200000);
		
		$pcre_errors = array();
		
		// Restore session
		if ($this->restoreSession && isset($this->backSession) && isset($this->backSession['data']))
		{
			if (isset($_SESSION))
			{
				session_destroy();
			}
			
			session_module_name($this->backSession['module']);
			
			session_id($this->backSession['id']);
			
			session_start();
			
			foreach($this->backSession['data'] as $parameter => $value)
			{
				$_SESSION[$parameter] = $value;
			}
		}
		
		$contents = array('head' => array(), 'body' => '');
		
		// Get the contents from the buffer
		
		$html = ob_get_clean();
		
		// Modify references to CakePHP URLs
		
		if (!empty($this->cakeUrlBase))
		{
			$html = $this->_changeReferences($html);
		}
                
		
		// If no further job is needed (such as for AJAX) give back the output
		
		if ($this->cleanOutput)
		{
			return array('body'=>$html);
		}
		
		$body = '';
		$head = '';

		// Get the head
		
		if (preg_match_all('/' . CAKEED_REGEX_PATTERN_HEAD . '/si', $html, $matches, PREG_PATTERN_ORDER) == 1)
		{
			$head = $matches[1][0];
		}

		$err = $this->pcre_error_decode();
		if ($err != null)
			$pcre_errors[] = 'Jake error in cake_embedded_dispatcher.class.php (head): ' . $err;
		
		
		if (!empty($head))
		{
			// Get elements within head
			
			$result = $this->_parseHead($head);
			
			if (!isset($result['custom']))
			{
				$result['custom'] = array();
			}
			
			$contents['head'] = $result; 
		}
                
		// Get the body
		
		if (preg_match_all('/' . CAKEED_REGEX_PATTERN_BODY . '/si', $html, $matches, PREG_PATTERN_ORDER) == 1)
		{
			$contents['body'] = $matches[1][0];
		}
		
		$err = $this->pcre_error_decode();
		if ($err != null)
			$pcre_errors[] = 'Jake error in cake_embedded_dispatcher.class.php (body): ' . $err;
		
		if (count($pcre_errors) > 0)	
			$contents['body'] = 'Jake errors:<pre>'.print_r($pcre_errors, true).'</pre>';
			
		// reset it back to the original limit

		ini_set('pcre.backtrack_limit', $backtrack_limit);
		
		return $contents;
	}
	
	// from http://us.php.net/manual/en/function.preg-last-error.php
	// returns null if no error, otherwise returns error string
	private function pcre_error_decode() {
		$s = null;
		
		// get rid of garbage warning on WAMP
		if (!defined('PREG_BAD_UTF8_OFFSET_ERROR'))
			define('PREG_BAD_UTF8_OFFSET_ERROR', -99);
		
		if (function_exists('preg_last_error')) {	// only available on PHP 5.2+
			switch (preg_last_error()) {
				
				case PREG_INTERNAL_ERROR:
					$s = "PREG_INTERNAL_ERROR";
					break;
				case PREG_BACKTRACK_LIMIT_ERROR:
					$s = "PREG_BACKTRACK_LIMIT_ERROR";
					break;
				case PREG_RECURSION_LIMIT_ERROR:
					$s = "PREG_RECURSION_LIMIT_ERROR";
					break;
				case PREG_BAD_UTF8_ERROR:
					$s = "PREG_BAD_UTF8_ERROR";
					break;
				case PREG_BAD_UTF8_OFFSET_ERROR:  // the problem is this is left over from some upstream calculation and I don't see how to get rid of it :(	
				case PREG_NO_ERROR:
				default:
					break;
			}
		}
		return $s;
	}
	
	/**
	 * Parse the HEAD portion of contents and get array of elements.
	 * 
	 * @param string $head	HEAD contents.
	 * 
	 * @return array	Associative array of elements
	 * 
	 * @access private
	 * @since 1.0
	 */
	function _parseHead(&$head)
	{
		$result = array();
		
		$backHeadElements = array();
		
		if (preg_match_all('/' . CAKEED_REGEX_PATTERN_HEAD_META_NAME . '/si', $head, $matches, PREG_PATTERN_ORDER) > 0)
		{
			$result['meta'] = array();
			
			for ($i=0, $limiti=count($matches[0]); $i < $limiti; $i++)
			{
				$backHeadElements[] = $matches[0][$i];
				
				$result['meta'][] = array(
					'tag' => $matches[0][$i],
					'name' => $matches[1][$i],
					'content' => $matches[2][$i]
				);
			}
		}
		
		// Document title
		
		if (preg_match_all('/' . CAKEED_REGEX_PATTERN_HEAD_TITLE . '/si', $head, $matches, PREG_PATTERN_ORDER) == 1)
		{
			$backHeadElements[] = $matches[0][0];
			
			$result['title'] = $matches[1][0];
		}
		
		// Script links (references to JS files)
		
		if (preg_match_all('/' . CAKEED_REGEX_PATTERN_HEAD_SCRIPT . '/si', $head, $matches, PREG_PATTERN_ORDER) > 0)
		{
			$result['script'] = array();
			
			for ($i=0, $limiti=count($matches[0]); $i < $limiti; $i++)
			{
				$backHeadElements[] = $matches[0][$i];
				
				$result['script'][] = array(
					'tag' => $matches[0][$i],
					'type' => 'text/javascript',
					'src' => $matches[2][$i]
				);
			}
		}
		
		// Blocks of scripting code
		
		if (preg_match_all('/' . CAKEED_REGEX_PATTERN_HEAD_SCRIPT_BODY . '/si', $head, $matches, PREG_PATTERN_ORDER) > 0)
		{
			if (!isset($result['script']))
			{
				$result['script'] = array();
			}
			
			for ($i=0, $limiti=count($matches[0]); $i < $limiti; $i++)
			{
				$backHeadElements[] = $matches[0][$i];
				
				$result['script'][] = array(
					'tag' => $matches[0][$i],
					'type' => $matches[2][$i],
					'body' => $matches[4][$i]
				);
			}
		}
		
		// Stylesheet links (references to CSS files)
		
		if (preg_match_all('/' . CAKEED_REGEX_PATTERN_HEAD_STYLESHEET . '/si', $head, $matches, PREG_PATTERN_ORDER) > 0)
		{
			$result['stylesheets'] = array();
			
			for ($i=0, $limiti=count($matches[0]); $i < $limiti; $i++)
			{
				$backHeadElements[] = $matches[0][$i];
				
				$result['stylesheets'][] = array(
					'tag' => $matches[0][$i],
					'rel' => 'stylesheet',
					'type' => 'text/css',
					'href' => $matches[1][$i]
				);
			}
		}
		
		// Meta http-equiv tags
		
		if (preg_match_all('/' . CAKEED_REGEX_PATTERN_HEAD_HTTPEQUIV . '/si', $head, $matches, PREG_PATTERN_ORDER) > 0)
		{
			$result['http-equiv'] = array();
			
			for ($i=0, $limiti=count($matches[0]); $i < $limiti; $i++)
			{
				$backHeadElements[] = $matches[0][$i];
				
				$result['http-equiv'][] = array(
					'tag' => $matches[0][$i],
					'http-equiv' => $matches[1][$i],
					'content' => $matches[2][$i]
				);
			}
		}
		
		// Remove elements we already parsed
		
		foreach($backHeadElements as $element)
		{
			$head = str_replace($element, '', $head);
		}
		
		// Get remaining head tags
		
		$head = trim($head);
		
		if (!empty($head) && preg_match_all('/' . CAKEED_REGEX_PATTERN_HEAD_TAGS . '/si', $head, $matches, PREG_PATTERN_ORDER) > 0)
		{
			$result['custom'] = array();
			
			for ($i=0, $limiti=count($matches[0]); $i < $limiti; $i++)
			{
				$tag = '<';
				$tag .= $matches[1][$i];
				$tag .= '>';
				
				$result['custom'][] = $tag;
			}
		}

		return $result;
	}	
	
	/**
	 * Change URL references so they pass through the component
	 * 
	 * @param string $html	HTML body
	 * 
	 * @return string	Modified contents
	 * 
	 * @access private
	 * @since 1.0
	 */
	function _changeReferences($html)
	{
		$result = $html;
		
		// Change relative references to resources
		
		$result = preg_replace('/<link([^>]*?)href="(\/[^"]*+)"([^>]*?)>/i', '<link\\1href="' . $this->cakeUrlBase . '\\2"\\3>', $result);
		$result = preg_replace('/<(img|style|script)([^>]*?)src="(\/[^"]*+)"([^>]*?)>/i', '<\\1\\2src="' . $this->cakeUrlBase . '\\3"\\4>', $result);
		
		// Change relative CakePHP links
		
		if (!empty($this->component))
		{
			$result = preg_replace('/<a([^>]*?)href="(\/[^"]*+)"([^>]*?)>/ie', "\$this->_changeUrl('<a', '$1', '$2', '$3', '>')", $result);
			$result = preg_replace('/<form([^>]*?)action="(\/[^"]*+)"([^>]*?)>/ie', "\$this->_changeUrl('<form', '$1', '$2', '$3', '>', 'action=\"')", $result);
			$result = preg_replace('/Ajax\.Updater\(' . '(\'[^\']*?\')' . '(,\')' . '([^\']*+)' . '(\')/ie', "\$this->_changeUrl('Ajax.Updater($1', '$2', '$3', '', '', '', '\'', true, true)", $result);
		}
		
		return $result;
	}
	
	/**
	 * Modify a URL to pass through the component (called by preg_replace)
	 * 
	 * @param string $start
	 * @param string $p1
	 * @param string $url
	 * @param string $p3
	 * @param string $ending
	 * @param string $startUrl
	 * @param string $endUrl
	 * @param bool $useComponent
	 * @param bool $clean
	 * 
	 * @return string	Modified URL
	 * 
	 * @access private
	 * @since 1.0
	 */
	function _changeUrl($start, $p1, $url, $p3, $ending, $startUrl='href="', $endUrl='"', $useComponent = true, $clean = false)
	{
		if (isset($this->sefCakeApplicationBase))
		{
			$newUrl = $this->sefCakeApplicationBase . $url;
		}
		else
		{
			// Patterns to match URLs that should not pass through the component
			
			$passThroughLinksMatching = array('/\.jpg$/i', '/\.png$/i', '/\.gif$/i', '/\.pdf$/i', '/\.rss$/i');
			
			if ($useComponent && isset($passThroughLinksMatching))
			{
				foreach($passThroughLinksMatching as $element)
				{
					if (preg_match($element, $url))
					{
						$useComponent = false;
						break;
					}
				}
			}
			
			if ($useComponent)
			{
				$newUrl = str_replace('$CAKE_ACTION', urlencode($url), $this->component);
				
				if (!empty($this->cakeUrlAddParameters))
				{
					$newUrl .= '&amp;' . $this->cakeUrlAddParameters;
				}
				
				if ($clean)
				{
					$newUrl .= '&amp;' . $this->cleanOutputParameter;
				}
			}
			else
			{
				$newUrl = $this->cakeUrlBase . $url;
			}
		}
		
		$newUrl = str_replace('&amp;', '&', $newUrl);
		
		$result = $start . $p1;
		$result .= $startUrl . $newUrl . $endUrl;
		$result .= $p3 . $ending;
		
		$result = stripslashes($result);

		return $result;
	}
}