<?php

declare(strict_types=1);

namespace Shopsys\Releaser\ReleaseWorker\AfterRelease;

use PharIo\Version\Version;
use Shopsys\Releaser\FilesProvider\ComposerJsonFilesProvider;
use Shopsys\Releaser\ReleaseWorker\AbstractShopsysReleaseWorker;
use Shopsys\Releaser\Stage;
use Symplify\MonorepoBuilder\FileSystem\ComposerJsonProvider;
use Shopsys\Releaser\DependencyUpdater;
use Symplify\MonorepoBuilder\Package\PackageNamesProvider;

final class SetMutualDependenciesToDevMasterReleaseWorker extends AbstractShopsysReleaseWorker
{
    /**
     * @var \Shopsys\Releaser\FilesProvider\ComposerJsonFilesProvider
     */
    private $composerJsonFilesProvider;

    /**
     * @var \Shopsys\Releaser\DependencyUpdater
     */
    private $dependencyUpdater;

    /**
     * @var \Symplify\MonorepoBuilder\Package\PackageNamesProvider
     */
    private $packageNamesProvider;

    /**
     * @var string
     */
    private const DEV_MASTER = 'dev-master';

    /**
     * @param \Shopsys\Releaser\FilesProvider\ComposerJsonFilesProvider $composerJsonFilesProvider
     * @param \Shopsys\Releaser\DependencyUpdater $dependencyUpdater
     * @param \Symplify\MonorepoBuilder\Package\PackageNamesProvider $packageNamesProvider
     */
    public function __construct(ComposerJsonFilesProvider $composerJsonFilesProvider, DependencyUpdater $dependencyUpdater, PackageNamesProvider $packageNamesProvider)
    {
        $this->composerJsonFilesProvider = $composerJsonFilesProvider;
        $this->dependencyUpdater = $dependencyUpdater;
        $this->packageNamesProvider = $packageNamesProvider;
    }

    /**
     * @param \PharIo\Version\Version $version
     * @return string
     */
    public function getDescription(Version $version): string
    {
        return sprintf('Set mutual package dependencies to "%s" version', self::DEV_MASTER);
    }

    /**
     * Higher first
     * @return int
     */
    public function getPriority(): int
    {
        return 160;
    }

    /**
     * @param \PharIo\Version\Version $version
     */
    public function work(Version $version): void
    {
        $this->dependencyUpdater->updateFileInfosWithPackagesAndVersion(
            $this->composerJsonFilesProvider->provideExcludingMonorepoComposerJson(),
            $this->packageNamesProvider->provide(),
            self::DEV_MASTER
        );

        $this->commit('composer.json in all packages now use latest shopsys version');
    }

    /**
     * @return string
     */
    public function getStage(): string
    {
        return Stage::AFTER_RELEASE;
    }
}
