# pdf-downloader

Simple class to download pdf files; or to create one from web content (html).

## Introduction
This is very simple pdf downloader. If url is not a pdf file, it will attempt to print a pdf from web page as an alternative.

```php
use JamesRCZ/PdfDownloader;
$downloader = new PdfDownloder("https://helloworld.org");
$downloader->downloadOrPrint();
```

Alternative individual methods are available:

```php
$downloader->download(); // Get PDF (if exists)
$downloader->printPdf(); // Generate PDF (using Mpdf)
```
### Requirements

pdf-downloader depends on the following packages:

- php : "^8.0"
- GuzzleHttp/Guzzle : "^7.0"
- Mpdf/Mpdf : "^8.0"

### Disclaimer

I use this for personal projects. I.e. code might not be the optimized (comments or contributions are welcomed).