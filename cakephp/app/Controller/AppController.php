<?php

/**
 * Application level Controller
 *
 * This file is application-wide controller file. You can put all
 * application-wide controller-related methods here.
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @package       app.Controller
 * @since         CakePHP(tm) v 0.2.9
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
App::uses('Controller', 'Controller');

/**
 * Application Controller
 *
 * Add your application-wide methods in the class below, your controllers
 * will inherit them.
 *
 * @package		app.Controller
 * @link		http://book.cakephp.org/2.0/en/controllers.html#the-app-controller
 */
class AppController extends Controller {

    public function beforeFilter() {

        // This is slightly misleading for historical reasons.  In this context, an "admin" can access everything, although that 
        // permission only corresponds to an Editor in Wordpress land.
        $is_admin = true;
        if (defined('JAKE')) {
            $session = $this->Session->read();
            if (!empty($session['Message'])) {
                define('DONOTCACHEPAGE', 1);
            }
            $is_admin = current_user_can('edit_posts');
        }
        $this->set('is_admin', $is_admin);
    }

    /**
     * In the case of a redirect, we exit without returning to Wordpress.  So the Wordpress Quick Cache cannot know not to handle caching the current URL and destination URL.  
     * So clear the destination, and if the current one should be cleared, clear that as well.
     * 
     * @param type $url
     * @param type $status
     * @param type $exit
     * @return boolean
     */
    public function beforeRedirect($url, $status = null, $exit = true) {
        parent::beforeRedirect($url, $status, $exit);

        if (defined('JAKE') && method_exists('quick_cache', 'clear_by_url')) {
            quick_cache::clear_by_url(Router::url($url));

            if (defined('DONOTCACHEPAGE'))
                quick_cache::clear_by_url($this->here);
        }
        return true;
    }

}
