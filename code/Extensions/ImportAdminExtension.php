<?php

namespace ImportExport\Extensions;

use ImportExport\Gridfield\GridFieldImporter;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;
use SilverStripe\Security\Security;

class ImportAdminExtension extends Extension
{

    /**
     * Prevent existing import form from showing up
     * @todo: there should be a better way to disable from an extension, rather than
     * disabling it after it has been created
     */
    public function updateImportForm(&$form)
    {
        if (Config::inst()->get('ModelAdmin', 'removelegacyimporters') === true) {
            $form = null;
        }
    }

    /**
     * Add in new bulk GridFieldImporter
     */
    public function updateEditForm($form)
    {
        if ($doadd = Config::inst()->get(ModelAdmin::class, 'addbetterimporters')) {
            $modelclass = $this->owner->modelClass;
            $grid = $form->Fields()->fieldByName($modelclass);
            $config =  $grid->getConfig();

            //don't proceed if there is already an importer
            if ($config->getComponentByType(GridFieldImporter::class)) {
                return;
            }
            //don't proceed if can't create
            if (!singleton($modelclass)->canCreate(Security::getCurrentUser())) {
                return;
            }
            //allow config to avoid adding when there are existing importers
            $importerClasses = $this->owner->stat('model_importers');
            if (
                $doadd === "scaffolded" &&
                !is_null($importerClasses) &&
                isset($importerClasses[$modelclass])
            ) {
                return;
            }

            //add the component
            $config->addComponent(new GridFieldImporter('before'));
        }
    }
}
