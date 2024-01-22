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
    public $ignoreBranch = [6, 7, 9];

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

            if (in_array($p->loja->id, $this->ignoreBranch)) continue;
            
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
        $this->priceOffer($price, $offerData);

        $this->price = $price;       

        return (object) $this->price;
    }

    private function priceOffer($price, $offerData)
    {

        //Preço normal
        $p1 = isset($offerData->precoNormalOferta)? $this->price_formmater($offerData->precoNormalOferta) : 0;

        //Preço Oferta
        $p2 = isset($offerData->precoOferta)? $this->price_formmater($offerData->precoOferta): 0;

        //Preço Connect
        $p3 = isset($offerData->precoOfertaConnect)? $this->price_formmater($offerData->precoOfertaConnect): 0;

        //Se preço Oferta existir, senão preço Normal
        $preco = ($p2 > 0)? $p2 : $p1;

        //Valores padrão
        $price->vlr_valores = $preco; //Preço
        $price->vlr_idcomercial = 1; //Dinâmica

        //Se preço Connect existir e maior que 0
        if ($p3 > 0) {
            $price->vlr_valores = "$preco!@#$p3"; //Preço
            $price->vlr_idcomercial = 5; //Dinâmica    
        }

        //Atribuir dados 
        $price->vlr_produto = (int) $this->product->prod_id;
        $price->vlr_filial = (int) $this->branchCheck((int) $offerData->idLoja);
        $price->vlr_data_de = $offerData->dataInicio;
        $price->vlr_data_ate = $offerData->dataTermino;        

    }

    private function priceProduct($price, $productData)
    {

        //Formatar preço string
        $p1 = (!empty($productData->precoVenda))? $this->price_formmater($productData->precoVenda) : 0;

        //Atribuir filial
        $filial = (isset($productData->loja) && property_exists($productData->loja, 'id'))? (int) $this->branchCheck((int) $productData->loja->id) : 1;
        
        //Atribuir dados padrão
        $price->vlr_valores = $p1; //preço comum
        $price->vlr_idcomercial = 1; //Dinâmica
        $price->vlr_produto = (int) $this->product->prod_id; //Produto
        $price->vlr_filial = (int) $filial; //filial

    }

    function createDailyPrint(object $dailyobject = null, $prices = [])
    {
        //Se não houver, adicionar req
        $offerData = $this->request_data;

        //Salvar/Atualizar Cartaz
        $dailyprint = new stdClass();
        $dailyprint->dp_produto = (int) $this->product->prod_id;
        $dailyprint->dp_fortam = (string) 'A6 PAISAGEM';
        $dailyprint->dp_tamanho = '148/105';
        $dailyprint->dp_estabelecimento = (int) 1;
        $dailyprint->dp_nome = (string) $offerData->tipoOferta;
        $dailyprint->dp_data = (string) (new Carbon($offerData->dataInicio))->format('Y-m-d');        

        //Salvar/Atualizar preço
        $this->mountPriceOffer($offerData);

        //Salvar preço de promoção, retornar se erro
        if (!$saved = $this->updateOrSavePrice(array($this->price))) {
            return false;
        }

        //Se não houver, adicionar req
        if (empty($prices)) {
            $prices[] = $this->price;
        }

        //Propriedades de formatos
        $dailyprint->dp_dgcartaz = 146;
        $dailyprint->dp_dgmotivo = 113;

        //Se oferta app
        if ($offerData->tipoOferta == "OFERTA APP") {
            $dailyprint->dp_dgcartaz = 229;
            $dailyprint->dp_dgmotivo = 198;
        }

        return parent::createDailyPrint($dailyprint, $prices);
    }

    function createMediaIndoorQueue()
    {
        //Dados utilizados
        $offerData = $this->request_data;

        //Atributos para composição de url de impressão direta
        $idProduto = $this->product->prod_id;
        $idPreco = $this->price->vlr_id;

        //Se nulo, formato definido por departamento
        switch ((int) $this->price->vlr_idcomercial) {
            case 1:
                $idMotivo = 33;
                $idCartaz = 33;
                $tamanhoPapel = 340;
                break;
            default:
                $idMotivo = null;
                $idCartaz = null;
                $tamanhoPapel = null;
                break;
        }

        //Não foi criado item em impressão
        if (is_null($idMotivo) || is_null($tamanhoPapel) || is_null($idCartaz)) {
            return false;
        }

        //Objeto de impressão
        $midia = new stdClass();
        $midia->md_url = "https://bengala.cartazfacil.pro/papel-lote-cartaz.php?id_dMotivo=$idMotivo&tam_papel=$tamanhoPapel&id_dCartaz=$idCartaz&id_valor=$idPreco&nParcelas=1&tax_id=null&cartazOuTv=tv&id_produto=$idProduto";
        $midia->md_empresa = '1';
        $midia->md_filial = (string) $this->price->vlr_filial;
        $midia->md_usuario = (string) $this->price->vlr_usuario;
        $midia->md_quadrante = '1';
        $midia->md_divisao = '1';
        $midia->md_tamtv = '43';
        $midia->md_tempo = '10';
        $midia->md_token = "JORNAL";
        $midia->md_lista = "nao";
        $midia->md_idProduto = (int) $idProduto;
        $midia->md_idValorProd = (int) $idPreco;
        $midia->md_transicao = 'NULL';
        $midia->md_dgCartaz = $idCartaz;
        $midia->md_datainicio = (string) (new Carbon($offerData->dataInicio))->format('Y-m-d');
        $midia->md_datafim = (string) (new Carbon($offerData->dataTermino))->format('Y-m-d');

        //Inserir item de mídia indoor
        $result = $this->getDb()->insertMediaIndoorQueue($midia);

        return $result;
    }

    public function branchCheck(int $idLoja) {        
        switch ($idLoja) {
            case 8:
                $loja = 6;
                break;            
            default:
                $loja = $idLoja;
                break;
        }

        return $loja;
    }

    static function clearMediaIndoor($truncate = false)
    {
        //Limpar apenas lista de items tabela cf_midia baseado nos parametros
        return (new self)->getDb()->deleteFrom('cf_midia', 'md_token', 'JORNAL');
    }

}