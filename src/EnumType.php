<?php

namespace Ferreira\AutoCrud;

use Illuminate\Support\Str;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * This type cannot actually be used to specify column declarations with
 * doctrine. Fortunately, we're using doctrine only to *read* the database
 * schema, and not to create one, so this limitation is actually irrelevant.
 */
abstract class EnumType extends Type
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string[]
     */
    protected $values;

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        $values = collect($this->values)->map(function ($val) {
            return "'$val'";
        })->implode(',');

        return "ENUM($values)";
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if (! in_array($value, $this->values)) {
            throw new \InvalidArgumentException("Invalid '$this->name' value.");
        }

        return $value;
    }

    public function getName()
    {
        return $this->name;
    }

    public function validValues()
    {
        return $this->values;
    }

    /**
     * Generate a class inheriting from `EnumType` for the given table, column
     * and list of valid values.
     *
     * @param string $tablename
     * @param string $column
     * @param string[] $valid
     */
    public static function generateDynamicEnumType(string $tablename, string $column, array $valid)
    {
        // I will probably go to hell for this piece of code. This effectively
        // creates a new class extending the abstract `EnumType` so that we can
        // keep using the Doctrine `Type` hierarchy with dynamically generated
        // Enum types.
        $name = "${tablename}_${column}_type";

        $className = Str::studly($name);

        if (! class_exists($className)) {
            $values = '[' . collect($valid)->map(function ($arg) {
                return "'$arg'";
            })->join(', ') . ']';

            $code =
                "class $className extends \\Ferreira\\AutoCrud\\EnumType\n" .
                "{\n" .
                "    protected \$name = '$name';\n" .
                "    protected \$values = $values;\n" .
                "}\n";

            eval($code);

            Type::addType($name, $className);
        }

        return Type::getType($name);
    }
}
