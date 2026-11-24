<?php
namespace App\Services\Sql;

use InvalidArgumentException;

class SqlCatalog
{
    public function tables(): array
    {
        $dictionary = json_decode(file_get_contents(base_path('docs/schema/clinomics-data-dictionary.json')), true, flags: JSON_THROW_ON_ERROR);
        $allowed = ['user_projects', 'projects', 'project_samples', 'project_patients', 'samples', 'patients',
            'sample_details', 'patient_details', 'var_pathogenic', 'project_cases', 'var_tier_avia', 'var_fusion', 'project_processed_cases', 'project_values'];
        $variant = (string) config('site.avia_table');
        if (preg_match('/^[a-z][a-z0-9_]*$/i', $variant) && isset($dictionary['tables'][$variant])) $allowed[] = $variant;
        $tables = [];
        foreach ($allowed as $name) {
            if (!isset($dictionary['tables'][$name])) continue;
            $tables[$name] = $dictionary['tables'][$name];
            $tables[$name]['columns'] = array_column($tables[$name]['columns'], null, 'name');
        }
        return $tables;
    }

    public function describe(?string $table, ?string $search = null): array
    {
        $tables = $this->tables();
        if ($table !== null && !isset($tables[$table])) throw new InvalidArgumentException('Table is not in the SQL allowlist.');
        $result = [];
        foreach ($table === null ? $tables : [$table => $tables[$table]] as $name => $definition) {
            $result[$name] = ['description' => $definition['description']];
            if ($table !== null) {
                $columns = $definition['columns'];
                if ($search !== null && $search !== '') $columns = array_filter($columns, fn ($c) => str_contains(strtolower($c['name'].' '.$c['description']), strtolower($search)));
                $result[$name]['columns'] = array_values($columns);
                $result[$name]['indexes'] = $definition['indexes'] ?? [];
            }
        }
        return $result;
    }
}
