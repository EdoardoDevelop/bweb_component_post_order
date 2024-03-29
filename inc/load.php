<?php
/**
* Class & Method
*/

$bcpo = new Bcpo();

class Bcpo 
{
	/**
	* Construct
	*/
	function __construct()
	{
		// activation
		/*$bcpo_ver = get_option( 'bcpo_ver' );
		if ( version_compare( $bcpo_ver, BCPO_VER ) < 0 ) $this->bcpo_activation();*/
		

		// add menu
		add_action( 'admin_menu', array( $this, 'admin_menu') );
		
		// admin init
		if ( empty($_GET) ) {
			add_action( 'admin_init', array( $this, 'refresh' ) );
		}
		add_action( 'admin_init', array( $this, 'update_options') );
		add_action( 'admin_init', array( $this, 'load_script_css' ) );
		
		// sortable ajax action
		add_action( 'wp_ajax_update-menu-order', array( $this, 'update_menu_order' ) );
		add_action( 'wp_ajax_update-menu-order-tags', array( $this, 'update_menu_order_tags' ) );
		
		// reorder post types
		add_action( 'pre_get_posts', array( $this, 'bcpo_pre_get_posts' ) );
		
		add_filter( 'get_previous_post_where', array( $this, 'bcpo_previous_post_where' ) );
		add_filter( 'get_previous_post_sort', array( $this, 'bcpo_previous_post_sort' ) );
		add_filter( 'get_next_post_where', array( $this, 'hocpo_next_post_where' ) );
		add_filter( 'get_next_post_sort', array( $this, 'bcpo_next_post_sort' ) );
		
		// reorder taxonomies
		add_filter( 'get_terms_orderby', array( $this, 'bcpo_get_terms_orderby' ), 10, 3 );
		add_filter( 'wp_get_object_terms', array( $this, 'bcpo_get_object_terms' ), 10, 3 );
		add_filter( 'get_terms', array( $this, 'bcpo_get_object_terms' ), 10, 3 );
				
		
	}
	
	
	function admin_menu()
	{
		add_submenu_page(
            'bweb-component',
			'Post Order', // page_title
			'Post Order', // menu_title
			'manage_options', // capability
			'post_order', // menu_slug
			array( $this, 'admin_page' ) // function
		);
	}
	
	function admin_page()
	{
		require BCPO_DIR.'admin/settings.php';
	}
	
	function _check_load_script_css()
	{
		global $pagenow, $typenow;
		
		$active = false;
		
		
		$objects = $this->get_bcpo_options_objects();
		$tags = $this->get_bcpo_options_tags();
		
		if ( empty( $objects ) && empty( $tags ) ) return false;
		
		// exclude (sorting, addnew page, edit page)
		if ( isset( $_GET['orderby'] ) || strstr( $_SERVER['REQUEST_URI'], 'action=edit' ) || strstr( $_SERVER['REQUEST_URI'], 'wp-admin/post-new.php' ) ) return false;
		
		if ( !empty( $objects ) ) {
			if ( isset( $_GET['post_type'] ) && !isset( $_GET['taxonomy'] ) && in_array( $_GET['post_type'], $objects ) ) { // if page or custom post types
				$active = true;
			}
			if ( !isset( $_GET['post_type'] ) && strstr( $_SERVER['REQUEST_URI'], 'wp-admin/edit.php' ) && in_array( 'post', $objects ) ) { // if post
				$active = true;
			}
		}
		
		if ( !empty( $tags ) ) {
			if ( isset( $_GET['taxonomy'] ) && in_array( $_GET['taxonomy'], $tags ) ) {
				$active = true;
			}
		}
		
		return $active;
	}

	function load_script_css()
	{
		if ( $this->_check_load_script_css() ) {
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'jquery-ui-sortable' );
			wp_enqueue_script( 'bcpojs', BCPO_URL.'/js/bcpo.js', array( 'jquery' ), null, true );
			wp_enqueue_style( 'bcpo', BCPO_URL.'/css/bcpo.css', array(), null );
		}
	}
			
	function refresh()
	{
		global $wpdb;
		$objects = $this->get_bcpo_options_objects();
		$tags = $this->get_bcpo_options_tags();
		
		if ( !empty( $objects ) ) {
			foreach( $objects as $object) {
				$result = $wpdb->get_results( "
					SELECT count(*) as cnt, max(menu_order) as max, min(menu_order) as min 
					FROM $wpdb->posts 
					WHERE post_type = '".$object."' AND post_status IN ('publish', 'pending', 'draft', 'private', 'future')
				" );
				if ( $result[0]->cnt == 0 || $result[0]->cnt == $result[0]->max ) continue;
				
				$results = $wpdb->get_results( "
					SELECT ID 
					FROM $wpdb->posts 
					WHERE post_type = '".$object."' AND post_status IN ('publish', 'pending', 'draft', 'private', 'future') 
					ORDER BY menu_order ASC
				" );
				foreach( $results as $key => $result ) {
					$wpdb->update( $wpdb->posts, array( 'menu_order' => $key+1 ), array( 'ID' => $result->ID ) );
				}
			}
		}

		if ( !empty( $tags ) ) {
			foreach( $tags as $taxonomy ) {
				$result = $wpdb->get_results( "
					SELECT count(*) as cnt, max(term_order) as max, min(term_order) as min 
					FROM $wpdb->terms AS terms 
					INNER JOIN $wpdb->term_taxonomy AS term_taxonomy ON ( terms.term_id = term_taxonomy.term_id ) 
					WHERE term_taxonomy.taxonomy = '".$taxonomy."'
				" );
				if ( $result[0]->cnt == 0 || $result[0]->cnt == $result[0]->max ) continue;
				
				$results = $wpdb->get_results( "
					SELECT terms.term_id 
					FROM $wpdb->terms AS terms 
					INNER JOIN $wpdb->term_taxonomy AS term_taxonomy ON ( terms.term_id = term_taxonomy.term_id ) 
					WHERE term_taxonomy.taxonomy = '".$taxonomy."' 
					ORDER BY term_order ASC
				" );
				foreach( $results as $key => $result ) {
					$wpdb->update( $wpdb->terms, array( 'term_order' => $key+1 ), array( 'term_id' => $result->term_id ) );
				}
			}
		}
	}
	
	
	
	
	
	function update_menu_order()
	{
		global $wpdb;

		parse_str( $_POST['order'], $data );
		
		if ( !is_array( $data ) ) return false;
			
		// get objects per now page
		$id_arr = array();
		foreach( $data as $key => $values ) {
			foreach( $values as $position => $id ) {
				$id_arr[] = $id;
			}
		}
		
		// get menu_order of objects per now page
		$menu_order_arr = array();
		foreach( $id_arr as $key => $id ) {
			$results = $wpdb->get_results( "SELECT menu_order FROM $wpdb->posts WHERE ID = ".intval( $id ) );
			foreach( $results as $result ) {
				$menu_order_arr[] = $result->menu_order;
			}
		}
		
		// maintains key association = no
		sort( $menu_order_arr );
		
		foreach( $data as $key => $values ) {
			foreach( $values as $position => $id ) {
				$wpdb->update( $wpdb->posts, array( 'menu_order' => $menu_order_arr[$position] ), array( 'ID' => intval( $id ) ) );
			}
		}

		// same number check
		$post_type = get_post_type($id);
		$sql = "SELECT COUNT(menu_order) AS mo_count, post_type, menu_order FROM $wpdb->posts
				 WHERE post_type = '{$post_type}' AND post_status IN ('publish', 'pending', 'draft', 'private', 'future')
				 AND menu_order > 0 GROUP BY post_type, menu_order HAVING (mo_count) > 1";
		$results = $wpdb->get_results( $sql );
		if(count($results) > 0) {
			// menu_order refresh
			$sql = "SELECT ID, menu_order FROM $wpdb->posts
			 WHERE post_type = '{$post_type}' AND post_status IN ('publish', 'pending', 'draft', 'private', 'future')
			 AND menu_order > 0 ORDER BY menu_order";
			$results = $wpdb->get_results( $sql );
			foreach( $results as $key => $result ) {
				$view_posi = array_search($result->ID, $id_arr, true);
				if( $view_posi === false) {
					$view_posi = 999;
				}
				$sort_key = ($result->menu_order * 1000) + $view_posi;
				$sort_ids[$sort_key] = $result->ID;
			}
			ksort($sort_ids);
			$oreder_no = 0;
			foreach( $sort_ids as $key => $id ) {
				$oreder_no = $oreder_no + 1;
				$wpdb->update( $wpdb->posts, array( 'menu_order' => $oreder_no ), array( 'ID' => intval( $id ) ) );
			}
		}

	}
	
	function update_menu_order_tags()
	{
		global $wpdb;
		
		parse_str( $_POST['order'], $data );
		
		if ( !is_array( $data ) ) return false;
		
		$id_arr = array();
		foreach( $data as $key => $values ) {
			foreach( $values as $position => $id ) {
				$id_arr[] = $id;
			}
		}
		
		$menu_order_arr = array();
		foreach( $id_arr as $key => $id ) {
			$results = $wpdb->get_results( "SELECT term_order FROM $wpdb->terms WHERE term_id = ".intval( $id ) );
			foreach( $results as $result ) {
				$menu_order_arr[] = $result->term_order;
			}
		}
		sort( $menu_order_arr );
		
		foreach( $data as $key => $values ) {
			foreach( $values as $position => $id ) {
				$wpdb->update( $wpdb->terms, array( 'term_order' => $menu_order_arr[$position] ), array( 'term_id' => intval( $id ) ) );
			}
		}

		// same number check
		$term = get_term($id);
		$taxonomy = $term->taxonomy;
		$sql = "SELECT COUNT(term_order) AS to_count, term_order 
			FROM $wpdb->terms AS terms 
			INNER JOIN $wpdb->term_taxonomy AS term_taxonomy ON ( terms.term_id = term_taxonomy.term_id ) 
			WHERE term_taxonomy.taxonomy = '".$taxonomy."'GROUP BY taxonomy, term_order HAVING (to_count) > 1";
		$results = $wpdb->get_results( $sql );
		if(count($results) > 0) {
			// term_order refresh
			$sql = "SELECT terms.term_id, term_order
			FROM $wpdb->terms AS terms 
			INNER JOIN $wpdb->term_taxonomy AS term_taxonomy ON ( terms.term_id = term_taxonomy.term_id ) 
			WHERE term_taxonomy.taxonomy = '".$taxonomy."' 
			ORDER BY term_order ASC";
			$results = $wpdb->get_results( $sql );
			foreach( $results as $key => $result ) {
				$view_posi = array_search($result->term_id, $id_arr, true);
				if( $view_posi === false) {
					$view_posi = 999;
				}
				$sort_key = ($result->term_order * 1000) + $view_posi;
				$sort_ids[$sort_key] = $result->term_id;
			}
			ksort($sort_ids);
			$oreder_no = 0;
			foreach( $sort_ids as $key => $id ) {
				$oreder_no = $oreder_no + 1;
				$wpdb->update( $wpdb->terms, array( 'term_order' => $oreder_no ), array( 'term_id' => $id ) );
			}
		}

	}
	
	
	
	/**
	*
	* post_type: orderby=post_date, order=DESC
	* page: orderby=menu_order, post_title, order=ASC
	* taxonomy: orderby=name, order=ASC
	*/
	
	function update_options()
	{
		global $wpdb;
		
		if ( !isset( $_POST['bcpo_submit'] ) ) return false;
			
		check_admin_referer( 'nonce_bcpo' );
			
		$input_options = array();
		$input_options['objects'] = isset( $_POST['objects'] ) ? $_POST['objects'] : '';
		$input_options['tags'] = isset( $_POST['tags'] ) ? $_POST['tags'] : '';
		
		update_option( 'bcpo_options', $input_options );
		
		$objects = $this->get_bcpo_options_objects();
		$tags = $this->get_bcpo_options_tags();
		
		if ( !empty( $objects ) ) {
			foreach( $objects as $object ) {
				$result = $wpdb->get_results( "
					SELECT count(*) as cnt, max(menu_order) as max, min(menu_order) as min 
					FROM $wpdb->posts 
					WHERE post_type = '".$object."' AND post_status IN ('publish', 'pending', 'draft', 'private', 'future')
				" );
				if ( $result[0]->cnt == 0 || $result[0]->cnt == $result[0]->max ) continue;
				
				if ( $object == 'page' ) {
					$results = $wpdb->get_results( "
						SELECT ID 
						FROM $wpdb->posts 
						WHERE post_type = '".$object."' AND post_status IN ('publish', 'pending', 'draft', 'private', 'future') 
						ORDER BY menu_order, post_title ASC
					" );
				} else {
					$results = $wpdb->get_results( "
						SELECT ID 
						FROM $wpdb->posts 
						WHERE post_type = '".$object."' AND post_status IN ('publish', 'pending', 'draft', 'private', 'future') 
						ORDER BY post_date DESC
					" );
				}
				foreach( $results as $key => $result ) {
					$wpdb->update( $wpdb->posts, array( 'menu_order' => $key+1 ), array( 'ID' => $result->ID ) );
				}
			}
		}
		
		if ( !empty( $tags ) ) {
			foreach( $tags as $taxonomy ) {
				$result = $wpdb->get_results( "
					SELECT count(*) as cnt, max(term_order) as max, min(term_order) as min 
					FROM $wpdb->terms AS terms 
					INNER JOIN $wpdb->term_taxonomy AS term_taxonomy ON ( terms.term_id = term_taxonomy.term_id ) 
					WHERE term_taxonomy.taxonomy = '".$taxonomy."'
				" );
				if ( $result[0]->cnt == 0 || $result[0]->cnt == $result[0]->max ) continue;
				
				$results = $wpdb->get_results( "
					SELECT terms.term_id 
					FROM $wpdb->terms AS terms 
					INNER JOIN $wpdb->term_taxonomy AS term_taxonomy ON ( terms.term_id = term_taxonomy.term_id ) 
					WHERE term_taxonomy.taxonomy = '".$taxonomy."' 
					ORDER BY name ASC
				" );
				foreach( $results as $key => $result ) {
					$wpdb->update( $wpdb->terms, array( 'term_order' => $key+1 ), array( 'term_id' => $result->term_id ) );
				}
			}
		}
		
		wp_redirect( 'admin.php?page=post_order&msg=update' );
	}
	
	
		
	function bcpo_previous_post_where( $where )
	{
		global $post;

		$objects = $this->get_bcpo_options_objects();
		if ( empty( $objects ) ) return $where;
		
		if ( isset( $post->post_type ) && in_array( $post->post_type, $objects ) ) {
			$current_menu_order = $post->menu_order;
			$where = str_replace( "p.post_date < '".$post->post_date."'", "p.menu_order > '".$current_menu_order."'", $where );
		}
		return $where;
	}
	
	function bcpo_previous_post_sort( $orderby )
	{
		global $post;
		
		$objects = $this->get_bcpo_options_objects();
		if ( empty( $objects ) ) return $orderby;
		
		if ( isset( $post->post_type ) && in_array( $post->post_type, $objects ) ) {
			$orderby = 'ORDER BY p.menu_order ASC LIMIT 1';
		}
		return $orderby;
	}
	
	function hocpo_next_post_where( $where )
	{
		global $post;

		$objects = $this->get_bcpo_options_objects();
		if ( empty( $objects ) ) return $where;
		
		if ( isset( $post->post_type ) && in_array( $post->post_type, $objects ) ) {
			$current_menu_order = $post->menu_order;
			$where = str_replace( "p.post_date > '".$post->post_date."'", "p.menu_order < '".$current_menu_order."'", $where );
		}
		return $where;
	}
	
	function bcpo_next_post_sort( $orderby )
	{
		global $post;
		
		$objects = $this->get_bcpo_options_objects();
		if ( empty( $objects ) ) return $orderby;
		
		if ( isset( $post->post_type ) && in_array( $post->post_type, $objects ) ) {
			$orderby = 'ORDER BY p.menu_order DESC LIMIT 1';
		}
		return $orderby;
	}
	
	function bcpo_pre_get_posts( $wp_query )
	{
		$objects = $this->get_bcpo_options_objects();
		if ( empty( $objects ) ) return false;
		
		/**
		* for Admin
		*
		* @default
		* post cpt: [order] => null(desc) [orderby] => null(date)
		* page: [order] => asc [orderby] => menu_order title
		* 
		*/
		
		if ( is_admin() ) {
			
			// adminの場合 $wp_query->query['post_type']=post も渡される
			if ( isset( $wp_query->query['post_type'] ) && !isset( $_GET['orderby'] ) ) {
				if ( in_array( $wp_query->query['post_type'], $objects ) ) {
					$wp_query->set( 'orderby', 'menu_order' );
					$wp_query->set( 'order', 'ASC' );
				}
			}
		
		/**
		* for Front End
		*/
		
		} else {
			
			$active = false;
			
			// page or custom post types
			if ( isset( $wp_query->query['post_type'] ) ) {
				// exclude array()
				if ( !is_array( $wp_query->query['post_type'] ) ) {
					if ( in_array( $wp_query->query['post_type'], $objects ) ) {
						$active = true;
					}
				}
			// post
			} else {
				if ( in_array( 'post', $objects ) ) {
					$active = true;
				}
			}
			
			if ( !$active ) return false;
			
			// get_posts()
			if ( isset( $wp_query->query['suppress_filters'] ) ) {
				if ( $wp_query->get( 'orderby' ) == 'date' || $wp_query->get( 'orderby' ) == 'menu_order' ) {
					$wp_query->set( 'orderby', 'menu_order' );
					$wp_query->set( 'order', 'ASC' );
				} elseif($wp_query->get( 'orderby' ) == 'default_date') {
					$wp_query->set( 'orderby', 'date' );
				}
			// WP_Query( contain main_query )
			} else {
				if ( !$wp_query->get( 'orderby' ) )  $wp_query->set( 'orderby', 'menu_order' );
				if ( !$wp_query->get( 'order' ) ) $wp_query->set( 'order', 'ASC' );
			}
		}
	}
	
	function bcpo_get_terms_orderby( $orderby, $args )
	{
		if ( is_admin() ) return $orderby;
		
		$tags = $this->get_bcpo_options_tags();
		
		if( !isset( $args['taxonomy'] ) ) return $orderby;
		
		$taxonomy = $args['taxonomy'];
		if ( !in_array( $taxonomy, $tags ) ) return $orderby;
		
		$orderby = 't.term_order';
		return $orderby;
	}

	function bcpo_get_object_terms( $terms )
	{
		$tags = $this->get_bcpo_options_tags();
		
		if ( is_admin() && isset( $_GET['orderby'] ) ) return $terms;
		
		foreach( $terms as $key => $term ) {
			if ( is_object( $term ) && isset( $term->taxonomy ) ) {
				$taxonomy = $term->taxonomy;
				if ( !in_array( $taxonomy, $tags ) ) return $terms;
			} else {
				return $terms;
			}
		}
		
		usort( $terms, array( $this, 'taxcmp' ) );
		return $terms;
	}
	
	function taxcmp( $a, $b )
	{
		if ( $a->term_order ==  $b->term_order ) return 0;
		return ( $a->term_order < $b->term_order ) ? -1 : 1;
	}
	
	
	
	

	
	
	function get_bcpo_options_objects()
	{
		$bcpo_options = get_option( 'bcpo_options' ) ? get_option( 'bcpo_options' ) : array();
		$objects = isset( $bcpo_options['objects'] ) && is_array( $bcpo_options['objects'] ) ? $bcpo_options['objects'] : array();
		return $objects;
	}
	function get_bcpo_options_tags()
	{
		$bcpo_options = get_option( 'bcpo_options' ) ? get_option( 'bcpo_options' ) : array();
		$tags = isset( $bcpo_options['tags'] ) && is_array( $bcpo_options['tags'] ) ? $bcpo_options['tags'] : array();
		return $tags;
	}
	
}

?>