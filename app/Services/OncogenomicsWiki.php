<?php

namespace App\Services;

use RuntimeException;

class OncogenomicsWiki
{
    public const URL = 'https://github.com/CCRGeneticsBranch/Oncogenomics_v2/wiki';
    public const REPOSITORY = 'https://github.com/CCRGeneticsBranch/Oncogenomics_v2.wiki.git';

    /** Build a portable snapshot from a wiki checkout. */
    public function snapshot(string $directory, string $revision, bool $workingTreeDirty = false): array
    {
        $pages = [];
        foreach (glob(rtrim($directory, '/').'/*.md') ?: [] as $file) {
            $slug = basename($file, '.md');
            if (str_starts_with($slug, '_')) continue;
            $pages[] = [
                'title' => str_replace('-', ' ', $slug),
                'url' => self::URL.'/'.rawurlencode($slug),
                'markdown' => file_get_contents($file),
            ];
        }
        if ($pages === []) throw new RuntimeException('No Markdown tutorial pages found; existing wiki index was preserved.');

        return ['version' => 1, 'source' => self::URL, 'revision' => $revision,
            'working_tree_dirty' => $workingTreeDirty,
            'content_sha256' => hash('sha256', json_encode($pages, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
            'indexed_at' => gmdate('c'), 'pages' => $pages];
    }

    public function search(string $query, int $limit = 5): array
    {
        $index = $this->load();
        $terms = array_keys($this->tokens($query));
        $chunks = [];
        foreach ($index['pages'] as $page) {
            foreach ($this->chunks($page) as $chunk) {
                $chunk['tokens'] = $this->tokens($chunk['text']);
                $chunk['heading_tokens'] = $this->tokens($chunk['title'].' '.$chunk['section']);
                $chunks[] = $chunk;
            }
        }
        $frequencies = [];
        foreach ($terms as $term) {
            $frequencies[$term] = count(array_filter($chunks, static fn ($chunk) =>
                isset($chunk['tokens'][$term]) || isset($chunk['heading_tokens'][$term])));
        }
        $matches = [];
        foreach ($chunks as $chunk) {
            $score = 0.0;
            foreach ($terms as $term) {
                $weight = log(1 + count($chunks) / (1 + $frequencies[$term]));
                $score += $weight * (min(3, $chunk['tokens'][$term] ?? 0)
                    + 3 * min(1, $chunk['heading_tokens'][$term] ?? 0));
            }
            if ($score <= 0) continue;
            unset($chunk['tokens'], $chunk['heading_tokens']);
            $matches[] = $chunk + ['score' => $score];
        }
        usort($matches, static fn ($a, $b) => ($b['score'] <=> $a['score']) ?: strcmp($a['url'], $b['url']));
        $total = count($matches);
        $matches = array_slice($matches, 0, max(1, min(8, $limit)));
        foreach ($matches as &$match) unset($match['score']);
        unset($match);

        return ['source' => self::URL, 'revision' => $index['revision'],
            'working_tree_dirty' => $index['working_tree_dirty'] ?? false,
            'content_sha256' => $index['content_sha256'] ?? null,
            'indexed_at' => $index['indexed_at'], 'page_count' => count($index['pages']),
            'query' => $query, 'match_count' => $total, 'matches' => $matches,
            'figures' => WikiTierFigures::forQuery($query, implode("\n", array_column($index['pages'], 'markdown'))),
            'notice' => 'Tutorial excerpts describe the documented interface, not live patient or project results. The wiki may lag the current interface.'
                . (!empty($index['working_tree_dirty']) ? ' This snapshot includes local wiki edits; GitHub source links may not reflect them until they are published.' : '')];
    }

    private function load(): array
    {
        // A failed/corrupt refresh must not prevent use of the bundled snapshot.
        foreach ([config('chatbot.wiki.index_path'), config('chatbot.wiki.snapshot_path')] as $path) {
            if (!is_string($path) || !is_file($path)) continue;
            $index = json_decode((string) file_get_contents($path), true);
            if (!is_array($index) || ($index['version'] ?? null) !== 1
                || !is_string($index['revision'] ?? null) || !is_string($index['indexed_at'] ?? null)
                || !is_array($index['pages'] ?? null) || $index['pages'] === []) continue;
            foreach ($index['pages'] as $page) {
                if (!is_array($page) || !is_string($page['markdown'] ?? null)
                    || !is_string($page['title'] ?? null) || !is_string($page['url'] ?? null)
                    || !str_starts_with($page['url'], self::URL.'/')) continue 2;
            }
            return $index;
        }
        throw new RuntimeException('The tutorial index is unavailable. Run php artisan chatbot:refresh-wiki.');
    }

    /** Split along headings, keeping each excerpt and its source link together. */
    private function chunks(array $page): array
    {
        $markdown = preg_replace('/!\[[^\]]*\]\(([^\n]*?)\)/u', '[Screenshot]($1)', $page['markdown']);
        $markdown = preg_replace('/<img\b[^>]*\bsrc=["\']([^"\']+)["\'][^>]*>/iu', '[Screenshot]($1)', $markdown);
        // strip_tags treats biomedical comparisons such as <100bp as HTML
        // and can discard the rest of a page. Remove actual tags only.
        $markdown = preg_replace('/<\/?[a-z][a-z0-9]*\b[^>]*>/iu', '', $markdown);
        $markdown = html_entity_decode($markdown, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Expand relative Markdown links so the agent receives usable source links.
        $markdown = preg_replace_callback('/\[([^\]]+)\]\(([^\s)]+)\)/u', static function ($m) use ($page) {
            $target = $m[2];
            if (str_starts_with($target, '#')) $target = $page['url'].$target;
            elseif (!preg_match('/^[a-z][a-z0-9+.-]*:/i', $target)) $target = self::URL.'/'.ltrim($target, '/');
            return '['.$m[1].']('.$target.')';
        }, $markdown);
        $chunks = [];
        $headings = [];
        $section = '';
        $anchor = '';
        $text = '';
        $flush = static function () use (&$chunks, &$text, &$section, &$anchor, $page): void {
            if (trim($text) !== '' && mb_strtolower($section) !== 'table of contents') {
                $chunks[] = ['title' => $page['title'], 'section' => $section ?: 'Overview',
                    'url' => $page['url'].$anchor, 'text' => trim($text)];
            }
            $text = '';
        };
        foreach (preg_split('/\R/u', $markdown) as $line) {
            if (preg_match('/^(#{1,6})\s+(.+?)\s*#*$/u', $line, $m)) {
                $flush();
                if (mb_strtolower(trim($m[2])) === 'table of contents') {
                    $section = 'Table of Contents';
                    $headings = [];
                    continue;
                }
                $level = strlen($m[1]);
                foreach (array_keys($headings) as $key) if ($key >= $level) unset($headings[$key]);
                $headings[$level] = trim($m[2]);
                $section = implode(' > ', $headings);
                $slug = preg_replace('/[^\p{L}\p{N}_\-\s]/u', '', mb_strtolower(trim($m[2])));
                $anchor = '#'.preg_replace('/\s/u', '-', $slug);
                continue;
            }
            // Bound even long lines (for example a large Markdown table row).
            while (mb_strlen($line) > 2200) {
                if ($text !== '') $flush();
                $text = mb_substr($line, 0, 2200);
                $flush();
                $line = mb_substr($line, 2200);
            }
            if (mb_strlen($text) + mb_strlen($line) > 2200) $flush();
            $text .= $line."\n";
        }
        $flush();
        return $chunks;
    }

    private function tokens(string $text): array
    {
        $text = preg_replace('/\btier[\s-]*([1-4])\b/i', 'tier $1', $text);
        $text = preg_replace('~https?://[^\s)]+~u', '', $text);
        $stop = array_fill_keys(explode(' ', 'a an and are as at be by can could did do does for from how i in is it me my of on or our please should show tell than that the their them there these they this to us use using was we were what when where which who why will with would you your'), true);
        $tokens = [];
        preg_match_all('/[\p{L}\p{N}]+/u', mb_strtolower($text), $matches);
        foreach ($matches[0] as $token) {
            if (isset($stop[$token]) || mb_strlen($token) < 2) continue;
            if (strlen($token) > 3 && str_ends_with($token, 's') && !str_ends_with($token, 'ss')) $token = substr($token, 0, -1);
            $token = ['tiering' => 'tier', 'filtering' => 'filter', 'downloading' => 'download'][$token] ?? $token;
            $tokens[$token] = ($tokens[$token] ?? 0) + 1;
        }
        return $tokens;
    }
}
