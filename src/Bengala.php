<?php
namespace IntegracaoBengala;

use Carbon\Carbon;
use Cartazfacil\DatabaseIntegracao\General;
use Dotenv\Dotenv as Dotenv;
use Exception;
use stdClass;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

class Bengala extends General
{
    public $family = null;
    protected $fromPrint = false;

    function mountProduct($productData = null)
    {
        if (is_null($productData)) {
            $productData = $this->request_data;
        }

        $product = new stdClass();
        $product->prod_cod = substr($productData->id, 0, 50);
        $product->prod_nome = $this->name_formatter($productData->descricaoCompleta);
        $product->prod_desc = $this->name_formatter($productData->descricaoCompleta, 200);
        $product->prod_desc_tabloide = $this->name_formatter($productData->descricaoReduzida, 200);
        $product->prod_proporcao = substr($productData->proporcao, 0, 50);
        $product->prod_sessao = substr($productData->mercadologico1, 0, 75);
        $product->prod_grupo = substr($productData->mercadologico2, 0, 75);
        $product->prod_subgrupo = substr($productData->mercadologico3, 0, 75);

        //Verificar se existem dados em 'familia'
        if (!empty((array) $productData->familia)) {
            $product->prod_familia = substr($productData->familia->id, 0, 50);
        }

        //Verificar se existem dados em 'produtoAutomacao'
        if (!empty($productData->produtoAutomacao)) {
            $product->prod_sku = substr($productData->produtoAutomacao[0]->ean, 0, 300);
            $product->prod_embalagem = substr($productData->produtoAutomacao[0]->qtdeEmbalagem, 0, 100);
        }        

        $product->prod_empresa = 1;
        $product->prod_estabelecimento = 1;

        //Atribuir departamento
        $this->depto = $product->prod_sessao;

        return $this->product = $product;

    }

    function mountFamily($productData = null)
    {
        if (is_null($productData)) {
            $productData = $this->request_data;
        }

        $family = new stdClass();
        $family->fam_id = $productData->familia->id;
        $family->fam_nomefamilia = $this->name_formatter($productData->familia->descricao);

        return $this->family = $family;

    }

    function mountPrice($productData = null)
    {
        if (is_null($productData)) {
            $productData = $this->request_data;
        }

        if (empty($productData)) {
            return false;
        }

        $this->price = [];

        foreach ($productData->produtoAutomacao as $p) {
            
            //Ver qual data
            $data_de = date('Y-m-d');
            $data_ate = date('Y-m-d');

            //Criando objeto cf_preco
            $price = new stdClass();
            $price->vlr_filial = 1;
            $price->vlr_empresa = 1;
            $price->vlr_usuario = 1;
            $price->vlr_data_de = $data_de;
            $price->vlr_data_ate = $data_ate;
            $price->vlr_hora = '03:03';           

            //Função para formatação de preço e dinâmica
            $this->priceProduct($price, $p);
            $this->price[] = $price;

        }        

        return (object) $this->price;
    }

    function mountPriceOffer($offerData = null)
    {
        if (is_null($offerData)) {
            $offerData = $this->request_data;
        }

        if (empty($offerData)) {
            return false;
        }

        //Ver qual data
        $data_de = date('Y-m-d');
        $data_ate = date('Y-m-d');

        //Criando objeto cf_preco
        $price = new stdClass();
        $price->vlr_filial = 1;
        $price->vlr_empresa = 1;
        $price->vlr_usuario = 1;
        $price->vlr_data_de = $data_de;
        $price->vlr_data_ate = $data_ate;
        $price->vlr_hora = '03:03';           

        //Função para formatação de preço e dinâmica em objeto 'oferta'
        $this->fromPrint && $this->priceOffer($price, $offerData);

        $this->price = $price;       

        return (object) $this->price;
    }

    private function priceOffer($price, $offerData)
    {

        //Preço normal
        $p1 = isset($offerData->precoNormalOferta)? $this->price_formmater($offerData->precoNormalOferta) : 0;

        //Preço Oferta
        $p2 = isset($offerData->precoOferta)? $this->price_formmater($offerData->precoOferta): 0;

        //Atribuir dados 
        $price->vlr_valores = "$p1!@#$p2"; //Preço
        $price->vlr_idcomercial = 2; //Dinâmica
        $price->vlr_produto = (int) $offerData->id_produto;
        $price->vlr_filial = (int) $offerData->id_loja;
        $price->vlr_data_de = $offerData->dataInicio;
        $price->vlr_data_ate = $offerData->dataTermino;        

    }

    private function priceProduct($price, $productData)
    {

        //Formatar preço string
        $p1 = (!empty($productData->precoVenda))? $this->price_formmater($productData->precoVenda) : 0;

        //Atribuir filial
        $filial = (isset($productData->loja) && property_exists($productData->loja, 'id'))? (int) $productData->loja->id : 1;
        
        //Atribuir dados padrão
        $price->vlr_valores = $p1; //preço comum
        $price->vlr_idcomercial = 1; //Dinâmica
        $price->vlr_produto = (int) $this->product->prod_id; //Produto
        $price->vlr_filial = (int) $filial; //filial

    }

    function createDailyPrint(object $dailyobject = null, $prices = [])
    {
        //Se não houver, adicionar req
        if (is_null($dailyobject)) {
            $productData = $this->request_data;
        }

        //Salvar/Atualizar Cartaz
        $dailyprint = new stdClass();
        $dailyprint->dp_fortam = (string) 'A6 PAISAGEM';
        $dailyprint->dp_estabelecimento = (int) 1;
        $dailyprint->dp_nome = (string) 'Cartazes - ' . $productData->dataInicio;
        $dailyprint->dp_data = (string) (new Carbon($productData->dataInicio))->format('Y-m-d');

        //Salvar/Atualizar preço
        $this->fromPrint = true;
        $this->mountPriceOffer($productData);

        //Salvar preço de promoção, retornar se erro
        if (!$saved = $this->updateOrSavePrice(array($this->price))) {
            return false;
        }

        //Se não houver, adicionar req
        if (empty($prices)) {
            $prices[] = $this->price;
        }

        //Propriedades de formatos
        $dailyprint->dp_dgcartaz = 118;
        $dailyprint->dp_dgmotivo = 113;
        $dailyprint->dp_tamanho = '148/105';

        return parent::createDailyPrint($dailyprint, $prices);
    }

}