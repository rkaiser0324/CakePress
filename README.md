CakePress
=========

CakePress is a Wordpress 3.6 plugin to integrate a CakePHP 2.3.8 web application into Wordpress.  It is based on the [Jake project](https://github.com/rkaiser0324/jake), which does the same for Joomla.

## Configuration

- The repo contains both WordPress and CakePHP codebases in the proper locations.  The CakePHP directory should be named "cakephp" and be a sibling to the WordPress one, i.e., your WORDPRESS_ROOT is `/path/to/www/WordPress` and your CAKEPHP_ROOT is `/path/to/www/cakephp`.
- Add the following Apache Alias, used for delivering existing files from under `CAKEPHP_ROOT/app/webroot`, to the WordPress VirtualHost. This should point to the `app/webroot` directory of the CakePHP app.

```
Alias /webapp "CAKEPHP_ROOT/app/webroot"
<Directory "CAKEPHP_ROOT/app/webroot">
    AllowOverride All
    Order allow,deny
    Allow from all
</Directory>
```
- Enable URL rewriting on both your WORDPRESS_ROOT and CAKEPHP_ROOT
- Bounce Apache


## Usage

After configuration, your CakePHP app is available as-is at `http://wordpressserver/app/`, so you can do stuff like this in your CakePHP app:
```php
// Check for the constant "JAKE" (named that for historical reasons)
if (defined('JAKE'))  
{
    // do something involving WordPress
    echo 'Running inside CakePress';
}
else
    echo "Standalone CakePHP app, not inside CakePress";
```

## Credits

This project was based upon the [Jake project](https://github.com/rkaiser0324/jake) which itself was originally developed in 2007 by [Mariano Iglesias](https://github.com/mariano) and [Max](http://www.gigapromoters.com/blog/). Further credits go to Dr. Tarique Sani for his insightful ideas.  Jake is now maintained by [Rolf Kaiser](http://blog.echothis.com).
