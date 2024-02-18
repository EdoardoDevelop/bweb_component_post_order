<?php
/**
 * ID: post_order
 * Name: Post Order
 * Description: Drag and Drop Sortable per ordinare pagine/post.
 * Icon: dashicons-sort
 * Version: 1.0
 * 
 */
if (!defined("ABSPATH")) {
    exit; // Exit if accessed directly
}


define( 'BCPO_URL', plugins_url( '', __FILE__ ).'/inc' );
define( 'BCPO_DIR', plugin_dir_path( __FILE__ ).'inc/' );

require plugin_dir_path( __FILE__ ) ."inc/load.php";
