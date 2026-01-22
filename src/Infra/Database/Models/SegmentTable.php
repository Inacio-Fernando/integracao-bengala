<?php

namespace IntegracaoBengala\Infra\Database\Models;

use Illuminate\Database\Eloquent\Model;
use IntegracaoBengala\Infra\Database\Database;
use Throwable;

/**
 * @property integer $seg_id
 * @property string $seg_nome
 */
class SegmentTable extends Model
{

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cf_segmento';
    public $timestamps = false;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'seg_id';

    /**
     * @var array
     */
    protected $fillable = [
        'seg_nome'
    ];

    public static function batchInsert($columns, $data, $batchSize)
    {
        try {
            $batch = Database::getBatch();
            $result = $batch->insert(new SegmentTable, $columns, $data, $batchSize);
            return $result;
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function batchUpdate($data, $primaryKey)
    {
        try {
            $batch = Database::getBatch();
            $result = $batch->update(new SegmentTable, $data, $primaryKey);
            return $result;
        } catch (Throwable $e) {
            return false;
        }
    }
}



