<?php

use App\Services\RichTextSanitizer;

test('scripts and event handlers are removed', function () {
    $clean = app(RichTextSanitizer::class)->clean('<p onclick="x()">Hi</p><script>alert(1)</script><img src="x" onerror="alert(1)">');

    expect($clean)->toBe('<p>Hi</p>');
});

test('formatting tags and lists are kept', function () {
    $clean = app(RichTextSanitizer::class)->clean('<h2>Care</h2><ul><li><strong>Wash</strong> cold</li></ul>');

    expect($clean)->toBe('<h2>Care</h2><ul><li><strong>Wash</strong> cold</li></ul>');
});

test('only http, https and mailto links survive', function () {
    $clean = app(RichTextSanitizer::class)->clean('<a href="javascript:alert(1)">bad</a> <a href="https://example.com/x">good</a> <a href="mailto:a@b.co">mail</a>');

    expect($clean)->toContain('<a href="https://example.com/x" rel="nofollow noopener">good</a>')
        ->and($clean)->toContain('<a href="mailto:a@b.co" rel="nofollow noopener">mail</a>')
        ->and($clean)->not->toContain('javascript')
        ->and($clean)->toContain('bad');
});

test('Trix paragraphs are stored as paragraph tags', function () {
    $clean = app(RichTextSanitizer::class)->clean('<div>First</div><div>Second</div>');

    expect($clean)->toBe('<p>First</p><p>Second</p>');
});

test('empty input stays empty', function () {
    expect(app(RichTextSanitizer::class)->clean('   '))->toBeNull()
        ->and(app(RichTextSanitizer::class)->clean(null))->toBeNull();
});
