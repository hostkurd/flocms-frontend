<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Collections;

use FloCMS\Frontend\Components\Prop;

/**
 * A collection's table (D27), written into a migration by site:collection:
 * one column per field, plus id, the publishing columns (D29), sort and the
 * dates. Translatable fields are JSON text by language, like admin Pages.
 */
final class Schema
{
    /**
     * The column definitions, without id.
     *
     * @return array<string, string> column => SQL (with '{{long}}' for the driver's long text type)
     */
    public static function columns(Collection $collection): array
    {
        $out = [];
        foreach ($collection->fieldMap() as $name => $prop) {
            $out[$name] = self::type($prop);
        }
        if ($collection::hasPublishing()) {
            $out['status'] = "VARCHAR(20) NOT NULL DEFAULT 'draft'";
            $out['publish_at'] ??= 'DATETIME NULL';
            $out['unpublish_at'] = 'DATETIME NULL';
        }
        $out['sort'] = 'INTEGER NOT NULL DEFAULT 0';
        $out['created_at'] = 'DATETIME NULL';
        $out['updated_at'] = 'DATETIME NULL';
        return $out;
    }

    /** @return array<string, string> index name => column list */
    public static function indexes(Collection $collection): array
    {
        $table = $collection::table();
        $out = [];
        if ($collection::hasPublishing()) {
            $out[$table . '_status_index'] = '`status`, `publish_at`';
        }
        $out[$table . '_sort_index'] = '`sort`';
        foreach ($collection->fieldMap() as $name => $prop) {
            if ($prop->type() === 'select' || $prop->type() === 'price') {
                $out[$table . '_' . $name . '_index'] = '`' . $name . '`';
            }
        }
        return $out;
    }

    private static function type(Prop $prop): string
    {
        if ($prop->isTranslatable()) {
            return $prop->type() === 'rich_text' ? '{{long}} NULL' : 'TEXT NULL';
        }
        return match ($prop->type()) {
            'rich_text' => '{{long}} NULL',
            'text', 'slug' => 'VARCHAR(190) NULL',
            'select' => $prop->getFrom() !== null ? 'INTEGER NULL' : 'VARCHAR(60) NULL',
            'price' => 'DECIMAL(12,2) NULL',
            'number' => 'DECIMAL(14,4) NULL',
            'toggle' => 'SMALLINT NOT NULL DEFAULT 0',
            'date' => 'DATE NULL',
            'colour' => 'VARCHAR(20) NULL',
            'icon' => 'VARCHAR(40) NULL',
            default => 'TEXT NULL', // textarea, image, images, tags, pairs, link, list
        };
    }

    /** The migration file's text. */
    public static function migration(Collection $collection): string
    {
        $table = $collection::table();
        $lines = [];
        foreach (self::columns($collection) as $column => $sql) {
            $lines[] = "            `{$column}` " . str_replace('{{long}}', "' . \$long . '", str_replace("'", "\\'", $sql));
        }
        $indexes = '';
        foreach (self::indexes($collection) as $name => $columns) {
            $indexes .= "            '{$name}' => '`{$table}` ({$columns})',\n";
        }
        $class = $collection::class;
        $columns = implode(",\n", $lines);
        return <<<PHP
<?php
declare(strict_types=1);

use FloCMS\\CLI\\Database\\Migration;
use FloCMS\\Core\\Database;

/**
 * The "{$collection::name()}" collection ({$class}), flocms-frontend 0.2.
 * Words that differ per language are JSON by language, {"en": "…", "ar": "…"}.
 * Written by php flo site:collection; change it freely before you migrate.
 */
return new class extends Migration
{
    public bool \$transactional = false;

    public function up(Database \$db): void
    {
        \$long = \$db->driver() === 'mysql' ? 'LONGTEXT' : 'TEXT';
        \$db->query('CREATE TABLE IF NOT EXISTS `{$table}` (
            ' . \$this->id(\$db) . ',
{$columns}
        )' . \$this->tableOptions(\$db));
        foreach ([
{$indexes}        ] as \$index => \$on) {
            try { \$db->query('CREATE INDEX `' . \$index . '` ON ' . \$on); } catch (\\PDOException) { /* already there */ }
        }
    }

    public function down(Database \$db): void
    {
        \$db->query('DROP TABLE IF EXISTS `{$table}`');
    }
};

PHP;
    }
}
