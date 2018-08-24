# XmlText field type for eZ Platform

[![Build Status](https://img.shields.io/travis/ezsystems/ezplatform-xmltext-fieldtype.svg?style=flat-square&branch=master)](https://travis-ci.org/ezsystems/ezplatform-xmltext-fieldtype)
[![Downloads](https://img.shields.io/packagist/dt/ezsystems/ezplatform-xmltext-fieldtype.svg?style=flat-square)](https://packagist.org/packages/ezsystems/ezplatform-xmltext-fieldtype)
[![Latest release](https://img.shields.io/github/release/ezsystems/ezplatform-xmltext-fieldtype.svg?style=flat-square)](https://github.com/ezsystems/ezplatform-xmltext-fieldtype/releases)
[![License](https://img.shields.io/github/license/ezsystems/ezplatform-xmltext-fieldtype.svg?style=flat-square)](LICENSE)

This is the XmlText field type for eZ Platform. It was extracted from the eZ Publish / Platform 5.x as it has been suceeded by docbook based [RichText](https://github.com/ezsystems/ezpublish-kernel/tree/master/eZ/Publish/Core/FieldType/RichText) field type.

_Note: This Field Type supports editing via Platform UI v1, however only as raw (simplified) xml. There has currently not been any attempts at getting Online Editor from legacy extension to work with within Platform UI, to do that among other things someone would need to port the custom html to xml handler from oe extension to this field type. So this Field Type is mainly meant for use for migrating to RichText, see below._


## Installation

NOTE: This package comes already bundled with [Legacy Bridge](https://github.com/ezsystems/LegacyBridge). However if you would rather like to 1. migrate your content directly to eZ Platform to take full advantage of it, or 2. otherwise don't want to use legacy but need this field type for some legacy content usage within pure eZ Platform setup, then run the following:

```
composer require --update-with-all-dependencies "ezsystems/ezplatform-xmltext-fieldtype"
```

And lastly enable the bundle by adding `new EzSystems\EzPlatformXmlTextFieldTypeBundle\EzSystemsEzPlatformXmlTextFieldTypeBundle(),` to `app/AppKernel.php` list of bundles.

----

_Once you have migrated your content you can remove the bundle from both `app/AppKernel.php` and `composer.json`._


## Migrating from XmlText to RichText

**Warning: As of 1.6 this is now fully supported, but regardless of that always make a backup before using the migration tools.**

This package provides tools to migration existing XmlText fields to RichText, the enriched text format eZ Platform uses.
The tool comes as a Symfony command, `ezxmltext:convert-to-richtext`.

It will do two things:

- convert `ezxmltext` field definitions to `ezrichtext` field definitions
- convert `ezxmltext` fields (content) to `ezrichtext`

We recommend that you do a test run first using something like:

```
php app/console ezxmltext:convert-to-richtext -v --concurrency=2 --dry-run
```

The `-v` flag will output logs to the console, making it easy to track the conversion work that is being done.
This is an example of a successful conversion log entry for one field:

```
[2016-02-03 15:25:52] app.INFO: Converted ezxmltext field #745 to richtext {"original":"<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<section xmlns:image=\"http://ez.no/namespaces/ezpublish3/image/\" xmlns:xhtml=\"http://ez.no/namespaces/ezpublish3/xhtml/\" xmlns:custom=\"http://ez.no/namespaces/ezpublish3/custom/\"/>\n","converted":"<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<section xmlns=\"http://docbook.org/ns/docbook\" xmlns:xlink=\"http://www.w3.org/1999/xlink\" xmlns:ezxhtml=\"http://ez.no/xmlns/ezpublish/docbook/xhtml\" xmlns:ezcustom=\"http://ez.no/xmlns/ezpublish/docbook/custom\" version=\"5.0-variant ezpublish-1.0\"/>\n"}
```

It contains, in a JSON structure, the `original` (ezxmltext) value, and the `converted` (ezrichtext) value that has been
written to the database.

Once you are ready to convert, drop `-v` and `--dry-run`.
