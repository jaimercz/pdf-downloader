<?php

require_once '../vendor/autoload.php'; // Autoload files using Composer autoload

use JamesRCZ\PdfDownloader;

$url = "https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf";

$url = "http://www.sernapesca.cl/sites/default/files/res.ex_.3120-2021.pdf";
$pdfDownloader = new PdfDownloader($url, "/tmp/", "pdf-downloader.pdf", true);
echo $pdfDownloader->download() ? _("Pdf downloaded") : _("Error");

$url = "https://www.w3.org/WAI/ER/";
$pdfDownloader = new PdfDownloader($url, "/tmp/", "pdf-downloader-pdf-generator.pdf", true);
echo $pdfDownloader->printPdf() ? _("Pdf generated") : _("Error");
