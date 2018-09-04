<?php

namespace SV\Utils;

use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;

/**
 * @property \XF\AddOn\AddOn addOn
 *
 * @method \XF\Db\AbstractAdapter db()
 * @method \XF\Db\SchemaManager schemaManager()
 */
trait InstallerHelper
{
    /**
     * @param string $addonId
     * @param int    $minVersion
     * @return bool|int
     */
    protected function addonExists($addonId, $minVersion = 0)
    {
        $addOns = \XF::app()->container('addon.cache');
        if (empty($addOns[$addonId]))
        {
            return false;
        }
        else if ($minVersion && ($addOns[$addonId] < $minVersion))
        {
            return false;
        }

        return $addOns[$addonId];
    }

    /*
     * @param      $title
     * @param      $value
     * @param bool $deOwn
     *
     * @throws \XF\PrintableException
     */
    protected function addDefaultPhrase($title, $value, $deOwn = true)
    {
        /** @var \XF\Entity\Phrase $phrase */
        $phrase = \XF::app()->finder('XF:Phrase')
                     ->where('title', '=', $title)
                     ->where('language_id', '=', 0)
                     ->fetchOne();
        if (!$phrase)
        {
            $phrase = \XF::em()->create('XF:Phrase');
            $phrase->language_id = 0;
            $phrase->title = $title;
            $phrase->phrase_text = $value;
            $phrase->global_cache = false;
            $phrase->addon_id = '';
            $phrase->save(false);
        }
        else if ($deOwn && $phrase->addon_id === $this->addOn->getAddOnId())
        {
            $phrase->addon_id = '';
            $phrase->save(false);
        }
    }

    /**
     * @param int   $groupId
     * @param int   $permissionId
     * @param int[] $userGroups
     */
    protected function applyGlobalPermissionByGroup($groupId, $permissionId, array $userGroups)
    {
        foreach($userGroups as $userGroupId)
        {
            $this->applyGlobalPermissionForGroup($groupId, $permissionId, $userGroupId);
        }
    }

    /**
     * @param string      $applyGroupId
     * @param string      $applyPermissionId
     * @param int         $userGroupId
     */
    public function applyGlobalPermissionForGroup($applyGroupId, $applyPermissionId, $userGroupId)
    {
        $this->db()->query(
            "INSERT IGNORE INTO xf_permission_entry (user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int) VALUES
                (?, 0, ?, ?, 'allow', '0')
            ", [$userGroupId, $applyGroupId, $applyPermissionId]
        );
        $db = $this->db();
    }

    /**
     * @param string $applyGroupId
     * @param string $applyPermissionId
     * @param int    $applyValue
     * @param int    $userGroupId
     */
    public function applyGlobalPermissionIntForGroup($applyGroupId, $applyPermissionId, $applyValue, $userGroupId)
    {
        $this->db()->query(
            "INSERT IGNORE INTO xf_permission_entry (user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int) VALUES
                (?, 0, ?, ?, 'use_int', ?)
            ", [$userGroupId, $applyGroupId, $applyPermissionId, $applyValue]
        );
    }

    /**
     * @param array $newRegistrationDefaults
     */
    protected function applyRegistrationDefaults(array $newRegistrationDefaults)
    {
        /** @var \XF\Entity\Option $option */
        $option = \XF::app()->finder('XF:Option')
                            ->where('option_id', '=', 'registrationDefaults')
                            ->fetchOne();

        if (!$option)
        {
            // Option: Mr. XenForo I don't feel so good
            throw new \LogicException("XenForo installation is damaged. Expected option 'registrationDefaults' to exist.");
        }
        $registrationDefaults = $option->option_value;

        foreach ($newRegistrationDefaults AS $optionName => $optionDefault)
        {
            if (!isset($registrationDefaults[$optionName]))
            {
                $registrationDefaults[$optionName] = $optionDefault;
            }
        }

        $option->option_value = $registrationDefaults;
        $option->saveIfChanged();
    }

    /**
     * @param $oldGroupId
     * @param $oldPermissionId
     * @param $newGroupId
     * @param $newPermissionId
     */
    protected function renamePermission($oldGroupId, $oldPermissionId, $newGroupId, $newPermissionId)
    {
        $this->db()->query('
            UPDATE IGNORE xf_permission_entry
            SET permission_group_id = ?, permission_id = ?
            WHERE permission_group_id = ? AND permission_id = ?
        ', [$newGroupId, $newPermissionId, $oldGroupId, $oldPermissionId]);

        $this->db()->query('
            UPDATE IGNORE xf_permission_entry_content
            SET permission_group_id = ?, permission_id = ?
            WHERE permission_group_id = ? AND permission_id = ?
        ', [$newGroupId, $newPermissionId, $oldGroupId, $oldPermissionId]);

        $this->db()->query('
            DELETE FROM xf_permission_entry
            WHERE permission_group_id = ? AND permission_id = ?
        ', [$oldGroupId, $oldPermissionId]);

        $this->db()->query('
            DELETE FROM xf_permission_entry_content
            WHERE permission_group_id = ? AND permission_id = ?
        ', [$oldGroupId, $oldPermissionId]);
    }

    /**
     * @param string $old
     * @param string $new
     * @param bool   $takeOwnership
     */
    protected function renameOption($old, $new, $takeOwnership = false)
    {
        /** @var \XF\Entity\Option $optionOld */
        $optionOld = \XF::finder('XF:Option')->whereId($old)->fetchOne();
        $optionNew = \XF::finder('XF:Option')->whereId($new)->fetchOne();
        if ($optionOld && !$optionNew)
        {
            $optionOld->option_id = $new;
            if ($takeOwnership)
            {
                $optionOld->addon_id = $this->addOn->getAddOnId();
            }
            $optionOld->saveIfChanged();
        }
    }

    /**
     * @param array $map
     * @param bool  $deOwn
     * @throws \XF\PrintableException
     */
    protected function renamePhrases($map, $deOwn = false)
    {
        $db = $this->db();

        foreach ($map AS $from => $to)
        {
            $mySqlRegex = '^' . str_replace('*', '[a-zA-Z0-9_]+', $from) . '$';
            $phpRegex = '/^' . str_replace('*', '([a-zA-Z0-9_]+)', $from) . '$/';
            $replace = str_replace('*', '$1', $to);

            $results = $db->fetchPairs("
				SELECT phrase_id, title
				FROM xf_phrase
				WHERE title RLIKE ?
					AND addon_id = ''
			", $mySqlRegex);

            if ($results)
            {
                /** @var \XF\Entity\Phrase[] $phrases */
                $phrases = \XF::em()->findByIds('XF:Phrase', array_keys($results));
                foreach ($results AS $phraseId => $oldTitle)
                {
                    if (isset($phrases[$phraseId]))
                    {
                        $newTitle = preg_replace($phpRegex, $replace, $oldTitle);

                        $phrase = $phrases[$phraseId];
                        $phrase->title = $newTitle;
                        $phrase->global_cache = false;
                        if ($deOwn)
                        {
                            $phrase->addon_id = '';
                        }
                        $phrase->save(false);
                    }
                }
            }
        }
    }

    /**
     * @param string[] $map
     */
    protected function deletePhrases($map)
    {
        $titles = [];
        foreach($map as $titlePattern)
        {
            $titles[] = ['title', 'LIKE', $titlePattern];
        }

        /** @var \XF\Finder\Phrase $phraseFinder */
        $phraseFinder = \XF::finder('XF:Phrase');
        /** @var \XF\Entity\Phrase[] $phrases */
        $phrases = $phraseFinder
            ->where('language_id', 0)
            ->whereOr($titles)
            ->fetch();

        foreach ($phrases as $phrase)
        {
            $phrase->delete();
        }
    }

    /**
     * @param string $old
     * @param string $new
     */
    protected function renameStyleProperty($old, $new)
    {
        /** @var \XF\Entity\StyleProperty $optionOld */
        $optionOld = \XF::finder('XF:StyleProperty')->where('property_name', '=', $old)->fetchOne();
        $optionNew = \XF::finder('XF:StyleProperty')->where('property_name', '=', $new)->fetchOne();
        if ($optionOld && !$optionNew)
        {
            $optionOld->property_name = $new;
            $optionOld->saveIfChanged();
        }
    }

    /**
     * @param string $old
     * @param string $new
     * @param bool   $dropOldIfNewExists
     */
    protected function migrateTable($old, $new, $dropOldIfNewExists = false)
    {
        $sm = $this->schemaManager();
        if ($sm->tableExists($old))
        {
            if (!$sm->tableExists($new))
            {
                $sm->renameTable($old, $new);
            }
            else if ($dropOldIfNewExists)
            {
                $sm->dropTable($old);
            }
        }
    }

    /**
     * @param Create|Alter $table
     * @param string       $name
     * @param string|null  $type
     * @param string|null  $length
     * @return \XF\Db\Schema\Column
     * @throws \LogicException If table is unknown schema object
     */
    protected function addOrChangeColumn($table, $name, $type = null, $length = null)
    {
        if ($table instanceof Create)
        {
            $table->checkExists(true);

            return $table->addColumn($name, $type, $length);
        }
        else if ($table instanceof Alter)
        {
            if ($table->getColumnDefinition($name))
            {
                return $table->changeColumn($name, $type, $length);
            }

            return $table->addColumn($name, $type, $length);
        }
        else
        {
            throw new \LogicException('Unknown schema DDL type ' . \get_class($table));
        }
    }

    /**
     * @param int $minAddonVersion
     * @param int $maxThreads
     * @param int $maxPosts
     * @param int $maxUsers
     * @return bool
     */
    protected function isCliRecommendedCheck($minAddonVersion, $maxThreads, $maxPosts, $maxUsers)
    {
        $totals = \XF::app()->db()->fetchOne("
			SELECT data_value
			FROM xf_data_registry
			WHERE data_key IN ('boardTotals', 'forumStatistics')
			LIMIT 1
		");
        if (!$totals)
        {
            return false;
        }

        $totals = @unserialize($totals);
        if (!$totals)
        {
            return false;
        }

        if ($maxPosts && !empty($totals['messages']) && $totals['messages'] >= $maxPosts)
        {
            return true;
        }

        if ($maxUsers && !empty($totals['users']) && $totals['users'] >= $maxUsers)
        {
            return true;
        }

        if ($maxThreads && !empty($totals['threads']) && $totals['threads'] >= $maxThreads)
        {
            return true;
        }

        if ($minAddonVersion)
        {
            $existing = $this->addOn->getInstalledAddOn();
            if ($existing === null || $existing->version_id < $minAddonVersion)
            {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string[] $warnings
     * @param string   $name
     * @param int      $minAddonVersion
     * @param int      $maxThreads
     * @param int      $maxPosts
     * @param int      $maxUsers
     * @return bool
     */
    public function isCliRecommended(&$warnings, $name, $minAddonVersion = 0, $maxThreads = 0, $maxPosts = 500000, $maxUsers = 50000)
    {
        if (\XF::app() instanceof \XF\Admin\App && $this->isCliRecommendedCheck($minAddonVersion, $maxThreads, $maxPosts, $maxUsers))
        {
            $existing = $this->addOn->getInstalledAddOn();
            if ($existing)
            {
                $html = 'Your XenForo installation is large. You may wish to upgrade via the command line.<br/>
			Simply run this command from within the root XenForo directory and follow the on-screen instructions:<br/>
			<pre style="margin: 1em 2em">php cmd.php xf-addon:upgrade ' . \XF::escapeString($this->addOn->getAddOnId()) . '</pre>
			You can continue with the browser-based upgrade, but large queries may cause browser timeouts<br/>
			that will force you to reload the page.';
            }
            else
            {
                $html = 'Your XenForo installation is large. You may wish to install via the command line.<br/>
			Simply run this command from within the root XenForo directory and follow the on-screen instructions:<br/>
			<pre style="margin: 1em 2em">php cmd.php xf-addon:install ' . \XF::escapeString($this->addOn->getAddOnId()) . '</pre>
			You can continue with the browser-based upgrade, but large queries may cause browser timeouts<br/>
			that will force you to reload the page.';
            }
            LanguagePhrasing::forceSetPhrase(\XF::language(), $name, $html);

            $warnings[] = \XF::phrase($name);

            return true;
        }

        return false;
    }
}