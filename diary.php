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
            if (!$product_id = $general->updateOrSaveProduct()) {
                throw new Exception("Produto:" . $productData->id . ". Não foi possível salvar produto. Erro: " . json_encode($productData), 1);
            }

            //Criar ou atualizar familia de produto se existir
            if (!empty((array) $productData->familia)) {
                if (!$family_id = $general->updateOrSaveFamily()) {
                    throw new Exception("Produto:" . $productData->id . ". Não foi possível salvar familia de produto. Erro: " . json_encode($productData), 1);
                }
            }

            //Preparar preços a inserir/atualizar
            $general->mountPrice();

            //Criar ou atualizar produto, em caso de erro registrar
            if (!$general->updateOrSavePrice((array) $general->price)) {
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
            if (in_array($offerData->idLoja, $general->ignoreBranch)) continue;

            //Atribuir objeto contexto
            $general->setRequestData($offerData);

            //Variaveis de familia/filial
            $filial = (int) $offerData->idLoja;
            $tem_familia = (boolean) $offerData->ofertaFamilia;
            $familia_produto = $offerData->familia;

            //Criar/Atualizar familia se existir
            if ($tem_familia) {
                if (!$family_id = $general->updateOrSaveFamily()) {
                    throw new Exception("Produto:" . $offerData->id . ". Não foi possível salvar familia de produto. Erro: " . json_encode($offerData), 1);
                }
            }

            //Se item já existir em array de familias usadas por filial, pular próximo
            if ($tem_familia
                && key_exists($filial, $family)
                && in_array($familia_produto->id, $family[$filial])
            ) {
                continue;
            } else if ($tem_familia) {
                //Família de oferta registrada para não repetir
                $family[$filial][] = (int) $familia_produto->id;
            }

            //Carregar produto em contexto via DB
            if (!$general->getProduct($offerData->idProduto, 'code')) {
                throw new Exception("Oferta:" . $offerData->id . ". Não foi possível encontrar produto em contexto 'id_produto'. Erro: " . json_encode($offerData), 1);
            }

            //Preparar preços a inserir/atualizar
            $general->mountPriceOffer();

            //Criar ou atualizar produto, em caso de erro registrar
            if (!$general->updateOrSavePrice(array($general->price))) {
                throw new Exception("Oferta:" . $offerData->id . ". Não foi possível salvar preço. Erro: " . json_encode($offerData), 1);
            }

            //Criar dailyprint (registra tb cf_valor)
            /*if (!$general->createDailyPrint()) {
                throw new Exception("Oferta:" . $offerData->id . ". Não foi possível salvar oferta/dailyprint. Erro: " . json_encode($offerData), 1);
            }

            //Se cf_valor da oferta for dinamica = 1
            if ($general->price->vlr_idcomercial == 1) {
                //Criar um nova linha de mídia indoor
                if (!$general->createMediaIndoorQueue()) {
                    throw new Exception("Oferta:" . $offerData->id . ". Não foi possível salvar item de mídia indoor. Erro: " . json_encode($offerData), 1);
                }
            }*/           

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
Bengala::clearMediaIndoor(); //Excluir md_token='JORNAL' (Limpar midias)
iterateOffer(0); //Salvar ofertas/dailyprint/midia

echo "diary.php: Execução de script finalizado!";