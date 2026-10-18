<?php

namespace App\Models;

use CodeIgniter\Model;

class QuarterModel extends Model
{
    protected $table = 'cuatrimestres';
    protected $primaryKey = 'id';
    protected $allowedFields = ['codigo', 'fecha_inicio', 'fecha_cierre'];
    protected $returnType = 'array';
}
