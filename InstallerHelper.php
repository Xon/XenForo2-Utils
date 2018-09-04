<?php

namespace SV\Utils;

use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;

/**
 * @method \XF\Db\AbstractAdapter db()
 */
trait InstallerHelper
{
    /**
     * @param int   $groupId
     * @param int   $permissionId
     * @param int[] $userGroups
     */
    protected function applyGlobalPermissionByGroup($groupId, $permissionId, array $userGroups)
    {
        foreach($userGroups as $userGroupId)
        {
            $this->db()->query(
                "INSERT IGNORE INTO xf_permission_entry (user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int) VALUES
                (?, 0, ?, ?, 'allow', '0')
            ", [$userGroupId, $groupId, $permissionId]);
        }
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
     * @param $old
     * @param $new
     *
     * @throws \XF\PrintableException
     */
    protected function renameOption($old, $new)
    {
        /** @var \XF\Entity\Option $optionOld */
        $optionOld = \XF::finder('XF:Option')->whereId($old)->fetchOne();
        $optionNew = \XF::finder('XF:Option')->whereId($new)->fetchOne();
        if ($optionOld && !$optionNew)
        {
            $optionOld->option_id = $new;
            $optionOld->save();
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
}