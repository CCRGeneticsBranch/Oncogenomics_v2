<?php

namespace App\Services;

use Illuminate\Support\Str;
use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

final class ChatbotMarkdownRenderer
{
    public function render(string $markdown): string
    {
        $markdown = $this->unwrapWholeMarkdownFence($markdown);
        $markdown = $this->renderStructuredLinkCells($markdown);
        if (trim($markdown) === '') {
            return '';
        }

        return Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 50,
            'max_delimiters_per_line' => 1000,
        ], [$this->omitRemoteImagesExtension()]);
    }

    private function unwrapWholeMarkdownFence(string $markdown): string
    {
        if (preg_match('/\A\s*```(?:markdown|md)\s*\R(.*?)\R```\s*\z/isu', $markdown, $matches) === 1) {
            return (string) $matches[1];
        }

        return $markdown;
    }

    /**
     * Tool tables use a JSON link-cell contract. If a model copies one into
     * its Markdown summary, convert only that narrow, validated shape to a
     * normal Markdown link instead of displaying the JSON source.
     */
    private function renderStructuredLinkCells(string $markdown): string
    {
        return (string) preg_replace_callback(
            '/\{\s*"type"\s*:\s*"link"\s*,\s*"label"\s*:\s*("(?:\\\\.|[^"\\\\])*")\s*,\s*"url"\s*:\s*("(?:\\\\.|[^"\\\\])*")\s*\}/u',
            function (array $matches): string {
                $label = json_decode($matches[1], true);
                $url = json_decode($matches[2], true);
                if (! is_string($label) || ! is_string($url)) {
                    return $matches[0];
                }

                $label = str_replace(['\\', '[', ']', '|'], ['\\\\', '\\[', '\\]', '\\|'], $label);
                if (! $this->isSafeLink($url)) {
                    return $label;
                }

                $url = str_replace(['\\', '>'], ['%5C', '%3E'], $url);

                return '['.$label.'](<'.$url.'>)';
            },
            $markdown,
        );
    }

    private function isSafeLink(string $url): bool
    {
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return true;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true)
            && parse_url($url, PHP_URL_USER) === null
            && parse_url($url, PHP_URL_PASS) === null;
    }

    /**
     * Chatbot plots are delivered as authorized execution artifacts. Dropping
     * Markdown images prevents an answer from making the browser request an
     * arbitrary third-party tracking URL.
     */
    private function omitRemoteImagesExtension(): ExtensionInterface
    {
        return new class implements ExtensionInterface
        {
            public function register(EnvironmentBuilderInterface $environment): void
            {
                $environment->addRenderer(Image::class, new class implements NodeRendererInterface
                {
                    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
                    {
                        Image::assertInstanceOf($node);

                        return '';
                    }
                }, 100);
            }
        };
    }
}
