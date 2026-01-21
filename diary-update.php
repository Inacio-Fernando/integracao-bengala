<?php

use IntegracaoBengala\Bengala;
use Carbon\Carbon;
use Cartazfacil\IntegracaoVRSoftware\VRSoftware;
use IntegracaoBengala\Models\ProductTable;
use IntegracaoBengala\Models\ValueTable;

require_once './vendor/autoload.php';
require_once './src/Models/boot.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('memory_limit', '1536M');
ini_set('max_execution_time', '0');
ini_set('mysql.connect_timeout', '180');
ini_set('mysqli.reconnect', '1');
error_reporting(E_ERROR);

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

//Criar diretório
if (!dir('./logs')) {
    mkdir('./logs', 0777, true);
}

// Funções gerais
$GLOBALS['vrsoftware'] = new VRSoftware(); //API
$GLOBALS['general'] = new Bengala(); //Regras
$GLOBALS['dia'] = new Carbon();

function iterateProducts($index)
{

    global $vrsoftware, $general, $dia;

    $productUpdateList = collect([]);
    $productInsertList = collect([]);
    $priceInsertList = collect([]);

    //Data inicial
    $time = $dia->format('d/m/Y H:m');

    //Retornar produtos do dia
    $vrsoftware->getProducts($index);

    //Retornar Resposta da API
    $response = $vrsoftware->getResponseContent();

    if (!$response || !property_exists($response, 'retorno') || !property_exists($response->retorno, 'conteudo') || count($response->retorno->conteudo) <= 0) {
        file_put_contents('./logs/diary-update-log.txt', "\n" . Carbon::now() . " - Nenhum produto retornado em requisição de produtos. Início Período: $time, Páginação: $index.", FILE_APPEND);
        return;
    }

    //Salvar requisição em arquivo
    //file_put_contents('./dumps/produtos.json', json_encode($response));

    $productResponseCollection = collect($response->retorno->conteudo);

    $codCollection = $productResponseCollection->pluck('id')->flatten();

    $productDBList = ProductTable::select(['prod_id', 'prod_cod'])->whereIn('prod_cod', $codCollection)->with('prices')->get();

    //Iterar sobre items retornados
    foreach ($productResponseCollection->all() as $requestItem) {

        //Converter em objeto
        $productData = (object) $requestItem;

        try {

            //Atribuir objeto contexto
            $general->setRequestData($productData);

            $product = $general->mountProduct();

            //Criar ou atualizar familia de produto se existir
            if (!empty((array) $productData->familia) && $general->mountFamily()) {
                if (!$general->updateOrSaveFamily()) {
                    throw new Exception("Produto:" . $productData->id . ". Não foi possível salvar familia de produto. Erro: " . json_encode($productData), 1);
                }
            }

            //Verificar produto existente
            $current = $productDBList->where('prod_cod', $productData->id)->first();

            //Atribuir produto em lista de update ou inserção
            if (!empty($current)) {

                $general->product->prod_id = $current->prod_id;
                $productUpdateList->push((array) $general->product);

                $price = $general->mountPrice();
                $priceInsertList->merge((array) $price);

                continue;

            }

            $productInsertList->push((array) $product);


        } catch (\Throwable $th) {
            file_put_contents('./logs/diary-update-error.txt', "\n" . $dia::now() . ' - ' . $th->getMessage(), FILE_APPEND);
        }

        file_put_contents('./logs/diary-update-log.txt', "\n" . $dia::now() . " - Produto Atualizado/Cadastrado. Produto ID:" . $productData->id, FILE_APPEND);

        //Limpar variaveis (memória)
        unset($productData, $product, $price);

    }

    //Update Produtos
    if (!empty($productUpdateList)) {
        ProductTable::batchUpdate($productUpdateList, 'prod_id');
    }

    //Insert Produtos
    if (!empty($productInsertList) && ProductTable::batchInsert('*', $productInsertList, $productInsertList->toArray())) {

        $insertDBList = ProductTable::select(['prod_id', 'prod_cod'])->whereIn('prod_cod', $productInsertList->pluck('prod_cod')->flatten())->with('prices')->get();

        foreach ($productResponseCollection->whereIn('id', $insertDBList->pluck('prod_cod')->flatten()) as $requestItem) {

            $general->setRequestData((object) $requestItem);
            $product = new StdClass();
            $product->prod_id = $insertDBList->firstWhere('prod_cod', $requestItem['id']);
            $general->product = $product;
            $priceInsertList->merge((array) $general->mountPrice());

        }
    }

    //Insert Preços
    if (!empty($priceInsertList)) {
        ValueTable::batchInsert('*', $priceInsertList->toArray(), count($priceInsertList));
    }

    //Salvar posição em arquivo de status
    file_put_contents('./diary-update.txt', (string) $index);

    //Limpar variaveis (memória)
    unset($response, $productResponseCollection, $productDBList, $productInsertList, $productUpdateList, $priceInsertList);

    //Invocar função em closure
    iterateProducts($index + 1);

}

//Atribuir ponto ao interrompido anteriormente
$prod_saved_index = (int) file_get_contents('./diary-update.txt');
$prod_start = (empty($prod_saved_index) || !$prod_saved_index || $prod_saved_index <= 0) ? 0 : $prod_saved_index;

//Iniciar
iterateProducts(0);

//Resetar posição para inicio em arquivo de status
file_put_contents('./diary-update.txt', (string) 0);

echo "diary-update.php: Execução de script finalizado!";
