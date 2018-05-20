<?php

namespace EMS\CoreBundle\Exception;


class AssetNotFoundException extends ElasticmsException
{
    public function __construct($hash)
    {
        parent::__construct(sprintf('Asset with the hash "%s" not found', $hash));
    }
}
