<?php

use IntegracaoBengala\Bengala;
use Carbon\Carbon;
use Cartazfacil\IntegracaoVRSoftware\VRSoftware;
use IntegracaoBengala\Infra\Database\Database;
use IntegracaoBengala\Infra\Database\Models\ProductTable;
use IntegracaoBengala\Infra\Database\Models\ValueTable;

require_once './vendor/autoload.php';

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

Database::setupEloquent();

// Funções gerais
$GLOBALS['vrsoftware'] = new VRSoftware(); //API
$GLOBALS['general'] = new Bengala(); //Regras
$GLOBALS['dia'] = new Carbon();

function iterateProducts($index)
{

    global $vrsoftware, $general, $dia;

    $productUpdateList = collect([]);
    $productInsertList = collect([]);
    $priceUpdateList = [];
    $priceInsertList = [];

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

    $productResponseCollection = collect($response->retorno->conteudo);

    $codCollection = $productResponseCollection->unique('id', true)->pluck('id')->flatten();

    $productDBList = ProductTable::select(['prod_id', 'prod_cod'])->whereIn('prod_cod', $codCollection->toArray())->with('prices')->get();

    //Iterar sobre items retornados
    foreach ($productResponseCollection->all() as $requestItem) {

        //Converter em objeto
        $productData = (object) $requestItem;

        try {

            //Atribuir objeto contexto
            $general->setRequestData($productData);

            $product = $general->mountProduct();

            //Criar ou atualizar familia de produto se existir
            if (!empty((array) $productData->familia) && $general->mountFamily() && !$general->updateOrSaveFamily()) {
                throw new Exception("Produto:" . $productData->id . ". Não foi possível salvar familia de produto. Erro: " . json_encode($productData), 1);
            }

            //Verificar produto existente e atribuir produto em lista de update ou inserção
            if (!empty($productDBList) && !empty($current = $productDBList->where('prod_cod', $productData->id)->first())) {

                //produto
                $general->product->prod_id = $current->prod_id;
                $productUpdateList->push((array) $general->product);

                //precos
                $prices = collect((array) $general->mountPrice());

                if ($current->prices->count() > 0) {
                    $current->prices->map(function ($value) use (&$prices, &$priceInsertList, &$priceUpdateList) {
                        $pIndex = $prices->search(function ($item) use ($value) {
                            return $item['vlr_produto'] == $value->vlr_produto && $item['vlr_filial'] == $value->vlr_filial && $item['vlr_idcomercial'] == $value->vlr_idcomercial;
                        });

                        if ($pIndex) {
                            $p = $prices[$pIndex];
                            $p['vlr_id'] = $value->vlr_id;
                            $priceUpdateList[] = (array) $p;
                            unset($prices[$pIndex]);
                        }

                    });
                } else {
                    $priceInsertList = array_merge($prices->toArray(), $priceInsertList);
                }

            } else {
                $productInsertList->push((array) $product);
            }

        } catch (\Throwable $th) {
            file_put_contents('./logs/diary-update-error.txt', "\n" . $dia::now() . ' - ' . $th->getMessage(), FILE_APPEND);
        }

        //Limpar variaveis (memória)
        unset($productData, $product, $price);

    }

    //Update Produtos
    if ($productUpdateList->count() > 0) {
        ProductTable::batchUpdate($productUpdateList->toArray(), 'prod_id');
    }

    //Insert Produtos
    if ($productInsertList->count() > 0 && ProductTable::insert($productInsertList->toArray())) {

        $productDBList = ProductTable::select(['prod_id', 'prod_cod'])->whereIn('prod_cod', $productInsertList->pluck('prod_cod')->flatten())->with('prices')->get();

        $filteredRequest = $productResponseCollection->whereIn('id', $productDBList->pluck('prod_cod')->flatten()->toArray());

        foreach ($productDBList as $product) {

            //Filtrar items de request
            $requestItems = $filteredRequest->where('id', $product->prod_cod)->all();

            //Atribuir valores ao array de insert de preços
            foreach ($requestItems as $requestItem) {
                $general->setRequestData((object) $requestItem);
                $general->mountProduct();
                $general->product->prod_id = $product->prod_id;
                $priceInsertList = array_merge((array) $general->mountPrice(), $priceInsertList);
            }
        }
    }

    //Update Preços
    if (count($priceUpdateList) > 0) {
        ValueTable::batchUpdate($priceUpdateList, 'vlr_id');
    }

    //Insert Preços
    if (count($priceInsertList) > 0) {

        //Código de produtos
        $prod_cods = collect($priceInsertList)->pluck('vlr_produto')->flatten()->toArray();

        //Limpeza de preços antes de insert
        count($prod_cods) > 0 && ValueTable::whereIn('vlr_produto', $prod_cods)->delete();

        try {
            foreach (array_chunk($priceInsertList, 1000) as $chunkPrice) {
                ValueTable::insert($chunkPrice);
            }
        } catch (\Throwable $th) {
            throw new Exception("Não foi possível salvar chunk preços. Erro: " . json_encode($th->getMessage(), $chunkPrice), 1);
        }
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
iterateProducts($prod_start);

//Resetar posição para inicio em arquivo de status
file_put_contents('./diary-update.txt', (string) 0);

echo "diary-update.php: Execução de script finalizado!";
