<?php

/*
 * This file is part of PHP-XPDF.
 *
 * (c) Alchemy <info@alchemy.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XPDF;

use Alchemy\BinaryDriver\AbstractBinary;
use Alchemy\BinaryDriver\Exception\ExecutionFailureException;
use Alchemy\BinaryDriver\Exception\ExecutableNotFoundException;
use Alchemy\BinaryDriver\Configuration;
use Alchemy\BinaryDriver\ConfigurationInterface;
use Psr\Log\LoggerInterface;
use XPDF\Exception\InvalidArgumentException;
use XPDF\Exception\RuntimeException;
use XPDF\Exception\BinaryNotFoundException;

/**
 * The PdfToHTML object.
 *
 * This binary adapter is used to extract HTML from PDF with the PdfToHTML
 * binary provided by XPDF.
 *
 * @see https://wikipedia.org/wiki/Pdftotext
 * @license MIT
 */
class PdfToHTML extends AbstractBinary
{
    private $charset = 'UTF-8';
    private $pages;
    private $output_mode = '-raw';

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'pdftohtml';
    }

    /**
     * Sets the output encoding. If the charset is invalid, the getHTML method
     * will fail.
     *
     * @param  string    $charset The output charset
     * @return PdfToHTML
     */
    public function setOuputEncoding($charset)
    {
        $this->charset = $charset;

        return $this;
    }

    /**
     * Gets the ouput encoding, default is UTF-8
     *
     * @return string
     */
    public function getOuputEncoding()
    {
        return $this->charset;
    }

    /**
     * Sets a quantity of page to extract by default
     *
     * @param integer $pages
     *
     * @return PdfToHTML
     */
    public function setPageQuantity($pages)
    {
        if (0 >= $pages) {
            throw new InvalidArgumentException('Page quantity must be a positive value');
        }

        $this->pages = $pages;

        return $this;
    }

    /**
     * Set the output formatting mode
     *
     * @param string $mode
     * 
     * @return PdfToHTML
     *
     * Valid modes are "raw" and "layout"
     */
    public function setOutputMode($mode)
    {
        switch (strtolower($mode)) {
            case 'raw':
            case '-raw':
                $this->output_mode = '-raw';
                break;
            case 'layout':
            case '-layout':
                $this->output_mode = '-layout';
                break;
            default:
                throw new InvalidArgumentException('Mode must be "raw" or "layout"');
                break;
        }

        return $this;
    }

    /**
     * Extracts the html of the current open PDF file, if not page start/end
     * provided, etxract all pages.
     *
     * @param string  $pathfile   The path to the PDF file
     * @param integer $page_start The starting page number (first is 1)
     * @param integer $page_end   The ending page number
     *
     * @return string The extracted HTML
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function getHTML($pathfile, $page_start = null, $page_end = null)
    {
        if ( ! file_exists($pathfile)) {
            throw new InvalidArgumentException(sprintf('%s is not a valid file', $pathfile));
        }

        $commands = array();

        if (null !== $page_start) {
            $commands[] = '-f';
            $commands[] = (int) $page_start;
        }
        if (null !== $page_end) {
            $commands[] = '-l';
            $commands[] = (int) $page_end;
        } elseif (null !== $this->pages) {
            $commands[] = '-l';
            $commands[] = (int) $page_start + $this->pages;
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'xpdf');

        $commands[] = $this->output_mode;
        $commands[] = '-s';
        $commands[] = '-i';
        $commands[] = '-noframes';
        $commands[] = '-enc';
        $commands[] = $this->charset;
        $commands[] = $pathfile;
        $commands[] = $tmpFile;

        try {
            $this->command($commands);
            $ret = file_get_contents($tmpFile);

            if (is_writable($tmpFile)) {
                unlink($tmpFile);
            }
        } catch (ExecutionFailureException $e) {
            throw new RuntimeException('Unale to extract HTML', $e->getCode(), $e);
        }

        return $ret;
    }

    /**
     * Factory for PdfToHTML
     *
     * @param array|Configuration $configuration
     * @param LoggerInterface     $logger
     *
     * @return PdfToHTML
     */
    public static function create($configuration = array(), LoggerInterface $logger = null)
    {
        if (!$configuration instanceof ConfigurationInterface) {
            $configuration = new Configuration($configuration);
        }

        $binaries = $configuration->get('pdftohtml.binaries', 'pdftohtml');

        if (!$configuration->has('timeout')) {
            $configuration->set('timeout', 60);
        }

        try {
            return static::load($binaries, $logger, $configuration);
        } catch (ExecutableNotFoundException $e) {
            throw new BinaryNotFoundException('Unable to find pdftohtml', $e->getCode(), $e);
        }
    }
}