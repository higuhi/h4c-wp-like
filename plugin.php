<?php

/*
Plugin Name: H4C Annonymous Like Button 
Plugin URI: 
Description: Adds "Like" button to blog post for any user with/without login
Version: 1.0.0
Author: H4C Hiroshi Higuchi
Author URI: 
License: MIT License
*/

define('H4C_ANNONYMOUS_LIKE_VERSION', '1.0.0');
define('H4C_ANNONYMOUS_LIKE_TABLE_NAME', 'h4c_wp_plugin_annonymous_like_table');


global $h4c_annnoymous_like_db_version;
$h4c_annnoymous_like_db_version = H4C_ANNONYMOUS_LIKE_VERSION;


register_activation_hook( __FILE__, 'h4c_annonymous_like_install' );                // for installation
add_action( 'init', 'h4c_annonymous_like_init');                                    // for plugin initialization
add_action( 'wp_ajax_h4c_annnonymous_like', 'h4c_annonymous_like_callback' );       // for logged-in users
add_action( 'wp_ajax_nopriv_h4c_annnonymous_like', 'h4c_annonymous_like_callback' );// for non-logged-in users
add_action( 'wp_enqueue_scripts', 'h4c_annonymous_like_script' );                   // for javascript/css enqueue
add_action( 'admin_menu', 'h4c_annonymous_like_admin_menu');                        // for admin menu
add_filter( 'the_content','h4c_annonymous_like_addtext' );                          // for contnet filter

/**
 * Install - should be called when the plugin is installed.
 * It will create/update the table to store 'Like' from a specific IP address. 
 */
function h4c_annonymous_like_install() {
    global $wpdb;
    global $h4c_annnoymous_like_db_version;

    $table_name = $wpdb->prefix . H4C_ANNONYMOUS_LIKE_TABLE_NAME;
    $charset_collate = $wpdb->get_charset_collate();
    
    if( $wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) { 
        $create_sql = "CREATE TABLE $table_name (
            id INT(11) NOT NULL AUTO_INCREMENT,
            postid INT(11) NOT NULL ,
            clientip VARCHAR(40) NOT NULL ,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        require_once(ABSPATH . "wp-admin/includes/upgrade.php");
        dbDelta( $create_sql );
    }
    
    add_option( "h4c_annnoymous_like_db_version", $h4c_annnoymous_like_db_version );
}

/**
 * Init - should be called to initialize the plugin. 
 * It will simply adds the table to the wpdb object. 
 */
function h4c_annonymous_like_init() {
    global $wpdb;
    $table_name = $wpdb->prefix . H4C_ANNONYMOUS_LIKE_TABLE_NAME;

    //register the new table with the wpdb object
    if (!isset($wpdb->h4c_wp_plugin_annonymous_like_table)) {
        $wpdb->h4c_wp_plugin_annonymous_like_table = $table_name;
        //add the shortcut so you can use $wpdb->stats
        $wpdb->tables[] = str_replace($wpdb->prefix, '', $table_name);
    }
}

/**
 * Enque a Javascript and CSS - should be called to enqueue a Javascript, 
 * which is localized with URL and nounce, and CSS stylesheet. 
 */
function h4c_annonymous_like_script() {
    wp_enqueue_style( 'h4c_wp_plugin_annonymous_like_style', plugins_url('/css/style.css', __FILE__ ) );

    wp_enqueue_script( 'h4c_wp_plugin_annonymous_like_ajax_script', 
        plugins_url('/js/annonymous_like.js', __FILE__ ), 
        array('jquery')
    );
    
    wp_localize_script( 'h4c_wp_plugin_annonymous_like_ajax_script', 
        'h4c_wp_plugin_annonymous_like_ajax', 
        array(
            // URL to wp-admin/admin-ajax.php to process the request
            'ajaxurl' => admin_url( 'admin-ajax.php' ),

            // generate a nonce with a unique ID "h4c_wp_plugin_annonymous_like_ajax_script"
            'security' => wp_create_nonce( 'h4c_annonymous_like_script' ),
        )
    );
}

/**
 * Ajax callback - should be called from Ajax
 * It will check the IP address and toggle the like flag and returns JSON response. 
 * postid - the post ID that has been like or disliked
 * likecount - the number of like for this post
 * clientip - the IP address of the host
 * liked - true if liked, false if disliked
 */
function h4c_annonymous_like_callback() {
    check_ajax_referer( 'h4c_annonymous_like_script', 'security' );

    global $wpdb;
    $table_name = $wpdb->h4c_wp_plugin_annonymous_like_table;

    $postid = intval( $_POST['postid'] );
    $clientip = $_SERVER['REMOTE_ADDR']; 
    $liked = false;
    $like_count=0;
    
    //check if post id and ip present
    $row = $wpdb->get_results( "SELECT id FROM $table_name WHERE postid = '$postid' AND clientip = '$clientip'");
    if(empty($row)) {
        //insert row
        $wpdb->insert( $table_name, array( 'postid' => $postid, 'clientip' => $clientip ), array( '%d', '%s' ) );
        $liked = true;
    } else {
        //delete row
        $wpdb->delete( $table_name, array( 'postid' => $postid, 'clientip'=> $clientip ), array( '%d','%s' ) );
        $liked = false;
    }
    
    //calculate like count from db.
    $totalrow = $wpdb->get_results( "SELECT id FROM $table_name WHERE postid = '$postid'");
    $total_like=$wpdb->num_rows;

    $data=array( 'postid'=>$postid, 'likecount'=>$total_like, 'clientip'=>$clientip, 'liked'=>$liked );
    echo json_encode($data);

    die(); // this is required to return a proper result
}

/**
 * Context Filter - should be called from the content filter. 
 * It will add an HTML for "Annonymous Like" button
 */
function h4c_annonymous_like_addtext($contentData) {

    if ( is_single() && ! is_attachment() ) {
        global $wpdb;
        $table_name = $wpdb->h4c_wp_plugin_annonymous_like_table;

        $isLiked = false;
        $postid = get_the_id();
        $clientip = $_SERVER['REMOTE_ADDR'];
        
        $result = $wpdb->get_results( "SELECT id FROM $table_name WHERE postid = '$postid' AND clientip = '$clientip'");
        if(!empty($result)){
            $isLiked = true;
        }

        $img_like  = '<img class="pp_like_like" src="' . plugins_url('/images/icon_like.png', __FILE__ ) . ($isLiked ? '" style="display:none" />' : '" />');
        $img_liked = '<img class="pp_like_liked" src="' . plugins_url('/images/icon_liked.png', __FILE__ ) . ($isLiked ? '" />' : '" style="display:none" />') ;
                
        $result = $wpdb->get_results( "SELECT id FROM $table_name WHERE postid = '$postid'");
        $total_like = $wpdb->num_rows;
        
        $str  = '<div class="post_like">';
        $str .= '<a class="pp_like" href="#" data-id=' . $postid . '>' . $img_like . $img_liked. '</a> <span>' . $total_like . ' like' . ( ($total_like>1) ? 's' : '' ) . '</span>';
        $str .= '</div>';
    
        $contentData = $contentData . $str;

    }
    return $contentData;
}

/**
 * Admin Menu - should be called from the admin menu filter. 
 * It will add a sub menu under Post. 
 */
function h4c_annonymous_like_admin_menu() {
    add_posts_page(
        __( 'Likes', 'h4c_annonymous_like' ),
        __( 'Likes', 'h4c_annonymous_like' ),
        'read',
        'h4c_annonymous_like',
        'h4c_annonymous_like_admin_view'
    );
}

/**
 * Admin View - should be called from admin panel.
 * It will list a table of blog posts and number of Likes on each post. 
 * Blog posts with no likes won't be shown. 
 */
function h4c_annonymous_like_admin_view() {

    if (!current_user_can('edit_posts')) {
        wp_die( __('You do not have sufficient permissions to access this page.', 'h4c_annonymous_like') );
    }

    global $wpdb;
    $table_name = $wpdb->h4c_wp_plugin_annonymous_like_table;

    $result = $wpdb->get_results( "SELECT id, postid, count(*) AS total_like FROM $table_name GROUP BY postid ORDER BY total_like DESC");

    
    $str = '<h1>' . __( 'Number of Like on Posts', 'h4c_annonymous_like' ) . '</h1>';
    $str .= '<div>';
    $str .= '<table class="wp-list-table widefat fixed striped ">';
    $str .= '  <thead>';  
    $str .= '    <tr>';
    $str .= '      <th>' . __( 'Title', 'h4c_annonymous_like') . '</th>';
    $str .= '      <th>' . __( 'Like Count', 'h4c_annonymous_like') . '</th>';
    $str .= '    </tr>';
    $str .= '  </thead>';  
    foreach($result as $key => $row) {
        if( current_user_can( 'edit_post', $row->postid ) ) {
            $str .= '<tr>';
            $str .= '  <td><a href="' . get_permalink($row->postid) . '" >'. get_the_title(get_post($row->postid)) . '</a></td>';
            $str .= '  <td>' . $row->total_like . '</td>';
            $str .= '</tr>';    
        }
    }
    $str .= '</table>';
    $str .= '</div>';
    echo $str;
}


?>
