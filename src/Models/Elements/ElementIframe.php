<?php

namespace NSWDPC\Elemental\Models\Iframe;

use DNADesign\Elemental\Models\BaseElement;
use gorriecoe\Link\Models\Link;
use NSWDPC\InlineLinker\InlineLinkCompositeField;
use NSWDPC\Elemental\Controllers\Iframe\ElementIframeController;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Security\Permission;
use SilverStripe\View\Requirements;
/**
 * ElementIframe class
 *
 * @author Mark Taylor <mark.taylor@dpc.nsw.gov.au>
 * @author James Ellis <mark.taylor@dpc.nsw.gov.au>
 */
class ElementIframe extends BaseElement implements PermissionProvider {

    private static $table_name = 'ElementIframe';

    private static $icon = 'font-icon-code';

    private static $singular_name = 'Iframe';
    private static $plural_name = 'Iframes';

    private static $default_allow_attributes = [
        'fullscreen'
    ];

    private static $has_one = [
        'URL' => Link::class,
    ];

    private static $db = [
        'IsLazy' => 'Boolean',
        'IsFullWidth' =>  'Boolean',
        'IsDynamic' =>  'Boolean',
        'IsResponsive' =>  'Varchar(8)',
        'Width' => 'Varchar(8)',
        'Height' => 'Varchar(8)',
        'AlternateContent' => 'Text'
    ];

    private static $defaults = [
        'IsLazy' => 1,
        'IsFullWidth' => 1,
        'IsResponsive' => '16x9',
        'Width' => '100%',
        'Height' => '400',
    ];

    private static $title = 'Iframe';
    private static $description = 'Display content in an HTML iframe tag';

    private static $responsive_options = [
        '16x9' => '16x9',
        '4x3' => '4x3'
    ];

    private static $default_height = '400';

    private static $load_polyfill = true;

    /**
     * @var bool
     */
    private static $resizer_log = false;

    public function getType()
    {
        return _t(__CLASS__ . '.BlockType', 'Iframe');
    }

    /**
     * Apply requirements when templating
     */
    public function forTemplate($holder = true)
    {

        // Responsive CSS
        if($this->IsResponsive) {
            Requirements::css(
                'nswdpc/silverstripe-elemental-iframe:client/static/style/iframe.css',
                'screen',
                [
                    'integrity' => 'BCvA93KSwNd2uyy/627Fmtp2cpR8qUvOA2b1zO52ashQ6RPM7BoEieDfManGxC2aq9XiL2jYmwWEcRZF+3Vovw==',
                    'crossorigin' => 'anonymous'
                ]
            );
        }

        // Dynamic iframe height
        if($this->IsDynamic) {
            Requirements::javascript(
                'https://cdnjs.cloudflare.com/ajax/libs/iframe-resizer/4.3.2/iframeResizer.min.js',
                [
                    'integrity' => 'sha512-dnvR4Aebv5bAtJxDunq3eE8puKAJrY9GBJYl9GC6lTOEC76s1dbDfJFcL9GyzpaDW4vlI/UjR8sKbc1j6Ynx6w==',
                    'crossorigin' => 'anonymous'
                ]
            );
            // Custom script, with uniquenessId to set only once in the case of > 1 iframe elements in the page
            Requirements::customScript(
                $this->DynamicCustomScript(),
                'iframe-resizer-trigger'
            );

        }
        // Lazy load polyfill, if configured and LazyLoad is on
        if($this->IsLazy && $this->config()->get('load_polyfill')) {
            Requirements::javascript(
                "https://cdnjs.cloudflare.com/ajax/libs/loading-attribute-polyfill/1.5.4/loading-attribute-polyfill.min.js",
                [
                    "integrity" => "sha512-Kq3/MTxphzXJIRDWtrpLhhNnLDPiBXPMKkx/KogMYZO92Geor9j8sJguZ1OozBS+YVmVKo2HEx2gZfGOQBFM8A==",
                    "crossorigin" => "anonymous"
                ]
            );
        }
        return parent::forTemplate($holder);
    }

    /**
     * Return the script used to handle dynamic height changes
     * this fires iFrameResize on window.load
     * @return string
     */
    public function DynamicCustomScript() : string {
            $log = $this->config()->get('resizer_log') ? 'true' : 'false';
            $script = <<<JAVASCRIPT
window.addEventListener('load', function() {
    try { iFrameResize( { log: {$log} }, '.iframe-resizer iframe' ); } catch (e) { console.warn(e); }
});
JAVASCRIPT;
        return $script;
    }

    /**
     * Return id attribute for iframe element
     */
    public function IframeID() : string {
        return $this->getAnchor() . "-frame";
    }

    /**
     * Return default 'allow' attribute values
     * @return string (escaped)
     */
    public function DefaultAllowAttributes() {
        $allow = $this->config()->get('default_allow_attributes');
        if(is_array($allow) && !empty($allow)) {
            $allow = array_unique($allow);
            $allow_value = htmlentities(implode(" ", $allow));
        } else {
            $allow_value = "";
        }
        return $allow_value;
    }

    /**
     * Provide a set of permissions to lock down who can add/view/delete iframe elements
     * @return array
     */
    public function providePermissions()
    {
        return [
            'ELEMENT_IFRAME_EDIT' => [
                'name' => 'Edit iframe elements',
                'category' => 'Iframe',
            ],
            'ELEMENT_IFRAME_DELETE' => [
                'name' => 'Delete iframe elements',
                'category' => 'Iframe',
            ]
        ];
    }

    public function canEdit($member = null)
    {
        return Permission::checkMember($member, 'ELEMENT_IFRAME_EDIT');
    }

    public function canDelete($member = null)
    {
        return Permission::checkMember($member, 'ELEMENT_IFRAME_DELETE');
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::checkMember($member, 'ELEMENT_IFRAME_EDIT');
    }

    public function getResponsiveOptions() {
        return $this->config()->get('responsive_options') ?: [];
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if($this->Width <= 0 || $this->IsFullWidth || $this->IsResponsive) {
            $this->Width = "100%";
        }

        if($this->Height <= 0) {
            $this->Height = $this->getDefaultHeight();
        }
    }

    /**
     * Return the height, the configured height or a default height, to ensure one
     */
    public function getIframeHeight() {
        $height = $this->getField('Height');
        if(!$height) {
            $height = $this->getDefaultHeight();
        }
        return $height;
    }

    /**
     * Return the width or 100% if not set
     */
    public function getIframeWidth() {
        $width = $this->getField('Width');
        if(!$width || $this->IsFullWidth || $this->IsResponsive) {
            $width = "100%";
        }
        return $width;
    }

    protected function getDefaultHeight() {
        $height = $this->config()->get('default_height');
        if(!$height) {
            $height = '400';
        }
        return $height;
    }

    public function getCMSFields() {
        $fields = parent::getCMSFields();

        $fields->removeByName([
            'URLID'
        ]);

        $fields->addFieldsToTab(
            'Root.Main', [
                InlineLinkCompositeField::create(
                    'URL',
                    _t(__CLASS__. '.URL', 'URL'),
                    $this
                ),
                CheckboxField::create(
                    'IsLazy',
                    _t(__CLASS__. '.LAZY_LOAD', 'Lazy load')
                )->setDescription(
                    _t(
                        __CLASS__ . '.LAZYLOAD_DESCRIPTION',
                        'When checked, load the iframe URL when contents are in view'
                    )
                ),
                CheckboxField::create(
                    'IsDynamic',
                    _t(__CLASS__. '.DYNAMIC', 'Automatically set height to iframe content')
                )->setDescription(
                    _t(
                        __CLASS__ . '.DYNAMIC_DESCRIPTION',
                        'Requires <code>https://github.com/davidjbradshaw/iframe-resizer</code> to be installed on the remote page. This option is not compatible with responsive iframes.'
                    )
                ),
                CheckboxField::create(
                    'IsFullWidth',
                    _t(__CLASS__. '.FULL_WIDTH', 'Enforce full width')
                )->setDescription(
                    _t(
                        __CLASS__ . '.FULL_WIDTH_DESCRIPTION',
                        'When checked, this option will override the width to 100% of the container and maintain aspect ratio'
                    )
                ),
                DropdownField::create(
                    'IsResponsive',
                    'Responsive options',
                    $this->getResponsiveOptions()
                )->setEmptyString('-not responsive-')
                    ->setDescription(
                        _t(
                            __CLASS__ . '.RESPONSIVE_DESCRIPTION',
                            'When set, this option will override the width to 100% of the container and maintain aspect ratio'
                        )
                ),
                TextField::create(
                    'Width',
                    _t(__CLASS__. '.WIDTH', 'Width')
                )->setDescription(
                    _t(
                        __CLASS__ . '.WIDTH_DESCRIPTION',
                        'A width value, for example <code>100%</code> or <code>800</code>. Do not specify \'px\'.'
                    )
                ),
                TextField::create(
                    'Height',
                    _t(__CLASS__. '.HEIGHT', 'Height')
                )->setDescription(
                    _t(
                        __CLASS__ . '.HEIGHT_DESCRIPTION',
                        'A height value, for example <code>200</code> or <code>800</code>. Do not specify \'px\'.'
                    )
                ),
                TextareaField::create(
                    'AlternateContent',
                    _t(__CLASS__. '.ALTERNATE_CONTENT', 'Alternate content for used for assistive technologies')
                )->setRows(4)
                ->setDescription(
                    _t(
                        __CLASS__. '.ALTERNATE_CONTENT_DESCRIPTION',
                        "This value should concisely describe the embedded content to people using assistive technologies such as a screen reader"
                    )
                )
            ]
        );
        return $fields;
    }

}
