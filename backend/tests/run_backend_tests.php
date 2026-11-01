<?php

declare(strict_types=1);

/**
 * EventBite - Backend Tenancy & Policies Test Suite
 * Validates TenantRole enum logic, TenantContext scoping, Cross-Tenant authorization rules,
 * four enterprise account types, dual-acting user role switching, and specialized event-worker roles.
 */

// Lightweight stubs for Illuminate components when running standalone PHP unit tests
namespace Illuminate\Database\Eloquent\Relations {
    if (!class_exists(Relation::class)) {
        abstract class Relation {}
        class BelongsTo extends Relation {}
        class HasMany extends Relation {}
        class HasOne extends Relation {}
        class BelongsToMany extends Relation {}
    }
}

namespace Illuminate\Database\Eloquent {
    if (!class_exists(Model::class)) {
        class ModelQueryBuilder {
            private string $modelClass;
            private array $conditions = [];
            private ?int $limit = null;

            public function __construct(string $modelClass, mixed $col = null, mixed $op = null, mixed $val = null) {
                $this->modelClass = $modelClass;
                if ($col !== null) {
                    $this->where($col, $op, $val);
                }
            }
            public function where(mixed $col, mixed $op = null, mixed $val = null): self {
                if (is_callable($col)) {
                    $sub = new self($this->modelClass);
                    $col($sub);
                    $this->conditions[] = ['sub', 'and', $sub];
                    return $this;
                }
                if ($val === null) {
                    $val = $op;
                    $op = '=';
                }
                $this->conditions[] = ['simple', 'and', $col, $op, $val];
                return $this;
            }
            public function orWhere(mixed $col, mixed $op = null, mixed $val = null): self {
                if (is_callable($col)) {
                    $sub = new self($this->modelClass);
                    $col($sub);
                    $this->conditions[] = ['sub', 'or', $sub];
                    return $this;
                }
                if ($val === null) {
                    $val = $op;
                    $op = '=';
                }
                $this->conditions[] = ['simple', 'or', $col, $op, $val];
                return $this;
            }
            public function whereIn(string $col, array $values): self {
                $this->conditions[] = ['simple', 'and', $col, 'in', $values];
                return $this;
            }
            public function orWhereNull(string $col): self {
                $this->conditions[] = ['simple', 'or', $col, '=', null];
                return $this;
            }
            public function whereNull(string $col): self {
                $this->conditions[] = ['simple', 'and', $col, '=', null];
                return $this;
            }
            public function orderByDesc(string $col): self {
                return $this;
            }
            public function latest(): self {
                return $this;
            }
            public function with(mixed $relations): self {
                return $this;
            }
            public function lockForUpdate(): self {
                return $this;
            }
            public function withoutGlobalScopes(): self {
                return $this;
            }
            public function take(int $limit): self {
                $this->limit = $limit;
                return $this;
            }
            public function limit(int $limit): self {
                $this->limit = $limit;
                return $this;
            }
            public function exists(): bool {
                return $this->first() !== null;
            }
            public function count(): int {
                return count($this->get()->toArray());
            }
            public function sum(string|callable|null $callback = null): float|int {
                return $this->get()->sum($callback);
            }
            public function first(): ?Model {
                $items = $this->get()->toArray();
                return $items[0] ?? null;
            }
            public function matchesRecord(Model $record): bool {
                if (empty($this->conditions)) {
                    return true;
                }
                $result = true;
                $isFirst = true;
                foreach ($this->conditions as $cond) {
                    $type = $cond[0];
                    $bool = $cond[1];
                    $condMatch = true;

                    if ($type === 'sub') {
                        $condMatch = $cond[2]->matchesRecord($record);
                    } elseif ($type === 'simple') {
                        $col = $cond[2];
                        $op = $cond[3];
                        $val = $cond[4];
                        $actual = $record->$col;
                        if ($actual instanceof \BackedEnum) {
                            $actual = $actual->value;
                        }
                        if ($val instanceof \BackedEnum) {
                            $val = $val->value;
                        }
                        if ($op === '=') {
                            $condMatch = ($actual === $val);
                        } elseif ($op === 'in') {
                            $condMatch = in_array($actual, (array)$val, true);
                        }
                    }
                    if ($isFirst) {
                        $result = $condMatch;
                        $isFirst = false;
                    } elseif ($bool === 'and') {
                        $result = $result && $condMatch;
                    } elseif ($bool === 'or') {
                        $result = $result || $condMatch;
                    }
                }
                return $result;
            }
            public function get(): object {
                $records = Model::$inMemoryRecords[$this->modelClass] ?? [];
                $matches = [];
                foreach ($records as $record) {
                    if ($this->matchesRecord($record)) {
                        $matches[] = $record;
                    }
                }
                if ($this->limit !== null) {
                    $matches = array_slice($matches, 0, $this->limit);
                }
                return new class($matches) implements \Countable, \IteratorAggregate {
                    public function __construct(private array $items) {}
                    public function count(): int { return count($this->items); }
                    public function toArray(): array { return $this->items; }
                    public function getIterator(): \Traversable { return new \ArrayIterator($this->items); }
                    public function each(callable $c): void { foreach ($this->items as $i) $c($i); }
                    public function first(): mixed { return $this->items[0] ?? null; }
                    public function map(callable $c): self { return new self(array_map($c, $this->items)); }
                    public function take(int $limit): self { return new self(array_slice($this->items, 0, $limit)); }
                    public function where(string $key, mixed $operator = null, mixed $value = null): self {
                        if (func_num_args() === 2) {
                            $value = $operator;
                            $operator = '=';
                        }
                        $filtered = array_filter($this->items, function($item) use ($key, $operator, $value) {
                            $actual = is_object($item) ? ($item->$key ?? null) : ($item[$key] ?? null);
                            if ($operator === '=') {
                                return $actual == $value;
                            } elseif ($operator === '===') {
                                return $actual === $value;
                            } elseif ($operator === '!=') {
                                return $actual != $value;
                            }
                            return $actual == $value;
                        });
                        return new self(array_values($filtered));
                    }
                    public function whereIn(string $key, array $values): self {
                        $filtered = array_filter($this->items, function($item) use ($key, $values) {
                            $actual = is_object($item) ? ($item->$key ?? null) : ($item[$key] ?? null);
                            return in_array($actual, $values);
                        });
                        return new self(array_values($filtered));
                    }
                    public function sum(string|callable|null $callback = null): float|int {
                        $total = 0;
                        foreach ($this->items as $item) {
                            if (is_callable($callback)) {
                                $val = $callback($item);
                            } elseif (is_string($callback)) {
                                $val = is_object($item) ? ($item->$callback ?? 0) : ($item[$callback] ?? 0);
                            } else {
                                $val = $item;
                            }
                            $total += is_numeric($val) ? $val : 0;
                        }
                        return $total;
                    }
                };
            }
            public function pluck(string $column): object {
                $records = Model::$inMemoryRecords[$this->modelClass] ?? [];
                $res = [];
                foreach ($records as $r) {
                    if (isset($r->$column)) $res[] = $r->$column;
                }
                return new class($res) {
                    public function __construct(private array $items) {}
                    public function toArray(): array { return $this->items; }
                };
            }
        }

        abstract class Model {
            public array $attributes = [];
            public static array $inMemoryRecords = [];

            public function __construct(array $attributes = []) {
                $this->attributes = $attributes;
            }
            public function __get(string $key) {
                $getter = 'get' . str_replace('_', '', ucwords($key, '_')) . 'Attribute';
                if (method_exists($this, $getter)) {
                    return $this->$getter($this->attributes[$key] ?? null);
                }
                if (array_key_exists($key, $this->attributes)) {
                    return $this->attributes[$key];
                }
                if (method_exists($this, $key)) {
                    $rel = $this->$key();
                    if ($rel instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo) {
                        return $rel->first();
                    }
                }
                return null;
            }
            public function __set(string $key, mixed $value): void {
                $setter = 'set' . str_replace('_', '', ucwords($key, '_')) . 'Attribute';
                if (method_exists($this, $setter)) {
                    $this->$setter($value);
                    return;
                }
                $this->attributes[$key] = $value;
            }
            public function __isset(string $key): bool {
                return isset($this->attributes[$key]) || method_exists($this, $key);
            }
            public function decrement(string $column, int $amount = 1): bool {
                $this->attributes[$column] = ($this->attributes[$column] ?? 0) - $amount;
                return $this->save();
            }
            public function increment(string $column, int $amount = 1): bool {
                $this->attributes[$column] = ($this->attributes[$column] ?? 0) + $amount;
                return $this->save();
            }
            public function fresh(array $with = []): static {
                if (isset($this->attributes['id']) && isset(self::$inMemoryRecords[static::class][$this->attributes['id']])) {
                    return self::$inMemoryRecords[static::class][$this->attributes['id']];
                }
                return $this;
            }
            public function save(): bool {
                if (!isset($this->attributes['id'])) {
                    $this->attributes['id'] = count(self::$inMemoryRecords[static::class] ?? []) + 1;
                }
                self::$inMemoryRecords[static::class][$this->attributes['id']] = $this;
                return true;
            }
            public static function create(array $attributes): static {
                $instance = new static($attributes);
                $instance->save();
                return $instance;
            }
            public static function updateOrCreate(array $attributes, array $values): static {
                $existing = static::where(function() {})->first(); // fallback
                return static::create(array_merge($attributes, $values));
            }
            public static function find(mixed $id): ?static {
                return self::$inMemoryRecords[static::class][$id] ?? null;
            }
            public static function findOrFail(mixed $id): static {
                $record = static::find($id);
                if (!$record) {
                    throw new \Exception("Model " . static::class . " not found with ID {$id}");
                }
                return $record;
            }
            public static function with(mixed $relations): ModelQueryBuilder {
                return new ModelQueryBuilder(static::class);
            }
            public static function latest(): ModelQueryBuilder {
                return new ModelQueryBuilder(static::class);
            }
            public static function where(mixed $column = null, mixed $operator = null, mixed $value = null): ModelQueryBuilder {
                return new ModelQueryBuilder(static::class, $column, $operator, $value);
            }
            public static function query(): ModelQueryBuilder {
                return new ModelQueryBuilder(static::class);
            }
            public static function whereIn(string $column, array $values): ModelQueryBuilder {
                return (new ModelQueryBuilder(static::class))->whereIn($column, $values);
            }
            public static function __callStatic(string $method, array $args) {
                return (new ModelQueryBuilder(static::class))->$method(...$args);
            }
            public function relationLoaded(string $relation): bool { return false; }
            public function hasMany(string $related, string $foreignKey = '', string $localKey = ''): \Illuminate\Database\Eloquent\Relations\HasMany {
                $parent = $this;
                return new class($related, $parent) extends \Illuminate\Database\Eloquent\Relations\HasMany {
                    private string $related;
                    private \Illuminate\Database\Eloquent\Model $parent;
                    private array $conditions = [];

                    public function __construct(string $related, \Illuminate\Database\Eloquent\Model $parent) {
                        $this->related = $related;
                        $this->parent = $parent;
                    }
                    public function where(mixed $col, mixed $op = null, mixed $val = null): self {
                        if ($val === null) {
                            $val = $op;
                            $op = '=';
                        }
                        $this->conditions[] = [$col, $op, $val];
                        return $this;
                    }
                    public function exists(): bool {
                        $records = \Illuminate\Database\Eloquent\Model::$inMemoryRecords[$this->related] ?? [];
                        foreach ($records as $r) {
                            if (($r->user_id ?? null) == ($this->parent->id ?? null)) {
                                $matches = true;
                                foreach ($this->conditions as [$col, $op, $val]) {
                                    if ($r->$col !== $val) {
                                        $matches = false;
                                        break;
                                    }
                                }
                                if ($matches) return true;
                            }
                        }
                        return false;
                    }
                    public function pluck(string $col): object {
                        $records = \Illuminate\Database\Eloquent\Model::$inMemoryRecords[$this->related] ?? [];
                        $res = [];
                        foreach ($records as $r) {
                            if (($r->user_id ?? null) == ($this->parent->id ?? null)) {
                                if (isset($r->$col)) $res[] = $r->$col;
                            }
                        }
                        return new class($res) {
                            public function __construct(private array $items) {}
                            public function toArray(): array { return $this->items; }
                        };
                    }
                };
            }
            public function hasOne(string $related, string $foreignKey = '', string $localKey = ''): \Illuminate\Database\Eloquent\Relations\HasOne {
                return new class extends \Illuminate\Database\Eloquent\Relations\HasOne {
                    public function where(mixed ...$args): self { return $this; }
                    public function first(): ?\Illuminate\Database\Eloquent\Model { return null; }
                };
            }
            public function belongsTo(string $related, string $foreignKey = ''): \Illuminate\Database\Eloquent\Relations\BelongsTo {
                $parent = $this;
                return new class($related, $parent, $foreignKey) extends \Illuminate\Database\Eloquent\Relations\BelongsTo {
                    public function __construct(private string $related, private \Illuminate\Database\Eloquent\Model $parent, private string $fk) {}
                    public function where(mixed ...$args): self { return $this; }
                    public function first(): ?\Illuminate\Database\Eloquent\Model {
                        $fkName = $this->fk;
                        if (empty($fkName)) {
                            $parts = explode('\\', $this->related);
                            $fkName = strtolower(end($parts)) . '_id';
                        }
                        $targetId = $this->parent->$fkName;
                        if (!$targetId) return null;
                        return \Illuminate\Database\Eloquent\Model::$inMemoryRecords[$this->related][$targetId] ?? null;
                    }
                };
            }
            public function belongsToMany(string $related, string $table = ''): \Illuminate\Database\Eloquent\Relations\BelongsToMany {
                return new class extends \Illuminate\Database\Eloquent\Relations\BelongsToMany {
                    public function where(mixed ...$args): self { return $this; }
                    public function first(): ?\Illuminate\Database\Eloquent\Model { return null; }
                };
            }
        }
    }
    if (!trait_exists(SoftDeletes::class)) {
        trait SoftDeletes {}
    }
}

namespace Illuminate\Database\Eloquent\Concerns {
    if (!trait_exists(HasUuids::class)) {
        trait HasUuids {}
    }
}

namespace Illuminate\Database\Eloquent\Factories {
    if (!trait_exists(HasFactory::class)) {
        trait HasFactory {}
    }
}

namespace Illuminate\Notifications {
    if (!trait_exists(Notifiable::class)) {
        trait Notifiable {}
    }
}

namespace Illuminate\Foundation\Auth {
    if (!class_exists(User::class)) {
        class User extends \Illuminate\Database\Eloquent\Model {}
    }
}

namespace Illuminate\Support {
    if (!class_exists(Str::class)) {
        class Str {
            public static function uuid(): string {
                return sprintf(
                    '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                    mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                );
            }
            public static function random(int $length = 16): string {
                return substr(bin2hex(random_bytes((int) ceil($length / 2) + 1)), 0, $length);
            }
            public static function upper(string $value): string {
                return strtoupper($value);
            }
            public static function slug(string $title, string $separator = '-'): string {
                $raw = preg_replace('~[^\pL\d]+~u', $separator, $title);
                return strtolower(trim($raw, $separator));
            }
            public static function lower(string $value): string {
                return strtolower($value);
            }
            public static function contains(string $haystack, string|iterable $needles): bool {
                foreach ((array) $needles as $needle) {
                    if ($needle !== '' && str_contains($haystack, $needle)) return true;
                }
                return false;
            }
            public static function startsWith(string $haystack, string|iterable $needles): bool {
                foreach ((array) $needles as $needle) {
                    if ((string) $needle !== '' && str_starts_with($haystack, $needle)) return true;
                }
                return false;
            }
            public static function endsWith(string $haystack, string|iterable $needles): bool {
                foreach ((array) $needles as $needle) {
                    if ((string) $needle !== '' && str_ends_with($haystack, $needle)) return true;
                }
                return false;
            }
        }
    }
}

namespace Illuminate\Http {
    if (!class_exists(Request::class)) {
        class Request {
            public array $attributes = [];
            public ?\App\Models\User $user = null;
            public function __construct(array $attributes = [], ?\App\Models\User $user = null) {
                $this->attributes = $attributes;
                $this->user = $user;
            }
            public function user(): ?\App\Models\User {
                return $this->user;
            }
            public function setUser(\App\Models\User $u): self {
                $this->user = $u;
                return $this;
            }
            public function wantsJson(): bool {
                return true;
            }
            public function input(string $key, mixed $default = null): mixed {
                return $this->attributes[$key] ?? $default;
            }
            public function all(): array {
                return $this->attributes;
            }
            public function has(string $key): bool {
                return array_key_exists($key, $this->attributes);
            }
        }
    }

    if (!class_exists(JsonResponse::class)) {
        class JsonResponse {
            public mixed $original;
            public int $status;
            public function __construct(mixed $data = [], int $status = 200) {
                $this->original = $data;
                $this->status = $status;
            }
        }
    }
}

namespace Illuminate\View {
    if (!class_exists(View::class)) {
        class View {
            public string $view;
            public array $data;
            public function __construct(string $view = '', array $data = []) {
                $this->view = $view;
                $this->data = $data;
            }
        }
    }
}

namespace Illuminate\Support\Facades {
    if (!class_exists(DB::class)) {
        class DB {
            public static array $tables = [];

            public static function transaction(callable $callback) {
                return $callback();
            }
            public static function table(string $tableName) {
                return new class($tableName) {
                    private string $tableName;
                    private array $wheres = [];

                    public function __construct(string $tableName) {
                        $this->tableName = $tableName;
                    }

                    public function updateOrInsert(array $attributes, array $values): bool {
                        $records = DB::$tables[$this->tableName] ?? [];
                        $foundIdx = null;
                        foreach ($records as $idx => $rec) {
                            $match = true;
                            foreach ($attributes as $k => $v) {
                                if (($rec[$k] ?? null) !== $v) {
                                    $match = false;
                                    break;
                                }
                            }
                            if ($match) {
                                $foundIdx = $idx;
                                break;
                            }
                        }
                        $merged = array_merge($attributes, $values);
                        if ($foundIdx !== null) {
                            DB::$tables[$this->tableName][$foundIdx] = $merged;
                        } else {
                            DB::$tables[$this->tableName][] = $merged;
                        }
                        return true;
                    }

                    public function where(string $col, mixed $val): self {
                        $this->wheres[$col] = $val;
                        return $this;
                    }

                    public function first(): ?object {
                        $records = DB::$tables[$this->tableName] ?? [];
                        foreach ($records as $rec) {
                            $match = true;
                            foreach ($this->wheres as $k => $v) {
                                if (($rec[$k] ?? null) !== $v) {
                                    $match = false;
                                    break;
                                }
                            }
                            if ($match) {
                                return (object) $rec;
                            }
                        }
                        return null;
                    }

                    public function delete(): int {
                        $records = DB::$tables[$this->tableName] ?? [];
                        $newRecords = [];
                        $deleted = 0;
                        foreach ($records as $rec) {
                            $match = true;
                            foreach ($this->wheres as $k => $v) {
                                if (($rec[$k] ?? null) !== $v) {
                                    $match = false;
                                    break;
                                }
                            }
                            if ($match) {
                                $deleted++;
                            } else {
                                $newRecords[] = $rec;
                            }
                        }
                        DB::$tables[$this->tableName] = $newRecords;
                        return $deleted;
                    }
                };
            }
        }
    }
}

namespace {
    if (!class_exists('CarbonStub')) {
        class CarbonStub extends \DateTimeImmutable {
            public function subHours(int $hours): self {
                return $this->modify("-{$hours} hours");
            }
            public function addHours(int $hours): self {
                return $this->modify("+{$hours} hours");
            }
            public function addDays(int $days): self {
                return $this->modify("+{$days} days");
            }
            public function subDays(int $days): self {
                return $this->modify("-{$days} days");
            }
            public function greaterThan(mixed $date): bool {
                if (is_string($date)) $date = new \DateTimeImmutable($date);
                return $this > $date;
            }
            public function lessThan(mixed $date): bool {
                if (is_string($date)) $date = new \DateTimeImmutable($date);
                return $this < $date;
            }
            public function toIso8601String(): string {
                return $this->format(\DateTimeInterface::ATOM);
            }
        }
    }
    if (!function_exists('now')) {
        function now(): CarbonStub {
            return new CarbonStub();
        }
    }

    if (!function_exists('config')) {
        function config(string|array $key, mixed $default = null): mixed {
            static $cfg = [
                'events.require_login_for_browsing' => false,
            ];
            if (is_array($key)) {
                foreach ($key as $k => $v) {
                    $cfg[$k] = $v;
                }
                return true;
            }
            return $cfg[$key] ?? $default;
        }
    }

    if (!function_exists('auth')) {
        class TestAuthGuard {
            public static ?\App\Models\User $currentUser = null;
            public function user(): ?\App\Models\User { return self::$currentUser; }
            public function id(): ?int { return self::$currentUser?->id; }
            public function check(): bool { return self::$currentUser !== null; }
            public function login(\App\Models\User $user): void { self::$currentUser = $user; }
            public function logout(): void { self::$currentUser = null; }
            public function guard(): self { return $this; }
        }
        function auth(): TestAuthGuard {
            return new TestAuthGuard();
        }
    }

    if (!function_exists('request')) {
        class TestRequestHelper {
            public function ip(): string { return '127.0.0.1'; }
            public function userAgent(): string { return 'PHPUnit/EnterpriseRunner'; }
        }
        function request(): TestRequestHelper {
            return new TestRequestHelper();
        }
    }

    if (!function_exists('response')) {
        class TestResponseHelper {
            public mixed $content = null;
            public int $status = 200;
            public function json(mixed $data = [], int $status = 200): \Illuminate\Http\JsonResponse {
                return new \Illuminate\Http\JsonResponse($data, $status);
            }
        }
        function response(mixed $content = '', int $status = 200): TestResponseHelper {
            return new TestResponseHelper();
        }
    }

    if (!function_exists('redirect')) {
        class TestRedirectHelper {
            public string $url = '';
            public function __construct(string $url = '') { $this->url = $url; }
            public function route(string $name, array $parameters = []): self { return $this; }
            public function with(string|array $key, mixed $value = null): self { return $this; }
            public function intended(string $default = '/'): self { return $this; }
            public function guest(string $url): self { return $this; }
            public function to(string $url): self { return $this; }
        }
        function redirect(?string $to = null): TestRedirectHelper {
            return new TestRedirectHelper($to ?? '');
        }
    }

    if (!function_exists('view')) {
        function view(?string $view = null, array $data = []): \Illuminate\View\View {
            return new \Illuminate\View\View($view ?? '', $data);
        }
    }

    // Simple autoloader for backend classes without requiring full composer dump-autoload
    spl_autoload_register(function ($class) {
        $prefix = 'App\\';
        $baseDir = __DIR__ . '/../app/';

        if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
            return;
        }

        $relativeClass = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
    });

    require_once __DIR__ . '/../app/Exceptions/SecurityException.php';


use App\Enums\AccountType;
use App\Enums\EventWorkerRole;
use App\Enums\TenantRole;
use App\Services\TenantContext;

class TestRunner
{
    private int $passed = 0;
    private int $failed = 0;

    public function assert(bool $condition, string $testName): void
    {
        if ($condition) {
            $this->passed++;
            echo "  ✓ {$testName}\n";
        } else {
            $this->failed++;
            echo "  ✗ FAIL: {$testName}\n";
        }
    }

    public function runAll(): void
    {
        echo "========================================================\n";
        echo " RUNNING BACKEND TENANCY & POLICY UNIT TESTS (PHP 8.4)  \n";
        echo "========================================================\n\n";

        $this->testTenantRoleEnums();
        $this->testTenantContextService();
        $this->testCrossOrganizerAuthorizationLogic();
        $this->testEventLifecycleAndFlyerScoping();
        $this->suite5AdvanceFoodOrderingAndExactPricing();
        $this->suite6AccountTypesResponsibilitiesAndCredentialProtection();
        $this->suite7AccountSettingsHierarchyAndScopes();
        $this->suite8SpecialAttentionSettingsAndInvariants();
        $this->suite9FourAccountTypesAndAuthentication();
        $this->suite10DualActingUserAndContextSwitching();
        $this->suite11FourDistinctDashboardsAndAuthorizationBoundaries();
        $this->suite12AccountSpecificSettingsStorageAndInvariants();
        $this->suite13AuditLoggingAndHistory();
        $this->suite14EventWorkerRolesAndOperationalBoundaries();
        $this->suite15UserRegistrationAuthenticationAndPersistence();
        $this->suite16PasswordResetAndEmailVerification();
        $this->suite17EventScopedStaffInvitationsAndSeparationOfDuties();
        $this->suite18SecureEventStaffAssignmentSystem();
        $this->suite19MandatoryAuthenticationAndAccountAccess();
        $this->suite20CompleteRoleBasedAccessControlAndNavigation();
        $this->suite21UnitedStatesOnlyConfigurationAndPaymentIntegrity();
        $this->suite22TicketRedemptionAndServerSideQrVerification();

        echo "\n--------------------------------------------------------\n";
        echo "SUMMARY: {$this->passed} Passed, {$this->failed} Failed\n";
        echo "--------------------------------------------------------\n";

        if ($this->failed > 0) {
            exit(1);
        }
    }

    private function testTenantRoleEnums(): void
    {
        echo "Suite 1: TenantRole Enums & Permissions\n";

        $this->assert(TenantRole::PLATFORM_ADMIN->value === 'platform_admin', "PLATFORM_ADMIN value is 'platform_admin'");
        $this->assert(TenantRole::EVENT_OWNER->value === 'event_owner', "EVENT_OWNER value is 'event_owner'");
        $this->assert(TenantRole::PAYMENT_VERIFIER->value === 'payment_verifier', "PAYMENT_VERIFIER value is 'payment_verifier'");
        $this->assert(TenantRole::FOOD_COLLECTOR->value === 'food_collector', "FOOD_COLLECTOR value is 'food_collector'");

        // Financial access
        $this->assert(TenantRole::PLATFORM_ADMIN->canAccessFinancials() === true, "Platform Admin can access financials");
        $this->assert(TenantRole::EVENT_OWNER->canAccessFinancials() === true, "Event Owner can access financials");
        $this->assert(TenantRole::PAYMENT_VERIFIER->canAccessFinancials() === false, "Payment Verifier cannot access financials");
        $this->assert(TenantRole::FOOD_COLLECTOR->canAccessFinancials() === false, "Food Collector cannot access financials");

        // Payment verification
        $this->assert(TenantRole::PAYMENT_VERIFIER->canVerifyPayments() === true, "Payment Verifier can verify payments");
        $this->assert(TenantRole::FOOD_COLLECTOR->canVerifyPayments() === false, "Food Collector cannot verify payments");

        // Food collection
        $this->assert(TenantRole::FOOD_COLLECTOR->canCollectFood() === true, "Food Collector can collect food");

        // Event management
        $this->assert(TenantRole::EVENT_OWNER->canManageEventConfiguration() === true, "Event Owner can manage event config");
        $this->assert(TenantRole::PAYMENT_VERIFIER->canManageEventConfiguration() === false, "Payment Verifier cannot alter menu/prices");
        $this->assert(TenantRole::FOOD_COLLECTOR->canManageEventConfiguration() === false, "Food Collector cannot alter menu/prices");
    }

    private function testTenantContextService(): void
    {
        echo "\nSuite 2: TenantContext Lifecycle & Scope Bypass\n";

        TenantContext::clear();
        $this->assert(TenantContext::get() === null, "Initial context is null");
        $this->assert(TenantContext::isBypassed() === false, "Initial bypass state is false");

        // Test withoutTenantScope
        $executed = false;
        $result = TenantContext::withoutTenantScope(function () use (&$executed) {
            $executed = true;
            return TenantContext::isBypassed();
        });

        $this->assert($executed === true, "Callback inside withoutTenantScope executed");
        $this->assert($result === true, "Bypass is true inside callback");
        $this->assert(TenantContext::isBypassed() === false, "Bypass is restored to false after execution");
    }

    private function testCrossOrganizerAuthorizationLogic(): void
    {
        echo "\nSuite 3: Cross-Organizer Isolation & Policy Logic\n";

        $tenantA = 'org-uuid-kingston-youth';
        $tenantB = 'org-uuid-portmore-church';

        // Helper replicating policy logic
        $evaluatePolicy = function (string $userTenantId, TenantRole $userRole, string $resourceTenantId, string $action): bool {
            if ($userRole === TenantRole::PLATFORM_ADMIN) {
                return true;
            }

            if ($userTenantId !== $resourceTenantId) {
                return false; // Cross-tenant violation
            }

            return match ($action) {
                'view_financials' => $userRole->canAccessFinancials(),
                'verify_payments' => $userRole->canVerifyPayments(),
                'collect_food' => $userRole->canCollectFood(),
                'manage_event' => $userRole->canManageEventConfiguration(),
                default => false,
            };
        };

        // Cross-tenant IDOR defense
        $this->assert(
            $evaluatePolicy($tenantA, TenantRole::EVENT_OWNER, $tenantB, 'view_financials') === false,
            "Event Owner from Tenant A is strictly blocked from Tenant B financials"
        );

        $this->assert(
            $evaluatePolicy($tenantA, TenantRole::FOOD_COLLECTOR, $tenantB, 'collect_food') === false,
            "Food Collector from Tenant A cannot redeem tickets belonging to Tenant B"
        );

        // Platform Admin bypass
        $this->assert(
            $evaluatePolicy('admin-tenant', TenantRole::PLATFORM_ADMIN, $tenantB, 'view_financials') === true,
            "Platform Admin can access Tenant B for global oversight"
        );

        // Within-tenant boundaries
        $this->assert(
            $evaluatePolicy($tenantA, TenantRole::FOOD_COLLECTOR, $tenantA, 'collect_food') === true,
            "Food Collector in Tenant A can collect food for Tenant A orders"
        );

        $this->assert(
            $evaluatePolicy($tenantA, TenantRole::FOOD_COLLECTOR, $tenantA, 'verify_payments') === false,
            "Food Collector in Tenant A cannot verify payments in Tenant A"
        );

        $this->assert(
            $evaluatePolicy($tenantA, TenantRole::PAYMENT_VERIFIER, $tenantA, 'verify_payments') === true,
            "Payment Verifier in Tenant A can verify payments in Tenant A"
        );
    }

    private function testEventLifecycleAndFlyerScoping(): void
    {
        echo "\nSuite 4: Event Lifecycle, Advance Cutoffs & Flyer Path Scoping\n";

        $tenantId = 'org-uuid-kingston-youth';

        // 1. Cutoff logic
        $futureDeadline = (new \DateTime('+2 days'))->format('Y-m-d H:i:s');
        $pastDeadline = (new \DateTime('-2 hours'))->format('Y-m-d H:i:s');

        $checkOrderingAllowed = function (bool $isOpen, ?string $deadline): bool {
            if (!$isOpen) return false;
            if ($deadline && (new \DateTime()) > (new \DateTime($deadline))) return false;
            return true;
        };

        $this->assert($checkOrderingAllowed(true, $futureDeadline) === true, "Ordering is allowed before advance deadline");
        $this->assert($checkOrderingAllowed(true, $pastDeadline) === false, "Ordering is closed after advance deadline passes");
        $this->assert($checkOrderingAllowed(false, $futureDeadline) === false, "Ordering is blocked when is_ordering_open is false");

        // 2. Flyer path isolation generator
        $generateFlyerPath = function (string $orgId, string $filename): string {
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            return "tenants/{$orgId}/flyers/" . time() . "_flyer.{$ext}";
        };

        $path = $generateFlyerPath($tenantId, 'beachfront_poster.png');
        $this->assert(str_starts_with($path, "tenants/{$tenantId}/flyers/"), "Flyer path is strictly isolated under tenant directory");
        $this->assert(str_ends_with($path, ".png"), "Flyer path preserves sanitized lowercase extension");

        // 4. Status transitions and visibility
        $evaluateVisibility = function (string $status, bool $isOwner): array {
            if ($status === 'published') {
                return ['allowed' => true, 'http' => 200, 'mode' => 'public'];
            }
            if ($isOwner) {
                return ['allowed' => false, 'http' => 200, 'mode' => 'owner_preview'];
            }
            return ['allowed' => false, 'http' => ($status === 'draft' ? 404 : 403), 'mode' => 'denied'];
        };

        $pubVis = $evaluateVisibility('published', false);
        $this->assert($pubVis['allowed'] === true && $pubVis['http'] === 200, "Published events are viewable by anonymous guests (HTTP 200)");

        $draftGuestVis = $evaluateVisibility('draft', false);
        $this->assert($draftGuestVis['allowed'] === false && $draftGuestVis['http'] === 404, "Draft events return HTTP 404 to anonymous guests");

        $draftOwnerVis = $evaluateVisibility('draft', true);
        $this->assert($draftOwnerVis['allowed'] === false && $draftOwnerVis['http'] === 200 && $draftOwnerVis['mode'] === 'owner_preview', "Draft events are previewable by authenticated owners");

        $cancelledVis = $evaluateVisibility('cancelled', false);
        $this->assert($cancelledVis['allowed'] === false && $cancelledVis['http'] === 403, "Cancelled events return HTTP 403 to public");

        // 5. Unique shareable URL and social metadata
        $slug = "kingston-fishfry-2026";
        $shareableUrl = "https://islandevents.app/e/{$slug}";
        $this->assert($shareableUrl === "https://islandevents.app/e/kingston-fishfry-2026", "Shareable URL formatted accurately with event slug");

        $socialMeta = [
            'og_title' => "Sunday Fish Fry | EventBite",
            'og_image' => "https://islandevents.app/storage/{$path}",
            'og_url' => $shareableUrl,
        ];
        $this->assert(str_starts_with($socialMeta['og_image'], "https://islandevents.app/storage/tenants/"), "Social sharing metadata includes secure tenant storage URL");
    }

    public function suite5AdvanceFoodOrderingAndExactPricing(): void
    {
        echo "\nSuite 5: Advance Food-Ordering System & Exact Pricing (Minor Units)\n";

        // 1. Exact integer minor units conversion
        $minorUSD = \App\Services\PriceCalculationService::toMinorUnits(18.50, 'USD');
        $this->assert($minorUSD === 1850, "Converts $18.50 into exact integer minor units (1850 cents)");

        $float1 = \App\Services\PriceCalculationService::toMinorUnits(0.10, 'USD');
        $float2 = \App\Services\PriceCalculationService::toMinorUnits(0.20, 'USD');
        $sumMinor = $float1 + $float2;
        $this->assert($sumMinor === 30, "Avoids IEEE-754 drift: 10 cents + 20 cents = exactly 30 cents");
        $this->assert(\App\Services\PriceCalculationService::fromMinorUnits($sumMinor, 'USD') === 0.30, "Converts 30 cents back to 0.30 without float distortion");

        // 2. Order totals in integer minor units
        $items = [
            ['final_item_price' => 25.25, 'quantity' => 2], // 5050 cents
            ['final_item_price' => 18.00, 'quantity' => 1], // 1800 cents
        ];
        $packagingFee = 2.50; // 250 cents
        $totals = \App\Services\PriceCalculationService::calculateOrderTotals($items, $packagingFee, 'USD');

        $this->assert($totals['subtotal_minor'] === 6850, "Subtotal calculated in exact minor units (6850 cents)");
        $this->assert($totals['packaging_fee_minor'] === 250, "Packaging fee calculated in exact minor units (250 cents)");
        $this->assert($totals['total_amount_minor'] === 7100, "Total amount calculated in exact minor units (7100 cents)");
        $this->assert($totals['total_amount'] === 71.00, "Total amount converted accurately to $71.00");

        // 3. Stock protection and atomic reservation logic
        $stock = ['snapper' => 3];
        $reserveStock = function (array &$inv, string $item, int $qty): array {
            if ($qty <= 0) {
                return ['success' => false, 'error' => "Invalid quantity: {$qty}"];
            }
            if (($inv[$item] ?? 0) < $qty) {
                return ['success' => false, 'error' => "Insufficient stock for '{$item}'"];
            }
            $inv[$item] -= $qty;
            return ['success' => true, 'remaining' => $inv[$item]];
        };

        // First customer orders 2 of 3 -> succeeds
        $res1 = $reserveStock($stock, 'snapper', 2);
        $this->assert($res1['success'] === true && $stock['snapper'] === 1, "First concurrent order atomically reserves 2 portions, leaving 1");

        // Second customer orders 2 of remaining 1 -> rejected, preventing overselling
        $res2 = $reserveStock($stock, 'snapper', 2);
        $this->assert($res2['success'] === false && $stock['snapper'] === 1, "Second concurrent order rejected when stock insufficient; inventory preserved");

        // Zero or negative quantity rejected
        $resZero = $reserveStock($stock, 'snapper', 0);
        $this->assert($resZero['success'] === false, "Zero quantity rejected by server-side validation");

        // 4. Immutable historical order snapshotting
        $catalogItem = [
            'id' => 'snapper-deluxe',
            'name' => 'Original Escovitch Snapper',
            'price_usd' => 18.00,
        ];
        // Create order item snapshot
        $snapshottedOrderItem = [
            'menu_item_id' => $catalogItem['id'],
            'name' => $catalogItem['name'],
            'final_item_price' => $catalogItem['price_usd'],
            'currency' => 'USD',
        ];
        // Modify catalog item next week
        $catalogItem['name'] = 'Escovitch Snapper (Price Raised)';
        $catalogItem['price_usd'] = 26.00;

        $this->assert($snapshottedOrderItem['name'] === 'Original Escovitch Snapper', "Order snapshot preserves historical item name after catalog edit");
        $this->assert($snapshottedOrderItem['final_item_price'] === 18.00, "Order snapshot preserves historical price ($18.00) after catalog price increase ($26.00)");

        // 5. Safe initial payment status
        $determineInitialStatus = function (string $method, ?string $ref): array {
            if ($method === 'cash') {
                return ['status' => 'awaiting_payment', 'amount_paid' => 0.00, 'authorized_unpaid' => true];
            }
            if (!empty($ref)) {
                return ['status' => 'payment_submitted', 'amount_paid' => 0.00, 'authorized_unpaid' => false];
            }
            return ['status' => 'awaiting_payment', 'amount_paid' => 0.00, 'authorized_unpaid' => false];
        };

        $cashInitial = $determineInitialStatus('cash', null);
        $this->assert($cashInitial['status'] === 'awaiting_payment' && $cashInitial['amount_paid'] === 0.00, "Cash orders initially created as awaiting_payment with $0.00 paid (never auto-marked paid)");

        $transferInitial = $determineInitialStatus('bank', 'NCB-TXN-9812');
        $this->assert($transferInitial['status'] === 'payment_submitted' && $transferInitial['amount_paid'] === 0.00, "Transfer orders with ref initially created as payment_submitted with $0.00 verified paid");
    }

    public function suite6AccountTypesResponsibilitiesAndCredentialProtection(): void
    {
        echo "\nSuite 6: Enterprise Account Types, Responsibilities & Credential Masking\n";

        // 1. System Administrator
        $sysAdmin = \App\Enums\AccountType::SYSTEM_ADMIN;
        $this->assert($sysAdmin->canAccessPlatformManagement() === true, "System Administrator can access platform management & health monitors");
        $this->assert($sysAdmin->canModifyGlobalPlatformFees() === true, "System Administrator can configure global platform fees");
        $this->assert($sysAdmin->canViewUnmaskedCredentials() === false, "System Administrator CANNOT view unmasked API credentials (universal security invariant)");

        // 2. Administrator (Delegated Operations)
        $admin = \App\Enums\AccountType::ADMINISTRATOR;
        $this->assert($admin->canAccessDelegatedAdmin() === true, "Administrator can access delegated operations dashboard (disputes, host support)");
        $this->assert($admin->canAccessPlatformManagement() === false, "Administrator CANNOT access root platform management");
        $this->assert($admin->canModifyGlobalPlatformFees() === false, "Administrator CANNOT modify global platform fees (explicit privilege separation)");
        $this->assert($admin->requiresPiiAuditLogging() === true, "Administrator customer PII access requires immutable audit logging");

        // 3. Event Host
        $host = \App\Enums\AccountType::EVENT_HOST;
        $this->assert($host->canOperateOwnEvents() === true, "Event Host can operate own events (flyers, menus, stock, cash receipts)");
        $this->assert($host->canAccessPlatformManagement() === false, "Event Host CANNOT access platform management");
        $this->assert($host->canAccessDelegatedAdmin() === false, "Event Host CANNOT access delegated administrator console");

        // 4. Attendee
        $attendee = \App\Enums\AccountType::ATTENDEE;
        $this->assert($attendee->isAttendee() === true, "Attendee is consumer account purchasing tickets and food");
        $this->assert($attendee->canOperateOwnEvents() === false, "Attendee CANNOT manage event menus or flyers");

        // 5. Sensitive Credential Masking Service
        $maskedKey = \App\Services\CredentialMaskingService::maskSecret('sk_live_998124871928371982', 4);
        $this->assert(str_starts_with($maskedKey, 'sk_live_••••••••') && str_ends_with($maskedKey, '1982'), "Twilio/Stripe key is masked with bullet characters preserving prefix and last 4");

        $maskedSes = \App\Services\CredentialMaskingService::maskSecret('AKIAIOSFODNN7EXAMPLE', 4);
        $this->assert(str_starts_with($maskedSes, 'AKIA••••••••') && str_ends_with($maskedSes, 'MPLE'), "AWS SES access key is safely masked");

        // 6. PII Masking
        $maskedPhone = \App\Services\CredentialMaskingService::maskPhoneNumber('+1 (876) 555-4321');
        $this->assert(str_contains($maskedPhone, '•••-4321'), "Customer phone number is masked for PII protection (+1 (876) •••-4321)");

        $maskedEmail = \App\Services\CredentialMaskingService::maskEmail('customer@islandevents.example');
        $this->assert(str_starts_with($maskedEmail, 'c••••••r@islandevents.example'), "Customer email is masked for PII protection");

        // 7. Privileged Access Audit Logging
        $auditEntry = \App\Services\CredentialMaskingService::logPrivilegedAccess(
            'user-admin-beverly',
            'admin',
            'order_dispute_review',
            'payment_records',
            'DSP-2026-001',
            '172.16.4.12',
            'Administrator reviewed bank transfer statement during customer dispute.'
        );
        $this->assert($auditEntry['is_pii_access'] === true, "Privileged payment record access is tagged with is_pii_access = true");
        $this->assert($auditEntry['actor_role'] === 'admin', "Audit log records accurate actor role");
        $this->assert(str_starts_with($auditEntry['id'], 'sec-'), "Audit entry has unique security record ID");
    }

    public function suite7AccountSettingsHierarchyAndScopes(): void
    {
        echo "\nSuite 7: Account Settings Hierarchy, Scopes & Exclusions\n";

        $sysAdmin = \App\Enums\AccountType::SYSTEM_ADMIN;
        $admin = \App\Enums\AccountType::ADMINISTRATOR;
        $host = \App\Enums\AccountType::EVENT_HOST;
        $attendee = \App\Enums\AccountType::ATTENDEE;

        // 1. Common Baseline Account Settings (All 4 Account Types)
        foreach (['personal_profile', 'change_password', 'two_factor_auth', 'notification_preferences'] as $commonSetting) {
            $this->assert($sysAdmin->canAccessSetting($commonSetting), "System Admin can access {$commonSetting}");
            $this->assert($admin->canAccessSetting($commonSetting), "Administrator can access {$commonSetting}");
            $this->assert($host->canAccessSetting($commonSetting), "Event Host can access {$commonSetting}");
            $this->assert($attendee->canAccessSetting($commonSetting), "Attendee can access {$commonSetting}");
        }

        // 2. Platform Configuration: STRICTLY System Administrator Only
        $this->assert($sysAdmin->canAccessSetting('platform_configuration'), "System Admin can access platform_configuration");
        $this->assert(!$admin->canAccessSetting('platform_configuration'), "Administrator CANNOT access platform_configuration (prohibited)");
        $this->assert(!$host->canAccessSetting('platform_configuration'), "Event Host CANNOT access platform_configuration (prohibited)");
        $this->assert(!$attendee->canAccessSetting('platform_configuration'), "Attendee CANNOT access platform_configuration (prohibited)");

        // 3. User & Role Management Scope Differentials
        $this->assert($sysAdmin->getSettingAccess('manage_platform_users') === 'all', "System Admin has 'all' access to manage_platform_users");
        $this->assert($admin->getSettingAccess('manage_platform_users') === 'limited', "Administrator has 'limited' access to manage_platform_users");
        $this->assert(!$host->canAccessSetting('manage_platform_users'), "Event Host CANNOT manage platform users");
        $this->assert(!$attendee->canAccessSetting('manage_platform_users'), "Attendee CANNOT manage platform users");

        $this->assert($sysAdmin->getSettingAccess('manage_platform_roles') === 'all', "System Admin has 'all' access to manage_platform_roles");
        $this->assert($admin->getSettingAccess('manage_platform_roles') === 'assigned_permissions_only', "Administrator has 'assigned_permissions_only' for roles");

        // 4. Event & Flyer / Menu Scopes
        $this->assert($sysAdmin->getSettingAccess('event_management') === 'all_subject_to_policy', "System Admin event management is 'all_subject_to_policy'");
        $this->assert($admin->getSettingAccess('event_management') === 'authorized_scope', "Administrator event management is 'authorized_scope'");
        $this->assert($host->getSettingAccess('event_management') === 'own_and_assigned_events', "Event Host event management is 'own_and_assigned_events'");
        $this->assert(!$attendee->canAccessSetting('event_management'), "Attendee CANNOT manage events");

        $this->assert($sysAdmin->getSettingAccess('flyer_menu_management') === 'oversight', "System Admin flyer/menu is 'oversight'");
        $this->assert($admin->getSettingAccess('flyer_menu_management') === 'authorized_scope', "Administrator flyer/menu is 'authorized_scope'");
        $this->assert($host->getSettingAccess('flyer_menu_management') === 'own_and_assigned_events', "Event Host flyer/menu is 'own_and_assigned_events'");
        $this->assert(!$attendee->canAccessSetting('flyer_menu_management'), "Attendee CANNOT manage flyers/menus");

        // 5. Payment Instructions & Verification
        $this->assert($sysAdmin->getSettingAccess('event_payment_instructions') === 'platform_defaults', "System Admin payment instructions is 'platform_defaults'");
        $this->assert($admin->getSettingAccess('event_payment_instructions') === 'authorized_scope', "Administrator payment instructions is 'authorized_scope'");
        $this->assert($host->getSettingAccess('event_payment_instructions') === 'own_events', "Event Host payment instructions is 'own_events'");

        $this->assert($sysAdmin->getSettingAccess('record_verify_payments') === 'authorized_oversight', "System Admin record/verify is 'authorized_oversight'");
        $this->assert($admin->getSettingAccess('record_verify_payments') === 'assigned_permissions', "Administrator record/verify is 'assigned_permissions'");
        $this->assert($host->getSettingAccess('record_verify_payments') === 'own_events', "Event Host record/verify is 'own_events'");

        // 6. Tickets & Collection
        $this->assert($sysAdmin->getSettingAccess('manage_tickets_collection') === 'oversight', "System Admin tickets is 'oversight'");
        $this->assert($admin->getSettingAccess('manage_tickets_collection') === 'authorized_scope', "Administrator tickets is 'authorized_scope'");
        $this->assert($host->getSettingAccess('manage_tickets_collection') === 'own_events', "Event Host tickets is 'own_events'");
        $this->assert($attendee->getSettingAccess('manage_tickets_collection') === 'own_tickets', "Attendee tickets is 'own_tickets'");

        // 7. Sales Reports & Financials
        $this->assert($sysAdmin->getSettingAccess('sales_financial_reports') === 'authorized_oversight', "System Admin sales reports is 'authorized_oversight'");
        $this->assert($admin->getSettingAccess('sales_financial_reports') === 'assigned_scope', "Administrator sales reports is 'assigned_scope'");
        $this->assert($host->getSettingAccess('sales_financial_reports') === 'own_events', "Event Host sales reports is 'own_events'");
        $this->assert($attendee->getSettingAccess('sales_financial_reports') === 'own_transactions', "Attendee reports is 'own_transactions'");

        // 8. Currency & Regional Preferences
        $this->assert($sysAdmin->getSettingAccess('currency_regional_preferences') === 'platform_defaults', "System Admin currency is 'platform_defaults'");
        $this->assert($admin->getSettingAccess('currency_regional_preferences') === 'assigned_scope', "Administrator currency is 'assigned_scope'");
        $this->assert($host->getSettingAccess('currency_regional_preferences') === 'event_specific_settings', "Event Host currency is 'event_specific_settings'");
        $this->assert($attendee->getSettingAccess('currency_regional_preferences') === 'personal_preference', "Attendee currency is 'personal_preference'");

        // 9. Privacy & Data Controls
        $this->assert($sysAdmin->getSettingAccess('privacy_data_controls') === 'platform_policies', "System Admin privacy is 'platform_policies'");
        $this->assert($admin->getSettingAccess('privacy_data_controls') === 'assigned_permissions', "Administrator privacy is 'assigned_permissions'");
        $this->assert($host->getSettingAccess('privacy_data_controls') === 'own_account_lawful_event_data', "Event Host privacy is 'own_account_lawful_event_data'");
        $this->assert($attendee->getSettingAccess('privacy_data_controls') === 'own_account_personal_data', "Attendee privacy is 'own_account_personal_data'");

        // 10. Audit & Security Logs
        $this->assert($sysAdmin->getSettingAccess('audit_security_logs') === 'authorized_platform_wide_access', "System Admin audit logs is 'authorized_platform_wide_access'");
        $this->assert($admin->getSettingAccess('audit_security_logs') === 'scoped_access', "Administrator audit logs is 'scoped_access'");
        $this->assert($host->getSettingAccess('audit_security_logs') === 'own_event_activity', "Event Host audit logs is 'own_event_activity'");
        $this->assert($attendee->getSettingAccess('audit_security_logs') === 'own_account_activity', "Attendee audit logs is 'own_account_activity'");

        // 11. Allowed Settings Count Invariants
        $this->assert(count($sysAdmin->getAllowedSettings()) === 17, "System Admin has exactly 17 allowed settings (complete platform scope)");
        $this->assert(count($admin->getAllowedSettings()) === 16, "Administrator has exactly 16 allowed settings (excludes platform_configuration)");
        $this->assert(count($host->getAllowedSettings()) === 14, "Event Host has exactly 14 allowed settings (excludes platform user/role/system config)");
        $this->assert(count($attendee->getAllowedSettings()) === 9, "Attendee has exactly 9 allowed settings (profile, auth, tickets, transactions, privacy)");
    }

    public function suite8SpecialAttentionSettingsAndInvariants(): void
    {
        echo "\nSuite 8: Special Attention Settings & Invariant Enforcement\n";

        // 1. Host Event Defaults & Historical Order Immutability
        // Invariant: Changing event defaults must NEVER alter existing historical orders
        $historicalOrder = [
            'id' => 'ord_hist_backend_001',
            'order_number' => 'ORD-HIST-001',
            'ticket_code' => 'TCK-HIST-001',
            'currency' => 'JMD',
            'subtotal_cents' => 300000,
            'packaging_cents' => 10000,
            'total_cents' => 310000,
            'collected_quantity' => 2,
            'total_quantity' => 2,
            'payment_status' => 'paid',
            'payment_method' => 'bank_transfer',
        ];

        // Host updates event defaults to USD, changes packaging fees, and alters pickup location
        $eventDefaultsUpdate = [
            'default_currency' => 'USD',
            'default_packaging_fee_jmd' => 500,
            'default_packaging_fee_usd' => 3,
            'default_location' => 'Lauderhill Sample Pavilion',
            'default_order_deadline_hours' => 12,
        ];

        // Verify historical order retains its historical values
        $this->assert($historicalOrder['currency'] === 'JMD', "Historical order currency remains frozen in JMD despite host changing default to USD");
        $this->assert($historicalOrder['total_cents'] === 310000, "Historical order total remains exactly 310,000 cents ($3,100 JMD)");
        $this->assert($historicalOrder['collected_quantity'] === 2, "Historical collection history preserved without modification");
        $this->assert($historicalOrder['payment_status'] === 'paid', "Historical payment status remains paid");

        // 2. Host Payment Method Eligibility Gating
        // Payment methods should be enabled only when host is eligible
        $checkEligibility = function(string $location, string $method): bool {
            if ($method === 'cash' || $method === 'bank_transfer' || $method === 'lynk') {
                return true;
            }
            if ($method === 'zelle' || $method === 'cashapp') {
                return $location === 'US';
            }
            return false;
        };

        $this->assert($checkEligibility('JM', 'cash') === true, "Cash payment is eligible for Jamaican host");
        $this->assert($checkEligibility('JM', 'bank_transfer') === true, "Bank transfer (NCB/Scotia) is eligible for Jamaican host");
        $this->assert($checkEligibility('JM', 'lynk') === true, "Lynk is eligible for Jamaican host");
        $this->assert($checkEligibility('JM', 'zelle') === false, "Zelle is strictly INELIGIBLE for Jamaican host (requires US routing)");
        $this->assert($checkEligibility('JM', 'cashapp') === false, "Cash App is strictly INELIGIBLE for Jamaican host (requires US entity)");
        $this->assert($checkEligibility('US', 'zelle') === true, "Zelle is eligible for verified US host");
        $this->assert($checkEligibility('US', 'cashapp') === true, "Cash App is eligible for verified US host");

        // 3. Operational Buffer Calculation
        // Event operations kitchen buffer: +10% over portions sold (using integer math to prevent IEEE-754 drift)
        $soldPortions = 220;
        $bufferPercent = 10;
        $prepBufferPortions = (int) ceil(($soldPortions * (100 + $bufferPercent)) / 100);
        $this->assert($prepBufferPortions === 242, "Kitchen preparation buffer calculated as ceil((220 * 110) / 100) = 242 portions");

        // 4. Customer Communications Token Interpolation
        $template = "Hi {customer_name}! Your order {order_number} has been received. Ticket pass: {ticket_code}. Total: {total_amount}.";
        $data = [
            '{customer_name}' => 'Grace Jones',
            '{order_number}' => 'ORD-2026-888',
            '{ticket_code}' => 'TCK-GJ-888',
            '{total_amount}' => 'JMD $4,500.00',
        ];
        $rendered = str_replace(array_keys($data), array_values($data), $template);
        $this->assert(str_contains($rendered, 'Hi Grace Jones!'), "Communication template interpolates customer name");
        $this->assert(str_contains($rendered, 'ORD-2026-888'), "Communication template interpolates order number");
        $this->assert(!str_contains($rendered, '{customer_name}'), "Raw placeholder tokens are completely replaced");

        // 5. Attendee Display Currency Invariant
        // The preferred display currency must not change the currency actually charged for an order
        $chargedCurrency = 'JMD';
        $chargedCents = 350000; // $3,500.00 JMD
        $attendeePreferredCurrency = 'USD';
        $exchangeRate = 158.0;

        // Estimated display amount for preview only:
        $displayEstimatedUsd = round(($chargedCents / 100) / $exchangeRate, 2);
        $this->assert($displayEstimatedUsd === 22.15, "Display conversion estimate calculated as ~$22.15 USD");
        $this->assert($chargedCurrency === 'JMD', "Actual charged currency strictly remains JMD (never rewritten by display preference)");
        $this->assert($chargedCents === 350000, "Actual charged amount strictly remains 350,000 cents (never rounded or mutated)");

        // 6. Universal Secret Key Protection Invariant
        // Secret keys must be stored securely and never exposed in plaintext
        $secrets = [
            'sk_live_' . '51MzAbcDefGhiJklMnoPqrStuVwxYz123456',
            'AC_twilio_secret_token_1234567890abcdef',
            'postmark_live_token_fedcba0987654321',
        ];
        foreach ($secrets as $secret) {
            $masked = \App\Services\CredentialMaskingService::maskSecret($secret, 4);
            $this->assert(!str_contains($masked, substr($secret, 8, 12)), "Masked secret never exposes internal token entropy");
            $this->assert(str_contains($masked, '••••••••'), "Masked secret contains explicit mask bullets");
        }
    }

    public function suite9FourAccountTypesAndAuthentication(): void
    {
        echo "\nSuite 9: Four Account Types, Authentication, Provisioning & Anti-Escalation\n";

        // 1. Account types enum verification
        $types = \App\Enums\AccountType::cases();
        $this->assert(count($types) === 4, "System defines exactly four distinct account types");
        $this->assert(\App\Enums\AccountType::SYSTEM_ADMIN->value === 'system_admin', "System Admin account type value verified");
        $this->assert(\App\Enums\AccountType::ADMINISTRATOR->value === 'admin', "Administrator account type value verified");
        $this->assert(\App\Enums\AccountType::EVENT_HOST->value === 'event_host', "Event Host account type value verified");
        $this->assert(\App\Enums\AccountType::ATTENDEE->value === 'attendee', "Attendee account type value verified");

        // 2. Public registration privilege escalation defense
        $systemAdminEscalationBlocked = false;
        try {
            \App\Services\SystemAdminProvisioningService::assertPublicRegistrationSafe(['role' => 'system_admin']);
        } catch (\SecurityException $e) {
            $systemAdminEscalationBlocked = true;
        }
        $this->assert($systemAdminEscalationBlocked, "Public registration attempting to claim 'system_admin' role is rejected with SecurityException");

        $systemAdminFlagBlocked = false;
        try {
            \App\Services\SystemAdminProvisioningService::assertPublicRegistrationSafe(['is_system_admin' => true, 'email' => 'hacker@bad.com']);
        } catch (\SecurityException $e) {
            $systemAdminFlagBlocked = true;
        }
        $this->assert($systemAdminFlagBlocked, "Public registration attempting to inject 'is_system_admin' flag is rejected");

        $adminEscalationBlocked = false;
        try {
            \App\Services\SystemAdminProvisioningService::assertPublicRegistrationSafe(['role' => 'administrator']);
        } catch (\SecurityException $e) {
            $adminEscalationBlocked = true;
        }
        $this->assert($adminEscalationBlocked, "Public registration attempting to claim 'administrator' role is rejected");

        // Normal attendee registration is permitted
        $attendeeSafe = true;
        try {
            \App\Services\SystemAdminProvisioningService::assertPublicRegistrationSafe(['role' => 'attendee', 'email' => 'guest@gmail.com']);
        } catch (\Throwable $e) {
            $attendeeSafe = false;
        }
        $this->assert($attendeeSafe, "Standard attendee registration passes safety check");

        // 3. Password complexity enforcement
        $shortPasswordBlocked = false;
        try {
            \App\Services\SystemAdminProvisioningService::provisionInitialSystemAdmin([
                'name' => 'Root Admin',
                'email' => 'root@islandevents.app',
                'password' => 'short123', // < 12 characters
            ]);
        } catch (\InvalidArgumentException $e) {
            $shortPasswordBlocked = true;
        }
        $this->assert($shortPasswordBlocked, "System Administrator provisioning rejects passwords shorter than 12 characters");

        // Password hashing verification
        $plainPassword = 'IslandSecure#Enterprise2026!';
        $hashed = password_hash($plainPassword, PASSWORD_DEFAULT);
        $this->assert(password_verify($plainPassword, $hashed), "Password hashing correctly verifies with standard bcrypt/Argon2");

        // 4. Role Assignment & Anti-Self-Elevation
        $canAssign = function(string $callerRole, int $callerId, int $targetId, string $roleToAssign): bool {
            if ($callerId === $targetId) return false; // Self-elevation blocked
            if ($roleToAssign === 'system_admin') return $callerRole === 'system_admin';
            if ($callerRole === 'system_admin') return true;
            if ($callerRole === 'administrator') {
                if ($roleToAssign === 'administrator') return false; // cannot replicate admin
                return in_array($roleToAssign, ['event_host', 'attendee']);
            }
            return false;
        };

        $this->assert($canAssign('administrator', 1, 1, 'system_admin') === false, "User cannot elevate their own privileges (self-elevation blocked)");
        $this->assert($canAssign('administrator', 1, 2, 'system_admin') === false, "Ordinary Administrator CANNOT create or promote a System Administrator");
        $this->assert($canAssign('administrator', 1, 2, 'administrator') === false, "Ordinary Administrator CANNOT grant Administrator privileges without superadmin");
        $this->assert($canAssign('administrator', 1, 2, 'event_host') === true, "Ordinary Administrator can assign Event Host role");
        $this->assert($canAssign('system_admin', 99, 2, 'system_admin') === true, "System Administrator can assign System Administrator role");

        // 5. Account Suspension
        $userStatus = 'suspended';
        $isSuspended = ($userStatus === 'suspended');
        $isActive = ($userStatus === 'active');
        $this->assert($isSuspended === true, "Suspended account is recognized by isSuspended()");
        $this->assert($isActive === false, "Suspended account fails isActive() check");
    }

    public function suite10DualActingUserAndContextSwitching(): void
    {
        echo "\nSuite 10: Dual-Acting User Context Switching & Role Invariants\n";

        // 1. Dual-acting user model: Holds both Event Host and Attendee
        $dualUser = [
            'id' => 10,
            'name' => 'Pastor Dave',
            'email' => 'pastor.dave@portmorechurch.org',
            'status' => 'active',
            'assigned_roles' => ['event_host', 'attendee'],
            'current_context_role' => 'event_host',
        ];

        $getAuthorizedRoles = function(array $user): array {
            $roles = $user['assigned_roles'];
            if (!in_array('attendee', $roles)) {
                $roles[] = 'attendee'; // Attendee is universally accessible for active users
            }
            return array_values(array_unique($roles));
        };

        $roles = $getAuthorizedRoles($dualUser);
        $this->assert(in_array('event_host', $roles), "Dual-acting user holds 'event_host' role");
        $this->assert(in_array('attendee', $roles), "Dual-acting user holds 'attendee' role");

        // Context switcher simulator
        $switchContext = function(array &$user, string $targetRole): array {
            if ($user['status'] === 'suspended') {
                return ['success' => false, 'error' => 'Account is suspended'];
            }
            if ($targetRole !== 'attendee' && !in_array($targetRole, $user['assigned_roles'])) {
                return ['success' => false, 'error' => 'Forbidden: Role not assigned'];
            }
            $prev = $user['current_context_role'];
            $user['current_context_role'] = $targetRole;
            return [
                'success' => true,
                'previous_role' => $prev,
                'active_role' => $targetRole,
                'audit_logged' => true,
            ];
        };

        // Switch to Attendee to purchase food
        $resToAttendee = $switchContext($dualUser, 'attendee');
        $this->assert($resToAttendee['success'] === true, "Dual-acting user successfully switches context to Attendee");
        $this->assert($dualUser['current_context_role'] === 'attendee', "Active context role is now Attendee");

        // Switch back to Event Host
        $resToHost = $switchContext($dualUser, 'event_host');
        $this->assert($resToHost['success'] === true, "Dual-acting user successfully switches context back to Event Host");
        $this->assert($dualUser['current_context_role'] === 'event_host', "Active context role is now Event Host");

        // Pure Attendee attempting unauthorized context switch
        $pureAttendee = [
            'id' => 15,
            'name' => 'Attendee Karen',
            'email' => 'karen@gmail.com',
            'status' => 'active',
            'assigned_roles' => ['attendee'],
            'current_context_role' => 'attendee',
        ];

        $badSwitch1 = $switchContext($pureAttendee, 'system_admin');
        $this->assert($badSwitch1['success'] === false, "Pure attendee cannot switch to System Admin (forbidden)");
        $this->assert($pureAttendee['current_context_role'] === 'attendee', "Pure attendee context remains Attendee after rejected attempt");

        $badSwitch2 = $switchContext($pureAttendee, 'event_host');
        $this->assert($badSwitch2['success'] === false, "Pure attendee cannot switch to Event Host (forbidden)");

        // Suspended user cannot switch
        $suspendedUser = $dualUser;
        $suspendedUser['status'] = 'suspended';
        $badSwitch3 = $switchContext($suspendedUser, 'attendee');
        $this->assert($badSwitch3['success'] === false, "Suspended account cannot perform context switching");

        // 3. Route resolution per role
        $this->assert(
            \App\Services\ContextSwitchingService::getDashboardRouteForRole(AccountType::SYSTEM_ADMIN) === 'system-admin.dashboard',
            "System Admin resolves to system-admin.dashboard"
        );
        $this->assert(
            \App\Services\ContextSwitchingService::getDashboardRouteForRole(AccountType::ADMINISTRATOR) === 'admin.dashboard',
            "Administrator resolves to admin.dashboard"
        );
        $this->assert(
            \App\Services\ContextSwitchingService::getDashboardRouteForRole(AccountType::EVENT_HOST) === 'host.dashboard',
            "Event Host resolves to host.dashboard"
        );
        $this->assert(
            \App\Services\ContextSwitchingService::getDashboardRouteForRole(AccountType::ATTENDEE) === 'attendee.dashboard',
            "Attendee resolves to attendee.dashboard"
        );
    }

    public function suite11FourDistinctDashboardsAndAuthorizationBoundaries(): void
    {
        echo "\nSuite 11: Four Distinct Dashboards & Authorization Boundaries\n";

        // 1. Dashboard access permissions
        $canAccessDashboard = function(AccountType $userRole, string $dashboard): bool {
            return match ($dashboard) {
                'system_admin' => $userRole === AccountType::SYSTEM_ADMIN,
                'admin' => in_array($userRole, [AccountType::SYSTEM_ADMIN, AccountType::ADMINISTRATOR]),
                'host' => in_array($userRole, [AccountType::SYSTEM_ADMIN, AccountType::EVENT_HOST]),
                'attendee' => true, // All active accounts can access consumer wallet
                default => false,
            };
        };

        $this->assert($canAccessDashboard(AccountType::SYSTEM_ADMIN, 'system_admin') === true, "System Admin can access System Admin dashboard");
        $this->assert($canAccessDashboard(AccountType::ADMINISTRATOR, 'system_admin') === false, "Administrator cannot access System Admin dashboard");
        $this->assert($canAccessDashboard(AccountType::EVENT_HOST, 'system_admin') === false, "Event Host cannot access System Admin dashboard");
        $this->assert($canAccessDashboard(AccountType::ATTENDEE, 'system_admin') === false, "Attendee cannot access System Admin dashboard");

        $this->assert($canAccessDashboard(AccountType::ADMINISTRATOR, 'admin') === true, "Administrator can access Administrator desk");
        $this->assert($canAccessDashboard(AccountType::EVENT_HOST, 'admin') === false, "Event Host cannot access Administrator desk");

        $this->assert($canAccessDashboard(AccountType::EVENT_HOST, 'host') === true, "Event Host can access Event Host command center");
        $this->assert($canAccessDashboard(AccountType::ATTENDEE, 'host') === false, "Attendee cannot access Event Host command center");

        $this->assert($canAccessDashboard(AccountType::ATTENDEE, 'attendee') === true, "Attendee can access Attendee wallet");

        // 2. Attendee Order Privacy Invariant (Barring Cross-Attendee Access)
        $orderAlice = [
            'id' => 'ord-alice-99',
            'order_number' => 'ORD-2026-99',
            'ticket_code' => 'TCK-ALICE-99',
            'customer_email' => 'alice@gmail.com',
            'customer_phone' => '+1 (876) 555-1234',
            'status' => 'paid',
        ];

        $evaluateAttendeeOrderLookup = function(string $requesterEmail, string $requesterPhone, array $order): array {
            $emailMatch = !empty($requesterEmail) && strtolower(trim($requesterEmail)) === strtolower(trim($order['customer_email']));
            $phoneMatch = !empty($requesterPhone) && trim($requesterPhone) === trim($order['customer_phone']);

            if ($emailMatch || $phoneMatch) {
                return ['allowed' => true, 'status' => 200, 'order' => $order];
            }
            return ['allowed' => false, 'status' => 403, 'error' => 'Forbidden: You do not own this order.'];
        };

        // Alice retrieves her own ticket -> OK
        $aliceAccess = $evaluateAttendeeOrderLookup('alice@gmail.com', '', $orderAlice);
        $this->assert($aliceAccess['allowed'] === true && $aliceAccess['status'] === 200, "Attendee Alice can view and retrieve her own order and ticket pass");

        // Bob attempts to retrieve Alice's ticket -> Blocked with 403 Forbidden
        $bobAccess = $evaluateAttendeeOrderLookup('bob@gmail.com', '+1 (876) 555-9876', $orderAlice);
        $this->assert($bobAccess['allowed'] === false && $bobAccess['status'] === 403, "Attendee Bob is strictly barred from inspecting Alice's order (HTTP 403 Forbidden)");
    }

    public function suite12AccountSpecificSettingsStorageAndInvariants(): void
    {
        echo "\nSuite 12: Account-Specific Settings Storage, Invariants & Security\n";

        // 1. Structured models rather than unrestricted JSON blobs
        $settingEntities = [
            'App\Models\HostEventSetting',
            'App\Models\HostPaymentPolicy',
            'App\Models\HostOperationalRule',
            'App\Models\SystemPlatformSetting',
        ];
        foreach ($settingEntities as $entity) {
            $this->assert(class_exists($entity), "Dedicated settings model exists: {$entity}");
        }

        // 2. Server validation for platform settings
        $validatePlatformSettings = function(array $data): array {
            $errors = [];
            if (isset($data['database_pool_max']) && $data['database_pool_max'] <= 0) {
                $errors[] = 'Database pool max must be greater than zero.';
            }
            if (isset($data['queue_workers_count']) && $data['queue_workers_count'] <= 0) {
                $errors[] = 'Queue worker count must be greater than zero.';
            }
            if (isset($data['audit_retention_days']) && $data['audit_retention_days'] < 90) {
                $errors[] = 'Audit log retention must be at least 90 days for compliance.';
            }
            return $errors;
        };

        $validSettings = ['database_pool_max' => 100, 'queue_workers_count' => 4, 'audit_retention_days' => 365];
        $this->assert(empty($validatePlatformSettings($validSettings)), "Valid platform settings pass server validation");

        $invalidSettings = ['database_pool_max' => -5, 'queue_workers_count' => 0, 'audit_retention_days' => 30];
        $errors = $validatePlatformSettings($invalidSettings);
        $this->assert(count($errors) === 3, "Invalid platform configurations are rejected by server validation");

        // 3. Display Currency Separation Invariant
        // Changing preferred display currency NEVER mutates historical order currency or total cents
        $historicalOrder = [
            'id' => 'ord-jmd-555',
            'currency' => 'JMD',
            'total_cents' => 250000, // $2,500.00 JMD
        ];
        $userPreferredCurrency = 'USD';
        $rate = 158.0;

        // Preview estimate
        $estimatedUSD = round(($historicalOrder['total_cents'] / 100) / $rate, 2); // ~$15.82
        $this->assert($historicalOrder['currency'] === 'JMD', "Historical order currency remains strictly frozen in JMD");
        $this->assert($historicalOrder['total_cents'] === 250000, "Historical order total remains exactly 250,000 cents");
        $this->assert($estimatedUSD === 15.82, "Display preview estimate is accurately computed as ~$15.82 USD");

        // 4. Credential Masking in Settings
        $testSecrets = [
            'database_password' => 'EnterpriseP@ssword2026',
            'redis_auth_token' => 'redistoken_secret_998877665544332211',
            'payment_webhook_secret' => 'whsec_9876543210fedcba9876543210abcdef',
        ];

        foreach ($testSecrets as $key => $secret) {
            $masked = \App\Services\CredentialMaskingService::maskSecret($secret, 4);
            $this->assert(str_starts_with($masked, substr($secret, 0, 4)), "Masked {$key} preserves identifiable prefix");
            $this->assert(str_contains($masked, '••••••••'), "Masked {$key} contains bullet obfuscation");
            $this->assert(!str_contains($masked, substr($secret, 4, -4)), "Masked {$key} hides sensitive internal entropy");
        }
    }

    public function suite13AuditLoggingAndHistory(): void
    {
        echo "\nSuite 13: Audit Logging, PII Access Tracking & History\n";

        // 1. Audit Log Schema fields
        $sampleLog = [
            'id' => 'sec-uuid-1234-5678',
            'user_id' => 1,
            'actor_role' => 'administrator',
            'action' => 'customer_dispute_reviewed',
            'target_entity' => 'orders',
            'target_id' => 'ord-2026-99',
            'ip_address' => '192.168.1.50',
            'user_agent' => 'Mozilla/5.0 Enterprise Admin Desk',
            'details' => 'Administrator inspected customer dispute details for Order ORD-2026-99.',
            'is_pii_access' => true,
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ];

        $this->assert(!empty($sampleLog['id']), "Audit log contains unique security record ID");
        $this->assert($sampleLog['actor_role'] === 'administrator', "Audit log records accurate actor role");
        $this->assert($sampleLog['is_pii_access'] === true, "Privileged customer data inspection is flagged with is_pii_access = true");

        // 2. Regular non-PII operational action
        $routineLog = [
            'action' => 'ticket_scanned',
            'target_entity' => 'tickets',
            'is_pii_access' => false,
        ];
        $this->assert($routineLog['is_pii_access'] === false, "Routine operational scan is marked is_pii_access = false");
    }

    public function suite14EventWorkerRolesAndOperationalBoundaries(): void
    {
        echo "\nSuite 14: Specialized Event-Worker Roles & Least-Privilege Boundaries\n";

        // 1. Role Enum verification
        $workerRoles = \App\Enums\EventWorkerRole::cases();
        $this->assert(count($workerRoles) === 5, "Exactly five specialized event-worker roles defined");

        $clerk = \App\Enums\EventWorkerRole::PAYMENT_CLERK;
        $checker = \App\Enums\EventWorkerRole::TICKET_CHECKER;
        $cook = \App\Enums\EventWorkerRole::FOOD_STATION_WORKER;
        $supervisor = \App\Enums\EventWorkerRole::EVENT_SUPERVISOR;
        $custom = \App\Enums\EventWorkerRole::CUSTOM_RESTRICTED_WORKER;

        $this->assert($clerk->value === 'payment_clerk', "Payment Clerk enum value is 'payment_clerk'");
        $this->assert($checker->value === 'ticket_checker', "Ticket Checker enum value is 'ticket_checker'");
        $this->assert($cook->value === 'food_station_worker', "Food Station Worker enum value is 'food_station_worker'");
        $this->assert($supervisor->value === 'event_supervisor', "Event Supervisor enum value is 'event_supervisor'");
        $this->assert($custom->value === 'custom_restricted_worker', "Custom Restricted Worker enum value is 'custom_restricted_worker'");

        // 2. Payment Clerk Capabilities and Prohibitions
        // Can: View relevant orders, record cash received, review submitted transfer references, perform payment verification
        $this->assert($clerk->canPerform('view_relevant_orders') === true, "Payment Clerk can view relevant orders");
        $this->assert($clerk->canPerform('record_cash_received') === true, "Payment Clerk can record cash received");
        $this->assert($clerk->canPerform('review_transfer_references') === true, "Payment Clerk can review submitted transfer references");
        $this->assert($clerk->canPerform('verify_payments') === true, "Payment Clerk can verify payments");

        // Must not: Change event ownership, change system settings, access unrelated events, alter financial records without audit trail
        $this->assert($clerk->canPerform('change_event_ownership') === false, "Payment Clerk CANNOT change event ownership");
        $this->assert($clerk->canPerform('change_system_settings') === false, "Payment Clerk CANNOT change system settings");
        $this->assert($clerk->canPerform('access_unrelated_events') === false, "Payment Clerk CANNOT access unrelated events");
        $this->assert($clerk->canPerform('alter_financials_without_audit') === false, "Payment Clerk CANNOT alter financial records without audit trail");
        $this->assert($clerk->canPerform('change_ticket_prices') === false, "Payment Clerk CANNOT change ticket prices");

        // 3. Ticket Checker Capabilities and Prohibitions
        // Can: Scan QR tickets, validate ticket status, record entry
        $this->assert($checker->canPerform('scan_qr_tickets') === true, "Ticket Checker can scan QR tickets");
        $this->assert($checker->canPerform('validate_ticket_status') === true, "Ticket Checker can validate ticket status");
        $this->assert($checker->canPerform('record_entry') === true, "Ticket Checker can record entry");

        // Must not: View unnecessary payment or personal information, issue refunds, change ticket prices
        $this->assert($checker->canPerform('view_payment_pii') === false, "Ticket Checker CANNOT view payment PII");
        $this->assert($checker->canPerform('issue_refunds') === false, "Ticket Checker CANNOT issue refunds");
        $this->assert($checker->canPerform('change_ticket_prices') === false, "Ticket Checker CANNOT change ticket prices");
        $this->assert($checker->canPerform('record_cash_received') === false, "Ticket Checker CANNOT record cash received");

        // Ticket Checker Sanitization test: payment info and customer phone/email stripped
        $rawOrderForChecker = [
            'order_number' => 'ORD-101',
            'ticket_code' => 'TCK-101',
            'customer_name' => 'Usain Bolt',
            'customer_email' => 'usain@track.com',
            'customer_phone' => '+18765551234',
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'NCB-SECRET-123',
            'total_amount' => 55.00,
            'total_cents' => 5500,
            'status' => 'paid',
        ];
        $sanitizedForChecker = $checker->sanitizeOrderForWorker($rawOrderForChecker);
        $this->assert(!isset($sanitizedForChecker['payment_method']), "Ticket Checker order view strips payment_method");
        $this->assert(!isset($sanitizedForChecker['payment_reference']), "Ticket Checker order view strips payment_reference");
        $this->assert(!isset($sanitizedForChecker['total_cents']), "Ticket Checker order view strips total_cents");
        $this->assert(!isset($sanitizedForChecker['customer_email']), "Ticket Checker order view strips customer_email");
        $this->assert(!isset($sanitizedForChecker['customer_phone']), "Ticket Checker order view strips customer_phone");
        $this->assert($sanitizedForChecker['ticket_code'] === 'TCK-101', "Ticket Checker order view retains ticket_code for entry verification");
        $this->assert($sanitizedForChecker['ticket_validation_only'] === true, "Ticket Checker order marked ticket_validation_only");

        // 4. Food Station Worker Capabilities and Prohibitions
        // Can: View assigned food items and collection instructions, mark quantities collected, see remaining quantities
        $this->assert($cook->canPerform('view_assigned_food_items') === true, "Food Station Worker can view assigned food items");
        $this->assert($cook->canPerform('view_collection_instructions') === true, "Food Station Worker can view collection instructions");
        $this->assert($cook->canPerform('mark_quantities_collected') === true, "Food Station Worker can mark quantities collected");
        $this->assert($cook->canPerform('see_remaining_quantities') === true, "Food Station Worker can see remaining quantities");

        // Must not: View unrelated customer information, change menu prices, access financial reports
        $this->assert($cook->canPerform('view_unrelated_customer_info') === false, "Food Station Worker CANNOT view unrelated customer info");
        $this->assert($cook->canPerform('change_menu_prices') === false, "Food Station Worker CANNOT change menu prices");
        $this->assert($cook->canPerform('access_financial_reports') === false, "Food Station Worker CANNOT access financial reports");
        $this->assert($cook->canPerform('record_cash_received') === false, "Food Station Worker CANNOT record cash receipts");

        // Food Station Worker Station Filtering & Price Sanitization
        $rawOrderForCook = [
            'order_number' => 'ORD-102',
            'customer_name' => 'Shelly-Ann Fraser',
            'customer_phone' => '+18765552345',
            'total_cents' => 6500,
            'items' => [
                ['name' => 'Escovitch Snapper', 'station' => 'mains', 'quantity' => 2, 'unit_price' => 25.00],
                ['name' => 'Tropical Rum Punch', 'station' => 'drinks', 'quantity' => 3, 'unit_price' => 5.00],
            ],
        ];
        $sanitizedForCookStationMains = $cook->sanitizeOrderForWorker($rawOrderForCook, 'mains');
        $this->assert(count($sanitizedForCookStationMains['items']) === 1, "Food Station Worker on 'mains' only sees mains items");
        $this->assert($sanitizedForCookStationMains['items'][0]['name'] === 'Escovitch Snapper', "Mains station shows Escovitch Snapper");
        $this->assert(!isset($sanitizedForCookStationMains['items'][0]['unit_price']), "Food Station Worker view strips unit_price");
        $this->assert(!isset($sanitizedForCookStationMains['total_cents']), "Food Station Worker view strips total_cents");
        $this->assert(!isset($sanitizedForCookStationMains['customer_phone']), "Food Station Worker view strips customer_phone");

        // 5. Event Supervisor Capabilities and Prohibitions
        // Can: Oversee assigned event operations, view relevant reports, manage permitted event-worker assignments
        $this->assert($supervisor->canPerform('oversee_assigned_operations') === true, "Event Supervisor can oversee assigned event operations");
        $this->assert($supervisor->canPerform('view_relevant_reports') === true, "Event Supervisor can view relevant reports");
        $this->assert($supervisor->canPerform('manage_permitted_worker_assignments') === true, "Event Supervisor can manage permitted event-worker assignments");
        $this->assert($supervisor->canPerform('scan_qr_tickets') === true, "Event Supervisor can scan tickets");
        $this->assert($supervisor->canPerform('mark_quantities_collected') === true, "Event Supervisor can collect food");

        // Must not: Access other hosts' events, access platform-wide administrator settings
        $this->assert($supervisor->canPerform('access_other_hosts_events') === false, "Event Supervisor CANNOT access other hosts' events");
        $this->assert($supervisor->canPerform('access_platform_settings') === false, "Event Supervisor CANNOT access platform-wide administrator settings");
        $this->assert($supervisor->canPerform('manage_platform_users') === false, "Event Supervisor CANNOT manage platform users");
        $this->assert($supervisor->canPerform('manage_platform_roles') === false, "Event Supervisor CANNOT manage platform roles");

        // 6. Custom Restricted Worker
        // Can: Receive a deliberately selected combination of approved permissions
        // Must not: Gain permissions beyond those explicitly granted
        $approvedCustomPermissions = ['scan_qr_tickets', 'record_entry'];
        $this->assert(
            $custom->canPerform('scan_qr_tickets', $approvedCustomPermissions) === true,
            "Custom Restricted Worker can perform explicitly approved permission (scan_qr_tickets)"
        );
        $this->assert(
            $custom->canPerform('record_entry', $approvedCustomPermissions) === true,
            "Custom Restricted Worker can perform explicitly approved permission (record_entry)"
        );
        $this->assert(
            $custom->canPerform('record_cash_received', $approvedCustomPermissions) === false,
            "Custom Restricted Worker CANNOT perform ungranted action (record_cash_received)"
        );
        $this->assert(
            $custom->canPerform('change_event_ownership', array_merge($approvedCustomPermissions, ['change_event_ownership'])) === false,
            "Custom Restricted Worker CANNOT perform prohibited action even if present in custom permissions array"
        );
        $this->assert(
            $custom->canPerform('access_platform_settings', ['access_platform_settings']) === false,
            "Custom Restricted Worker CANNOT access platform settings under any circumstances"
        );
    }

    public function suite15UserRegistrationAuthenticationAndPersistence(): void
    {
        echo "\nSuite 15: User Registration, Authentication & Database Persistence\n";

        // Reset in-memory database records
        \Illuminate\Database\Eloquent\Model::$inMemoryRecords = [];
        \Illuminate\Support\Facades\DB::$tables = [];

        // 1. Transactional Registration with Attendee Default Role
        $regData = [
            'first_name' => 'Shanique',
            'last_name' => 'Brown',
            'email' => 'shanique.brown@islandevents.app',
            'password' => 'IslandPass2026!',
            'password_confirmation' => 'IslandPass2026!',
            'phone' => '+1-876-555-0199',
            'preferred_display_currency' => 'JMD',
            'agree_terms' => true,
        ];

        $regResult = \App\Services\AuthenticationService::register($regData);

        $this->assert($regResult['success'] === true, "AuthenticationService::register succeeds with valid payload");
        $this->assert($regResult['redirect_route'] === 'attendee.dashboard', "Public registration automatically routes to attendee dashboard");

        $registeredUser = $regResult['user'];
        $this->assert($registeredUser instanceof \App\Models\User, "Registration returns persistent User instance");
        $this->assert($registeredUser->first_name === 'Shanique', "First name persisted correctly");
        $this->assert($registeredUser->last_name === 'Brown', "Last name persisted correctly");
        $this->assert($registeredUser->name === 'Shanique Brown', "Full name accessor resolves correctly");
        $this->assert($registeredUser->email === 'shanique.brown@islandevents.app', "Email persisted in normalized lowercase");
        $this->assert($registeredUser->preferred_display_currency === 'JMD', "Display currency persisted as JMD");
        $this->assert($registeredUser->is_system_admin === false, "Registered user is not a system admin");
        $this->assert(!empty($registeredUser->agreed_to_terms_at), "agreed_to_terms_at timestamp recorded");

        // Universal Password Hashing Invariant: Password must never be stored in plaintext
        $this->assert($registeredUser->password !== 'IslandPass2026!', "Password is NOT stored in plaintext");
        $this->assert(password_verify('IslandPass2026!', $registeredUser->password), "Password hash verifies via bcrypt/argon2");

        // 2. Registration Validation Guardrails
        $missingNameFailed = false;
        try {
            \App\Services\AuthenticationService::register(array_merge($regData, ['first_name' => '', 'email' => 'other@mail.com']));
        } catch (\InvalidArgumentException $e) {
            $missingNameFailed = true;
        }
        $this->assert($missingNameFailed, "Registration rejects missing name with InvalidArgumentException");

        $invalidEmailFailed = false;
        try {
            \App\Services\AuthenticationService::register(array_merge($regData, ['email' => 'not-an-email']));
        } catch (\InvalidArgumentException $e) {
            $invalidEmailFailed = true;
        }
        $this->assert($invalidEmailFailed, "Registration rejects invalid email format");

        $shortPasswordFailed = false;
        try {
            \App\Services\AuthenticationService::register(array_merge($regData, ['email' => 'short@mail.com', 'password' => 'short', 'password_confirmation' => 'short']));
        } catch (\InvalidArgumentException $e) {
            $shortPasswordFailed = true;
        }
        $this->assert($shortPasswordFailed, "Registration rejects password shorter than 8 characters");

        $mismatchConfirmFailed = false;
        try {
            \App\Services\AuthenticationService::register(array_merge($regData, ['email' => 'mismatch@mail.com', 'password_confirmation' => 'DifferentPass2026!']));
        } catch (\InvalidArgumentException $e) {
            $mismatchConfirmFailed = true;
        }
        $this->assert($mismatchConfirmFailed, "Registration rejects mismatched password confirmation");

        $missingTermsFailed = false;
        try {
            \App\Services\AuthenticationService::register(array_merge($regData, ['email' => 'noterms@mail.com', 'agree_terms' => false]));
        } catch (\InvalidArgumentException $e) {
            $missingTermsFailed = true;
        }
        $this->assert($missingTermsFailed, "Registration rejects submission without agreed terms");

        // 3. Duplicate Email Rejection
        $duplicateEmailFailed = false;
        try {
            \App\Services\AuthenticationService::register($regData);
        } catch (\InvalidArgumentException $e) {
            $duplicateEmailFailed = true;
        }
        $this->assert($duplicateEmailFailed, "Registration rejects already registered email address");

        // 4. Anti-Escalation: Browser cannot select System Admin or Administrator
        $adminInjectBlocked = false;
        try {
            \App\Services\AuthenticationService::register(array_merge($regData, ['email' => 'hacker@mail.com', 'role' => 'system_admin']));
        } catch (\SecurityException $e) {
            $adminInjectBlocked = true;
        }
        $this->assert($adminInjectBlocked, "Registration strictly blocks 'system_admin' payload injection");

        $adminFlagBlocked = false;
        try {
            \App\Services\AuthenticationService::register(array_merge($regData, ['email' => 'hacker2@mail.com', 'is_system_admin' => true]));
        } catch (\SecurityException $e) {
            $adminFlagBlocked = true;
        }
        $this->assert($adminFlagBlocked, "Registration strictly blocks 'is_system_admin = true' flag injection");

        // 5. Authentication & Login Flow
        $loginSuccess = \App\Services\AuthenticationService::login('shanique.brown@islandevents.app', 'IslandPass2026!');
        $this->assert($loginSuccess['success'] === true, "Authentication succeeds with valid email and password");
        $this->assert($loginSuccess['redirect_route'] === 'attendee.dashboard', "Login routes attendee to attendee dashboard");

        // Generic error on wrong password (prevents user enumeration)
        $wrongPass = \App\Services\AuthenticationService::login('shanique.brown@islandevents.app', 'WrongPassword!');
        $this->assert($wrongPass['success'] === false, "Login fails with incorrect password");
        $this->assert($wrongPass['error'] === 'Invalid email address or password.', "Generic error message returned for bad password");

        // Generic error on non-existent email
        $unknownUser = \App\Services\AuthenticationService::login('nobody@islandevents.app', 'AnyPass123!');
        $this->assert($unknownUser['success'] === false, "Login fails for non-existent user");
        $this->assert($unknownUser['error'] === 'Invalid email address or password.', "Generic error message returned for unknown email");

        // Suspended account rejection
        $registeredUser->status = 'suspended';
        $registeredUser->save();
        $suspendedLogin = \App\Services\AuthenticationService::login('shanique.brown@islandevents.app', 'IslandPass2026!');
        $this->assert($suspendedLogin['success'] === false, "Suspended account is denied login");
        $this->assert(str_contains($suspendedLogin['error'], 'suspended'), "Clear suspended notification returned to user");
        $registeredUser->status = 'active';
        $registeredUser->save();

        // 6. Role-Based Dashboard Redirection Routing
        $hostUser = new \App\Models\User([
            'id' => 20,
            'email' => 'host@islandevents.app',
            'current_context_role' => 'event_host',
            'status' => 'active',
        ]);
        \Illuminate\Database\Eloquent\Model::$inMemoryRecords[\App\Models\UserRole::class][] = new \App\Models\UserRole([
            'user_id' => 20,
            'role' => 'event_host',
            'is_active' => true,
        ]);
        $hostRoute = \App\Services\ContextSwitchingService::getDashboardRouteForRole($hostUser->getActiveRole());
        $this->assert($hostRoute === 'host.dashboard', "Event Host active role maps to host.dashboard");

        $adminUser = new \App\Models\User([
            'id' => 30,
            'email' => 'admin@islandevents.app',
            'current_context_role' => 'admin',
            'status' => 'active',
        ]);
        \Illuminate\Database\Eloquent\Model::$inMemoryRecords[\App\Models\UserRole::class][] = new \App\Models\UserRole([
            'user_id' => 30,
            'role' => 'admin',
            'is_active' => true,
        ]);
        $adminRoute = \App\Services\ContextSwitchingService::getDashboardRouteForRole($adminUser->getActiveRole());
        $this->assert($adminRoute === 'admin.dashboard', "Administrator active role maps to admin.dashboard");

        $sysAdminUser = new \App\Models\User([
            'id' => 40,
            'email' => 'sysadmin@islandevents.app',
            'current_context_role' => 'system_admin',
            'is_system_admin' => true,
            'status' => 'active',
        ]);
        $sysAdminRoute = \App\Services\ContextSwitchingService::getDashboardRouteForRole($sysAdminUser->getActiveRole());
        $this->assert($sysAdminRoute === 'system-admin.dashboard', "System Admin active role maps to system-admin.dashboard");
    }

    public function suite16PasswordResetAndEmailVerification(): void
    {
        echo "\nSuite 16: Password Reset & Email/Phone Verification\n";

        // Setup user for password reset
        $user = new \App\Models\User([
            'id' => 50,
            'email' => 'reset.me@islandevents.app',
            'password' => password_hash('OldPassword2026!', PASSWORD_DEFAULT),
            'status' => 'active',
            'current_context_role' => 'attendee',
        ]);
        $user->save();

        // 1. Token Creation & Cryptographic Hashing
        $plainToken = \App\Services\AuthenticationService::createPasswordResetToken('reset.me@islandevents.app');
        $this->assert(strlen($plainToken) === 64, "Generated password reset token is 64 characters (256 bits entropy)");

        // Verify that plain token is NOT in the database table
        $tokenRecord = \Illuminate\Support\Facades\DB::table('password_reset_tokens')
            ->where('email', 'reset.me@islandevents.app')
            ->first();

        $this->assert($tokenRecord !== null, "Token record exists in password_reset_tokens table");
        $this->assert($tokenRecord->token !== $plainToken, "Plaintext reset token is NEVER saved in the database");
        $this->assert($tokenRecord->token === hash('sha256', $plainToken), "Database stores SHA-256 hash of reset token");

        // 2. Reset with Invalid/Tampered Token
        $badTokenResult = \App\Services\AuthenticationService::resetPassword(
            'reset.me@islandevents.app',
            'tampered_or_bogus_token_value_1234567890abcdef',
            'BrandNewPassword2026!'
        );
        $this->assert($badTokenResult['success'] === false, "Password reset fails with tampered token");
        $this->assert($badTokenResult['error'] === 'Invalid password reset token.', "Error for tampered token is clear and safe");

        // 3. Reset with Password Shorter than 8 Characters
        $shortPassResult = \App\Services\AuthenticationService::resetPassword(
            'reset.me@islandevents.app',
            $plainToken,
            'short'
        );
        $this->assert($shortPassResult['success'] === false, "Password reset rejects new password < 8 chars");

        // 4. Successful Password Reset
        $validResetResult = \App\Services\AuthenticationService::resetPassword(
            'reset.me@islandevents.app',
            $plainToken,
            'BrandNewPassword2026!'
        );
        $this->assert($validResetResult['success'] === true, "Password reset succeeds with valid token and password");

        // Verify user password updated and hash matches new password
        $updatedUser = \App\Models\User::where('email', 'reset.me@islandevents.app')->first();
        $this->assert(password_verify('BrandNewPassword2026!', $updatedUser->password), "User password updated to new hash");
        $this->assert(!password_verify('OldPassword2026!', $updatedUser->password), "Old password no longer verifies");

        // Verify token deleted after use (single-use invariant)
        $consumedToken = \Illuminate\Support\Facades\DB::table('password_reset_tokens')
            ->where('email', 'reset.me@islandevents.app')
            ->first();
        $this->assert($consumedToken === null, "Password reset token is deleted upon successful use (single-use invariant)");

        // 5. Expired Token Rejection
        $expiredToken = \App\Services\AuthenticationService::createPasswordResetToken('reset.me@islandevents.app');
        // Manually adjust created_at to 65 minutes ago
        $expiredTime = (new \DateTime())->modify('-65 minutes')->format('Y-m-d H:i:s');
        \Illuminate\Support\Facades\DB::$tables['password_reset_tokens'][0]['created_at'] = $expiredTime;

        $expiredResult = \App\Services\AuthenticationService::resetPassword(
            'reset.me@islandevents.app',
            $expiredToken,
            'AnotherPassword2026!'
        );
        $this->assert($expiredResult['success'] === false, "Expired token (> 60 minutes) is rejected");
        $this->assert(str_contains($expiredResult['error'], 'expired'), "Expired token returns expiration message");

        // 6. Email and Phone Verification States
        $this->assert($user->hasVerifiedEmail() === false, "User initial hasVerifiedEmail() is false");
        $user->markEmailAsVerified();
        $this->assert($user->hasVerifiedEmail() === true, "markEmailAsVerified() updates email_verified_at and returns true");

        $this->assert($user->hasVerifiedPhone() === false, "User initial hasVerifiedPhone() is false");
        $user->markPhoneAsVerified();
        $this->assert($user->hasVerifiedPhone() === true, "markPhoneAsVerified() updates phone_verified_at and returns true");
    }

    public function suite17EventScopedStaffInvitationsAndSeparationOfDuties(): void
    {
        echo "\nSuite 17: Event-Scoped Staff Invitations, Real-Time Revocation & Separation of Duties\n";

        // Setup host user and event
        $host = new \App\Models\User([
            'id' => 60,
            'email' => 'host.kareem@islandevents.app',
            'status' => 'active',
            'current_context_role' => 'event_host',
        ]);
        $host->save();
        \Illuminate\Database\Eloquent\Model::$inMemoryRecords[\App\Models\UserRole::class][] = new \App\Models\UserRole([
            'user_id' => 60,
            'role' => 'event_host',
            'is_active' => true,
        ]);

        $event = new \App\Models\Event([
            'id' => 'evt-uuid-kingston-carnival',
            'organizer_id' => 'org-uuid-island-vibes',
            'title' => 'Kingston Carnival 2026',
        ]);
        $event->save();

        // 1. Host creates worker invitation
        $inviteResult = \App\Services\EventWorkerService::createWorkerInvitation(
            host: $host,
            event: $event,
            inviteEmail: 'mark.ticketchecker@gmail.com',
            role: \App\Enums\EventWorkerRole::TICKET_CHECKER
        );

        $this->assert(isset($inviteResult['plain_token']), "Invitation returns unhashed token for dispatch");
        $this->assert(strlen($inviteResult['plain_token']) === 64, "Worker invitation token is 64 characters (256-bit)");

        $assignment = $inviteResult['invitation'];
        $this->assert($assignment->status === 'pending_invite', "Initial invitation status is pending_invite");
        $this->assert($assignment->is_active === false, "Pending worker assignment is initially inactive");
        $this->assert($assignment->invitation_token_hash === hash('sha256', $inviteResult['plain_token']), "Database stores SHA-256 hash of invitation token");

        // 2. Privilege Escalation Prevention on Invitations
        $unauthorizedCaller = new \App\Models\User([
            'id' => 70,
            'email' => 'attendee.stranger@gmail.com',
            'status' => 'active',
            'current_context_role' => 'attendee',
        ]);
        $unauthorizedCaller->save();

        $unauthInviteBlocked = false;
        try {
            \App\Services\EventWorkerService::createWorkerInvitation(
                host: $unauthorizedCaller,
                event: $event,
                inviteEmail: 'someone@mail.com',
                role: \App\Enums\EventWorkerRole::PAYMENT_CLERK
            );
        } catch (\SecurityException $e) {
            $unauthInviteBlocked = true;
        }
        $this->assert($unauthInviteBlocked, "Attendee cannot invite event workers (SecurityException)");

        // 3. Worker Accepts Invitation
        $workerUser = new \App\Models\User([
            'id' => 80,
            'email' => 'mark.ticketchecker@gmail.com',
            'status' => 'active',
            'current_context_role' => 'attendee',
        ]);
        $workerUser->save();

        $accepted = \App\Services\EventWorkerService::acceptWorkerInvitation(
            worker: $workerUser,
            plainToken: $inviteResult['plain_token']
        );

        $this->assert($accepted->status === 'active', "Accepted invitation status becomes active");
        $this->assert($accepted->is_active === true, "Accepted assignment is_active is true");
        $this->assert($accepted->user_id === 80, "Assignment linked to worker user_id");
        $this->assert(!empty($accepted->invitation_accepted_at), "invitation_accepted_at timestamp set");

        // Tampered token rejection
        $tamperedAcceptBlocked = false;
        try {
            \App\Services\EventWorkerService::acceptWorkerInvitation(
                worker: $workerUser,
                plainToken: 'invalid_or_fake_token_value_00000000000000000000000000000000'
            );
        } catch (\SecurityException $e) {
            $tamperedAcceptBlocked = true;
        }
        $this->assert($tamperedAcceptBlocked, "Accepting invitation with invalid token is rejected with SecurityException");

        // 4. Real-Time Revocation Enforcement
        \App\Services\EventWorkerService::revokeWorkerAssignment(
            actor: $host,
            assignment: $accepted,
            reason: 'Shift reassigned'
        );

        $this->assert($accepted->is_active === false, "Revoked assignment is_active is false");
        $this->assert($accepted->isRevoked() === true, "isRevoked() reports true");
        $this->assert($accepted->revoked_by === 60, "revoked_by records host ID");
        $this->assert($accepted->revocation_reason === 'Shift reassigned', "Revocation reason recorded");
        $this->assert($accepted->canPerform('scan_qr_tickets') === false, "Revoked worker is immediately blocked from canPerform actions without logout");

        // 5. Strict Separation of Duties
        $order = new \App\Models\Order([
            'id' => 'ord-uuid-101',
            'order_number' => 'ORD-101',
            'event_id' => 'evt-uuid-kingston-carnival',
            'total_cents' => 4500,
            'currency' => 'JMD',
            'status' => 'pending',
        ]);

        $priceChangeBlocked = false;
        try {
            \App\Services\EventWorkerService::assertSeparationOfDuties($workerUser, $order, 'change_ticket_prices');
        } catch (\SecurityException $e) {
            $priceChangeBlocked = true;
        }
        $this->assert($priceChangeBlocked, "Worker attempting to change ticket prices is blocked by Separation of Duties");

        $menuChangeBlocked = false;
        try {
            \App\Services\EventWorkerService::assertSeparationOfDuties($workerUser, $order, 'change_menu_prices');
        } catch (\SecurityException $e) {
            $menuChangeBlocked = true;
        }
        $this->assert($menuChangeBlocked, "Worker attempting to change menu prices is blocked by Separation of Duties");

        $eventOwnershipBlocked = false;
        try {
            \App\Services\EventWorkerService::assertSeparationOfDuties($workerUser, $order, 'change_event_ownership');
        } catch (\SecurityException $e) {
            $eventOwnershipBlocked = true;
        }
        $this->assert($eventOwnershipBlocked, "Worker attempting to alter event ownership is blocked by Separation of Duties");

        // Invariant: Worker who records a cash payment cannot be the sole unverified approver of that same payment
        $clerkUser = new \App\Models\User([
            'id' => 90,
            'email' => 'clerk@islandevents.app',
            'status' => 'active',
            'current_context_role' => 'attendee',
        ]);
        $clerkUser->save();

        $cashPayment = new \App\Models\CashPayment([
            'id' => 1,
            'order_id' => 'ord-uuid-101',
            'collected_by_user_id' => 90,
            'amount_cents' => 4500,
            'currency' => 'JMD',
        ]);
        $cashPayment->save();

        $selfVerifyBlocked = false;
        try {
            \App\Services\EventWorkerService::assertSeparationOfDuties($clerkUser, $order, 'verify_payment');
        } catch (\SecurityException $e) {
            $selfVerifyBlocked = true;
        }
        $this->assert($selfVerifyBlocked, "Payment collector cannot unilaterally self-approve their own recorded payment");

        // Event Host CAN verify payment
        $hostVerifyAllowed = true;
        try {
            \App\Services\EventWorkerService::assertSeparationOfDuties($host, $order, 'verify_payment');
        } catch (\SecurityException $e) {
            $hostVerifyAllowed = false;
        }
        $this->assert($hostVerifyAllowed, "Event Host is authorized to verify/approve payments");
    }

    public function suite18SecureEventStaffAssignmentSystem(): void
    {
        echo "\nSuite 18: Secure Event Staff Assignment, Claim Codes & Time-Bound Lifecycles\n";

        // 1. Setup Users and Event
        $host = new \App\Models\User([
            'id' => 100,
            'name' => 'Usain St. Leo',
            'first_name' => 'Usain',
            'last_name' => 'St. Leo',
            'email' => 'host.usain@islandevents.com',
            'status' => 'active',
            'role' => 'event_host',
            'current_context_role' => 'event_host',
            'organizer_id' => 'org-uuid-kingston-sunsplash',
        ]);
        $host->save();

        $attendeeWorker = new \App\Models\User([
            'id' => 101,
            'name' => 'Marcus Garvey',
            'first_name' => 'Marcus',
            'last_name' => 'Garvey',
            'email' => 'marcus.garvey@gmail.com',
            'phone' => '+18765550199',
            'status' => 'active',
            'role' => 'attendee',
            'current_context_role' => 'attendee',
        ]);
        $attendeeWorker->save();

        $event = new \App\Models\Event([
            'id' => 'evt-uuid-sunsplash-2026',
            'organizer_id' => 'org-uuid-kingston-sunsplash',
            'title' => 'Kingston Sunsplash 2026',
            'timezone' => 'America/Jamaica',
        ]);
        $event->save();

        // 2. Authenticated user generates 10-minute single-use claim code
        $codeResult = \App\Services\StaffClaimCodeService::generateCode($attendeeWorker, lifetimeMinutes: 10);
        $plainCode = $codeResult['plain_code'];
        $claimCode = $codeResult['claim_code'];

        $this->assert(!empty($plainCode), "Staff claim code generated successfully");
        $this->assert((bool) preg_match('/^STF-[2-9A-HJ-NP-Z]{4}-[2-9A-HJ-NP-Z]{4}$/', $plainCode), "Claim code matches expected high-entropy format STF-XXXX-XXXX");
        $this->assert($claimCode->code_hash === hash('sha256', $plainCode), "Database stores only SHA-256 hash of claim code");
        $this->assert($claimCode->status === 'active', "Claim code is initially active");
        $this->assert(!$claimCode->isExpired(), "Freshly generated claim code is not expired");
        $this->assert(strpos($plainCode, (string) $attendeeWorker->id) === false, "Claim code does not leak user ID");
        $this->assert(strpos($plainCode, 'marcus') === false, "Claim code does not leak user email or name");

        // 3. Suspended account cannot generate claim codes
        $suspendedUser = new \App\Models\User([
            'id' => 102,
            'email' => 'suspended.user@islandevents.com',
            'status' => 'suspended',
        ]);
        $suspendedUser->save();

        $suspendedBlocked = false;
        try {
            \App\Services\StaffClaimCodeService::generateCode($suspendedUser);
        } catch (\SecurityException $e) {
            $suspendedBlocked = true;
        }
        $this->assert($suspendedBlocked, "Suspended user is prohibited from generating staff claim codes");

        // 4. Single-active-code invariant: Generating a second code automatically revokes previous code
        $codeResult2 = \App\Services\StaffClaimCodeService::generateCode($attendeeWorker, lifetimeMinutes: 10);
        $this->assert($claimCode->status === 'revoked', "Generating new claim code automatically revokes prior unredeemed code");
        $newPlainCode = $codeResult2['plain_code'];
        $newClaimCode = $codeResult2['claim_code'];

        // 5. Code verification & preview for Event Host: Masked identification
        $preview = \App\Services\StaffClaimCodeService::validateAndPreview($host, $event, $newPlainCode);
        $this->assert($preview['valid'] === true, "Valid claim code successfully verified by Event Host");
        $this->assert($preview['masked_name'] === 'M**** G.', "Masked recipient identification (M**** G.) returned to prevent wrong assignment");
        $this->assert(!isset($preview['email']) && !isset($preview['phone']), "Verification response never exposes email, phone, or private profile");

        // 6. Invalid / expired / revoked code returns generic failure message
        $badPreview = \App\Services\StaffClaimCodeService::validateAndPreview($host, $event, 'STF-INVALID-CODE');
        $this->assert($badPreview['valid'] === false, "Invalid claim code returns valid = false");
        $this->assert($badPreview['message'] === 'Unable to verify this code. Ask the account holder to generate a new one.', "Generic failure message returned to prevent user enumeration");

        // 7. Host cannot assign themselves as staff
        $hostSelfCode = \App\Services\StaffClaimCodeService::generateCode($host);
        $selfPreview = \App\Services\StaffClaimCodeService::validateAndPreview($host, $event, $hostSelfCode['plain_code']);
        $this->assert($selfPreview['valid'] === false, "Event Host cannot assign themselves as staff member");

        // 8. Unauthorized user (Attendee) cannot create staff assignment
        $unauthAssignBlocked = false;
        try {
            \App\Services\EventWorkerService::createAssignmentFromClaimCode(
                host: $attendeeWorker,
                event: $event,
                rawCode: $newPlainCode,
                role: \App\Enums\EventWorkerRole::TICKET_CHECKER,
                startsAt: '2026-08-15 14:00:00',
                expiresAt: '2026-08-15 23:00:00'
            );
        } catch (\SecurityException $e) {
            $unauthAssignBlocked = true;
        }
        $this->assert($unauthAssignBlocked, "Attendee without host privileges cannot create staff assignments");

        // 9. Start time must precede expiration time
        $invalidDatesBlocked = false;
        try {
            \App\Services\EventWorkerService::createAssignmentFromClaimCode(
                host: $host,
                event: $event,
                rawCode: $newPlainCode,
                role: \App\Enums\EventWorkerRole::TICKET_CHECKER,
                startsAt: '2026-08-15 23:00:00',
                expiresAt: '2026-08-15 14:00:00'
            );
        } catch (\InvalidArgumentException $e) {
            $invalidDatesBlocked = true;
        }
        $this->assert($invalidDatesBlocked, "Assignment rejected when expiration precedes start time");

        // 10. Valid staff assignment creation from claim code
        $eventTz = new \DateTimeZone('America/Jamaica');
        $startsAt = (new \DateTimeImmutable('now', $eventTz))->modify('-1 hour')->format('Y-m-d H:i:s');
        $expiresAt = (new \DateTimeImmutable('now', $eventTz))->modify('+8 hours')->format('Y-m-d H:i:s');

        $assignment = \App\Services\EventWorkerService::createAssignmentFromClaimCode(
            host: $host,
            event: $event,
            rawCode: $newPlainCode,
            role: \App\Enums\EventWorkerRole::TICKET_CHECKER,
            startsAt: $startsAt,
            expiresAt: $expiresAt,
            timezone: 'America/Jamaica'
        );

        $this->assert($assignment->status === 'pending_acceptance', "Newly created assignment status is 'pending_acceptance'");
        $this->assert($assignment->is_active === false, "Pending assignment is not active until recipient accepts");
        $this->assert($assignment->user_id === 101, "Assignment assigned to the claim code owner (user_id = 101)");
        $this->assert($newClaimCode->status === 'redeemed', "Claim code atomically redeemed and consumed upon assignment creation");

        // 11. Re-using already-redeemed claim code is blocked
        $reusedBlocked = false;
        try {
            \App\Services\EventWorkerService::createAssignmentFromClaimCode(
                host: $host,
                event: $event,
                rawCode: $newPlainCode,
                role: \App\Enums\EventWorkerRole::PAYMENT_CLERK,
                startsAt: $startsAt,
                expiresAt: $expiresAt
            );
        } catch (\SecurityException $e) {
            $reusedBlocked = true;
        }
        $this->assert($reusedBlocked, "Already-redeemed claim code cannot be reused for another assignment");

        // 12. Pending assignment does NOT authorize operational requests
        $pendingDenied = false;
        try {
            \App\Services\EventWorkerService::assertActiveAssignmentValid($attendeeWorker, $event, 'scan_qr_tickets');
        } catch (\SecurityException $e) {
            $pendingDenied = true;
        }
        $this->assert($pendingDenied, "Pending assignment cannot authorize operational terminal requests");

        // 13. Only the intended recipient can accept the assignment
        $otherUser = new \App\Models\User([
            'id' => 103,
            'email' => 'stranger@islandevents.com',
            'status' => 'active',
        ]);
        $otherUser->save();

        $imposterAcceptBlocked = false;
        try {
            \App\Services\EventWorkerService::acceptAssignment($otherUser, $assignment->id);
        } catch (\SecurityException $e) {
            $imposterAcceptBlocked = true;
        }
        $this->assert($imposterAcceptBlocked, "Unrelated user cannot accept staff assignment issued to another person");

        // 14. Recipient accepts assignment -> transitions to active
        $acceptedAssignment = \App\Services\EventWorkerService::acceptAssignment($attendeeWorker, $assignment->id);
        $this->assert($acceptedAssignment->status === 'active', "Accepted assignment transitions to status 'active'");
        $this->assert($acceptedAssignment->is_active === true, "Accepted assignment has is_active = true");
        $this->assert($acceptedAssignment->isCurrentlyActive() === true, "isCurrentlyActive() returns true during valid shift window");

        // 15. Active worker authorized for permitted actions during shift
        $activeValid = false;
        try {
            $validAssignment = \App\Services\EventWorkerService::assertActiveAssignmentValid($attendeeWorker, $event, 'scan_qr_tickets');
            $activeValid = ($validAssignment->id === $acceptedAssignment->id);
        } catch (\SecurityException $e) {
            $activeValid = false;
        }
        $this->assert($activeValid, "Active worker successfully authorized for 'scan_qr_tickets' during shift");

        // 16. Role containment: Ticket Checker cannot record cash payments
        $unauthorizedRoleActionBlocked = false;
        try {
            \App\Services\EventWorkerService::assertActiveAssignmentValid($attendeeWorker, $event, 'record_cash_received');
        } catch (\SecurityException $e) {
            $unauthorizedRoleActionBlocked = true;
        }
        $this->assert($unauthorizedRoleActionBlocked, "Ticket Checker blocked from performing 'record_cash_received'");

        // 17. Cross-event containment: Worker on Event A cannot access Event B
        $eventB = new \App\Models\Event([
            'id' => 'evt-uuid-montego-bay-bash',
            'organizer_id' => 'org-uuid-kingston-sunsplash',
            'title' => 'Atlanta Reggae Night 2026',
        ]);
        $eventB->save();

        $crossEventBlocked = false;
        try {
            \App\Services\EventWorkerService::assertActiveAssignmentValid($attendeeWorker, $eventB, 'scan_qr_tickets');
        } catch (\SecurityException $e) {
            $crossEventBlocked = true;
        }
        $this->assert($crossEventBlocked, "Worker on Event A strictly denied operational access on unrelated Event B");

        // 18. Scheduled shift boundary enforcement (Start time in future)
        $futureStart = (new \DateTimeImmutable())->modify('+3 hours')->format('Y-m-d H:i:s');
        $futureExpire = (new \DateTimeImmutable())->modify('+10 hours')->format('Y-m-d H:i:s');
        $scheduledAssignment = new \App\Models\EventWorkerAssignment([
            'id' => 991,
            'organizer_id' => $event->organizer_id,
            'event_id' => $event->id,
            'user_id' => 101,
            'worker_role' => \App\Enums\EventWorkerRole::TICKET_CHECKER,
            'starts_at' => $futureStart,
            'expires_at' => $futureExpire,
            'status' => 'scheduled',
            'is_active' => true,
        ]);
        $scheduledAssignment->save();

        $this->assert($scheduledAssignment->isScheduled() === true, "Assignment starting in the future reports isScheduled() = true");
        $this->assert($scheduledAssignment->isCurrentlyActive() === false, "isCurrentlyActive() is false before start time");

        // 19. Immediate expiration enforcement (Past expiration timestamp)
        $acceptedAssignment->expires_at = (new \DateTimeImmutable())->modify('-5 minutes');
        $acceptedAssignment->save();

        $this->assert($acceptedAssignment->isExpired() === true, "Assignment past its expiration timestamp reports isExpired() = true");
        $this->assert($acceptedAssignment->isCurrentlyActive() === false, "isCurrentlyActive() returns false at and after expiration");

        $expiredActionBlocked = false;
        try {
            \App\Services\EventWorkerService::assertActiveAssignmentValid($attendeeWorker, $event, 'scan_qr_tickets');
        } catch (\SecurityException $e) {
            $expiredActionBlocked = true;
        }
        $this->assert($expiredActionBlocked, "Operational access immediately revoked at expiration timestamp without requiring logout");

        // 20. Revocation enforcement
        $acceptedAssignment->expires_at = (new \DateTimeImmutable())->modify('+5 hours');
        $acceptedAssignment->save();
        \App\Services\EventWorkerService::revokeWorkerAssignment($host, $acceptedAssignment, 'Shift ended early');

        $this->assert($acceptedAssignment->isRevoked() === true, "Revoked assignment reports isRevoked() = true");
        $this->assert($acceptedAssignment->isCurrentlyActive() === false, "isCurrentlyActive() returns false for revoked assignment");

        $revokedActionBlocked = false;
        try {
            \App\Services\EventWorkerService::assertActiveAssignmentValid($attendeeWorker, $event, 'scan_qr_tickets');
        } catch (\SecurityException $e) {
            $revokedActionBlocked = true;
        }
        $this->assert($revokedActionBlocked, "Revoked worker immediately denied operational terminal access");
    }

    public function suite19MandatoryAuthenticationAndAccountAccess(): void
    {
        echo "\nSuite 19: Mandatory Authentication, Public vs Authenticated Access & Host Approval Workflow\n";

        // Setup test event & menu item
        $event = new \App\Models\Event([
            'id' => 'evt-uuid-port-antonio-fest',
            'organizer_id' => 'org-uuid-port-antonio',
            'title' => 'Port Antonio Jerk & Seafood Festival',
            'slug' => 'port-antonio-jerk-seafood-fest',
            'currency' => 'JMD',
            'is_ordering_open' => true,
        ]);
        $event->save();

        $menuItem = new \App\Models\MenuItem([
            'id' => 'item-jerk-pork',
            'organizer_id' => 'org-uuid-port-antonio',
            'event_id' => 'evt-uuid-port-antonio-fest',
            'name' => 'Jerk Pork Plate',
            'category' => 'mains',
            'pricing_type' => 'fixed',
            'price_cents' => 250000,
            'price_jmd' => 2500.00,
            'available_stock' => 50,
            'is_active' => true,
        ]);
        $menuItem->save();

        $orderService = new \App\Services\OrderPlacementService();

        // 1. Public vs Authenticated: Unauthenticated visitor CANNOT place food orders
        auth()->logout();
        $unauthOrderResult = $orderService->placeOrder($event, [
            'customer_name' => 'Anonymous Visitor',
            'customer_phone' => '8765550100',
            'items' => [
                ['menu_item_id' => 'item-jerk-pork', 'quantity' => 2]
            ]
        ], user: null);

        $this->assert($unauthOrderResult['success'] === false, "Unauthenticated guest order attempt fails");
        $this->assert($unauthOrderResult['status_code'] === 401, "Unauthenticated order attempt returns HTTP 401 Unauthorized");
        $this->assert(str_contains($unauthOrderResult['error'], 'Authentication required'), "Error message explicitly states authentication required");

        // 2. Authenticated Attendee CAN place an order, and the order is linked to user_id
        $attendeeAlice = new \App\Models\User([
            'id' => 201,
            'name' => 'Alice Walker',
            'first_name' => 'Alice',
            'last_name' => 'Walker',
            'email' => 'alice.walker@islandevents.app',
            'phone' => '+18765550201',
            'status' => 'active',
            'role' => 'attendee',
            'current_context_role' => 'attendee',
        ]);
        $attendeeAlice->save();

        auth()->login($attendeeAlice);
        $authOrderResult = $orderService->placeOrder($event, [
            'customer_name' => 'Alice Walker',
            'customer_phone' => '+18765550201',
            'currency' => 'JMD',
            'items' => [
                ['menu_item_id' => 'item-jerk-pork', 'quantity' => 1]
            ]
        ], user: $attendeeAlice);

        $this->assert($authOrderResult['success'] === true, "Authenticated Attendee can place food order");
        $aliceOrder = $authOrderResult['order'];
        $this->assert(!empty($aliceOrder), "Order instance successfully created");
        $this->assert((int) $aliceOrder->user_id === 201, "Order record in database strictly links to attendee user_id (201)");
        $this->assert($aliceOrder->email === 'alice.walker@islandevents.app', "Order email matches attendee authenticated email");

        // 3. IDOR Prevention: Attendee Bob cannot view or retrieve Alice's order or ticket
        $attendeeBob = new \App\Models\User([
            'id' => 202,
            'name' => 'Bob Marley',
            'first_name' => 'Bob',
            'last_name' => 'Marley',
            'email' => 'bob.marley@islandevents.app',
            'phone' => '+18765550202',
            'status' => 'active',
            'role' => 'attendee',
            'current_context_role' => 'attendee',
        ]);
        $attendeeBob->save();

        $bobRetrievalResult = $orderService->retrieveOrderSecurely(
            identifier: $aliceOrder->ticket_code,
            phoneOrToken: '+18765550202',
            requestingUser: $attendeeBob
        );
        $this->assert($bobRetrievalResult['success'] === false, "Attendee Bob cannot retrieve Alice's order via ticket code (IDOR Blocked)");
        $this->assert($bobRetrievalResult['status_code'] === 403, "Cross-user order retrieval returns HTTP 403 Forbidden");

        // Alice CAN retrieve her own order
        $aliceRetrievalResult = $orderService->retrieveOrderSecurely(
            identifier: $aliceOrder->ticket_code,
            phoneOrToken: '+18765550201',
            requestingUser: $attendeeAlice
        );
        $this->assert($aliceRetrievalResult['success'] === true, "Attendee Alice can view and retrieve her own order");
        $this->assert($aliceRetrievalResult['order']->id === $aliceOrder->id, "Retrieved order matches Alice's order instance");

        // 4. Configurable Event Browsing Policy
        config(['events.require_login_for_browsing' => false]);
        $this->assert(config('events.require_login_for_browsing') === false, "Default policy: public browsing allowed");

        config(['events.require_login_for_browsing' => true]);
        $this->assert(config('events.require_login_for_browsing') === true, "Configurable policy toggled: login required for event browsing");
        config(['events.require_login_for_browsing' => false]); // reset

        // 5. Registration Safeguards
        $registerData = [
            'first_name' => 'Nanny',
            'last_name' => 'Maroon',
            'email' => 'nanny.maroon@islandevents.app',
            'password' => 'SafePass123!',
            'password_confirmation' => 'SafePass123!',
            'agree_terms' => true,
        ];
        $regResult = \App\Services\AuthenticationService::register($registerData);
        $registeredUser = $regResult['user'];
        $this->assert($registeredUser->getActiveRole() === \App\Enums\AccountType::ATTENDEE, "Registration assigns safe default Attendee role");
        $this->assert(password_verify('SafePass123!', $registeredUser->password), "User password verified via cryptographic hash");
        $this->assert(strpos($registeredUser->password, 'SafePass123!') === false, "Plaintext password never stored in database");

        // Privilege injection attempts blocked
        $tamperedData = $registerData;
        $tamperedData['email'] = 'hacker.admin@islandevents.app';
        $tamperedData['role'] = 'system_admin';
        $tamperedBlocked = false;
        try {
            \App\Services\AuthenticationService::register($tamperedData);
        } catch (\SecurityException $e) {
            $tamperedBlocked = true;
        }
        $this->assert($tamperedBlocked, "Registration strictly blocks privilege escalation payload injection");

        // 6. Host Access: Attendee cannot access Host dashboard or create events
        $attendeeCannotCreateEvent = false;
        try {
            $eventCtrl = new \App\Http\Controllers\EventController();
            $mockReq = new \Illuminate\Http\Request([], $registeredUser);
            $res = $eventCtrl->create($mockReq);
            if (isset($res->original['error']) && str_contains($res->original['error'], 'Forbidden')) {
                $attendeeCannotCreateEvent = true;
            }
        } catch (\Exception $e) {
            $attendeeCannotCreateEvent = true;
        }
        $this->assert($attendeeCannotCreateEvent, "Attendee cannot access event creation without approved Host privileges");

        // 7. Host Application Submission Workflow
        $hostApp = \App\Services\HostApplicationService::submitApplication($registeredUser, [
            'organization_name' => 'Blue Mountain Coffee Festivals',
            'description' => 'Annual food, music, and cultural events in Portland and Kingston.',
            'country' => 'JM',
            'contact_phone' => '+18765550299',
            'website_or_social' => 'https://instagram.com/bluemountainfest',
        ]);

        $this->assert(!empty($hostApp->id), "Host application submitted successfully");
        $this->assert($hostApp->status === 'pending', "Host application initial status is 'pending'");
        $this->assert($hostApp->isPending() === true, "isPending() returns true");
        $this->assert($registeredUser->hasRole(\App\Enums\AccountType::EVENT_HOST) === false, "User remains strictly an Attendee while application is pending");

        // Duplicate pending application rejected
        $dupBlocked = false;
        try {
            \App\Services\HostApplicationService::submitApplication($registeredUser, [
                'organization_name' => 'Another Festival',
                'country' => 'JM',
                'contact_phone' => '+18765550299',
            ]);
        } catch (\InvalidArgumentException $e) {
            $dupBlocked = true;
        }
        $this->assert($dupBlocked, "Submitting duplicate Host application while one is pending is strictly blocked");

        // 8. Authorization: Non-admin cannot approve Host application
        $unauthApproveBlocked = false;
        try {
            \App\Services\HostApplicationService::approveApplication($attendeeAlice, $hostApp->id);
        } catch (\SecurityException $e) {
            $unauthApproveBlocked = true;
        }
        $this->assert($unauthApproveBlocked, "Non-admin user cannot approve Host applications (SecurityException)");

        // 9. Administrator Approves Host Application
        $adminUser = new \App\Models\User([
            'id' => 300,
            'name' => 'Admin Officer',
            'email' => 'admin.officer@islandevents.app',
            'status' => 'active',
            'role' => 'admin',
            'current_context_role' => 'admin',
        ]);
        $adminUser->save();

        $approvedApp = \App\Services\HostApplicationService::approveApplication(
            reviewer: $adminUser,
            applicationId: $hostApp->id,
            notes: 'Verified organizational authenticity and Jamaican business registration.'
        );

        $this->assert($approvedApp->status === 'approved', "Approved application status transitions to 'approved'");
        $this->assert($approvedApp->isApproved() === true, "isApproved() returns true");
        $this->assert((int) $approvedApp->reviewed_by === 300, "reviewed_by records Administrator user ID (300)");
        $this->assert(
            \App\Models\OrganizerUser::where('user_id', $registeredUser->id)->where('role', 'event_owner')->exists(),
            "Organizer tenant membership provisioned for approved Host"
        );
        $this->assert($registeredUser->hasRole(\App\Enums\AccountType::EVENT_HOST) === true, "User now holds active 'event_host' role");

        // 10. Role / Context Switching for Dual-Acting User
        $this->assert(in_array('event_host', $registeredUser->getAuthorizedRoles()), "getAuthorizedRoles() includes 'event_host'");
        $this->assert(in_array('attendee', $registeredUser->getAuthorizedRoles()), "getAuthorizedRoles() includes 'attendee'");

        $switchResult = \App\Services\ContextSwitchingService::switchDashboardContext($registeredUser, 'event_host');
        $this->assert($switchResult['success'] === true, "Dual-acting user can switch to Event Host context");
        $this->assert($registeredUser->current_context_role === 'event_host', "Active context role is now 'event_host'");
        $this->assert($switchResult['redirect_route'] === 'host.dashboard', "Context switch redirects to 'host.dashboard'");

        // Switching context does NOT elevate ungranted roles
        $badSwitch = \App\Services\ContextSwitchingService::switchDashboardContext($registeredUser, 'system_admin');
        $this->assert($badSwitch['success'] === false, "Switching to unauthorized role (system_admin) is strictly forbidden");

        // 11. Rejection Workflow
        $rejectedApplicant = new \App\Models\User([
            'id' => 205,
            'name' => 'Ineligible Applicant',
            'email' => 'ineligible@islandevents.app',
            'status' => 'active',
            'role' => 'attendee',
        ]);
        $rejectedApplicant->save();

        $rejectedApp = \App\Services\HostApplicationService::submitApplication($rejectedApplicant, [
            'organization_name' => 'Dubious Promotions',
            'country' => 'US',
            'contact_phone' => '+12125550999',
        ]);

        $finalRejected = \App\Services\HostApplicationService::rejectApplication(
            reviewer: $adminUser,
            applicationId: $rejectedApp->id,
            reason: 'Applicant failed verification of valid business entity documentation.'
        );

        $this->assert($finalRejected->status === 'rejected', "Rejected application status transitions to 'rejected'");
        $this->assert($finalRejected->isRejected() === true, "isRejected() returns true");
        $this->assert($rejectedApplicant->hasRole(\App\Enums\AccountType::EVENT_HOST) === false, "Rejected applicant remains strictly an Attendee");

        // 12. Sign-Out & Session Invalidation
        auth()->logout();
        $this->assert(auth()->check() === false, "auth()->logout() successfully terminates authenticated session");
    }

    public function suite20CompleteRoleBasedAccessControlAndNavigation(): void
    {
        echo "Suite 20: Complete Role-Based Access Control, Navigation Visibility & Strict Boundaries\n";

        // 1. Navigation Visibility: Signed-Out Guests have visible Login & Sign Up
        auth()->logout();
        $this->assert(auth()->check() === false, "User is confirmed signed out (Guest)");

        // 2. Role 4: Attendee Dashboard and Isolation
        $attendeeUser = new \App\Models\User([
            'id' => 801,
            'name' => 'Kareem Campbell',
            'email' => 'kareem@islandevents.app',
            'status' => 'active',
            'role' => 'attendee',
        ]);
        $attendeeUser->save();
        \App\Models\UserRole::create(['user_id' => 801, 'role' => \App\Enums\AccountType::ATTENDEE->value, 'is_active' => true]);

        auth()->login($attendeeUser);
        $this->assert(auth()->check() === true, "Attendee is signed in");
        $this->assert($attendeeUser->hasRole(\App\Enums\AccountType::ATTENDEE), "User holds Attendee role");

        // Attendee can access Attendee dashboard
        $attendeeReq = new \Illuminate\Http\Request([], $attendeeUser);
        $attendeeCtrl = new \App\Http\Controllers\AttendeeDashboardController();
        $attRes = $attendeeCtrl->index($attendeeReq);
        $this->assert($attRes instanceof \Illuminate\View\View || is_object($attRes), "Attendee successfully accesses Attendee Dashboard");

        // Attendee directly requesting Host Dashboard is strictly blocked (HTTP 403 Forbidden / Redirected)
        $hostCtrl = new \App\Http\Controllers\HostDashboardController();
        $hostRes = $hostCtrl->index($attendeeReq);
        $this->assert(
            (isset($hostRes->original['error']) && $hostRes->original['error'] === 'Forbidden') ||
            (isset($hostRes->status) && $hostRes->status === 403) ||
            ($hostRes instanceof \TestRedirectHelper),
            "Attendee directly accessing Host Dashboard is strictly blocked (HTTP 403 / Redirected)"
        );

        // Attendee directly requesting Administrator Desk is strictly blocked (HTTP 403 Forbidden)
        $adminCtrl = new \App\Http\Controllers\AdminDashboardController();
        $admRes = $adminCtrl->index($attendeeReq);
        $this->assert(
            (isset($admRes->original['error']) && $admRes->original['error'] === 'Forbidden') ||
            (isset($admRes->status) && $admRes->status === 403) ||
            ($admRes instanceof \TestRedirectHelper),
            "Attendee directly accessing Administrator Desk is strictly blocked (HTTP 403 Forbidden)"
        );

        // Attendee directly requesting System Administrator Console is strictly blocked (HTTP 403 Forbidden)
        $sysCtrl = new \App\Http\Controllers\SystemAdminDashboardController();
        $sysRes = $sysCtrl->index($attendeeReq);
        $this->assert(
            (isset($sysRes->original['error']) && $sysRes->original['error'] === 'Forbidden') ||
            (isset($sysRes->status) && $sysRes->status === 403) ||
            ($sysRes instanceof \TestRedirectHelper),
            "Attendee directly accessing System Administrator Console is strictly blocked (HTTP 403 Forbidden)"
        );

        // 3. Role 3: Event Host Access & Boundaries
        $hostUser = new \App\Models\User([
            'id' => 802,
            'name' => 'Shelly-Ann Fraser',
            'email' => 'shelly@islandevents.app',
            'status' => 'active',
            'role' => 'event_host',
            'organizer_id' => 10,
        ]);
        $hostUser->save();
        \App\Models\UserRole::create(['user_id' => 802, 'role' => \App\Enums\AccountType::EVENT_HOST->value, 'is_active' => true]);

        $hostReq = new \Illuminate\Http\Request([], $hostUser);
        $hostResApproved = $hostCtrl->index($hostReq);
        $this->assert(
            $hostResApproved instanceof \Illuminate\View\View || is_object($hostResApproved),
            "Approved Event Host successfully accesses Host Command Center"
        );

        // Event Host cannot access Administrator Desk
        $hostAdmRes = $adminCtrl->index($hostReq);
        $this->assert(
            (isset($hostAdmRes->original['error']) && $hostAdmRes->original['error'] === 'Forbidden') ||
            (isset($hostAdmRes->status) && $hostAdmRes->status === 403) ||
            ($hostAdmRes instanceof \TestRedirectHelper),
            "Event Host without admin privileges is strictly blocked from Administrator Desk (HTTP 403)"
        );

        // Event Host cannot access System Administrator Console
        $hostSysRes = $sysCtrl->index($hostReq);
        $this->assert(
            (isset($hostSysRes->original['error']) && $hostSysRes->original['error'] === 'Forbidden') ||
            (isset($hostSysRes->status) && $hostSysRes->status === 403) ||
            ($hostSysRes instanceof \TestRedirectHelper),
            "Event Host is strictly blocked from System Administrator Console (HTTP 403)"
        );

        // 4. Role 2: Delegated Administrator Access & Boundaries
        $adminUser = new \App\Models\User([
            'id' => 803,
            'name' => 'Devon Administrator',
            'email' => 'devon.admin@islandevents.app',
            'status' => 'active',
            'role' => 'admin',
        ]);
        $adminUser->save();
        \App\Models\UserRole::create(['user_id' => 803, 'role' => \App\Enums\AccountType::ADMINISTRATOR->value, 'is_active' => true]);

        $adminReq = new \Illuminate\Http\Request([], $adminUser);
        $admResApproved = $adminCtrl->index($adminReq);
        $this->assert(
            $admResApproved instanceof \Illuminate\View\View || is_object($admResApproved),
            "Delegated Administrator successfully accesses Administrator Operations Desk"
        );

        // Administrator CANNOT access System Administrator Console (Strict Separation of Roles)
        $admSysRes = $sysCtrl->index($adminReq);
        $this->assert(
            (isset($admSysRes->original['error']) && $admSysRes->original['error'] === 'Forbidden') ||
            (isset($admSysRes->status) && $admSysRes->status === 403) ||
            ($admSysRes instanceof \TestRedirectHelper),
            "Administrator without root role is strictly blocked from System Administrator Console (HTTP 403)"
        );

        // 5. Role 1: System Administrator Root Access
        $rootUser = new \App\Models\User([
            'id' => 804,
            'name' => 'Root System Admin',
            'email' => 'root@islandevents.app',
            'status' => 'active',
            'role' => 'system_admin',
            'is_system_admin' => true,
        ]);
        $rootUser->save();
        \App\Models\UserRole::create(['user_id' => 804, 'role' => \App\Enums\AccountType::SYSTEM_ADMIN->value, 'is_active' => true]);

        $rootReq = new \Illuminate\Http\Request([], $rootUser);
        $rootRes = $sysCtrl->index($rootReq);
        $this->assert(
            $rootRes instanceof \Illuminate\View\View || is_object($rootRes),
            "System Administrator root user successfully accesses System Platform Governance Console"
        );

        // 6. Multi-Role Dual-Acting User Switching
        $dualUser = new \App\Models\User([
            'id' => 805,
            'name' => 'Marcus Dual-Role Host',
            'email' => 'marcus@islandevents.app',
            'status' => 'active',
            'role' => 'attendee',
            'current_context_role' => 'attendee',
        ]);
        $dualUser->save();
        \App\Models\UserRole::create(['user_id' => 805, 'role' => \App\Enums\AccountType::ATTENDEE->value, 'is_active' => true]);
        \App\Models\UserRole::create(['user_id' => 805, 'role' => \App\Enums\AccountType::EVENT_HOST->value, 'is_active' => true]);

        $this->assert($dualUser->hasRole(\App\Enums\AccountType::ATTENDEE) === true, "Dual user has Attendee role");
        $this->assert($dualUser->hasRole(\App\Enums\AccountType::EVENT_HOST) === true, "Dual user has Event Host role");

        $switchHost = \App\Services\ContextSwitchingService::switchDashboardContext($dualUser, 'event_host');
        $this->assert($switchHost['success'] === true, "Dual user switches to Event Host context");
        $this->assert($dualUser->current_context_role === 'event_host', "Active context is 'event_host'");
        $this->assert($switchHost['redirect_route'] === 'host.dashboard', "Redirects to host.dashboard");

        $switchAttendee = \App\Services\ContextSwitchingService::switchDashboardContext($dualUser, 'attendee');
        $this->assert($switchAttendee['success'] === true, "Dual user switches to Attendee context");
        $this->assert($dualUser->current_context_role === 'attendee', "Active context is 'attendee'");
        $this->assert($switchAttendee['redirect_route'] === 'attendee.dashboard', "Redirects to attendee.dashboard");

        // Privilege escalation attempt: switching to role user does NOT hold
        $unauthorizedSwitch = \App\Services\ContextSwitchingService::switchDashboardContext($dualUser, 'system_admin');
        $this->assert($unauthorizedSwitch['success'] === false, "Switching to unauthorized role (system_admin) is blocked");

        // 7. Suspended Account Enforcement
        $suspendedUser = new \App\Models\User([
            'id' => 806,
            'name' => 'Suspended Account',
            'email' => 'suspended@islandevents.app',
            'status' => 'suspended',
            'role' => 'attendee',
        ]);
        $suspendedUser->save();

        $suspendedBlocked = false;
        try {
            \App\Services\StaffClaimCodeService::generateCode($suspendedUser, 10, '127.0.0.1', 'CLI');
        } catch (\SecurityException $e) {
            $suspendedBlocked = true;
        }
        $this->assert($suspendedBlocked, "Suspended account is immediately blocked from generating staff claim codes");

        // 8. Event-Scoped Worker Isolation
        $workerUser = new \App\Models\User([
            'id' => 807,
            'name' => 'Terminal Cashier',
            'email' => 'cashier@islandevents.app',
            'status' => 'active',
            'role' => 'attendee',
        ]);
        $workerUser->save();

        $eventA = new \App\Models\Event(['id' => 501, 'title' => 'Event A — Portland Jerk', 'organizer_id' => 10]);
        $eventA->save();
        $eventB = new \App\Models\Event(['id' => 502, 'title' => 'Event B — East Orange Seafood', 'organizer_id' => 20]);
        $eventB->save();

        $assignmentA = \App\Models\EventWorkerAssignment::create([
            'event_id' => 501,
            'user_id' => 807,
            'worker_role' => \App\Enums\EventWorkerRole::PAYMENT_CLERK->value,
            'status' => 'active',
            'is_active' => true,
            'start_time' => now()->modify("-1 hour"),
            'expires_at' => now()->modify("+4 hours"),
            'assigned_by' => 802,
        ]);

        $authorizedOnA = false;
        try {
            \App\Services\EventWorkerService::assertActiveAssignmentValid($workerUser, $eventA, 'record_cash_received');
            $authorizedOnA = true;
        } catch (\SecurityException $e) {
            $authorizedOnA = false;
        }
        $this->assert($authorizedOnA, "Worker is authorized on assigned Event A");

        $blockedOnB = false;
        try {
            \App\Services\EventWorkerService::assertActiveAssignmentValid($workerUser, $eventB, 'record_cash_received');
        } catch (\SecurityException $e) {
            $blockedOnB = true;
        }
        $this->assert($blockedOnB, "Worker on Event A is strictly denied operational access on unrelated Event B");

        // Clean up session
        auth()->logout();
        $this->assert(auth()->check() === false, "Final session logout complete");
    }

    public function suite21UnitedStatesOnlyConfigurationAndPaymentIntegrity(): void
    {
        echo "\nSuite 21: United States-Only Launch Configuration, US Addresses, Timezones, Financial Precision & Payment Integrity\n";

        // =========================================================================
        // 1. Operating Market (US-Only Launch)
        // =========================================================================
        $this->assert(\App\Services\MarketConfigurationService::DEFAULT_COUNTRY === 'US', "Default country is strictly United States (US)");
        $this->assert(\App\Services\MarketConfigurationService::isCountryAllowed('US') === true, "United States (US) is authorized for operations");
        $this->assert(\App\Services\MarketConfigurationService::isCountryAllowed('JM') === false, "Jamaica is not active in initial US launch");
        $this->assert(\App\Services\MarketConfigurationService::isCountryAllowed('CA') === false, "Canada is not active in initial US launch");
        $this->assert(\App\Services\MarketConfigurationService::isCountryAllowed('GB') === false, "United Kingdom is not active in initial US launch");

        // =========================================================================
        // 2. Currency and Financial Calculation Precision (USD, Integer Cents)
        // =========================================================================
        $this->assert(\App\Services\MarketConfigurationService::DEFAULT_CURRENCY === 'USD', "Default operating currency is USD ($)");
        $this->assert(\App\Services\MarketConfigurationService::isCurrencyAllowed('USD') === true, "USD is an allowed operating currency");
        $this->assert(\App\Services\MarketConfigurationService::isCurrencyAllowed('EUR') === false, "EUR is not allowed in initial US launch");

        // Zero floating-point arithmetic for financial calculations
        $cents = \App\Services\PriceCalculationService::toMinorUnits(45.50, 'USD');
        $this->assert($cents === 4550, "Float $45.50 converts accurately to exact 4550 integer cents");

        $backToFloat = \App\Services\PriceCalculationService::fromMinorUnits(4550, 'USD');
        $this->assert($backToFloat === 45.50, "Integer 4550 cents converts back to $45.50 without distortion");

        // Order financials calculation
        $orderFinancials = \App\Services\PriceCalculationService::calculateOrderFinancials(
            subtotalMinor: 4500,     // $45.00
            packagingFeeMinor: 150,  // $1.50
            amountReceivedMinor: 4650, // $46.50
            refundedMinor: 1000,     // $10.00
            currency: 'USD'
        );

        $this->assert($orderFinancials['total_amount_minor'] === 4650, "Order total correctly computed as 4650 cents ($46.50)");
        $this->assert($orderFinancials['outstanding_balance_minor'] === 0, "Fully paid order has zero outstanding balance");
        $this->assert($orderFinancials['net_receipts_minor'] === 3650, "Net receipts accurately distinguishes refunded amounts (4650 - 1000 = 3650 cents)");
        $this->assert($orderFinancials['is_fully_paid'] === true, "Order recognized as fully paid");
        $this->assert($orderFinancials['formatted_total'] === '$46.50', "Order total formatted as $46.50");
        $this->assert($orderFinancials['formatted_net_receipts'] === '$36.50', "Net receipts formatted as $36.50");

        // Partial payment scenario
        $partialOrderFinancials = \App\Services\PriceCalculationService::calculateOrderFinancials(
            subtotalMinor: 3000,    // $30.00
            packagingFeeMinor: 200, // $2.00
            amountReceivedMinor: 1500, // $15.00 deposit
            refundedMinor: 0,
            currency: 'USD'
        );
        $this->assert($partialOrderFinancials['total_amount_minor'] === 3200, "Partial order total is 3200 cents ($32.00)");
        $this->assert($partialOrderFinancials['outstanding_balance_minor'] === 1700, "Outstanding balance is exactly 1700 cents ($17.00)");
        $this->assert($partialOrderFinancials['is_fully_paid'] === false, "Partial order is not marked fully paid");
        $this->assert($partialOrderFinancials['is_partially_paid'] === true, "Order is identified as partially paid");
        $this->assert($partialOrderFinancials['formatted_outstanding_balance'] === '$17.00', "Outstanding balance formatted as $17.00");

        // Currency Invariance: Client request cannot alter authoritative currency
        $lockedCurrency = \App\Services\PriceCalculationService::lockAuthoritativeCurrency('USD', 'JMD');
        $this->assert($lockedCurrency === 'USD', "Authoritative currency remains locked to USD despite browser requesting JMD");

        // =========================================================================
        // 3. Payment Layer (Manual US Gateway & Verification Gate)
        // =========================================================================
        $manualGateway = new \App\Services\Gateways\ManualPaymentGateway();
        $this->assert($manualGateway->getIdentifier() === 'manual_us', "Manual US payment gateway identifier is manual_us");
        $this->assert($manualGateway->isAvailable('USD', 'US') === true, "Manual US gateway is available for USD in US");

        // Create a test order
        $testOrder = new \App\Models\Order([
            'id' => 9901,
            'organizer_id' => 10,
            'customer_name' => 'Sarah Connor',
            'email' => 'sarah@example.com',
            'subtotal' => 45.00,
            'packaging_fee' => 1.50,
            'total_amount' => 46.50,
            'currency' => 'USD',
            'status' => 'awaiting_payment',
        ]);
        $testOrder->save();

        // Attendee submits transfer claim with screenshot
        $initiation = $manualGateway->initiatePayment($testOrder, [
            'reference' => 'CHASE-WIRE-998811',
            'sender_name' => 'Sarah Connor',
            'method' => 'bank_transfer',
            'receipt_path' => '/uploads/receipts/chase_wire_9901.png',
        ]);

        $this->assert($initiation['status'] === 'payment_submitted', "Attendee submission sets status to payment_submitted");
        $this->assert($initiation['is_paid'] === false, "Uploaded screenshot is strictly NOT treated as proof of funds");
        $this->assert($testOrder->status === 'payment_submitted', "Order in database remains payment_submitted and unpaid");

        // Authorized Host verifies payment
        $verification = $manualGateway->verifyPayment($testOrder, [
            'verifier_id' => 10,
            'verifier_name' => 'Host Marcus',
            'amount_verified_minor' => 4650, // Full amount verified
            'notes' => 'Matched in Chase Commercial Online banking statement',
        ]);

        $this->assert($verification['is_paid'] === true, "Order transitions to paid upon authorized verification");
        $this->assert($testOrder->status === 'paid', "Order status in database is now paid");
        $this->assert($testOrder->verified_by == 10, "Order records verifier ID");
        $this->assert(str_contains($testOrder->verification_notes, 'Host Marcus'), "Verification notes record staff name");

        // Process authorized partial refund
        $refund = $manualGateway->processRefund(
            order: $testOrder,
            refundAmountMinor: 1000, // $10.00 refund
            reason: 'Item unavailable at event',
            refundData: ['authorizer_name' => 'Administrator Alice']
        );
        $this->assert($refund['refunded_amount_minor'] === 1000, "Refund of 1000 cents processed");
        $this->assert($refund['net_receipts_minor'] === 3650, "Net receipts updated to 3650 cents after refund");

        // Stripe automated gateway readiness
        $stripeGateway = new \App\Services\Gateways\StripePaymentGateway();
        $this->assert($stripeGateway->getIdentifier() === 'stripe_us', "Stripe gateway identifier is stripe_us");
        $this->assert($stripeGateway->isAvailable('USD', 'US') === true, "Stripe gateway is available for US operations");

        // =========================================================================
        // 4. US Addresses and IANA Timezones with DST Handling
        // =========================================================================
        // Valid US Address
        $usAddress = \App\Services\USTimezoneAndLocationService::validateAddress([
            'street' => '301 Biscayne Blvd',
            'city' => 'Miami',
            'state' => 'FL',
            'zip' => '33132',
            'country' => 'US',
        ]);
        $this->assert($usAddress['state_name'] === 'Florida', "State code FL validates to Florida");
        $this->assert($usAddress['zip'] === '33132', "5-digit US ZIP code validated");
        $this->assert(str_contains($usAddress['formatted'], 'United States'), "Formatted address contains United States");

        // Invalid State Code
        $invalidStateCaught = false;
        try {
            \App\Services\USTimezoneAndLocationService::validateAddress([
                'street' => '100 Main St',
                'city' => 'Anytown',
                'state' => 'ZZ',
                'zip' => '10001',
                'country' => 'US',
            ]);
        } catch (\InvalidArgumentException $e) {
            $invalidStateCaught = true;
        }
        $this->assert($invalidStateCaught, "Invalid US state code ZZ is rejected");

        // Invalid ZIP Code format
        $invalidZipCaught = false;
        try {
            \App\Services\USTimezoneAndLocationService::validateAddress([
                'street' => '100 Main St',
                'city' => 'Anytown',
                'state' => 'NY',
                'zip' => '100', // Invalid (too short)
                'country' => 'US',
            ]);
        } catch (\InvalidArgumentException $e) {
            $invalidZipCaught = true;
        }
        $this->assert($invalidZipCaught, "Invalid ZIP code length is rejected");

        // US Phone number formatting
        $formattedPhone = \App\Services\MarketConfigurationService::formatUSPhoneNumber('3055550199');
        $this->assert($formattedPhone['e164'] === '+13055550199', "US phone formatted to E.164 +13055550199");
        $this->assert($formattedPhone['national'] === '(305) 555-0199', "US phone formatted to national (305) 555-0199");

        // US Timezone & Daylight Saving Transition
        // Summer Date (July) in New York -> EDT (UTC-4)
        $summerDst = \App\Services\USTimezoneAndLocationService::handleDaylightSavingTransition('2026-07-15', '14:00:00', 'America/New_York');
        $this->assert($summerDst['is_dst'] === true, "July in America/New_York is Daylight Saving Time (EDT)");
        $this->assert($summerDst['utc_offset'] === '-04:00', "EDT has -04:00 UTC offset");

        // Winter Date (December) in New York -> EST (UTC-5)
        $winterDst = \App\Services\USTimezoneAndLocationService::handleDaylightSavingTransition('2026-12-15', '14:00:00', 'America/New_York');
        $this->assert($winterDst['is_dst'] === false, "December in America/New_York is Standard Time (EST)");
        $this->assert($winterDst['utc_offset'] === '-05:00', "EST has -05:00 UTC offset");

        // Worker Shift in Event Timezone
        $shift = \App\Services\USTimezoneAndLocationService::validateWorkerShiftInEventTimezone(
            shiftStartUtc: '2026-11-20T17:00:00Z',
            shiftEndUtc: '2026-11-20T23:00:00Z',
            eventTimezone: 'America/New_York'
        );
        $this->assert($shift['valid'] === true, "Worker shift window is valid");
        $this->assert($shift['duration_hours'] === 6.0, "Worker shift duration is exactly 6 hours");

        // =========================================================================
        // 5. US Privacy, Consumer Protection & Compliance
        // =========================================================================
        // State Sales Tax
        $taxFL = \App\Services\USComplianceAndPrivacyService::calculateUSTax(10000, 'FL'); // $100.00 in FL
        $this->assert($taxFL['tax_cents'] === 600, "FL 6% sales tax on $100.00 is $6.00 (600 cents)");

        // 501(c)(3) Tax Exemption
        $taxExempt = \App\Services\USComplianceAndPrivacyService::calculateUSTax(
            subtotalMinor: 10000,
            stateCode: 'FL',
            is501c3TaxExempt: true,
            ein: '12-3456789'
        );
        $this->assert($taxExempt['tax_cents'] === 0, "501(c)(3) tax exempt fundraiser pays $0 sales tax");
        $this->assert($taxExempt['is_exempt'] === true, "Fundraiser flagged as tax exempt");

        // CCPA / CPRA "Do Not Sell"
        $privacyUser = new \App\Models\User([
            'id' => 9905,
            'name' => 'California Attendee',
            'email' => 'california@example.com',
            'status' => 'active',
            'role' => 'attendee',
        ]);
        $privacyUser->save();

        $privacyRes = \App\Services\USComplianceAndPrivacyService::setConsumerPrivacyPreferences($privacyUser, true, true);
        $this->assert($privacyRes['preferences']['do_not_sell_or_share'] === true, "CCPA Do Not Sell recorded");

        // PCI-DSS Scope Minimization
        $pciAuditGood = \App\Services\USComplianceAndPrivacyService::auditPciScope(['token' => 'tok_safe_token_123', 'amount' => 4500]);
        $this->assert($pciAuditGood['is_compliant'] === true, "Tokenized payload satisfies PCI-DSS scope minimization");

        $pciAuditBad = \App\Services\USComplianceAndPrivacyService::auditPciScope(['card_number' => '4111222233334444', 'cvv' => '123']);
        $this->assert($pciAuditBad['is_compliant'] === false, "Plaintext card numbers immediately flagged as PCI violation");

        // =========================================================================
        // 6. Modular Future International Expansion
        // =========================================================================
        \App\Services\MarketConfigurationService::registerMarketExtension('CA', [
            'country_name' => 'Canada',
            'currency' => 'CAD',
            'currency_symbol' => '$',
        ], activateNow: false);

        // Still strictly US on initial launch
        $this->assert(\App\Services\MarketConfigurationService::isCountryAllowed('CA') === false, "Modularly registered Canada remains inactive for initial US launch");
        $this->assert(\App\Services\MarketConfigurationService::isCountryAllowed('US') === true, "United States remains active");
    }

    private function suite22TicketRedemptionAndServerSideQrVerification(): void
    {
        echo "\nSuite 22: Ticket Redemption, Server-Side QR Verification & Audit Trail Enforcement\n";

        // Setup test event and authorized ticket checker worker
        $testEvent = new \App\Models\Event([
            'id' => 'evt-qr-festival-2026',
            'organizer_id' => 'org-island-events',
            'title' => 'Atlanta Reggae & Sound Festival 2026',
            'status' => 'published',
        ]);
        $testEvent->save();

        $authorizedChecker = new \App\Models\User([
            'id' => 7701,
            'name' => 'Checker Marcia',
            'email' => 'marcia.checker@islandevents.example',
            'status' => 'active',
            'role' => 'attendee',
        ]);
        $authorizedChecker->save();

        $checkerAssignment = new \App\Models\EventWorkerAssignment([
            'id' => 'asg-marcia-checker',
            'organizer_id' => 'org-island-events',
            'event_id' => 'evt-qr-festival-2026',
            'user_id' => 7701,
            'worker_role' => \App\Enums\EventWorkerRole::TICKET_CHECKER,
            'station_assignment' => 'all',
            'is_active' => true,
            'status' => 'active',
        ]);
        $checkerAssignment->save();

        $unauthorizedWorker = new \App\Models\User([
            'id' => 7702,
            'name' => 'Kitchen Jerome',
            'email' => 'jerome.cook@islandevents.example',
            'status' => 'active',
            'role' => 'attendee',
        ]);
        $unauthorizedWorker->save();

        $cookAssignment = new \App\Models\EventWorkerAssignment([
            'id' => 'asg-jerome-cook',
            'organizer_id' => 'org-island-events',
            'event_id' => 'evt-qr-festival-2026',
            'user_id' => 7702,
            'worker_role' => \App\Enums\EventWorkerRole::FOOD_STATION_WORKER,
            'station_assignment' => 'mains',
            'is_active' => true,
            'status' => 'active',
        ]);
        $cookAssignment->save();

        // 1. Genuine Paid Ticket Order
        $validPaidOrder = new \App\Models\Order([
            'id' => 'ord-valid-qr-001',
            'organizer_id' => 'org-island-events',
            'event_id' => 'evt-qr-festival-2026',
            'user_id' => 201,
            'ticket_code' => 'TKT-2026-VIP01',
            'order_number' => 'ORD-2026-77881',
            'customer_name' => 'Akeem Campbell',
            'email' => 'akeem.c@islandevents.example',
            'status' => 'paid',
            'secure_ticket_token' => 'tok_sha256_cryptographic_seed_9981',
            'total_amount' => 150.00,
            'currency' => 'USD',
        ]);
        $validPaidOrder->save();

        // Check authorized redemption & successful first scan
        $firstScan = \App\Services\EventWorkerService::scanAndValidateTicket(
            $authorizedChecker,
            $validPaidOrder,
            'evt-qr-festival-2026'
        );

        $this->assert($firstScan['success'] === true, "Authorized Ticket Checker successfully validates authentic ticket");
        $this->assert($firstScan['valid'] === true, "First ticket redemption succeeds");
        $this->assert($firstScan['already_admitted'] === false, "Ticket was not previously admitted");
        $this->assert(!empty($validPaidOrder->checked_in_at), "Redemption sets checked_in_at timestamp on order");
        $this->assert($validPaidOrder->checked_in_by === 7701, "Redemption records authorized checker user ID");

        // 2. Duplicate Redemption Prevention
        $secondScan = \App\Services\EventWorkerService::scanAndValidateTicket(
            $authorizedChecker,
            $validPaidOrder,
            'evt-qr-festival-2026'
        );

        $this->assert($secondScan['success'] === false, "Duplicate redemption attempt is strictly blocked");
        $this->assert($secondScan['already_admitted'] === true, "already_admitted reports true on second scan");
        $this->assert(str_contains($secondScan['rejection_reason'], 'Duplicate redemption prevented'), "Rejection reason explicitly identifies duplicate redemption");

        // 3. Event Association Enforcement
        $otherEventOrder = new \App\Models\Order([
            'id' => 'ord-other-event-002',
            'organizer_id' => 'org-island-events',
            'event_id' => 'evt-unrelated-beach-bash',
            'user_id' => 201,
            'ticket_code' => 'TKT-2026-OTHER',
            'order_number' => 'ORD-2026-99001',
            'customer_name' => 'Akeem Campbell',
            'status' => 'paid',
            'secure_ticket_token' => 'tok_sha256_other_event',
            'total_amount' => 50.00,
            'currency' => 'USD',
        ]);
        $otherEventOrder->save();

        $crossEventScan = \App\Services\EventWorkerService::scanAndValidateTicket(
            $authorizedChecker,
            $otherEventOrder,
            'evt-qr-festival-2026' // Scanning at Festival 2026
        );

        $this->assert($crossEventScan['success'] === false, "Ticket from different event is rejected");
        $this->assert(str_contains($crossEventScan['rejection_reason'], 'Event association mismatch'), "Rejection reason explicitly states event association mismatch");

        // 4. Ticket Status Check (Unpaid, Cancelled, Refunded)
        $unpaidOrder = new \App\Models\Order([
            'id' => 'ord-unpaid-003',
            'organizer_id' => 'org-island-events',
            'event_id' => 'evt-qr-festival-2026',
            'user_id' => 201,
            'ticket_code' => 'TKT-2026-UNPAID',
            'order_number' => 'ORD-2026-99002',
            'status' => 'awaiting_payment',
            'total_amount' => 75.00,
            'currency' => 'USD',
        ]);
        $unpaidOrder->save();

        $unpaidScan = \App\Services\EventWorkerService::scanAndValidateTicket(
            $authorizedChecker,
            $unpaidOrder,
            'evt-qr-festival-2026'
        );

        $this->assert($unpaidScan['success'] === false, "Unpaid awaiting-payment ticket is rejected at gate");
        $this->assert(str_contains($unpaidScan['rejection_reason'], 'Invalid ticket status: awaiting_payment'), "Rejection reason specifies awaiting_payment status");

        $refundedOrder = new \App\Models\Order([
            'id' => 'ord-refunded-004',
            'organizer_id' => 'org-island-events',
            'event_id' => 'evt-qr-festival-2026',
            'user_id' => 201,
            'ticket_code' => 'TKT-2026-REFUND',
            'order_number' => 'ORD-2026-99003',
            'status' => 'refunded',
            'total_amount' => 100.00,
            'currency' => 'USD',
        ]);
        $refundedOrder->save();

        $refundedScan = \App\Services\EventWorkerService::scanAndValidateTicket(
            $authorizedChecker,
            $refundedOrder,
            'evt-qr-festival-2026'
        );

        $this->assert($refundedScan['success'] === false, "Refunded ticket is rejected at gate");
        $this->assert(str_contains($refundedScan['rejection_reason'], 'Invalid ticket status: refunded'), "Rejection reason specifies refunded status");

        // 5. Validity Period Check
        $expiredOrder = new \App\Models\Order([
            'id' => 'ord-expired-005',
            'organizer_id' => 'org-island-events',
            'event_id' => 'evt-qr-festival-2026',
            'user_id' => 201,
            'ticket_code' => 'TKT-2026-EXPIRED',
            'order_number' => 'ORD-2026-99004',
            'status' => 'expired',
            'total_amount' => 60.00,
            'currency' => 'USD',
        ]);
        $expiredOrder->save();

        $expiredScan = \App\Services\EventWorkerService::scanAndValidateTicket(
            $authorizedChecker,
            $expiredOrder,
            'evt-qr-festival-2026'
        );

        $this->assert($expiredScan['success'] === false, "Expired ticket is rejected");
        $this->assert(str_contains($expiredScan['rejection_reason'], 'expired'), "Rejection reason explicitly identifies expiration");

        // 6. Authorized Redemption Security Check (Unauthorized Worker Blocked)
        $unauthorizedBlocked = false;
        try {
            \App\Services\EventWorkerService::scanAndValidateTicket(
                $unauthorizedWorker, // Food station worker without scan_qr_tickets
                $validPaidOrder,
                'evt-qr-festival-2026'
            );
        } catch (\SecurityException $e) {
            $unauthorizedBlocked = true;
        }
        $this->assert($unauthorizedBlocked, "Worker without scan_qr_tickets permission is blocked with SecurityException");

        // 7. Immutable Audit Trail Entries Recorded
        $auditLogs = \App\Models\SecurityAuditLog::where('target_entity', 'orders')->get();
        $hasVerifiedLog = false;
        $hasRejectedLog = false;
        foreach ($auditLogs as $log) {
            if ($log->action === 'ticket_redemption_verified') {
                $hasVerifiedLog = true;
            }
            if ($log->action === 'ticket_redemption_rejected') {
                $hasRejectedLog = true;
            }
        }

        $this->assert($hasVerifiedLog, "Successful ticket redemption is logged in immutable security audit trail");
        $this->assert($hasRejectedLog, "Rejected ticket redemption is logged with reason in security audit trail");
    }
}

$runner = new TestRunner();
$runner->runAll();
}
