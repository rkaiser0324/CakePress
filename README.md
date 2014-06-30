CakePress
=========

CakePress is a Wordpress plugin to integrate a CakePHP web application into Wordpress.  It is based on the [Jake project](https://github.com/rkaiser0324/jake), which does the same for Joomla.

This project contains sample CakePHP 2.5.1 and WordPress 3.9.1 codebases, as a demonstration.  But all the magic is contained in the following files:

* `WordPress/wp-content/plugins/CakePress/*`
* `cakephp/app/webroot/index.php`

It requires Apache 2.2.2, with mod_rewrite enabled.

## Configuration

* The repo contains both WordPress and CakePHP codebases in the proper locations.  The CakePHP directory should be named "cakephp" and be a sibling to the WordPress one, i.e., your WORDPRESS_ROOT is `/path/to/www/WordPress` and your CAKEPHP_ROOT is `/path/to/www/cakephp`.
* Add the following Apache Alias, used for delivering existing files from under `CAKEPHP_ROOT/app/webroot`, to the WordPress VirtualHost. This should point to the `app/webroot` directory of the CakePHP app.

```
Alias /app/webroot "CAKEPHP_ROOT/app/webroot"
<Directory "CAKEPHP_ROOT/app/webroot">
    AllowOverride All
    Order allow,deny
    Allow from all
</Directory>
```

* Enable URL rewriting on both your WORDPRESS_ROOT and CAKEPHP_ROOT
* Bounce Apache

### Plugins
If used, the following WordPress plugins require additional configuration:

* [WP Super Cache](https://wordpress.org/plugins/wp-super-cache/) - On the Advanced Settings, make sure that you add the string `app` to the list of URL strings to leave uncached.
* [Autoptimize](https://wordpress.org/plugins/autoptimize/) - Add `app` to "Exclude scripts from Autoptimize" and "Exclude CSS from Autoptimize".  Due to the nonstandard location of these assets, the plugin is not able to follow the URL aliases to them, so the CakePHP assets cannot be minified by this plugin.  You could of course minify them separately in the CakePHP app.


## Usage

After configuration, your CakePHP app is available at `http://wordpressserver/app/`.  You can do stuff like this in your CakePHP controller:
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

## Credits

This project was based upon the [Jake project](https://github.com/rkaiser0324/jake) which itself was originally developed in 2007 by [Mariano Iglesias](https://github.com/mariano) and [Max](http://www.gigapromoters.com/blog/). Further credits go to Dr. Tarique Sani for his insightful ideas.  Jake is now maintained by [Rolf Kaiser](http://blog.echothis.com).
