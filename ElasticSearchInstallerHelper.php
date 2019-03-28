<?php

namespace SV\Utils;

use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;

/**
 * @property \XF\AddOn\AddOn addOn
 */
trait ElasticSearchInstallerHelper
{
    protected function checkElasticSearchOptimizableState()
    {
        $es = \XFES\Listener::getElasticsearchApi();

        /** @var \XFES\Service\Configurer $configurer */
        $configurer = \XF::service('XFES:Configurer', $es);
        $version = null;
        $testError = null;
        $stats = null;
        $isOptimizable = false;
        $analyzerConfig = null;

        if ($configurer->hasActiveConfig())
        {
            try
            {
                $version = $es->version();

                if ($version && $es->test($testError))
                {
                    if ($es->indexExists())
                    {
                        /** @var \XFES\Service\Optimizer $optimizer */
                        $optimizer = \XF::service('XFES:Optimizer', $es);
                        $isOptimizable = $optimizer->isOptimizable();
                    }
                    else
                    {
                        $isOptimizable = true;
                    }
                }
            }
            catch (\XFES\Elasticsearch\Exception $e) {}
        }

        if ($isOptimizable)
        {
            \XF::logError('Elasticsearch index must be rebuilt to include custom mappings.', true);
        }
    }
}