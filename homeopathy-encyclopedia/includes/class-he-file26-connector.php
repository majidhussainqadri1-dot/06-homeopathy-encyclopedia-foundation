<?php
/**
 * Owner File06 → File26 public encyclopedia search, PROPOSED ONLY.
 * Owner review/safety + current version are authoritative; no research
 * records, patients, private versions, editorial notes or rights-bearing
 * structured text are exported.
 */
defined('ABSPATH') || exit;
final class HE_File26_Connector {
    const SLUG='file06-public-encyclopedia';
    public static function register(){
        add_filter('sabri_file26_connector_manifests',array(__CLASS__,'append'),20);
        add_action('sabri_file26_connectors_ready',array(__CLASS__,'ready'),20);
        // File26 collects this filter at plugins_loaded; do not also register
        // directly. Duplicate registrations each persist the same connector.
    }
    public static function manifest(){
        return array(
            'slug'=>self::SLUG,'owner_file'=>'File 06','contract_version'=>'1.0',
            'entity_types'=>array('encyclopedia_entry','concept'),
            'privacy_classes'=>array('public'),'visibility_fields'=>array('public'),
            'deletion_semantics'=>'owner_integrity_tombstone_reconciliation_pending',
            'status'=>'proposed',
            'event_contract'=>'File06IntegrityStateChanged.v1.pending-file26-cutover',
            'index_schema'=>'sabri.file26.document.v1.1',
            'list_batch'=>array(__CLASS__,'list_batch'),
            'can_view'=>array(__CLASS__,'can_view'),
            'health'=>array(__CLASS__,'health'),
            'fetch_object'=>array(__CLASS__,'fetch_object')
        );
    }
    public static function append($manifests){
        $manifests=is_array($manifests)?$manifests:array();
        foreach($manifests as $item){if(is_array($item)&&($item['slug']??'')===self::SLUG){return $manifests;}}
        $manifests[]=self::manifest();return $manifests;
    }
    public static function ready($registry){
        if(!is_object($registry)||!method_exists($registry,'register')){return;}
        // Current File26 boot first consumes the manifests filter and then
        // emits connectors_ready; only use this event as a fallback.
        if(method_exists($registry,'get')&&$registry->get(self::SLUG)){return;}
        $registry->register(self::manifest());
    }
    private static function source_ready(){
        return class_exists('HE_V2_Domain')&&class_exists('HE_V2_Schema')&&
            defined('HE_SCHEMA_VERSION')&&'active'===HE_V2_Schema::runtime_status()&&
            class_exists('HE_V24_Migration_Safety')&&HE_V24_Migration_Safety::ready();
    }
    private static function same_origin($url){
        $base=wp_parse_url(home_url('/'));
        $target=wp_parse_url((string)$url);
        if(!is_array($base)||!is_array($target)
           ||empty($base['scheme'])||empty($base['host'])
           ||empty($target['scheme'])||empty($target['host'])
           ||isset($target['user'])||isset($target['pass'])||isset($target['fragment'])){
            return false;
        }
        return strtolower((string)$base['scheme'])===strtolower((string)$target['scheme'])
            &&strtolower((string)$base['host'])===strtolower((string)$target['host'])
            &&(string)($base['port']??'')===(string)($target['port']??'');
    }
    private static function project($row,$version=0){
        if(!self::source_ready()||!is_array($row)||!empty($row['merged_into_id'])||
            ($row['status']??'')!=='published'||!HE_V2_Domain::is_public_concept($row)){return null;}
        $row_version=(int)($row['row_version']??0);
        if($row_version<1||($version>0&&$row_version!==$version)){return null;}
        // Only owner-approved bounded public DTO fields. Never export full
        // structured body, unverified aliases, research datasets or cases.
        $dto=HE_V2_Domain::public_dto($row);
        if(!is_array($dto)||empty($dto['id'])||empty($dto['title'])||empty($dto['canonical_url'])||
            (string)$dto['id']!==(string)($row['public_id']??'')){return null;}
        if(($dto['review_status']??'')!=='approved'||($dto['safety_status']??'')!=='approved'||($dto['record_status']??'')!=='published'){return null;}
        $safe_url=esc_url_raw((string)$dto['canonical_url']);
        // A valid outbound HTTPS URL may still lead to an external host.
        // File26's public canonical route must remain on the WordPress origin.
        if($safe_url===''||!wp_http_validate_url($safe_url)||!self::same_origin($safe_url)){return null;}
        $title=sanitize_text_field((string)$dto['title']);
        $summary=sanitize_text_field(wp_strip_all_tags((string)($dto['summary']??''),true));
        return array(
            'connector_slug'=>self::SLUG,'domain'=>'encyclopedia',
            'object_id'=>(string)$dto['id'],
            'object_version'=>$row_version,'source_event_sequence'=>$row_version,
            'entity_type'=>'encyclopedia_entry',
            'locale'=>substr(sanitize_text_field((string)($dto['language']??'en-US')),0,20),
            'state'=>'published','visibility'=>'public',
            'title'=>$title,'excerpt'=>$summary,'search_text'=>$title.' '.$summary,
            'canonical_url'=>$safe_url,'safety_class'=>'educational_reference'
        );
    }
    public static function list_batch($cursor='',$limit=50,$scope=array()){
        global $wpdb;
        if(!self::source_ready()){return new WP_Error('file06_search_source_unready','Verified File06 schema and migration are required.');}
        // File26's reindex worker supplies ['connector' => owner_slug].
        // All other scopes are unsupported: ignoring them could return
        // unexpectedly broad owner data to a caller expecting a subset.
        if(!is_array($scope)||($scope!==array()&&
            (count($scope)!==1||!isset($scope['connector'])||
            $scope['connector']!==self::SLUG))){
            return new WP_Error('file06_search_partial_scope','Only the complete owner snapshot is supported.');
        }
        if($cursor!==''&&(!is_string($cursor)||!preg_match('/^[0-9]{1,18}$/D',$cursor))){return new WP_Error('file06_search_cursor_invalid','Invalid cursor.');}
        $table=HE_V2_Schema::table('concepts');
        $limit=max(1,min(50,(int)$limit));$current=(int)$cursor;
        $wpdb->last_error='';
        $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE id>%d ORDER BY id ASC LIMIT %d",$current,$limit+1),ARRAY_A);
        if(!is_array($rows)||''!==(string)$wpdb->last_error){return new WP_Error('file06_search_source_unreadable','Cannot read canonical concepts.');}
        $more=count($rows)>$limit;if($more){array_pop($rows);}
        $items=array();
        foreach($rows as $row){
            $doc=self::project($row,(int)$row['row_version']);
            if($doc){$items[]=$doc;}
            $current=max($current,(int)$row['id']);
        }
        return array('items'=>$items,'done'=>!$more,'next_cursor'=>$more?(string)$current:'');
    }
    public static function fetch_object($entity_type,$public_id=''){
        if(!in_array($entity_type,array('encyclopedia_entry','concept'),true)||!is_string($public_id)
            ||$public_id===''||ctype_digit($public_id)||!self::source_ready()){return null;}
        $row=HE_V2_Domain::concept_by_id($public_id,false);
        if(!is_array($row)||(string)$row['public_id']!==$public_id){return null;}
        return self::project($row);
    }
    private static function matches_version($provided,$owner){
        if(!is_int($owner)||$owner<1){return false;}
        if(is_int($provided)){return $provided===$owner;}
        return is_string($provided)&&preg_match('/^[1-9][0-9]{0,17}$/D',$provided)
            &&$provided===(string)$owner;
    }
    public static function can_view($document,$audience=array()){
        if(!is_array($document)||($document['connector_slug']??'')!==self::SLUG||($document['visibility']??'')!=='public'){return false;}
        $fresh=self::fetch_object((string)($document['entity_type']??''),(string)($document['object_id']??''));
        // File26 must not reuse a stale or tampered canonical destination,
        // locale or type when a previously indexed document is clicked.
        return is_array($fresh)
            &&self::matches_version($document['object_version']??null,$fresh['object_version'])
            &&(string)$fresh['canonical_url']===(string)($document['canonical_url']??'')
            &&(string)$fresh['locale']===(string)($document['locale']??'')
            &&(string)$fresh['entity_type']===(string)($document['entity_type']??'');
    }
    public static function health(){
        return self::source_ready()?
            array('state'=>'healthy','owner_file'=>'File 06','status'=>'proposed','review_and_safety_verified'=>true,'event_tombstone_cutover_approved'=>false):
            array('state'=>'degraded','owner_file'=>'File 06','reason'=>'schema_migration_or_policy_not_verified');
    }
}
