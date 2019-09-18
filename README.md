CakePress
=========

CakePress is a WordPress plugin to integrate a CakePHP web application into Wordpress, either single-site or multisite.  It was originally based on the [Jake project](https://github.com/rkaiser0324/jake), which was a similar integration into Joomla.  It has been tested under the following architectures:

* Apache 2.2 or 2.4 with mod_php and mod_rewrite enabled, optionally with nginx 1.6.2 as a reverse-proxy
* nginx 1.6.2 with PHP-FPM

It is known to work with WordPress 4.8.2 and CakePHP 2.9.1.


## Installation

### Standard WordPress layout

By default, the CakePHP directory should be named `cakephp` and is typically a sibling to the WordPress one, i.e., if WordPress is at `/path/to/www/Wordpress` then CakePHP is at `/path/to/www/cakephp`.  

Then install the dependencies via `composer install` in the CakePress plugin directory.

### Bedrock layout

If you're using the [Bedrock](https://roots.io/bedrock/) framework, the CakePHP directory should be a sibling to `/web`.  

Use Composer to install the plugin and its one dependency, `rkaiser0324/dom-query`. 


## Server Configuration

The following assumes the WordPress installation lives at `http://wordpressserver`:

### Apache with mod_php

Add the following directives to the WordPress VirtualHost:

```
ServerName	wordpressserver
DocumentRoot	/path/to/www/WordPress
# List the standard asset folders used by CakePHP.  
# Would need modification if your app uses other paths as well.
AliasMatch	^/(wp/)?(js|css|img)/(.*)$	/path/to/www/cakephp/app/webroot/$2/$3
# And, if not otherwise set
RewriteEngine 	on
```

### nginx with PHP-FPM

Add the following to your nginx configuration, modifying as needed:

```
# Upstream to abstract backend connection(s) for php
upstream php {
        #server unix:/var/run/php5-fpm.sock;
        server 127.0.0.1:9000;
} 
server {
        listen   80; 
        root /path/to/www/Wordpress;
        index index.php index.html index.htm;
        server_name wordpressserver;        
        # List the standard asset folders used by Cake.  
        # Would need modification if your app uses other paths as well.
        location ~ ^/(js|css|img)/ {
            root /path/to/www/cakephp/app/webroot;
        }
	# Add URLs that will be managed by CakePress
        rewrite ^/(controller1|controller2|controller3) /index.php?pagename=cakepress last;
        location / {
            try_files $uri $uri/ /index.php?$args;
        }
        location ~ \.php$ {
            try_files $uri =404;
            include /etc/nginx/fastcgi_params;
            fastcgi_pass    127.0.0.1:9000;
            fastcgi_index   index.php;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        }
        location ~ /\.ht {
                deny all;
        }
}
```


## WordPress and CakePHP Configuration

1.  Login into your WordPress dashboard at `http://wordpressserver/wp-admin`
2.  Create a page at `/cakepress` with the page contents of `[cakepress]`
3.  Go to Settings->Permalinks and enable permalinks 
4.  In your theme `functions.php` set the filters below so the CakePress plugin knows which URLs to handle
5.  Overwrite the contents of `cakephp/app/webroot/index.php` with the file found in this plugin

### Third-Party WordPress Plugins

Asset minification presents a problem due to the nonstandard location of Javascript and CSS assets served by the CakePHP app (i.e., the aliased location in the URL doesn't match the typical file path under the WordPress webroot).  If you use a asset minification plugin like [Autoptimize](https://wordpress.org/plugins/autoptimize/) or [BWP Minify](https://github.com/OddOneOut/bwp-minify), you will need to either:
* Exclude these assets from minification via the plugin configuration, or
* Minify these assets separately in the CakePHP app.

If you use a WordPress page caching plugin like [WP Super Cache](https://wordpress.org/plugins/wp-super-cache/) or the now-defunct [Quick Cache](https://github.com/joeldbirch/Quick-Cache) then you probably want to exclude CakePress URLs from being cached, due to problems with redirection.  See `cakephp/app/Controller/AppController.php` for more details.  For this reason, we recommend you use server proxy caching instead of plugin-based page caching, as it doesn't have this limitation.


## Usage

### CakePHP

See `cakephp/app/Controller/AppController.php` for sample code showing how user authentication from WordPress to CakePHP can be handled, as well as integration with page-caching plugins like Quick Cache, if you are using those.

### WordPress

A number of filters are available to control the CakePress behavior.

```php
/**
 * Set the path to rkaiser0324/dom-query/vendor/Loader.php, if it's not 
 * located in the usual locations for either of the following:
 *  1. WordPress-standard layout - in ABSPATH/wp-content/plugins/CakePress/vendor/*
 *  2. Bedrock layout - in /vendor/*
 *      
 * @param string $path           Path to Loader.php 
 */
add_filter('cakepress_dom_query_loader_path', function($path) {
    return $path;
}, 10, 1);

/**
* Set the URL pattern for CakePress to handle, excluding initial slash.  See add_rewrite_rule() at 
* https://codex.wordpress.org/Rewrite_API/add_rewrite_rule for examples.  After changing this you 
* must flush the rewrite rules, e.g., by navigating to Settings->Permalinks.  This must be set for 
* CakePress to function.
*     
* @param string $regex_excluding_initial_slash           Default '' 
* @return string 
*/
add_filter('cakepress_url_regex', function($regex_excluding_initial_slash) {
    // This must return a nonempty string for CakePress to function.
    // $regex_excluding_initial_slash = "^(controller1|controller2)";
    return $regex_excluding_initial_slash;
}, 10, 1);

/**
* Set the path to the CakePHP directory containing your application, excluding trailing slash.  This
* doesn't relate to where the actual CakePHP library is located (e.g., that might be in /vendor/, for
* example, if you are deploying it via composer).
*     
* @param string $path           Default to be a sibling of the WordPress directory (i.e., ABSPATH)
* @return string
*/
add_filter('cakepress_cakephp_path', function($path) {
    return $path;
}, 10, 1);

/**
* Set whether access is allowed to the current URL.  For example you may wish to limit access to 
* certain CakePress URLs, based on the WordPress user role.  By default, any URL not containing 
* the string "wc-ajax=get_refreshed_fragments" is allowed; this is put in place to avoid unintended
* behavior if WooCommerce is installed.
*
* @param bool $is_allowed     Default true, unless the URL contains "wc-ajax=get_refreshed_fragments"
* @param string $url  
* @return bool $is_allowed         
*/
add_filter('cakepress_check_acl', function($is_allowed, $url) {
    // This would limit access to /controller1/adminaction to WordPress Editors only
    // if (!current_user_can('edit_posts')) {
    //    if (preg_match('@/controller1/adminaction)@', $url)) {
    //        $is_allowed = false;
    //    }
    // }
   return $is_allowed;
}, 10, 2);

/**
* Set whether the output should be rendered "clean", i.e., without the WordPress theme header and footer.
* This would be done for AJAX responses, for example.
*
* @param bool $is_clean                     Default false
* @param string $url   
* @return bool          
*/
add_filter('cakepress_clean_output', function($is_clean, $url) {
   // if (preg_match('@layout=ajax@', $url))
   //    $is_clean = true;
   return $is_clean;
}, 10, 2);

/**
* Set whether shortcodes in the Cake body should be executed.
*
* @param bool $execute_shortcodes           Default true
* @param string $url   
* @return bool        
*/
add_filter('cakepress_execute_shortcodes', function($execute_shortcodes, $url) {
   // Do not execute shortcodes on these pages
   // if (preg_match('@^/controller/action@', $url))
   //    $execute_shortcodes = false;
   return $execute_shortcodes;
}, 10, 2);

/**
* Modify the parsed data returned from the CakePHP application for the URL, e.g., the <body> string or 
* HTTP response code.  
*
* @param array $contents           
* @param string $url  
* @return array        
*/
add_filter('cakepress_filter_contents_array', function($contents, $url) {
   // See CakeEmbeddedDispatcher->$_defaultContentArray for the format of $contents
   // $contents['body'] .= '<p>This is appended to the body HTML</p>';
   return $contents;
}, 10, 2);
```
 
## Credits

This project was based upon the [Jake project](https://github.com/rkaiser0324/jake) which itself was originally developed in 2007 by [Mariano Iglesias](https://github.com/mariano) and [Max](http://www.gigapromoters.com/blog/). Further credits go to Dr. Tarique Sani for his insightful ideas.  Jake is now maintained by [Rolf Kaiser](http://blog.echothis.com).
