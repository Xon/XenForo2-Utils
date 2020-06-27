<?php

namespace SV\Utils;

use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;

/**
 * @property \XF\AddOn\AddOn addOn
 */
trait InstallerSoftRequire2
{
    /**
     * Supports a 'require-soft' section with near identical structure to 'require'
     *
     * An example;
"require-soft" :{
    "SV/Threadmarks": [
        2000100,
        "Threadmarks v2.0.3+",
        false
    ]
},
     * The 3rd array argument has 3 supported values, null/true/false
     *   null/no exists - this is advisory for "Extra Cli Tools" when determining bulk install order, and isn't actually checked
     *   false - if the item exists and is below the minimum version, log as a warning
     *   true - if the item exists and is below the minimum version, log as an error
     *
     * @param array $errors
     * @param array $warnings
     */
    protected function checkSoftRequires(&$errors, &$warnings)
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
            // advisory
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
                    $versionValid = ($version === '*') || (version_compare(phpversion($parts[1]), $version, 'ge'));
                }
            }
            else if (strpos($productKey, 'php') === 0)
            {
                $enabled = true;
                $versionValid = version_compare(phpversion(), $version, 'ge');
            }
            else if (strpos($productKey, 'mysql') === 0)
            {
                $mySqlVersion = \XF::db()->getServerVersion();
                if ($mySqlVersion)
                {
                    $enabled = true;
                    $versionValid = version_compare(strtolower($mySqlVersion), $version, 'ge');
                }
            }
            else
            {
                $enabled = isset($addOns[$productKey]);
                $versionValid = ($version === '*' || ($enabled && $addOns[$productKey] >= $version));
            }

            if (!$enabled)
            {
                continue;
            }

            if (!$versionValid)
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
