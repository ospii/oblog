Oblog - Markdown to HTML
========================

This is a simple Markdown + Twig => HTML + sitemap.xml script. It will read source markdown files from a directory, natural sort the files, pass them through Twig templates and write output into given directory.

The script will also generate `sitemap.xml` for easier crawling/submission to search engines.

Usage
-----

See the example site in `/example` directory. Sample has two public posts and one draft. To generate posts run the following

    php gen.php

**Warning! All files with `.html` extension in output directory will be deleted**

First line of the source markdown file has several **magical** properties:

* it will be used as part of the html filename eg. "Laihduta regexillä" would be named "laihduta-regexilla.html".
* if it contains `+DRAFT+`, the post won't be added to link list nor `sitemap.xml` and is given a slightly obfuscated filename which is only shown during HTML generation.

via Composer
------------

    {
        "require": {
            "ospii/oblog": "dev-master"
        }
    }

