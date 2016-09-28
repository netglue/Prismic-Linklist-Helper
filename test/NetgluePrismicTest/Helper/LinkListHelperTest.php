<?php

namespace NetgluePrismicTest\Helper;

use Prismic;
use NetgluePrismic\Helper\LinkListHelper;
use NetgluePrismicTest\Asset\LinkResolver;

class LinkListHelperTest extends \PHPUnit_Framework_TestCase
{

    private $api;

    public function setUp()
    {
        $this->api = $this->createMock(Prismic\Api::class);
        $this->resolver = new LinkResolver;
    }

    private function getFlatDocument()
    {
        return Prismic\Document::parse(json_decode(
            file_get_contents(__DIR__ . '/../../data/link-list.json')
        ));
    }

    public function testSetGetDocumentType()
    {
        $helper = new LinkListHelper($this->api, $this->resolver);

        $this->assertInternalType('string', $helper->getDocumentType());
        $helper->setDocumentType('foo');
        $this->assertSame('foo', $helper->getDocumentType());
    }

    public function testSetGetFragmentName()
    {
        $helper = new LinkListHelper($this->api, $this->resolver);

        $this->assertInternalType('string', $helper->getFragmentName());
        $helper->setFragmentName('foo');
        $this->assertSame('foo', $helper->getFragmentName());
    }

    public function testFlatStructure()
    {
        $helper = new LinkListHelper($this->api, $this->resolver);
        $doc = $this->getFlatDocument();
        $links = $helper->documentToArray($doc);

        $this->assertInternalType('array', $links);
        $this->assertCount(6, $links);
        foreach($links as $link) {
            $this->assertArrayHasKey('text', $link);
            $this->assertArrayHasKey('attributes', $link);
            $this->assertArrayHasKey('children', $link);
            $this->assertCount(0, $link['children']);
        }

        /**
         * The first link in the fixture has extra attrs to test
         */
        $link = current($links);
        $this->assertCount(3, $link['attributes']);
        $this->assertArrayHasKey('href', $link['attributes']);
        $this->assertArrayHasKey('class', $link['attributes']);
        $this->assertArrayHasKey('data-value', $link['attributes']);
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage does not contain a fragment with the name
     */
    public function testInvalidFragmentNameThrowsException()
    {
        $helper = new LinkListHelper($this->api, $this->resolver);
        $helper->setFragmentName('unknown');
        $doc = $this->getFlatDocument();
        $helper->documentToArray($doc);
    }

    public function testNestedStructure()
    {
        $helper = new LinkListHelper($this->api, $this->resolver);

        $parent = Prismic\Document::parse(json_decode(
            file_get_contents(__DIR__ . '/../../data/nested-parent.json')
        ));
        $child = Prismic\Document::parse(json_decode(
            file_get_contents(__DIR__ . '/../../data/nested-child.json')
        ));

        $this->api->method('getById')->willReturn($child);

        $links = $helper->documentToArray($parent);

        $this->assertCount(2, $links);
        $child = end($links);
        $this->assertCount(1, $child['children']);
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage Infinite recursion detected for the link list
     */
    public function testInfiniteRecursionThrowsException()
    {
        $helper = new LinkListHelper($this->api, $this->resolver);

        $parent = Prismic\Document::parse(json_decode(
            file_get_contents(__DIR__ . '/../../data/nested-parent.json')
        ));
        $child = Prismic\Document::parse(json_decode(
            file_get_contents(__DIR__ . '/../../data/recursive-child.json')
        ));

        $this->api
             ->method('getById')
             ->will($this->returnCallback(function() use ($parent, $child) {
                $id = current(func_get_args());
                return ($id === 'DocumentID') ? $parent : $child;
            }));


        $links = $helper->documentToArray($parent);
    }

}
