<?php
/** File 20-compatible encyclopedia catalog and public entry rendering. */

defined( 'ABSPATH' ) || exit;

final class HE_Catalog {
	public function hooks() {
		add_shortcode( 'he_encyclopedia_home', array( $this, 'home' ) );
		add_shortcode( 'sabri_encyclopedia', array( $this, 'home' ) );
		add_shortcode( 'he_saved_entries', array( $this, 'saved' ) );
		add_filter( 'the_content', array( $this, 'replace' ), 8 );
		add_filter( 'the_content', array( $this, 'single' ), 20 );
		add_action( 'template_redirect', array( $this, 'guard' ), 1 );
	}

	/** Replace only the Foundation-owned Encyclopedia module placeholder. */
	public function replace( $content ) {
		if ( ! is_singular( 'page' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$pages = (array) get_option( 'spf_page_map', array() );
		$page_id = isset( $pages['encyclopedia'] ) ? absint( $pages['encyclopedia'] ) : 0;
		if ( $page_id && $page_id === (int) get_queried_object_id() && has_shortcode( $content, 'sabri_platform_module' ) ) {
			return '[sabri_encyclopedia]';
		}
		return $content;
	}

	public function home() {
		ob_start();
		?>
		<section class="he-module he-shell" aria-labelledby="he-encyclopedia-title">
			<header class="he-hero">
				<div><span><?php esc_html_e( 'Homeopathy Knowledge Center', 'homeopathy-encyclopedia' ); ?></span><h1 id="he-encyclopedia-title"><?php esc_html_e( 'Encyclopedia', 'homeopathy-encyclopedia' ); ?></h1><p><?php esc_html_e( 'Search governed American English entries, references, relationships, medical red flags, and connected learning material.', 'homeopathy-encyclopedia' ); ?></p></div>
				<form method="get" role="search"><label class="screen-reader-text" for="he-search"><?php esc_html_e( 'Search Encyclopedia', 'homeopathy-encyclopedia' ); ?></label><input id="he-search" type="search" name="entry_search" value="<?php echo esc_attr( $this->request( 'entry_search' ) ); ?>" placeholder="<?php esc_attr_e( 'Search remedies, symptoms, conditions, anatomy, or concepts', 'homeopathy-encyclopedia' ); ?>"><button type="submit"><?php esc_html_e( 'Search', 'homeopathy-encyclopedia' ); ?></button></form>
			</header>
			<?php echo $this->browser(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</section>
		<?php
		return ob_get_clean();
	}

	private function browser( array $fixed_ids = null ) {
		$filters = array(
			'search' => sanitize_text_field( $this->request( 'entry_search' ) ),
			'type' => sanitize_title( $this->request( 'entry_type' ) ),
			'system' => sanitize_title( $this->request( 'entry_system' ) ),
			'letter' => strtoupper( substr( sanitize_text_field( $this->request( 'entry_letter' ) ), 0, 1 ) ),
			'sort' => 'popular' === sanitize_key( $this->request( 'entry_sort' ) ) ? 'popular' : 'latest',
		);
		if ( $filters['letter'] && ! preg_match( '/^[A-Z#]$/', $filters['letter'] ) ) {
			$filters['letter'] = '';
		}
		$page = isset( $_GET['he_page'] ) ? max( 1, absint( $_GET['he_page'] ) ) : 1;
		$result = HE_Database::catalog( $filters, $page, 24, $fixed_ids );
		$entries = $result['ids'] ? get_posts( array( 'post_type' => HE_Content::TYPE, 'post_status' => 'publish', 'post__in' => $result['ids'], 'orderby' => 'post__in', 'posts_per_page' => count( $result['ids'] ), 'no_found_rows' => true ) ) : array();
		ob_start();
		?>
		<section class="he-browser" aria-labelledby="he-browser-title">
			<div class="he-section-head"><div><span><?php esc_html_e( 'Browse Knowledge', 'homeopathy-encyclopedia' ); ?></span><h2 id="he-browser-title"><?php echo esc_html( is_array( $fixed_ids ) ? __( 'Saved Knowledge', 'homeopathy-encyclopedia' ) : __( 'Explore Entries', 'homeopathy-encyclopedia' ) ); ?></h2></div><?php $pages = (array) get_option( 'he_page_map', array() ); if ( ! empty( $pages['submit'] ) ) : ?><a class="he-button" href="<?php echo esc_url( get_permalink( absint( $pages['submit'] ) ) ); ?>"><?php esc_html_e( 'Submit Entry', 'homeopathy-encyclopedia' ); ?></a><?php endif; ?></div>
			<nav class="he-az" aria-label="<?php esc_attr_e( 'Browse entries alphabetically', 'homeopathy-encyclopedia' ); ?>"><a class="<?php echo '' === $filters['letter'] ? 'is-active' : ''; ?>" href="<?php echo esc_url( $this->filter_url( 'entry_letter', false ) ); ?>"><?php esc_html_e( 'All', 'homeopathy-encyclopedia' ); ?></a><?php foreach ( range( 'A', 'Z' ) as $char ) : ?><a class="<?php echo $filters['letter'] === $char ? 'is-active' : ''; ?>" href="<?php echo esc_url( $this->filter_url( 'entry_letter', $char ) ); ?>"><?php echo esc_html( $char ); ?></a><?php endforeach; ?><a class="<?php echo '#' === $filters['letter'] ? 'is-active' : ''; ?>" href="<?php echo esc_url( $this->filter_url( 'entry_letter', '#' ) ); ?>">#</a></nav>
			<?php if ( ! is_array( $fixed_ids ) ) : ?>
				<form class="he-filters" method="get">
					<label><?php esc_html_e( 'Search', 'homeopathy-encyclopedia' ); ?><input name="entry_search" value="<?php echo esc_attr( $filters['search'] ); ?>"></label>
					<label><?php esc_html_e( 'Knowledge type', 'homeopathy-encyclopedia' ); ?><select name="entry_type"><option value=""><?php esc_html_e( 'All types', 'homeopathy-encyclopedia' ); ?></option><?php foreach ( HE_Content::types() as $slug => $name ) : ?><option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $filters['type'], $slug ); ?>><?php echo esc_html( $name ); ?></option><?php endforeach; ?></select></label>
					<label><?php esc_html_e( 'Body system', 'homeopathy-encyclopedia' ); ?><select name="entry_system"><option value=""><?php esc_html_e( 'All systems', 'homeopathy-encyclopedia' ); ?></option><?php foreach ( HE_Content::systems() as $slug => $name ) : ?><option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $filters['system'], $slug ); ?>><?php echo esc_html( $name ); ?></option><?php endforeach; ?></select></label>
					<label><?php esc_html_e( 'Order', 'homeopathy-encyclopedia' ); ?><select name="entry_sort"><option value="latest" <?php selected( $filters['sort'], 'latest' ); ?>><?php esc_html_e( 'Latest', 'homeopathy-encyclopedia' ); ?></option><option value="popular" <?php selected( $filters['sort'], 'popular' ); ?>><?php esc_html_e( 'Popular', 'homeopathy-encyclopedia' ); ?></option></select></label>
					<button class="he-button" type="submit"><?php esc_html_e( 'Apply', 'homeopathy-encyclopedia' ); ?></button>
				</form>
			<?php endif; ?>
			<p class="he-result-count" role="status"><?php echo esc_html( sprintf( _n( '%s entry found', '%s entries found', $result['total'], 'homeopathy-encyclopedia' ), number_format_i18n( $result['total'] ) ) ); ?></p>
			<div class="he-grid"><?php if ( $entries ) : foreach ( $entries as $entry ) : echo $this->card( $entry ); endforeach; else : ?><div class="he-empty"><h3><?php esc_html_e( 'No entries found', 'homeopathy-encyclopedia' ); ?></h3><p><?php esc_html_e( 'Try another type, letter, system, or search phrase.', 'homeopathy-encyclopedia' ); ?></p></div><?php endif; ?></div>
			<?php if ( $result['pages'] > 1 ) : ?><nav class="he-pagination" aria-label="<?php esc_attr_e( 'Encyclopedia pages', 'homeopathy-encyclopedia' ); ?>"><?php echo wp_kses_post( paginate_links( array( 'base' => str_replace( '999999999', '%#%', esc_url( add_query_arg( 'he_page', 999999999 ) ) ), 'format' => '', 'current' => $result['page'], 'total' => $result['pages'], 'prev_text' => __( 'Previous', 'homeopathy-encyclopedia' ), 'next_text' => __( 'Next', 'homeopathy-encyclopedia' ) ) ) ); ?></nav><?php endif; ?>
			<p class="he-disclaimer"><?php esc_html_e( 'Encyclopedia entries are educational. They do not replace urgent assessment or an individualized consultation with a qualified practitioner.', 'homeopathy-encyclopedia' ); ?></p>
		</section>
		<?php
		return ob_get_clean();
	}

	private function card( $entry ) {
		$entry_id = absint( $entry->ID );
		ob_start();
		?>
		<article class="he-card" data-entry-id="<?php echo absint( $entry_id ); ?>"><?php if ( has_post_thumbnail( $entry_id ) ) : ?><a class="he-card-image" href="<?php echo esc_url( get_permalink( $entry_id ) ); ?>"><?php echo get_the_post_thumbnail( $entry_id, 'medium_large', array( 'loading' => 'lazy', 'alt' => $entry->post_title ) ); ?></a><?php endif; ?><div><span class="he-type"><?php echo esc_html( HE_Content::term( $entry_id ) ); ?></span><h3><a href="<?php echo esc_url( get_permalink( $entry_id ) ); ?>"><?php echo esc_html( $entry->post_title ); ?></a></h3><p><?php echo esc_html( wp_trim_words( $entry->post_excerpt, 26 ) ); ?></p><small><?php echo esc_html( HE_Permissions::label( $entry->post_author ) ); ?> · <?php echo esc_html( sprintf( __( 'Updated %s', 'homeopathy-encyclopedia' ), get_the_modified_date( '', $entry_id ) ) ); ?></small><?php echo HE_Interactions::actions( $entry_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div></article>
		<?php
		return ob_get_clean();
	}

	public function saved() {
		if ( ! is_user_logged_in() ) {
			return '<div class="he-notice"><p>' . esc_html__( 'Log in to view Saved Knowledge.', 'homeopathy-encyclopedia' ) . '</p><a class="he-button" href="' . esc_url( wp_login_url( get_permalink() ) ) . '">' . esc_html__( 'Log In', 'homeopathy-encyclopedia' ) . '</a></div>';
		}
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT entry_id FROM {$wpdb->prefix}he_bookmarks WHERE user_id=%d ORDER BY created_at DESC", get_current_user_id() ) );
		return '<section class="he-module he-shell">' . $this->browser( array_map( 'absint', $ids ) ) . '</section>';
	}

	public function single( $content ) {
		if ( ! is_singular( HE_Content::TYPE ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$entry_id = get_the_ID();
		if ( ! HE_Content::publicly_available( $entry_id ) ) {
			return '';
		}
		$author = absint( get_post_field( 'post_author', $entry_id ) );
		$reviewer = absint( HE_Content::meta( $entry_id, 'reviewer_id' ) );
		$head = '<div class="he-entry-head"><span class="he-type">' . esc_html( HE_Content::term( $entry_id ) ) . '</span><p>' . sprintf( esc_html__( 'By %1$s · %2$s', 'homeopathy-encyclopedia' ), '<a href="' . esc_url( HE_Permissions::profile_url( $author ) ) . '">' . esc_html( get_the_author_meta( 'display_name', $author ) ) . '</a>', esc_html( HE_Permissions::label( $author ) ) ) . '</p>';
		if ( $reviewer ) {
			$head .= '<p>' . esc_html( sprintf( __( 'Reviewed by %s', 'homeopathy-encyclopedia' ), get_the_author_meta( 'display_name', $reviewer ) ) ) . '</p>';
		}
		$head .= '<p>' . esc_html( sprintf( __( 'Published %1$s · Updated %2$s', 'homeopathy-encyclopedia' ), get_the_date( '', $entry_id ), get_the_modified_date( '', $entry_id ) ) ) . '</p></div>';
		$tail = '<section class="he-panel"><h2>' . esc_html__( 'Body System', 'homeopathy-encyclopedia' ) . '</h2><p>' . esc_html( HE_Content::term( $entry_id, HE_Content::SYSTEM ) ) . '</p></section>';
		foreach ( HE_Content::fields() as $key => $label ) {
			$value = HE_Content::meta( $entry_id, $key );
			if ( $value ) {
				$tail .= '<section class="he-panel he-' . esc_attr( str_replace( '_', '-', $key ) ) . '"><h2>' . esc_html( $label ) . '</h2>' . $this->lines( $value ) . '</section>';
			}
		}
		$related = (array) HE_Content::meta( $entry_id, 'related_ids' );
		$related_links = '';
		foreach ( array_slice( array_map( 'absint', $related ), 0, 5 ) as $related_id ) {
			if ( HE_Content::publicly_available( $related_id ) ) {
				$related_links .= '<li><a href="' . esc_url( get_permalink( $related_id ) ) . '">' . esc_html( get_the_title( $related_id ) ) . '</a></li>';
			}
		}
		if ( $related_links ) {
			$tail .= '<section class="he-panel"><h2>' . esc_html__( 'Related Encyclopedia Entries', 'homeopathy-encyclopedia' ) . '</h2><ul>' . $related_links . '</ul></section>';
		}
		$learning_links = '';
		$book_id = absint( HE_Content::meta( $entry_id, 'book_id' ) );
		$lesson_id = absint( HE_Content::meta( $entry_id, 'lesson_id' ) );
		if ( $book_id && HE_Content::learning_item_public( $book_id, 'slc_book' ) ) {
			$learning_links .= '<li><a href="' . esc_url( get_permalink( $book_id ) ) . '">' . esc_html( get_the_title( $book_id ) ) . '</a></li>';
		}
		if ( $lesson_id && HE_Content::learning_item_public( $lesson_id, 'slc_lesson' ) ) {
			$learning_links .= '<li><a href="' . esc_url( get_permalink( $lesson_id ) ) . '">' . esc_html( get_the_title( $lesson_id ) ) . '</a></li>';
		}
		if ( $learning_links ) {
			$tail .= '<section class="he-panel"><h2>' . esc_html__( 'Connected Learning', 'homeopathy-encyclopedia' ) . '</h2><ul>' . $learning_links . '</ul></section>';
		}
		$tail .= '<p class="he-disclaimer">' . esc_html__( 'Educational information only. Seek urgent help for severe or rapidly worsening symptoms and obtain individualized professional assessment when needed.', 'homeopathy-encyclopedia' ) . '</p>' . HE_Interactions::actions( $entry_id );
		return '<article class="he-entry" data-entry-id="' . absint( $entry_id ) . '">' . $head . $content . $tail . '</article>';
	}

	private function lines( $text ) {
		$lines = preg_split( '/\r\n|\r|\n/', (string) $text );
		if ( count( $lines ) < 2 ) {
			return '<p>' . esc_html( $text ) . '</p>';
		}
		$output = '<ul>';
		foreach ( $lines as $line ) {
			if ( trim( $line ) ) {
				$output .= '<li>' . esc_html( trim( $line ) ) . '</li>';
			}
		}
		return $output . '</ul>';
	}

	public function guard() {
		$pages = (array) get_option( 'he_page_map', array() );
		$current = get_queried_object_id();
		if ( is_page() && in_array( $current, array_map( 'absint', $pages ), true ) ) {
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true );
			}
			nocache_headers();
		}
		if ( is_singular( HE_Content::TYPE ) && ! HE_Content::publicly_available( $current ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			$template = get_404_template();
			if ( $template ) {
				include $template;
			}
			exit;
		}
	}

	private function request( $key ) {
		return isset( $_GET[ $key ] ) ? wp_unslash( $_GET[ $key ] ) : '';
	}

	private function filter_url( $key, $value ) {
		$args = array();
		foreach ( array( 'entry_search', 'entry_type', 'entry_system', 'entry_sort', 'entry_letter' ) as $allowed ) {
			if ( isset( $_GET[ $allowed ] ) && is_scalar( $_GET[ $allowed ] ) ) {
				$args[ $allowed ] = sanitize_text_field( wp_unslash( $_GET[ $allowed ] ) );
			}
		}
		if ( false === $value || '' === $value ) {
			unset( $args[ $key ] );
		} else {
			$args[ $key ] = sanitize_text_field( $value );
		}
		return add_query_arg( $args, remove_query_arg( array( 'entry_search', 'entry_type', 'entry_system', 'entry_sort', 'entry_letter', 'he_page' ) ) );
	}
}
