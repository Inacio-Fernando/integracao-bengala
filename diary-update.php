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
    $dia = new Carbon();
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
    file_put_contents('./dumps/produtos.json', json_encode($response));

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
            if (!$product_id = $general->updateOrSavePrice((array) $general->price)) {
                throw new Exception("Produto:" . $productData->id . ". Não foi possível salvar preço. Erro: " . json_encode($productData), 1);
            }

        } catch (\Throwable $th) {
            file_put_contents('./logs/diary-update-error.txt', "\n" . Carbon::now() . ' - ' . $th->getMessage(), FILE_APPEND);
        }

        file_put_contents('./logs/diary-update-log.txt', "\n" . Carbon::now() . " - Produto Atualizado/Cadastrado. Produto ID:" . $productData->id, FILE_APPEND);

    }

    //Salvar posição em arquivo de status
    file_put_contents('./diary-update.txt', (string) $index);

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