<?php

use SilverStripe\Admin\ModelAdmin;
use ImportExport\Extensions\ImportAdminExtension;
use SilverStripe\Core\Config\Config;

ModelAdmin::add_extension(ImportAdminExtension::class);
$remove = Config::inst()->get('ModelAdmin','removelegacyimporters');
if($remove === "scaffolded"){
    Config::inst()->update("ModelAdmin", 'model_importers', array());
}
//cache mappings forever
//Cache::set_cache_lifetime('gridfieldimporter', null); //this want to improve
