<?php
namespace app\controller;

use app\BaseController;
use app\gateway\GatewayClient;

class Index extends BaseController
{
    public function index()
    {
        if (checkInt(input("u_id"), false)) {
            return (int) GatewayClient::isUidOnline(input("u_id"));
        }
        return "";
    }
}
