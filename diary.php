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

$GLOBALS['vrsoftware'] = $vrsoftware;

function iterateProducts($index)
{

    global $vrsoftware;

    //Data inicial
    $dia = new Carbon('01-09-2023');
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

            //Criar ou atualizar produto, em caso de erro registrar
            if (!$product_id = $general->updateOrSavePrice()) {
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

    //Data inicial
    $dia = new Carbon('01-11-2023');
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
    foreach ($response->retorno->conteudo as $key => $dbProduct) {

        //Converter em objeto
        $productData = (object) $dbProduct;

        try {

            // Funções gerais
            $general = new Bengala();

            //Atribuir objeto contexto
            $general->setRequestData($productData);

            //Criar ou atualizar produto, em caso de erro registrar
            if (!$general->createDailyPrint()) {
                throw new Exception("Oferta:" . $productData->id . ". Não foi possível salvar oferta/dailyprint. Erro: " . json_encode($productData), 1);
            }

        } catch (\Throwable $th) {
            file_put_contents('./logs/diary-error.txt', "\n" . Carbon::now() . ' - ' . $th->getMessage(), FILE_APPEND);
        }

        file_put_contents('./logs/diary-log.txt', "\n" . Carbon::now() . " - Oferta/Dailyprint Cadastrada. Oferta ID:" . $productData->id, FILE_APPEND);

    }

    //Invocar função em closure
    iterateOffer($index + 1);
}

//Iniciar funções
iterateProducts(0);
iterateOffer(0);

echo "diary.php: Execução de script finalizado!";