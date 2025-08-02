<?php

namespace Lolaji\LaravelControllerTrait\Filters;

use Closure;
use Exception;

class MorphFilter {
    private $value;
    private Closure $columnClosure;

    public function __construct(
        public string $morphName,
        public mixed $type,
        public string $column,
        public ?string $relation,
        public ?string $operator="=",
    ) {}

    public function __toString()
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }

    public function toArray(): array
    {
        return [
            "morphName"     => $this->morphName,
            "type"          => $this->type,
            "column"        => $this->column ?? "",
            "relation"      => $this->relation,
            "operator"      => $this->operator,
            "value"         => $this->value,
        ];
    }

    public function setColumnByClosure(Closure $closure)
    {
        $this->columnClosure = $closure;
    }

    public function getColumn($type)
    {
        if (!is_null($this->columnClosure)) {
            $closure = $this->columnClosure;
            return $closure($type);
        } else {
            if (!is_null($this->column)) {
                return $this->column;
            } else {
                throw new Exception("{column} property must be set on MorphFilter class.");
            }
        }
    }

    public function setValue($value): void
    {
        $this->value = $value;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }
}