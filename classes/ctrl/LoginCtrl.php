<?php

namespace ctrl;

use framework\util\Singleton;
use service\AnswerService;
use service\GiftCodeService;
use service\LoginService;
use utils\HttpUtil;
use view\JsonView;

class LoginCtrl extends CtrlBase
{

    /**
     * LoginCtrl constructor.
     */
    public function __construct()
    {
    }

    /**
     * 登陆注册
     * @return JsonView
     */
    public function login()
    {
        //获取get或post请求数据
        $uid = HttpUtil::getPostData('uid');
        /** @var LoginService $loginService */
        $loginService = Singleton::get(LoginService::class);

        //校验数据
        list($checkResult, $checkNotice) = $loginService->checkParam($uid);

        if (true !== $checkResult) {
            $rspArr = AnswerService::makeResponseArray($checkNotice);
            return new JsonView($rspArr);
        }

        //执行函数
        $result = $loginService->login($uid);

        return new JsonView($result);
    }


    /**
     * 使用礼包码
     * @return JsonView
     */
    public function useCode()
    {
        //获取get或post请求数据
        $uid = HttpUtil::getPostData('uid');
        $role = HttpUtil::getPostData('role');
        $code = HttpUtil::getPostData('code');

        /** @var GiftCodeService $giftCodeService */
        $giftCodeService = Singleton::get(GiftCodeService::class);

        //校验数据
        list($checkResult, $checkNotice) = $giftCodeService->checkParams($uid, $code, $role);
        if (true !== $checkResult) {
            $rspArr = AnswerService::makeResponseArray($checkNotice);
            return new JsonView($rspArr);
        }

        //执行函数
        $result = $giftCodeService->useCode($uid, $role, $code);

        return new JsonView($result);

    }

    public function test()
    {

        /** @var LoginService $loginService */
        $loginService = Singleton::get(LoginService::class);

        $re = $loginService->test();

        return $re;
    }
}
