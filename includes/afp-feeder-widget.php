<?php

class AFP_Feeder_widget extends WP_Widget {
	
	var $max_per_page;
	var $nav_mode;

	public function __construct() {
		parent::__construct( 'afp_feeder_widget', 'D&eacute;p&ecirc;ches AFP', array(
			'description' => 'Affiche les derni&egrave;res d&eacute;p&ecirc;ches AFP',
		) );
		add_action('widgets_init', function(){register_widget('AFP_Feeder_widget');});
		add_action( 'wp_ajax_afpfw_get_page', array( $this, 'afpfw_get_page' ) );
		add_action( 'wp_ajax_nopriv_afpfw_get_page', array( $this, 'afpfw_get_page' ) );
	}
	
	/**
	 * Requête et pagination : appelé au chargement de la home et via ajax pour la nav
	 * 
	 */
	function afpfw_get_page(){
		$afpfw_max_per_page = isset($_GET['afpfw_max_per_page']) ? $_GET['afpfw_max_per_page'] : $this->max_per_page;
		$afpfw_paged =  isset( $_GET['afpfw_paged'] ) ? absint( $_GET['afpfw_paged'] ) : 1;
				
		$params = array(
			'post_type'		=> 'afpfeed',
			'posts_per_page'=> $afpfw_max_per_page,
			'paged'			=> $afpfw_paged,
		);
		$afpf_query = new WP_Query( $params ); ?>
		<div id="afpfw_page_<?php echo $afpfw_paged ?>" class="afpfw_page">
		<?php if ( $afpf_query->have_posts() ) : ?>
				<?php while ( $afpf_query->have_posts() ) : ?>
					<?php $afpf_query->the_post(); ?>
					<div class="afp-hour"><?php the_time( 'H:i') ?></div>
					<div class="afp-title" id="<?php the_ID() ?>"><a href="<?php the_permalink() ?>" rel="bookmark"><?php the_title() ?></a></div>
				<?php endwhile; ?>
			<?php endif; ?>
		</div>
		<?php if (isset($_GET['afpfw_ajax'])) {
			die();
		}
	}

	/**
	 * Affichage widget
	 * 
	 * @param type $args
	 * @param type $instance
	 */
	public function widget( $args, $instance ) {
		$afpf_content_height = isset($instance['afpf_content_height']) ? 'style="height: ' . $instance['afpf_content_height'] . ';"'  : '';
		
		$this->max_per_page = isset($instance['afpf_per_page']) ? $instance['afpf_per_page'] : 7;
		$this->nav_mode = isset($instance['afpf_nav_mode']) ? $instance['afpf_nav_mode'] : 'archives';
		$afpfw_total_pages = ceil( wp_count_posts( 'afpfeed' )->publish / $this->max_per_page );
		
		wp_enqueue_style( 'afpfw-script', AFP_FEEDER_URL . '/includes/afp-feeder-widget-style.css');
		
		$afpfw_nav = __('+ TOUTES LES D&Eactue;P&Ecirc;CHES', 'afp-feeder');
				
		if ( 'ajax' === $this->nav_mode ) {
			wp_enqueue_script( 'afpfw-script', AFP_FEEDER_URL . '/includes/afp-feeder-widget-script.js', array(), 1.0, true );
			wp_localize_script('afpfw-script', 'afpfw_vars', array(
				'afpfw_max_per_page'	=> $this->max_per_page,
				'afpfw_total_pages'		=> $afpfw_total_pages,
				'ajaxurl'				=> admin_url('admin-ajax.php'),
				) );
			$afpfw_nav = __('+ LES D&Eactue;P&Ecirc;CHES PR&Eactue;C&Eactue;DENTES', 'afp-feeder');
		}
		?>
		<div id="afpfw-container" class="afpfw-container">
			<p>Le direct <img src='' alt='AFP' /></p>
			<div id="afpfw-content" class="afpfw-content" <?php echo $afpf_content_height?>>
				<?php $this->afpfw_get_page(); ?>
			</div>

		</div>
		<a href=<?php echo get_post_type_archive_link( 'afpfeed' ); ?> id="afpfw-more" class="afpfw-nav" value="1">
				<?php _e('+ TOUTES LES D&Eacute;P&Ecirc;CHES') ?>
		</a>

		<?php
	}
	/**
	 * Formulaire backo pour les options de widget
	 * 
	 * @param type $instance
	 */
	public function form($instance) {
		$archive_status = $ajax_status = '';
		
		$afpf_per_page = (!empty($instance['afpf_per_page'])) ? ($instance['afpf_per_page']) : '';
		$afpf_content_height = (!empty($instance['afpf_content_height'])) ? ($instance['afpf_content_height']) : '';
		$afpf_nav_mode = (!empty($instance['afpf_nav_mode'])) ? ($instance['afpf_nav_mode']) : '';
		
		// Default -> archives
		'ajax' === $afpf_nav_mode ? $ajax_status= 'checked="checked"' : $archive_status = 'checked="checked"';

    ?>
    <p>
        <label for="<?php echo $this->get_field_name( 'afpf_per_page' ); ?>"><?php _e( 'Nombre de d&eacute;p&ecirc;ches visibles <br />(0-99) :', 'afp-feeder' ); ?></label>
        <input id="<?php echo $this->get_field_id( 'afpf_per_page' ); ?>" name="<?php echo $this->get_field_name( 'afpf_per_page' ); ?>" 
			   type="text" value="<?php echo  $afpf_per_page; ?>" size="2" maxlength="10"/>
    </p>
		<p>
        <label for="<?php echo $this->get_field_name( 'afpf_content_height' ); ?>"><?php _e( 'Hauteur du bloc de contenu <br /> (ex "400px", "100%") :', 'afp-feeder' ); ?></label>
        <input id="<?php echo $this->get_field_id( 'afpf_content_height' ); ?>" name="<?php echo $this->get_field_name( 'afpf_content_height' ); ?>" 
			   type="text" value="<?php echo  $afpf_content_height; ?>" size="5" />
    </p>
	<p><?php _e('Mode de navigation :', 'afp-feeder'); ?></p>
	<p><label for="<?php echo $this->get_field_name( 'afpf_nav_mode' ); ?>"><?php _e( 'Page d\'archives', 'afp-feeder' ); ?></label>
	<input type="radio" id="<?php echo $this->get_field_id( 'afpf_nav_mode' ); ?>" 
		   name="<?php echo $this->get_field_name( 'afpf_nav_mode' ); ?>" value="archives" <?php echo $archive_status; ?>/>
	<label for="<?php echo $this->get_field_name( 'afpf_nav_mode' ); ?>"><?php _e( 'Ajax', 'afp-feeder' ); ?></label>
	<input type="radio" id="<?php echo $this->get_field_id( 'afpf_nav_mode' ); ?>" 
		   name="<?php echo $this->get_field_name( 'afpf_nav_mode' ); ?>" value="ajax" <?php echo $ajax_status; ?>/>
	</p>
    <?php
	
	}

}
