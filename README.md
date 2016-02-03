# XmlText field type for eZ Platform

This is the XmlText FieldType for eZ Platform. It was extracted from the eZ Publish / Platform 5.x.

## Migrating from XmlText to RichText

**Warning: this part is a non-finalized work-in-progress. Always make a backup before using the migration tools.**

This package provides tools to migration existing XmlText fields to RichText, the enriched text format eZ Platform uses.
The tool comes as a Symfony command, `ezxmltext:convert-to-richtext`.

It will do two things:

- convert `ezxmltext` field definitions to `ezrichtext` field definitions
- convert `ezxmltext` fields (content) to `ezrichtext`

We recommend that you execute it this way:

```
php app/console ezxmltext:convert-to-richtext -v
```

The `-v` flag will output logs to the console, making it easy to track the conversion work that is being done.
This is an example of a successful conversion log entry for one field:

```
[2016-02-03 15:25:52] app.INFO: Converted ezxmltext field #745 to richtext {"original":"<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<section xmlns:image=\"http://ez.no/namespaces/ezpublish3/image/\" xmlns:xhtml=\"http://ez.no/namespaces/ezpublish3/xhtml/\" xmlns:custom=\"http://ez.no/namespaces/ezpublish3/custom/\"/>\n","converted":"<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<section xmlns=\"http://docbook.org/ns/docbook\" xmlns:xlink=\"http://www.w3.org/1999/xlink\" xmlns:ezxhtml=\"http://ez.no/xmlns/ezpublish/docbook/xhtml\" xmlns:ezcustom=\"http://ez.no/xmlns/ezpublish/docbook/custom\" version=\"5.0-variant ezpublish-1.0\"/>\n"}
```

It contains, in a JSON structure, the `original` (ezxmltext) value, and the `converted` (ezrichtext) value that has been
written to the database.
