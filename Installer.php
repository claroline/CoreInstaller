<?php

namespace Claroline\CoreInstaller;

use Composer\Installer\LibraryInstaller;

/**
 * Composer custom installer for Claroline core bundles.
 */
class Installer extends LibraryInstaller
{
    /**
     * {@inheritDoc}
     */
    public function supports($packageType)
    {
        return false;
    }
}
