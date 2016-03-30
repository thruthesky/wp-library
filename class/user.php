<?php
/**
 * @file class.php
 */
/**
 * Includes.
 */
include_once ABSPATH . '/wp-includes/pluggable.php';
include_once ABSPATH . '/wp-includes/user.php';
include_once ABSPATH . '/wp-admin/includes/user.php';
/**
 * Class WP_INCLUDE_USER
 */
class user extends WP_User {


    static $properties = [
        'nickname',
        'description',
        'user_description',
        'first_name',
        'user_firstname',
        'last_name',
        'user_lastname',
        'user_login',
        'user_pass',
        'user_nicename',
        'user_email',
        'user_url',
        'user_registered',
        'user_activation_key',
        'user_status',
        'display_name',
        'spam',
        'deleted',
    ];

    /**
     * WP_LIBRARY_USER constructor.
     * @param null $uid - it can be one of the following value.
     *      - ID
     *      - user_email
     *      - user_login
     *      - empty - the current logged in user.
     */
    public function __construct( $uid = null )
    {
        if ( $uid ) {
            if ( is_numeric($uid) ) parent::__construct( $uid );
            else if ( is_email( $uid ) ) parent::__construct( get_user_by( 'email', $uid ) );
            else parent::__construct( 0, $uid);
        }
        else {
            parent::__construct( $this->currentUser()->ID );
        }
    }


    public function currentUser() {
        return wp_get_current_user();
    }






    /**
     *
     * @return bool - same as is_user_logged_in();
     * @code
     *      user()->login();
     * @endcode
     */
    public function login() {
        return is_user_logged_in();
    }

    public function admin()
    {
        return user_can( $this->user, 'manage_options' );
    }

    /**
     * Creates a user
     * @param $meta
     *
     * @return int|WP_Error The newly created user's ID or a WP_Error object if the user could not
     *                      be created.
     */
    public function create( $meta ) {

        $id = wp_insert_user($meta);
        if ( is_wp_error( $id ) ) return $id;
        $user = user( $id );
        $keys = array_keys( $meta );
        $keys_diff = array_diff( $keys, self::$properties );
        foreach ( $keys_diff as $key ) {
            $user->set( $key, $meta[$key] );
        }
        return $id;
    }

    /**
     * It stores the $meta_value in usermeta.
     *
     * @note
     *      - It uses wp_update_user() for updating the WP_User properties.
     *      - It uses update_user_meta() for updating non WP_User properties.
     *
     * @note update_user_meta() 는 usermeta 테이블 정보를 업데이트한다. 하지만 user 테이블은 업데이트를 하지 않는다.
     * 따라서 WP_User property 의 경우에는 wp_update_user() 를 통해서 업데이트를 한다.
     *
     * @note wp_update_user() 는 데이터베이스를 업데이트한다. __set() 은 메모리만 업데이트한다. 따라서 이 둘을 동기화 시켜야 한다.
     * @param $meta_key
     * @param $meta_value
     * @return bool|int
     *
     * @see test.php
     *
     */
    public function set( $meta_key, $meta_value ) {
        if ( in_array( $meta_key, self::$properties ) ) {
            $userdata = array( 'ID' => $this->ID, $meta_key => $meta_value );
            $id = wp_update_user( $userdata );
            if ( is_wp_error( $id ) ) return $id;
            parent::__set($meta_key, $meta_value);
            return $id;
        }
        else {
            //echo "user::set($meta_key) not property.\n";
            return update_user_meta( $this->ID, $meta_key, $meta_value );
        }
    }

    public function __set( $key, $value )
    {
        return self::set( $key, $value );
    }


    /*
        public function get( $key ) {

            return $this->__get( $key );
        }
    */

    /**
     * wrapper of wp_delete_user()
     *
     *
     * @param null $reassign
     *
     * @return bool
     */
    public function delete( $reassign = null ) {
        if ( $this->ID ) {
            return wp_delete_user( $this->ID, $reassign );
        }
        return false;
    }


    /**
     *
     * @todo UNIT-TEST
     */
    public function count() {
        wp_send_json( count_users() );
    }


    /**
     * Register a user with $_GET, $POST input.
     *
     * @todo UNIT-TEST
     * @todo theme developer may use different registratoin form. Need to provide a way to adapt form variation.
     */
    public function registerSubmit() {
        if ( ! in('user_login') ) wp_send_json(json_error(-5, 'Input username') );
        if ( ! in('user_pass') ) wp_send_json(json_error(-6, 'Input password') );
        if ( user( in('user_login') )->exists() ) wp_send_json(json_error(-10, 'Username already exists.'));

        if ( ! in('user_email') ) wp_send_json(json_error(-7, 'Input email') );
        if ( user( in('user_email') )->exists() ) wp_send_json(json_error(-20, 'email already exists.'));

        if ( ! in('name') ) wp_send_json(json_error(-8, 'Input name') );
        if ( ! in('mobile') ) wp_send_json(json_error(-9, 'Input mobile number') );


        $id = user()->create(
            array(
                'user_login' => in('user_login'),
                'user_pass' => in('user_pass'),
                'user_email' => in('user_email'),
                'name' => in('name'),
                'mobile' => in('mobile'),
                'landline' => in('landline'),
                'address' => in('address'),
                'skype' => in('skype'),
                'kakao' => in('kakao'),
            )
        );
        if ( is_wp_error( $id ) ) wp_send_json( json_error( -10140, 'failed on registratoin' ) ); // $id ;
        else { // Registration is OK
            if ( in('login') == '1' ) { // Set user logged-in
                $credits = array(
                    'user_login'    => in('user_login'),
                    'user_password' => in('user_pass'),
                    'rememberme'    => true
                );
                wp_signon( $credits, false );
            }
            wp_send_json( json_success( $id ) );
        }

    }

    public function updateSubmit() {
        if ( ! user()->login() ) wp_send_json(json_error(-4077, 'Login first') );
        if ( ! in('user_email') ) wp_send_json(json_error(-4078, 'Input email') );
        // @note
        $user = user( in('user_email') );
        if ( $user->exists() ) {
            if ( $user->ID != my()->ID ) {
                wp_send_json(json_error(-4079, 'email already exists.'));
            }
        }
        if ( ! in('name') ) wp_send_json(json_error(4070, 'Input name') );
        if ( ! in('mobile') ) wp_send_json(json_error(-4071, 'Input mobile number') );

        my()->user_email = in('user_email');
        my()->name = in('name');
        my()->mobile = in('mobile');
        my()->landline = in('landline');
        my()->address = in('address');
        my()->skype = in('skype');
        my()->kakao = in('kakao');

        wp_send_json( json_success() );
    }


    public function loginSubmit() {
        $credits = array(
            'user_login'    => in('user_login'),
            'user_password' => in('user_pass'),
            'rememberme'    => in('rememberme')
        );
        $re = wp_signon( $credits, false );
        if ( is_wp_error($re) ) {
            $user = user( in('user_login') );
            if ( $user->exists() ) $re = json_error( -40132, "Wrong password" );
            else $re = json_error( -40131, "Wrong username" );
            wp_send_json( $re );
        }
        else wp_send_json( json_success() );
    }



    public function passwordLostSubmit() {
        include ABSPATH . '/wp-login.php';
        retrieve_password();
    }


}

/**
 *
 * @see test file for example.
 *
 * @param null $uid
 * @return user
 *
 *
 */
function user( $uid = null ) {
    $user = new user($uid);
    return $user;
}

/**
 * @param null $uid
 * @return user
 * @code
 *
$user = user( in('user_email') );
if ( $user->exists() ) {
if ( $user->ID != my()->ID ) {
wp_send_json(json_error(-20, 'email already exists.'));
}
}
 * @endcode
 */
function my( $uid = null ) {
    return user( $uid );
}

