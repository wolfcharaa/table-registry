<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Tests\Contract\Support;

final class PublicApiInspector
{
    private const API_START = '<!-- public-api:start -->';
    private const API_END = '<!-- public-api:end -->';

    public function __construct(private readonly string $rootDirectory)
    {
    }

    /** @return list<class-string> */
    public function documentedSymbols(): array
    {
        $contents = file_get_contents($this->rootDirectory . '/docs/public-api.md');
        if ($contents === false) {
            throw new \RuntimeException('Cannot read docs/public-api.md.');
        }

        $start = strpos($contents, self::API_START);
        $end = strpos($contents, self::API_END);
        if ($start === false || $end === false || $end <= $start) {
            throw new \RuntimeException('Public API markers are missing or invalid.');
        }

        $block = substr($contents, $start + strlen(self::API_START), $end - $start - strlen(self::API_START));
        preg_match_all('/^- `([^`]+)`$/m', $block, $matches);

        /** @var list<class-string> $symbols */
        $symbols = array_values($matches[1]);

        return $symbols;
    }

    /** @return list<class-string> */
    public function sourceSymbols(): array
    {
        $sourceDirectory = $this->rootDirectory . '/src';
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDirectory, \FilesystemIterator::SKIP_DOTS)
        );
        $symbols = [];

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = substr($file->getPathname(), strlen($sourceDirectory) + 1, -4);
            $symbols[] = 'Wolfcharaa\\TableRegistry\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);
        }

        sort($symbols);

        /** @var list<class-string> $symbols */
        return $symbols;
    }

    /** @return list<string> */
    public function violations(): array
    {
        $violations = [];
        $documented = $this->documentedSymbols();
        $sorted = array_values(array_unique($documented));
        sort($sorted);
        if ($documented !== $sorted) {
            $violations[] = 'Public API allowlist must be unique and sorted.';
        }

        $documentedMap = array_fill_keys($documented, true);
        $internalSymbols = [];

        foreach ($this->sourceSymbols() as $symbol) {
            if (!$this->symbolExists($symbol)) {
                $violations[] = sprintf('Source symbol %s is not loadable.', $symbol);
                continue;
            }

            $reflection = new \ReflectionClass($symbol);
            $docComment = $reflection->getDocComment() ?: '';
            $hasApi = str_contains($docComment, '@api');
            $hasInternal = str_contains($docComment, '@internal');

            if (isset($documentedMap[$symbol])) {
                if (!$hasApi || $hasInternal) {
                    $violations[] = sprintf('Public symbol %s must have only @api status.', $symbol);
                }
            } else {
                if (!$hasInternal || $hasApi) {
                    $violations[] = sprintf('Non-public symbol %s must have only @internal status.', $symbol);
                } else {
                    $internalSymbols[$symbol] = true;
                }
            }
        }

        foreach ($documented as $symbol) {
            if (!in_array($symbol, $this->sourceSymbols(), true)) {
                $violations[] = sprintf('Documented symbol %s has no source file.', $symbol);
            }
        }

        foreach ($documented as $symbol) {
            if (!$this->symbolExists($symbol)) {
                continue;
            }

            $reflection = new \ReflectionClass($symbol);
            foreach ($this->exposedReflections($reflection) as $member) {
                foreach ($this->reflectionTypeNames($member) as $typeName) {
                    if (str_starts_with($typeName, 'Wolfcharaa\\TableRegistry\\Internal\\')
                        || isset($internalSymbols[$typeName])) {
                        $violations[] = sprintf('%s exposes internal type %s.', $symbol, $typeName);
                    }
                }

                $docComment = $member->getDocComment() ?: '';
                if (str_contains($docComment, 'TableRegistry\\Internal\\')) {
                    $violations[] = sprintf('%s exposes an internal PHPDoc type.', $symbol);
                }
                foreach (array_keys($internalSymbols) as $internalSymbol) {
                    if (str_contains($docComment, $internalSymbol)) {
                        $violations[] = sprintf('%s exposes internal PHPDoc symbol %s.', $symbol, $internalSymbol);
                    }
                }
            }
        }

        sort($violations);

        return array_values(array_unique($violations));
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        $snapshot = [];
        foreach ($this->documentedSymbols() as $symbol) {
            $reflection = new \ReflectionClass($symbol);
            $snapshot[$symbol] = $this->snapshotSymbol($reflection);
        }

        return $snapshot;
    }

    /** @return array<string, mixed> */
    public function storedSnapshot(): array
    {
        $contents = file_get_contents($this->rootDirectory . '/tests/Contract/fixtures/public-api-signatures.json');
        if ($contents === false) {
            throw new \RuntimeException('Cannot read public API signature snapshot.');
        }

        /** @var array<string, mixed> $snapshot */
        $snapshot = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        return $snapshot;
    }

    private function symbolExists(string $symbol): bool
    {
        return class_exists($symbol) || interface_exists($symbol) || enum_exists($symbol);
    }

    /** @return list<\ReflectionClass|\ReflectionMethod|\ReflectionProperty> */
    private function exposedReflections(\ReflectionClass $reflection): array
    {
        $members = [$reflection];
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED) as $method) {
            if ($method->getDeclaringClass()->getName() === $reflection->getName()) {
                $members[] = $method;
            }
        }
        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED) as $property) {
            if ($property->getDeclaringClass()->getName() === $reflection->getName()) {
                $members[] = $property;
            }
        }

        return $members;
    }

    /** @return list<string> */
    private function reflectionTypeNames(\Reflector $reflection): array
    {
        $types = [];
        if ($reflection instanceof \ReflectionClass) {
            $parent = $reflection->getParentClass();
            if ($parent !== false) {
                $types[] = $parent->getName();
            }
            array_push($types, ...$reflection->getInterfaceNames());
        } elseif ($reflection instanceof \ReflectionMethod) {
            array_push($types, ...$this->typeNames($reflection->getReturnType()));
            foreach ($reflection->getParameters() as $parameter) {
                array_push($types, ...$this->typeNames($parameter->getType()));
            }
        } elseif ($reflection instanceof \ReflectionProperty) {
            array_push($types, ...$this->typeNames($reflection->getType()));
        }

        return array_values(array_unique($types));
    }

    /** @return list<string> */
    private function typeNames(?\ReflectionType $type): array
    {
        if ($type === null) {
            return [];
        }
        if ($type instanceof \ReflectionNamedType) {
            return $type->isBuiltin() ? [] : [$type->getName()];
        }

        $names = [];
        foreach ($type->getTypes() as $nestedType) {
            array_push($names, ...$this->typeNames($nestedType));
        }

        return $names;
    }

    /** @return array<string, mixed> */
    private function snapshotSymbol(\ReflectionClass $reflection): array
    {
        $parent = $reflection->getParentClass();
        $interfaces = $reflection->getInterfaceNames();
        sort($interfaces);

        $members = [];
        foreach ($reflection->getReflectionConstants(\ReflectionClassConstant::IS_PUBLIC | \ReflectionClassConstant::IS_PROTECTED) as $constant) {
            if ($constant->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }
            $members[] = sprintf(
                '%s%s const %s = %s',
                $constant->isPublic() ? 'public' : 'protected',
                $constant->isFinal() ? ' final' : '',
                $constant->getName(),
                $this->renderValue($constant->getValue())
            );
        }

        $defaultProperties = $reflection->getDefaultProperties();
        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED) as $property) {
            if ($property->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }
            $hasDefault = array_key_exists($property->getName(), $defaultProperties);
            $propertyType = $this->renderType($property->getType());
            $members[] = sprintf(
                '%s%s%s %s$%s%s',
                $property->isPublic() ? 'public' : 'protected',
                $property->isStatic() ? ' static' : '',
                $property->isReadOnly() ? ' readonly' : '',
                $propertyType === '' ? '' : $propertyType . ' ',
                $property->getName(),
                $hasDefault ? ' = ' . $this->renderValue($defaultProperties[$property->getName()]) : ''
            );
        }

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED) as $method) {
            if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }

            $parameters = [];
            foreach ($method->getParameters() as $parameter) {
                $hasDefault = $parameter->isDefaultValueAvailable();
                $default = '';
                if ($hasDefault) {
                    $default = ' = ' . ($parameter->isDefaultValueConstant()
                        ? $parameter->getDefaultValueConstantName()
                        : $this->renderValue($parameter->getDefaultValue()));
                }
                $parameterType = $this->renderType($parameter->getType());
                $parameters[] = sprintf(
                    '%s%s%s%s$%s%s',
                    $parameterType,
                    $parameterType === '' ? '' : ' ',
                    $parameter->isPassedByReference() ? '&' : '',
                    $parameter->isVariadic() ? '...' : '',
                    $parameter->getName(),
                    $default
                );
            }

            $returnType = $this->renderType($method->getReturnType());
            $members[] = sprintf(
                '%s%s%s%s function %s%s(%s)%s',
                $method->isPublic() ? 'public' : 'protected',
                $method->isAbstract() ? ' abstract' : '',
                $method->isFinal() ? ' final' : '',
                $method->isStatic() ? ' static' : '',
                $method->returnsReference() ? '&' : '',
                $method->getName(),
                implode(', ', $parameters),
                $returnType === '' ? '' : ': ' . $returnType
            );
        }
        sort($members);

        $kind = $reflection->isInterface() ? 'interface' : ($reflection->isEnum() ? 'enum' : 'class');
        if ($kind === 'class' && $reflection->isAbstract()) {
            $kind = 'abstract ' . $kind;
        } elseif ($kind === 'class' && $reflection->isFinal()) {
            $kind = 'final ' . $kind;
        }

        return [
            'kind' => $kind,
            'parent' => $parent === false ? null : $parent->getName(),
            'interfaces' => $interfaces,
            'members' => $members,
        ];
    }

    private function renderType(?\ReflectionType $type): string
    {
        if ($type === null) {
            return '';
        }
        if ($type instanceof \ReflectionNamedType) {
            $nullable = $type->allowsNull() && !in_array($type->getName(), ['mixed', 'null'], true);

            return ($nullable ? '?' : '') . $type->getName();
        }

        $separator = $type instanceof \ReflectionUnionType ? '|' : '&';

        return implode($separator, array_map(fn (\ReflectionType $nested): string => $this->renderType($nested), $type->getTypes()));
    }

    private function renderValue(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }
        if (is_object($value)) {
            return $value instanceof \UnitEnum ? $value::class . '::' . $value->name : $value::class;
        }

        return var_export($value, true);
    }
}
