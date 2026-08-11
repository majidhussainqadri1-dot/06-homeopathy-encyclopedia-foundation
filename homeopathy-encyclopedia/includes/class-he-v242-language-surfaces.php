<?php
/** File 06 v2.4.2 source-language and canonical public translation surfaces. */
defined( 'ABSPATH' ) || exit;

final class HE_V242_Language_Surfaces {
	const NONCE_ACTION = 'he_v242_source_language';
	const NONCE_FIELD = 'he_v242_source_language_nonce';
	private static $normalizing = false;

	public static function hooks() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ), 510 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_source_box' ), 80 );
		add_action( 'save_post_' . HE_V2_Domain::ENTRY_TYPE, array( __CLASS__, 'save_source_language' ), 180, 3 );
		add_action( 'added_post_meta', array( __CLASS__, 'normalize_language_meta' ), 30, 4 );
		add_action( 'updated_post_meta', array( __CLASS__, 'normalize_language_meta' ), 30, 4 );
		add_action( 'wp_footer', array( __CLASS__, 'dynamic_language_filter' ), 50 );
		add_filter( 'sabri_platform_contracts', array( __CLASS__, 'contract' ), 520 );
	}

	public static function routes() {
		if ( ! class_exists( 'HE_V24_Migration_Safety' ) || ! HE_V24_Migration_Safety::ready() ) { return; }
		register_rest_route( HE_V2_API::NS, '/future/public/translations/(?P<id>[a-fA-F0-9-]{36})', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array( __CLASS__, 'public_translations' ),
			'permission_callback' => '__return_true',
		) );
	}

	public static function public_translations( WP_REST_Request $request ) {
		$public_id = strtolower( sanitize_text_field( (string) $request['id'] ) );
		$concept = HE_V2_Domain::concept_by_id( $public_id, false );
		if ( ! $concept || strtolower( (string) $concept['public_id'] ) !== $public_id || ! $concept['current_version'] ) {
			return new WP_Error( 'he_not_found', __( 'Public translations are not available for this canonical concept.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
		}
		$locale = HE_V242_Multilingual::canonical_locale( $request->get_param( 'locale' ) );
		global $wpdb;
		$table = HE_V24_Future_Schema::table( 'translations' );
		$params = array( (int) $concept['id'], (int) $concept['current_version'] );
		$where = "concept_id=%d AND source_version=%d AND status='published'";
		if ( $locale ) {
			$where .= ' AND locale=%s';
			$params[] = $locale;
		}
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT locale,source_locale,source_version,translation_version,content_json,content_hash,published_at,updated_at FROM {$table} WHERE {$where} ORDER BY locale ASC", $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$items = array();
		foreach ( $rows as $row ) {
			$content = json_decode( (string) $row['content_json'], true );
			$content = is_array( $content ) ? $content : array();
			$meta = isset( $content['_translation_meta'] ) && is_array( $content['_translation_meta'] ) ? $content['_translation_meta'] : array();
			unset( $content['_translation_meta'] );
			$items[] = array(
				'locale' => HE_V242_Multilingual::canonical_locale( $row['locale'] ) ?: $row['locale'],
				'source_locale' => HE_V242_Multilingual::canonical_locale( $row['source_locale'] ?: $concept['language'] ),
				'source_version' => (int) $row['source_version'],
				'translation_version' => (int) $row['translation_version'],
				'content' => $content,
				'content_hash' => $row['content_hash'],
				'published_at' => $row['published_at'],
				'updated_at' => $row['updated_at'],
				'human_reviewed' => true,
				'machine_assisted' => ! empty( $meta['machine_assisted'] ),
				'policy_version' => $meta['policy_version'] ?? HE_V242_Multilingual::POLICY_VERSION,
			);
		}
		return rest_ensure_response( array(
			'concept_id' => $concept['public_id'],
			'source_locale' => HE_V242_Multilingual::canonical_locale( $concept['language'] ),
			'source_version' => (int) $concept['current_version'],
			'targets' => HE_V242_Multilingual::targets_for_source( $concept['language'] ),
			'items' => $items,
			'localized_url_owner' => 'cross-file-multilingual-publishing-search',
			'policy_version' => HE_V242_Multilingual::POLICY_VERSION,
		) );
	}

	public static function add_source_box() {
		add_meta_box(
			'he-v242-source-language',
			__( 'Original source language', 'homeopathy-encyclopedia' ),
			array( __CLASS__, 'source_box' ),
			HE_V2_Domain::ENTRY_TYPE,
			'side',
			'high'
		);
	}

	public static function source_box( $post ) {
		$current = HE_V242_Multilingual::canonical_locale( get_post_meta( $post->ID, '_he_language', true ) ?: 'en-US' );
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		$known = array_values( array_unique( array_merge( array( $current ), HE_V242_Multilingual::targets_for_source( '__none__' ), array( 'ur','en-US','ar','zh-Hans','hi','es','fr','bn','pt','ru','id','de','ja','tr','fa' ) ) ) );
		?>
		<p><?php esc_html_e( 'This is the canonical language in which the knowledge entry was originally authored. Any valid BCP-47 language code may be used; the nine translation targets are resolved separately.', 'homeopathy-encyclopedia' ); ?></p>
		<input type="text" name="he_v242_source_language" list="he-v242-source-language-list" value="<?php echo esc_attr( $current ); ?>" pattern="[A-Za-z]{2,3}(-[A-Za-z0-9]{2,8})*" required>
		<datalist id="he-v242-source-language-list"><?php foreach ( array_filter( $known ) as $code ) : ?><option value="<?php echo esc_attr( $code ); ?>"></option><?php endforeach; ?></datalist>
		<p><code><?php echo esc_html( HE_V242_Multilingual::POLICY_VERSION ); ?></code></p>
		<?php
	}

	public static function save_source_language( $post_id, $post, $update ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! isset( $_POST[ self::NONCE_FIELD ] ) ) { return; } // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) || ! HE_V2_Auth::can( HE_V2_Auth::CAP_EDIT, $post_id, 'source-language-save' ) ) { return; }
		$locale = HE_V242_Multilingual::canonical_locale( wp_unslash( $_POST['he_v242_source_language'] ?? '' ) );
		if ( ! $locale ) {
			HE_V2_Schema::record_runtime_failure( 'invalid_source_language', 'An invalid source-language code was rejected.' );
			return;
		}
		update_post_meta( $post_id, '_he_language', $locale );
		global $wpdb;
		$wpdb->update( HE_V2_Schema::table( 'concepts' ), array( 'language' => $locale, 'updated_at' => current_time( 'mysql', true ) ), array( 'post_id' => absint( $post_id ) ), array( '%s','%s' ), array( '%d' ) );
		HE_V242_Third_Audit::repair_canonical_alias_language( $post_id, $post, true );
	}

	public static function normalize_language_meta( $meta_id, $object_id, $meta_key, $meta_value ) {
		if ( self::$normalizing || '_he_language' !== $meta_key || HE_V2_Domain::ENTRY_TYPE !== get_post_type( $object_id ) ) { return; }
		$canonical = HE_V242_Multilingual::canonical_locale( $meta_value );
		if ( ! $canonical ) {
			global $wpdb;
			$current = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT language FROM ' . HE_V2_Schema::table( 'concepts' ) . ' WHERE post_id=%d', absint( $object_id ) ) );
			self::$normalizing = true;
			if ( HE_V242_Multilingual::canonical_locale( $current ) ) { update_post_meta( $object_id, '_he_language', $current ); }
			else { delete_post_meta( $object_id, '_he_language' ); }
			self::$normalizing = false;
			HE_V2_Schema::record_runtime_failure( 'invalid_source_language', 'An invalid source-language code was rejected and the canonical concept language was restored.' );
			return;
		}
		if ( $canonical !== (string) $meta_value ) {
			self::$normalizing = true;
			update_post_meta( $object_id, '_he_language', $canonical );
			self::$normalizing = false;
		}
		global $wpdb;
		$concept = $wpdb->get_row( $wpdb->prepare( 'SELECT id,language,public_id FROM ' . HE_V2_Schema::table( 'concepts' ) . ' WHERE post_id=%d', absint( $object_id ) ), ARRAY_A );
		if ( ! $concept || $canonical === (string) $concept['language'] ) { return; }
		$wpdb->update( HE_V2_Schema::table( 'concepts' ), array( 'language' => $canonical, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $concept['id'] ), array( '%s','%s' ), array( '%d' ) );
		if ( class_exists( 'HE_V24_Migration_Safety' ) && HE_V24_Migration_Safety::ready() ) {
			$wpdb->query( $wpdb->prepare( "UPDATE " . HE_V24_Future_Schema::table( 'translations' ) . " SET status='translation-outdated',updated_at=UTC_TIMESTAMP() WHERE concept_id=%d AND source_locale<>%s AND status IN ('draft','approved','published')", (int) $concept['id'], $canonical ) );
			HE_V24_Future_Schema::queue_impact( 'concept', $concept['public_id'], 'KnowledgeTranslationOutdated.v1', array( 'concept_id' => $concept['public_id'], 'reason' => 'source-language-changed' ) );
		}
		HE_V242_Third_Audit::repair_canonical_alias_language( $object_id, get_post( $object_id ), true );
	}

	private static function public_languages() {
		global $wpdb;
		$rows = $wpdb->get_col( 'SELECT DISTINCT language FROM ' . HE_V2_Schema::table( 'concepts' ) . " WHERE status='published' AND review_status='approved' AND safety_status='approved' AND merged_into_id=0 ORDER BY language ASC LIMIT 100" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$known = array_merge( array( 'ur','en-US','ar','zh-Hans','hi','es','fr','bn','pt' ), (array) $rows );
		$out = array();
		foreach ( $known as $locale ) {
			$canonical = HE_V242_Multilingual::canonical_locale( $locale );
			if ( $canonical ) { $out[] = $canonical; }
		}
		return array_values( array_unique( $out ) );
	}

	public static function dynamic_language_filter() {
		if ( ! is_post_type_archive( HE_V2_Domain::ENTRY_TYPE ) && ! is_singular( HE_V2_Domain::ENTRY_TYPE ) && ! is_page() ) { return; }
		$languages = self::public_languages();
		?>
		<script>(function(){var s=document.querySelector('[data-he-encyclopedia] select[name="language"]');if(!s)return;var chosen=s.value;var labels=<?php echo wp_json_encode( array( 'ur'=>'اردو','en-US'=>'English (US)','ar'=>'العربية','zh-Hans'=>'中文','hi'=>'हिन्दी','es'=>'Español','fr'=>'Français','bn'=>'বাংলা','pt'=>'Português','ru'=>'Русский','id'=>'Bahasa Indonesia','de'=>'Deutsch','ja'=>'日本語','tr'=>'Türkçe','fa'=>'فارسی' ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?>;var langs=<?php echo wp_json_encode( $languages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?>;s.innerHTML='';var all=document.createElement('option');all.value='';all.textContent=<?php echo wp_json_encode( __( 'All languages', 'homeopathy-encyclopedia' ) ); ?>;s.appendChild(all);langs.forEach(function(c){var o=document.createElement('option');o.value=c;o.textContent=labels[c]||c;if(c===chosen)o.selected=true;s.appendChild(o);});})();</script>
		<?php
	}

	public static function contract( $contracts ) {
		$contracts = is_array( $contracts ) ? $contracts : array();
		if ( isset( $contracts['file-06'] ) && is_array( $contracts['file-06'] ) ) {
			$queries = isset( $contracts['file-06']['queries'] ) ? (array) $contracts['file-06']['queries'] : array();
			$queries[] = 'get_governed_public_translations';
			$contracts['file-06']['queries'] = array_values( array_unique( $queries ) );
			$contracts['file-06']['public_api']['translation_read_route'] = '/future/public/translations/{canonical_public_id}';
			$contracts['file-06']['public_api']['dynamic_source_language'] = true;
		}
		return $contracts;
	}
}
