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
            ->and($result['system'])->toContain('expert image analyzer')
            ->and($result['system'])->not->toContain('Prioritize')
            ->and($result['user'])->toContain('Analyze this image')
            ->and($result['user'])->not->toContain('PRIORITY TAGS');
    });

    it('includes priority keys in system prompt when provided', function () {
        $priorityKeys = ['color', 'brand', 'size'];

        $result = $this->builder->buildPrompt($this->image, null, $priorityKeys);

        expect($result['system'])->toContain('Prioritize generating tags for these keys if applicable')
            ->and($result['system'])->toContain('color, brand, size')
            ->and($result['system'])->toContain('Also include other relevant tags as needed');
    });

    it('includes priority keys in user prompt when provided', function () {
        $priorityKeys = ['color', 'brand', 'condition'];

        $result = $this->builder->buildPrompt($this->image, null, $priorityKeys);

        expect($result['user'])->toContain('⭐ PRIORITY TAGS')
            ->and($result['user'])->toContain('color, brand, condition')
            ->and($result['user'])->toContain('still include any other relevant tags as well');
    });

    it('does not add priority keys when requested keys are provided', function () {
        $requestedKeys = ['material', 'weight'];
        $priorityKeys = ['color', 'brand'];

        $result = $this->builder->buildPrompt($this->image, $requestedKeys, $priorityKeys);

        // When requested keys are provided, priority keys should be ignored
        expect($result['system'])->not->toContain('Prioritize')
            ->and($result['system'])->toContain('provide specific information requested by the user')
            ->and($result['user'])->toContain('provide values for the following requested information: material, weight')
            ->and($result['user'])->not->toContain('PRIORITY TAGS');
    });

    it('handles empty priority keys array', function () {
        $result = $this->builder->buildPrompt($this->image, null, []);

        expect($result['system'])->not->toContain('Prioritize')
            ->and($result['user'])->not->toContain('PRIORITY TAGS');
    });

    it('handles null priority keys (backward compatibility)', function () {
        $result = $this->builder->buildPrompt($this->image, null, null);

        expect($result)->toBeArray()
            ->and($result)->toHaveKeys(['system', 'user', 'schema'])
            ->and($result['system'])->not->toContain('Prioritize');
    });

    it('includes image description in user prompt when available', function () {
        $imageWithDescription = new Image;
        $imageWithDescription->description = 'A vintage Pokemon card';

        $result = $this->builder->buildPrompt($imageWithDescription, null, ['color', 'condition']);

        expect($result['user'])->toContain('User provided context: A vintage Pokemon card')
            ->and($result['user'])->toContain('PRIORITY TAGS');
    });

    it('returns correct JSON schema structure', function () {
        $result = $this->builder->buildPrompt($this->image);

        expect($result['schema'])->toBeArray()
            ->and($result['schema']['type'])->toBe('object')
            ->and($result['schema']['properties'])->toHaveKey('tags')
            ->and($result['schema']['required'])->toContain('tags');
    });

    it('includes formatting rules in user prompt', function () {
        $result = $this->builder->buildPrompt($this->image);

        expect($result['user'])->toContain('CRITICAL RULE - NEVER CREATE LISTS IN TAG VALUES')
            ->and($result['user'])->toContain('QUANTITY - MUST BE PURE NUMBERS ONLY')
            ->and($result['user'])->toContain('SIZE - NEVER USE \'x\' OR COMBINED DIMENSIONS');
    });
});
