<?php

namespace Echron\Scss\Preprocessor\Adapter\Scss;

use Leafo\ScssPhp\Compiler;
use Leafo\ScssPhp\Formatter;
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
    const FORMATTER_EXPANDED = Formatter\Expanded::class;
    const FORMATTER_NESTED = Formatter\Nested::class;
    const FORMATTER_COMPRESSED = Formatter\Compressed::class;
    const FORMATTER_COMPACT = Formatter\Compact::class;
    const FORMATTER_CRUNCHED = Formatter\Crunched::class;

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

    /**
     * @var State
     */
    private $appState;

    /**
     * Constructor
     *
     * @param Source $assetSource
     * @param LoggerInterface $logger
     * @param State $appState
     */
    public function __construct(Source $assetSource, LoggerInterface $logger, State $appState)
    {
        $this->assetSource = $assetSource;
        $this->logger = $logger;
        $this->appState = $appState;
    }

    /**
     * @inheritdoc
     * @throws ContentProcessorException
     */
    public function processContent(File $asset)
    {
        $path = $asset->getPath();
        try {

            $content = $this->assetSource->getContent($asset);

            if (trim($content) === '') {
                return '';
            }

            $source = $this->assetSource->findSource($asset);

            $compiler = $this->getSCSSCompiler();

            $sourceDirectories = $this->getSourceDirectories($source);

            $compiler->setImportPaths([]);
            $compiler->addImportPath(function ($path) use ($sourceDirectories) {
                return $this->findImportPath($path, $sourceDirectories);
            });

            $result = $compiler->compile($content);
            if ($this->appState->getMode() === State::MODE_DEVELOPER) {
                $result = '/* Generated ' . date("Y-m-d H:i:s") . ' */' . PHP_EOL . $result;
                //TODO: add compile duration
            }

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

        $formatting = null;
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

    /**
     * @param $source
     * @return array
     */
    private function getSourceDirectories($source)
    {
        $styleFolder = realpath(dirname($source));
        //If source ends with "web/css" then add styles folder
        $styleFolder2 = str_replace('web/css', 'styles', $styleFolder);

        $folders = [
            $styleFolder,
            $styleFolder2,
            BP,
        ];

        return $folders;
    }

    private function findImportPath($path, $sourceDirectories)
    {
        $fileInfo = pathinfo($path);
        $alternativePath = $fileInfo['dirname'] . DIRECTORY_SEPARATOR . '_' . $fileInfo['basename'];

        $files = [
            $path . '.scss',
            $alternativePath . '.scss',
        ];
        foreach ($files as $file) {
            foreach ($sourceDirectories as $sourceDirectory) {
                $filePath = $sourceDirectory . DIRECTORY_SEPARATOR . $file;

                $fullPath = realpath($filePath);
                $exists = file_exists($fullPath);
                if ($exists) {
                    return $fullPath;
                }
            }
        }

        return null;
    }
}
