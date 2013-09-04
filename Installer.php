<?php

namespace Claroline\CoreInstaller;

use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Composer;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Package\PackageInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Composer custom installer for Claroline core bundles.
 */
class Installer extends LibraryInstaller
{
    /**
     * @var \Symfony\Component\HttpKernel\KernelInterface
     */
    private $kernel;

    /**
     * Constructor.
     *
     * @param \Composer\IO\IOInterface                      $io
     * @param \Composer\Composer                            $composer
     * @param string                                        $type
     * @param \Symfony\Component\HttpKernel\KernelInterface $kernel
     */
    public function __construct(
        IOInterface $io,
        Composer $composer,
        $type = 'library',
        KernelInterface $kernel = null
    )
    {
        parent::__construct($io, $composer, $type);
        $this->kernel = $kernel;
    }

    /**
     * {@inheritDoc}
     */
    public function supports($packageType)
    {
        return $packageType === 'claroline-core';
    }

    /**
     * {@inheritDoc}
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        $this->initialize();
        $this->installPackage($repo, $package);
        $properties = $this->resolvePackageName($package->getName());

        try {
            $this->io->write("  - Installing <info>{$package->getName()}</info> as a Claroline core bundle");
            $this->getBaseInstaller()->install($properties['fqcn'], $properties['path']);
        } catch (\Exception $ex) {
            $this->uninstallPackage($repo, $package);

            throw new InstallationException(
                "An exception with message '{$ex->getMessage()}' occured during "
                    . "{$package->getName()} installation. The package has been "
                    . 'removed. Installation is aborting.'
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        $this->initialize();
        $properties = $this->resolvePackageName($package->getName());
        $this->io->write("  - Uninstalling Claroline core bundle <info>{$package->getName()}</info>");
        $this->getBaseInstaller()->uninstall($properties['fqcn']);
        $this->uninstallPackage($repo, $package);
    }

    /**
     * {@inheritDoc}
     */
    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target)
    {
        $this->initialize();
        $baseInstaller = $this->getBaseInstaller();
        $properties = $this->resolvePackageName($initial->getName());
        $initialDbVersion = $this->getDatabaseVersion($initial);
        $targetDbVersion = $this->getDatabaseVersion($target);

        if (false === $targetDbVersion || $initialDbVersion === $targetDbVersion) {
            $this->updatePackage($repo, $initial, $target);
        } elseif (false === $initialDbVersion || $initialDbVersion < $targetDbVersion) {
            $this->updatePackage($repo, $initial, $target);
            $this->io->write("  - Migrating <info>{$target->getName()}</info> to db version '{$targetDbVersion}'");
            $baseInstaller->migrate($properties['fqcn'], $targetDbVersion);
        } elseif ($initialDbVersion > $targetDbVersion) {
            $this->io->write("  - Migrating <info>{$target->getName()}</info> to db version '{$targetDbVersion}'");
            $baseInstaller->migrate($properties['fqcn'], $targetDbVersion);
            $this->updatePackage($repo, $initial, $target);
        }
    }

    /**
     * Parent method wrapper (testing purposes).
     *
     * @param \Composer\Repository\InstalledRepositoryInterface $repo
     * @param \Composer\Package\PackageInterface                $package
     */
    public function installPackage(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        parent::install($repo, $package);
    }

    /**
     * Parent method wrapper (testing purposes).
     *
     * @param \Composer\Repository\InstalledRepositoryInterface $repo
     * @param \Composer\Package\PackageInterface                $package
     */
    public function uninstallPackage(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        parent::uninstall($repo, $package);
    }

    /**
     * Parent method wrapper (testing purposes).
     *
     * @param \Composer\Repository\InstalledRepositoryInterface $repo
     * @param \Composer\Package\PackageInterface                $initial
     * @param \Composer\Package\PackageInterface                $target
     */
    public function updatePackage(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target)
    {
        parent::update($repo, $initial, $target);
    }

    private function initialize()
    {
        if ($this->kernel === null) {
            require_once $this->vendorDir . '/../app/AppKernel.php';
            $this->kernel = new \AppKernel('dev', true);
            $this->kernel->boot();
        }
    }

    private function resolvePackageName($packageName)
    {
        $parts = explode('/', $packageName);
        $vendor = ucfirst($parts[0]);
        $bundleParts = explode('-', $parts[1]);
        $bundle = '';

        foreach ($bundleParts as $bundlePart) {
            $bundle .= ucfirst($bundlePart);
        }

        $namespace = "{$vendor}\\{$bundle}";
        $fqcn = "{$namespace}\\{$vendor}{$bundle}";
        $path = "{$this->vendorDir}/{$packageName}/{$vendor}/{$bundle}/{$vendor}{$bundle}.php";

        return array('namespace' => $namespace, 'fqcn' => $fqcn, 'path' => $path);
    }

    private function getBaseInstaller()
    {
        return $this->kernel->getContainer()->get('claroline.installation.manager');
    }

    private function getDatabaseVersion(PackageInterface $package)
    {
        $extra = $package->getExtra();

        return isset($extra['dbVersion']) ? $extra['dbVersion'] : false;
    }
}
