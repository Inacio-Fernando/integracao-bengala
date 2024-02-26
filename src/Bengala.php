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
        $product->prod_proporcao = substr($this->proportion_formatter($productData->proporcao), 0, 50);
        $product->prod_sessao = substr($productData->mercadologico1, 0, 75);
        $product->prod_grupo = substr($productData->mercadologico2, 0, 75);
        $product->prod_subgrupo = substr($productData->mercadologico3, 0, 75);

        //Verificar se existem dados em 'familia'
        /*if (!empty((array) $productData->familia)) {
            $product->prod_familia = substr($productData->familia->id, 0, 50);
        }*/

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

            if (in_array($p->loja->id, $this->ignoreBranch))
                continue;

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
        $p1 = isset($offerData->precoNormalOferta) ? $this->price_formmater($offerData->precoNormalOferta) : 0;

        //Preço Oferta
        $p2 = isset($offerData->precoOferta) ? $this->price_formmater($offerData->precoOferta) : 0;

        //Preço Connect
        $p3 = isset($offerData->precoOfertaConnect) ? $this->price_formmater($offerData->precoOfertaConnect) : 0;

        //Se preço Oferta existir, senão preço Normal
        $preco = ($p2 > 0) ? $p2 : $p1;

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
        $p1 = (!empty($productData->precoVenda)) ? $this->price_formmater($productData->precoVenda) : 0;

        //Atribuir filial
        $filial = (isset($productData->loja) && property_exists($productData->loja, 'id')) ? (int) $this->branchCheck((int) $productData->loja->id) : 1;

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
        $dailyprint->dp_estabelecimento = (int) $offerData->idLoja;
        $dailyprint->dp_nome = (string) "IMPRESSAO_DIARIA_BENGALA";
        $dailyprint->dp_data = (string) (new Carbon($offerData->dataInicio))->format('Y-m-d');

        //Se preço não existir
        if (empty($this->price)) return false;

        //Instanciar data da oferta
        $date = Carbon::createFromFormat('Y-m-d', $offerData->dataInicio);

        //Verificar se dataInicio é ultima quinta-feira do mês
        $listaCartaz = ($date->isFriday() && $date->isLastWeek()) ? $this->createDiaBPrint() : $this->createPrint();

        //Se não houver cartazes para impressão
        if (empty($listaCartaz)) return false;

        //Cadastrar multiplas impressões
        foreach ($listaCartaz as $cartaz) {
            $dailyprint->dp_fortam = (string) $cartaz['dp_fortam'];
            $dailyprint->dp_tamanho = (string) $cartaz['dp_tamanho'];
            $dailyprint->dp_dgcartaz = (int) $cartaz['dp_dgcartaz'];
            $dailyprint->dp_dgmotivo = (int) $cartaz['dp_dgmotivo'];
            $result = $this->saveDailyPrint($dailyprint, array($this->price));
        }

        return $result;
    }

    function createDiaBPrint()
    {
        //Cartaz
        $modeloCartaz = ['dp_dgcartaz', 'dp_dgmotivo', 'dp_tamanho', 'dp_fortam'];
        $listaCartaz = [];

        //Variaveis comparação
        $filial = (int) $this->price->vlr_filial;
        $dinamica = (int) $this->price->vlr_idcomercial;

        switch (true) {
            case in_array($filial, [1, 2, 3, 4, 5]):
                switch ($dinamica) {
                    case 1:
                        $listaCartaz = [
                            array_combine($modeloCartaz, [40, 39, '148/105', 'A6 PAISAGEM']),
                            array_combine($modeloCartaz, [42, 42, '600/640', 'BANNER 600X640']),
                            array_combine($modeloCartaz, [202, 85, '420/594', 'A3 COMBINADO']),
                            array_combine($modeloCartaz, [41, 40, '210/297', 'A4 RETRATO'])
                        ];
                        break;
                    default:
                        break;
                }
                break;
            case in_array($filial, [6]):
                switch ($dinamica) {
                    case 1:
                        $listaCartaz = [
                            array_combine($modeloCartaz, [40, 39, '148/105', 'A6 PAISAGEM']),
                            array_combine($modeloCartaz, [41, 40, '210/297', 'A4 RETRATO'])
                        ];
                        break;
                    default:
                        break;
                }
                break;
            default:
                break;
        }

        return $listaCartaz;
    }

    function createPrint()
    {
        //Cartaz
        $modeloCartaz = ['dp_dgcartaz', 'dp_dgmotivo', 'dp_tamanho', 'dp_fortam'];
        $listaCartaz = [];

        //Variaveis comparação
        $filial = (int) $this->price->vlr_filial;
        $dinamica = (int) $this->price->vlr_idcomercial;

        switch (true) {
            case in_array($filial, [1, 2, 3, 4, 5]):
                switch ($dinamica) {
                    case 5:
                        $listaCartaz = [
                            array_combine($modeloCartaz, [229, 198, '148/105', 'A6 PAISAGEM']),
                            array_combine($modeloCartaz, [232, 199, '600/640', 'BANNER 600X640']),
                            array_combine($modeloCartaz, [233, 201, '420/594', 'A3 COMBINADO']),
                            array_combine($modeloCartaz, [235, 1, '210/297', 'A4 RETRATO']),
                        ];
                        break;
                    default:
                        $listaCartaz = [
                            array_combine($modeloCartaz, [118, 113, '148/105', 'A6 PAISAGEM']),
                            array_combine($modeloCartaz, [21, 19, '600/640', 'BANNER 600X640']),
                            array_combine($modeloCartaz, [91, 85, '420/594', 'A3 COMBINADO']),
                            array_combine($modeloCartaz, [1, 1, '210/297', 'A4 RETRATO']),
                        ];
                        break;
                }
                break;
            case in_array($filial, [6]):
                switch ($dinamica) {
                    case 5:
                        $listaCartaz = [
                            array_combine($modeloCartaz, [229, 198, '148/105', 'A6 PAISAGEM']),
                            array_combine($modeloCartaz, [235, 1, '210/297', 'A4 RETRATO']),
                            array_combine($modeloCartaz, [276, 214, '900/1220', 'BANNER DUPLO'])
                        ];
                        break;
                    default:
                        $listaCartaz = [
                            array_combine($modeloCartaz, [118, 113, '148/105', 'A6 PAISAGEM']),
                            array_combine($modeloCartaz, [1, 1, '210/297', 'A4 RETRATO']),
                            array_combine($modeloCartaz, [258, 214, '900/1220', 'BANNER DUPLO'])
                        ];
                        break;
                }
                break;
            default:
                break;
        }

        return $listaCartaz;
    }

    /** 
     * @code 
     * Função copiada de General, apenas removemos a opção de atualizar dailyprint
     */
    function saveDailyPrint(object $dailyobject, array $prices)
    {

        //Default propriedades
        $dailyprint = new stdClass();
        $dailyprint->dp_produto = null;
        $dailyprint->dp_valor = null;
        $dailyprint->dp_dgcartaz = 38;
        $dailyprint->dp_dgmotivo = 3;
        $dailyprint->dp_empresa = 1;
        $dailyprint->dp_estabelecimento = 1;
        $dailyprint->dp_usuario = 1;
        $dailyprint->dp_data = date('Y-m-d');
        $dailyprint->dp_hora = '3:00';
        $dailyprint->dp_tamanho = '105/148';
        $dailyprint->dp_fortam = 'A6 RETRATO';
        $dailyprint->dp_nome = 'DE POR / A6 RETRATO/ PRECO BAIXO';
        $dailyprint->dp_mobile = 0;
        $dailyprint->dp_auditoria = 0;
        $dailyprint->dp_qntparcela = 1;
        $dailyprint->dp_idtaxa = 'sjuros';

        //Juntar objetos
        $dailyprint = (object) array_merge((array) $dailyprint, (array) $dailyobject);

        foreach ($prices as $key => $price) {

            //Objeto impressão diária
            $dailyprint->dp_produto = $price->vlr_produto;

            //Se não atribuido, usar de valor
            $dailyprint->dp_estabelecimento = (!empty($price->vlr_filial)) ? $price->vlr_filial : 1;

            //Se não atribuido, usar de valor
            if (empty($dailyprint->dp_data)) {
                $dailyprint->dp_data = $price->vlr_data_de;
            }

            //Se informado ID de cf_valor
            if (property_exists($price, 'vlr_id')) {
                $dailyprint->dp_valor = $price->vlr_id;
            }

            //Inserir item de impressão para cada filial
            $result = $this->getDb()->insertDailyPrint($dailyprint);

        }

        return $result;
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
                $tamanhoPapel = 21;
                break;
            case 5:
                $idMotivo = 216;
                $idCartaz = 279;
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
        $midia->md_token = "JORNAL_B".$this->price->vlr_filial;
        $midia->md_lista = "Nao";
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

    public function branchCheck(int $idLoja)
    {
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

    public function proportion_formatter($text)
    {
        return (in_array($text, ['PC', 'FD', 'CX'])) ? 'UN' : $text;
    }

    static function clearMediaIndoor($truncate = false)
    {
        $db = (new self)->getDb();
        
        //Limpar apenas lista de items tabela cf_midia baseado em data
        $query = $db->conn->prepare("DELETE FROM cf_midia WHERE md_token LIKE '%JORNAL_B%'");
        $result = $query->execute();
        return $result;
    }

    static function clearDailyPrint($truncate = false)
    {
        //Limpar apenas lista de items tabela cf_dailyprint baseado nos parametros
        return (new self)->getDb()->deleteFrom('cf_dailyprint', 'dp_nome', 'IMPRESSAO_DIARIA_BENGALA');
    }

}