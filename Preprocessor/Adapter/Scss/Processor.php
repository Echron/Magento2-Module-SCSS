<?php

namespace Echron\Scss\Preprocessor\Adapter\Scss;

use Leafo\ScssPhp\Compiler;
use Magento\Framework\View\Asset\ContentProcessorInterface;
use Magento\Framework\View\Asset\File;
use Magento\Framework\View\Asset\Source;
use Psr\Log\LoggerInterface;

/**
 * Class Processor
 */
class Processor implements ContentProcessorInterface
{
    const FORMATTER_EXPANDED = 'expanded';
    const FORMATTER_NESTED = 'nested';
    const FORMATTER_COMPRESSED = 'compressed';
    const FORMATTER_COMPACT = 'compact';
    const FORMATTER_CRUNCHED = 'crunched';
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var Source
     */
    private $assetSource;

    /**
     * Constructor
     *
     * @param Source $assetSource
     * @param LoggerInterface $logger
     */
    public function __construct(Source $assetSource, LoggerInterface $logger)
    {
        $this->assetSource = $assetSource;
        $this->logger = $logger;
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

            return $result;
        } catch (\Exception $e) {
            $errorMessage = PHP_EOL . self::ERROR_MESSAGE_PREFIX . PHP_EOL . $path . PHP_EOL . $e->getMessage();
            $this->logger->critical($errorMessage);

            return $errorMessage;
        }
    }

    private function getSCSSCompiler()
    {
        //TODO: make this configuratable or check magento mode
        $compiler = new Compiler();
        //Set line numbers
        $compiler->setLineNumberStyle(Compiler::LINE_COMMENTS);
        //Set formatting
        $compiler->setFormatter(self::FORMATTER_NESTED);

        return $compiler;
    }
}
