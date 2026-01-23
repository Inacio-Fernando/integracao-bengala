<?php

use IntegracaoBengala\Bengala;
use Carbon\Carbon;
use Cartazfacil\IntegracaoVRSoftware\VRSoftware;

require('./vendor/autoload.php');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('memory_limit', '1536M');
ini_set('max_execution_time', '0');
ini_set('mysql.connect_timeout', '180');
ini_set('mysqli.reconnect', '1');
error_reporting(E_ALL);

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

//Criar diretório
if (!dir('./logs')) {
    mkdir('./logs', 0777, true);
}

//Cliente API
$vrsoftware = new VRSoftware();

function iterateProducts($index)
{

    global $vrsoftware;

    //Data inicial
    $dia = new Carbon();
    $time = $dia->format('d/m/Y H:m');

    //Retornar produtos do dia começando a partir zero hora
    $vrsoftware->getProductsByDate($index, $dia->format('d/m/Y') . " - 00:00:00");

    //Retornar Resposta da API
    $response = $vrsoftware->getResponseContent();

    if (!$response || !property_exists($response, 'retorno') || !property_exists($response->retorno, 'conteudo') || count($response->retorno->conteudo) <= 0) {
        file_put_contents('./logs/diary-log.txt', "\n" . Carbon::now() . " - Nenhum produto retornado em requisição de produtos. Início Período: $time, Páginação: $index.", FILE_APPEND);
        return;
    }

    //Iterar sobre items retornados
    foreach ($response->retorno->conteudo as $key => $dbProduct) {

        //Converter em objeto
        $productData = (object) $dbProduct;

        try {

            // Funções gerais
            $general = new Bengala();

            //Atribuir objeto contexto
            $general->setRequestData($productData);

            //Criar ou atualizar produto, em caso de erro registrar
            if (!$general->updateOrSaveProduct()) {
                throw new Exception("Produto:" . $productData->id . ". Não foi possível salvar produto. Erro: " . json_encode($productData), 1);
            }

            //Criar ou atualizar familia de produto se existir
            if (!empty((array) $productData->familia)) {
                if (!$family_id = $general->updateOrSaveFamily()) {
                    throw new Exception("Produto:" . $productData->id . ". Não foi possível salvar familia de produto. Erro: " . json_encode($productData), 1);
                }
            }

            //Preparar preços a inserir/atualizar
            $general->mountPrice(null, false);

            //Criar ou atualizar produto, em caso de erro registrar
            if (!empty((array) $general->price) && !$general->updateOrSavePrice((array) $general->price)) {
                throw new Exception("Produto:" . $productData->id . ". Não foi possível salvar preço. Erro: " . json_encode($productData), 1);
            }
        } catch (\Throwable $th) {
            file_put_contents('./logs/diary-error.txt', "\n" . Carbon::now() . ' - ' . $th->getMessage(), FILE_APPEND);
        }

        file_put_contents('./logs/diary-log.txt', "\n" . Carbon::now() . " - Produto Atualizado/Cadastrado. Produto ID:" . $productData->id, FILE_APPEND);
    }

    //Invocar função em closure
    iterateProducts($index + 1);
}

function iterateOffer($index)
{

    global $vrsoftware;
    global $family;

    //Data inicial
    $dia = new Carbon();
    $time = $dia->format('d/m/Y H:m');

    //Retornar produtos do dia começando a partir zero hora
    $vrsoftware->getPromotion($index, $dia->format('d/m/Y'));

    //Retornar Resposta da API
    $response = $vrsoftware->getResponseContent();

    //$response = json_decode(file_get_contents('dumps/ofertas.json', true));

    if (!$response || !property_exists($response, 'retorno') || !property_exists($response->retorno, 'conteudo') || count($response->retorno->conteudo) <= 0) {
        file_put_contents('./logs/diary-log.txt', "\n" . Carbon::now() . " - Nenhuma oferta retornada em requisição de ofertas. Início Período: $time, Páginação: $index.", FILE_APPEND);
        return;
    }

    //Iterar sobre items retornados
    foreach ($response->retorno->conteudo as $key => $offerItem) {

        //Converter em objeto
        $offerData = (object) $offerItem;

        try {

            //Funções gerais
            $general = new Bengala();

            //Filiais a ignorar interação
            if (in_array($offerData->idLoja, $general->ignoreBranch))
                continue;

            //Atribuir objeto contexto
            $general->setRequestData($offerData);

            //Variaveis de familia/filial
            $filial = (int) $offerData->idLoja;
            $tem_familia = (bool) $offerData->ofertaFamilia;
            $familia_produto = $offerData->familia;

            //Criar/Atualizar familia se existir
            if ($tem_familia) {
                if (!$has_family = $general->updateOrSaveFamily()) {
                    throw new Exception("Produto:" . $offerData->id . ". Não foi possível salvar familia de produto. Erro: " . json_encode($offerData), 1);
                }
            }

            //Carregar produto em contexto via DB, em caso de não existir requisitar API
            if (!$general->getProduct($offerData->idProduto, 'code')) {
                //Carregar produto via API
                $temp = clone $vrsoftware;
                $temp->getProduct((int) $offerData->idProduto);
                $produtoResponse = $temp->getResponseContent();

                //Se requisição não retornar produto
                if (!$produtoResponse || !property_exists($produtoResponse, 'retorno') || !property_exists($produtoResponse->retorno, 'conteudo') || count($response->retorno->conteudo) <= 0) {
                    throw new Exception("Produto:" . $offerData->idProduto . ". Não foi possível encontrar produto com 'id_produto' informado. Erro: " . json_encode($offerData) . $temp->getLastError(), 1);
                }

                //Formatar produto
                $productData = $general->mountProduct((object) $produtoResponse->retorno->conteudo[0]);

                //Salvar produto no banco
                if (!$general->updateOrSaveProduct($productData)) {
                    throw new Exception("Oferta:" . $offerData->id . ". Não foi possível salvar produto 'id_produto'. Erro: " . json_encode($offerData), 1);
                }
            }

            //Se item já existir em array de familias usadas por filial, pular próximo
            if (
                $tem_familia
                && key_exists($filial, $family)
                && in_array($familia_produto->id, $family[$filial])
            ) {
                continue;
            } else if ($tem_familia) {
                //Família de oferta registrada para não repetir
                $family[$filial][] = (int) $familia_produto->id;
            }

            //Atualização 14/02/2024 
            //Atualizar 'prod_desc' com descrição de familia se existir
            if ($tem_familia && $familia_produto) {
                $general->product->prod_desc = $familia_produto->descricao;
                unset($general->product->prod_familia);
                if (!$general->getDb()->updateProduct($general->product->prod_id, (object) $general->product)) {
                    throw new Exception("Oferta:" . $offerData->id . ". Não foi possível atualizar produto com prod_desc de família. Erro: " . json_encode($offerData), 1);
                }
            }

            //Preparar preços
            $general->mountPriceOffer();

            //Limpar preços antigos
            $general->clearProductPrices();

            //Salvar preço
            if (!$general->getDb()->insertPrice($general->price)) {
                throw new Exception("Oferta:" . $offerData->id . ". Não foi possível salvar preço. Erro: " . json_encode($offerData), 1);
            }

            //Atribuir preço salvo a propriedade
            $general->price = $general->getDb()->getPrice($general->getDb()->resultId());

            //Criar dailyprint (registra tb cf_valor)
            if (!$general->createDailyPrint()) {
                throw new Exception("Oferta:" . $offerData->id . ". Não foi possível salvar dailyprint da oferta. Erro: " . json_encode($offerData), 1);
            }

            //Atualização: 19/02/2024
            //Criar mídia indoor apenas com tipo de oferta contiver 'jornal'
            if (strpos(strtolower($offerData->tipoOferta), 'jornal') !== false) {
                if (!$general->createMediaIndoorQueue()) {
                    throw new Exception("Oferta:" . $offerData->id . ". Não foi possível salvar item de mídia indoor. Erro: " . json_encode($offerData), 1);
                }
            }
        } catch (\Throwable $th) {
            file_put_contents('./logs/diary-error.txt', "\n" . Carbon::now() . ' - ' . $th->getMessage(), FILE_APPEND);
        }

        file_put_contents('./logs/diary-log.txt', "\n" . Carbon::now() . " - Oferta/Dailyprint/MídiaIndoor Cadastrada. Oferta ID:" . $offerData->id, FILE_APPEND);
    }

    //Invocar função em closure
    iterateOffer($index + 1);
}

//Inicializar vars globais
$GLOBALS['family'] = array(0);
$GLOBALS['vrsoftware'] = $vrsoftware;

//Salvar produtos
iterateProducts(0);

//Salvar Ofertas
Bengala::clearDailyPrint(); //Limpar impressões
Bengala::clearMediaIndoor(); //Limpar midia indoor
iterateOffer(0); //Salvar ofertas/dailyprint/midia

echo "diary.php: Execução de script finalizado!";
