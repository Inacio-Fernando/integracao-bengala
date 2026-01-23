<?php

namespace IntegracaoBengala\Infra\Database\Models;

use Illuminate\Database\Eloquent\Model;
use IntegracaoBengala\Infra\Database\Database;
use Mavinoo\Batch\BatchFacade;
use Throwable;

/**
 * @property integer $prod_id
 * @property string $prod_nome
 * @property string $prod_apresentacao
 * @property string $prod_embalagem
 * @property string $prod_sessao
 * @property string $prod_grupo
 * @property string $prod_subgrupo
 * @property string $prod_descricao
 * @property integer $prod_empresa
 * @property integer $prod_estabelecimento
 * @property string $prod_cod
 * @property integer $prod_filial
 * @property string $prod_sku
 * @property string $prod_proporcao
 * @property string $prod_desc
 * @property string $prod_revisao
 * @property string $prod_flag100g
 * @property string $prod_desc_alt
 */
class ProductTable extends Model
{
    protected $connection = 'mysql';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cf_produto';
    public $timestamps = false;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'prod_id';

    /**
     * @var array
     */
    protected $fillable = [
        'prod_nome',
        'prod_apresentacao',
        'prod_embalagem',
        'prod_sessao',
        'prod_grupo',
        'prod_subgrupo',
        'prod_descricao',
        'prod_empresa',
        'prod_estabelecimento',
        'prod_cod',
        'prod_filial',
        'prod_sku',
        'prod_proporcao',
        'prod_desc',
        'prod_revisao',
        'prod_flag100g',
        'prod_desc_alt'
    ];

    public static function batchInsert(array $columns, $data, $batchSize)
    {
        try {
            return Database::getBatch()->insert(new ProductTable(), $columns, $data, $batchSize);
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function batchUpdate($data, $primaryKey)
    {
        try {
            return Database::getBatch()->update(new ProductTable, $data, $primaryKey);
        } catch (Throwable $e) {
            return false;
        }
    }



    public function prices()
    {
        return $this->hasMany(ValueTable::class, 'vlr_produto');
    }
}



