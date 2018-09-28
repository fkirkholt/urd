<?php
	$json = file_get_contents('/Users/frokir/Sites/urd/schemas/betty/database.json');
	$db = json_decode($json);
	
	$xml = [];
	
	$xml['addml'] = [
		'@attributes' => [
			'xmlns' => "http://www.arkivverket.no/standards/addml_8_3",
			'xmlns:xs' => 'http://www.w3.org/2001/XMLSchema'
		],
		'dataset' => [
			'description' => 'todo',
			'flatFileDefinitions' => [
				'flatFileDefinition' => []
			]
		]
	];
	
	// Describe tables
	foreach($db->tables as $tbl_name => $table) {
		$ffd = [
			'@attributes' => [
				'name' => $table->name,
				'typeReference' => 'csv'
			],
			'description' => $table->description,
			'recordDefinitions' => [
				'recordDefinition' => [
					'@attributes' => [
						'name' => $table->name
					],
					'description' => $table->description,
					'keys' => [
						'key' => [
							'@attributes' => [
								'name' => 'PRIMARY'
							],
							'primaryKey' => '',
							'fieldDefinitionReferences' => array_map(function($field) {
								return [
									'fieldDefinitionReference' => [
										'@attributes' => [
											'name' => $field
										]
									]
								];
							}, (array) $table->primary_key)
						]
					],
					'fieldDefinitions' => [
						'fieldDefinition' => array_map(function($field) {
							return [
								'@attributes' => [
									'name' => $field->name,
									'typeReference' => $field->datatype
								],
								'description' => $field->description,
								'properties' 
							]
						}, $table->fields)
					]
				]
			]
			
		];
		
		$xml['addml']['dataset']['flatFileDefinitions']['flatFileDefinition'][] = $ffd;
		
		foreach ((array) $table->primary_key as $field) {
			// $ffd['']
		}
	}
	
	print_r($xml);
	
	
?>