<?php

global $wpdb;
// add term_order COLUMN to $wpdb->terms TABLE
$result = $wpdb->query( "DESCRIBE $wpdb->terms `term_order`" );
if ( !$result ) {
    $query = "ALTER TABLE $wpdb->terms ADD `term_order` INT( 4 ) NULL DEFAULT '0'";
    $result = $wpdb->query( $query );
}