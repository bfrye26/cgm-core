<?php
namespace CGM\Core\REST;use CGM\Core\Plugin;use CGM\Core\Query\SavedQueryRepository;use CGM\Core\Configuration\ConfigurationManager;use CGM\Core\Telemetry\Telemetry;
final class RestRegistrar {
    public function __construct(private Plugin $core,private SavedQueryRepository $repo,private ConfigurationManager $config){}
    public function register():void{foreach(array(new BootstrapController($this->core),new RegistryController($this->core),new QueryController($this->core,$this->repo),new EditorController($this->core),new RelationshipController($this->core),new DynamicDataController($this->core),new ObjectController($this->core),new ConfigController($this->config),new IntegrityController($this->core),new ActivityController(new Telemetry()),new IndexController($this->core),new RuleController($this->core),new BulkController($this->core),new WorkflowController($this->core),new SearchController($this->core),new GraphController($this->core),new FacadeController($this->core)) as $c)$c->register_routes();}
}
