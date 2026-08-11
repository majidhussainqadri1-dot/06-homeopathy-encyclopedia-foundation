<?php
/** Public encyclopedia/research presentation, canonical redirects and SEO. */
defined( 'ABSPATH' ) || exit;

final class HE_V2_Public {
	public function hooks() {
		add_shortcode( 'he_encyclopedia', array( $this, 'encyclopedia' ) );
		add_shortcode( 'sabri_encyclopedia', array( $this, 'encyclopedia' ) );
		add_shortcode( 'he_research', array( $this, 'research' ) );
		add_filter( 'the_content', array( $this, 'entry_content' ), 20 );
		add_filter( 'template_redirect', array( $this, 'canonical_redirects' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'wp_head', array( $this, 'head' ), 20 );
		add_filter( 'wp_robots', array( $this, 'robots' ) );
		add_filter( 'document_title_parts', array( $this, 'title' ) );
	}

	public function assets() {
		global $post;
		$needed = is_post_type_archive( HE_V2_Domain::ENTRY_TYPE ) || is_singular( HE_V2_Domain::ENTRY_TYPE ) || is_post_type_archive( HE_V2_Domain::RESEARCH_TYPE ) || is_singular( HE_V2_Domain::RESEARCH_TYPE );
		if ( $post instanceof WP_Post ) {
			$needed = $needed || has_shortcode( $post->post_content, 'he_encyclopedia' ) || has_shortcode( $post->post_content, 'sabri_encyclopedia' ) || has_shortcode( $post->post_content, 'he_research' );
		}
		if ( ! $needed ) {
			return;
		}
		wp_enqueue_style( 'he-v2', HE_URL . 'assets/css/encyclopedia-v2.css', array(), HE_VERSION );
		wp_enqueue_script( 'he-v2', HE_URL . 'assets/js/encyclopedia-v2.js', array(), HE_VERSION, true );
		wp_localize_script( 'he-v2', 'heV2', array(
			'api' => esc_url_raw( rest_url( HE_V2_API::NS ) ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
			'loginUrl' => wp_login_url( get_permalink() ),
			'i18n' => array(
				'loading' => __( 'Loading knowledge…', 'homeopathy-encyclopedia' ),
				'noResults' => __( 'No public knowledge matched these filters.', 'homeopathy-encyclopedia' ),
				'error' => __( 'Knowledge could not be loaded safely. Please try again.', 'homeopathy-encyclopedia' ),
			),
		) );
	}

	private function structured_html( $value, $depth = 0 ) {
		if ( $depth > 6 ) { return ''; }
		if ( is_array( $value ) ) {
			$items = array();
			foreach ( $value as $key => $item ) {
				$child = $this->structured_html( $item, $depth + 1 );
				if ( '' === $child ) { continue; }
				$label = is_int( $key ) ? '' : '<strong>' . esc_html( ucwords( str_replace( array( '_','-' ), ' ', (string) $key ) ) ) . ':</strong> ';
				$items[] = '<li>' . $label . $child . '</li>';
			}
			return $items ? '<ul class="he-v2__structured-list">' . implode( '', $items ) . '</ul>' : '';
		}
		if ( is_bool( $value ) ) { return esc_html( $value ? __( 'Yes', 'homeopathy-encyclopedia' ) : __( 'No', 'homeopathy-encyclopedia' ) ); }
		if ( null === $value || ! is_scalar( $value ) ) { return ''; }
		return wp_kses_post( wpautop( (string) $value ) );
	}

	private function icon( $name ) {
		$paths = array(
			'search' => '<path d="m21 21-4.3-4.3m2.3-5.2a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z"/>',
			'book' => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H11v16H6.5A2.5 2.5 0 0 0 4 21.5v-16Zm16 0A2.5 2.5 0 0 0 17.5 3H13v16h4.5a2.5 2.5 0 0 1 2.5 2.5v-16Z"/>',
			'shield' => '<path d="M12 3 5 6v5c0 4.8 2.9 8.1 7 10 4.1-1.9 7-5.2 7-10V6l-7-3Zm-2.2 9.1 1.5 1.5 3.2-3.5"/>',
			'flask' => '<path d="M9 3h6M10 3v5l-5 9.2A2.5 2.5 0 0 0 7.2 21h9.6a2.5 2.5 0 0 0 2.2-3.8L14 8V3M7.5 16h9"/>',
			'link' => '<path d="M10.5 13.5 13.5 10M8.5 16H7a4 4 0 0 1 0-8h3M15.5 8H17a4 4 0 0 1 0 8h-3"/>',
			'history' => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8m0 0h5M12 7v5l3 2"/>',
		);
		return '<svg class="he-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . ( $paths[ $name ] ?? $paths['book'] ) . '</svg>';
	}

	public function encyclopedia( $atts = array() ) {
		$atts = shortcode_atts( array( 'limit' => 20, 'type' => '', 'body_system' => '' ), $atts, 'he_encyclopedia' );
		$letters = array_merge( range( 'A', 'Z' ), array( 'ا', 'ب', 'پ', 'ت', 'ج', 'د', 'ر', 'س', 'ش', 'ع', 'ف', 'ک', 'گ', 'ل', 'م', 'ن', 'و', 'ہ', 'ی' ) );
		ob_start();
		?>
		<section class="he-v2" data-he-encyclopedia data-limit="<?php echo esc_attr( min( 50, absint( $atts['limit'] ) ) ); ?>" data-type="<?php echo esc_attr( sanitize_key( $atts['type'] ) ); ?>" data-system="<?php echo esc_attr( sanitize_key( $atts['body_system'] ) ); ?>">
			<header class="he-v2__hero">
				<div>
					<p class="he-v2__eyebrow"><?php echo $this->icon( 'book' ); ?><?php esc_html_e( 'Canonical Homeopathy Knowledge', 'homeopathy-encyclopedia' ); ?></p>
					<h1><?php esc_html_e( 'Homeopathy Encyclopedia', 'homeopathy-encyclopedia' ); ?></h1>
					<p><?php esc_html_e( 'Source-linked, reviewed, versioned knowledge with medical safety boundaries and transparent corrections.', 'homeopathy-encyclopedia' ); ?></p>
				</div>
				<div class="he-v2__trust" role="note"><?php echo $this->icon( 'shield' ); ?><span><?php esc_html_e( 'Educational knowledge only. Urgent or individualized medical care requires a qualified local professional.', 'homeopathy-encyclopedia' ); ?></span></div>
			</header>
			<form class="he-v2__filters" data-he-filters role="search" aria-label="<?php esc_attr_e( 'Search encyclopedia', 'homeopathy-encyclopedia' ); ?>">
				<label class="he-v2__search"><span class="screen-reader-text"><?php esc_html_e( 'Search', 'homeopathy-encyclopedia' ); ?></span><?php echo $this->icon( 'search' ); ?><input type="search" name="q" autocomplete="off" placeholder="<?php esc_attr_e( 'Search exact terms, phrases, aliases, Urdu or English…', 'homeopathy-encyclopedia' ); ?>" aria-controls="he-v2-results"><div class="he-v2__suggestions" data-he-suggestions hidden></div></label>
				<label><span><?php esc_html_e( 'Knowledge type', 'homeopathy-encyclopedia' ); ?></span><select name="type"><option value=""><?php esc_html_e( 'All types', 'homeopathy-encyclopedia' ); ?></option><?php foreach ( HE_V2_Domain::types() as $slug => $name ) : ?><option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option><?php endforeach; ?></select></label>
				<label><span><?php esc_html_e( 'Body system', 'homeopathy-encyclopedia' ); ?></span><select name="body_system"><option value=""><?php esc_html_e( 'All systems', 'homeopathy-encyclopedia' ); ?></option><?php foreach ( HE_V2_Domain::systems() as $slug => $name ) : ?><option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option><?php endforeach; ?></select></label>
				<label><span><?php esc_html_e( 'Language', 'homeopathy-encyclopedia' ); ?></span><select name="language"><option value=""><?php esc_html_e( 'All languages', 'homeopathy-encyclopedia' ); ?></option><option value="en-US">English (US)</option><option value="ur-PK">اردو</option><option value="ar">العربية</option></select></label>
				<button class="he-v2__button" type="submit"><?php echo $this->icon( 'search' ); ?><?php esc_html_e( 'Search', 'homeopathy-encyclopedia' ); ?></button>
			</form>
			<nav class="he-v2__az" aria-label="<?php esc_attr_e( 'Browse alphabetically', 'homeopathy-encyclopedia' ); ?>"><button type="button" data-letter="" aria-pressed="true"><?php esc_html_e( 'All', 'homeopathy-encyclopedia' ); ?></button><?php foreach ( $letters as $letter ) : ?><button type="button" data-letter="<?php echo esc_attr( $letter ); ?>" aria-pressed="false"><?php echo esc_html( $letter ); ?></button><?php endforeach; ?></nav>
			<div class="he-v2__status" data-he-status aria-live="polite"></div>
			<div class="he-v2__grid" id="he-v2-results" data-he-results aria-busy="true"></div>
			<div class="he-v2__pagination"><button class="he-v2__button he-v2__button--secondary" type="button" data-he-more hidden><?php esc_html_e( 'Load more', 'homeopathy-encyclopedia' ); ?></button></div>
		</section>
		<?php
		return ob_get_clean();
	}

	public function research( $atts = array() ) {
		ob_start();
		?>
		<section class="he-v2 he-v2--research" data-he-research>
			<header class="he-v2__hero he-v2__hero--compact">
				<div><p class="he-v2__eyebrow"><?php echo $this->icon( 'flask' ); ?><?php esc_html_e( 'Governed Research', 'homeopathy-encyclopedia' ); ?></p><h1><?php esc_html_e( 'Research Center', 'homeopathy-encyclopedia' ); ?></h1><p><?php esc_html_e( 'Approved protocols, publications and anonymized successful cases. Restricted datasets are never public by default.', 'homeopathy-encyclopedia' ); ?></p></div>
			</header>
			<div class="he-v2__grid" data-he-research-results aria-busy="true"></div>
		</section>
		<?php
		return ob_get_clean();
	}

	public function entry_content( $content ) {
		if ( ! is_singular( HE_V2_Domain::ENTRY_TYPE ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		global $post;
		$raw = $post ? HE_V2_Domain::concept_by_post_id( (int) $post->ID ) : null;
		$row = $raw ? HE_V2_Domain::concept_by_id( (int) $raw['id'] ) : null;
		$dto = $row ? HE_V2_Domain::public_dto( $row ) : null;
		if ( ! $dto ) {
			return '<div class="he-v2 he-v2__notice he-v2__notice--restricted" role="alert">' . esc_html__( 'This knowledge record is not publicly available.', 'homeopathy-encyclopedia' ) . '</div>';
		}
		ob_start();
		?>
		<article class="he-v2 he-v2__entry" data-he-entry-id="<?php echo esc_attr( $dto['id'] ); ?>">
			<header class="he-v2__entry-header">
				<div class="he-v2__meta"><span><?php echo esc_html( HE_V2_Domain::types()[ $dto['type'] ] ?? $dto['type'] ); ?></span><span><?php echo esc_html( $dto['body_system'] ); ?></span><span><?php echo $this->icon( 'history' ); ?><?php echo esc_html( sprintf( __( 'Version %d', 'homeopathy-encyclopedia' ), $dto['version'] ) ); ?></span></div>
				<p class="he-v2__summary"><?php echo esc_html( $dto['summary'] ); ?></p>
			</header>
			<?php if ( 'retracted' === $dto['record_status'] ) : ?><aside class="he-v2__notice he-v2__notice--danger" role="alert"><strong><?php esc_html_e( 'Retracted knowledge record.', 'homeopathy-encyclopedia' ); ?></strong> <?php esc_html_e( 'This page is retained only to preserve citation and correction history. Do not rely on it as current guidance.', 'homeopathy-encyclopedia' ); ?></aside><?php endif; ?>
			<?php if ( $dto['is_historical'] ) : ?><aside class="he-v2__notice he-v2__notice--warning" role="note"><?php echo esc_html( sprintf( __( 'You are reading historical version %1$d. The current version is %2$d.', 'homeopathy-encyclopedia' ), $dto['version'], $dto['current_version'] ) ); ?></aside><?php endif; ?>
			<?php foreach ( $dto['integrity_notices'] as $notice ) : ?><aside class="he-v2__notice he-v2__notice--integrity" role="note"><strong><?php echo esc_html( ucfirst( $notice['action_type'] ) ); ?>:</strong> <?php echo esc_html( $notice['reason'] ); ?></aside><?php endforeach; ?>
			<?php if ( ! empty( $dto['fields']['red_flags'] ) ) : ?><aside class="he-v2__notice he-v2__notice--danger"><h2><?php esc_html_e( 'Medical red flags', 'homeopathy-encyclopedia' ); ?></h2><?php echo $this->structured_html( $dto['fields']['red_flags'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></aside><?php endif; ?>
			<?php if ( ! empty( $dto['fields']['emergency_boundary'] ) ) : ?><aside class="he-v2__notice he-v2__notice--warning"><h2><?php esc_html_e( 'Urgent-care boundary', 'homeopathy-encyclopedia' ); ?></h2><?php echo $this->structured_html( $dto['fields']['emergency_boundary'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></aside><?php endif; ?>
			<div class="he-v2__body"><?php echo wp_kses_post( $dto['body'] ); ?></div>
			<?php foreach ( $dto['fields'] as $key => $value ) : if ( in_array( $key, array( 'red_flags', 'emergency_boundary' ), true ) ) { continue; } ?><section class="he-v2__panel"><h2><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?></h2><?php echo $this->structured_html( $value ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></section><?php endforeach; ?>
			<section class="he-v2__panel"><h2><?php echo $this->icon( 'link' ); ?><?php esc_html_e( 'Sources and evidence', 'homeopathy-encyclopedia' ); ?></h2><?php if ( $dto['references'] ) : ?><ol class="he-v2__references"><?php foreach ( $dto['references'] as $ref ) : ?><li><strong><?php echo esc_html( $ref['author'] ? $ref['author'] . ', ' . $ref['title'] : $ref['title'] ); ?></strong><?php echo $ref['edition'] ? ' — ' . esc_html( $ref['edition'] ) : ''; ?><?php echo $ref['volume'] ? ', ' . esc_html( $ref['volume'] ) : ''; ?><?php echo $ref['page_locator'] ? ', ' . esc_html( $ref['page_locator'] ) : ''; ?> <span class="he-v2__grade"><?php echo esc_html( $ref['evidence_grade'] ); ?></span><?php if ( $ref['url'] ) : ?> <a href="<?php echo esc_url( $ref['url'] ); ?>" rel="nofollow noopener noreferrer"><?php esc_html_e( 'Source', 'homeopathy-encyclopedia' ); ?></a><?php endif; ?></li><?php endforeach; ?></ol><?php else : ?><p><?php esc_html_e( 'No public references are available for this version.', 'homeopathy-encyclopedia' ); ?></p><?php endif; ?></section>
			<footer class="he-v2__footer"><p><?php esc_html_e( 'This encyclopedia is educational. It does not diagnose, prescribe, guarantee cure or replace urgent professional assessment.', 'homeopathy-encyclopedia' ); ?></p><?php if ( is_user_logged_in() ) : ?><button class="he-v2__button he-v2__button--secondary" type="button" data-he-correction><?php esc_html_e( 'Suggest a sourced correction', 'homeopathy-encyclopedia' ); ?></button><?php endif; ?></footer>
		</article>
		<?php
		return ob_get_clean();
	}

	public function canonical_redirects() {
		if ( is_singular( HE_V2_Domain::ENTRY_TYPE ) ) {
			global $post;
			$row = $post ? HE_V2_Domain::concept_by_post_id( (int) $post->ID ) : null;
			if ( $row && ! empty( $row['merged_into_id'] ) ) {
				$target = HE_V2_Domain::concept_by_id( (int) $row['merged_into_id'] );
				$url = $target ? get_permalink( (int) $target['post_id'] ) : '';
				if ( $url && wp_safe_redirect( $url, 301, 'File-06-Merge' ) ) { exit; }
			}
		}
		if ( ! is_404() ) {
			return;
		}
		$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
		if ( ! preg_match( '#^encyclopedia/entry/([^/]+)/?$#', $path, $matches ) ) {
			return;
		}
		$row = HE_V2_Domain::concept_by_id( sanitize_text_field( rawurldecode( $matches[1] ) ) );
		if ( $row ) {
			$url = get_permalink( (int) $row['post_id'] );
			if ( $url && wp_safe_redirect( $url, 301, 'File-06-Canonical' ) ) {
				exit;
			}
		}
	}

	public function robots( $robots ) {
		if ( is_singular( HE_V2_Domain::ENTRY_TYPE ) ) {
			global $post;
			$raw = $post ? HE_V2_Domain::concept_by_post_id( (int) $post->ID ) : null;
			$row = $raw ? HE_V2_Domain::concept_by_id( (int) $raw['id'] ) : null;
			if ( ! $row ) {
				$robots['noindex'] = true;
				$robots['nofollow'] = true;
			}
		}
		if ( is_singular( HE_V2_Domain::RESEARCH_TYPE ) ) {
			global $wpdb, $post;
			$status = $post ? $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE post_id=%d', $post->ID ) ) : '';
			if ( 'published' !== $status ) {
				$robots['noindex'] = true;
				$robots['nofollow'] = true;
			}
		}
		return $robots;
	}

	public function head() {
		if ( ! is_singular( HE_V2_Domain::ENTRY_TYPE ) ) {
			return;
		}
		global $post;
		$raw = $post ? HE_V2_Domain::concept_by_post_id( (int) $post->ID ) : null;
		$row = $raw ? HE_V2_Domain::concept_by_id( (int) $raw['id'] ) : null;
		$dto = $row ? HE_V2_Domain::public_dto( $row ) : null;
		if ( ! $dto ) {
			return;
		}
		$schema_type = in_array( $dto['type'], array( 'health-condition', 'pathology', 'remedy' ), true ) ? 'MedicalWebPage' : 'Article';
		$graph = array(
			'@context' => 'https://schema.org',
			'@type' => $schema_type,
			'@id' => $dto['canonical_url'] . '#knowledge',
			'url' => $dto['canonical_url'],
			'name' => $dto['title'],
			'description' => $dto['summary'],
			'inLanguage' => $dto['language'],
			'dateModified' => gmdate( 'c', strtotime( $dto['freshness']['updated_at'] ) ),
			'version' => (string) $dto['version'],
			'isPartOf' => array( '@type' => 'WebSite', 'name' => 'Sabri Social Homeopathy Platform', 'url' => home_url( '/' ) ),
			'citation' => array_map( static function( $ref ) { return array_filter( array( '@type' => 'CreativeWork', 'name' => $ref['title'], 'author' => $ref['author'], 'url' => $ref['url'], 'identifier' => $ref['doi'] ) ); }, $dto['references'] ),
		);
		echo '<link rel="canonical" href="' . esc_url( $dto['canonical_url'] ) . '">' . "\n";
		echo '<script type="application/ld+json">' . wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}

	public function title( $parts ) {
		if ( is_post_type_archive( HE_V2_Domain::ENTRY_TYPE ) ) {
			$parts['title'] = __( 'Homeopathy Encyclopedia', 'homeopathy-encyclopedia' );
		}
		if ( is_post_type_archive( HE_V2_Domain::RESEARCH_TYPE ) ) {
			$parts['title'] = __( 'Research Center', 'homeopathy-encyclopedia' );
		}
		return $parts;
	}
}
