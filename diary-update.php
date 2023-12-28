<?php
use Carbon\Carbon;

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
$bluesoft = new Bluesoft($_ENV['API_CLIENT'], $_ENV['API_TOKEN']);

$GLOBALS['bluesoft'] = $bluesoft;

function iterateProducts($index, $count)
{

    global $bluesoft;

    //Data inicial
    $dia = new Carbon('2023-06-01 00:00');
    $time = $dia->format('d/m/Y H:m');

    //Retornar produtos do dia
    $bluesoft->getProducts($index, $count, $time, ['status' => 'ATIVO']);
    
    //Retornar Resposta da API
    $response = $bluesoft->getResponseContent();

    if (!$response || !key_exists('data', $response) || count($response->data) <= 0) {
        file_put_contents('./logs/diary-update-log.txt', "\n" . Carbon::now() . " - Nenhum produto retornado em requisição de produtos. Início Período: $time, Páginação: $index.", FILE_APPEND);
        return;
    }

    //Salvar requisição em arquivo
    file_put_contents('./dumps/produtos.json', json_encode($response));

    //Iterar sobre items retornados via DATABASE
    foreach ($response->data as $key => $dbProduct) {

        //Converter em objeto
        $productData = (object) $dbProduct;

        try {

            // Funções gerais
            $general = new General();

            //Atribuir objeto contexto
            $general->setRequestData($productData);

            //Criar ou atualizar produto, em caso de erro registrar
            if (!$product_id = $general->updateOrSaveProduct()) {
                throw new Exception("Produto:" . $productData->produtoKey . ". Não foi possível salvar produto. Erro: " . json_encode($productData), 1);
            }

            //Retornar preços de produto
            $bluesoft->getPrices(0, 1000,  $time, ['produtoKey' => $productData->produtoKey]);

            //Retornar Resposta da API
            $prices = $bluesoft->getResponseContent();

            //Se houver retorno
            if (count($prices) > 0 && property_exists($prices, 'data')) {
                
                $pricesMounted = [];
                
                foreach ($prices->data as $p) {
                    //Montar objeto de preço e salvar no banco
                    $pricesMounted[] = $general->mountPrice($p);
                }

                if (count($pricesMounted) > 0 && !$general->updateOrSavePrice($pricesMounted)) {
                    throw new Exception("Produto:" . $productData->produtoKey . ". Erro ao salvar no valor em cf_valor. Erro: " . json_encode($pricesMounted), 1);
                }
            }

        } catch (\Throwable $th) {
            file_put_contents('./logs/diary-update-error.txt', "\n" . Carbon::now() . ' - ' . $th->getMessage(), FILE_APPEND);
        }

        file_put_contents('./logs/diary-log.txt', "\n" . Carbon::now() . " - Produto Atualizado/Cadastrado. Produto ID:" . $productData->produtoKey, FILE_APPEND);

    }

    //Salvar posição em arquivo de status
    file_put_contents('./diary-update.txt', (string) $index);

    //Cálculo de páginação (quantidade por quantidade)
    //$index = $count * (($index + $count) / $count);

    //Invocar função em closure
    iterateProducts($index + 1, $count);
}

function iteratePrices($index, $count)
{

    global $bluesoft;

    //Data inicial
    $dia = new Carbon('2023-06-01 00:00');
    $time = $dia->format('d/m/Y H:m');

    //Retornar preços do dia
    $bluesoft->getPrices($index, $count, $time);

    //Retornar Resposta da API
    unset($response);
    $response = $bluesoft->getResponseContent();

    if (!$response || !key_exists('data', $response) || count($response->data) <= 0) {
        file_put_contents('./logs/diary-update-log.txt', "\n" . Carbon::now() . " - Nenhum preço retornado em requisição de preços: Início Período: $time, Páginação: $index. ", FILE_APPEND);
        return;
    }

    //Salvar requisição em arquivo
    file_put_contents('./dumps/precos.json', json_encode($response));

    //Iterar sobre items retornados via DATABASE
    foreach ($response->data as $key => $dbProduct) {

        //Converter em objeto
        $productData = (object) $dbProduct;

        try {

            // Funções gerais
            $general = new General();

            //Atribuir objeto contexto
            $general->setRequestData($productData);

            //Cadastrar produto se não existir
            if (!$general->getProduct($productData->produtoKey, 'code')) {
                
                //Requisitar produto via API
                $bluesoft->getProducts(0, 1, null, ['produtoKey' => $productData->produtoKey]);
                $prod_response = $bluesoft->getResponseContent();

                //Se houver produto em resposta, salvar no banco
                if ($prod_response && key_exists('data', $prod_response) && count($prod_response->data) > 0) {
                    $product = $general->mountProduct($prod_response->data[0]);
                    $general->saveProduct($product);
                }
            }

            //Montar objeto de preço e salvar no banco
            if (!$general->updateOrSavePrice()) {
                throw new Exception("Produto:" . $productData->produtoKey . ". Erro ao salvar no valor em cf_valor. Erro: " . json_encode($productData), 1);
            }

        } catch (\Throwable $th) {
            file_put_contents('./logs/diary-update-error.txt', "\n" . Carbon::now() . ' - ' . $th->getMessage(), FILE_APPEND);
        }

        file_put_contents('./logs/diary-update-log.txt', "\n" . Carbon::now() . " - Preço Atualizado/Cadastrado. Produto ID:" . $productData->produtoKey, FILE_APPEND);

    }

    //Salvar posição em arquivo de status
    file_put_contents('./diary-update-price.txt', (string) $index);

    //Cálculo de páginação (quantidade por quantidade)
    //$index = $count * (($index + $count) / $count);

    //Invocar função em closure
    iteratePrices($index + 1, $count);
}

//Quantidade de items por requisição
$qtd = 1000;

//Atribuir ponto ao interrompido anteriormente
$prod_saved_index = (int) file_get_contents('./diary-update.txt');
$prod_start = (empty($prod_saved_index) || !$prod_saved_index || $prod_saved_index <= 0) ? 0 : $prod_saved_index;

//Iniciar
iterateProducts($prod_start, $qtd);

//Resetar posição para inicio em arquivo de status
file_put_contents('./diary-update.txt', (string) 0);

$price_saved_index = (int) file_get_contents('./diary-update-price.txt');
$price_start = (empty($price_saved_index) || !$price_saved_index || $price_saved_index <= 0) ? 0 : $price_saved_index;

//Iniciar
iteratePrices($price_start, $qtd);

//Resetar posição para inicio em arquivo de status
file_put_contents('./diary-update-price.txt', (string) 0);

echo "diary-update.php: Execução de script finalizado!";