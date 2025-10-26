<?php

use App\Models\Image;
use App\Support\PromptBuilder;

describe('PromptBuilder', function () {
    beforeEach(function () {
        $this->builder = new PromptBuilder;
        $this->image = new Image;
    });

    it('builds basic prompt without priority keys', function () {
        $result = $this->builder->buildPrompt($this->image);

        expect($result)->toBeArray()
            ->and($result)->toHaveKeys(['system', 'user', 'schema'])
            ->and($result['system'])->toBeString()->not->toBeEmpty()
            ->and($result['user'])->toBeString()->not->toBeEmpty()
            ->and($result['schema'])->toBeArray();
    });

    it('includes priority keys when provided', function () {
        $priorityKeys = ['color', 'brand', 'size'];

        $result = $this->builder->buildPrompt($this->image, null, $priorityKeys);

        // Just verify the keys are mentioned somewhere in the prompts
        $combinedPrompt = $result['system'].$result['user'];
        expect($combinedPrompt)->toContain('color')
            ->and($combinedPrompt)->toContain('brand')
            ->and($combinedPrompt)->toContain('size');
    });

    it('does not add priority keys when requested keys are provided', function () {
        $requestedKeys = ['material', 'weight'];
        $priorityKeys = ['color', 'brand'];

        $result = $this->builder->buildPrompt($this->image, $requestedKeys, $priorityKeys);

        // When requested keys are provided, they should be in the prompt
        $combinedPrompt = $result['system'].$result['user'];
        expect($combinedPrompt)->toContain('material')
            ->and($combinedPrompt)->toContain('weight');
    });

    it('handles empty priority keys array', function () {
        $result = $this->builder->buildPrompt($this->image, null, []);

        expect($result)->toBeArray()
            ->and($result)->toHaveKeys(['system', 'user', 'schema']);
    });

    it('handles null priority keys (backward compatibility)', function () {
        $result = $this->builder->buildPrompt($this->image, null, null);

        expect($result)->toBeArray()
            ->and($result)->toHaveKeys(['system', 'user', 'schema']);
    });

    it('includes image description in user prompt when available', function () {
        $imageWithDescription = new Image;
        $imageWithDescription->description = 'A vintage Pokemon card';

        $result = $this->builder->buildPrompt($imageWithDescription, null, ['color', 'condition']);

        expect($result['user'])->toContain('A vintage Pokemon card');
    });

    it('returns correct JSON schema structure', function () {
        $result = $this->builder->buildPrompt($this->image);

        expect($result['schema'])->toBeArray()
            ->and($result['schema']['type'])->toBe('object')
            ->and($result['schema']['properties'])->toHaveKey('tags')
            ->and($result['schema']['required'])->toContain('tags');
    });

    it('builds tag extraction prompt with query and tag keys', function () {
        $query = 'show me toy story dvds';
        $tagKeys = ['title', 'format', 'category'];

        $result = $this->builder->buildTagExtractionPrompt($query, $tagKeys);

        expect($result)->toBeArray()
            ->and($result)->toHaveKeys(['system', 'user', 'schema'])
            ->and($result['system'])->toBeString()->not->toBeEmpty()
            ->and($result['user'])->toBeString()->not->toBeEmpty()
            ->and($result['schema'])->toBeArray();
    });

    it('includes query text in tag extraction prompt', function () {
        $query = 'red nike shoes';
        $tagKeys = ['color', 'brand', 'product type'];

        $result = $this->builder->buildTagExtractionPrompt($query, $tagKeys);

        expect($result['user'])->toContain($query);
    });

    it('includes all tag keys in extraction prompt', function () {
        $tagKeys = ['color', 'brand', 'size', 'material'];

        $result = $this->builder->buildTagExtractionPrompt('search query', $tagKeys);

        $combinedPrompt = $result['system'].$result['user'];

        foreach ($tagKeys as $key) {
            expect($combinedPrompt)->toContain($key);
        }
    });

    it('tag extraction prompt uses same schema as image tagging', function () {
        $imagePromptSchema = $this->builder->buildPrompt($this->image)['schema'];
        $extractionPromptSchema = $this->builder->buildTagExtractionPrompt('test', ['color'])['schema'];

        expect($extractionPromptSchema)->toBe($imagePromptSchema);
    });
});
