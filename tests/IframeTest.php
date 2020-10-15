<?php

namespace  NSWDPC\Elemental\Tests\QuickGallery;

use gorriecoe\Link\Models\Link;
use gorriecoe\LinkField\LinkField;
use NSWDPC\Elemental\Models\Iframe\ElementIframe;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use Silverstripe\Assets\Dev\TestAssetStore;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Folder;
use SilverStripe\Assets\Image;
use SilverStripe\View\Requirements;

/**
 * Unit test to verify Iframe element handling
 * @author James
 */
class IframeTest extends SapphireTest
{

    protected $usesDatabase = true;

    public function setUp() {
        parent::setUp();
    }

    public function tearDown()
    {
        parent::tearDown();
    }

    public function testIframe() {

        Config::inst()->update(
            ElementIframe::class,
            'default_allow_attributes',
            [
                'fullscreen'
            ]
        );

        $width = 300;
        $height = 200;

        $record = [
            'Title' => 'IFRAME_TITLE',
            'ShowTitle' => 1,
            'IsLazy' => 1,
            'IsFullWidth' => 1,
            'IsResponsive' => '16x9',
            'Width' => $width,
            'Height' => $height,
            'AlternateContent' => 'ALT_CONTENT'
        ];

        $link_record = [
            'Title' => 'TEST_LINK_IFRAME',
            'Type' => 'URL',
            'URL' => 'https://example.com/?foo=bar&1=<small>'
        ];

        $link = Link::create($link_record);
        $link->write();
        $record['URLID'] = $link->ID;// store record

        $iframe = ElementIframe::create($record);
        $iframe->write();

        $this->assertTrue($iframe->exists(), "Element iframe does not exist");

        $iframe_width = $iframe->getIframeWidth();
        $this->assertEquals("100%", $iframe_width, "Responsive iframe should be 100% width");

        $iframe_height = $iframe->getIframeHeight();
        $this->assertEquals("200", $iframe_height, "Iframe should be {$height} height");

        $template = $iframe->forTemplate();

        $strings = [
            "is-16x9",
            "allow=\"fullscreen\"",
            "loading=\"lazy\"",
            "width=\"100%\"",
            "height=\"200\"",
            "<h2>IFRAME_TITLE</h2>",
            "title=\"ALT_CONTENT\"",
            "src=\"" . htmlspecialchars($link_record['URL']) . "\""
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

}
