<?php
declare(strict_types=1);

namespace App\Tests\Api;

use PHPUnit\Framework\TestCase;

/**
 * End-to-end API tests using PHP cURL.
 *
 * Requirements:
 * - The Symfony app is running and reachable via BASE_URL (default http://localhost:8000).
 * - The dataset file exists at DATASET_PATH (default var/data/request.json) and is loaded
 *   automatically on first request via the BootstrapDatasetSubscriber.
 *
 * You can override defaults with environment variables:
 *   BASE_URL=http://localhost:8000
 *   DATASET_PATH=var/data/request.json
 */
final class ApiTest extends TestCase
{
    private string $baseUrl;
    private string $datasetPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->baseUrl      = rtrim(getenv('BASE_URL') ?: 'http://localhost:8000', '/');
        $this->datasetPath  = getenv('DATASET_PATH') ?: \dirname(__DIR__, 2) . '/var/data/request.json';
    }

    /** Simple helper to perform JSON requests via cURL */
    private function curlJson(string $method, string $path, ?array $body = null, array $query = [], array $headers = []): array
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        if ($query) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        $ch = curl_init($url);
        $curlHeaders = array_merge([
            'Accept: application/json',
        ], $headers);

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_HTTPHEADER     => $curlHeaders,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_FOLLOWLOCATION => true,
        ];

        if ($body !== null) {
            $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $opts[CURLOPT_POSTFIELDS] = $payload;
            // Ensure content-type header exists
            $hasCt = false;
            foreach ($curlHeaders as $h) {
                if (stripos($h, 'content-type:') === 0) { $hasCt = true; break; }
            }
            if (!$hasCt) {
                $opts[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
            }
        }

        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            $this->fail('cURL error: ' . $err);
        }

        $json = json_decode($response, true);
        // Allow endpoints to return arrays or objects
        return ['status' => $status, 'json' => $json, 'raw' => $response];
    }

    /** GET /api/fruit */
    private function getFruits(array $query = []): array
    {
        return $this->curlJson('GET', 'api/fruit', null, $query);
    }

    /** GET /api/vegetable */
    private function getVegetables(array $query = []): array
    {
        return $this->curlJson('GET', 'api/vegetable', null, $query);
    }

    /** Positive test: list fruits with filters and units */
    public function testListFruitsWithFiltersAndUnitsSuccess(): void
    {
        $res = $this->getFruits(['unit' => 'kg', 'q' => 'ber', 'min' => 9000]);

        $this->assertSame(200, $res['status'], 'Expected HTTP 200 for filtered fruit list');

        $this->assertIsArray($res['json']);
        
        $found = false;
        foreach ($res['json'] as $row) {
            if (isset($row['name']) && stripos($row['name'], 'berries') !== false) {
                $this->assertSame('kg', $row['unit'] ?? null);
                $this->assertEquals(10, $row['quantity']); // 10000 g => 10 kg
                $found = true;
            }
        }
        $this->assertTrue($found, 'Expected to find "Berries" with 10 kg in response');
    }

    /** Negative test: list fruits with filters and units */
    public function testListFruitsWithFiltersAndUnitsError(): void
    {
        $res = $this->getFruits(['unit' => 'kg', 'q' => 'ber', 'min' => 11000]);

        $this->assertSame(200, $res['status'], 'Expected HTTP 200 for filtered fruit list');

        $this->assertIsArray($res['json']);
        
        $found = false;
        foreach ($res['json'] as $row) {
            if (isset($row['name']) && stripos($row['name'], 'berries') !== false) {
                $found = true;
            }
        }
        $this->assertFalse($found, 'Expected not to find "Berries" with 10 kg in response');
    }

    /** POST /api/vegetable to add a new item */
    public function testAddVegetableAndRetrieve(): void
    {
        $payload = [
            'id'       => 2101,
            'name'     => 'Spinach',
            'type'     => 'vegetable',
            'quantity' => 5,
            'unit'     => 'kg',
        ];
        $post = $this->curlJson('POST', 'api/vegetable', $payload);

        $this->assertSame(201, $post['status'], 'Expected HTTP 201 on successful POST');
        $this->assertSame('ok', $post['json']['status'] ?? null);
    }

    /**
     * Dataset consistency test:
     * - Reads request.json
     * - Calls /api/fruit and /api/vegetable
     * - Verifies that *all* items from JSON appear via the API with correct fields/units (g)
     */
    public function testDatasetConsistencyWithRequestJson(): void
    {
        $this->assertFileExists($this->datasetPath, 'Dataset file not found: '.$this->datasetPath);
        $data = json_decode((string)file_get_contents($this->datasetPath), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($data, 'Invalid dataset JSON');

        // Build expected maps normalized to grams and grouped by type
        $expectedFruits = [];
        $expectedVeggies = [];
        foreach ($data as $row) {
            $this->assertArrayHasKey('id', $row);
            $this->assertArrayHasKey('name', $row);
            $this->assertArrayHasKey('type', $row);
            $this->assertArrayHasKey('quantity', $row);
            $this->assertArrayHasKey('unit', $row);

            $id   = (int)$row['id'];
            $name = (string)$row['name'];
            $type = strtolower((string)$row['type']);
            $qty  = (int)$row['quantity'];
            $unit = strtolower((string)$row['unit']);

            $grams = $unit === 'kg' ? (int)round($qty * 1000) : $qty;

            $expected = [
                'id'       => $id,
                'name'     => $name,
                'type'     => $type,
                'quantity' => $grams,
                'unit'     => 'g', // API default output
            ];

            if ($type === 'fruit') {
                $expectedFruits[$id] = $expected;
            } elseif ($type === 'vegetable') {
                $expectedVeggies[$id] = $expected;
            } else {
                $this->fail("Unexpected type in dataset: {$type}");
            }
        }

        // Fetch from API
        $fruits = $this->getFruits();
        $vegetables = $this->getVegetables();

        $this->assertSame(200, $fruits['status'], 'GET /api/fruit must return 200');
        $this->assertSame(200, $vegetables['status'], 'GET /api/vegetable(s) must return 200');

        // Index results by id for robust comparison
        $actualFruits = [];
        foreach ($fruits['json'] as $row) {
            $actualFruits[(int)$row['id']] = $row;
        }
        $actualVeggies = [];
        foreach ($vegetables['json'] as $row) {
            $actualVeggies[(int)$row['id']] = $row;
        }

        // Ensure every dataset item is present and correctly normalized
        foreach ($expectedFruits as $id => $exp) {
            $this->assertArrayHasKey($id, $actualFruits, "Fruit id {$id} missing in API response");
            $act = $actualFruits[$id];
            $this->assertSame($exp['name'], $act['name'], "Fruit name mismatch for id {$id}");
            $this->assertSame('fruit', $act['type'], "Fruit type mismatch for id {$id}");
            $this->assertSame('g', $act['unit'], "Fruit unit mismatch for id {$id}");
            $this->assertSame($exp['quantity'], (int)$act['quantity'], "Fruit quantity(g) mismatch for id {$id}");
        }
        foreach ($expectedVeggies as $id => $exp) {
            $this->assertArrayHasKey($id, $actualVeggies, "Vegetable id {$id} missing in API response");
            $act = $actualVeggies[$id];
            $this->assertSame($exp['name'], $act['name'], "Vegetable name mismatch for id {$id}");
            $this->assertSame('vegetable', $act['type'], "Vegetable type mismatch for id {$id}");
            $this->assertSame('g', $act['unit'], "Vegetable unit mismatch for id {$id}");
            $this->assertSame($exp['quantity'], (int)$act['quantity'], "Vegetable quantity(g) mismatch for id {$id}");
        }
    }

    /** A couple of error examples */
    public function testErrorInvalidType(): void
    {
        $res = $this->curlJson('GET', 'api/meat');
        $this->assertSame(404, $res['status']);
        $this->assertSame('Unknown collection', $res['json']['error'] ?? null);
    }

    public function testErrorInvalidJson(): void
    {
        $res = $this->curlJson('POST', 'api/fruit', body: [], query: [], headers: ['Content-Type: application/json']);
        $this->assertSame(400, $res['status']);
        $this->assertSame('Missing key \'id\'', $res['json']['error'] ?? null);
    }

    public function testErrorInvalidUnit(): void
    {
        $payload = [
            'id'       => 77701,
            'name'     => 'TestFruit',
            'type'     => 'fruit',
            'quantity' => 10,
            'unit'     => 'lb',
        ];
        $res = $this->curlJson('POST', 'api/fruit', $payload);
        $this->assertSame(400, $res['status']);
        $this->assertStringContainsString('Invalid unit', (string)($res['json']['error'] ?? ''));
    }
}
