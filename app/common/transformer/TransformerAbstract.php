<?php

namespace app\common\transformer;

use League\Fractal\TransformerAbstract as TransformerBaseAbstract;

/**
 * Class TransformerAbstract.
 *
 * @package App\Transformers
 */
abstract class TransformerAbstract extends TransformerBaseAbstract implements TransformerInterface
{
    /**
     * @var array
     */
    protected $_queries;

    /**
     * TransformerAbstract constructor.
     *
     * @param array $params
     */
    public function __construct(array $params = null)
    {
        $this->_queries = $params ?? [];
    }

    public function transform(array $data)
    {
        return $this->transformData($data ?? []);
    }

    abstract public function transformData(array $data);
}
