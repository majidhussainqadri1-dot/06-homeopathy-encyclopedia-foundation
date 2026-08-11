<?php
/** Public-surface guardrails: immutable entry/research presentation only. */
defined( 'ABSPATH' ) || exit;

final class HE_V22_Public_Guard {
	public static function hooks() {
		add_filter( 'the_content', array( __CLASS__, 'research_content' ), 99 );
		add_filter( 'the_title', array( __CLASS__, 'research_title' ), 98, 2 );
		add_filter( 'get_the_excerpt', array( __CLASS__, 'research_excerpt' ), 98, 2 );
		add_filter( 'posts_where', array( __CLASS__, 'research_public_query_where' ), 99, 2 );
		add_filter( 'posts_results', array( __CLASS__, 'research_public_query_results' ), 99, 2 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ), 99 );
		add_action( 'wp_head', array( __CLASS__, 'research_head' ), 25 );
		add_filter( 'sabri_search_connectors', array( __CLASS__, 'search_events' ), 110 );
	}

	private static function row_for_post( $post_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE post_id=%d', absint( $post_id ) ), ARRAY_A );
	}

	private static function is_public_row( $row ) {
		return HE_V22_Research_Guard::public_surface_eligible( $row );
	}

	public static function research_public_query_where( $where, $query ) {
		if ( is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query() ) { return $where; }
		global $wpdb;
		$research = HE_V2_Schema::table( 'research' );
		return $where . $wpdb->prepare(
			" AND ({$wpdb->posts}.post_type<>%s OR EXISTS (SELECT 1 FROM {$research} he_public_research WHERE he_public_research.post_id={$wpdb->posts}.ID AND he_public_research.status IN (%s,%s,%s) AND ((he_public_research.record_type=%s AND he_public_research.data_class IN (%s,%s)) OR (he_public_research.record_type<>%s AND he_public_research.data_class=%s)) AND (he_public_research.record_type<>%s OR (he_public_research.case_anonymized=1 AND he_public_research.case_consent_verified=1))))",
			HE_V2_Domain::RESEARCH_TYPE, 'published', 'corrected', 'retracted', 'dataset', 'restricted', 'highly-restricted', 'dataset', 'public', 'successful-case'
		);
	}

	public static function research_public_query_results( $posts, $query ) {
		if ( is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query() || ! is_array( $posts ) ) { return $posts; }
		$out = array();
		foreach ( $posts as $post ) {
			if ( ! is_object( $post ) || HE_V2_Domain::RESEARCH_TYPE !== $post->post_type ) { $out[] = $post; continue; }
			$row = self::row_for_post( (int) $post->ID );
			if ( self::is_public_row( $row ) ) { $out[] = $post; }
		}
		return $out;
	}

	public static function research_title( $title, $post_id = 0 ) {
		if ( ! $post_id || HE_V2_Domain::RESEARCH_TYPE !== get_post_type( $post_id ) ) {
			return $title;
		}
		$row = self::row_for_post( $post_id );
		return self::is_public_row( $row ) ? (string) $row['title'] : __( 'Research record unavailable', 'homeopathy-encyclopedia' );
	}

	public static function research_excerpt( $excerpt, $post = null ) {
		$post = is_object( $post ) ? $post : get_post( $post );
		if ( ! $post || HE_V2_Domain::RESEARCH_TYPE !== $post->post_type ) {
			return $excerpt;
		}
		$row = self::row_for_post( $post->ID );
		return self::is_public_row( $row ) ? (string) $row['question'] : '';
	}

	public static function research_content( $content ) {
		if ( ! is_singular( HE_V2_Domain::RESEARCH_TYPE ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		global $post;
		$row = $post ? self::row_for_post( $post->ID ) : null;
		if ( ! self::is_public_row( $row ) ) {
			return '<div class="he-v2 he-v2__notice he-v2__notice--restricted" role="alert">' . esc_html__( 'This research record is not publicly available.', 'homeopathy-encyclopedia' ) . '</div>';
		}

		$case = 'successful-case' === $row['record_type'] && 'public' === $row['data_class'] && in_array( $row['status'], array( 'published', 'corrected' ), true ) && ! empty( $row['case_anonymized'] ) && ! empty( $row['case_consent_verified'] ) ? json_decode( (string) $row['case_json'], true ) : array();
		$dataset = 'dataset' === $row['record_type'] ? json_decode( (string) $row['metadata_json'], true ) : array();
		ob_start();
		?>
		<article class="he-v2 he-v2__entry he-v2__research-record" data-he-research-id="<?php echo esc_attr( $row['public_id'] ); ?>">
			<header class="he-v2__entry-header">
				<div class="he-v2__meta"><span><?php echo esc_html( $row['record_type'] ); ?></span><span><?php echo esc_html( $row['status'] ); ?></span><span><?php echo esc_html( sprintf( __( 'Version %d', 'homeopathy-encyclopedia' ), (int) $row['row_version'] ) ); ?></span><?php if ( $row['case_tag'] ) : ?><span><?php echo esc_html( $row['case_tag'] ); ?></span><?php endif; ?></div>
				<p class="he-v2__summary"><?php echo esc_html( $row['question'] ); ?></p>
			</header>
			<?php if ( 'corrected' === $row['status'] ) : ?>
				<aside class="he-v2__notice he-v2__notice--integrity" role="note"><strong><?php esc_html_e( 'Corrected research record.', 'homeopathy-encyclopedia' ); ?></strong> <?php esc_html_e( 'This page reflects the current corrected research metadata. Earlier audit history remains preserved.', 'homeopathy-encyclopedia' ); ?></aside>
			<?php endif; ?>
			<?php if ( 'retracted' === $row['status'] ) : ?>
				<aside class="he-v2__notice he-v2__notice--danger" role="alert"><strong><?php esc_html_e( 'Retracted research record.', 'homeopathy-encyclopedia' ); ?></strong> <?php esc_html_e( 'Metadata remains visible for correction and citation integrity; the protocol is not presented as current evidence.', 'homeopathy-encyclopedia' ); ?></aside>
			<?php elseif ( 'public' === $row['data_class'] ) : ?>
				<section class="he-v2__body"><h2><?php esc_html_e( 'Protocol', 'homeopathy-encyclopedia' ); ?></h2><?php echo wp_kses_post( wpautop( $row['protocol'] ) ); ?></section>
			<?php else : ?>
				<aside class="he-v2__notice he-v2__notice--restricted" role="note"><?php esc_html_e( 'The protocol or underlying data is restricted. Only approved public metadata is displayed.', 'homeopathy-encyclopedia' ); ?></aside>
			<?php endif; ?>
			<?php if ( $case ) : ?><section class="he-v2__panel"><h2><?php echo esc_html( $row['case_tag'] ?: __( 'Successful case observation', 'homeopathy-encyclopedia' ) ); ?></h2><?php foreach ( array( 'observation_label', 'baseline', 'intervention', 'follow_up', 'adverse_events', 'limitations' ) as $key ) : if ( ! empty( $case[ $key ] ) ) : ?><h3><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?></h3><?php echo wp_kses_post( wpautop( (string) $case[ $key ] ) ); ?><?php endif; endforeach; ?></section><?php endif; ?>
			<?php if ( $dataset ) : ?><section class="he-v2__panel"><h2><?php esc_html_e( 'Dataset governance metadata', 'homeopathy-encyclopedia' ); ?></h2><?php foreach ( array( 'description', 'de_identification', 'lawful_basis', 'access_policy' ) as $key ) : if ( ! empty( $dataset[ $key ] ) ) : ?><h3><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?></h3><p><?php echo esc_html( (string) $dataset[ $key ] ); ?></p><?php endif; endforeach; ?></section><?php endif; ?>
			<footer class="he-v2__footer"><p><?php esc_html_e( 'Research observations and successful cases are not automatic proof of efficacy and do not replace individualized professional care.', 'homeopathy-encyclopedia' ); ?></p></footer>
		</article>
		<?php
		return ob_get_clean();
	}

	public static function robots( $robots ) {
		if ( get_query_var( 'he_v22_editor' ) ) {
			$robots['noindex'] = true;
			$robots['nofollow'] = true;
			$robots['noarchive'] = true;
		}
		if ( is_singular( HE_V2_Domain::RESEARCH_TYPE ) ) {
			global $post;
			$row = $post ? self::row_for_post( $post->ID ) : null;
			if ( ! self::is_public_row( $row ) ) {
				$robots['noindex'] = true;
				$robots['nofollow'] = true;
				$robots['noarchive'] = true;
				return $robots;
			}
			if ( in_array( $row['status'], array( 'published', 'corrected' ), true ) ) {
				unset( $robots['noindex'], $robots['nofollow'] );
			}
			if ( $row && 'retracted' === $row['status'] ) {
				$robots['noindex'] = true;
				$robots['follow'] = true;
			}
		}
		return $robots;
	}

	public static function research_head() {
		if ( ! is_singular( HE_V2_Domain::RESEARCH_TYPE ) ) {
			return;
		}
		global $post;
		$row = $post ? self::row_for_post( $post->ID ) : null;
		if ( ! self::is_public_row( $row ) ) {
			return;
		}
		$url = home_url( '/research/' . rawurlencode( $row['public_id'] ) . '/' );
		$type = 'Dataset' === $row['record_type'] ? 'Dataset' : ( in_array( $row['record_type'], array( 'publication', 'successful-case' ), true ) ? 'ScholarlyArticle' : 'CreativeWork' );
		if ( 'dataset' === $row['record_type'] ) {
			$type = 'Dataset';
		}
		$graph = array(
			'@context' => 'https://schema.org',
			'@type' => $type,
			'@id' => $url . '#research',
			'url' => $url,
			'name' => $row['title'],
			'description' => $row['question'],
			'version' => (string) $row['row_version'],
			'dateCreated' => gmdate( 'c', strtotime( $row['created_at'] ) ),
			'dateModified' => gmdate( 'c', strtotime( $row['updated_at'] ) ),
			'isPartOf' => array( '@type' => 'WebSite', 'name' => 'Sabri Social Homeopathy Platform', 'url' => home_url( '/' ) ),
			'additionalProperty' => array(
				array( '@type' => 'PropertyValue', 'name' => 'Record status', 'value' => $row['status'] ),
				array( '@type' => 'PropertyValue', 'name' => 'Data class', 'value' => $row['data_class'] ),
			),
		);
		if ( $row['case_tag'] ) {
			$graph['keywords'] = array( $row['case_tag'] );
		}
		echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";
		echo '<script type="application/ld+json">' . wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}

	public static function search_events( $connectors ) {
		$connectors = is_array( $connectors ) ? $connectors : array();
		if ( isset( $connectors['file-06'] ) ) {
			$events = isset( $connectors['file-06']['events'] ) ? (array) $connectors['file-06']['events'] : array();
			$events[] = 'ResearchPublicationCorrected.v1';
			$connectors['file-06']['events'] = array_values( array_unique( $events ) );
		}
		return $connectors;
	}
}
