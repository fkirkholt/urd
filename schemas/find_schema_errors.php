<?php

function getNestedVar(&$context, $name) {
    $pieces = explode('.', $name);
    foreach ($pieces as $piece) {
        if (!is_array($context) || !array_key_exists($piece, $context)) {
            // error occurred
            return null;
        }
        $context = &$context[$piece];
    }
    return $context;
}

function check_reference($table, $form) {
    if (!isset($form['items'])) return;
    foreach ($form['items'] as $item) {
        if (gettype($item) === 'array') {
            check_reference($table, $item);
        } else if (!getNestedVar($table, $item)) {
            if (!isset($table['fields'][$item])) {
                echo "  => Fant ikke elementet $item i form\n";
            }
        }
    }
}

$file_paths = glob('*/schema.json');

$schemas = [];

foreach ($file_paths as $path) {
    $schema_name = explode('/', $path)[0];
    $schemas[$schema_name] = json_decode(file_get_contents($path), true);
}

foreach ($schemas as $schema_name => $schema) {

    echo "Gjennomgår skjemaet $schema_name\n";

    foreach ($schema['tables'] as $tbl_alias => $table) {

        echo "- $tbl_alias\n";

        // Check if primary keys are correct referenced
        foreach ($table['primary_key'] as $pk_field) {
            if (!isset($table['fields'][$pk_field])) {
                echo "  => Feil i primærnøkkel\n";
            }
        }

        // Check if foreign keys are correct referenced
        if (isset($table['foreign_keys'])) {
            foreach ($table['foreign_keys'] as $fk_alias => $fk) {
                $fk = (object) $fk;
                foreach ($fk->local as $field_alias) {
                    if (!isset($schema['tables'][$tbl_alias]['fields'][$field_alias])) {
                        echo "  => Feil lokalt felt `$field_alias` i $tbl_alias.$fk_alias\n";
                    }
                }
                foreach ($fk->foreign as $field_alias) {
                    $foreign_schema = isset($fk->schema) && $fk->schema !== $schema_name ? $schemas[$fk->schema] : $schema;
                    if (!isset($foreign_schema['tables'][$fk->table]['fields'][$field_alias])) {
                        echo "  => Feil foreign felt `$field_alias` i $tbl_alias.$fk_alias\n";
                    }
                }
            }
        }

        // Check if grid.columns are correct
        if (isset($table['grid']) && isset($table['grid']['columns'])) {
            foreach ($table['grid']['columns'] as $column) {
                if (!getNestedVar($table, $column)) {
                    if (!isset($table['fields'][$column])) {
                        echo "  => Feil kolonne $column i tabell\n";
                    }
                }
            }
        }

        // Check if form.items are correct
        if (isset($table['form'])) {
            check_reference($table, $table['form']);
        }
    }
}
