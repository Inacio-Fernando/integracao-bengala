<?php

namespace IntegracaoBengala\Infra\Database\Models;

use Illuminate\Database\Eloquent\Model;
use IntegracaoBengala\Infra\Database\Database;
use Throwable;

/**
 *  @property integer $seg_seqproduto
 *	@property integer $seg_qtdembalagem
 *	@property integer $seg_nrosegmento
 *	@property integer $seg_nroempresa
 *	@property string $seg_statusvenda
 */
class ProductSegmentTable extends Model
{

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cf_produto_segmento_filial';
    public $timestamps = false;

    public $incrementing = false;

    /**
     * @var array
     */
    protected $fillable = [
        'seg_seqproduto',
        'seg_qtdembalagem',
        'seg_nrosegmento',
        'seg_nroempresa',
        'seg_statusvenda'
    ];

    public static function batchInsert($columns, $data, $batchSize)
    {
        try {
            $batch = Database::getBatch();
            $result = $batch->insert(new ProductSegmentTable, $columns, $data, $batchSize);
            return $result;
        } catch (Throwable $e) {
            return false;
        }
    }
}



