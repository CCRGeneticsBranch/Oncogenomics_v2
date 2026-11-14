<?php

namespace App\Services;

class PcaPlotPresenter
{
    public function applyQueryIntent(array $arguments, ?string $query): array
    {
        if (preg_match('/\b(?:3\s*-?\s*d|three[ -]dimensional)\b/i', $query ?? '')) $arguments['plot_type'] = 'scatter3d';
        elseif (preg_match('/\b(?:2\s*-?\s*d|two[ -]dimensional)\b/i', $query ?? '')) $arguments['plot_type'] = 'scatter';
        if (empty($arguments['color_by']) && preg_match('/\bcolou?r(?:ed|ing)?\s+by\s+(?:the\s+)?diagnosis\b/i', $query ?? '')) $arguments['color_by'] = 'Diagnosis';
        return $arguments;
    }

    public function present(array $result, ?int $maxBytes = null, ?string $query = null): ?array
    {
        if (strtolower($result['action'] ?? '') !== 'getpcadata' || ($result['status'] ?? '') !== 'success') return null;
        $result = $this->applyQueryIntent($result, $query);
        $type = $result['plot_type'] ?? 'scatter';
        if (!in_array($type, ['scatter', 'scatter3d'], true)) return null;
        $fields = $type === 'scatter3d' ? ['pc1', 'pc2', 'pc3'] : ['pc1', 'pc2'];
        $rows = array_values(array_filter($result['coordinates'] ?? [], static function ($row) use ($fields) {
            foreach ($fields as $field) {
                if (!is_numeric($row[$field] ?? null) || !is_finite((float) $row[$field])) return false;
            }
            return true;
        }));
        if ($rows === []) return null;
        $colorBy = trim((string) ($result['color_by'] ?? ''));
        $metadata = $result['sample_metadata'] ?? [];
        $colorIndex = null;
        foreach ($metadata['attr_list'] ?? [] as $index => $field) {
            if ($colorBy !== '' && strcasecmp(trim((string) $field), $colorBy) === 0) {
                $colorIndex = $index;
                $colorBy = trim((string) $field);
                break;
            }
        }
        // Sample diagnosis has different display labels across installations.
        // Match exact requested fields first; Tissue alone is not a diagnosis alias.
        $diagnosisLabel = static fn ($label) => preg_replace('/[\s_|]+/', '', strtolower(trim((string) $label)));
        $diagnosisAliases = ['diagnosis', 'tissuetype', 'tissuediagnosis'];
        if ($colorIndex === null && in_array($diagnosisLabel($colorBy), $diagnosisAliases, true)) {
            foreach ($metadata['attr_list'] ?? [] as $index => $field) {
                if (in_array($diagnosisLabel($field), $diagnosisAliases, true)) {
                    $colorIndex = $index;
                    $colorBy = trim((string) $field);
                    break;
                }
            }
        }
        $colorNote = '';
        if ($colorBy !== '') {
            $colorNote = $colorIndex === null
                ? ' Requested color field is unavailable; points are labeled Unknown.'
                : ' Colored by '.$colorBy.'. Missing metadata is labeled Unknown.';
        }
        $total = count($rows);
        $limit = min($total, 20000);
        $maxBytes = max(4096, min($maxBytes ?? 2000000, 2000000));
        $title = htmlspecialchars((string) ($result['title'] ?? 'Expression PCA'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $axis = static function ($pc) use ($result) {
            $percent = $result['explained_variance_percent'][$pc] ?? null;
            return ['title' => ['text' => $pc.(is_numeric($percent) ? ' ('.round((float) $percent, 2).'%)' : '')]];
        };
        do {
            $trace = ['type' => $type, 'mode' => 'markers', 'x' => [], 'y' => [], 'text' => [],
                'marker' => ['size' => $type === 'scatter3d' ? 4 : 6, 'opacity' => 0.8],
                'hovertemplate' => '%{text}<br>PC1: %{x}<br>PC2: %{y}'.($type === 'scatter3d' ? '<br>PC3: %{z}' : '').'<extra></extra>'];
            if ($type === 'scatter3d') $trace['z'] = [];
            $traces = [];
            for ($i = 0; $i < $limit; $i++) {
                $row = $rows[(int) floor($i * $total / $limit)];
                $group = 'Samples';
                if ($colorBy !== '') {
                    $value = $colorIndex === null ? null : ($metadata['data'][$row['entity_id'] ?? ''][$colorIndex] ?? null);
                    $group = is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : 'Unknown';
                }
                if (!isset($traces[$group])) {
                    $traces[$group] = $trace;
                    $traces[$group]['name'] = htmlspecialchars($group, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    if ($colorBy !== '') {
                        $traces[$group]['marker']['color'] = $group === 'Unknown' ? '#999999' : 'hsl('.(abs(crc32($group)) % 360).',65%,45%)';
                        $traces[$group]['hovertemplate'] = '%{text}<br>'.$traces[$group]['name'].'<br>PC1: %{x}<br>PC2: %{y}'.($type === 'scatter3d' ? '<br>PC3: %{z}' : '').'<extra></extra>';
                    }
                }
                $traces[$group]['x'][] = (float) $row['pc1'];
                $traces[$group]['y'][] = (float) $row['pc2'];
                if ($type === 'scatter3d') $traces[$group]['z'][] = (float) $row['pc3'];
                $traces[$group]['text'][] = htmlspecialchars(mb_substr((string) ($row['entity_id'] ?? ''), 0, 200), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
            $layout = ['title' => ['text' => $title], 'height' => 650, 'showlegend' => $colorBy !== ''];
            if ($colorBy !== '') $layout['legend'] = ['title' => ['text' => htmlspecialchars($colorBy, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')]];
            if ($type === 'scatter3d') $layout['scene'] = ['xaxis' => $axis('PC1'), 'yaxis' => $axis('PC2'), 'zaxis' => $axis('PC3')];
            else $layout += ['xaxis' => $axis('PC1'), 'yaxis' => $axis('PC2')];
            $chart = ['type' => 'plotly', 'title' => $title, 'data' => array_values($traces), 'layout' => $layout,
                'config' => ['responsive' => true, 'displaylogo' => false], 'point_count' => $total,
                'displayed_point_count' => $limit, 'truncated' => $limit < $total,
                'summary' => ($type === 'scatter3d' ? '3D PCA (PC1, PC2, PC3)' : '2D PCA (PC1, PC2)')."; displaying {$limit} of {$total} valid coordinates.".$colorNote.($limit < $total ? ' Points are evenly subsampled for browser size limits.' : '')];
            if (strlen(json_encode($chart, JSON_INVALID_UTF8_SUBSTITUTE)) <= $maxBytes) return $chart;
            $limit = intdiv($limit, 2);
        } while ($limit > 0);
        return null;
    }
}
