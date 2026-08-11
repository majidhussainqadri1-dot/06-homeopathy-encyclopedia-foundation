<?php
/** Moderation, feedback resolution, audit visibility, and administrator notices. */

defined( 'ABSPATH' ) || exit;

final class HE_Admin {
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_he_review_entry', array( $this, 'review' ) );
		add_action( 'admin_post_he_resolve_feedback', array( $this, 'resolve' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
	}

	public function menu() {
		add_menu_page( __( 'Encyclopedia Management', 'homeopathy-encyclopedia' ), __( 'Encyclopedia', 'homeopathy-encyclopedia' ), HE_Permissions::CAP_REVIEW, 'encyclopedia-management', array( $this, 'dashboard' ), 'dashicons-book-alt', 30 );
		add_submenu_page( 'encyclopedia-management', __( 'Entry Moderation', 'homeopathy-encyclopedia' ), __( 'Entry Moderation', 'homeopathy-encyclopedia' ), HE_Permissions::CAP_REVIEW, 'encyclopedia-management', array( $this, 'dashboard' ) );
		add_submenu_page( 'encyclopedia-management', __( 'Corrections and Reports', 'homeopathy-encyclopedia' ), __( 'Corrections and Reports', 'homeopathy-encyclopedia' ), HE_Permissions::CAP_FEEDBACK, 'encyclopedia-feedback', array( $this, 'feedback' ) );
		add_submenu_page( 'encyclopedia-management', __( 'Audit History', 'homeopathy-encyclopedia' ), __( 'Audit History', 'homeopathy-encyclopedia' ), HE_Permissions::CAP_AUDIT, 'encyclopedia-audit', array( $this, 'audit_page' ) );
		add_submenu_page( 'encyclopedia-management', __( 'All Entries', 'homeopathy-encyclopedia' ), __( 'All Entries', 'homeopathy-encyclopedia' ), HE_Permissions::CAP_MANAGE, 'edit.php?post_type=' . HE_Content::TYPE );
	}

	public function dashboard() {
		$this->guard( HE_Permissions::CAP_REVIEW );
		$status = isset( $_GET['entry_status'] ) ? sanitize_key( wp_unslash( $_GET['entry_status'] ) ) : 'pending';
		if ( ! in_array( $status, array( 'pending', 'publish', 'draft', 'private' ), true ) ) {
			$status = 'pending';
		}
		$page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$query = new WP_Query(
			array(
				'post_type' => HE_Content::TYPE,
				'post_status' => $status,
				'posts_per_page' => 30,
				'paged' => $page,
				'orderby' => 'date',
				'order' => 'DESC',
			)
		);
		?>
		<div class="wrap he-admin"><h1><?php esc_html_e( 'Encyclopedia Management', 'homeopathy-encyclopedia' ); ?></h1><p><?php esc_html_e( 'Review references, author eligibility, relationships, copyright, medical red flags, safety, language, and unsupported claims.', 'homeopathy-encyclopedia' ); ?></p>
		<nav><?php foreach ( array( 'pending' => __( 'Pending', 'homeopathy-encyclopedia' ), 'publish' => __( 'Published', 'homeopathy-encyclopedia' ), 'draft' => __( 'Rejected / Draft', 'homeopathy-encyclopedia' ), 'private' => __( 'Hidden', 'homeopathy-encyclopedia' ) ) as $key => $label ) : ?><a class="<?php echo $status === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'encyclopedia-management', 'entry_status' => $key ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $label ); ?></a><?php endforeach; ?></nav>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Entry', 'homeopathy-encyclopedia' ); ?></th><th><?php esc_html_e( 'Classification and author', 'homeopathy-encyclopedia' ); ?></th><th><?php esc_html_e( 'Governance', 'homeopathy-encyclopedia' ); ?></th><th><?php esc_html_e( 'Review', 'homeopathy-encyclopedia' ); ?></th></tr></thead><tbody>
		<?php if ( $query->posts ) : foreach ( $query->posts as $entry ) : ?><tr><td><strong><?php echo esc_html( $entry->post_title ); ?></strong><p><?php echo esc_html( $entry->post_excerpt ); ?></p><details><summary><?php esc_html_e( 'Review full entry', 'homeopathy-encyclopedia' ); ?></summary><div class="he-admin-content"><?php echo wp_kses_post( wpautop( $entry->post_content ) ); ?><h4><?php esc_html_e( 'References', 'homeopathy-encyclopedia' ); ?></h4><?php echo nl2br( esc_html( HE_Content::meta( $entry->ID, 'references' ) ) ); ?></div></details></td><td><?php echo esc_html( HE_Content::term( $entry->ID ) ); ?><br><?php echo esc_html( HE_Content::term( $entry->ID, HE_Content::SYSTEM ) ); ?><br><?php echo esc_html( get_the_author_meta( 'display_name', $entry->post_author ) ); ?><br><?php echo esc_html( HE_Permissions::label( $entry->post_author ) ); ?></td><td><strong><?php esc_html_e( 'State:', 'homeopathy-encyclopedia' ); ?></strong> <?php echo esc_html( HE_Content::meta( $entry->ID, 'workflow_state' ) ?: 'legacy' ); ?><br><strong><?php esc_html_e( 'Red flags:', 'homeopathy-encyclopedia' ); ?></strong> <?php echo HE_Content::meta( $entry->ID, 'red_flags' ) ? esc_html__( 'Present', 'homeopathy-encyclopedia' ) : esc_html__( 'Not added', 'homeopathy-encyclopedia' ); ?><br><strong><?php esc_html_e( 'Safety:', 'homeopathy-encyclopedia' ); ?></strong> <?php echo HE_Content::meta( $entry->ID, 'safety' ) ? esc_html__( 'Present', 'homeopathy-encyclopedia' ) : esc_html__( 'Not added', 'homeopathy-encyclopedia' ); ?><br><strong><?php esc_html_e( 'Language declaration:', 'homeopathy-encyclopedia' ); ?></strong> <?php echo esc_html( HE_Content::meta( $entry->ID, 'language' ) ?: __( 'Not set', 'homeopathy-encyclopedia' ) ); ?></td><td><?php echo $this->review_form( $entry ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td></tr><?php endforeach; else : ?><tr><td colspan="4"><?php esc_html_e( 'No entries in this view.', 'homeopathy-encyclopedia' ); ?></td></tr><?php endif; ?>
		</tbody></table>
		<?php if ( $query->max_num_pages > 1 ) : ?><div class="tablenav"><div class="tablenav-pages"><?php echo wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'current' => $page, 'total' => $query->max_num_pages ) ) ); ?></div></div><?php endif; ?>
		</div>
		<?php
		wp_reset_postdata();
	}

	private function review_form( $entry ) {
		if ( ! HE_Permissions::can_review_entry( $entry->ID ) ) {
			return '<p>' . esc_html__( 'Self-review is not permitted.', 'homeopathy-encyclopedia' ) . '</p>';
		}
		$version = max( 1, absint( HE_Content::meta( $entry->ID, 'row_version' ) ) );
		ob_start();
		?>
		<form class="he-review" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><input type="hidden" name="action" value="he_review_entry"><input type="hidden" name="entry_id" value="<?php echo absint( $entry->ID ); ?>"><input type="hidden" name="row_version" value="<?php echo absint( $version ); ?>"><?php wp_nonce_field( 'he_review_' . $entry->ID ); ?><label><span class="screen-reader-text"><?php esc_html_e( 'Review decision', 'homeopathy-encyclopedia' ); ?></span><select name="review_action"><option value="approve"><?php esc_html_e( 'Approve and publish', 'homeopathy-encyclopedia' ); ?></option><option value="reject"><?php esc_html_e( 'Reject to draft', 'homeopathy-encyclopedia' ); ?></option><option value="hide"><?php esc_html_e( 'Hide entry', 'homeopathy-encyclopedia' ); ?></option></select></label><label><span class="screen-reader-text"><?php esc_html_e( 'Internal review note', 'homeopathy-encyclopedia' ); ?></span><textarea name="note" rows="3" placeholder="<?php esc_attr_e( 'A note is mandatory for rejection or hiding.', 'homeopathy-encyclopedia' ); ?>"></textarea></label><button class="button button-primary" type="submit"><?php esc_html_e( 'Apply', 'homeopathy-encyclopedia' ); ?></button></form>
		<?php
		return ob_get_clean();
	}

	public function review() {
		$this->guard( HE_Permissions::CAP_REVIEW );
		$entry_id = isset( $_POST['entry_id'] ) ? absint( $_POST['entry_id'] ) : 0;
		check_admin_referer( 'he_review_' . $entry_id );
		$entry = get_post( $entry_id );
		$action = isset( $_POST['review_action'] ) ? sanitize_key( wp_unslash( $_POST['review_action'] ) ) : '';
		$note = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
		$version = isset( $_POST['row_version'] ) ? absint( $_POST['row_version'] ) : 0;
		if ( ! $entry instanceof WP_Post || HE_Content::TYPE !== $entry->post_type || ! in_array( $action, array( 'approve', 'reject', 'hide' ), true ) || ! HE_Permissions::can_review_entry( $entry_id ) ) {
			wp_die( esc_html__( 'Invalid or unauthorized review request.', 'homeopathy-encyclopedia' ), '', array( 'response' => 400 ) );
		}
		if ( in_array( $action, array( 'reject', 'hide' ), true ) && ! trim( $note ) ) {
			wp_die( esc_html__( 'A review note is required when rejecting or hiding an entry.', 'homeopathy-encyclopedia' ), '', array( 'response' => 400 ) );
		}

		$from_state = sanitize_key( HE_Content::meta( $entry_id, 'workflow_state' ) );
		$allowed = array(
			'submitted' => array( 'approve', 'reject', 'hide' ),
			'published' => array( 'approve', 'reject', 'hide' ),
			'rejected' => array( 'approve', 'hide' ),
			'hidden' => array( 'approve', 'reject' ),
			'seeded_draft' => array( 'approve', 'reject', 'hide' ),
		);
		if ( empty( $allowed[ $from_state ] ) || ! in_array( $action, $allowed[ $from_state ], true ) ) {
			wp_die( esc_html__( 'This state transition is not permitted.', 'homeopathy-encyclopedia' ), '', array( 'response' => 409 ) );
		}
		if ( $version !== max( 1, absint( HE_Content::meta( $entry_id, 'row_version' ) ) ) || ! $this->claim_version( $entry_id, $version ) ) {
			wp_die( esc_html__( 'Another reviewer changed this entry. Reload the page before applying a decision.', 'homeopathy-encyclopedia' ), '', array( 'response' => 409 ) );
		}

		$to_state = 'approve' === $action ? 'published' : ( 'reject' === $action ? 'rejected' : 'hidden' );
		$post_status = 'approve' === $action ? 'publish' : ( 'reject' === $action ? 'draft' : 'private' );
		if ( 'approve' === $action ) {
			$error = HE_Content::publication_error( $entry_id );
			if ( $error ) {
				$this->release_version( $entry_id, $version );
				wp_die( esc_html( $error ), '', array( 'response' => 400 ) );
			}
			if ( ! HE_Permissions::is_founder( $entry->post_author ) && ! user_can( $entry->post_author, HE_Permissions::CAP_MANAGE ) && ! HE_Permissions::is_verified_doctor( $entry->post_author ) ) {
				$this->release_version( $entry_id, $version );
				wp_die( esc_html__( 'The author is no longer eligible to publish on the platform.', 'homeopathy-encyclopedia' ), '', array( 'response' => 400 ) );
			}
		}

		$result = wp_update_post( array( 'ID' => $entry_id, 'post_status' => $post_status ), true );
		if ( is_wp_error( $result ) ) {
			$this->release_version( $entry_id, $version );
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 500 ) );
		}
		if ( 'approve' === $action ) {
			update_post_meta( $entry_id, '_he_language_reviewed', 1 );
			update_post_meta( $entry_id, '_he_reviewer_id', get_current_user_id() );
			update_post_meta( $entry_id, '_he_reviewed_at', current_time( 'mysql', true ) );
		}
		update_post_meta( $entry_id, '_he_workflow_state', $to_state );
		update_post_meta( $entry_id, '_he_review_note', $note );
		HE_Database::audit( $entry_id, $action, $from_state, $to_state, $note );
		HE_Database::reindex_entry( $entry_id );
		wp_safe_redirect( add_query_arg( 'reviewed', '1', admin_url( 'admin.php?page=encyclopedia-management' ) ) );
		exit;
	}

	private function claim_version( $entry_id, $version ) {
		global $wpdb;
		return 1 === (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_value=%s WHERE post_id=%d AND meta_key='_he_row_version' AND meta_value=%s",
				(string) ( $version + 1 ),
				absint( $entry_id ),
				(string) $version
			)
		);
	}

	private function release_version( $entry_id, $version ) {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_value=%s WHERE post_id=%d AND meta_key='_he_row_version' AND meta_value=%s",
				(string) $version,
				absint( $entry_id ),
				(string) ( $version + 1 )
			)
		);
	}

	public function feedback() {
		$this->guard( HE_Permissions::CAP_FEEDBACK );
		global $wpdb;
		$page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$per_page = 50;
		$offset = ( $page - 1 ) * $per_page;
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}he_feedback WHERE status='open'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}he_feedback WHERE status='open' ORDER BY created_at DESC,id DESC LIMIT %d OFFSET %d", $per_page, $offset ) );
		?>
		<div class="wrap he-admin"><h1><?php esc_html_e( 'Corrections and Reports', 'homeopathy-encyclopedia' ); ?></h1><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Entry', 'homeopathy-encyclopedia' ); ?></th><th><?php esc_html_e( 'Kind', 'homeopathy-encyclopedia' ); ?></th><th><?php esc_html_e( 'Reason and details', 'homeopathy-encyclopedia' ); ?></th><th><?php esc_html_e( 'Resolution', 'homeopathy-encyclopedia' ); ?></th></tr></thead><tbody><?php if ( $items ) : foreach ( $items as $item ) : ?><tr><td><a href="<?php echo esc_url( get_permalink( $item->entry_id ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( get_the_title( $item->entry_id ) ); ?></a></td><td><?php echo esc_html( ucfirst( $item->kind ) ); ?></td><td><strong><?php echo esc_html( ucwords( str_replace( '-', ' ', $item->reason ) ) ); ?></strong><p><?php echo esc_html( $item->details ); ?></p></td><td><form class="he-review" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><input type="hidden" name="action" value="he_resolve_feedback"><input type="hidden" name="feedback_id" value="<?php echo absint( $item->id ); ?>"><input type="hidden" name="row_version" value="<?php echo absint( $item->row_version ); ?>"><?php wp_nonce_field( 'he_resolve_' . $item->id ); ?><select name="disposition" required><option value=""><?php esc_html_e( 'Choose disposition', 'homeopathy-encyclopedia' ); ?></option><option value="corrected"><?php esc_html_e( 'Entry corrected', 'homeopathy-encyclopedia' ); ?></option><option value="no-action"><?php esc_html_e( 'No action required', 'homeopathy-encyclopedia' ); ?></option><option value="duplicate"><?php esc_html_e( 'Duplicate report', 'homeopathy-encyclopedia' ); ?></option><option value="escalated"><?php esc_html_e( 'Escalated for further review', 'homeopathy-encyclopedia' ); ?></option><option value="copyright-action"><?php esc_html_e( 'Copyright action taken', 'homeopathy-encyclopedia' ); ?></option></select><textarea name="resolution_note" rows="3" required placeholder="<?php esc_attr_e( 'Record what was checked and decided.', 'homeopathy-encyclopedia' ); ?>"></textarea><button class="button" type="submit"><?php esc_html_e( 'Resolve', 'homeopathy-encyclopedia' ); ?></button></form></td></tr><?php endforeach; else : ?><tr><td colspan="4"><?php esc_html_e( 'No open submissions.', 'homeopathy-encyclopedia' ); ?></td></tr><?php endif; ?></tbody></table>
		<?php $pages = max( 1, (int) ceil( $total / $per_page ) ); if ( $pages > 1 ) : ?><div class="tablenav"><div class="tablenav-pages"><?php echo wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'current' => $page, 'total' => $pages ) ) ); ?></div></div><?php endif; ?></div>
		<?php
	}

	public function resolve() {
		$this->guard( HE_Permissions::CAP_FEEDBACK );
		global $wpdb;
		$feedback_id = isset( $_POST['feedback_id'] ) ? absint( $_POST['feedback_id'] ) : 0;
		check_admin_referer( 'he_resolve_' . $feedback_id );
		$version = isset( $_POST['row_version'] ) ? absint( $_POST['row_version'] ) : 0;
		$disposition = isset( $_POST['disposition'] ) ? sanitize_key( wp_unslash( $_POST['disposition'] ) ) : '';
		$note = isset( $_POST['resolution_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['resolution_note'] ) ) : '';
		$allowed = array( 'corrected', 'no-action', 'duplicate', 'escalated', 'copyright-action' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}he_feedback WHERE id=%d", $feedback_id ) );
		if ( ! $row || ! in_array( $disposition, $allowed, true ) || ! trim( $note ) ) {
			wp_die( esc_html__( 'The feedback resolution request is incomplete or invalid.', 'homeopathy-encyclopedia' ), '', array( 'response' => 400 ) );
		}
		if ( ! HE_Database::resolve_feedback( $feedback_id, $version, $disposition, $note, get_current_user_id() ) ) {
			wp_die( esc_html__( 'Another editor already changed this feedback item. Reload before retrying.', 'homeopathy-encyclopedia' ), '', array( 'response' => 409 ) );
		}
		HE_Database::audit( $row->entry_id, 'feedback_resolved', 'open', 'resolved', $disposition . ': ' . $note );
		wp_safe_redirect( add_query_arg( 'resolved', '1', admin_url( 'admin.php?page=encyclopedia-feedback' ) ) );
		exit;
	}

	public function audit_page() {
		$this->guard( HE_Permissions::CAP_AUDIT );
		global $wpdb;
		$page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$entry_id = isset( $_GET['entry_id'] ) ? absint( $_GET['entry_id'] ) : 0;
		$action = isset( $_GET['audit_action'] ) ? sanitize_key( wp_unslash( $_GET['audit_action'] ) ) : '';
		$where = array( '1=1' );
		$params = array();
		if ( $entry_id ) { $where[] = 'entry_id=%d'; $params[] = $entry_id; }
		if ( $action ) { $where[] = 'action=%s'; $params[] = $action; }
		$base = " FROM {$wpdb->prefix}he_audit_log WHERE " . implode( ' AND ', $where );
		$count_sql = 'SELECT COUNT(*)' . $base;
		if ( $params ) { $count_sql = $wpdb->prepare( $count_sql, $params ); }
		$total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$per_page = 100;
		$sql = 'SELECT *' . $base . ' ORDER BY created_at DESC,id DESC LIMIT %d OFFSET %d';
		$sql = $wpdb->prepare( $sql, array_merge( $params, array( $per_page, ( $page - 1 ) * $per_page ) ) );
		$rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		?>
		<div class="wrap he-admin"><h1><?php esc_html_e( 'Encyclopedia Audit History', 'homeopathy-encyclopedia' ); ?></h1><form method="get"><input type="hidden" name="page" value="encyclopedia-audit"><label><?php esc_html_e( 'Entry ID', 'homeopathy-encyclopedia' ); ?> <input type="number" name="entry_id" min="1" value="<?php echo $entry_id ? absint( $entry_id ) : ''; ?>"></label> <label><?php esc_html_e( 'Action', 'homeopathy-encyclopedia' ); ?> <input name="audit_action" value="<?php echo esc_attr( $action ); ?>"></label> <button class="button"><?php esc_html_e( 'Filter', 'homeopathy-encyclopedia' ); ?></button></form><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Time (UTC)', 'homeopathy-encyclopedia' ); ?></th><th><?php esc_html_e( 'Entry', 'homeopathy-encyclopedia' ); ?></th><th><?php esc_html_e( 'Actor', 'homeopathy-encyclopedia' ); ?></th><th><?php esc_html_e( 'Action / transition', 'homeopathy-encyclopedia' ); ?></th><th><?php esc_html_e( 'Note', 'homeopathy-encyclopedia' ); ?></th><th><?php esc_html_e( 'Request ID', 'homeopathy-encyclopedia' ); ?></th></tr></thead><tbody><?php if ( $rows ) : foreach ( $rows as $row ) : ?><tr><td><?php echo esc_html( $row->created_at ); ?></td><td><?php echo absint( $row->entry_id ); ?> — <?php echo esc_html( get_the_title( $row->entry_id ) ); ?></td><td><?php echo absint( $row->actor_id ); ?> — <?php echo esc_html( get_the_author_meta( 'display_name', $row->actor_id ) ); ?></td><td><?php echo esc_html( $row->action ); ?><br><?php echo esc_html( $row->from_state . ' → ' . $row->to_state ); ?></td><td><?php echo esc_html( $row->note ); ?></td><td><code><?php echo esc_html( $row->request_id ); ?></code></td></tr><?php endforeach; else : ?><tr><td colspan="6"><?php esc_html_e( 'No audit events match the filters.', 'homeopathy-encyclopedia' ); ?></td></tr><?php endif; ?></tbody></table><?php $pages = max( 1, (int) ceil( $total / $per_page ) ); if ( $pages > 1 ) : ?><div class="tablenav"><div class="tablenav-pages"><?php echo wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'current' => $page, 'total' => $pages ) ) ); ?></div></div><?php endif; ?></div>
		<?php
	}

	private function guard( $capability ) {
		if ( ! current_user_can( $capability ) ) {
			wp_die( esc_html__( 'You cannot perform this encyclopedia action.', 'homeopathy-encyclopedia' ), '', array( 'response' => 403 ) );
		}
	}

	public function notice() {
		if ( current_user_can( HE_Permissions::CAP_MANAGE ) && get_transient( 'he_activation_notice' ) ) {
			delete_transient( 'he_activation_notice' );
			echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html__( 'Homeopathy Encyclopedia Foundation is active.', 'homeopathy-encyclopedia' ) . '</strong> ' . esc_html__( 'Starter material remains in draft until reviewed. Verify the Encyclopedia page and management dashboards.', 'homeopathy-encyclopedia' ) . '</p></div>';
		}
	}
}
