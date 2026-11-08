<?php
/** Isolated canonical File 06 sitemap privacy gate, not installed WordPress QA. */
declare(strict_types=1);
define( 'ABSPATH', __DIR__ );
class HE_V2_Domain { const ENTRY_TYPE='he_entry'; const RESEARCH_TYPE='he_research'; }
class HE_V2_Schema {
	public static function runtime_status() { return $GLOBALS['state']; }
	public static function table($name) { return 'wp_he_' . $name; }
}
class WP_Query {
	private $vars;
	public function __construct($vars=array()) { $this->vars=$vars; }
	public function get($name) { return $this->vars[$name] ?? null; }
}
class V12DB {
	public $posts='wp_posts';
	public function prepare($sql,...$args) { return vsprintf($sql,array_map(function($s){return "'".$s."'";},$args)); }
}
$wpdb=new V12DB();$GLOBALS['state']='active';$checks=0;
function check_sitemap($p,$label) { global $checks;$checks++;if(!$p){fwrite(STDERR,"FAIL: ".$label."\n");exit(1);} }
require dirname(__DIR__).'/homeopathy-encyclopedia/includes/class-he-v12-sitemap.php';
$types=HE_V12_Sitemap::post_types(array('he_entry'=>(object)array('name'=>'he_entry'),'he_research'=>(object)array('name'=>'he_research'),'post'=>(object)array('name'=>'post')));
check_sitemap(!isset($types['he_research']),'Private research omitted from generic sitemap');
check_sitemap(isset($types['he_entry'])&&isset($types['post']),'Public nonresearch types retained');
$entry=HE_V12_Sitemap::query_args(array('posts_per_page'=>200),'he_entry');
check_sitemap($entry['he_v12_sitemap_guard']==='1','Native sitemap flag set');
check_sitemap($entry['post_status']==='publish','Published WordPress gate enforced');
$other=HE_V12_Sitemap::query_args(array('posts_per_page'=>5),'post');
check_sitemap(!isset($other['he_v12_sitemap_guard']),'Unrelated WordPress sitemaps untouched');
$clauses=HE_V12_Sitemap::query_clauses(array('join'=>'','where'=>' WHERE 1=1'),new WP_Query(array('he_v12_sitemap_guard'=>'1')));
check_sitemap(strpos($clauses['join'],"review_status='approved'")!==false,'Independent review required');
check_sitemap(strpos($clauses['join'],"safety_status='approved'")!==false,'Clinical safety required');
check_sitemap(strpos($clauses['join'],"merged_into_id=0")!==false,'Merged aliases omitted');
check_sitemap(strpos($clauses['join'],"current_version>0")!==false,'Current version required');
check_sitemap(strpos($clauses['join'],"status='published'")!==false,'Retracted excluded');
check_sitemap(strpos($clauses['where'],'he_entry')!==false,'Entry type only');
$untagged=HE_V12_Sitemap::query_clauses(array('join'=>'','where'=>' WHERE 1=1'),new WP_Query());
check_sitemap($untagged['join']===''&&$untagged['where']===' WHERE 1=1','No global query injection');
$GLOBALS['state']='migration-required';
$denied=HE_V12_Sitemap::query_args(array(),'he_entry');
check_sitemap($denied['post__in']===array(0),'Migration blocks public sitemap query');
$stale=HE_V12_Sitemap::query_clauses(array('join'=>'','where'=>' WHERE 1=1'),new WP_Query(array('he_v12_sitemap_guard'=>'1')));
check_sitemap(strpos($stale['where'],'1=0')!==false,'Stale schema fails closed even with copied query flag');
echo "File 06 v12 sitemap privacy: {$checks} isolated assertions PASS.\n";
