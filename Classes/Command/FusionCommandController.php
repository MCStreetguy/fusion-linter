<?php
namespace MCStreetguy\FusionLinter\Command;

/*
 * This file is part of the MCStreetguy.FusionLinter package.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Package\PackageManagerInterface;
use Neos\Fusion\Core\Parser;
use Neos\Fusion\Core\Runtime;
use MCStreetguy\FusionLinter\Fusion\Utility\FusionFile;
use BlueM\Tree;
use Neos\Utility\Files;
use Neos\Flow\Exception;
use Neos\Fusion\Exception as FusionException;

/**
 * @Flow\Scope("singleton")
 */
class FusionCommandController extends AbstractCommandController
{
    /**
     * @Flow\InjectConfiguration
     * @var array
     */
    protected $settings;

    /**
     * @Flow\Inject
     * @var PackageManagerInterface
     */
    protected $packageManager;

    /**
     * @Flow\Inject
     * @var Parser
     */
    protected $fusionParser;

    /**
     * @var Runtime
     */
    protected $fusionRuntime;

    // Commands

    /**
     * Lint the existing Fusion code.
     *
     * Lint the existing Fusion code.
     *
     * @param string $packageKey The package to load the Fusion code from.
     * @param bool $verbose Produce additional output with additional information.
     * @return void
     */
    public function lintCommand(string $packageKey = null, bool $verbose = false)
    {
        $filesToLint = $this->loadFusionFiles($packageKey);
        $totalCount = count($filesToLint);
        $errors = 0;

        foreach ($filesToLint as $file) {
            try {
                $fileTree = $this->fusionParser->parse(
                    $file->getContents(),
                    $file->getFullPath()
                );
            } catch (Exception $e) {
                $containingPackageKey = $file->getPackageKey();
                $containingPackage = $this->packageManager->getPackage($containingPackageKey);
                $packageRootPath = $containingPackage->getPackagePath();
                $relativeFilePath = preg_replace('/resource:\/\/[a-z0-9]+\.(?:[a-z0-9][\.a-z0-9]*)+\//i', '', $file->getFullPath());

                $this->outputErrorMessage("Error in $containingPackageKey -> '$relativeFilePath': {$e->getMessage()}");

                $errors++;
                continue;
            }

            if ($verbose === true) {
                $this->outputSuccessMessage("File {$file->getRelativePath()} contains no errors.");
            }
        }

        $this->newline();

        if ($errors <= 0) {
            $this->outputSuccessMessage("Processed $totalCount files and found no syntax errors.");
        } else {
            $this->outputWarningMessage("Processed $totalCount files and encountered $errors errors!");
            $this->outputWarningMessage('There may be additional output containing more information above.');
        }
    }

    // Service methods

    /**
     * @return FusionFile[]
     */
    protected function loadFusionFiles(string $fromPackageKey = null)
    {
        $foundFusionFiles = [];

        if ((
            $fromPackageKey !== null &&
            $this->packageManager->isPackageKeyValid($fromPackageKey) &&
            $this->packageManager->isPackageAvailable($fromPackageKey)
        )) {
            $sourcePackages = [$this->packageManager->getPackage($fromPackageKey)];
        } else {
            $sourcePackages = $this->packageManager->getActivePackages();
        }

        /** @var PackageInterface $package */
        foreach ($sourcePackages as $package) {
            $packageKey = $package->getPackageKey();

            if ($this->packageManager->isPackageFrozen($packageKey)) {
                //* If the package is frozen it has no impact on fusion rendering and thus can safely be skipped
                continue;
            }

            $basePath = "resource://$packageKey/Private/Fusion";

            foreach ($this->settings['fusionFilePathPatterns'] as $basePathPattern) {
                $basePath = str_replace('@package', $packageKey, $basePathPattern);

                if (file_exists($basePath) && is_dir($basePath) && is_readable($basePath)) {
                    foreach (Files::readDirectoryRecursively($basePath, '.fusion') as $file) {
                        $foundFusionFiles[] = new FusionFile($packageKey, $file);
                    }
                }
            }
        }

        //? Caching?

        return $foundFusionFiles;
    }

    protected function transformExceptionMessage(\Exception $e)
    {
        if ($e->getCode() === 1180600696 || $e->getCode() === 1180600697 || $e->getCode() === 1180547190) {
            /** @see https://github.com/neos/neos-development-collection/blob/master/Neos.Fusion/Classes/Core/Parser.php#L322 */
            /** @see https://github.com/neos/neos-development-collection/blob/master/Neos.Fusion/Classes/Core/Parser.php#L325 */
            /** @see https://github.com/neos/neos-development-collection/blob/master/Neos.Fusion/Classes/Core/Parser.php#L577 */
            $this->outputErrorMessage('Invalid namespace declaration given!');
        } elseif ($e->getCode() === 1180547966) {
            /** @see https://github.com/neos/neos-development-collection/blob/master/Neos.Fusion/Classes/Core/Parser.php#L384 */

            preg_match('/Syntax error in line (\d+). \((.+)\)/i', $e->getMessage(), $matches);
            $this->outputErrorMessage('Syntax error on line ' . $matches[1] . ': ' . $matches[2] . '!');
        } elseif ($e->getCode() === 1180615119) {
            /** @see https://github.com/neos/neos-development-collection/blob/master/Neos.Fusion/Classes/Core/Parser.php#L405 */
            $this->outputErrorMessage('Closed a block comment that has never been opened!');
        } elseif ($e->getCode() === 1180614895) {
            /** @see https://github.com/neos/neos-development-collection/blob/master/Neos.Fusion/Classes/Core/Parser.php#L416 */
            $this->outputErrorMessage('An internal parser error occurred! (Could not parse comment line properly)');
        } elseif ($e->getCode() === 1181575973) {
            /** @see https://github.com/neos/neos-development-collection/blob/master/Neos.Fusion/Classes/Core/Parser.php#L435 */
            $this->outputErrorMessage('More closing curly braces than opening ones!');
        } elseif ($e->getCode() === 1180544656) {
            /** @see https://github.com/neos/neos-development-collection/blob/master/Neos.Fusion/Classes/Core/Parser.php#L452 */

            preg_match('/Invalid declaration "(.+)"/i', $e->getMessage(), $matches);
            $this->outputErrorMessage('Unknown declaration given: ' . $matches[1] . '!');
        } elseif (in_array($e->getCode(), [
            1180548488,
            1358418019,
            1358418015,
        ])) { // no special treatment
            $this->outputErrorMessage($e->getMessage());
        }
    }
}
