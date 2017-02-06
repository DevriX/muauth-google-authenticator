<?php
/*
Plugin Name: MuAuth Google Authenticator
Plugin URI: https://github.com/elhardoum/muauth-google-authenticator
Description: Two-Factor Authentication addon for multisite authentication plugin with <a href="//wordpress.org/plugins/google-authenticator">Google Authenticator</a> WordPress plugin
Author: Samuel Elh
Version: 0.1
Author URI: https://samelh.com
*/

defined('ABSPATH') || exit('Direct access not allowed.' . PHP_EOL);

class muauthGA
{
    /** Class instance **/
    protected static $instance = null;

    public $ga;

    /** Get Class instance **/
    public static function instance()
    {
        return null == self::$instance ? new self : self::$instance;
    }

    function __construct()
    {
        if ( class_exists('GoogleAuthenticator') )
            $this->ga = new GoogleAuthenticator;
    }

    public static function init()
    {
        $ins = self::instance();

        if ( !is_a($ins->ga, 'GoogleAuthenticator') )
            return $ins::GoogleAuthMissing();

        if ( !method_exists($ins->ga, 'check_otp') )
            return $ins::GoogleAuthMissing();

        add_action('plugins_loaded', array($ins, 'ready'), 999);
    }

    public static function ready()
    {
        $ins = self::instance();

        add_action('muauth_login_before_remember', array($ins, 'printOTPField'));
        add_action('muauth_login_form_before_remember', array($ins, 'printOTPField'));
        add_action('muauth_validate_login', array($ins, 'checkOTP'));
        add_filter('muauth_parse_template_errors_exclude_codes', array($ins, 'appendTOPErrKey'));
    }

    public static function GoogleAuthMissing()
    {
        error_log("Google Authenticator plugin is not installed or it's child methods are no longer available");
    }

    public static function printOTPField()
    {
        $ins = self::instance();

        ob_start();
        call_user_func(array($ins->ga, 'loginform'));
        $field = ob_get_clean();

        // append appropriate CSS classes
        $field = preg_replace_callback('/<p(.*?)?>/si', function($m){            
            $p = array_shift($m);

            return sprintf(
                '<p class="form-section%s">',
                muauth_has_errors('googleotp') ? ' has-errors' : ''
            );
        }, $field);

        // append inline error messages
        if ( muauth_has_errors('googleotp') ) {
            $field = preg_replace_callback('/<\/p>/si', function($m){            
                $p = array_shift($m);

                ob_start();
                muauth_print_error( 'googleotp' );
                $err = ob_get_clean();

                return $err . $p;

            }, $field);
        }

        // append tab index
        $field = preg_replace_callback('/<input(.*?)?name=["\']googleotp["\']/si', function($m){
            return sprintf(
                '%s tabindex="%d"',
                array_shift($m),
                muauth_tabindex(1)
            );
        }, $field);

        print($field);
    }

    public static function checkOTP($user)
    {
        $ins = self::instance();

        $check = call_user_func_array(array($ins->ga, 'check_otp'), array(
            $user,
            $_POST['login'],
            $_POST['password']
        ));

        if ( is_wp_error($check) ) {
            $errors = $check->errors;

            foreach ( $errors as $error ) {
                if ( is_array($error) ) {
                    foreach ( $error as $_error ) {
                        muauth_add_error('googleotp', $_error, 'error');
                    }
                } else {
                    muauth_add_error('googleotp', $error, 'error');
                }
            }

        }
    }

    public static function appendTOPErrKey($k)
    {
        return array_merge($k, array('googleotp'));
    }
}

muauthGA::init();