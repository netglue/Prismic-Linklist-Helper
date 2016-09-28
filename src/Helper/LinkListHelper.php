<?php
/**
 * This file is part of the Prismic Link List Helper Package
 * Copyright 2016 Net Glue Ltd (https://netglue.uk).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace NetgluePrismic\Helper;

use Prismic;
use Prismic\Fragment\Link\LinkInterface;
use Prismic\Fragment\FragmentInterface;
use Prismic\Fragment\Link\DocumentLink;
use Prismic\Fragment\GroupDoc;
use Prismic\Fragment\Group;

/**
 * The sole purpose of this helper is to extract a group of links in a
 * document and return a nested array with which your templating library
 * can render a menu or navigation list
 */
class LinkListHelper
{

    /**
     * Prismic Api Instance
     *
     * @var Prismic\Api
     */
    private $api;

    /**
     * Link Resolver
     *
     * @var Prismic\LinkResolver
     */
    private $resolver;

    /**
     * The document type that represents a link list
     *
     * @var string
     */
    private $type = 'link-list';

    /**
     * The fragment name containing the group of link elements
     *
     * @var string
     */
    private $fragment = 'links';

    /**
     * For keeping track of processed documents so we don't end up in an infinite loop
     *
     * @var array
     */
    private $processed = [];

    /**
     * @param Prismic\Api          $api
     * @param Prismic\LinkResolver $resolver
     */
    public function __construct(Prismic\Api $api, Prismic\LinkResolver $resolver)
    {
        $this->api      = $api;
        $this->resolver = $resolver;
    }

    /**
     * Set the document type for link lists
     *
     * @param string $type
     * @return void
     */
    public function setDocumentType(string $type)
    {
        $this->type = $type;
    }

    /**
     * Return current document type
     *
     * @return string
     */
    public function getDocumentType() : string
    {
        return $this->type;
    }

    /**
     * Set fragment name used to retrieve the links group
     *
     * @param string $name
     * @return void
     */
    public function setFragmentName(string $name)
    {
        $this->fragment = $name;
    }

    /**
     * Return fragment name used for retrieving the links group
     *
     * @return string
     */
    public function getFragmentName() : string
    {
        return $this->fragment;
    }

    /**
     * Given a bookmark name, return a link array for the corresponding document
     *
     * @param string $bookmark
     * @return array
     */
    public function bookmarkToArray(string $bookmark) : array
    {
        $id = $this->api->bookmark($bookmark);
        if (null === $id) {
            throw new \RuntimeException(sprintf(
                'There is no document with the bookmark %s',
                $bookmark
            ));
        }

        return $this->documentIdToArray($id);
    }

    /**
     * Given a document id, return a link array for the corresponding document
     *
     * @param string $id
     * @return array
     */
    public function documentIdToArray(string $id) : array
    {
        $document = $this->api->getByID($id);
        if (null === $document) {
            throw new \RuntimeException(sprintf(
                'There is no document with the id %s',
                $id
            ));
        }

        return $this->documentToArray($document);
    }

    /**
     * Given a document, find the group containing the links and return as an array
     *
     * @param  Prismic\Document $linkList
     * @return array
     */
    public function documentToArray(Prismic\Document $linkList) : array
    {
        $this->processed = [];

        return $this->parseDocument($linkList);
    }

    /**
     * Private helper method used in order to track processed documents to avoid infinite recursion
     *
     * @param  Prismic\Document $linkList
     * @return array
     */
    private function parseDocument(Prismic\Document $linkList) : array
    {
        $this->processed[] = $linkList->getId();
        $group = $this->getGroup($linkList);
        $links = [];
        foreach ($group->getArray() as $groupDoc) {
            $this->filterGroup($groupDoc, $link, $anchor, $attributes);
            if ($link) {
                $links[] = $this->generateLink($link, $anchor, $attributes);
            }
        }

        return $links;
    }

    /**
     * Return an array representing a single link recursing to child links if appropriate
     *
     * This method returns an array in the following format
     * [
     *   'text' => 'String, Anchor Text',
     *   'attributes' => array( pairs of html link attributes keyed by attribute name )
     *   'children' => array( nested child links in the same format )
     * ]
     *
     * @param  LinkInterface     $link
     * @param  FragmentInterface $anchor
     * @param  array             $attributes An array of Fragments
     * @return array
     */
    private function generateLink(LinkInterface $link, FragmentInterface $anchor, array $attributes) : array
    {
        /**
         * Anchor Text
         */
        $text = $anchor ? $anchor->asText() : '';

        /**
         * Conditionally load child links for nested menus
         */
        $children = [];
        if ($link instanceof DocumentLink && $link->getType() === $this->type) {
            $href = null;
            if (in_array($link->getId(), $this->processed)) {
                throw new \RuntimeException(sprintf(
                    'Infinite recursion detected for the link list with ID %s',
                    $link->getId()
                ));
            }
            $document = $this->api->getByID($link->getId());
            $children = $this->parseDocument($document);
        } else {
            $href = $this->resolver->resolve($link);
        }

        $atrs = [
            'href' => $href,
        ];
        foreach ($attributes as $name => $atr) {
            $atrs[$name] = $atr->asText();
        }

        return [
            'text' => $text,
            'attributes' => $atrs,
            'children' => $children,
        ];
    }

    /**
     * Populate link, anchor and attributes from a group doc
     *
     * @param  GroupDoc $groupDoc
     * @param  mixed    &$link This will be a LinkInterface instance or null
     * @param  mixed    &$anchor This will be a Fragment for the text anchor or null
     * @param  array    &$attributes Any left over fragments will be considered as attributes and added to this array
     * @return void
     */
    private function filterGroup(GroupDoc $groupDoc, &$link, &$anchor, &$attributes)
    {
        $link = $anchor = $attributes = null;
        $fragments = $groupDoc->getFragments();
        foreach ($fragments as $key => $frag) {
            if ($frag instanceof LinkInterface) {
                $link = $frag;
                unset($fragments[$key]);
            }
        }
        if (isset($fragments['text'])) {
            $anchor = $fragments['text'];
            unset($fragments['text']);
        }

        $attributes = count($fragments) ? $fragments : [];
    }

    /**
     * Locate and return the group fragment that contains the link information
     * @param  Prismic\Document $linkList
     * @return GroupDoc
     */
    private function getGroup(Prismic\Document $linkList) : Group
    {
        $name  = sprintf('%s.%s', $linkList->getType(), $this->fragment);
        $group = $linkList->get($name);
        if (!$group instanceof Group) {
            throw new \RuntimeException(sprintf(
                  'The given document with id %s and type %s does not contain '
                . 'a fragment with the name %s, or, the fragment is not a group',
                $linkList->getId(),
                $linkList->getType(),
                $this->fragment));
        }
        return $group;
    }

}
