<?php
/*
 * Template Name: CakePress
 * 
 * This is a minimal template to contain the CakePress body content in its entirety.  It overrides whatever template might be otherwise set for the CakePress page.
 */
if (!CAKEPRESS_CLEAN_OUTPUT)
    get_header();
if (have_posts()) {
    while (have_posts()) {
        the_post();
        remove_filter('the_content', 'wpautop');
        the_content();
    }
}
if (!CAKEPRESS_CLEAN_OUTPUT)
    get_footer();
