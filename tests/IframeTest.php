<?php

namespace NSWDPC\Elemental\Tests\Iframe;

use NSWDPC\Elemental\Models\Iframe\ElementIframe;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
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
    #[\Override]
    public function setUp(): void
    {
        parent::setUp();
        Config::modify()->set(
            ElementIframe::class,
            'default_allow_attributes',
            [
                'fullscreen'
            ]
        );

        // default no polyfill for lazy loading
        Config::modify()->set(ElementIframe::class, 'load_polyfill', false);

    }

    protected function checkRequirementHashes(array $included = [], array $excluded = []): void
    {

        $backend = Requirements::backend();
        $js = $backend->getJavascript();
        $css = $backend->getCSS();
        $assets = array_merge($js, $css);
        $hashes = array_column($assets, 'integrity');

        foreach ($included as $includedReason => $includedIntegrityHash) {
            $this->assertContains($includedIntegrityHash, $hashes, "Expected integrity hash for '{$includedReason}' is not present in requirements");
        }

        foreach ($excluded as $excludedReason => $excludedIntegrityHash) {
            $this->assertNotContains($excludedIntegrityHash, $hashes, "Excluded integrity hash for '{$excludedReason}' is present in requirements");
        }
    }

    protected function getDomDocument(string $htmlTemplate): \DOMDocument
    {
        $doc = new \DOMDocument();
        $doc->loadHTML($htmlTemplate);
        return $doc;
    }

    protected function verifyIframeTemplate(string $htmlTemplate, string $title = "", array $expectedAttributes = [], array $unexpectedAttributes = [], array $expectedParentNodeClasses = []): void
    {

        $dom = $this->getDomDocument($htmlTemplate);

        if ($title !== "") {
            $h2 = $dom->getElementsByTagName('h2')[0];
            $this->assertEquals($title, trim((string) $h2->textContent));
        }

        $iframe = $dom->getElementsByTagName('iframe')[0];
        foreach ($expectedAttributes as $name => $value) {
            $this->assertEquals($value, trim((string) $iframe->getAttribute($name)));
        }

        foreach (array_keys($unexpectedAttributes) as $name) {
            $this->assertFalse($iframe->hasAttribute($name));
        }

        $parentNode = $iframe->parentNode;
        if ($parentNode->nodeName == 'noscript') {
            // skip noscript
            $parentNode = $parentNode->parentNode;
        }

        $parentClass = $parentNode->getAttribute('class');
        $parentClasses = explode(" ", (string) $parentClass);
        foreach ($expectedParentNodeClasses as $expectedParentNodeClass) {
            $this->assertContains($expectedParentNodeClass, $parentClasses);
        }
    }

    /**
     * Test standard iframe without lazy loading enabled
     */
    public function testIframeStandardNonLazy(): void
    {

        // no polyfill
        Config::modify()->set(ElementIframe::class, 'load_polyfill', false);

        $iframe = $this->objFromFixture(ElementIframe::class, 'standard');

        $this->assertEquals(0, $iframe->IsLazy);

        // save this URL value
        $url = 'https://example.com/?foo=bar&1=<small>';
        $iframe->URLValue = $url;
        $iframe->write();

        $this->assertEquals('16x9', $iframe->IsResponsive);

        $iframe_width = $iframe->getIframeWidth();
        $this->assertEquals("100%", $iframe_width, "Responsive iframe should be 100% width");

        $iframe_height = $iframe->getIframeHeight();
        $this->assertEquals($iframe->Height, $iframe_height, "Iframe should be {$iframe->Height} height");

        $this->verifyIframeTemplate(
            $iframe->forTemplate(),
            "IFRAME_TITLE",
            [
                "class" => "responsive-item",
                "allow" => "fullscreen",
                "width" => "100%",
                "height" => $iframe_height,
                "title" => "ALT_CONTENT",
                "src" => $url
            ],
            [
                "loading" => "lazy",
            ],
            [
                "responsive-iframe",
                "is-16x9",
            ]
        );

        // Requirements check
        $included = [
            "iframe css" => "BCvA93KSwNd2uyy/627Fmtp2cpR8qUvOA2b1zO52ashQ6RPM7BoEieDfManGxC2aq9XiL2jYmwWEcRZF+3Vovw=="
        ];
        $excluded = [
            "lazy loading polyfill script" => "sha512-Kq3/MTxphzXJIRDWtrpLhhNnLDPiBXPMKkx/KogMYZO92Geor9j8sJguZ1OozBS+YVmVKo2HEx2gZfGOQBFM8A=="
        ];
        $this->checkRequirementHashes($included, $excluded);

        $iframe->publishSingle();

        $this->assertTrue($iframe->isPublished(), "Iframe is not published");

    }

    /**
     * Test iframe element with IsLazy enabled and without polyfill (default)
     */
    public function testIframeLazyWithoutPolyfill(): void
    {

        // no polyfill
        Config::modify()->set(ElementIframe::class, 'load_polyfill', false);

        $iframe = $this->objFromFixture(ElementIframe::class, 'lazyload');

        $this->assertEquals(1, $iframe->IsLazy);

        // save this URL value
        $url = 'https://example.com/?lazy';
        $iframe->URLValue = $url;
        $iframe->write();

        $this->assertEquals('16x9', $iframe->IsResponsive);

        $iframe_width = $iframe->getIframeWidth();
        $this->assertEquals("100%", $iframe_width, "Responsive iframe should be 100% width");

        $iframe_height = $iframe->getIframeHeight();
        $this->assertEquals($iframe->Height, $iframe_height, "Iframe should be {$iframe->Height} height");

        $this->verifyIframeTemplate(
            $iframe->forTemplate(),
            "IFRAME_TITLE_LAZY",
            [
                "class" => "responsive-item",
                "allow" => "fullscreen",
                "loading" => "lazy",
                "width" => "100%",
                "height" => $iframe_height,
                "title" => "ALT_CONTENT_LAZY",
                "src" => $url
            ],
            [],
            [
                "responsive-iframe",
                "is-16x9",
            ]
        );

        // Requirements check
        $included = [
            "iframe css" => "BCvA93KSwNd2uyy/627Fmtp2cpR8qUvOA2b1zO52ashQ6RPM7BoEieDfManGxC2aq9XiL2jYmwWEcRZF+3Vovw==" // module's iframe.css
        ];
        $excluded = [
            "lazy loading polyfill script" => "sha512-Kq3/MTxphzXJIRDWtrpLhhNnLDPiBXPMKkx/KogMYZO92Geor9j8sJguZ1OozBS+YVmVKo2HEx2gZfGOQBFM8A==" // polyfill
        ];
        $this->checkRequirementHashes($included, $excluded);

        $iframe->publishSingle();

        $this->assertTrue($iframe->isPublished(), "Iframe is not published");

    }

    /**
     * Test iframe element with IsLazy enabled and polyfill enabled
     */
    public function testIframeLazyWithPolyfill(): void
    {

        // load polyfill in requirements
        Config::modify()->set(ElementIframe::class, 'load_polyfill', true);

        $iframe = $this->objFromFixture(ElementIframe::class, 'lazyload');

        $this->assertEquals(1, $iframe->IsLazy);

        // save this URL value
        $url = 'https://example.com/?lazy';
        $iframe->URLValue = $url;
        $iframe->write();

        $this->assertEquals('16x9', $iframe->IsResponsive);

        $iframe_width = $iframe->getIframeWidth();
        $this->assertEquals("100%", $iframe_width, "Responsive iframe should be 100% width");

        $iframe_height = $iframe->getIframeHeight();
        $this->assertEquals($iframe->Height, $iframe_height, "Iframe should be {$iframe->Height} height");

        $this->verifyIframeTemplate(
            $iframe->forTemplate(),
            "IFRAME_TITLE_LAZY",
            [
                "class" => "responsive-item",
                "allow" => "fullscreen",
                "loading" => "lazy",
                "width" => "100%",
                "height" => $iframe_height,
                "title" => "ALT_CONTENT_LAZY",
                "src" => $url
            ],
            [],
            [
                "responsive-iframe",
                "is-16x9",
            ]
        );

        // Requirements check
        $included = [
            "iframe css" => "BCvA93KSwNd2uyy/627Fmtp2cpR8qUvOA2b1zO52ashQ6RPM7BoEieDfManGxC2aq9XiL2jYmwWEcRZF+3Vovw==",
            "lazy loading polyfill script" => "sha512-Kq3/MTxphzXJIRDWtrpLhhNnLDPiBXPMKkx/KogMYZO92Geor9j8sJguZ1OozBS+YVmVKo2HEx2gZfGOQBFM8A==" // polyfill
        ];
        $excluded = [];
        $this->checkRequirementHashes($included, $excluded);

        $iframe->publishSingle();

        $this->assertTrue($iframe->isPublished(), "Iframe is not published");

    }

}
