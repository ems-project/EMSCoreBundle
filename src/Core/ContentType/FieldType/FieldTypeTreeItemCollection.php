<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\ContentType\FieldType;

use Doctrine\Common\Collections\ArrayCollection;

/**
 * @extends ArrayCollection<int, FieldTypeTreeItem>
 *
 * @method \ArrayIterator<int, FieldTypeTreeItem> getIterator()
 */
class FieldTypeTreeItemCollection extends ArrayCollection
{
}
