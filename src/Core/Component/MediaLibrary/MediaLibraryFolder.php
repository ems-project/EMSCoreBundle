<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\MediaLibrary;

class MediaLibraryFolder
{
    public MediaLibraryFolders $folders;

    public function __construct(public string $name, public string $path)
    {
        $this->folders = new MediaLibraryFolders();
    }
}
