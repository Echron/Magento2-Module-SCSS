<?php

namespace Echron\Scss\Preprocessor\Adapter\Scss;

use Leafo\ScssPhp\Compiler;
use Magento\Framework\App\State;
use Magento\Framework\Phrase;
use Magento\Framework\View\Asset\ContentProcessorException;
use Magento\Framework\View\Asset\ContentProcessorInterface;
use Magento\Framework\View\Asset\File;
use Magento\Framework\View\Asset\Source;
use Psr\Log\LoggerInterface;

/**
 * Class Processor
 */
class Processor implements ContentProcessorInterface
{
    const FORMATTER_AUTO = 'auto';
    const FORMATTER_EXPANDED = \Leafo\ScssPhp\Formatter\Expanded::class;
    const FORMATTER_NESTED = \Leafo\ScssPhp\Formatter\Nested::class;
    const FORMATTER_COMPRESSED = \Leafo\ScssPhp\Formatter\Compressed::class;
    const FORMATTER_COMPACT = \Leafo\ScssPhp\Formatter\Compact::class;
    const FORMATTER_CRUNCHED = \Leafo\ScssPhp\Formatter\Crunched::class;

    const LINE_NUMBER_STYLE_AUTO = 'auto';
    const LINE_NUMBER_STYLE_ON = 'on';
    const LINE_NUMBER_STYLE_OFF = 'off';
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var Source
     */
    private $assetSource;

    private $appState;

    /**
     * Constructor
     *
     * @param Source $assetSource
     * @param LoggerInterface $logger
     */
    public function __construct(Source $assetSource, LoggerInterface $logger, \Magento\Framework\App\State $appState)
    {
        $this->assetSource = $assetSource;
        $this->logger = $logger;
        $this->appState = $appState;
    }

    /**
     * Process file content
     *
     * @param File $asset
     * @return string
     */
    public function processContent(File $asset)
    {
        $path = $asset->getPath();
        try {

            //  $compiler = new \scssc();
            $content = $this->assetSource->getContent($asset);

            if (trim($content) === '') {
                return '';
            }

            $source = $this->assetSource->findSource($asset);

//TODO: needed?
            $styleFolder = realpath(dirname($source));
            //If source ends with "web/css" then add styles folder
            $styleFolder2 = str_replace('web/css', 'styles', $styleFolder);
            $compiler = $this->getSCSSCompiler();

            $folders = [
                $styleFolder,
                $styleFolder2,
                BP,
            ];
            $compiler->setImportPaths([]);
            $compiler->addImportPath(function ($path) use ($folders) {

                $fileInfo = pathinfo($path);
                $alternativePath = $fileInfo['dirname'] . '/_' . $fileInfo['basename'];

                //TODO: remove sass
                $files = [
                    $path . '.scss',
                    $path . '.sass',
                    $alternativePath . '.scss',
                    $alternativePath . '.sass',
                ];
                foreach ($files as $file) {
                    foreach ($folders as $folder) {
                        $filePath = $folder . '/' . $file;

                        $fullPath = realpath($filePath);
                        $exists = file_exists($fullPath);
                        if ($exists) {
                            return $fullPath;
                        } else {
                        }
                    }
                }

                return null;

            });

            $result = $compiler->compile($content);
            $result = '/* Generated ' . date("Y-m-d H:i:s") . ' */' . PHP_EOL . $result;

            return $result;
        } catch (\Exception $e) {
            $errorMessage = PHP_EOL . self::ERROR_MESSAGE_PREFIX . PHP_EOL . $path . PHP_EOL . $e->getMessage();
            $this->logger->critical($errorMessage);

            throw new ContentProcessorException(new Phrase($errorMessage));
        }
    }

    private function getSCSSCompiler()
    {
        //TODO: make this configuratable or check magento mode
        $compiler = new Compiler();
        //Set line numbers
        $lineNumberStyle = $this->getLineNumberStyle();
        $compiler->setLineNumberStyle($lineNumberStyle);

        //Set formatting
        $formatter = $this->getFormatting();
        $compiler->setFormatter($formatter);

        return $compiler;
    }

    private function getLineNumberStyle()
    {

        $setting = $this->getLineNumberStyleConfig();
        $style = null;
        switch ($setting) {
            case self::LINE_NUMBER_STYLE_AUTO:
                if ($this->appState->getMode() === State::MODE_DEVELOPER) {
                    $style = Compiler::LINE_COMMENTS;
                }

                break;
            case self::LINE_NUMBER_STYLE_ON:
                $style = Compiler::LINE_COMMENTS;
                break;
            case self::LINE_NUMBER_STYLE_OFF:
                break;
        }

        return $style;
    }

    private function getLineNumberStyleConfig()
    {
        return self::LINE_NUMBER_STYLE_AUTO;
    }

    private function getFormatting()
    {
        $setting = $this->getFormattingConfig();

        $formatting = self::FORMATTER_NESTED;
        switch ($setting) {
            case self::FORMATTER_AUTO:
                if ($this->appState->getMode() === State::MODE_DEVELOPER) {
                    $formatting = self::FORMATTER_NESTED;
                } else {
                    $formatting = self::FORMATTER_COMPACT;
                }
                break;
            default:
                $formatting = $setting;
        }

        return $formatting;
    }

    private function getFormattingConfig()
    {
        return self::FORMATTER_AUTO;
    }
}
