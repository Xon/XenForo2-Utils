<?php

namespace SV\Utils\Finder;

/**
 * @property array joins
 * @property \XF\Db\AbstractAdapter db
 *
 * @method string columnSqlName(string $column, bool $markFundamental = true)
 */
trait SqlJoinTrait
{
    /** @var array */
    protected $rawJoins = [];

    /**
     * @param string $rawJoinTable
     * @param string $alias
     * @param array  $columns
     * @param bool   $mustExist
     * @return $this
     */
    public function sqlJoin($rawJoinTable, $alias, array $columns, $mustExist = false)
    {
        $columns = \array_fill_keys($columns, true);
        $this->rawJoins[$alias] = isset($this->rawJoins[$alias]) ? $this->rawJoins[$alias] + $columns : $columns;

        if (isset($this->joins[$alias]))
        {
            $this->joins[$alias]['exists'] = $this->joins[$alias]['exists'] || $mustExist;

            return $this;
        }

        $this->joins[$alias] = [
            'rawJoin'        => true,
            // the $this->>oin entry must match the following structure, with 'fetch' being false so getHydrationMap doesn't try to parse this
            'table'          => $rawJoinTable,
            'alias'          => $alias,
            'condition'      => '',
            'fetch'          => false,
            'fundamental'    => false,
            'exists'         => $mustExist,

            // this are all the attributes stored in the joins array, used by getHydrationMap() but not getQuery()
            'structure'      => null,
            'parentAlias'    => null,
            'proxy'          => null,
            'parentRelation' => null,
            'relation'       => null,
            'relationValue'  => null,
            'entity'         => null,
        ];

        return $this;
    }

    /**
     * @param string $alias
     * @param array  $conditions
     */
    public function sqlJoinConditions($alias, array $conditions)
    {
        if (empty($this->rawJoins[$alias]) || empty($this->joins[$alias]))
        {
            throw new \LogicException('Need to invoke sqlJoin() before sqlJoinConditions()');
        }

        $joinConditions = [];

        foreach ($conditions AS $condition)
        {
            if (is_string($condition))
            {
                $joinConditions[] = "`$alias`.`$condition` = " . $this->columnSqlName($condition);
            }
            else
            {
                list($field, $operator, $value) = $condition;

                if (count($condition) > 3)
                {
                    $readValue = [];
                    foreach (array_slice($condition, 2) AS $v)
                    {
                        if ($v && $v[0] === '$')
                        {
                            $readValue[] = $this->columnSqlName(substr($v, 1));
                        }
                        else
                        {
                            $readValue[] = $this->db->quote($v);
                        }
                    }

                    $value = 'CONCAT(' . implode(', ', $readValue) . ')';
                }
                else if (is_string($value) && $value && $value[0] === '$')
                {
                    $value = $this->columnSqlName(substr($value, 1));
                }
                else
                {
                    $value = $this->db->quote($value);
                }

                if ($field[0] === '$')
                {
                    $fromJoinAlias = $this->columnSqlName(substr($field, 1));
                }
                else
                {
                    $fromJoinAlias = "`$alias`.`$field`";
                }

                $joinConditions[] = "$fromJoinAlias $operator $value";
            }
        }

        $this->joins[$alias]['fundamental'] = (bool)$joinConditions;
        $this->joins[$alias]['condition'] = implode(' AND ', $joinConditions);
    }

    /**
     * @param      $field
     * @param bool $markJoinFundamental
     *
     * @return array
     */
    public function resolveFieldToTableAndColumn($field, $markJoinFundamental = true)
    {
        $parts = explode('.', $field);
        if (count($parts) === 2)
        {
            list($alias, $column) = $parts;
            if (isset($this->rawJoins[$alias][$column]))
            {
                $this->joins[$alias]['fundamental'] = $markJoinFundamental;

                return [$alias, $column];
            }
        }

        /** @noinspection PhpUndefinedClassInspection */
        return parent::resolveFieldToTableAndColumn($field, $markJoinFundamental);
    }
}