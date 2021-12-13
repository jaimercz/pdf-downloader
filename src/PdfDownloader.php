<?php
declare(strict_types = 1);

/**
 * PDF Downloader
 *
 * PDF Downloader Class is a simple class to download PDF
 * from the web. If url is not a pdf file, then it creates
 * one frow web using Mpdf.
 *
 * @package     PDFDownloader
 * @author      James RCZ <james@sglms.com>
 * @copyright   MIT
 *
 */

namespace JamesRCZ;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use GuzzleHttp\Client;
use Mpdf\Mpdf;

/**
 * PDF Downloader (only) class.
 *
 */
class PdfDownloader
{
    /**
     * Url where contents to be downloaded are located.
     * 
     * @var string
     */
    private string $url;

    /**
     * Where pdf will be saved.
     *
     * @var string
     */
    private string $path;

    /**
     * Store remote content
     */
    private string $remoteContent;

    /**
     * Array to store remote headers.
     *
     * @var array<string>
     */
    private array  $remoteHeaders;

    /**
     * Replace existing file.
     *
     * @var bool
     */
    private bool   $replace = false;

    /**
     * Name to be used for the downloaded / created pdf file.
     *
     * @var string
     */
    public string $pdfFilename;

    /**
     * Letter head to be included in all generated pdfs.
     *
     * @var string
     */
    public string $pdfHeader = "Generated by jaimercz/pdf-downloader!";

    public array  $streamContext = [
        'ssl'=>[
            "verify_peer"      => false,
            "verify_peer_name" => false
        ]
    ];

    /**
     * PDF Downloader constructor
     *
     * @param string $url
     * @param string $path
     * @param string $name [Default: pdffile.php]
     * @param bool $replace [Default: false]
     *
     * @return void
     */
    public function __construct(
        string $url,
        ?string $path = null,
        string $name = "pdffile.pdf",
        bool $replace = false
    ) {
        $this->url      = $url;
        $this->path     = $path ?? '/tmp/';
        $this->pdfFilename = $name;
        $this->replace  = $replace;
    }

    public function __toString()
    {
        return $this->url 
            . " -> "
            . $this->path . $this->pdfFilename;
    }

    protected function getHttpResponse() : \GuzzleHttp\Psr7\Response
    {
        $onRedirect = function (
            RequestInterface $request,
            ResponseInterface $response,
            UriInterface $uri
        ) {
            echo 'Redirecting!!! ' . $request->getUri() . ' to ' . $uri . "\n";
        };
        $client = new Client([
            'base_uri' => $this->url,
            'timeout'  => 5.0
        ]);
        $response = $client->request('GET', $this->url, [
            'allow_redirects' => [
                'max'             => 10,        // allow at most 10 redirects.
                'strict'          => true,      // use "strict" RFC compliant redirects.
                'referer'         => true,      // add a Referer header
                'protocols'       => ['http', 'https'], // only allow https URLs
                'on_redirect'     => $onRedirect,
                'track_redirects' => true
            ],
            'sink' => "/tmp/guzzle"
        ]);
        return $response;
    }

    protected function getRemoteContent(): void
    {
        $this->remoteContent = file_get_contents(
            $this->url
        );
    }

    protected function savePdfFile(): bool
    {
        if ($this->verifyFileExists()) {
            if ($this->replace) {
                file_put_contents(
                    $this->path . $this->pdfFilename,
                    $this->remoteContent,
                    /* false, */
                    /* stream_context_create($this->streamContext) */
                );
                return true;
            } else {
                return false;
            }
        } else {
            return file_put_contents(
                $this->path . $this->pdfFilename,
                $this->remoteContent,
                /* false, */
                /* stream_context_create($this->streamContext) */
            );
        }
        return false;
    }

    /**
     * Verify if file already exists in filesystem.
     *
     * @return bool
     */
    protected function verifyFileExists(): bool
    {
        return file_exists($this->path . $this->pdfFilename);
    }

    /**
     * Verify file mimetype is application/pdf.
     *
     * @return bool
     */
    protected function verifyMimeType(): bool
    {
        if ($this->verifyFileExists()) {
            $mimeType = mime_content_type($this->path . $this->pdfFilename);
            if ($mimeType !== "application/pdf") {
                throw new \ErrorException(_("File is not a PDF file (but it claims to be one)!"), 1000, 1);
            } else {
                return true;
            }
        }
        return false;
    }

    protected function verifyRemoteMimeType(): bool
    {
        $response               = $this->getHttpResponse();
        $contentType            = $response->getHeader('content-type');
        $this->remoteHeaders    = $response->getHeaders();
        if(preg_match("/application\/pdf/i", $contentType [0])) {
            return true;
        }
        return false;
    }

    /**
     * Download pdf file (if remote url is a pdf).
     *
     * @return bool
     */
    public function download(): bool
    {
        if ($this->verifyRemoteMimeType()) {
            $this->getRemoteContent();
            $saved = $this->savePdfFile();
            if ($this->verifyMimeType()) {
                return $saved;
            } else {
                unlink($this->path . $this->pdfFilename);
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Download or create pdf from source if url is a web page
     *
     * @return bool
     */
    public function downloadOrPrint(): bool
    {
        return !$this->download() ? $this->printPdf() : false;
    }

    public function getHeaders(): array
    {
        return $this->remoteHeaders;
    }

    /**
     * Print web page to pdf using Mpdf
     *
     * @return bool
     */
    public function printPdf() : bool
    {
        $response = $this->getHttpResponse();

        // Get all of the response headers.
        /* echo $response->getStatusCode(); */
        /* echo $response->getHeader('content-type')[0]; */
        /* foreach ($response->getHeaders() as $name => $values) { */
        /*     echo p($name . ': ' . implode(', ', $values) . "\r\n"); */
        /* } */

        $htmlBody = $response->getBody();
        
        if ($htmlBody) {
            $doc        = new \DOMDocument();
            $docBody    = new \DOMDocument();
            libxml_use_internal_errors(true);
            $doc->loadHTML((string) $htmlBody);
            libxml_clear_errors();
            $body = $doc->getElementsByTagName('body')->item(0);
            foreach ($body->childNodes as $child) {
                $docBody->appendChild($docBody->importNode($child, true));
            }
            $filteredHtmlContent = $docBody->saveHTML();

            $pdf = new Mpdf(['tempDir' => "/tmp/"]);
            @$pdf->WriteHTML(
                "<html>" .
                ($this->pdfHeader ? '<div style="border:thick solid slategray; margin: 3em 0;padding: 1em;">'.$this->pdfHeader.'</div>' : null) .
                $filteredHtmlContent .
                "</html>"
            );
            @$pdf->Output(
                $this->path . $this->pdfFilename,
                \Mpdf\Output\Destination::FILE
            );
            return true;
        }
        return false;
    }

    public function printPdfUsingWget(): bool
    {
        $htmlContent = file_get_contents(
            $this->url,
            false,
            stream_context_create($this->streamContext)
        );
        if ($htmlContent) {
            $doc        = new \DOMDocument();
            $docBody    = new \DOMDocument();
            libxml_use_internal_errors(true);
            $doc->loadHTML($htmlContent);
            libxml_clear_errors();
            $body = $doc->getElementsByTagName('body')->item(0);
            foreach ($body->childNodes as $child) {
                $docBody->appendChild($docBody->importNode($child, true));
            }
            $filteredHtmlContent = $docBody->saveHTML();

            $pdf = new Mpdf();
            @$pdf->WriteHTML(
                "<html><body>" .
                ($this->pdfHeader ? '<div style="border:thick solid slategray; margin: 3em 0;padding: 1em;">'.$this->pdfHeader.'</div>' : null) .
                $filteredHtmlContent .
                "</body></html>"
            );
            @$pdf->Output(
                $this->path . $this->pdfFilename,
                \Mpdf\Output\Destination::FILE
            );
            return true;
        }
        return false;
    }
}
