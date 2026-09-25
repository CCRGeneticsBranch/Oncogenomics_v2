<?php

namespace App\Services;

final class ExpressionPlotPresenter
{
    private const DEFAULT_MAX_BYTES = 2_000_000;

    private const MAX_GROUPS = 100;

    private const MAX_HEATMAP_GROUPS = 250;

    private const MAX_TOTAL_POINTS = 20_000;

    private const MAX_POINTS_PER_GROUP = 2_000;

    /** A kernel-density violin cannot be estimated from a single value. */
    private const MIN_VIOLIN_GROUP_SIZE = 2;

    /** Large grouped charts need more observations for a stable density. */
    private const MIN_LARGE_VIOLIN_GROUP_SIZE = 5;

    private const LARGE_VIOLIN_GROUP_THRESHOLD = 20;

    /**
     * Convert a successful project or cohort expression result into a
     * browser-safe, size-bounded Plotly chart. Group medians and their ordering
     * always use every valid value; only browser values may be sampled.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>|null
     */
    public function present(array $result, ?int $maxBytes = null, ?string $query = null): ?array
    {
        $action = strtolower(trim((string) ($result['action'] ?? '')));
        if (! in_array($action, ['expression_by_gene', 'getcohortexpression'], true)) {
            return null;
        }

        if ($action === 'getcohortexpression') {
            $result = $this->normalizeCohortExpression($result);
        }
        $result = $this->applyQueryIntent($result, $query);

        $plotType = $this->normalizePlotType($result['plot_type'] ?? null);
        if ($plotType === null) {
            return null;
        }

        $transform = strtolower(trim((string) ($result['transform'] ?? 'none')));
        if (! in_array($transform, ['none', 'log2p1', 'zscore'], true)) {
            return null;
        }

        $groupOrder = strtolower(trim((string) ($result['group_order'] ?? 'none')));
        if (! in_array($groupOrder, ['none', 'median_asc', 'median_desc'], true)) {
            $groupOrder = 'none';
        }

        $rawGroups = $this->rawGroups((array) ($result['plot_rows'] ?? []), $transform);
        if ($rawGroups === []) {
            return null;
        }

        $groups = $this->transformGroups($rawGroups, $transform);
        if ($groups === []) {
            return null;
        }

        $medians = [];
        foreach ($groups as $name => $values) {
            $medians[$name] = $this->median($values);
        }
        $orderedNames = $this->orderedNames($groups, $medians, $groupOrder);
        $excludedViolinNames = [];
        $minimumViolinGroupSize = self::MIN_VIOLIN_GROUP_SIZE;
        if ($plotType === 'violin') {
            if (count($groups) > self::LARGE_VIOLIN_GROUP_THRESHOLD) {
                $minimumViolinGroupSize = self::MIN_LARGE_VIOLIN_GROUP_SIZE;
            }
            $excludedViolinNames = array_values(array_filter(
                $orderedNames,
                static fn (string $name): bool => count($groups[$name]) < $minimumViolinGroupSize,
            ));
            $orderedNames = array_values(array_filter(
                $orderedNames,
                static fn (string $name): bool => count($groups[$name]) >= $minimumViolinGroupSize,
            ));
            if ($orderedNames === []) {
                return null;
            }
        }

        $totalGroupCount = count($groups);
        $totalValueCount = array_sum(array_map('count', $groups));
        $excludedViolinValueCount = array_sum(array_map(
            static fn (string $name): int => count($groups[$name]),
            $excludedViolinNames,
        ));
        $displayedNames = array_slice(
            $orderedNames,
            0,
            $plotType === 'heatmap' ? self::MAX_HEATMAP_GROUPS : self::MAX_GROUPS,
        );

        $maxBytes ??= self::DEFAULT_MAX_BYTES;
        $maxBytes = max(4096, min($maxBytes, self::DEFAULT_MAX_BYTES));
        $pointsPerGroup = $totalValueCount <= self::MAX_TOTAL_POINTS
            ? self::MAX_POINTS_PER_GROUP
            : max(1, min(
                self::MAX_POINTS_PER_GROUP,
                intdiv(self::MAX_TOTAL_POINTS, max(1, count($displayedNames))),
            ));

        do {
            $chart = $this->buildChart(
                $result,
                $plotType,
                $transform,
                $groupOrder,
                $groups,
                $medians,
                $displayedNames,
                $totalGroupCount,
                $totalValueCount,
                $pointsPerGroup,
                count($excludedViolinNames),
                $excludedViolinValueCount,
                $minimumViolinGroupSize,
            );
            if ($this->encodedSize($chart) <= $maxBytes) {
                return $chart;
            }

            if (in_array($plotType, ['violin', 'boxplot', 'heatmap'], true)) {
                if ($pointsPerGroup > 1) {
                    $pointsPerGroup = max(1, intdiv($pointsPerGroup, 2));

                    continue;
                }
            }

            if (count($displayedNames) > 1) {
                $displayedNames = array_slice($displayedNames, 0, max(1, intdiv(count($displayedNames), 2)));

                continue;
            }

            return null;
        } while (true);
    }

    /** @param array<string, mixed> $result @return array<string, mixed> */
    private function normalizeCohortExpression(array $result): array
    {
        $cohort = $this->safeText(
            $result['cohort_name'] ?? $result['cohort_id'] ?? 'Cohort',
            120,
        );
        $cohort = $cohort !== '' ? $cohort : 'Cohort';
        $plotRows = [];
        foreach ((array) ($result['rows'] ?? []) as $row) {
            if (! is_array($row) || ! is_numeric($row['tpm'] ?? null)) {
                continue;
            }

            $plotRows[] = [
                'group' => $cohort,
                'dataset' => $cohort,
                'sample' => $row['sample_id'] ?? '',
                'patient_id' => $row['patient_id'] ?? '',
                'raw_expression' => (float) $row['tpm'],
            ];
        }

        $result['plot_rows'] = $plotRows;
        $result['group_by'] = $result['group_by'] ?? 'Cohort';
        $result['transform'] = $result['transform'] ?? 'none';
        $result['group_order'] = $result['group_order'] ?? 'none';

        return $result;
    }

    /** @param array<string, mixed> $result @return array<string, mixed> */
    public function applyQueryIntent(array $result, ?string $query): array
    {
        $query = trim((string) $query);
        if ($query === '') {
            return $result;
        }

        $plotTypes = [
            'heatmap' => '/\bheat\s*map\b/i',
            'violin' => '/\bviolin(?:e)?(?:\s*plot)?\b/i',
            'boxplot' => '/\bbox\s*plot\b/i',
            'barplot' => '/\bbar(?:\s*(?:plot|chart))\b/i',
            'column' => '/\bcolumn(?:\s*(?:plot|chart))?\b/i',
        ];
        foreach ($plotTypes as $plotType => $pattern) {
            if (preg_match($pattern, $query) === 1) {
                $result['plot_type'] = $plotType;
                break;
            }
        }

        $compactQuery = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '', $query));
        if (str_contains($compactQuery, 'zscore')
            || str_contains($compactQuery, 'standardscore')
            || str_contains($compactQuery, 'standarddeviation')) {
            $result['transform'] = 'zscore';
        } elseif (preg_match('/\blog\s*2\b/i', $query) === 1
            || str_contains($compactQuery, 'log2tpm')
            || str_contains($compactQuery, 'log2rpkm')
            || str_contains($compactQuery, 'log2fpkm')) {
            $result['transform'] = 'log2p1';
        }

        if (preg_match(
            '/\border(?:ed)?\s+by\s+(?:the\s+)?median(?:\s+value)?(?:\s+in)?(?:\s+(descending|desc|highest|ascending|asc|lowest))?/i',
            $query,
            $matches,
        ) === 1) {
            $direction = strtolower((string) ($matches[1] ?? ''));
            $result['group_order'] = in_array($direction, ['descending', 'desc', 'highest'], true)
                ? 'median_desc'
                : 'median_asc';
        } elseif (preg_match('/\b(?:in\s+)?descending\s+order\b/i', $query) === 1) {
            $result['group_order'] = 'median_desc';
        } elseif (preg_match('/\b(?:in\s+)?ascending\s+order\b/i', $query) === 1) {
            $result['group_order'] = 'median_asc';
        }

        foreach ([
            '/\bgroup(?:ed)?\s+by\s+([A-Za-z][A-Za-z0-9_\-]*(?:\s+[A-Za-z][A-Za-z0-9_\-]*)*?)(?=\s+order(?:ed)?\s+by\s+|\s+(?:for|using|from|with|in)\s+|[,.?]|$)/i',
            '/\b(?:box\s*plot|violin(?:e)?(?:\s*plot)?|bar(?:\s*(?:plot|chart))|column(?:\s*(?:plot|chart))?)\s+by\s+([A-Za-z][A-Za-z0-9_\-]*(?:\s+[A-Za-z][A-Za-z0-9_\-]*)*?)(?=\s+order(?:ed)?\s+by\s+|\s+(?:for|using|from|with|in)\s+|[,.?]|$)/i',
        ] as $pattern) {
            if (preg_match($pattern, $query, $matches) === 1) {
                $result['group_by'] = strtolower(trim((string) $matches[1]));
                break;
            }
        }

        return $result;
    }

    private function normalizePlotType(mixed $plotType): ?string
    {
        return match (strtolower(trim((string) $plotType))) {
            'heatmap', 'heat map', 'heat_map' => 'heatmap',
            'violin' => 'violin',
            'box', 'boxplot' => 'boxplot',
            'bar', 'barplot' => 'barplot',
            'column', 'columnplot' => 'column',
            default => null,
        };
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array<string, array<int, float>>
     */
    private function rawGroups(array $rows, string $transform): array
    {
        $groups = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $value = $row['raw_expression'] ?? $row['expression'] ?? null;
            if (! is_numeric($value)) {
                continue;
            }
            $value = (float) $value;
            if (! is_finite($value) || ($transform === 'log2p1' && $value < 0)) {
                continue;
            }

            $groups[$this->rowGroupName($row)][] = $value;
        }

        return array_filter($groups, static fn (array $values): bool => $values !== []);
    }

    /**
     * @param  array<string, array<int, float>>  $rawGroups
     * @return array<string, array<int, float>>
     */
    private function transformGroups(array $rawGroups, string $transform): array
    {
        $allValues = [];
        foreach ($rawGroups as $values) {
            array_push($allValues, ...$values);
        }

        $mean = $allValues === [] ? 0.0 : array_sum($allValues) / count($allValues);
        $standardDeviation = 0.0;
        if ($transform === 'zscore' && $allValues !== []) {
            $sum = 0.0;
            foreach ($allValues as $value) {
                $sum += ($value - $mean) ** 2;
            }
            $standardDeviation = sqrt($sum / count($allValues));
        }

        $groups = [];
        foreach ($rawGroups as $name => $values) {
            foreach ($values as $value) {
                $transformed = match ($transform) {
                    'log2p1' => log($value + 1, 2),
                    'zscore' => $standardDeviation > 0 ? ($value - $mean) / $standardDeviation : 0.0,
                    default => $value,
                };
                if (is_finite($transformed)) {
                    $groups[$name][] = $transformed;
                }
            }
        }

        return array_filter($groups, static fn (array $values): bool => $values !== []);
    }

    /**
     * @param  array<string, array<int, float>>  $groups
     * @param  array<string, float>  $medians
     * @return array<int, string>
     */
    private function orderedNames(array $groups, array $medians, string $groupOrder): array
    {
        $names = array_keys($groups);
        if ($groupOrder === 'none') {
            return $names;
        }

        usort($names, static function (string $left, string $right) use ($medians, $groupOrder): int {
            $comparison = $groupOrder === 'median_desc'
                ? $medians[$right] <=> $medians[$left]
                : $medians[$left] <=> $medians[$right];

            return $comparison !== 0 ? $comparison : strcasecmp($left, $right);
        });

        return $names;
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, array<int, float>>  $groups
     * @param  array<string, float>  $medians
     * @param  array<int, string>  $displayedNames
     * @return array<string, mixed>
     */
    private function buildChart(
        array $result,
        string $plotType,
        string $transform,
        string $groupOrder,
        array $groups,
        array $medians,
        array $displayedNames,
        int $totalGroupCount,
        int $totalValueCount,
        int $pointsPerGroup,
        int $excludedViolinGroupCount,
        int $excludedViolinValueCount,
        int $minimumViolinGroupSize,
    ): array {
        $data = [];
        $displayedValueCount = 0;
        $heatmapColumnCount = 0;
        $heatmapTickValues = [];
        $heatmapTickLabels = [];
        $heatmapBoundaries = [];
        $gene = $this->safeText($result['gene'] ?? 'Gene', 100);
        $gene = $gene !== '' ? $gene : 'Gene';
        $groupLabel = $this->safeText($result['group_by'] ?? '', 100);
        if ($groupLabel === '') {
            $datasets = array_values(array_unique(array_filter(array_map(
                fn (mixed $row): string => is_array($row)
                    ? $this->safeText($row['dataset'] ?? '', 100)
                    : '',
                (array) ($result['plot_rows'] ?? []),
            ))));
            $groupLabel = count($datasets) > 1 ? 'Dataset' : 'Group';
        }
        $valueType = strtolower($this->safeText($result['value_type'] ?? '', 40));
        $baseValueLabel = match ($valueType) {
            'tmm-rpkm' => 'TMM-RPKM',
            'tpm' => 'TPM',
            'rpkm' => 'RPKM',
            'fpkm' => 'FPKM',
            default => 'expression',
        };
        $valueLabel = match ($transform) {
            'log2p1' => 'log2('.$baseValueLabel.' + 1)',
            'zscore' => 'z-score '.$baseValueLabel,
            default => ucfirst($baseValueLabel),
        };

        if ($plotType === 'heatmap') {
            $recordsByGroup = $this->heatmapRecords(
                (array) ($result['plot_rows'] ?? []),
                $transform,
            );
            $values = [];
            $customData = [];
            $displayedValues = [];
            foreach ($displayedNames as $displayedIndex => $name) {
                $records = $this->sampleHeatmapRecords(
                    (array) ($recordsByGroup[$name] ?? []),
                    $pointsPerGroup,
                );
                if ($records === []) {
                    continue;
                }

                $firstPosition = count($values) + 1;
                foreach ($records as $record) {
                    $values[] = $record['value'];
                    $displayedValues[] = $record['value'];
                    $customData[] = [
                        $record['sample'],
                        $record['patient'],
                        $name,
                        $record['dataset'],
                    ];
                }
                $lastPosition = count($values);
                $heatmapTickValues[] = ($firstPosition + $lastPosition) / 2;
                $heatmapTickLabels[] = $name;
                $displayedValueCount += count($records);
                if ($displayedIndex < count($displayedNames) - 1) {
                    $heatmapBoundaries[] = $lastPosition + 0.5;
                }
            }
            $heatmapColumnCount = count($values);

            $colorMinimum = min($displayedValues);
            $colorMaximum = max($displayedValues);
            if ($colorMinimum === $colorMaximum) {
                $colorMinimum -= 0.5;
                $colorMaximum += 0.5;
            }
            if ($transform === 'zscore') {
                $maximumMagnitude = max(abs($colorMinimum), abs($colorMaximum));
                $colorMinimum = -$maximumMagnitude;
                $colorMaximum = $maximumMagnitude;
            }
            $trace = [
                'type' => 'heatmap',
                'x' => range(1, max(1, $heatmapColumnCount)),
                'y' => [$gene],
                'z' => [$values],
                'customdata' => [$customData],
                'zmin' => $colorMinimum,
                'zmax' => $colorMaximum,
                'colorscale' => $transform === 'zscore' ? 'RdBu' : 'Viridis',
                'reversescale' => $transform === 'zscore',
                'colorbar' => ['title' => ['text' => $valueLabel]],
                'hoverongaps' => false,
                'zsmooth' => false,
                'hovertemplate' => '<b>%{customdata[2]}</b><br>Sample: %{customdata[0]}<br>Patient: %{customdata[1]}<br>Dataset: %{customdata[3]}<br>'.$valueLabel.': %{z:.4f}<extra></extra>',
            ];
            if ($transform === 'zscore') {
                $trace['zmid'] = 0;
            }
            $data[] = $trace;
        } elseif ($plotType === 'barplot' || $plotType === 'column') {
            $values = array_map(static fn (string $name): float => $medians[$name], $displayedNames);
            $displayedValueCount = array_sum(array_map(
                static fn (string $name): int => count($groups[$name]),
                $displayedNames,
            ));
            $colors = array_map(fn (string $name): string => $this->color($name, false), $displayedNames);
            $data[] = $plotType === 'barplot'
                ? [
                    'type' => 'bar',
                    'orientation' => 'h',
                    'x' => $values,
                    'y' => $displayedNames,
                    'marker' => ['color' => $colors],
                    'hovertemplate' => '%{y}: %{x:.4f}<extra></extra>',
                ]
                : [
                    'type' => 'bar',
                    'x' => $displayedNames,
                    'y' => $values,
                    'marker' => ['color' => $colors],
                    'hovertemplate' => '%{x}: %{y:.4f}<extra></extra>',
                ];
        } elseif ($plotType === 'violin') {
            // Give each category its own density trace positioned with x0.
            // This avoids Plotly combining categorical x values into one
            // giant density while still using every displayed observation.
            foreach ($displayedNames as $name) {
                $values = $this->sampleValues($groups[$name], $pointsPerGroup);
                $displayedValueCount += count($values);
                $data[] = [
                    'type' => 'violin',
                    'name' => $name,
                    'x0' => $name,
                    'y' => $values,
                    'points' => false,
                    'spanmode' => 'hard',
                    'scalemode' => 'width',
                    'width' => 0.8,
                    'side' => 'both',
                    'box' => ['visible' => false],
                    'meanline' => ['visible' => false],
                    'line' => ['color' => 'rgb(45, 111, 164)', 'width' => 1.5],
                    'fillcolor' => 'rgba(45, 111, 164, 0.48)',
                    'hoveron' => 'violins',
                    'meta' => [$name, count($groups[$name]), $medians[$name]],
                    'hovertemplate' => 'Group: %{meta[0]} · '.$valueLabel.': %{y:.4f} · Samples: %{meta[1]} · Median: %{meta[2]:.4f}',
                ];
            }
        } else {
            foreach ($displayedNames as $name) {
                $values = $this->sampleValues($groups[$name], $pointsPerGroup);
                $displayedValueCount += count($values);
                $trace = [
                    'type' => 'box',
                    'name' => $name,
                    'x' => array_fill(0, count($values), $name),
                    'y' => $values,
                    'line' => ['color' => $this->color($name, false)],
                    'marker' => ['color' => $this->color($name, true)],
                ];
                $trace += ['boxpoints' => 'outliers', 'boxmean' => true];
                $data[] = $trace;
            }
        }

        $displayedGroupCount = count($displayedNames);
        $truncated = $displayedGroupCount < $totalGroupCount
            || (in_array($plotType, ['violin', 'boxplot', 'heatmap'], true)
                && $displayedValueCount < $totalValueCount);
        $title = $gene.' expression by '.$groupLabel;
        $layout = [
            'title' => ['text' => $title],
            'xaxis' => [
                'title' => ['text' => $groupLabel],
                'type' => 'category',
                'categoryorder' => 'array',
                'categoryarray' => $displayedNames,
                'automargin' => true,
                'tickangle' => $displayedGroupCount > 6 ? -45 : 0,
            ],
            'yaxis' => [
                'title' => ['text' => $valueLabel],
                'automargin' => true,
                'zeroline' => false,
            ],
            'showlegend' => false,
            'height' => match ($plotType) {
                'barplot' => max(480, min(1600, 180 + ($displayedGroupCount * 28))),
                'heatmap' => 480,
                default => 680,
            },
            'margin' => ['t' => 60, 'r' => 24, 'b' => 110, 'l' => 80],
            'hovermode' => 'closest',
        ];
        if ($plotType === 'heatmap') {
            $layout['xaxis'] = [
                'title' => ['text' => 'Samples grouped by '.$groupLabel],
                'type' => 'linear',
                'tickmode' => 'array',
                'tickvals' => $heatmapTickValues,
                'ticktext' => $heatmapTickLabels,
                'tickangle' => count($heatmapTickLabels) > 6 ? -45 : 0,
                'automargin' => true,
            ];
            $layout['yaxis'] = [
                'title' => ['text' => 'Gene'],
                'type' => 'category',
                'categoryorder' => 'array',
                'categoryarray' => [$gene],
                'automargin' => true,
            ];
            $layout['shapes'] = array_map(static fn (float $position): array => [
                'type' => 'line',
                'xref' => 'x',
                'yref' => 'paper',
                'x0' => $position,
                'x1' => $position,
                'y0' => 0,
                'y1' => 1,
                'line' => ['color' => 'rgba(255,255,255,0.8)', 'width' => 1],
            ], $heatmapBoundaries);
            $layout['margin'] = ['t' => 60, 'r' => 100, 'b' => 80, 'l' => 180];
        } elseif ($plotType === 'barplot') {
            $layout['xaxis'] = [
                'title' => ['text' => 'Median '.$valueLabel],
                'type' => 'linear',
                'automargin' => true,
            ];
            $layout['yaxis'] = [
                'title' => ['text' => $groupLabel],
                'type' => 'category',
                'categoryorder' => 'array',
                'categoryarray' => array_reverse($displayedNames),
                'automargin' => true,
            ];
        } elseif ($plotType === 'column') {
            $layout['yaxis']['title']['text'] = 'Median '.$valueLabel;
        } elseif ($plotType === 'violin') {
            $layout['violinmode'] = 'group';
            $layout['violingap'] = 0.1;
        } elseif ($plotType === 'boxplot') {
            $layout['boxmode'] = 'group';
            $layout['boxgap'] = 0.2;
        }
        if ($transform === 'log2p1') {
            if ($plotType === 'barplot') {
                $layout['xaxis']['rangemode'] = 'nonnegative';
            } elseif ($plotType !== 'heatmap') {
                $layout['yaxis']['rangemode'] = 'nonnegative';
            }
        }
        $usesFixedWidth = $plotType === 'heatmap'
            ? $heatmapColumnCount > 60
            : $plotType !== 'barplot' && $displayedGroupCount > 8;
        if ($usesFixedWidth) {
            $layout['width'] = $plotType === 'heatmap'
                ? min(20_000, max(1000, 280 + ($heatmapColumnCount * 12)))
                : min(20_000, max(1000, 160 + ($displayedGroupCount * 45)));
        }

        $summary = sprintf(
            '%s plot of %s across %d group%s using %d valid expression value%s (transform: %s; order: %s).',
            ucfirst($plotType),
            $gene,
            $totalGroupCount,
            $totalGroupCount === 1 ? '' : 's',
            $totalValueCount,
            $totalValueCount === 1 ? '' : 's',
            $transform,
            $groupOrder,
        );
        if ($plotType === 'heatmap') {
            $summary .= ' Samples retain their source order within each median-ordered group.';
        }
        if ($plotType === 'violin' && $excludedViolinGroupCount > 0) {
            $summary .= sprintf(
                ' %d group%s containing %d value%s %s omitted because a violin density requires at least %d observations per group.',
                $excludedViolinGroupCount,
                $excludedViolinGroupCount === 1 ? '' : 's',
                $excludedViolinValueCount,
                $excludedViolinValueCount === 1 ? '' : 's',
                $excludedViolinGroupCount === 1 ? 'was' : 'were',
                $minimumViolinGroupSize,
            );
        }
        if ($truncated) {
            $summary .= sprintf(
                ' The displayed chart is bounded to %d of %d group%s and %d of %d value%s; medians and group ordering were computed from all valid values.',
                $displayedGroupCount,
                $totalGroupCount,
                $totalGroupCount === 1 ? '' : 's',
                $displayedValueCount,
                $totalValueCount,
                $totalValueCount === 1 ? '' : 's',
            );
        }

        return [
            'type' => 'plotly',
            'title' => $title,
            'summary' => $summary,
            'data' => $data,
            'layout' => $layout,
            'config' => [
                'responsive' => ! $usesFixedWidth,
                'displaylogo' => false,
                'modeBarButtonsToRemove' => ['lasso2d', 'select2d'],
            ],
            'value_count' => $totalValueCount,
            'group_count' => $totalGroupCount,
            'displayed_value_count' => $displayedValueCount,
            'displayed_group_count' => $displayedGroupCount,
            'excluded_group_count' => $excludedViolinGroupCount,
            'excluded_value_count' => $excludedViolinValueCount,
            'minimum_group_size' => $plotType === 'violin' ? $minimumViolinGroupSize : null,
            'truncated' => $truncated,
        ];
    }

    /** @param array<int, float> $values @return array<int, float> */
    private function sampleValues(array $values, int $limit): array
    {
        sort($values, SORT_NUMERIC);
        $count = count($values);
        if ($count <= $limit) {
            return array_values($values);
        }
        if ($limit <= 1) {
            return [$this->median($values)];
        }

        $sample = [];
        for ($index = 0; $index < $limit; $index++) {
            $sourceIndex = (int) round(($index * ($count - 1)) / ($limit - 1));
            $sample[] = $values[$sourceIndex];
        }

        return $sample;
    }

    /** @param array<string, mixed> $row */
    private function rowGroupName(array $row): string
    {
        $dataset = $this->safeText($row['dataset'] ?? '', 100);
        $metadataField = $this->safeText($row['metadata_field'] ?? '', 100);
        $metadataValue = $this->safeText($row['metadata_value'] ?? '', 100);
        $name = $metadataField === ''
            && strcasecmp($metadataValue, 'All') === 0
            && $dataset !== ''
                ? ucfirst($dataset)
                : $this->safeText($row['group'] ?? 'N/A', 120);

        return $name !== '' ? $name : 'N/A';
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array<string, array<int, array{value: float, sample: string, patient: string, dataset: string}>>
     */
    private function heatmapRecords(array $rows, string $transform): array
    {
        $records = [];
        $allValues = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $value = $row['raw_expression'] ?? $row['expression'] ?? null;
            if (! is_numeric($value)) {
                continue;
            }
            $value = (float) $value;
            if (! is_finite($value) || ($transform === 'log2p1' && $value < 0)) {
                continue;
            }

            $group = $this->rowGroupName($row);
            $records[$group][] = [
                'raw_value' => $value,
                'sample' => $this->safeText($row['sample'] ?? 'N/A', 120) ?: 'N/A',
                'patient' => $this->safeText($row['patient_id'] ?? 'N/A', 120) ?: 'N/A',
                'dataset' => $this->safeText($row['dataset'] ?? 'N/A', 60) ?: 'N/A',
            ];
            $allValues[] = $value;
        }
        if ($allValues === []) {
            return [];
        }

        $mean = array_sum($allValues) / count($allValues);
        $standardDeviation = 0.0;
        if ($transform === 'zscore') {
            $squaredDifferences = 0.0;
            foreach ($allValues as $value) {
                $squaredDifferences += ($value - $mean) ** 2;
            }
            $standardDeviation = sqrt($squaredDifferences / count($allValues));
        }

        foreach ($records as &$groupRecords) {
            foreach ($groupRecords as &$record) {
                $rawValue = $record['raw_value'];
                $record['value'] = match ($transform) {
                    'log2p1' => log($rawValue + 1, 2),
                    'zscore' => $standardDeviation > 0
                        ? ($rawValue - $mean) / $standardDeviation
                        : 0.0,
                    default => $rawValue,
                };
                unset($record['raw_value']);
            }
            unset($record);
        }
        unset($groupRecords);

        return $records;
    }

    /**
     * @param  array<int, array{value: float, sample: string, patient: string, dataset: string}>  $records
     * @return array<int, array{value: float, sample: string, patient: string, dataset: string}>
     */
    private function sampleHeatmapRecords(array $records, int $limit): array
    {
        $count = count($records);
        if ($count <= $limit) {
            return array_values($records);
        }
        if ($limit <= 1) {
            return [$records[intdiv($count, 2)]];
        }

        $sample = [];
        for ($index = 0; $index < $limit; $index++) {
            $sourceIndex = (int) round(($index * ($count - 1)) / ($limit - 1));
            $sample[] = $records[$sourceIndex];
        }

        return $sample;
    }

    /** @param array<int, float> $values */
    private function median(array $values): float
    {
        sort($values, SORT_NUMERIC);
        $count = count($values);
        $middle = intdiv($count, 2);

        return $count % 2 === 0
            ? ($values[$middle - 1] + $values[$middle]) / 2
            : $values[$middle];
    }

    private function safeText(mixed $value, int $limit): string
    {
        $text = strip_tags((string) $value);
        $text = (string) preg_replace('/[\x00-\x1F\x7F<>]/u', ' ', $text);
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return mb_substr($text, 0, $limit);
    }

    private function color(string $name, bool $transparent): string
    {
        $hue = (int) (sprintf('%u', crc32($name)) % 360);

        return $transparent
            ? sprintf('hsla(%d, 68%%, 52%%, 0.45)', $hue)
            : sprintf('hsl(%d, 68%%, 38%%)', $hue);
    }

    /** @param array<string, mixed> $chart */
    private function encodedSize(array $chart): int
    {
        return strlen((string) json_encode(
            $chart,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
        ));
    }
}
