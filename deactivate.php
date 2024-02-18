<?php
	
global $wpdb;
$result = $wpdb->query( "DESCRIBE $wpdb->terms `term_order`" );
if ( $result ){
    $query = "ALTER TABLE $wpdb->terms DROP `term_order`";
    $result = $wpdb->query( $query );
}
delete_option( 'bcpo_options' );
