<?php

namespace app\common\model;

use think\Model as ThinkModel;
use \Exception;

class AbstractModel extends ThinkModel
{
    protected $pk = 'id';

    protected $resultSetType = 'collection';

    protected function getPagerDataList($query, $page = 0, $limit = 10)
    {
        return new \Pagination($query, $page, $limit);
    }

    /**
     * TODO 乐观锁,等升级5.2版本后,更新相关调用后移除
     *
     * @param array $data
     * @param array $where
     * @param int   $originVer
     *
     * @return int|string
     * @throws Exception
     */
    public function saveWithOptimLock(array $data, array $where, int $originVer)
    {
        $optimLock = property_exists($this, 'optimLockField') && isset($this->optimLockField) ? $this->optimLockField : 'lock_version';
        if (!empty($data) && !empty($where) && $optimLock && ctype_digit((string)$originVer)) {
            $lockVer = $originVer + 1;
            $data[$optimLock] = $lockVer;
            $where[] = [$optimLock, '=', $originVer];

            return $this->where($where)->update($data);
        }

        throw new Exception('SaveWithOptimLock Invalid Arguments.');
    }
}
