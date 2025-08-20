<?php
namespace App\Infrastructure;

use App\Domain\FruitCollection;
use App\Domain\VegetableCollection;

final class InMemoryStorage
{
    private FruitCollection $fruits;
    private VegetableCollection $vegetables;

    public function __construct(FruitCollection $fruits, VegetableCollection $vegetables)
    {
        $this->fruits = $fruits;
        $this->vegetables = $vegetables;
    }

    public function fruits(): FruitCollection { return $this->fruits; }
    public function vegetables(): VegetableCollection { return $this->vegetables; }
}
