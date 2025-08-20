<?php
namespace App\Domain;

abstract class AbstractItemCollection implements CollectionInterface
{
    /** @var array<int, Item> */
    protected array $items = [];

    public function remove(int $id): void
    {
        unset($this->items[$id]);
    }

    public function list(array $filters = []): array
    {
        $q    = isset($filters['q'])   ? (string)$filters['q']   : null;
        $min  = isset($filters['min']) ? (int)$filters['min']    : null; // in g
        $max  = isset($filters['max']) ? (int)$filters['max']    : null; // in g
        $unit = isset($filters['unit'])? (string)$filters['unit']: 'g';

        $out = [];
        foreach ($this->items as $item) {
            if ($q !== null && stripos($item->getName(), $q) === false) {
                continue;
            }
            $qty = $item->getQuantity(); // grams
            if ($min !== null && $qty < $min) continue;
            if ($max !== null && $qty > $max) continue;

            $out[] = $item->toArray($unit);
        }
        return $out;
    }
}
