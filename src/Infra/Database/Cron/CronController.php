<?php

namespace IntegracaoSimpatia\Infra\Database\Cron;

use IntegracaoSimpatia\Application\Usecases\CreateAllProductsUsecase;
use IntegracaoSimpatia\Application\Usecases\CreatePricesUsecase;
use IntegracaoSimpatia\Application\Usecases\CreateProductSegmentUsecase;
use IntegracaoSimpatia\Application\Usecases\CreateProductsUsecase;
use IntegracaoSimpatia\Application\Usecases\CreatePromotionGroupUsecase;
use IntegracaoSimpatia\Application\Usecases\CreateSegmentsUsecase;
use IntegracaoSimpatia\Application\Usecases\CreateSimilarUsecase;
use IntegracaoSimpatia\Application\Usecases\Strategy\DefaultPriceCreationStrategy;
use IntegracaoSimpatia\Application\Usecases\Strategy\DumpPriceCreationStrategy;
use IntegracaoSimpatia\Infra\Database\Database;
use IntegracaoSimpatia\Infra\Database\DatabaseInterface;
use IntegracaoSimpatia\Infra\Database\DumpOracleConnectionAdapter;
use IntegracaoSimpatia\Infra\Database\DumpRulesRepositoryAdapter;
use IntegracaoSimpatia\Infra\Database\OracleConnectionAdapter;
use IntegracaoSimpatia\Infra\Database\RulesRepositoryAdapter;

class CronController
{

    public function __construct(
        CronHandler $handler
    ) {

        //Dump de Carga diária de grupos de promoção,segmentos, similares, produtos e preços
        $handler->on("dump-all", [$this, 'dumpAll']);

        //Carga diária de preços por filial via dump
        $handler->on("load-all", [$this, 'loadAll']);

        //Carga diária de preços por filial via dump
        $handler->on("load-price", [$this, 'loadPrice']);

        //Carga diária de grupos de promoção,segmentos, similares, produtos e preços
        $handler->on("create-all", [$this, 'createAll']);

        //Carga diária de segmentações de produto por filial
        $handler->on("product-segment-all", [$this, 'productSegmentAll']);

        //Carga geral de produtos e famílias
        $handler->on("product-all", [$this, 'productAll']);

    }


    //Dump de Carga diária de grupos de promoção,segmentos, similares, produtos e preços
    static function dumpAll()
    {
        $start = microtime(true);
        (new DumpOracleConnectionAdapter())->execute();
        echo "Script 'Dump Create' Finalizado: " . (microtime(true) - $start);
    }

    //Carga diária de preços por filial via dump
    static function loadAll()
    {
        $start = microtime(true);

        //Repositório dumps realizados
        $repository = new DumpRulesRepositoryAdapter(new DumbConnectionAdapter());

        (new CreatePromotionGroupUsecase($repository))->execute();
        (new CreateSegmentsUsecase($repository))->execute();
        (new CreateSimilarUsecase($repository))->execute();
        (new CreateProductsUsecase($repository))->execute();

        echo "Script 'Dump Load' Finalizado: " . (microtime(true) - $start);
    }

    //Carga diária de preços por filial via dump
    static function loadPrice(array $params = [])
    {
        $start = microtime(true);

        //Repositório dumps realizados
        $repository = new DumpRulesRepositoryAdapter(new DumbConnectionAdapter());
        $repository->setParams($params);

        $createPricesUsecase = new CreatePricesUsecase($repository);
        $createPricesUsecase->setStrategy(new DumpPriceCreationStrategy($repository));
        $createPricesUsecase->execute();
        echo "Script 'Dump Load' (Preço) Finalizado: " . (microtime(true) - $start);
    }

    //Carga diária de grupos de promoção,segmentos, similares, produtos e preços
    function createAll($params = null)
    {

        //Conexão BD
        $database = new OracleConnectionAdapter();
        $repository = new RulesRepositoryAdapter($database);

        #Outros
        $createProductsUsecase = new CreateProductsUsecase($repository);
        $createPromotionGroupUsecase = new CreatePromotionGroupUsecase($repository);
        $createSegmentsUsecase = new CreateSegmentsUsecase($repository);
        $createSimilarUsecase = new CreateSimilarUsecase($repository);

        #Preços
        $defaultStrategy = new DefaultPriceCreationStrategy($repository);
        $createDefaultPricesUsecase = new CreatePricesUsecase($repository);
        $createDefaultPricesUsecase->setStrategy($defaultStrategy);

        $start = microtime(true);
        $createPromotionGroupUsecase->execute();
        $createSegmentsUsecase->execute();
        $createSimilarUsecase->execute();
        $createProductsUsecase->execute();
        $createDefaultPricesUsecase->execute();

        echo "Script Finalizado: " . (microtime(true) - $start);

    }

    //Carga diária de segmentações de produto por filial
    function productSegmentAll()
    {

        //Conexão BD
        $database = new OracleConnectionAdapter();
        $repository = new RulesRepositoryAdapter($database);

        #Outros
        $createProductSegmentUsecase = new CreateProductSegmentUsecase($repository);

        $start = microtime(true);
        $createProductSegmentUsecase->execute();
        echo "Script Finalizado: " . (microtime(true) - $start);
    }

    //Carga geral de produtos e famílias
    function productAll()
    {

        //Conexão BD
        $database = new OracleConnectionAdapter();
        $repository = new RulesRepositoryAdapter($database);

        #Outros
        $createAllProductsUsecase = new CreateAllProductsUsecase($repository);

        $start = microtime(true);
        $createAllProductsUsecase->execute();
        echo "Script Finalizado: " . (microtime(true) - $start);
    }

}

class DumbConnectionAdapter implements DatabaseInterface
{
    public function close()
    {
        return;
    }
    public function getConnection()
    {
        return;
    }
    public function query($query)
    {
        return;
    }
}