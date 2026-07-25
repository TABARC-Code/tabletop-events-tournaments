<?php
/**
 * The ttrn_tournament CPT: one tournament, tied to one tec_event.
 * Not public — pairings and results are edited from wp-admin (see
 * TTRN_Admin_Page) and read from the public standings shortcode via
 * REST, so there's nothing for a theme-rendered single/archive page
 * to do with it.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TTRN_Post_Type {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post_' . TTRN_POST_TYPE, array( $this, 'save_meta' ), 10, 2 );
	}

	public function register_post_type() {
		register_post_type(
			TTRN_POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Tournaments', 'tabletop-events-tournaments' ),
					'singular_name' => __( 'Tournament', 'tabletop-events-tournaments' ),
					'add_new_item'  => __( 'Add New Tournament', 'tabletop-events-tournaments' ),
					'edit_item'     => __( 'Edit Tournament', 'tabletop-events-tournaments' ),
					'all_items'     => __( 'Tournaments', 'tabletop-events-tournaments' ),
					'search_items'  => __( 'Search Tournaments', 'tabletop-events-tournaments' ),
					'not_found'     => __( 'No tournaments found.', 'tabletop-events-tournaments' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'edit.php?post_type=' . TEC_POST_TYPE,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'supports'            => array( 'title' ),
				'show_in_rest'        => false, // Vetted through /ttrn/v1/, not the default REST controller.
			)
		);
	}

	public function add_meta_box() {
		add_meta_box(
			'ttrn_tournament_meta',
			__( 'Tournament', 'tabletop-events-tournaments' ),
			array( $this, 'render_meta_box' ),
			TTRN_POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Deliberately just the event link and rounds — the actual
	 * player/pairing/results workflow lives on its own admin page
	 * (TTRN_Admin_Page), where there's room for a proper table rather
	 * than squeezing a live Swiss-pairing tool into a meta box.
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( 'ttrn_save_tournament_meta', 'ttrn_tournament_meta_nonce' );

		$event_id     = (int) get_post_meta( $post->ID, '_ttrn_event_id', true );
		$rounds_total = (int) get_post_meta( $post->ID, '_ttrn_rounds_total', true ) ?: 4;
		?>
		<p>
			<label for="ttrn_event_id"><strong><?php esc_html_e( 'Event ID', 'tabletop-events-tournaments' ); ?></strong></label><br>
			<input type="number" min="1" name="ttrn_event_id" id="ttrn_event_id" value="<?php echo esc_attr( $event_id ); ?>">
			<?php if ( $event_id ) : ?>
				<a href="<?php echo esc_url( get_edit_post_link( $event_id ) ); ?>"><?php echo esc_html( get_the_title( $event_id ) ); ?></a>
			<?php endif; ?>
		</p>
		<p>
			<label for="ttrn_rounds_total"><strong><?php esc_html_e( 'Planned rounds', 'tabletop-events-tournaments' ); ?></strong></label><br>
			<input type="number" min="1" name="ttrn_rounds_total" id="ttrn_rounds_total" value="<?php echo esc_attr( $rounds_total ); ?>">
			<p class="description"><?php esc_html_e( 'Safe to raise once the tournament is under way if it runs long — it can\'t be dropped below however many rounds have already been generated.', 'tabletop-events-tournaments' ); ?></p>
		</p>
		<?php if ( $post->ID && get_post_status( $post->ID ) !== 'auto-draft' ) : ?>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . TEC_POST_TYPE . '&page=ttrn-manage&tournament=' . $post->ID ) ); ?>">
					<?php esc_html_e( 'Manage Players & Pairings', 'tabletop-events-tournaments' ); ?>
				</a>
			</p>
		<?php endif; ?>
		<?php
	}

	public function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['ttrn_tournament_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ttrn_tournament_meta_nonce'] ) ), 'ttrn_save_tournament_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['ttrn_event_id'] ) ) {
			update_post_meta( $post_id, '_ttrn_event_id', (int) $_POST['ttrn_event_id'] );
		}
		// Can go up any time (an organiser running long and wanting one
		// more round is a completely normal thing to want mid-event),
		// but never below whatever round's already been generated —
		// that would leave a "Generate Round N" button pointing past a
		// total that no longer covers the rounds already played.
		if ( isset( $_POST['ttrn_rounds_total'] ) ) {
			$current_round = (int) get_post_meta( $post_id, '_ttrn_current_round', true );
			$floor         = max( 1, $current_round );
			update_post_meta( $post_id, '_ttrn_rounds_total', max( $floor, (int) $_POST['ttrn_rounds_total'] ) );
		}
	}
}
