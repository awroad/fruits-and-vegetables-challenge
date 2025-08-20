<?php
namespace App\Domain;

final class FruitCollection extends AbstractItemCollection
{
    public function add(Item $item): void
    {
        if ($item->getType() !== 'fruit') {
            throw new \InvalidArgumentException('Only fruits allowed.');
        }
        $this->items[$item->getId()] = $item;
    }
}
