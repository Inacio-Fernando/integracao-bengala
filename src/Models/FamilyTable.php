<?php

namespace IntegracaoBengala\Models;

use Illuminate\Database\Eloquent\Model;
use Mavinoo\Batch\BatchFacade;
use Throwable;

/**
 * @property integer $fam_id
 * @property string  $fam_nomefamilia
 */
class FamilyTable extends Model
{

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cf_familia';
    public $timestamps = false;
    public $incrementing = false;

    /**
     * @var array
     */
    protected $fillable = [
        'fam_id',
        'fam_nomefamilia'
    ];

    public static function batchInsert($columns, $data, $batchSize)
    {
        try {
            return BatchFacade::insert(new FamilyTable, $columns, $data, $batchSize);
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function batchUpdate($data, $primaryKey)
    {
        try {
            return BatchFacade::update(new FamilyTable, $data, $primaryKey);
        } catch (Throwable $e) {
            return false;
        }
    }
}



