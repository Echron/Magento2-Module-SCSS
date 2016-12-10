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
            $compiler = new Compiler();

            $folders = [
                $styleFolder,
                BP,
            ];
            $compiler->addImportPath(function ($path) use ($folders) {

                $fileInfo = pathinfo($path);
                $alternativePath = $fileInfo['dirname'] . '/_' . $fileInfo['basename'];

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
                            //echo $filePath . ' =>' . $fullPath . PHP_EOL;
                        } else {
                            // echo $filePath . ' =>' . $fullPath . PHP_EOL;
                        }
                    }
                }

//                foreach ($folders as $folder) {
//                    $fullPath = realpath($folder . '/' . $path . '.scss');
//                    $exists = file_exists($fullPath);
//                    if ($exists) {
//                        return $fullPath;
//                    }
//
//                }

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
}
