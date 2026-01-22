<?php

namespace IntegracaoBengala\Infra\Database\Models;

use Illuminate\Database\Eloquent\Model;
use IntegracaoBengala\Infra\Database\Database;
use Throwable;

/**
 * @property integer $gprom_seqgrupopromoc
 * @property string  $gprom_grupopromoc
 * @property string  $gprom_tipopromocao
 */
class PromotionGroupTable extends Model
{

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cf_grupo_promocao';
    public $timestamps = false;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'gprom_id';

    /**
     * @var array
     */
    protected $fillable = [
        'gprom_nome',
        'gprom_tipopromocao'
    ];

    public static function batchInsert($columns, $data, $batchSize)
    {
        try {
            $batch = Database::getBatch();
            $result = $batch->insert(new PromotionGroupTable, $columns, $data, $batchSize);
            return $result;
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function batchUpdate($data, $primaryKey)
    {
        try {
            $batch = Database::getBatch();
            $result = $batch->update(new PromotionGroupTable, $data, $primaryKey);
            return $result;
        } catch (Throwable $e) {
            return false;
        }
    }
}



