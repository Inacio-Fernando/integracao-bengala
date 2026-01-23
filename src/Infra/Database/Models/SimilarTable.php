<?php

namespace IntegracaoBengala\Infra\Database\Models;

use Illuminate\Database\Eloquent\Model;
use IntegracaoBengala\Infra\Database\Database;
use Throwable;

/**
 * @property integer $ps_id
 * @property string $ps_descricao
 * @property integer $ps_seqproduto
 * @property integer $ps_seqprodutovinc
 * @property integer $ps_proporcao
 * @property string $ps_tipo
 */
class SimilarTable extends Model
{

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cf_produto_similaridade';
    public $timestamps = false;
    public $incrementing = false;

    /**
     * @var array
     */
    protected $fillable = [
        'ps_id',
        'ps_descricao',
        'ps_seqproduto',
        'ps_seqprodutovinc',
        'ps_proporcao',
        'ps_tipo'
    ];

    public static function batchInsert($columns, $data, $batchSize)
    {
        try {
            $batch = Database::getBatch();
            $result = $batch->insert(new SimilarTable, $columns, $data, $batchSize);
            return $result;
        } catch (Throwable $e) {
            return false;
        }
    }
}



