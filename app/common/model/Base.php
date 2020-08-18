<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/9/18
 * Time: 16:56
 */

namespace app\common\model;

use think\Model;

class Base extends Model
{
    public function findById($id)
    {
        return $this->where(static::getPk(), $id)->find();
    }

    public function saveByData($data)
    {
        $returnData = $data;
        $returnData[static::getPk()] = $this->insertGetId($data);
        return $returnData;
    }

    public function updateByIdAndData($id,$data)
    {
        return $this->isUpdate(true)->save($data, [static::getPk() => $id]);
    }

    public function findByMap(array $map)
    {
        return $this->where($map)->find();
    }
}