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
		<ul class="list-afp">
		<?php if ( $afpf_query->have_posts() ) : ?>
				<?php while ( $afpf_query->have_posts() ) : ?>
					<?php $afpf_query->the_post(); ?>
					<li class="list-afp__item">
						<a href="<?php the_permalink() ?>" class="text-13 c-black"><strong><?php the_time( 'H:i') ?></strong>
						<?php the_title() ?></a>
					</li>
				<?php endwhile; ?>
			<?php endif; ?>
		</ul>
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
			$afpfw_nav = __('+ LES D&Eacute;P&Ecirc;CHES PR&Eacute;C&Eacute;DENTES', 'afp-feeder');
		}
		?>
		<div class="box-afp margin-t-40 box--padded b-white">
			<h3 class="box-afp__header heading-4"><a href="#"><span class="c-afp">Le direct 
						<i class="icon icon-afp size30"></i></span></a></h3>
				<?php $this->afpfw_get_page(); ?>
			</ul>

		</div>

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
