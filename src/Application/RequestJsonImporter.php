<?php
namespace App\Application;

use App\Domain\Item;
use App\Infrastructure\InMemoryStorage;

final class RequestJsonImporter
{
    private InMemoryStorage $storage;
    private bool $loaded = false;

    public function __construct(InMemoryStorage $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Loads from file once. Subsequent calls are no-op.
     */
    public function loadOnceFromFile(string $filePath): void
    {
        if ($this->loaded) {
            return;
        }
        $data = $this->readFile($filePath);
        $this->loadFromArray($data);
        $this->loaded = true;
    }

    /**
     * For tests or direct usage without file.
     * @param array<int, array<string,mixed>> $data
     */
    public function loadFromArray(array $data): void
    {
        foreach ($data as $row) {
            $item = $this->createItemFromRow($row);
            if ($item->getType() === 'fruit') {
                $this->storage->fruits()->add($item);
            } else {
                $this->storage->vegetables()->add($item);
            }
        }
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    private function readFile(string $filePath): array
    {
        if (!is_file($filePath)) {
            throw new \RuntimeException("Bootstrap file not found: $filePath");
        }
        $json = file_get_contents($filePath);
        if ($json === false) {
            throw new \RuntimeException("Cannot read file: $filePath");
        }
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!\is_array($data)) {
            throw new \RuntimeException("Invalid JSON structure in $filePath");
        }
        return $data;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function createItemFromRow(array $row): Item
    {
        // Minimal validation: throw meaningful exceptions
        foreach (['id','name','type','quantity','unit'] as $key) {
            if (!array_key_exists($key, $row)) {
                throw new \InvalidArgumentException("Missing key '$key'");
            }
        }
        $id = (int)$row['id'];
        $name = (string)$row['name'];
        $type = strtolower((string)$row['type']);
        $quantity = (int)$row['quantity'];
        $unit = (string)$row['unit'];

        if ($id <= 0) {
            throw new \InvalidArgumentException("Invalid id '$id'");
        }
        if ($name === '') {
            throw new \InvalidArgumentException("Empty name");
        }
        if (!\in_array($type, ['fruit','vegetable'], true)) {
            throw new \InvalidArgumentException("Invalid type '$type'");
        }
        if ($quantity < 0) {
            throw new \InvalidArgumentException("Negative quantity not allowed");
        }
        if (!\in_array(strtolower($unit), ['g','kg'], true)) {
            throw new \InvalidArgumentException("Invalid unit '$unit'");
        }

        return new Item($id, $name, $type, $quantity, $unit);
    }
}
