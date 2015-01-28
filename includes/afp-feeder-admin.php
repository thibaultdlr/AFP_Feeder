<?php

class AFP_Feeder_admin {
	
	var $afp_feeder;
	
	function __construct($afp_feeder) {
				
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_post_do_settings', array( $this, 'handle_options' ) );
		add_action( 'admin_notices', array( $this, 'afpf_admin_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'afpf_wptuts_script' ) );
		add_action( 'wp_ajax_add_category', array( $this, 'afpf_add_category' ) );;
		
		$this->afp_feeder = $afp_feeder;
	}
			
	function set_afp_feeder($afp_feeder) {
		$this->afp_feeder = $afp_feeder;
	}
	
	function add_admin_menu() {
		add_menu_page('Import AFP', 'Import AFP', 'edit_posts', 'afpfeeder');
		// Sous menu 'Dêpeches' déclaré lors de register_afpfeed_type() dans afp-feeder.php
		add_submenu_page ('afpfeeder', 'Import AFP', 'Options', 'manage_options', 'afp-feeder', array($this, 'afpfeeder_option_page'));
	}

	/**
	 * CSS dans en head, js avant la fermeture de body.
	 * Ne sont chargés que si la requete http comporte 'afp-feeder'.
	 */
	function afpf_wptuts_script() {
		wp_register_style( 'afpf-style', AFP_FEEDER_URL . '/assets/css/admin-style.css' );
		wp_register_script( 'afpf-script', AFP_FEEDER_URL . '/assets/js/admin-script.js', array(), 1.0, true );
		if ( preg_match('#afp-feeder#', urlencode( $_SERVER['REQUEST_URI'] ) ) ) {
			wp_enqueue_style( 'afpf-style' );
			wp_enqueue_script('afpf-script' );
		}
	}

	/**
	 * Contrôleur des options
	 */
	function handle_options() {
		$updated = 'true';
		$msg = 'Options enregistr&eacute;es.';
		$msgtype = 'updated';
		
		$urlparam = array();

		$p = $_POST;
		
		if ( empty ( $p['afpf_base_path'] ) or empty( $p['user'] ) or empty( $p['afpf_status'] ) or empty( $p['afpf_comments'] ) ) {
			$msg = empty ( $p['afpf_base_path'] ) ? "Veuillez renseigner un chemin d'acc&egrave;s."  : 'Une erreur est intervenue. Veuillez v&eacute;rifier vos r&eacute;glages.';
			$msgtype = 'error';
			$updated = 'false';
		} else {
			$options = array(
				'author'	=> wp_strip_all_tags( $p['user'] ),
				'base_path'	=> wp_strip_all_tags( $p['afpf_base_path'] ),
				'status'	=> wp_strip_all_tags( $p['afpf_status'] ),
				'comments'	=> wp_strip_all_tags( $p['afpf_comments'] ),
				'scheduled'	=> '',
				'recur'		=> '',
				'lastimport'=> $this->afp_feeder->get_last_imported() ? $this->afp_feeder->get_last_imported() : '',
			);
			
			$options['cats'] = ( isset( $p['post_category'] ) ) ? wp_strip_all_tags( implode( ',', $p['post_category'] ) ) : '' ;
			
			if ( isset( $p['afpf_scheduled'] ) ) {
				$options['scheduled'] = wp_strip_all_tags( $p['afpf_scheduled'] ) ;
				if ( !empty( $p['afpf_recur'] ) ) {
					$options['recur'] = $p['afpf_recur'];
					if ( wp_get_schedule( 'afpimport' )  && wp_get_schedule( 'afpimport' ) !== $p['afpf_recur'] ) {
						wp_clear_scheduled_hook( 'afpimport' );
						wp_schedule_event( time() + ( get_option( 'gmt_offset' ) * 3600 ), $p['afpf_recur'], 'afpimport' );
					} elseif ( !wp_get_schedule( 'afpimport' ) ) {
						wp_schedule_event( time() + ( get_option( 'gmt_offset' ) * 3600 ), $p['afpf_recur'], 'afpimport' );
					}
				} 
			} else {
				if ( wp_get_schedule( 'afpimport' ) ) {
					wp_clear_scheduled_hook( 'afpimport' );
				}
			}
		}
		$urlparam['updated'] = $updated;


		if ($options) {
			update_option('afpf_general_settings', $options);
		}
		
		if ( isset( $p['submitandimport'] ) && 'true' === $updated ) {
			$urlparam['imported'] = 'true';
			$this->afp_feeder->get_saved_options();
			$result = $this->afp_feeder->import();
			if ( is_wp_error( $result ) ) {
				$wp_err_msg = sprintf( __("Une erreur est utervenue lors de l'import : %s.", 'afp-feeder'), $result->get_error_message() );
				add_settings_error( 'afpf_general_settings', 'import', __( $wp_err_msg ), 'error' );

			}
		}
		
		add_settings_error( 'afpf_general_settings', 'settings-update', __( $msg ), $msgtype );
		set_transient('settings_error', get_settings_errors('afpf_general_settings'), MINUTE_IN_SECONDS);
		set_transient('afpf_report', get_settings_errors('afpf_report'), MINUTE_IN_SECONDS);
		
		$url = add_query_arg( $urlparam, urldecode( $p['_wp_http_referer'] ) );
		wp_safe_redirect( $url );
		exit;
	}

	
	/**
	 * DESTINÉ A DISPARAITRE AVEC LA V2
	 * @see afpf_category_box()
	 */
	function afpf_add_category() {
		check_ajax_referer( 'afpf_add_category', '_ajax_nonce' );
		if ( !empty( $_POST['tag-name'] ) ) {
			if ( !$id = category_exists( $_POST['tag-name'] ) ) {
				$id = wp_create_category(
					wp_strip_all_tags( $_POST['tag-name'] ), wp_strip_all_tags( $_POST['parent'] )
				);
		
			}
			array_push( $this->afp_feeder->categories, $id );

		}
		if ( isset(  $_POST['checked'] ) )  {
				$checked = preg_replace('#post_category\[\]=#', '', (urldecode($_POST['checked'])));
				$this->afp_feeder->categories = array_unique(array_merge( $this->afp_feeder->categories, explode( '&', $checked ) ) );
			}
		$response = $this->afpf_category_box();
		echo $response;
		die; // sinon WP_ajax die(0)
	}

	/**
	 * Affichage des messages d'erreur
	 */
	function afpf_admin_notice() {
		if ($transient = get_transient( 'settings_error' )) {
			foreach ($transient as $msg) : ?>
			<div id="<?php echo $msg['code']; ?>" class="<?php echo $msg['type']; ?>"><p><?php echo $msg['message']; ?></p></div>					
				<?php
			endforeach;	
		}
		delete_transient( 'settings_error' );
	}

	function header() {
		if ( $transient = get_transient('settings_error') ) var_dump ( $transient );
		settings_errors( 'afpf_general_settings' ); ?>
		<div class="wrap" id="afpfwrap">
		<h2><?php _e( 'Import AFP') ?></h2>
		<div id="afpf-admin" class="narrow">
		<p><?php _e( "Ce module d'import vous permet d'importer les fichiers xml de d&eacute;p&ecirc;ches fournis "
				. "par l'AFP (Agence France Presse) dans le cadre de l'offre AFP Texte avec d&eacute;pot FTP. "
				. "Vous pouvez r&eacute;aliser un import ponctuel ou programmer un import automatique.<br /> "
				. "Renseignez les options pour commencer à importer.", 'afp-feeder' ) . '</p>';
		if ($this->afp_feeder->last_imported) {
			echo '<p><em>' . sprintf( __('Dernier import le %s', 'afp-feeder'), date( 'd/m/Y à H:i.', $this->afp_feeder->last_imported )) . '</em></p>';
		}
	}

	function footer() {
		echo '</div>';
		echo '</div>';
	}

	/**
	 * DESTINÉ A DISPARAITRE AVEC LA V2
	 * Permet d'afficher la meta-box category en countournat la necessité de renseigner un argument WP_Post dans post_categories_meta_box()
	 * @see afpf_add_category()
	 */
	function afpf_category_box() {
		?>
		<p><?php _e( "Cat&eacute;gorie des posts import&eacute;s :" ); ?>
			<span class="handlediv hide-if-no-js" title="Cliquer pour inverser."></span></p>
		<?php if ( $category = get_taxonomy( 'category' ) ) : ?>
		<div class="closable"><div class="inside">
				<input type='hidden' name='post_category[]' value='0' />
				<ul id="categorychecklist" data-wp-lists="list:category" class="categorychecklist form-no-clear">
					<?php wp_terms_checklist( 0, array(
							'selected_cats' => $this->afp_feeder->categories,
							'taxonomy' => 'category',
							'hierarchical'		=> 1,
						) ); ?>
				</ul>
			</div>
				<?php endif; ?>
				<?php if ( !current_user_can( $category->cap->assign_terms ) ) : ?>
			<p><em><?php _e( 'You cannot modify this Taxonomy.' ); ?></em></p>
				<?php elseif ( current_user_can( $category->cap->edit_terms ) ) : ?>
			<div id="category-adder" class="wp-hidden-children">
				<h4>
					<a id="category-add-toggle" href="#category-add" class="hide-if-no-js">
					<?php printf( __( '+ %s' ), $category->labels->add_new_item ); ?></a>
				</h4>
				<p id="category-add" class="category-add wp-hidden-child">
					<label class="screen-reader-text" for="newcategory"><?php echo $category->labels->add_new_item; ?></label>
						<input type="text" name="newcategory" id="newcategory" class="form-required form-input-tip" aria-required="true"/>
					<label class="screen-reader-text" for="newcategory_parent">
						<?php echo $category->labels->parent_item_colon; ?>
					</label>
					<?php wp_dropdown_categories( array(
							'taxonomy'			=> 'category',
							'hide_empty'		=> 0,
							'name'				=> 'newcategory_parent',
							'orderby'			=> 'name',
							'hierarchical'		=> 1,
							'show_option_none' => '&mdash; ' . $category->labels->parent_item . ' &mdash;'
						) ); ?>
					<input type="button" id="category-add-submit" class="button category-add-submit" value="<?php echo esc_attr( $category->labels->add_new_item ); ?>" />
					<input type="hidden" id="add-cat-ajax-nonce" value="<?php echo $nonce = wp_create_nonce( 'afpf_add_category' ); ?>" />
					<span id="category-ajax-response"></span>
				</p>
			</div>
		</div>
		<?php endif;	
	}

	function afpfeeder_option_page() {
		$redirect = urlencode( remove_query_arg( array( 'updated', 'step' ), $_SERVER['REQUEST_URI'] ) );
		
		$this->header();
		?>
		<form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" name='afp-feeder'>

			<input type="hidden" name="action" value="do_settings"/>
			<input type="hidden" name="_wp_http_referer" value="<?php echo $redirect; ?>">

			<table class="form-table" id="afpf-form-table">
				<tr>
					<th scope="row">
						<label for="afp_base_path"><?php _e( "Chemin d'acc&egrave;s vers le dossier de d&eacute;pot FTP :" ); ?></label>
					</th>
					<td>
						<input type="text" name="afpf_base_path" value="<?php echo $this->afp_feeder->base_path; ?>"/>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="afp_author"><?php _e( "Auteur des posts import&eacute;s :" ); ?></label>
					</th>
					<td>
						<?php
						$select_user = ($this->afp_feeder->post_author) ? $this->afp_feeder->post_author : wp_get_current_user()->ID;
						wp_dropdown_users( array(
							'selected' => $select_user,
						) )
						?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="afpf_status"><?php _e( "Statut des posts import&eacute;s :" ); ?></label>
					</th>
					<td>
							<?php $statuses = get_post_statuses(); ?>
						<select name='afpf_status'>
							<?php
							$status = ($this->afp_feeder->post_status) ? $this->afp_feeder->post_status : 'publish';
							foreach ( $statuses as $key => $value ) :
								$selected = ($key === $status) ? ' selected="selected"' : '';
								echo '<option value="' . $key . '"' . $selected . '>' . $value . '</option>';
							endforeach;
							?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="afpf_comments"><?php _e( "Statut des commentaires pour les posts import&eacute;s :" ); ?></label>
					</th>
					<td>
							<?php $comments_sts = $this->afp_feeder->comment_status; ?>
						<select name='afpf_comments'>
							<?php
							$selected = 'selected="selected"';
							echo '<option value="open"';
							print_r( $comments_sts === 'open' ? $selected : '' );
							echo '>';
							_e( 'Ouverts' ) . '</option>';
							echo '<option value="closed"';
							print_r( $comments_sts === 'closed' ? $selected : '' );
							echo '>';
							_e( 'Ferm&eacute;s' ) . '</option>';
							?>
						</select>
					</td>
				</tr>
			</table>
			
			<div id="afpf-box-group">
				<div id="afpf-schedule" class="afpf-box">
					<p><label for="afpf_scheduled"><?php _e( "<strong>Activer l'import automatique</strong>", 'afp-feeder' ); ?></label>
						<?php $schedule_check = $this->afp_feeder->isscheduled ? 'checked="checked"' : '' ?>
						<input type="checkbox" <?php echo $schedule_check; ?>name="afpf_scheduled" id="afpf_scheduled"></p>
					<div class="inside closable">
						<label for="afpf_recur"><?php _e( "R&eacute;currence :" ); ?></label>
							<?php $schedules = wp_get_schedules(); ?>
						<select name='afpf_recur'>
							<?php
							foreach ( $schedules as $key => $recur ) :
								$selected = ($key === $this->afp_feeder->recurence) ? ' selected="selected"' : '';
								echo '<option value="' . $key . '"' . $selected . '>' . $recur['display'] . '</option>';
							endforeach;
							?>
						</select>
					</div>
				</div>
				<div id="post-categories" class="afpf-box">
					<?php $this->afpf_category_box(); ?>
				</div>
			</div><!-- #afpf-boxes -->
			
			<div id="afpf-submit">
				<?php
				$attr = array( 'class' => 'afpfsubmit' );
				submit_button( __( 'Enregistrer les modifications' ), 'primary', 'submit', false, $attr );
				submit_button( __( 'Enregistrer et importer' ), 'primary', 'submitandimport', false, $attr );
				?>
			</div>
		</form>
		
		<?php if ( $report = get_transient( 'afpf_report')) : ?>
			<div id="report">
				<ul>
					<?php foreach ($report as $line) : ?>
						<li><?php echo $line['message']; ?></li>
					<?php endforeach; ?>
				</ul>
			<?php delete_transient('afpf_report'); ?>
			</div>
		<?php endif; 
		
		$this->footer();
	} // function afp_option_form
}