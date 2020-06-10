<?php

\SilverStripe\Admin\ModelAdmin::add_extension("ImportAdminExtension");
$remove = \SilverStripe\Core\Config\Config::inst()->get('ModelAdmin','removelegacyimporters');
if($remove === "scaffolded"){
	\SilverStripe\Core\Config\Config::inst()->update("ModelAdmin", 'model_importers', array());
}
//cache mappings forever
Cache::set_cache_lifetime('gridfieldimporter', null); //this want to improve
