<?php
namespace App\Domain;

final class Item
{
    private int $id;
    private string $name;
    private string $type;   // 'fruit' | 'vegetable'
    private int $quantity;  // in grams

    public function __construct(int $id, string $name, string $type, int $quantityInGivenUnit, string $unit)
    {
        $type = strtolower($type);
        if (!\in_array($type, ['fruit','vegetable'], true)) {
            throw new \InvalidArgumentException("Invalid type '$type'.");
        }
        $this->id = $id;
        $this->name = $name;
        $this->type = $type;
        $this->quantity = $this->toGrams($quantityInGivenUnit, $unit);
    }

    private function toGrams(int|float $q, string $unit): int
    {
        $u = strtolower($unit);
        if ($u === 'kg') {
            return (int)\round(((float)$q) * 1000);
        }
        if ($u !== 'g') {
            throw new \InvalidArgumentException("Unsupported unit '$unit'.");
        }
        return (int)$q;
    }

    // --- getters ---
    public function getId(): int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getType(): string { return $this->type; }
    public function getQuantity(): int { return $this->quantity; } // grams

    public function toArray(string $unit = 'g'): array
    {
        $unit = strtolower($unit);
        $quantity = $this->quantity;
        if ($unit === 'kg') {
            return [
                'id' => $this->id,
                'name' => $this->name,
                'type' => $this->type,
                'quantity' => $quantity / 1000, // float possible
                'unit' => 'kg',
            ];
        }
        // default g
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'quantity' => $quantity,
            'unit' => 'g',
        ];
    }
}
