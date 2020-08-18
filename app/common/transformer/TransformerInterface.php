<?php
/**
 * 数据格式统一化处理接口类.
 */

namespace app\common\transformer;

/**
 * Interface TransformerInterface.
 *
 * @package App\Transformers
 */
interface TransformerInterface
{
    public function transform(array $data);
}
