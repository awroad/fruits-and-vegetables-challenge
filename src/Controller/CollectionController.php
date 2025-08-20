<?php
namespace App\Controller;

use App\Infrastructure\InMemoryStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

final class CollectionController extends AbstractController
{
    public function __construct(private InMemoryStorage $storage) {}

    #[Route('/api/{type}', name: 'collection_list', methods: ['GET'])]
    public function list(string $type, Request $request): JsonResponse
    {
        $type = strtolower($type);
        if (!\in_array($type, ['fruit','vegetable'], true)) {
            return $this->json(['error' => 'Unknown collection'], 404);
        }

        $filters = $request->query->all(); // q, min, max, unit

        // --- Input validation ---
        $validated = [];

        // q: optional string
        if (isset($filters['q'])) {
            if (!is_string($filters['q'])) {
                return $this->json(['error' => "Parameter 'q' must be a string"], 400);
            }
            $validated['q'] = trim($filters['q']);
        }

        // min: optional, must be positive integer
        if (isset($filters['min'])) {
            if (!ctype_digit((string)$filters['min'])) {
                return $this->json(['error' => "Parameter 'min' must be a positive integer (grams)"], 400);
            }
            $validated['min'] = (int)$filters['min'];
        }

        // max: optional, must be positive integer
        if (isset($filters['max'])) {
            if (!ctype_digit((string)$filters['max'])) {
                return $this->json(['error' => "Parameter 'max' must be a positive integer (grams)"], 400);
            }
            $validated['max'] = (int)$filters['max'];
        }

        // unit: optional, must be g or kg
        if (isset($filters['unit'])) {
            $unit = strtolower((string)$filters['unit']);
            if (!in_array($unit, ['g','kg'], true)) {
                return $this->json(['error' => "Parameter 'unit' must be 'g' or 'kg'"], 400);
            }
            $validated['unit'] = $unit;
        }

        // if both min and max exist, ensure min <= max
        if (isset($validated['min'], $validated['max']) && $validated['min'] > $validated['max']) {
            return $this->json(['error' => "Parameter 'min' cannot be greater than 'max'"], 400);
        }

        $collection = $type === 'fruit' ? $this->storage->fruits() : $this->storage->vegetables();
        return $this->json($collection->list($validated));
    }

    #[Route('/api/{type}', name: 'collection_add', methods: ['POST'])]
    public function add(string $type, Request $request): JsonResponse
    {
        $type = strtolower($type);
        if (!\in_array($type, ['fruit','vegetable'], true)) {
            return $this->json(['error' => 'Unknown collection'], 404);
        }

        $data = json_decode((string)$request->getContent(), true);
        if (!\is_array($data)) {
            return $this->json(['error' => 'Invalid JSON'], 400);
        }

        // Delegate validation to the importer (reuses the same logic)
        // Advantage: DRY (POST & bootstrap use the same path)
        try {
            // we use the importer "in-memory" with a single entry
            (new \App\Application\RequestJsonImporter($this->storage))
                ->loadFromArray([$data]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }

        return $this->json(['status' => 'ok'], 201);
    }
}
