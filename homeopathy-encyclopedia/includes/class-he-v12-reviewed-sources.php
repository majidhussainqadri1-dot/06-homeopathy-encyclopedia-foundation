<?php
/**
 * File 06 v12 public side-by-side original texts and peer-reviewed translations.
 * Source text belongs to File 12; File 06 owns approved comparative commentary.
 * A missing PDF, rights decision, receipt, canonical version or independent
 * review immediately removes a quote from any public File 06 presentation.
 */
defined('ABSPATH') || exit;

final class HE_V12_Reviewed_Sources {
	const OPTION_PREFIX='he_v12_reviewed_source_';

	public static function hooks() {
		add_action('rest_api_init',array(__CLASS__,'routes'));
	}

	private static function key($concept_id,$reference_id) {
		return self::OPTION_PREFIX.hash('sha256',strtolower($concept_id).'|'.absint($reference_id));
	}
	private static function public_entry($id) {
		$check=apply_filters('he_v12_file06_concept_assertion_v1',null,(string)$id,absint(self::requested_version($id)));
		// Use canonical File 06 row, never authority assumed from route ID alone.
		if('active'!==HE_V2_Schema::runtime_status())return null;
		$row=HE_V2_Domain::concept_by_id((string)$id,true);
		if(!is_array($row)||!hash_equals(strtolower((string)$row['public_id']),strtolower((string)$id))
			||'published'!==($row['status']??'')||!HE_V2_Domain::is_public_concept($row))return null;
		$dto=HE_V2_Domain::public_dto($row);
		if(!is_array($dto)||(int)($dto['version']??0)<1
			||(int)$dto['version']!==(int)($dto['current_version']??0))return null;
		return array($row,$dto);
	}
	private static function requested_version($id) {return 1;}

	private static function reference($dto,$position) {
		$position=filter_var($position,FILTER_VALIDATE_INT);
		$all=$dto['references']??array();
		if(false===$position||$position<0||$position>=min(100,count((array)$all)))return null;
		$ref=$all[$position]??null;
		return is_array($ref)&&absint($ref['id']??0)>0?$ref:null;
	}

	private static function PDF_proof($claim,$reference,$dto) {
		if(!is_array($claim)||!is_array($reference)||!is_array($dto)
			||!hash_equals(strtolower((string)$dto['id']),(string)($claim['concept_public_id']??''))
			||(int)$dto['version']!==(int)($claim['concept_version']??0)
			||(int)$reference['id']!==(int)($claim['reference_id']??0))return null;
		try {
			$proof=apply_filters('he_v12_file12_quote_assertion_v1',null,$claim);
		} catch(Throwable $error){return null;}
		if(!is_array($proof)||true!==($proof['verified']??false)
			||'file-12'!==($proof['owner_file']??'')
			||!hash_equals((string)($claim['original_excerpt']??''),(string)($proof['exact']??''))
			||!hash_equals((string)($claim['edition_sha256']??''),(string)($proof['edition_sha256']??''))
			||(int)($claim['page']??0)!==(int)($proof['page']??0)
			||!hash_equals((string)($claim['rights_evidence_ref']??''),(string)($proof['rights_evidence_ref']??'')))return null;
		// Reference bibliography must name the edition/source being quoted,
		// not any unrelated book from the same concept's reference list.
		$left=trim(mb_strtolower((string)($reference['title']??''),'UTF-8'));
		$right=trim(mb_strtolower((string)($proof['source_title']??''),'UTF-8'));
		if(!$left||!$right||$left!==$right)return null;
		$reference_edition=trim((string)($reference['edition']??''));
		if($reference_edition!==''&&!hash_equals($reference_edition,(string)($proof['edition_label']??'')))return null;
		return $proof;
	}

	/** Public verified quotation DTO, no draft text leakage. */
	public static function column($dto,$position) {
		if(!is_array($dto)||!isset($dto['id'],$dto['version']))return null;
		$ref=self::reference($dto,$position);
		if(!$ref)return null;
		$record=get_option(self::key((string)$dto['id'],(int)$ref['id']),null);
		if(!is_array($record)||'approved'!==($record['state']??'')
			||absint($record['submitted_by']??0)<1||absint($record['approved_by']??0)<1
			||absint($record['submitted_by'])===absint($record['approved_by'])
			||absint($record['concept_version']??0)!==absint($dto['version'])
			||!hash_equals(hash('sha256',(string)($record['original_excerpt']??'')."\n".(string)($record['translation']??'')),
				(string)($record['content_sha256']??'')))return null;
		$proof=self::PDF_proof($record,$ref,$dto);
		if(!$proof)return null;
		return array(
			'original_excerpt'=>(string)$record['original_excerpt'],
			'translation'=>(string)$record['translation'],
			'translation_locale'=>(string)$record['translation_locale'],
			'review_note'=>(string)$record['review_note'],
			'rights_basis'=>(string)$proof['rights_basis'],
			'page'=>(int)$proof['page'],
			'edition_id'=>(int)$proof['edition_id'],
			'source_url'=>esc_url_raw((string)$proof['citation_url']),
			'original_source_owner'=>'file-12',
			'translation_status'=>'independently-reviewed',
			'approved_at_utc'=>(string)$record['approved_at_utc'],
		);
	}

	public static function routes() {
		register_rest_route(HE_V2_API::NS,'/v12/reviewed-source/(?P<id>[0-9a-fA-F-]{36})/(?P<position>[0-9]{1,2})',array(
			array('methods'=>'POST','callback'=>array(__CLASS__,'stage'),
				'permission_callback'=>static function($request) {
					$pair=self::public_entry((string)$request['id']);
					return $pair&&true===HE_V2_Auth::rest_permission(HE_V2_Auth::CAP_EDIT,
						(int)$pair[0]['post_id'],'file06-v12-stage-verified-source');
				}),
		));
		register_rest_route(HE_V2_API::NS,'/v12/reviewed-source/(?P<id>[0-9a-fA-F-]{36})/(?P<position>[0-9]{1,2})/approve',array(
			'methods'=>'POST','callback'=>array(__CLASS__,'approve'),
			'permission_callback'=>static function($request) {
				$pair=self::public_entry((string)$request['id']);
				return $pair&&true===HE_V2_Auth::rest_permission(HE_V2_Auth::CAP_REVIEW,
					(int)$pair[0]['post_id'],'file06-v12-approve-verified-translation');
			},
		));
	}

	public static function stage($request) {
		if(!HE_V2_Auth::require_nonce($request))return new WP_Error('he_v12_source_nonce','Current session nonce required',array('status'=>403));
		$pair=self::public_entry((string)$request['id']);
		if(!$pair)return new WP_Error('he_v12_source_missing','Canonical public source missing',array('status'=>404));
		list($row,$dto)=$pair;
		$ref=self::reference($dto,$request['position']);
		if(!$ref)return new WP_Error('he_v12_reference_missing','Citation index not available',array('status'=>404));
		$data=$request->get_json_params();
		if(!is_array($data))return new WP_Error('he_v12_source_shape','Source request object invalid',array('status'=>422));
		$original=trim(sanitize_textarea_field((string)($data['original_excerpt']??'')));
		$translation=trim(sanitize_textarea_field((string)($data['translation']??'')));
		$note=trim(sanitize_textarea_field((string)($data['review_note']??'')));
		$locale=sanitize_text_field((string)($data['translation_locale']??''));
		if(strlen($original)<4||strlen($original)>3000||strlen($translation)<4||strlen($translation)>4000
			||strlen($note)>1500||!preg_match('/^[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/',$locale)
			||absint($data['concept_version']??0)!==(int)$dto['version'])
			||absint($data['reference_id']??0)!==(int)$ref['id'])
			||!preg_match('/^[a-f0-9]{64}$/i',(string)($data['edition_sha256']??''))
			||!preg_match('/^[A-Za-z0-9._:-]{8,120}$/',(string)($data['rights_evidence_ref']??''))) {
			return new WP_Error('he_v12_source_invalid','Original and translation, exact citation, rights and current versions required',array('status'=>422));
		}
		$claim=array('concept_public_id'=>strtolower((string)$dto['id']),
			'concept_version'=>(int)$dto['version'],'reference_id'=>(int)$ref['id'],
			'original_excerpt'=>$original,'exact'=>$original,
			'translation'=>$translation,'translation_locale'=>$locale,'review_note'=>$note,
			'edition_id'=>absint($data['edition_id']??0),'edition_version'=>absint($data['edition_version']??0),
			'edition_sha256'=>strtolower((string)$data['edition_sha256']),
			'page'=>absint($data['page']??0),'rights_evidence_ref'=>(string)$data['rights_evidence_ref']);
		if(!self::PDF_proof($claim,$ref,$dto))return new WP_Error('he_v12_source_unverified','File12 exact source/edition/page rights proof unavailable',array('status'=>503));
		$claim['state']='pending';
		$claim['submitted_by']=get_current_user_id();
		$claim['submitted_at_utc']=gmdate('c');
		$claim['approved_by']=0;
		$claim['content_sha256']=hash('sha256',$original."\n".$translation);
		$key=self::key((string)$dto['id'],(int)$ref['id']);
		if(false===update_option($key,$claim,false)&&get_option($key)!==$claim) {
			return new WP_Error('he_v12_source_stage_failed','Reviewed source proposal was not saved',array('status'=>503));
		}
		return rest_ensure_response(array('state'=>'pending','reference_id'=>(int)$ref['id'],
			'source_hash'=>$claim['content_sha256'],'requires_independent_review'=>true));
	}

	public static function approve($request) {
		if(!HE_V2_Auth::require_nonce($request))return new WP_Error('he_v12_source_nonce','Current session nonce required',array('status'=>403));
		$pair=self::public_entry((string)$request['id']);
		if(!$pair)return new WP_Error('he_v12_source_missing','Canonical public source missing',array('status'=>404));
		list($row,$dto)=$pair;
		$ref=self::reference($dto,$request['position']);
		if(!$ref)return new WP_Error('he_v12_reference_missing','Citation index not available',array('status'=>404));
		$key=self::key((string)$dto['id'],(int)$ref['id']);
		$record=get_option($key,null);
		$reviewer=get_current_user_id();
		if(!is_array($record)||'pending'!==($record['state']??'')
			||!$reviewer||$reviewer===absint($record['submitted_by']??0)
			||$reviewer===absint($row['created_by']??0)
			||!HE_V2_Auth::membership_allowed($reviewer)
			||!self::PDF_proof($record,$ref,$dto))return new WP_Error('he_v12_source_review_denied','Fresh independent File06 review and File12 proof are required',array('status'=>403));
		$data=$request->get_json_params();
		if(!is_array($data)||true!==($data['source_and_translation_compared']??false)
			||!hash_equals((string)$record['content_sha256'],(string)($data['content_sha256']??''))
			||!preg_match('/^[A-Za-z0-9._:-]{8,120}$/',(string)($data['review_evidence_ref']??''))) {
			return new WP_Error('he_v12_source_review_missing','Human comparison, exact content digest and independent review evidence required',array('status'=>422));
		}
		$record['state']='approved';$record['approved_by']=$reviewer;
		$record['approved_at_utc']=gmdate('c');
		$record['review_evidence_ref']=sanitize_text_field((string)$data['review_evidence_ref']);
		if(false===update_option($key,$record,false)&&get_option($key)!==$record)
			return new WP_Error('he_v12_source_review_store','Independent source approval could not be saved',array('status'=>503));
		return rest_ensure_response(array('state'=>'approved','reference_id'=>(int)$ref['id'],
			'content_sha256'=>$record['content_sha256'],'reviewer_id'=>$reviewer));
	}
}
