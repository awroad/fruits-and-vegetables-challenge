<?php
namespace App\Domain;

final class VegetableCollection extends AbstractItemCollection
{
    public function add(Item $item): void
    {
        if ($item->getType() !== 'vegetable') {
            throw new \InvalidArgumentException('Only vegetables allowed.');
        }
        $this->items[$item->getId()] = $item;
    }
}
