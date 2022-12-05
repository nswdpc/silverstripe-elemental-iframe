<?php

namespace  NSWDPC\Elemental\Tests\Iframe;

use Codem\Utilities\HTML5\UrlField;
use gorriecoe\Link\Models\Link;
use gorriecoe\Link\View\Phone as PhoneView;
use NSWDPC\InlineLinker\InlineLinkCompositeField;
use NSWDPC\Elemental\Models\Iframe\ElementIframe;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use Silverstripe\Assets\Dev\TestAssetStore;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Folder;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationException;
use SilverStripe\View\Requirements;

/**
 * Unit test to verify Iframe element handling
 * @author James
 */
class IframeTest extends SapphireTest
{

    /**
     * @inheritdoc
     */
    protected $usesDatabase = true;

    /**
     * @inheritdoc
     */
    protected static $fixture_file = './IframeTest.yml';

    /**
     * @inheritdoc
     */
    public function setUp() {
        parent::setUp();
        Config::inst()->update(
            ElementIframe::class,
            'default_allow_attributes',
            [
                'fullscreen'
            ]
        );

        // Set backend root to /IframeFileTest
        TestAssetStore::activate('IframeFileTest');

        // Create a test files for each of the fixture references
        $fileIDs = array_merge(
            $this->allFixtureIDs(File::class)
        );
        foreach ($fileIDs as $fileID) {
            /** @var File $file */
            $file = DataObject::get_by_id(File::class, $fileID);
            $file->setFromString(str_repeat('x', 1000000), $file->getFilename());
            $file->doPublish();
        }

    }

    /**
     * @inheritdoc
     */
    public function tearDown()
    {
        parent::tearDown();
        TestAssetStore::reset();
    }

    /**
     * Test iframe element saving
     */
    public function testIframe() {

        $iframe = $this->objFromFixture( ElementIframe::class, 'standard');

        // save this URL value
        $url = 'https://example.com/?foo=bar&1=<small>';
        $iframe->URLValue = $url;
        $iframe->write();

        // assert the iframe has a link
        $link = $iframe->URL();
        $this->assertInstanceOf(Link::class, $link);
        $this->assertEquals('URL', $link->Type);

        $linkURL = $link->getLinkURL();
        $this->assertEquals($url, $linkURL);

        $iframe_width = $iframe->getIframeWidth();
        $this->assertEquals("100%", $iframe_width, "Responsive iframe should be 100% width");

        $iframe_height = $iframe->getIframeHeight();
        $this->assertEquals($iframe->Height, $iframe_height, "Iframe should be {$iframe->Height} height");

        $template = $iframe->forTemplate();

        $strings = [
            "is-16x9",
            "allow=\"fullscreen\"",
            "loading=\"lazy\"",
            "width=\"100%\"",
            "height=\"{$iframe->Height}\"",
            "<h2>IFRAME_TITLE</h2>",
            "title=\"ALT_CONTENT\"",
            "src=\"" . htmlspecialchars($linkURL) . "\""
        ];

        foreach($strings as $string) {
            $this->assertTrue(strpos($template, $string) !== false, "{$string} should appear in the template");
        }

        $backend = Requirements::backend();

        $js = $backend->getJavascript();
        $css = $backend->getCSS();

        $assets = array_merge($js, $css);

        $lazy_load_hash = "sha512-Kq3/MTxphzXJIRDWtrpLhhNnLDPiBXPMKkx/KogMYZO92Geor9j8sJguZ1OozBS+YVmVKo2HEx2gZfGOQBFM8A==";
        $css_hash = "BCvA93KSwNd2uyy/627Fmtp2cpR8qUvOA2b1zO52ashQ6RPM7BoEieDfManGxC2aq9XiL2jYmwWEcRZF+3Vovw==";
        $hashes = [
            $lazy_load_hash,//JS
            $css_hash//CSS
        ];

        foreach($hashes as $hash) {
            $result = array_search( $hash, array_column($assets, 'integrity'));
            $this->assertTrue($result !== false, "Expected integrity hash {$hash} is not present in requirements");
        }

        $iframe->doPublish();

        $this->assertTrue($iframe->isPublished(), "Iframe is not published");

        // turn off responsive-ness and lazy loading
        $iframe->IsResponsive = 0;
        $iframe->IsFullWidth = 0;
        $iframe->IsLazy = 0;
        $iframe->Width = 600;
        $iframe->write();
        $iframe->doPublish();

        $this->assertEquals(600, $iframe->getIframeWidth(), "Iframe width should now be 600");

        Requirements::clear();
        $template = $iframe->forTemplate();

        $backend = Requirements::backend();

        $js = $backend->getJavascript();
        $css = $backend->getCSS();

        $assets = array_merge($js, $css);

        foreach($hashes as $hash) {
            $this->assertFalse(
                array_search( $hash, array_column($assets, 'integrity') ),
                "Hash {$hash} should no longer be in requirements"
            );
        }

        // these strings should not appear in the template
        $strings = [
            "is-16x9",
            "loading=\"lazy\"",
            "width=\"100%\""
        ];

        foreach($strings as $string) {
            $this->assertFalse(strpos($template, $string), "{$string} should NOT appear in the template");
        }

    }

    /**
     * ----
     * Tests to handle migration to external URL types after moving from LinkField to a standard URL field
     * (for BC)
     * ----
     */

    public function testBCURL() {
        $expected = 'https://example.org?1=2';
        $iframe = $this->objFromFixture( ElementIframe::class, 'bcurl');
        $link = $iframe->URL();
        $this->assertEquals('URL', $link->Type);

        $field = $iframe->getCmsFields()->dataFieldByName('URLValue');
        $this->assertInstanceOf( UrlField::class, $field );
        $this->assertEquals( $expected, $field->dataValue() );

        $iframe->URLValue = $expected;
        $iframe->write();

        $this->assertEquals('URL', $link->Type);
        $this->assertEquals($expected, $iframe->getURLAsString());
    }

    public function testBCEmail() {
        $value = 'test@example.com';
        $expected = 'mailto:' . $value;
        $iframe = $this->objFromFixture( ElementIframe::class, 'bcemail');
        $link = $iframe->URL();
        $this->assertEquals('Email', $link->Type);

        $field = $iframe->getCmsFields()->dataFieldByName('URLValue');
        $this->assertInstanceOf( UrlField::class, $field );
        $this->assertEquals( $expected, $field->dataValue() );

        $iframe->URLValue = $expected;
        $iframe->write();

        $this->assertEquals('URL', $link->Type);
        $this->assertEquals($expected, $iframe->getURLAsString());
    }

    public function testBCPhone() {

        Config::inst()->update( PhoneView::class, 'default_country', 'AU');

        $value = '+61-400-000-000';
        $expected = 'tel:' . $value;
        $iframe = $this->objFromFixture( ElementIframe::class, 'bcphone');
        $link = $iframe->URL();

        $this->assertEquals('Phone', $link->Type);

        $field = $iframe->getCmsFields()->dataFieldByName('URLValue');
        $this->assertInstanceOf( UrlField::class, $field );
        $this->assertEquals( $expected, $field->dataValue() );

        try {
            $iframe->URLValue = $value;
            $iframe->write();
        } catch (ValidationException $e) {
            // This value will fail validation
            $this->assertNotEmpty($e->getMessage());
        }

    }

    public function testBCSiteTree() {
        $expected = '/page-test/';
        $iframe = $this->objFromFixture( ElementIframe::class, 'bcsitetree');
        $link = $iframe->URL();
        $this->assertEquals('SiteTree', $link->Type);

        $field = $iframe->getCmsFields()->dataFieldByName('URLValue');
        $this->assertInstanceOf( UrlField::class, $field );
        $this->assertEquals( $expected, $field->dataValue() );

        $iframe->URLValue = $expected;
        $iframe->write();

        $this->assertEquals('URL', $link->Type);
        $this->assertEquals($expected, $iframe->getURLAsString());
    }

    public function testBCFile() {
        $expected = '/' . ASSETS_DIR . '/IframeFileTest/file.jpg';
        $iframe = $this->objFromFixture( ElementIframe::class, 'bcfile');
        $link = $iframe->URL();
        $file = $link->File();
        $this->assertEquals('File', $link->Type);

        $field = $iframe->getCmsFields()->dataFieldByName('URLValue');
        $this->assertInstanceOf( UrlField::class, $field );
        $this->assertEquals( $expected, $field->dataValue() );

        $iframe->URLValue = $expected;
        $iframe->write();

        $this->assertEquals('URL', $link->Type);
        $this->assertEquals($expected, $iframe->getURLAsString());
    }
}
