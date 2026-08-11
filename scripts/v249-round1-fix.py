from pathlib import Path

p=Path('homeopathy-encyclopedia/includes/class-he-v22-governance.php')
s=p.read_text()
if 'private static function entry_content_hash( $row ) {' in s:
    s=s.replace('private static function entry_content_hash( $row ) {','public static function entry_content_hash( $row ) {',1)
old="""\t\tif ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/entries/([^/]+)/review$#', $route, $m ) && WP_REST_Server::CREATABLE === $request->get_method() ) {
\t\t\t$row = HE_V2_Domain::concept_by_id( $m[1], true );
\t\t\tif ( $row ) {
\t\t\t\tself::bind_latest_entry_review( $row );
\t\t\t}
\t\t}
"""
if old not in s:
    raise SystemExit('R1 rest_after_callbacks review binding marker missing')
s=s.replace(old,'',1)
p.write_text(s)

p=Path('homeopathy-encyclopedia/includes/class-he-v2-api.php')
s=p.read_text()
old="HE_V2_Domain::add_review( $row['id'], sanitize_key( $data['scope'] ?? 'scientific' ), sanitize_key( $data['decision'] ?? 'changes_required' ), ! empty( $data['conflict_declared'] ), $data['note'] ?? '', get_current_user_id() )"
new="HE_V2_Domain::add_review( $row['id'], sanitize_key( $data['scope'] ?? 'scientific' ), sanitize_key( $data['decision'] ?? 'changes_required' ), ! empty( $data['conflict_declared'] ), $data['note'] ?? '', get_current_user_id(), absint( $data['expected_version'] ?? 0 ) )"
if old not in s:
    raise SystemExit('R1 API add_review marker missing')
p.write_text(s.replace(old,new,1))

p=Path('homeopathy-encyclopedia/includes/class-he-v2-domain.php')
s=p.read_text()
old_sig="public static function add_review( $concept_id, $scope, $decision, $conflict, $note, $reviewer_id ) {"
new_sig="public static function add_review( $concept_id, $scope, $decision, $conflict, $note, $reviewer_id, $expected_version = 0 ) {"
if old_sig not in s:
    raise SystemExit('R1 domain signature marker missing')
s=s.replace(old_sig,new_sig,1)
pos=s.index(new_sig)
old="""\t\tif ( ! $row ) {
\t\t\treturn new WP_Error( 'he_not_found', __( 'Concept not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
\t\t}
\t\t$post = get_post( (int) $row['post_id'] );
"""
new="""\t\tif ( ! $row ) {
\t\t\treturn new WP_Error( 'he_not_found', __( 'Concept not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
\t\t}
\t\t$expected_version = absint( $expected_version );
\t\tif ( ! $expected_version || $expected_version !== (int) $row['row_version'] ) {
\t\t\treturn new WP_Error( 'he_version_conflict', __( 'The entry changed before the review could be stored. Reload the current version before deciding.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) );
\t\t}
\t\t$post = get_post( (int) $row['post_id'] );
"""
idx=s.find(old,pos)
if idx<0:
    raise SystemExit('R1 add_review row marker missing')
s=s[:idx]+new+s[idx+len(old):]
old="""\t\t$ok = $wpdb->insert( HE_V2_Schema::table( 'reviews' ), array(
\t\t\t'object_type' => 'concept',
\t\t\t'object_id' => $row['id'],
\t\t\t'reviewer_id' => absint( $reviewer_id ),
\t\t\t'scope' => $scope,
\t\t\t'decision' => $decision,
\t\t\t'conflict_declared' => $conflict ? 1 : 0,
\t\t\t'note' => sanitize_textarea_field( $note ),
\t\t\t'created_at' => current_time( 'mysql', true ),
\t\t), array( '%s','%d','%d','%s','%s','%d','%s','%s' ) );
"""
new="""\t\t$content_hash = HE_V22_Governance::entry_content_hash( $row );
\t\t$ok = $wpdb->insert( HE_V2_Schema::table( 'reviews' ), array(
\t\t\t'object_type' => 'concept',
\t\t\t'object_id' => $row['id'],
\t\t\t'reviewer_id' => absint( $reviewer_id ),
\t\t\t'scope' => $scope,
\t\t\t'decision' => $decision,
\t\t\t'conflict_declared' => $conflict ? 1 : 0,
\t\t\t'note' => sanitize_textarea_field( $note ),
\t\t\t'content_hash' => $content_hash,
\t\t\t'reviewed_row_version' => (int) $row['row_version'],
\t\t\t'review_subject_author' => $post ? (int) $post->post_author : 0,
\t\t\t'created_at' => current_time( 'mysql', true ),
\t\t), array( '%s','%d','%d','%s','%s','%d','%s','%s','%d','%d','%s' ) );
"""
if old not in s:
    raise SystemExit('R1 review insert marker missing')
p.write_text(s.replace(old,new,1))

t=Path('tests/v249-tenth-ten-round-regressions.php')
t.write_text("""<?php
/** File 06 v2.4.9 tenth fresh ten-round regression controls. */
$root=dirname(__DIR__);$fail=array();
function v249_read($p){$v=file_get_contents($p);if(false===$v){throw new RuntimeException($p);}return $v;}
function v249_ok($ok,$m){global $fail;if(!$ok)$fail[]=$m;}
$domain=v249_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-domain.php');
$api=v249_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-api.php');
$gov=v249_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-governance.php');
v249_ok(false!==strpos($domain,'$expected_version = 0') && false!==strpos($domain,"'content_hash' => $content_hash") && false!==strpos($domain,"'reviewed_row_version' => (int) $row['row_version']"),'R1 entry review is not bound at the owning insert to the expected reviewed state');
v249_ok(false!==strpos($api,"absint( $data['expected_version'] ?? 0 ) )") && false===strpos($gov,'self::bind_latest_entry_review( $row )'),'R1 after-callback rebind can attach a review to a newer concurrent entry state');
/*__V249_MORE__*/
if($fail){fwrite(STDERR,"File 06 v2.4.9 tenth-review regressions FAILED:\n- ".implode("\n- ",$fail)."\n");exit(1);}echo "File 06 v2.4.9 tenth-review regressions: PASS\n";
""")
