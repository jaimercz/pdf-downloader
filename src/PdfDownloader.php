<?php

/**
 * PDF Downloader
 *
 * PDF Downloader Class is a simple class to download PDF
 * from the web using wget.
 *
 * @package     pdfdownloader
 * @author      Jaime C. Rubin de Celis <james@sglms.com>
 * @access      protected
 * @copyright   All rights reserved
 *
 */

namespace JamesRCZ;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use GuzzleHttp\Client;
use Mpdf\Mpdf;

class PdfDownloader
{
    private string $url;
    private string $path;
    private ?string $remoteContent;
    private ?array  $remoteHeaders;

    /**
     * Replace existing file.
     *
     * @var bool
     */
    private bool    $replace = false;

    /**
     * Name of file to be saved.
     *
     * @var string
     */
    public string $pdfFilename;
    public string $pdfHeader;

    public array  $streamContext = [
        'ssl'=>[
            "verify_peer"      => false,
            "verify_peer_name" => false
        ]
    ];

    public function __construct(
        $url,
        ?string $path = null,
        ?string $name = "pdffile.pdf",
        bool $replace = false
    ) {
        $this->url      = $url;
        $this->path     = $path ?? '/tmp/';
        $this->pdfFilename = $name;
        $this->replace  = $replace;
    }

    public function __toString()
    {
        return $this->path . $this->pdfFilename;
    }

    protected function getPdfFile(): void
    {
        $this->remoteContent = file_get_contents($this->url);
    }

    protected function savePdfFile(): bool
    {
        if ($this->verifyFileExists()) {
            if ($this->replace) {
                file_put_contents(
                    $this->path . $this->pdfFilename,
                    $this->remoteContent,
                    false,
                    stream_context_create($this->streamContext)
                );
                return true;
            } else {
                return false;
            }
        } else {
            return file_put_contents(
                $this->path . $this->pdfFilename,
                $this->remoteContent,
                false,
                stream_context_create($this->streamContext)
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
        $this->remoteHeaders = $this->url ?
            @get_headers(
                $this->url,
                false,
                stream_context_create($this->streamContext)
            ) : [];
        if (
            !$this->remoteHeaders ||
            false === array_search('Content-Type: application/pdf', $this->remoteHeaders)
        ) {
            return false;
        } else {
            return true;
        }
    }

    public function printPdf(): bool
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

    public function printPdf2()
    {
        $onRedirect = function (
            RequestInterface $request,
            ResponseInterface $response,
            UriInterface $uri
        ) {
            echo 'Redirecting! ' . $request->getUri() . ' to ' . $uri . "\n";
        };
        $client = new Client([
            'base_uri' => $this->url,
            'timeout'  => 2.0
        ]);
        $response = $client->request('GET', $this->url, [
            'allow_redirects' => [
                'max'             => 10,        // allow at most 10 redirects.
                'strict'          => true,      // use "strict" RFC compliant redirects.
                'referer'         => true,      // add a Referer header
                'protocols'       => ['https'], // only allow https URLs
                'on_redirect'     => $onRedirect,
                'track_redirects' => true
            ]
        ]);
        echo $response->getStatusCode();
        echo $response->getHeader('content-type')[0];
        // Get all of the response headers.
        foreach ($response->getHeaders() as $name => $values) {
            echo p($name . ': ' . implode(', ', $values) . "\r\n");
        }
        $htmlBody = $response->getBody();
        if ($htmlBody) {
            $doc        = new \DOMDocument();
            $docBody    = new \DOMDocument();
            libxml_use_internal_errors(true);
            $doc->loadHTML($htmlBody);
            libxml_clear_errors();
            $body = $doc->getElementsByTagName('body')->item(0);
            foreach ($body->childNodes as $child) {
                $docBody->appendChild($docBody->importNode($child, true));
            }
            $filteredHtmlContent = $docBody->saveHTML();

            $pdf = new Mpdf();
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
    }


    /**
     * Get the PDF (or print one from page)
     *
     * @return bool
     */
    public function download(): bool
    {
        if ($this->verifyRemoteMimeType()) {
            $this->getPdfFile();
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

    public function downloadOrPrint(): bool
    {
        if (!$this->download()) {
            if ($this->printPdf()) {
                return true;
            }
            return false;
        }
        return false;
    }

    public function getHeaders(): array
    {
        return $this->remoteHeaders;
    }
}
