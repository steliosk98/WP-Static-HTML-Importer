<?php
/**
 * Plugin Name: Static HTML Importer
 * Description: Import static HTML files as WordPress pages.
 * Version: 0.1.0
 * Author: Stelios Kiliaris
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Static_HTML_Importer' ) ) {
	class Static_HTML_Importer {
		const NONCE_ACTION = 'ship_import_action';
		const NONCE_NAME   = 'ship_import_nonce';
		const MENU_SLUG    = 'static-html-importer';

		public function __construct() {
			add_action( 'admin_menu', array( $this, 'register_menu' ) );
			add_action( 'admin_post_ship_import', array( $this, 'handle_import' ) );
			add_action( 'admin_notices', array( $this, 'render_notices' ) );
		}

		public function register_menu() {
			add_menu_page(
				__( 'HTML Importer', 'static-html-importer' ),
				__( 'HTML Importer', 'static-html-importer' ),
				'manage_options',
				self::MENU_SLUG,
				array( $this, 'render_admin_page' )
			);
		}

		public function render_admin_page() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to access this page.', 'static-html-importer' ) );
			}

			$form_action = esc_url( admin_url( 'admin-post.php' ) );
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Static HTML Importer', 'static-html-importer' ); ?></h1>
				<form method="post" action="<?php echo $form_action; ?>" enctype="multipart/form-data">
					<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
					<input type="hidden" name="action" value="ship_import" />
					<table class="form-table" role="presentation">
						<tbody>
						<tr>
							<th scope="row">
								<label for="ship_html_file"><?php esc_html_e( 'HTML File', 'static-html-importer' ); ?></label>
							</th>
							<td>
								<input type="file" id="ship_html_file" name="ship_html_file" accept=".html,.htm" required />
								<p class="description"><?php esc_html_e( 'Upload a .html or .htm file to import as a page.', 'static-html-importer' ); ?></p>
							</td>
						</tr>
						</tbody>
					</table>
					<?php submit_button( __( 'Import HTML', 'static-html-importer' ) ); ?>
				</form>
			</div>
			<?php
		}

		public function handle_import() {
			if ( ! current_user_can( 'manage_options' ) ) {
				$this->redirect_with_message( 'error', 'permission' );
			}

			if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( wp_unslash( $_POST[ self::NONCE_NAME ] ), self::NONCE_ACTION ) ) {
				$this->redirect_with_message( 'error', 'nonce' );
			}

			if ( ! isset( $_FILES['ship_html_file'] ) || ! is_array( $_FILES['ship_html_file'] ) ) {
				$this->redirect_with_message( 'error', 'missing' );
			}

			$file = $_FILES['ship_html_file'];

			if ( ! empty( $file['error'] ) ) {
				$this->redirect_with_message( 'error', 'upload' );
			}

			if ( empty( $file['size'] ) ) {
				$this->redirect_with_message( 'error', 'empty' );
			}

			$filename = isset( $file['name'] ) ? sanitize_file_name( wp_unslash( $file['name'] ) ) : '';
			$ext      = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

			if ( ! in_array( $ext, array( 'html', 'htm' ), true ) ) {
				$this->redirect_with_message( 'error', 'type' );
			}

			$contents = file_get_contents( $file['tmp_name'] );

			if ( false === $contents ) {
				$this->redirect_with_message( 'error', 'read' );
			}

			$parsed = $this->parse_html( $contents, $filename );

			$post_id = wp_insert_post(
				array(
					'post_title'   => sanitize_text_field( $parsed['title'] ),
					'post_content' => wp_kses_post( $parsed['content'] ),
					'post_status'  => 'publish',
					'post_type'    => 'page',
				)
			);

			if ( is_wp_error( $post_id ) || 0 === $post_id ) {
				$this->redirect_with_message( 'error', 'insert' );
			}

			$this->redirect_with_message( 'success', 'imported' );
		}

		private function parse_html( $html, $filename ) {
			$title   = '';
			$content = '';

			if ( preg_match( '/<title>(.*?)<\/title>/is', $html, $title_match ) ) {
				$title = trim( wp_strip_all_tags( $title_match[1] ) );
			}

			if ( preg_match( '/<body[^>]*>(.*?)<\/body>/is', $html, $body_match ) ) {
				$content = trim( $body_match[1] );
			}

			if ( '' === $title ) {
				$title = $filename ? pathinfo( $filename, PATHINFO_FILENAME ) : __( 'Imported Page', 'static-html-importer' );
			}

			if ( '' === $content ) {
				$content = $html;
			}

			return array(
				'title'   => $title,
				'content' => $content,
			);
		}

		private function redirect_with_message( $status, $code ) {
			$base_url = add_query_arg(
				array(
					'page'          => self::MENU_SLUG,
					'ship_status'   => $status,
					'ship_message'  => $code,
				),
				admin_url( 'admin.php' )
			);

			wp_safe_redirect( $base_url );
			exit;
		}

		public function render_notices() {
			if ( ! isset( $_GET['ship_status'], $_GET['ship_message'] ) ) {
				return;
			}

			$status  = sanitize_text_field( wp_unslash( $_GET['ship_status'] ) );
			$message = sanitize_text_field( wp_unslash( $_GET['ship_message'] ) );

			$notice = '';

			if ( 'success' === $status && 'imported' === $message ) {
				$notice = __( 'HTML file imported successfully.', 'static-html-importer' );
			} elseif ( 'error' === $status ) {
				$errors = array(
					'permission' => __( 'You do not have permission to perform this action.', 'static-html-importer' ),
					'nonce'      => __( 'Security check failed. Please try again.', 'static-html-importer' ),
					'missing'    => __( 'No file was uploaded.', 'static-html-importer' ),
					'upload'     => __( 'There was an error uploading the file.', 'static-html-importer' ),
					'empty'      => __( 'Uploaded file is empty.', 'static-html-importer' ),
					'type'       => __( 'Invalid file type. Please upload an .html or .htm file.', 'static-html-importer' ),
					'read'       => __( 'Could not read the uploaded file.', 'static-html-importer' ),
					'insert'     => __( 'Failed to create the page.', 'static-html-importer' ),
				);

				$notice = $errors[ $message ] ?? __( 'An unknown error occurred.', 'static-html-importer' );
			}

			if ( '' === $notice ) {
				return;
			}

			$class = ( 'success' === $status ) ? 'notice-success' : 'notice-error';
			?>
			<div class="notice <?php echo esc_attr( $class ); ?> is-dismissible">
				<p><?php echo esc_html( $notice ); ?></p>
			</div>
			<?php
		}
	}

	new Static_HTML_Importer();
}
