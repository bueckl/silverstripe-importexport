<?php

/**
 * Request handler that provides a seperate interface
 * for users to map columns and trigger import.
 */
namespace ImportExport\Gridfield;

use ImportExport\CSVFieldMapper;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\File;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\RequestHandler;
use SilverStripe\Core\Convert;
use SilverStripe\Dev\BulkLoader_Result;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_URLHandler;
use SilverStripe\Forms\GridField\GridFieldDetailForm_ItemRequest;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\View\ArrayData;

class GridFieldImporter_Request extends RequestHandler
{

    /**
     * Gridfield instance
     * @var GridField
     */
    protected $gridField;

    /**
     * The parent GridFieldImporter
     * @var GridFieldImporter
     */
    protected $component;

    /**
     * URLSegment for this request handler
     * @var string
     */
    protected $urlSegment = 'importer';

    /**
     * Parent handler to link up to
     * @var RequestHandler
     */
    protected $requestHandler;

    /**
     * RequestHandler allowed actions
     * @var array
     */
    private static $allowed_actions = array(
        'preview', 'upload', 'import'
    );

    /**
     * RequestHandler url => action map
     * @var array
     */
    private static $url_handlers = array(
        'upload!' => 'upload',
        '$Action/$FileID' => '$Action'
    );

    /**
     * Handler's constructor
     *
     * @param GridField $gridField
     * @param GridField_URLHandler $component
     * @param RequestHandler $handler
     */
    public function __construct($gridField, $component, $handler)
    {
        $this->gridField = $gridField;
        $this->component = $component;
        $this->requestHandler = $handler;
        parent::__construct();
    }

    /**
     * Return the original component's UploadField
     *
     * @return UploadField UploadField instance as defined in the component
     */
    public function getUploadField()
    {
        return $this->component->getUploadField($this->gridField);
    }

    /**
     * Upload the given file, and import or start preview.
     * @param  HTTPRequest $request
     * @return string
     */
    public function upload(HTTPRequest $request)
    {
        $field = $this->getUploadField();
        $uploadResponse = $field->upload($request);
        //decode response body. ugly hack ;o
        $body = json_decode($uploadResponse->getBody());
        $body = array_shift($body);
        //add extra data
        $body['import_url'] = Controller::join_links(
           $this->Link('preview'), $body['id'],
           // Also pull the back URL from the current request so we can persist this particular URL through the following pages.
           "?BackURL=" . $this->getBackURL($request)
        );
        //don't return buttons at all
        unset($body['buttons']);
        //re-encode
        $response = new HTTPResponse(json_encode(array($body)));

        return $response;
    }

    /**
     * Action for getting preview interface.
     * @param  HTTPRequest $request
     * @return string
     */
    public function preview(HTTPRequest $request)
    {
        $file = File::get()
            ->byID($request->param('FileID'));
        if (!$file) {
            return "file not found";
        }
        //TODO: validate file?
        $mapper = new CSVFieldMapper($file->getFullPath());
        $mapper->setMappableCols($this->getMappableColumns());
        //load previously stored values
        if ($cachedmapping = $this->getCachedMapping()) {
            $mapper->loadDataFrom($cachedmapping);
        }

        $form = $this->MapperForm();
        $form->Fields()->unshift(
            new LiteralField('mapperfield', $mapper->forTemplate())
        );
        $form->Fields()->push(new HiddenField("BackURL", "BackURL", $this->getBackURL($request)));
        $form->setFormAction($this->Link('import').'/'.$file->ID);
        $content = ArrayData::create(array(
            'File' => $file,
            'MapperForm'=> $form
        ))->renderWith('GridFieldImporter_preview');
        $controller = $this->getToplevelController();

        return $controller->customise(array(
            'Content' => $content
        ));
    }

    /**
     * The import form for creating mapping,
     * and choosing options.
     * @return Form
     */
    public function MapperForm()
    {
        $fields = new FieldList(
            CheckboxField::create("HasHeader",
                "This data includes a header row.",
                true
            )
        );
        if ($this->component->getCanClearData()) {
            $fields->push(
                CheckboxField::create("ClearData",
                    "Remove all existing records before import."
                )
            );
        }
        $actions = new FieldList(
            new FormAction("import", "Import CSV"),
            new FormAction("cancel", "Cancel")
        );
        $form = new Form($this, __FUNCTION__, $fields, $actions);

        return $form;
    }

    /**
     * Get all columns that can be mapped to in BulkLoader
     * @return array
     */
    protected function getMappableColumns()
    {
        return $this->component->getLoader($this->gridField)
                    ->getMappableColumns();
    }

    /**
     * Import the current file
     * @param  HTTPRequest $request
     */
    public function import(HTTPRequest $request)
    {
        $hasheader = (bool)$request->postVar('HasHeader');
        $cleardata = $this->component->getCanClearData() ?
                         (bool)$request->postVar('ClearData') :
                         false;
        if ($request->postVar('action_import')) {
            $file = File::get()
                ->byID($request->param('FileID'));
            if (!$file) {
                return "file not found";
            }
            $colmap = Convert::raw2sql($request->postVar('mappings'));
            if ($colmap) {
                //save mapping to cache
                $this->cacheMapping($colmap);
                //do import
                $results = $this->importFile(
                    $file->getFullPath(),
                    $colmap,
                    $hasheader,
                    $cleardata
                );
                $this->gridField->getForm()
                    ->sessionMessage($results->getMessage(), 'good');
            }
        }
        $controller = $this->getToplevelController();
        $controller->redirectBack();
    }

    /**
     * Do the import using the configured importer.
     * @param  string $filepath
     * @param  array|null $colmap
     * @return BulkLoader_Result
     */
    public function importFile($filepath, $colmap = null, $hasheader = true, $cleardata = false)
    {
        $loader = $this->component->getLoader($this->gridField);
        $loader->deleteExistingRecords = $cleardata;

        //set or merge in given col map
        if (is_array($colmap)) {
            $loader->columnMap = $loader->columnMap ?
                array_merge($loader->columnMap, $colmap) : $colmap;
        }
        $loader->getSource()
            ->setFilePath($filepath)
            ->setHasHeader($hasheader);

        return $loader->load();
    }

    /**
     * Pass fileexists request to UploadField
     *
     * @link UploadField->fileexists()
     */
    public function fileexists(HTTPRequest $request)
    {
        $uploadField = $this->getUploadField();
        return $uploadField->fileexists($request);
    }

    /**
     * @param string $action
     * @return string
     */
    public function Link($action = null)
    {
        return Controller::join_links(
            $this->gridField->Link(), $this->urlSegment, $action
        );
    }

    /**
     * @see GridFieldDetailForm_ItemRequest::getTopLevelController
     * @return Controller
     */
    protected function getToplevelController()
    {
        $c = $this->requestHandler;
        while ($c && $c instanceof GridFieldDetailForm_ItemRequest) {
            $c = $c->getController();
        }
        if (!$c) {
            $c = Controller::curr();
        }

        return $c;
    }

    /**
     * Store the user defined mapping for future use.
     */
    protected function cacheMapping($mapping)
    {
        $mapping = array_filter($mapping);
        if ($mapping && !empty($mapping)) {
            $cache = SS_Cache::factory('gridfieldimporter');
            $cache->save(serialize($mapping), $this->cacheKey());
        }
    }

    /**
     * Look for a previously stored user defined mapping.
     */
    protected function getCachedMapping()
    {
        $cache = SS_Cache::factory('gridfieldimporter');
        if ($result = $cache->load($this->cacheKey())) {
            return unserialize($result);
        }
    }

    /**
     * Generate a cache key unique to this gridfield
     */
    protected function cacheKey()
    {
        return md5($this->gridField->Link());
    }

   /**
    * Get's the previous URL that lead up to the current request.
    *
    * NOTE: Honestly, this should be built into SS_HTTPRequest, but we can't depend on that right now... so instead,
    * this is being copied verbatim from Controller (in the framework).
    *
    * @param HTTPRequest $request
    * @return string
    */
   protected function getBackURL(HTTPRequest $request) {
      // Initialize a sane default (basically redirects to root admin URL).
      $controller = $this->getToplevelController();
      $url = method_exists($this->requestHandler, "Link") ?
            $this->requestHandler->Link() :
            $controller->Link();

      // Try to parse out a back URL using standard framework technique.
      if($request->requestVar('BackURL')) {
         $url = $request->requestVar('BackURL');
      } else if($request->isAjax() && $request->getHeader('X-Backurl')) {
         $url = $request->getHeader('X-Backurl');
      } else if($request->getHeader('Referer')) {
         $url = $request->getHeader('Referer');
      }

      return $url;
   }

}
