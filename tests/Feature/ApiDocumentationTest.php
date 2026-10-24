<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiDocumentationTest extends TestCase
{
    public function test_every_api_route_has_a_documented_operation(): void
    {
        $contract = $this->contract();
        $actual = [];
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/')) {
                continue;
            }

            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $actual[] = $method.' /'.$route->uri();
            }
        }

        $documented = [];
        foreach ($contract['paths'] as $path => $operations) {
            foreach ($operations as $method => $operation) {
                if (in_array($method, ['get', 'post', 'put', 'patch', 'delete', 'options', 'head'], true)) {
                    $documented[] = strtoupper($method).' '.$path;
                }
            }
        }

        sort($actual);
        sort($documented);
        $this->assertSame($actual, $documented, 'Update docs/api/openapi.json and docs/api/README.md for every API route change.');
    }

    public function test_operations_have_schemas_examples_and_resolvable_references(): void
    {
        $contract = $this->contract();
        $reference = file_get_contents(base_path('docs/api/README.md'));
        $operationIds = [];

        foreach ($contract['paths'] as $path => $operations) {
            foreach ($operations as $method => $operation) {
                $label = strtoupper($method).' '.$path;
                $this->assertStringContainsString('`'.$label.'`', $reference, 'Missing readable endpoint example: '.$label);
                $this->assertNotEmpty($operation['operationId']);
                $operationIds[] = $operation['operationId'];
                $this->assertArrayHasKey('security', $operation, $label);

                $successful = array_filter($operation['responses'], fn ($status): bool => str_starts_with((string) $status, '2'), ARRAY_FILTER_USE_KEY);
                $this->assertNotEmpty($successful, $label);
                foreach ($operation['responses'] as $response) {
                    $media = $response['content']['application/json'];
                    $this->assertArrayHasKey('schema', $media, $label);
                    $this->assertArrayHasKey('example', $media, $label);
                }

                if (isset($operation['requestBody'])) {
                    $media = $operation['requestBody']['content']['application/json'];
                    $this->assertArrayHasKey('schema', $media, $label);
                    $this->assertArrayHasKey('example', $media, $label);
                }

                $section = explode("\n## ", explode("\n## ".$operation['operationId']."\n", $reference, 2)[1], 2)[0];
                preg_match_all('/```json\s*\n(.*?)\n```/s', $section, $blocks);
                $expectedExamples = [];
                if (isset($operation['requestBody'])) {
                    $expectedExamples[] = $operation['requestBody']['content']['application/json']['example'];
                }
                foreach ($successful as $response) {
                    $expectedExamples[] = $response['content']['application/json']['example'];
                }
                $readableExamples = array_map(fn (string $json): array => json_decode($json, true, flags: JSON_THROW_ON_ERROR), $blocks[1]);
                $this->assertSame($expectedExamples, $readableExamples, 'Keep Markdown and OpenAPI examples consistent: '.$label);

                preg_match_all('/\{([^}]+)\}/', $path, $matches);
                $parameters = $operation['parameters'] ?? [];
                foreach ($matches[1] as $name) {
                    $matching = array_values(array_filter($parameters, fn (array $parameter): bool => $parameter['in'] === 'path' && $parameter['name'] === $name));
                    $this->assertCount(1, $matching, $label);
                    $this->assertTrue($matching[0]['required'], $label);
                }
            }
        }

        $this->assertCount(count($operationIds), array_unique($operationIds), 'OpenAPI operation IDs must be unique.');
        $this->assertReferencesResolve($contract, $contract);
    }

    private function contract(): array
    {
        return json_decode(file_get_contents(base_path('docs/api/openapi.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    private function assertReferencesResolve(array $node, array $contract): void
    {
        if (isset($node['$ref'])) {
            $this->assertStringStartsWith('#/', $node['$ref']);
            $target = $contract;
            foreach (explode('/', substr($node['$ref'], 2)) as $part) {
                $part = str_replace(['~1', '~0'], ['/', '~'], $part);
                $this->assertArrayHasKey($part, $target, 'Unresolved OpenAPI reference: '.$node['$ref']);
                $target = $target[$part];
            }
        }

        foreach ($node as $value) {
            if (is_array($value)) {
                $this->assertReferencesResolve($value, $contract);
            }
        }
    }
}
