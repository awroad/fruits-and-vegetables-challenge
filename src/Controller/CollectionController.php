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
        $collection = $type === 'fruit' ? $this->storage->fruits() : $this->storage->vegetables();
        return $this->json($collection->list($filters));
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
