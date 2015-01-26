CakePress
=========

CakePress is a WordPress plugin to integrate a CakePHP web application into Wordpress.  It is based on the [Jake project](https://github.com/rkaiser0324/jake), which does the same for Joomla.  It can run under any of the following:

* Apache 2.2 or 2.4 with mod_php and mod_rewrite enabled
* The above, with nginx as a reverse-proxy
* nginx with PHP-FPM

This project contains sample CakePHP 2.5.2 and WordPress 3.9.1 codebases, as a demonstration.  But all the magic is contained in the following files:

* `WordPress/wp-content/plugins/CakePress/*` - plugin files
* `WordPress/.htaccess` - add a rewrite rule to handle app-specific URLs
* `cakephp/app/webroot/index.php` - conditionally setting variables to support executing the CakePHP app from the plugin
* `cakephp/app/Controller/AppController.php` - (Optional) handle authentication and caching integration between WordPress and CakePHP

## Configuration

* The repo contains both WordPress and CakePHP codebases in the proper locations.  The CakePHP directory should be named "cakephp" and be a sibling to the WordPress one, i.e., if WordPress is at `/path/to/www/Wordpress` then CakePHP is at `/path/to/www/cakephp`.  

After you've done the following, bounced your web servers, and set up Wordpress (if needed) at `http://wordpressserver/`, log into the WordPress dashboard and enable permalinks (anything other than the default should work).

### Apache with mod_php

* Add the following directives to the WordPress VirtualHost:

```
DocumentRoot /path/to/WordPress
# List the asset folders used by Cake
Alias /js "/path/to/www/cakephp/app/webroot/js"
Alias /css "/path/to/www/cakephp/app/webroot/css"
Alias /img "/path/to/www/cakephp/app/webroot/img"
RewriteEngine on                # if needed
```

* Add the following rewrite rule to `/Wordpress/.htaccess`, modifying as needed for your app:

```
# Add URLs that will be managed by CakePress
RewriteRule ^(controller1|controller2|controller3)              index.php?post_type=page&pagename=cakepress [L]
```

### nginx with PHP-FPM

* Add the following to your nginx configuration, modifying as needed:
```
# Upstream to abstract backend connection(s) for php
upstream php {
        #server unix:/var/run/php5-fpm.sock;
        server 127.0.0.1:9000;
} 
server {
        listen   80; 
        root /path/to/Wordpress;
        index index.php index.html index.htm;
        server_name wordpressserver;        # etc.
        # List the asset folders used by CakePress
        location ~ ^/(css|js|img)/ {
            root /path/to/cakephp/app/webroot;
        }
	# Add URLs that will be managed by CakePress
        rewrite ^/(controller1|controller2|controller3) /index.php?post_type=page&pagename=cakepress last;
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

### WordPress Plugins

If used, the following WordPress plugins require additional configuration:

* [WP Super Cache](https://wordpress.org/plugins/wp-super-cache/) - On the Advanced Settings, make sure that you add the string `app` to the list of URL strings to leave uncached.
* [Autoptimize](https://wordpress.org/plugins/autoptimize/) - Add `app` to "Exclude scripts from Autoptimize" and "Exclude CSS from Autoptimize".  Due to the nonstandard location of these assets, the plugin is not able to follow the URL aliases to them, so the CakePHP assets cannot be minified by this plugin.  You could of course minify them separately in the CakePHP app.


## Usage

You can access your CakePHP app at `http://wordpressserver/app/`.  You can then do stuff like this in your CakePHP controller:
```php

    function beforeFilter() {
        // Check for the constant "JAKE" (named that for historical reasons)
        if (defined('JAKE'))  
        {
            // Running inside CakePress
            $this->set('wordpress_user', wp_get_current_user());
        }
        else
        {
            // Standalone CakePHP app, not inside CakePress
        }             
    }
```

See `cakephp/app/Controller/AppController.php` for more details on how authentication from WordPress to Cake can be handled, as well as caching integration.
 
## Credits

This project was based upon the [Jake project](https://github.com/rkaiser0324/jake) which itself was originally developed in 2007 by [Mariano Iglesias](https://github.com/mariano) and [Max](http://www.gigapromoters.com/blog/). Further credits go to Dr. Tarique Sani for his insightful ideas.  Jake is now maintained by [Rolf Kaiser](http://blog.echothis.com).
