Over-Blog HTML-to-Markdown Converter
====================================

This tool is intended to provide a way to convert the exported data from Over-Blog to a more readable and processable format.

It includes a converter for [HTMLy](https://www.htmly.com/) but can be used as a base to import the data to any other platform.

## Objectives

- Export each post or page to a separate HTML file
- Convert each post or page to a separate Markdown file
- Normalize posts and pages URL
- Retrieve all images included in posts and pages

> This tool has been only developed for a one-shot personal need. It won't likely be fixed, improved or more generally maintained.  
> Feel free to fork it and adjust it to your needs!

## Requirements

- PHP 7+ with:
  - DOM
  - JSON
  - SimpleXML
- Composer

## Usage

Extract the XML file from OB archive to `export.xml` and place it in this folder.

Install the dependencies:

```shell
composer install
```

Then you may want to:

- Run the conversion:
  ```shell
  make run-convert
  ```
  It will create or replace a `export/` folder with `posts` and `pages`, containing all the converted content (HTML + Markdown).  
  Additionnally, it will also create a `export.json` and `export.clean.json` files with preprocessed data in them.

- Retrieve all images (you need to have the `export.clean.json` first):
  ```shell
  make run-images
  ```
  It will download any image found in the content of your posts or pages, and place it in a `export/images/` directory.
  Additionnally, it will also create a `export/images.json` file with preprocessed data in it.

- Convert the previous content to HTMLy:
  ```shell
  make run-tohtmly
  ```
  It will create and populate a `to-htmly/` folder with all the content converted for HTMLy.

## License

See [LICENSE](LICENSE).