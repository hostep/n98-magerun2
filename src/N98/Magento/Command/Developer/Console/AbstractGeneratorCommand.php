<?php

namespace N98\Magento\Command\Developer\Console;

use Magento\Framework\Code\Generator\ClassGenerator;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Module\Dir as ModuleDir;
use Magento\Framework\Filesystem\Directory\WriteFactory as DirectoryWriteFactory;
use Magento\Framework\Filesystem\Directory\ReadFactory as DirectoryReadFactory;
use Symfony\Component\Console\Output\OutputInterface;
use Zend\Code\Generator\FileGenerator;
use Zend\Filter\Word\SeparatorToSeparator;

abstract class AbstractGeneratorCommand extends AbstractConsoleCommand
{
    /**
     * @var WriteInterface
     */
    protected $currentModuleDirWriter;

    /**
     * @param string $type
     * @return string
     */
    public function getCurrentModulePath($type = '')
    {
        return $this->get(ModuleDir::class)->getDir($this->getCurrentModuleName(), $type);
    }

    /**
     * @param string $path
     * @return string
     */
    public function getCurrentModuleFilePath($path)
    {
        return $this->getCurrentModulePath() . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }

    /**
     * @return string
     */
    public function getCurrentModuleNamespace()
    {
        $moduleName = $this->getCurrentModuleName();
        list($vendorPrefix, $moduleNamespace) = explode('_', $moduleName);

        return $vendorPrefix . '\\' . $moduleNamespace;
    }

    /**
     * @return string
     */
    public function getCurrentModuleName()
    {
        try {
            $currentModuleName = $this->getScopeVariable('_current_module');
        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException('Module not defined. Please use "module <name>" command');
        }

        return $currentModuleName;
    }

    /**
     * @param string $name
     */
    public function setCurrentModuleName($name)
    {
        $this->setScopeVariable('_current_module', $name);
    }

    /**
     * @param WriteInterface $currentModuleDirWriter
     */
    public function setCurrentModuleDirectoryWriter(WriteInterface $currentModuleDirWriter)
    {
        $this->currentModuleDirWriter = $currentModuleDirWriter;
    }

    /**
     * @return WriteInterface
     */
    public function getCurrentModuleDirectoryWriter()
    {
        if (!$this->currentModuleDirWriter) {
            $directoryWrite = $this->get(DirectoryWriteFactory::class);
            /** @var $directoryWrite DirectoryWriteFactory */

            $this->currentModuleDirWriter = $directoryWrite->create($this->getCurrentModulePath());
        }

        return $this->currentModuleDirWriter;
    }

    /**
     * @return \Magento\Framework\Filesystem\Directory\Read
     */
    public function getCurrentModuleDirectoryReader()
    {
        $directoryRead = $this->get(DirectoryWriteFactory::class);
        /** @var $directoryRead DirectoryReadFactory */
        return $directoryRead->create($this->getCurrentModulePath());
    }

    /**
     * @param string $pathArgument
     * @return string
     */
    public function getNormalizedPathByArgument($pathArgument)
    {
        $namespaceFilterDot = $this->create(
            SeparatorToSeparator::class,
            ['searchSeparator' => '.', 'replacementSeparator' => DIRECTORY_SEPARATOR]
        );
        $namespaceFilterBackslash = $this->create(
            SeparatorToSeparator::class,
            ['searchSeparator' => '\\', 'replacementSeparator' => DIRECTORY_SEPARATOR]
        );
        $pathArgument = $namespaceFilterDot->filter($pathArgument);
        $pathArgument = $namespaceFilterBackslash->filter($pathArgument);

        $parts = explode(DIRECTORY_SEPARATOR, $pathArgument);

        return implode(DIRECTORY_SEPARATOR, array_map('ucfirst', $parts));
    }

    /**
     * @param string $pathArgument
     * @return string
     */
    public function getNormalizedClassnameByArgument($pathArgument)
    {
        $namespaceFilterDot = $this->create(
            SeparatorToSeparator::class,
            ['searchSeparator' => '.', 'replacementSeparator' => '\\']
        );
        $namespaceFilterBackslash = $this->create(
            SeparatorToSeparator::class,
            ['searchSeparator' => '.', 'replacementSeparator' => '\\']
        );

        $pathArgument = $namespaceFilterDot->filter($pathArgument);
        $pathArgument = $namespaceFilterBackslash->filter($pathArgument);

        $parts = explode('\\', $pathArgument);

        return implode('\\', array_map('ucfirst', $parts));
    }

    /**
     * @param OutputInterface $output
     * @param ClassGenerator $classGenerator
     * @param string $filePathToGenerate
     */
    protected function writeClassToFile(
        OutputInterface $output,
        ClassGenerator $classGenerator,
        $filePathToGenerate
    ) {
        $fileGenerator = FileGenerator::fromArray(
            [
                'classes' => [$classGenerator]
            ]
        );

        $this->getCurrentModuleDirectoryWriter()
            ->writeFile($filePathToGenerate, $fileGenerator->generate());

        $output->writeln('<info>generated </info><comment>' . $filePathToGenerate . '</comment>');

    }

}