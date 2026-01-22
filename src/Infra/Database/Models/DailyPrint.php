<?php

namespace IntegracaoBengala\Infra\Database\Models;

use Illuminate\Database\Eloquent\Model;
use IntegracaoBengala\Infra\Database\Database;
use Throwable;

/**
 * @property integer $dp_id
 * @property integer $dp_produto
 * @property integer $dp_valor
 * @property integer $dp_dgcartaz
 * @property integer $dp_dgmotivo
 * @property integer $dp_empresa
 * @property integer $dp_estabelecimento
 * @property integer $dp_usuario
 * @property string $dp_data
 * @property string $dp_hora
 * @property string $dp_tamanho
 * @property string $dp_fortam
 * @property string $dp_nome
 * @property string $dp_mobile
 * @property string $dp_qntparcela
 * @property string $dp_idtaxa
 * @property integer $dp_auditoria
 */
class DailyPrint extends Model
{
	/**
	 * The table associated with the model.
	 * 
	 * @var string
	 */
	protected $table = 'cf_dailyprint';
	public $timestamps = false;

	/**
	 * The primary key for the model.
	 * 
	 * @var string
	 */
	protected $primaryKey = 'dp_id';

	/**
	 * @var array
	 */
	protected $fillable = [
		'dp_produto',
		'dp_valor',
		'dp_dgcartaz',
		'dp_dgmotivo',
		'dp_empresa',
		'dp_estabelecimento',
		'dp_usuario',
		'dp_data',
		'dp_hora',
		'dp_tamanho',
		'dp_fortam',
		'dp_nome',
		'dp_mobile',
		'dp_qntparcela',
		'dp_idtaxa',
		'dp_auditoria'
	];

	public static function batchInsert($columns, $data, $batchSize)
	{
		try {
			$batch = Database::getBatch();
			$result = $batch->insert(new DailyPrint, $columns, $data, $batchSize);
			return $result;
		} catch (Throwable $e) {
			return false;
		}
	}

	public static function batchUpdate($data, $primaryKey)
	{
		try {

			$batch = Database::getBatch();
			$result = $batch->update(new DailyPrint, $data, $primaryKey);
			return $result;
		} catch (Throwable $e) {
			return false;
		}
	}
}
