<?php
namespace CGM\Core\Providers\CGMSuite;
use CGM\Core\Contracts\ProviderInterface;use CGM\Core\Plugin;
/**
 * Registers the rest of the CGM suite as Core providers so the control room can
 * see the whole newsroom stack at a glance. These plugins do not expose a data
 * bridge (their storage stays their own); Core detects them and, through
 * SuiteIntegration, emits reindex/SEO/purge events they can consume.
 */
final class CGMSuiteMembersProvider implements ProviderInterface {
    public function id():string{return 'cgm-suite-members';}
    private const MEMBERS = array(
        'cgm-scheduled-revisions'    => array( 'const'=>'CGM_SR_VERSION',              'label'=>'CGM Scheduled Revisions',  'caps'=>array('scheduled-updates') ),
        'cgm-seo'                    => array( 'const'=>'CGM_SEO_VERSION',             'label'=>'CGM SEO',                  'caps'=>array('seo.recalculate','metadata') ),
        'cgm-image-renamer'          => array( 'const'=>'CGM_IMAGE_RENAMER_VERSION',   'label'=>'CGM Image Renamer',        'caps'=>array('media.filenames','seo.images') ),
        'cgm-homepage-manager'       => array( 'const'=>'CGMHM_VERSION',               'label'=>'CGM Homepage Manager',     'caps'=>array('homepage.slots','bricks') ),
        'cgm-feed-manager'           => array( 'const'=>'CGM_FEED_MANAGER_VERSION',    'label'=>'CGM Feed Manager',         'caps'=>array('distribution','syndication') ),
        'cgm-editorial-intelligence' => array( 'const'=>'CGM_EI_VERSION',              'label'=>'CGM Editorial Intelligence','caps'=>array('analytics','content-health','recommendations') ),
        'cgm-tag-manager'            => array( 'const'=>'CGM_TAG_MANAGER_VERSION',     'label'=>'CGM Tag Manager',          'caps'=>array('taxonomy.governance') ),
        'search-with-typesense'      => array( 'const'=>'CODEMANAS_TYPESENSE_VERSION',  'label'=>'Search with Typesense',    'caps'=>array('search.index','reindex') ),
    );
    public function register(Plugin $core):void{
        foreach(self::MEMBERS as $id=>$member){
            $const=$member['const'];
            if(!defined($const))continue;
            $version=constant($const);
            $core->providers()->register(array(
                'id'=>$id,
                'label'=>$member['label'],
                'version'=>is_string($version)?$version:'',
                'apis'=>array('core'=>'*'),
                'requires'=>array('wordpress'=>'*'),
                'capabilities'=>$member['caps'],
                'status'=>'active',
                'notes'=>__('Registered as a CGM suite member. Core emits reindex, SEO and purge events it can consume.','cgm-core'),
            ));
        }
    }
}
