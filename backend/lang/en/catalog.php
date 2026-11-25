<?php

declare(strict_types=1);

return [
    'default_price_list' => 'General price list',
    'units' => ['piece' => 'piece', 'kg' => 'kg', 'sqm' => 'm²'],
    'sheet' => [
        'name' => 'Price list',
        'id' => 'ID',
        'category' => 'Category',
        'item' => 'Item',
        'unit' => 'Unit',
        'file' => 'Excel file',
        'unreadable' => 'The file could not be read. Edit the Excel (xlsx) file downloaded here and upload it.',
        'no_id_column' => 'No "ID" column in the first row. Use the file downloaded here.',
        'no_service_columns' => 'No column matches a service.',
        'unknown_column' => 'Column ":column" matches no service and was ignored.',
        'unknown_item' => 'There is no item with ID ":id".',
        'duplicate_item' => '":item" already appears in row :row.',
        'invalid_amount' => '":value" is not a valid amount for ":item" (:service).',
    ],
];
