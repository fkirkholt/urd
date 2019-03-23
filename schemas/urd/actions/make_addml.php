<?php

$base = $_GET['base'];

$file = fopen('addml.xml', 'w');
$file = fopen('addml.xml', 'a');

$xml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<addml xmlns="http://www.arkivverket.no/standards/addml_8_2"
       xmlns:xs="http://www.w3.org/2001/XMLSchema">
    <dataset>
        <description>$betegnelse</description>
        <flatFiles>
XML;

foreach ($tables as $table) {
    $xml += <<<XML
            <flatFile name="$table->name" definitionReferene="$table->name">
                <properties>
                    <property name="fileName">
                        <value>$table->name.dat</value>
                    </property>
                </properties>
            </flatFile>
XML;
}

$xml += <<<XML
            <flatFileDefinitions>
XML;

foreach ($tables as $table) {
    $xml += <<<XML
                <flatFileDefinition name="$table->name" typeReference="csv">
                    <recordDefinitions>
                        <recordDefinition name="$table->name">
                            <description>$table->description</description>
                            <properties>
                                <property name="label">
                                    <value>$table->label</value>
                                </property>
                                <property name="comment">
                                    <value>$table->comment</value>
                                </property>
                            </properties>
                            <keys>
                                <key name="PRIMARY">
                                    <primaryKey/>
                                    <fieldDefinitionReferences>
XML;

    foreach ($table->prim_key_fields as $field) {
        $xml += <<<XML
                                        <fieldDefinitionReference name="$field"/>
XML;

    }

    $xml += <<<XML
                                    </fieldDefinitionReferences>
                                </key>
XML;

    foreach ($table->foreign_keys as $key) {
        $xml += <<<XML
                                <key>
                                    <foreignKey>
                                        <flatFileDefinitionReference name="$key->ref_table">
                                            <recordDefinitionReferences>
                                                <recordDefinitionReference name="$key->ref_table">
                                                    <fieldDefinitionReferences>
XML;

        foreach ($key->ref_fields as $ref_field) {
            $xml += <<<XML
                                                        <fieldDefinitionReference name="$ref_field">
XML;
        }

        $xml += <<<XML
                                                    </fieldDefinitionReferences>
                                                </recordDefinitionReference>
                                            </recordDefinitionReferences>
                                        </flatFileDefinitionReference>
                                        <relationType>n:1</relationType>
                                    </foreignKey>
                                    <fieldDefinitionReferences>
XML;

        foreach ($table->foreign_key as $field) {
            $xml += <<<XML
                                        <fieldDefinitionReference name="$field">
XML;
        }

        $xml += <<<XML
                                    </fieldDefinitionReferences>
                                </key>
XML;
    }

    $xml += <<<XML
                            </keys>
                            <fieldDefinitions>
XML;

    foreach ($table->fields as $field) {
        $xml += <<<XML
                                <fieldDefinition name="$field->name" typeReference="$field->datatype">
                                    <description>$field->description</description>
                                    <properties>
                                        <property name="label">
                                            <value>$field->label</value>
                                        </property>
                                    </properties>
                                    <maxLength>$field->size</maxLength>
XML;
        if ($field->not_null) {
            $xml += <<<XML
                                    <notNull/>
XML;
        }

        $xml += <<<XML
                                </fieldDefinition>
XML;
    }

    $xml += <<<XML
                            </fieldDefinitions>
                        </recordDefinition>
                    </recordDefinitions>
                </flatFileDefinition>
XML;
}

$xml += <<<XML
            </flatFileDefinitions>
            <structureTypes>
                <flatFileTypes>
XML;

foreach ($tables as $table) {
    $xml += <<<XML
                    <flatFileType name="csv">
                        <charset>UTF-8</charset>
                        <delimFileFormat>
                            <recordSeparator>LF</recordSeparator>
                            <fieldSeparatingChar>;</fieldSeparatingChar>
                            <quotingChar>"</quotingChar>
                        </delimFileFormat>
                    </flatFileType>
XML;
}

$xml += <<<XML
                </flatFileTypes>
                <fieldTypes>
                    <fieldType name="string">
                        <dataType>string</dataType>
                        <alignment>left</alignment>
                    </fieldType>
                    <fieldType name="integer">
                        <dataType>integer</dataType>
                        <alignment>right</alignment>
                    </fieldType>
                    <fieldType name="float">
                        <dataType>float</dataType>
                        <alignment>right</alignment>
                    </fieldType>
                    <fieldType name="date">
                        <dataType>date</dataType>
                        <alignment>left</alignment>
                        <nullValues>
                            <nullValue>0000-00-00</nullValue>
                        </nullValues>
                    </fieldType>
                </fieldTypes>
            </structureTypes>
        </flatFiles>
    </dataset>
</addml>
XML;
