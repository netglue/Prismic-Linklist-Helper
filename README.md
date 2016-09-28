# Prismic.io Link List Helper

I'm in the habit of creating lists of links to use throughout content managed websites built using [prismic](https://prismic.io). This means that the end user can manage primary site navigation easily and re-order links as they see fit.

Link lists have to contain a group type fragment where each fragment within the group is used to create the link itself. One drawback to Prismic is the inability to nest group fragments, so this helper identifies links to other link lists and uses these to generate sub menus in the same way as a flat menu.

For an example 'link-list' document type, [see the JSON file included](https://github.com/netglue/Prismic-Linklist-Helper/blob/master/data/link-list.json).

You'll see that amongst other stuff, there is a group fragment named 'links', this what the helper iterates over to generate lists.

This package is purposefully minimal to avoid assumptions about the DI container in use and to be templating system agnostic. All you get is an array so it's up to you to render that however you see fit.

## Installation

```bash
$ composer require netglue/prismic-linklist-helper
```

## Usage

The helper requires a `Prismic\Api` instance and an object that extends `Prismic\LinkResolver`. It's expected that you'll create some kind of factory to return a properly constructed helper using whatever DI container you choose. It might go something like this:

**MyHelperFactory.php**

```php
<?php
namespace My\Factory;
use Interop\Container\ContainerInterface;
use NetgluePrismic\Helper\LinkListHelper;
use Prismic;

class LinkListHelperFactory
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $api      = $container->get(Prismic\Api::class);
        $resolver = $container->get(Prismic\LinkResolver::class);

        return new LinkListHelper($api, $resolver);
    }

}
```

The `LinkResolver` is dependent on how you've setup your site, but I use this helper with various modules built around Zend Expressive such as [Expressive Prismic](https://github.com/netglue/Expressive-Prismic) which contains a LinkResolver implementation.

Once you have an instance, you can generate an array of 'ready to render' link information by locating the document you want and providing it to the `documentToArray()` method like this:

```php
$links = $helper->documentToArray($document);
```

Alternatively, you can provide either bookmark names or document IDs to the relevant method to achieve the same thing:

```php
$links = $helper->bookmarkToArray('bookmark-name');
// ... or ...
$links = $helper->documentIdToArray('Some-Document-Id');
```

Assuming your document type is `link-list` and the group fragment name containing the link info is `links`, that's all you need, otherwise, you can set the document type and fragment name, either in your factory or on an individual basis with:

```php
$helper->setDocumentType('my-document-type');
$helper->setFragmentName('some-fragment');
```

The document type is only really neccessary if you plan on using nested link list documents.

## Link Information Array Format

The generated array will look like this:

```php
[
    [
        'text' => 'String, anchor text or null',
        'attributes' => [
            'href' => 'URL resolved by given link resolver or null',
            // ... Other html attribute pairs depending on your group configuration
        ],
        'children' => [
            // ... An array of nested links in the same format if appropriate
        ]
    ],
    // ... more links ...
]
```


## Caveats

* Your group structure should contain only 1 fragment of type link - the idea being is that I can't see a need for a link to have more than 1 href.
* In order to render anchor text, it is assumed that the group will contain a fragment named `text`

## Tests

```bash
$ cd vendor/netglue/prismic-linklist-helper
$ composer install
$ phpunit
```

## About

[Netglue makes web based stuff in Devon, England](https://netglue.uk). We hope this is useful to you and we’d appreciate feedback either way :)
