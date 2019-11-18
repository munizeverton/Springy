<?php

use Springy\Model;

class Test extends Model
{
    protected $tableName = 'tests';
    protected $writableColumns = ['id', 'name', 'updated_at'];
    protected $insertDateColumn = 'created_at';
    protected $deletedColumn = 'deleted';

    /**
     *  \brief The valitation rules to save into table.
     */
    protected function validationRules()
    {
        return [
            'name' => 'required',
        ];
    }

    /**
     *  \brief Validation error messages.
     */
    protected function validationErrorMessages()
    {
        return [
            'name' => [
                'required' => 'O campo Nome é obrigatório!',
            ],
        ];
    }
}
