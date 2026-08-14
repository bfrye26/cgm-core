<?php
namespace CGM\Core\Support;
final class ApiCompatibility {
    public function satisfies(string $required):bool{return VersionConstraint::matches(defined('CGM_CORE_API_VERSION')?CGM_CORE_API_VERSION:'0',$required);}
    public function report(array $requirements):array{$out=array();foreach($requirements as $api=>$constraint){$current=match($api){'core'=>CGM_CORE_API_VERSION,'query'=>defined('CGM_CORE_QUERY_API_VERSION')?CGM_CORE_QUERY_API_VERSION:CGM_CORE_API_VERSION,'relationships'=>defined('CGM_CORE_RELATIONSHIP_API_VERSION')?CGM_CORE_RELATIONSHIP_API_VERSION:CGM_CORE_API_VERSION,'dynamic_data'=>defined('CGM_CORE_DYNAMIC_DATA_API_VERSION')?CGM_CORE_DYNAMIC_DATA_API_VERSION:CGM_CORE_API_VERSION,default=>'0'};$out[$api]=array('required'=>$constraint,'current'=>$current,'compatible'=>VersionConstraint::matches($current,(string)$constraint));}return $out;}
    public function deprecated(string $symbol,string $version,string $replacement=''):void{_deprecated_function($symbol,$version,$replacement?:null);do_action('cgm_core/deprecated',$symbol,$version,$replacement);}
}
