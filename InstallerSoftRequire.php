<?php

namespace SV\Utils;

use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;

/**
 * @property \XF\AddOn\AddOn addOn
 */
trait InstallerSoftRequire
{
    /**
     * @param array $errors
     * @param array $warnings
     */
    protected function checkSoftRequires(array &$errors, array &$warnings)
    {
        $json = $this->addOn->getJson();
        if (empty($json['require-soft']))
        {
            return;
        }
        $addOns = \XF::app()->container('addon.cache');
        foreach ((array)$json['require-soft'] as $productKey => $requirement)
        {
            if (!is_array($requirement))
            {
                continue;
            }
            list ($version, $product) = $requirement;
            $errorType = count($requirement) >= 3 ? $requirement[2] : null;
            // advisor
            if ($errorType === null)
            {
                continue;
            }

            $enabled = false;
            $versionValid = false;

            if (strpos($productKey, 'php-ext') === 0)
            {
                $parts = explode('/', $productKey, 2);
                if (isset($parts[1]))
                {
                    $enabled = phpversion($parts[1]) !== false;
                    $versionValid = ($version === '*') || (version_compare(phpversion($parts[1]), $version) === 1);
                }
            }
            else if (strpos($productKey, 'php') === 0)
            {
                $enabled = true;
                $versionValid = (version_compare(phpversion(), $version) === 1);
            }
            else if (strpos($productKey, 'mysql') === 0)
            {
                $mySqlVersion = \XF::db()->getServerVersion();
                if ($mySqlVersion)
                {
                    $enabled = true;
                    $versionValid = (version_compare(strtolower($mySqlVersion), $version) === 1);
                }
            }
            else
            {
                $enabled = isset($addOns[$productKey]);
                $versionValid = ($version === '*' || ($enabled && $addOns[$productKey] >= $version));
            }

            if (!$enabled || !$versionValid)
            {
                if ($errorType)
                {
                    $errors[] = "{$json['title']} requires {$product}.";
                }
                else
                {
                    $warnings[] = "{$json['title']} recommends {$product}.";
                }
            }
        }
    }
}