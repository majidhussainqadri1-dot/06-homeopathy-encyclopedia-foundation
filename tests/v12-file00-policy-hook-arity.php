<?php
/**
 * Real WordPress add_filter() callback arity regression for File06→File00.
 * Caller supplies ($source, $locale); registering accepted_args=1 omits locale,
 * causing an ArgumentCountError before the independent policy can be checked.
 * Isolated test; the paired real WordPress/MariaDB test lives in File00 PR56.
 */
declare(strict_types=1);
define('ABSPATH', __DIR__);
$GLOBALS['hook_args'] = array();
function add_filter($tag,$callback,$priority=10,$accepted_args=1) {
    $GLOBALS['hook_args'][$tag]=array('callback'=>$callback,'accepted_args'=>$accepted_args);
    return true;
}
function add_action($tag,$callback,$priority=10,$accepted_args=1){return true;}
require dirname(__DIR__).'/homeopathy-encyclopedia/includes/class-he-v12-case-consent-owner.php';
HE_V12_Case_Consent_Owner::hooks();
$claim=$GLOBALS['hook_args']['he_v12_file06_consent_policy_v1']??null;
if(!is_array($claim)
    || $claim['accepted_args']!==2
    || !is_array($claim['callback'])
    || $claim['callback'][1]!=='approved_policy'){
    fwrite(STDERR,"FAIL: File06 approved policy must receive exactly source and locale.\n");
    exit(1);
}
$relation=$GLOBALS['hook_args']['he_v12_file06_patient_relation_v1']??null;
if(!is_array($relation) || $relation['accepted_args']!==3){
    fwrite(STDERR,"FAIL: File06 patient/case relation must receive exact subject + UUID.\n");
    exit(1);
}
echo "File06↔File00 REST consent hook signatures: 2/2 PASS.\n";
