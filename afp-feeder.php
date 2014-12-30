<?php

/*
  Plugin Name: AFP Feeder
  Author: Thibault Rivrain
  Description: Import ponctuel et automatique des dêpeches AFP dans le cadre de l'offre AFP Texte/FTP.
  License: GPL version 3 or later - http://www.gnu.org/licenses/old-licenses/gpl-3.0.html
  Version: b0.1
 */

define( 'AFP_FEEDER_PATH', plugin_dir_path( __FILE__ ) );

define( 'AFP_FEEDER_URL', plugin_dir_url( __FILE__ ) ); 


// Charger Importer API
require_once ABSPATH . 'wp-admin/includes/import.php';

require_once AFP_FEEDER_PATH . '/includes/afp-feeder-admin.php';
include_once AFP_FEEDER_PATH . '/includes/afp-feeder-widget.php';

if ( !class_exists( 'WP_Importer' ) ) {
	$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
	if ( file_exists( $class_wp_importer ) )
		require_once $class_wp_importer;
}

// Pour l'import "offline" ; pas d'appel à la methode admin_url, on charge le minimum vital
if ( !function_exists( 'get_home_path' ) )
	require_once( dirname( __FILE__ ) . '/../../../wp-admin/includes/file.php' );

if ( !function_exists( 'post_exists' ) )
	require_once( dirname( __FILE__ ) . '/../../../wp-admin/includes/post.php' );

if ( !function_exists( 'wp_create_categories' ) )
	require_once( dirname( __FILE__ ) . '/../../../wp-admin/includes/taxonomy.php' );

/**
 * AFP Feeder
 * 
 * TODO:
 * - erreurs champs obligatoires
 * - revoir settings errors
 * - revoir gestion rapport d'import dans admin
 * - gestion fichiers xml
 */
if ( class_exists( 'WP_Importer' ) ) {

	class AFP_Feeder extends WP_Importer {

		const POST_TYPE = 'afpfeed';

		var $posts = array();
		var $base_path;
		var $post_author;
		var $post_status;
		var $comment_status;
		var $categories = array();
		var $isscheduled;
		var $recurence;
		var $last_imported;

		function __construct() {

			register_activation_hook( __FILE__, array( $this, 'afp_feeder_activate' ) );
			register_deactivation_hook( __FILE__, array( $this, 'afp_feeder_deactivate' ) );
			add_action( 'init', array( $this, 'register_afpfeed_type' ) );
			add_action( 'afpimport', array( $this, 'import' ) );
			add_action( 'admin_init', array( $this, 'register_afpf_settings' ) );

			$this->get_saved_options();

			$afpf_admin = new AFP_Feeder_admin( $this );
			$widget = new AFP_Feeder_widget();
		}

		/**
		 * Assigne aux propriétées options de l'instance AFP Feeder les valeurs actuellement sauvegardées dans la base de données
		 */
		function get_saved_options() {
			$options = get_option( 'afpf_general_settings' );

			if ( $options ) {
				$this->base_path = $options['base_path'];
				$this->post_author = $options['author'];
				$this->post_status = $options['status'];
				$this->comment_status = $options['comments'];
				$this->categories = explode( ',', $options['cats'] );
				$this->isscheduled = $options['scheduled'];
				$this->recurence = $options['recur'];
				$this->last_imported = $options['lastimport'];
			}
		}

		/**
		 * Enregistre la date d'import dans les options serialisée dans wp options.
		 */
		function set_last_imported() {
			$options = get_option( 'afpf_general_settings' );
			$options['lastimport'] = time() + ( get_option( 'gmt_offset' ) * 3600 );
			update_option( 'afpf_general_settings', $options );
		}
		
		function get_last_imported() {
			$options = get_option( 'afpf_general_settings' );
			if ( $options && !empty( $options['lastimport'] ) ) {
				return $options['lastimport'];
			}
			return false;
		}

		function afp_feeder_activate() {
			$this->register_afpfeed_type();
			flush_rewrite_rules();
		}

		function afp_feeder_deactivate() {
			wp_clear_scheduled_hook( 'afpimport' );
			
			if ( isset( $wp_post_types[self::POST_TYPE] ) ) {
				unset( $wp_post_types[self::POST_TYPE] );
			}
			delete_option( 'afpf_general_settings' );
		}

		function register_afpf_settings() {
			register_setting( 'afpf_general', 'afpf_general_settings' );
		}

		function register_afpfeed_type() {

			$labels = array(
				'name' => _x( 'D&eacute;p&ecirc;ches AFP', 'post type general name', 'afp-feeder' ),
				'singular_name' => _x( 'D&eacute;p&ecirc;che AFP', 'post type singular name', 'afp-feeder' ),
			);

			$args = array(
				'labels' => $labels,
				'public' => true,
				'publicly_queryable' => true,
				'show_ui' => true,
				'show_in_menu' => 'afpfeeder',
				'show_in_admin_bar' => false,
				'query_var' => true,
				'rewrite' => array( 'slug' => __( 'depecheafp', 'afp-feeder' ) ),
				'capability_type' => 'post',
				'has_archive' => true,
				'taxonomies' => array( 'category', 'post_tag' ),
			);

			register_post_type( 'afpfeed', $args );
		}

		/**
		 * Transforme la date des depeche AFP au format reconnu par WP
		 */
		function validate_afp_date( $inDate ) {
			$inFormat = 'Ymd\THis\Z';
			$outFormat = 'Y-m-d H:i:s';
			$date = date_create_from_format( $inFormat, $inDate );

			if ( ($date < date_create()) && ( date_format( $date, $inFormat ) === $inDate ) ) {
				return date_format( $date, $outFormat ); // Ou retourne rien et laisse wp publier à la date&heure actuelle
			}
		}

		/**
		 * Charge le contenu de index.xml et stocke le contenu du xml pour chaque depeche dans la tabeau $this->posts
		 * N'effectue aucune validation ni traitement.
		 */
		function loadxml() {

			// Verifier si le chemin d'accès est absolu ou rel et assurer qu'il se termine par /
			if ( !preg_match( '#^/#', $this->base_path ) )
				$this->base_path = get_home_path() . $this->base_path;
			if ( !preg_match( '#/$#', $this->base_path ) )
				$this->base_path .= '/';

			libxml_use_internal_errors( true );
			if ( !$feed_index = simplexml_load_file( $this->base_path . 'index.xml' ) ) {
				if ( libxml_get_errors() ) {
					$msg = __( "Une erreur est intervenue lors de la r&eacute;cup&eacute;ration du fichier <strong><em>index.xml</em></strong>"
							. " à l'emplacement : '<strong><em>$this->base_path</em></strong>' .<br />"
							. "Veuillez v&eacute;rifier le chemin d'acc&egrave;s d&eacute;fini et vous assurer de la pr&eacute;sence du fichier." );
					add_settings_error( 'afpf_general_settings', 'report-status', __( $msg ), 'error' );
					libxml_clear_errors();
					libxml_use_internal_errors( false );
					return false;					
				}
			}
			
			if ( $this->get_last_imported() && ( $this->get_last_imported() > (filemtime( $this->base_path) + ( get_option( 'gmt_offset' ) * 3600 ) ) ) ) {
				$msg = __( "Il n'y a pas de nouvelles d&eacute;p&ecirc;ches &agrave; importer." );
				add_settings_error( 'afpf_general_settings', 'report-status', __( $msg ), 'updated' );
				return false;
			}
			
			foreach ( $feed_index->xpath( "//NewsComponent" ) as $newsitem ) {
				if ( is_null( $itemRef = $newsitem->NewsItemRef['NewsItem'] ) ) {
					continue;
				}

				//$xmlWire = simplexml_load_file( $this->base_path . $itemRef );

				libxml_use_internal_errors( true );
				if ( !$xmlWire = simplexml_load_file( $this->base_path . $itemRef ) ) {
					foreach ( libxml_get_errors() as $error ) {
						$msg = __( "Une erreur est intervenue lors de la r&eacute;cup&eacute;ration du fichier individuel de d&eacute;p&ecirc;che à l'emplacement : "
								. "'<strong><em>$this->base_path . $itemRef</em>'</strong>." );
						add_settings_error( 'afpf_report', 'report-status', __( $msg ), 'failed' );
					}
					libxml_clear_errors();
					continue;
				}

				$date = $xmlWire->NewsItem->NewsManagement->FirstCreated;
				$title = $xmlWire->NewsItem->NewsComponent->NewsLines->HeadLine;
				$tags = $xmlWire->NewsItem->NewsComponent->NewsLines->SlugLine;
				$body = array();
				foreach ( $xmlWire->xpath( "//p" ) as $bodyAfp ) {
					array_push( $body, $bodyAfp );
				}
				$post = compact( 'date', 'title', 'tags', 'body' );

				if ( $post ) {
					array_push( $this->posts, $post );
				}
				libxml_use_internal_errors( false );
			}
			return true;
		}

		/**
		 * Examine les valeurs stockés dans $posts par load_xml() et construit des post wp valides
		 * Echappement de toutes les balise <>. 
		 */
		function get_posts() {

			if ( !$this->loadxml() ) {
				return false;
			} else

			$index = 0;
			foreach ( $this->posts as $post ) {
				extract( $post );
				$post_title = wp_strip_all_tags( $title, true );
				$post_date = $this->validate_afp_date( wp_strip_all_tags( $date, true ) );

				$post_content = '';
				foreach ( $body as $paragraph ) {
					$post_content .= wp_strip_all_tags( $paragraph, true ) . '<br />';
				}
				
				$tags_input = array(); // TLB NO TAG
//				$tags_input = explode( '-', $tags );
//
//				$tag_index = 0;
//				foreach ( $tags_input as $tag ) {
//					$tags_input[$tag_index] = wp_strip_all_tags( $tag, true );
//					$tag_index++;
//				}

				$post_author = $this->post_author;
				$categories = $this->categories;
				$cat_index = 0;
				foreach ( $categories as $cat ) {
					$slug = get_category( $cat )->slug;
					$categories[$cat_index] = $slug;
					$cat_index++;
				}
				$post_status = $this->post_status;
				$comment_status = $this->comment_status;
				$post_type = self::POST_TYPE;

				$this->posts[$index] = compact( 'post_author', 'post_date', 'post_content', 'post_title', 'post_status', 'comment_status', 'categories', 'tags_input', 'post_type' );
				$index++;
			}
			return true;
		}

		function import_posts() {

			foreach ( $this->posts as $post ) {
				$msg = __( 'Import en cours...', 'afp-feeder' );

				extract( $post );

				if ( $post_id = post_exists( $post_title, $post_content, $post_date ) ) {
					$msg .= __( 'D&eacute;p&ecirc;che d&eacute;jà import&eacute;e', 'afp-feeder' );
				} else {
					$post_id = wp_insert_post( $post );
					if ( is_wp_error( $post_id ) ) {
						return $post_id;
					}
					if ( 0 != count( $categories ) ) {
						wp_create_categories( $categories, $post_id );
					}
					$msg .= __( 'Et voilà !', 'afp-feeder' );
				}
				add_settings_error( 'afpf_report', 'import-status', __( $msg ), 'succeeded' );
			}
		}

		function import() {
			

			if ( $this->get_posts() ) {
				$result = $this->import_posts();
				if ( is_wp_error( $result ) )
					return $result;
				do_action( 'import_done', 'afpfeeder' );
				$this->set_last_imported();
				$msg = __("L'import &agrave; &eacute;t&eacute; effectu&eacute;. "
						. 'Vous pouvez consulter le rapport <a href="#report">ci dessous</a>.', 'afp-feeder');
				add_settings_error( 'afpf_general_settings', 'import', __( $msg ), 'updated' );

			}
		}

	}// Class AFP_Feeder

	$afp_feed_import = new AFP_Feeder();

	register_importer( 'afpfeeder', __( 'AFP Feeder', 'afp-feeder' ), __( "Importe des posts pour l'offre AFP texte/FTP.", 'afp-feeder' ), array( $afp_feed_import, 'dispatch' ) );
} // class_exists( 'WP_Importer' )