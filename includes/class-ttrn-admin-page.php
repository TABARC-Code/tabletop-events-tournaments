<?php
/**
 * The "Manage Players & Pairings" admin screen — a JS-driven tool in
 * the same shape as the front-end shortcodes elsewhere in this
 * family, just living in wp-admin and using WordPress's own REST
 * nonce for auth instead of a public token.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TTRN_Admin_Page {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function add_page() {
		add_submenu_page(
			'edit.php?post_type=' . TEC_POST_TYPE,
			__( 'Manage Pairings', 'tabletop-events-tournaments' ),
			__( 'Manage Pairings', 'tabletop-events-tournaments' ),
			'manage_options',
			'ttrn-manage',
			array( $this, 'render_page' )
		);
	}

	public function enqueue( $hook ) {
		if ( false === strpos( $hook, 'ttrn-manage' ) ) {
			return;
		}
		wp_enqueue_style( 'ttrn-admin', TTRN_PLUGIN_URL . 'assets/css/tournament-admin.css', array(), TTRN_VERSION );
		wp_enqueue_script( 'ttrn-admin', TTRN_PLUGIN_URL . 'assets/js/tournament-admin.js', array(), TTRN_VERSION, true );

		wp_localize_script(
			'ttrn-admin',
			'TTRN_ADMIN',
			array(
				'restUrl'      => esc_url_raw( rest_url( 'ttrn/v1' ) ),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'tournamentId' => isset( $_GET['tournament'] ) ? (int) $_GET['tournament'] : 0,
			)
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tournament_id = isset( $_GET['tournament'] ) ? (int) $_GET['tournament'] : 0;

		if ( class_exists( 'TEC_Admin' ) ) {
			TEC_Admin::page_header(
				'awards',
				__( 'Manage Pairings', 'tabletop-events-tournaments' ),
				__( 'Add players, generate Swiss pairings round by round, and record results.', 'tabletop-events-tournaments' )
			);
		}

		if ( ! $tournament_id ) {
			$this->render_picker();
			return;
		}
		?>
		<div class="wrap">
			<div id="ttrn-admin-root" data-ttrn-admin></div>
		</div>
		<?php
	}

	private function render_picker() {
		$tournaments = get_posts(
			array(
				'post_type'      => TTRN_POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		?>
		<div class="wrap">
			<p><?php esc_html_e( 'Pick a tournament to manage, or create one first.', 'tabletop-events-tournaments' ); ?></p>
			<?php if ( ! $tournaments ) : ?>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . TTRN_POST_TYPE ) ); ?>">
						<?php esc_html_e( 'Add New Tournament', 'tabletop-events-tournaments' ); ?>
					</a>
				</p>
			<?php else : ?>
				<ul>
					<?php foreach ( $tournaments as $t ) : ?>
						<li>
							<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . TEC_POST_TYPE . '&page=ttrn-manage&tournament=' . $t->ID ) ); ?>">
								<?php echo esc_html( get_the_title( $t ) ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}
}
