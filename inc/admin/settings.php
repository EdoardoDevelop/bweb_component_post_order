<?php

$bcpo_options = get_option( 'bcpo_options' );
$bcpo_objects = isset( $bcpo_options['objects'] ) ? $bcpo_options['objects'] : array();
$bcpo_tags = isset( $bcpo_options['tags'] ) ? $bcpo_options['tags'] : array();

?>

<div class="wrap">

<h2 class="wp-heading-inline"><?php _e( 'Post Order Settings', 'bcpo' ); ?></h2>

<?php if ( isset($_GET['msg'] )) : ?>
<div id="message" class="updated below-h2">
	<?php if ( $_GET['msg'] == 'update' ) : ?>
		<p><?php _e( 'Settings saved.' ); ?></p>
	<?php endif; ?>
</div>
<?php endif; ?>

<form method="post">

<?php if ( function_exists( 'wp_nonce_field' ) ) wp_nonce_field( 'nonce_bcpo' ); ?>
<br>
<div id="bcpo_select_objects" style="display: inline-block; border: 1px solid #ccc; padding: 0 20px 20px; background-color: #fff;">

	<table class="form-table">
		<tbody>
			<tr valign="top">
				<td>
					<h4 scope="row"><?php _e( 'Ordinamento Post Types', 'bcpo' ) ?></h4>
				<?php
					$post_types = get_post_types( array (
						'show_ui' => true,
						'show_in_menu' => true,
					), 'objects' );
					
					foreach ( $post_types  as $post_type ) {
						if ( $post_type->name == 'attachment' ) continue;
						?>
						<label style="margin-right: 20px;"><input type="checkbox" name="objects[]" value="<?php echo $post_type->name; ?>" <?php if ( isset( $bcpo_objects ) && is_array( $bcpo_objects ) ) { if ( in_array( $post_type->name, $bcpo_objects ) ) { echo 'checked="checked"'; } } ?>>&nbsp;<?php echo $post_type->label; ?></label>
						<?php
					}
				?>
					<br><br><hr>
					<label><input type="checkbox" id="bcpo_allcheck_objects"> <?php _e( 'Seleziona tutto', 'bcpo' ) ?></label>
				</td>
			</tr>
		</tbody>
	</table>

</div>
<br><br>

<div id="bcpo_select_tags" style="display: inline-block; border: 1px solid #ccc; padding:  0 20px 20px; background-color: #fff;">

	<table class="form-table">
		<tbody>
			<tr valign="top">
				<td>
					<h4><?php _e( 'Ordinamento Taxonomies', 'bcpo' ) ?></h4>
				<?php
					$taxonomies = get_taxonomies( array(
						'show_ui' => true,
					), 'objects' );
					
					foreach( $taxonomies as $taxonomy ) {
						if ( $taxonomy->name == 'post_format' ) continue;
						?>
						<label style="margin-right: 20px;"><input type="checkbox" name="tags[]" value="<?php echo $taxonomy->name; ?>" <?php if ( isset( $bcpo_tags ) && is_array( $bcpo_tags ) ) { if ( in_array( $taxonomy->name, $bcpo_tags ) ) { echo 'checked="checked"'; } } ?>>&nbsp;<?php echo $taxonomy->label ?></label>
						<?php
					}
				?>
					<br><br><hr>
					<label><input type="checkbox" id="bcpo_allcheck_tags"> <?php _e( 'Seleziona tutto', 'bcpo' ) ?></label>
				</td>
			</tr>
		</tbody>
	</table>

</div>

<p class="submit">
	<input type="submit" class="button-primary" name="bcpo_submit" value="<?php _e( 'Update' ); ?>">
</p>
	
</form>

</div>

<script>
(function($){
	
	$("#bcpo_allcheck_objects").on('click', function(){
		var items = $("#bcpo_select_objects input");
		if ( $(this).is(':checked') ) $(items).prop('checked', true);
		else $(items).prop('checked', false);	
	});

	$("#bcpo_allcheck_tags").on('click', function(){
		var items = $("#bcpo_select_tags input");
		if ( $(this).is(':checked') ) $(items).prop('checked', true);
		else $(items).prop('checked', false);	
	});
	
})(jQuery)
</script>