<?php
namespace App\Domain;

interface CollectionInterface
{
    public function add(Item $item): void;
    public function remove(int $id): void;

    /**
     * $filters:
     * - 'q'    => string   (name substring, case-insensitive)
     * - 'min'  => int      (minimum quantity in g)
     * - 'max'  => int      (maximum quantity in g)
     * - 'unit' => 'g'|'kg' (output unit)
     */
    public function list(array $filters = []): array;
}
