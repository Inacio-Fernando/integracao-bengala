<?php
namespace IntegracaoFederzoni;

use Carbon\Carbon;
use Cartazfacil\DatabaseIntegracao\General;
use Dotenv\Dotenv as Dotenv;
use Exception;
use stdClass;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

class Bengala extends General
{
    protected $fromPrint = false;
    protected $rule = 0;

    function mountProduct($productData = null)
    {
        if (is_null($productData)) {
            $productData = $this->request_data;
        }

        $product = new stdClass();
        $product->prod_cod = substr($productData->CODIGO, 0, 50);
        $product->prod_nome = $this->name_formatter($productData->NOME);
        $product->prod_desc = $this->name_formatter($productData->NOME, 200);
        $product->prod_sku = substr($productData->EANINTERNO, 0, 300);
        $product->prod_proporcao = substr($productData->PROPORCAO, 0, 50);
        $product->prod_sessao = substr($productData->DEPARTAMENTO, 0, 75);
        $product->prod_grupo = substr($productData->GRUPO, 0, 75);
        $product->prod_subgrupo = substr($productData->SUBGRUPO, 0, 75);
        $product->prod_empresa = 1;
        $product->prod_estabelecimento = 1;

        //Atribuir departamento
        $this->depto = $product->prod_sessao;

        return $this->product = $product;

    }

    function mountPrice($productData = null)
    {
        if (is_null($productData)) {
            $productData = $this->request_data;
        }

        if (empty($productData)) {
            return false;
        }

        //Filiais
        $filial_switch = [1 => 5, 2 => 3, 3 => 2, 4 => 6, 5 => 4, 6 => 1];
        $filial = (key_exists((int) $productData->NROEMPRESA, $filial_switch)) ? $filial_switch[(int) $productData->NROEMPRESA] : (int) $productData->NROEMPRESA;

        //Ver qual data
        $data_de = date('Y-m-d');
        $data_ate = date('Y-m-d');

        //Criando objeto cf_preco
        $price = new stdClass();
        $price->vlr_produto = (int) ($this->fromPrint)? $productData->SEQPRODUTO : $productData->CODIGO;
        $price->vlr_empresa = 1;
        $price->vlr_filial = $filial;
        $price->vlr_usuario = 1;
        $price->vlr_data_de = $data_de;
        $price->vlr_data_ate = $data_ate;
        $price->vlr_hora = '03:03';

        $this->price = $price;

        //Função para formatação de preço e dinâmica
        ($this->fromPrint) ? $this->priceByPrint() : $this->priceByDynamic();

        return $this->price;
    }

    private function priceByDynamic()
    {

        $productData = $this->request_data;

        //Formatar preço string
        $p1 = isset($productData->PRECOREGULAR)? $this->price_formmater($productData->PRECOREGULAR) : 0;
        $p2 = isset($productData->PRECOPROMOCIONAL)? $this->price_formmater($productData->PRECOPROMOCIONAL): 0;
        $p3 = isset($productData->PRECOREGULARFATOR)? $this->price_formmater($productData->PRECOREGULARFATOR) : 0;

        //Atribuir dados padrão
        $this->price->vlr_valores = $p1; //preço inicial
        $this->price->vlr_idcomercial = 1; //Dinâmica

        //Verificação para tipo de valor promocional, substituir
        if (
            isset($productData->PRECOPROMOCIONAL)
            && $p1 != $p2
            && $p2 > 0
        ) {
            $this->price->vlr_valores .= "!@#$p2"; //preço adicional
            $this->price->vlr_idcomercial = 2; //De por
        }

        //Se houver fator, adicionar juntamente com texto
        if (
            isset($productData->PRECOREGULARFATOR)
            && $p3 > 0
        ) {
            $this->price->vlr_valores .= "!@#Equivale em $productData->MULTEQPEMBALAGEM. - R$ $p3";
        }

    }

    private function priceByPrint()
    {

        $productData = $this->request_data;

        //Formatar preço string
        $p1 = isset($productData->PRECO_BASE)? $this->price_formmater($productData->PRECO_BASE) : 0;
        $p2 = isset($productData->PRECO_PROMOCIONAL)? $this->price_formmater($productData->PRECO_PROMOCIONAL): 0;
        $p3 = isset($productData->PRECO_MASCOTE)? $this->price_formmater($productData->PRECO_MASCOTE) : 0;

        //Datas Validade
        $start = new Carbon($productData->DTAINICIO, 'America/Sao_Paulo');
        $end = new Carbon($productData->DTAFIM, 'America/Sao_Paulo');

        //Pegar dados de produto
        $this->product = $product = $this->getProduct($productData->SEQPRODUTO, 'code');

        $valor = '';
        $dinamica = 1;
        $regra = 0;

        switch (true) {
            case is_object($product) && $product->prod_sessao == 'FLV' 
            && $start->dayOfWeekIso == 4 && $end->dayOfWeekIso == 5:
                $valor = ($p2 > 0) ? $p2 : $p1;                
                $dinamica = 1;
                $regra = 1;
                break;
            case is_object($product) && $product->prod_sessao == 'FLV':
                $valor = ($p2 > 0) ? $p2 : $p1;
                $dinamica = 1;
                $regra = 2;
                break;
            case strpos($productData->PROMOCAO, 'APP') != false:
                $valor = ($p1 > 0) ? $p1 : $p2;
                $dinamica = 3;
                $regra = 4;
                break;
            case $p2 > 0 && $p3 > 0:
                $valor = "$p2!@#$p3";
                $dinamica = 7;
                $regra = 3;
                break;
            default:
                $valor = ($p2 > 0) ? $p2 : $p1;
                $dinamica = 1;
                $regra = 5;
                break;
        }

        $this->price->vlr_valores = $valor; //Preço
        $this->price->vlr_idcomercial = $dinamica; //Dinâmica
        $this->rule = $regra; //Regras

    }

    function createDailyPrint(object $dailyobject = null, $prices = [])
    {
        //Se não houver, adicionar req
        if (is_null($dailyobject)) {
            $productData = $this->request_data;
        }

        //Salvar/Atualizar Cartaz
        $dailyprint = new stdClass();
        $dailyprint->dp_fortam = (string) $productData->PROMOCAO;
        $dailyprint->dp_estabelecimento = (int) $productData->NROEMPRESA;
        $dailyprint->dp_nome = (string) 'CARTAZ PROMOCAO';
        $dailyprint->dp_data = (string) (new Carbon($productData->DTAINICIO))->format('Y-m-d');

        //Salvar/Atualizar preço
        $this->fromPrint = true;
        $this->mountPrice($productData);

        //Salvar preço de promoção, retornar se erro
        if (!$saved = $this->updateOrSavePrice()) {
            return false;
        }

        //Aplicar formatos baseados nas regras
        switch ($this->rule) {
            case 1:
                $cartaz = 83;
                $motivo = 339;
                $tamanho = '297/420';
                break;
            case 2:
                $cartaz = 83;
                $motivo = 339;
                $tamanho = '297/420';
                break;
            case 3:
                $cartaz = 211;
                $motivo = 424;
                $tamanho = '105/148';
                break;
            case 4:
                $cartaz = 66;
                $motivo = 425;
                $tamanho = '297/420';
                break;
            default:
                $cartaz = 50;
                $motivo = 422;
                $tamanho = '105/148';
                break;
        }

        //Propriedades de formatos
        $dailyprint->dp_dgcartaz = $cartaz;
        $dailyprint->dp_dgmotivo = $motivo;
        $dailyprint->dp_tamanho = $tamanho;

        //Se não houver, adicionar req
        if (empty($prices)) {
            $prices[] = $this->price;
        }

        return parent::createDailyPrint($dailyprint, $prices);
    }

}