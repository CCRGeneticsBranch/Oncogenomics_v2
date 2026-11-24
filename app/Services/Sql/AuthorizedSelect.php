<?php
namespace App\Services\Sql;

use InvalidArgumentException;

/** Parser and authorization checker for a deliberately small SELECT grammar.
 * No SQL received from the model is executed verbatim: accepted tokens are compiled.
 */
class AuthorizedSelect
{
    private array $tokens = [];
    private int $pos = 0;
    private array $aliases = [];
    private array $refs = [];
    private array $params = [];
    private array $outputs = [];
    private array $edges = [];
    private array $catalog = [];
    private array $predicates = [];
    private const RESERVED = ['select','distinct','from','as','join','inner','on','where','and','or','union','with','group','by','order','having','asc','desc','limit','offset','fetch','null','is','not','in','like','between','left','right','full','cross','outer','into','for','update','delete','insert'];

    public function __construct(private readonly SqlCatalog $schema) {}

    public function compile(string $sql, array $bindings, int $userId, ?string $scope = null, string|int|null $cohort = null): array
    {
        if ($userId < 1 || strlen($sql) > 16000) $this->fail('Invalid user or SQL length.');
        $this->tokens = $this->lex($sql);
        $this->pos = 0;
        $this->aliases = $this->refs = $this->params = $this->outputs = $this->edges = $this->predicates = [];
        $this->catalog = $this->schema->tables();
        $this->take('select');
        $distinct = $this->accept('distinct') ? 'DISTINCT ' : '';
        $select = [];
        do {
            $expr = $this->expression();
            $this->take('as');
            $alias = $this->identifier();
            if (isset($this->outputs[$alias])) $this->fail('Duplicate output alias.');
            $this->outputs[$alias] = true;
            $select[] = $expr.' AS '.$alias;
        } while ($this->accept(','));
        $this->take('from');
        $from = $this->relation();
        while ($this->peek() === 'inner' || $this->peek() === 'join') {
            $this->accept('inner'); $this->take('join');
            $from .= ' INNER JOIN '.$this->relation(); $this->take('on');
            $conditions = [];
            do {
                $a = $this->column(); $this->take('='); $b = $this->column();
                if (explode('.', $a)[0] === explode('.', $b)[0]) $this->fail('Join must connect different aliases.');
                $this->edges[$a.'='.$b] = $this->edges[$b.'='.$a] = true;
                $conditions[] = $a.' = '.$b;
            } while ($this->accept('and'));
            $from .= ' ON '.implode(' AND ', $conditions);
        }
        $this->take('where');
        $where = [];
        do { $where[] = $this->predicate(); } while ($this->accept('and'));
        $tail = '';
        if ($this->accept('group')) {
            $this->take('by'); $group = [];
            do { $group[] = $this->column(); } while ($this->accept(','));
            $tail .= ' GROUP BY '.implode(', ', $group);
        }
        if ($this->accept('having')) {
            $having = [];
            do { $having[] = $this->predicate(); } while ($this->accept('and'));
            $tail .= ' HAVING '.implode(' AND ', $having);
        }
        if ($this->accept('order')) {
            $this->take('by'); $order = [];
            do {
                if (($this->tokens[$this->pos + 1] ?? '') === '.') $item = $this->column();
                else {
                    $item = $this->identifier();
                    if (!isset($this->outputs[$item])) $this->fail('ORDER BY requires an output alias or qualified column.');
                }
                $direction = $this->accept('desc') ? ' DESC' : ($this->accept('asc') ? ' ASC' : '');
                $order[] = $item.$direction;
            } while ($this->accept(','));
            $tail .= ' ORDER BY '.implode(', ', $order);
        }
        if ($this->peek() !== null) $this->fail('Unsupported SQL clause or extra statement.');
        foreach ($this->refs as [$alias,$column]) {
            if (!isset($this->aliases[$alias]) || !isset($this->catalog[$this->aliases[$alias]]['columns'][$column])) $this->fail('Unknown or disallowed table/column.');
        }
        $users = array_keys($this->aliases, 'user_projects', true);
        if (count($users) !== 1) $this->fail('Exactly one user_projects alias is required.');
        $u = $users[0];
        if (!in_array($u.'.user_id = :auth_user_id', $where, true)) $this->fail('WHERE must include user_projects_alias.user_id = :auth_user_id as a mandatory AND condition.');
        foreach ($this->aliases as $alias => $table) {
            if ($table === 'user_projects') continue;
            if (in_array($table, ['project_samples','project_patients','project_processed_cases','project_values','project_cases'], true)) {
                if (!$this->edge($alias.'.project_id', $u.'.project_id')) $this->fail('Every project membership must join directly to user_projects.project_id.');
            } elseif ($table === 'var_pathogenic') {
                $authorized = false;
                foreach (array_keys($this->aliases, 'project_cases', true) as $parent) {
                    $authorized = $authorized || ($this->edge($alias.'.patient_id', $parent.'.patient_id')
                        && $this->edge($alias.'.case_id', $parent.'.case_id'));
                }
                if (!$authorized) $this->fail('var_pathogenic requires BOTH patient_id and case_id joined to the same authorized project_cases alias.');
            } elseif ($table === 'projects') {
                if (!$this->edge($alias.'.id', $u.'.project_id')) $this->fail('projects.id must join user_projects.project_id.');
            } else {
                $patientOnly = in_array($table, ['patients','patient_details'], true);
                $parents = array_keys($this->aliases, $patientOnly ? 'project_patients' : 'project_samples', true);
                $keys = $patientOnly ? ['patient_id'] : ($table === 'sample_details' ? ['sample_id'] : ['patient_id','sample_id']);
                $authorized = false;
                foreach ($parents as $parent) {
                    $matches = true;
                    foreach ($keys as $key) $matches = $matches && $this->edge($alias.'.'.$key, $parent.'.'.$key);
                    $authorized = $authorized || $matches;
                }
                if (!$authorized) $this->fail('Protected source lacks its complete authorized patient/sample membership join.');
            }
        }
        if ($scope === 'project') {
            if (!ctype_digit((string) $cohort) || (int) $cohort < 1) $this->fail('Invalid fixed project.');
            $where[] = $u.'.project_id = :scope_project_id';
        } elseif ($scope === 'cancer_type') {
            if (!is_string($cohort) || trim($cohort) === '') $this->fail('Invalid fixed diagnosis.');
            if (array_intersect(array_values($this->aliases), ['project_values','project_processed_cases','project_cases'])) $this->fail('Expression vectors and case memberships are not supported in fixed diagnosis SQL.');
            $members = array_filter($this->aliases, fn ($t) => in_array($t, ['project_samples','project_patients'], true));
            if ($members === []) $this->fail('Diagnosis scope requires patient/sample membership.');
            foreach ($members as $alias => $table) $where[] = $alias.'.diagnosis = :scope_diagnosis';
        } elseif ($scope !== null && $scope !== 'global') $this->fail('Invalid scope.');
        foreach ($bindings as $key => $value) {
            if (!preg_match('/^p[1-9][0-9]*$/', (string) $key) || (!is_scalar($value) && $value !== null) || (is_string($value) && strlen($value) > 2000) || (is_float($value) && !is_finite($value))) $this->fail('Bindings must be scalar p1, p2, ... values; user/scope bindings are server-owned.');
        }
        $required = array_values(array_diff(array_unique($this->params), ['auth_user_id']));
        if (array_diff($required,array_keys($bindings)) || array_diff(array_keys($bindings),$required)) $this->fail('Bindings must exactly match query parameters.');
        $this->validateNumericPredicates($bindings);
        $bindings['auth_user_id'] = $userId;
        if ($scope === 'project') $bindings['scope_project_id'] = (int) $cohort;
        if ($scope === 'cancer_type') $bindings['scope_diagnosis'] = $cohort;
        return ['sql' => 'SELECT '.$distinct.implode(', ', $select).' FROM '.$from.' WHERE '.implode(' AND ', $where).$tail,
            'bindings' => $bindings, 'columns' => array_keys($this->outputs)];
    }

    private function lex(string $sql): array
    {
        $tokens = []; $offset = 0;
        while ($offset < strlen($sql)) {
            if (!preg_match('/\G(?:\s+|:[a-zA-Z_][a-zA-Z0-9_]*|[a-zA-Z_][a-zA-Z0-9_]*|<>|!=|<=|>=|[=<>(),.*])/A', $sql, $m, 0, $offset)) $this->fail('Only restricted SELECT syntax and bound values are accepted. Quotes, comments, numeric literals and semicolons are not allowed.');
            $offset += strlen($m[0]);
            if (trim($m[0]) !== '') $tokens[] = strtolower($m[0]);
            if (count($tokens) > 2000) $this->fail('Too many SQL tokens.');
        }
        return $tokens;
    }
    private function relation(): string
    {
        $table = $this->identifier();
        if (!isset($this->catalog[$table])) $this->fail('Table is not allowed.');
        $this->accept('as'); $alias = $this->identifier();
        if (isset($this->aliases[$alias]) || count($this->aliases) >= 8) $this->fail('Duplicate alias or too many joins.');
        $this->aliases[$alias] = $table;
        return $table.' '.$alias;
    }
    private function expression(): string
    {
        if (($this->tokens[$this->pos + 1] ?? null) !== '(') return $this->column();
        $function = $this->identifier();
        if (!in_array($function,['count','sum','avg','min','max','upper','lower','safe_number'],true)) $this->fail('Function is not allowed.');
        $this->take('(');
        $distinct = $this->accept('distinct');
        if ($distinct && $function !== 'count') $this->fail('Only COUNT supports DISTINCT.');
        if ($this->accept('*')) {
            if ($function !== 'count' || $distinct) $this->fail('Only COUNT(*) may use wildcard.');
            $arg = '*';
        } elseif (in_array($function, ['count','sum','avg','min','max'], true) && $this->peek() === 'safe_number') {
            $arg = $this->expression();
        } else $arg = $this->column();
        $this->take(')');
        return strtoupper($function).'('.($distinct ? 'DISTINCT ' : '').$arg.')';
    }
    private function column(): string
    {
        $alias = $this->identifier(); $this->take('.'); $column = $this->identifier();
        $this->refs[] = [$alias,$column];
        return $alias.'.'.$column;
    }
    private function predicate(): string
    {
        $left = $this->expression();
        if ($this->accept('is')) return $left.' IS '.($this->accept('not') ? 'NOT ' : '').$this->take('null');
        if ($this->accept('in')) {
            $this->take('('); $params = [];
            do {
                $parameter = $this->parameter();
                $this->predicates[] = [$left, 'in', substr($parameter, 1)];
                $params[] = $this->numericExpression($left) ? 'SAFE_NUMBER('.$parameter.')' : $parameter;
            } while ($this->accept(','));
            $this->take(')'); return $left.' IN ('.implode(', ', $params).')';
        }
        $op = $this->peek();
        if (!in_array($op,['=','<>','!=','<','>','<=','>=','like'],true)) $this->fail('Unsupported predicate. Only AND-connected comparisons, IN, LIKE and IS NULL are supported.');
        $this->pos++;
        $parameter = $this->parameter();
        $this->predicates[] = [$left, $op, substr($parameter, 1)];
        if ($this->numericExpression($left)) {
            if ($op === 'like') $this->fail('LIKE cannot filter numeric values. Use a numeric comparison.');
            $parameter = 'SAFE_NUMBER('.$parameter.')';
        }
        return $left.' '.strtoupper($op).' '.$parameter;
    }
    private function numericExpression(string $expression): bool
    {
        return preg_match('/^(SAFE_NUMBER|COUNT|SUM|AVG)\(/', $expression) === 1
            || preg_match('/^(MIN|MAX)\(SAFE_NUMBER\(/', $expression) === 1;
    }

    private function validateNumericPredicates(array $bindings): void
    {
        foreach ($this->predicates as [$left, $op, $parameter]) {
            if ($this->numericExpression($left) && !NumericSqlExpression::isDecimal($bindings[$parameter] ?? null)) {
                $this->fail('SAFE_NUMBER comparisons require a decimal numeric parameter.');
            }
            foreach ($this->aliases as $alias => $table) {
                if (!in_array($table, ['patient_details','sample_details'], true)) continue;
                if ($left !== $alias.'.attr_value') continue;
                if (in_array($op, ['<','>','<=','>='], true)) {
                    $this->fail('attr_value is stored as text. Use SAFE_NUMBER('.$left.') for numeric comparisons; never substitute LIKE for a numeric threshold.');
                }
                foreach ($this->predicates as [$typeColumn, $typeOp, $typeParameter]) {
                    if ($typeColumn === $alias.'.type' && $typeOp === '=' && ($bindings[$typeParameter] ?? null) === 'number') {
                        $this->fail('Numeric metadata requires SAFE_NUMBER('.$left.') for comparisons, not text comparisons or LIKE.');
                    }
                }
            }
        }
    }

    private function parameter(): string
    {
        $token = $this->peek();
        if (!is_string($token) || !preg_match('/^:(auth_user_id|p[1-9][0-9]*)$/',$token)) $this->fail('Use :p1, :p2, ... or the mandatory :auth_user_id placeholder.');
        $this->pos++; $this->params[] = substr($token,1); return $token;
    }
    private function identifier(): string
    {
        $token = $this->peek();
        if (!is_string($token) || !preg_match('/^[a-z][a-z0-9_]*$/',$token) || in_array($token,self::RESERVED,true) || strlen($token)>64) $this->fail('Expected unquoted identifier (aliases are mandatory).');
        $this->pos++; return $token;
    }
    private function edge(string $a,string $b): bool { return isset($this->edges[$a.'='.$b]); }
    private function peek(): ?string { return $this->tokens[$this->pos] ?? null; }
    private function accept(string $token): bool { if ($this->peek() !== $token) return false; $this->pos++; return true; }
    private function take(string $token): string { if (!$this->accept($token)) $this->fail('Expected '.$token.'.'); return strtoupper($token); }
    private function fail(string $message): never { throw new InvalidArgumentException($message); }
}
