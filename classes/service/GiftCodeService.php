<?php

namespace service;

use conf\MysqlCon;
use conf\RedisCon;
use entity\codeModel;
use entity\userData;
use framework\util\Singleton;
use conf\mysqlConfig;
use utils\HttpUtil;

class GiftCodeService extends BaseService
{
    /**
     * GiftCodeService constructor.
     */
    public function __construct()
    {
    }

    public function checkParams($uid, $code, $role)
    {
        if (!isset($uid) || empty($uid)) {
            return [false, 'lack_of_uid'];
        }
        if (!isset($code) || empty($code)) {
            return [false, 'lack_of_code'];
        }
        if (!isset($role) || empty($role)) {
            return [false, 'lack_of_role'];
        }

        return [true, 'ok'];
    }

    public function checkParam($code)
    {
        if (!isset($code) || empty($code)) {
            return [false, 'lack_of_$code'];
        }
        return [true, 'ok'];
    }

    /**
     * 使用礼包码
     * @param $uid
     * @param $role
     * @param $code
     * @return array
     */
    public function useCode($uid, $role, $code)
    {
        /** @var CacheService $cacheService */
        $cacheService = Singleton::get(CacheService::class);

        //获取礼品码redis数据
        $redisArray = $cacheService->getAllHash($code);

        $description = $cacheService->getHash($code, 'description');
        $count = $cacheService->getHash($code, 'count');
        $begintime = $cacheService->getHash($code, 'begin_time');
        $endtime = $cacheService->getHash($code, 'end_time');
        $type = $cacheService->getHash($code, 'type');
        $roled = $cacheService->getHash($code, 'role');
        $receivedCount = $cacheService->getHash($code, 'receivedCount');
        $nowData = date('Y-m-d H:i:s');

        $content = array();
        foreach ($redisArray as $key => $value) {
            if (substr($key, 0, 8) == 'content_') {
                $content[substr($key, 8, strlen($key))] = $value;
            }
        }

        if (empty($redisArray)) {
            return parent::show(
                200,
                'ok',
                '礼包码未找到！'
            );
        } else if (strtotime($nowData) - strtotime($begintime) > 0 && strtotime($nowData) - strtotime($endtime) < 0) {
            //指定用户一次性消耗
            if ($type == 1) {
                if ($role == $roled) {//判断是否为指定角色
                    if ($count > 0) {//礼包码可使用次数是否足够
                        //可领取次数减一
                        $cacheService->setHash($code, 'count', $count - 1);
                        //领取次数加一
                        $cacheService->setHash($code, 'receivedCount', $receivedCount + 1);
                        //增加领取记录
                        $cacheService->setHash($code . '_use', 'user_' . $uid, $nowData);
                        //更新数据库
                        $finllyresult = $this->update($uid, $content);
                        return parent::show(
                            200,
                            'ok',
                            $finllyresult
                        );
                    } else {
                        return parent::show(
                            2000,
                            'ok',
                            '礼包码已被兑换！'
                        );
                    }
                } else {
                    return parent::show(
                        400,
                        'error',
                        '权限不足，无法兑换！'
                    );
                }

            } else if ($type == 2) {//不指定用户限制兑换次数
                if ($count > 0) {//礼包码可使用次数是否足够
                    //判断是否领取过
                    $useList = $cacheService->getAllHash($code . '_use');
                    foreach ($useList as $key => $value) {
                        if ($key == 'user_' . $uid) {
                            return parent::show(
                                400,
                                'error',
                                '已兑换过！'
                            );
                        }
                    }
                    //可领取次数减一
                    $cacheService->setHash($code, 'count', $count - 1);
                    //领取次数加一
                    $cacheService->setHash($code, 'receivedCount', $receivedCount + 1);
                    //增加领取记录
                    $cacheService->setHash($code . '_use', 'user_' . $uid, $nowData);
                    //更新数据库
                    $finllyresult = $this->update($uid, $content);
                    return parent::show(
                        200,
                        'ok',
                        $finllyresult
                    );

                } else {
                    return parent::show(
                        200,
                        'ok',
                        '礼包码兑换次数已达到上限！'
                    );
                }
            } else if (type == 3) {//不限用户不限次数兑换
                //领取次数加一
                $cacheService->setHash($code, 'receivedCount', $receivedCount + 1);
                //增加领取记录
                $cacheService->setHash($code . '_use', $uid, $nowData);
                //更新数据库
                $finllyresult = $this->update($uid, $content);
                return parent::show(
                    200,
                    'ok',
                    $finllyresult
                );
            }


        }


    }

    function getCodeInfo($code)
    {
        /** @var CacheService $cacheService */
        $cacheService = Singleton::get(CacheService::class);
        $codeList = $cacheService->getAllHash($code);
        $useList = $cacheService->getAllHash($code . '_use');

        if (empty($codeList)) {
            return parent::show(
                200,
                'ok',
                '礼包码未找到！'
            );
        } else {
            return parent::showResule(
                200,
                'ok',
                $codeList,
                $useList
            );
        }


    }

    /**
     * 生成随机数
     * @param $len
     * @param null $chars
     * @return string
     */
    function getRandomString($len, $chars = null)
    {
        if (is_null($chars)) {
            $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        }
        mt_srand(10000000 * (double)microtime());
        for ($i = 0, $str = '', $lc = strlen($chars) - 1; $i < $len; $i++) {
            $str .= $chars[mt_rand(0, $lc)];
        }
        return $str;
    }

    /**
     * 更新数据库
     * @param $uid
     * @param $content
     */
    function update($uid, $content)
    {
        /** @var SampleService $sampleService */
        $sampleService = Singleton::get(SampleService::class);
        //查询用户原数据
        //金币，钻石
        $result = $sampleService->query("SELECT * FROM user WHERE id = '$uid'");
        //累加奖励
        $coin = $content[coin] + $result[0][coin];
        $diamond = $content[diamond] + $result[0][diamond];

        $date = date('Y-m-d H:i:s');
        //更新金币，钻石
        $sql = "update `user` set coin='$coin',diamond='$diamond',updateTime='$date' where id='$uid'";
        $upresult = $sampleService->query($sql);

        //插入礼包码奖励
        $sql = "INSERT INTO `user_thing` (uid,hero,soldier,props) VALUES ('$uid','$content[hero]','$content[soldier]','$content[props]')";
        $inserResult = $sampleService->query($sql);

        //查询更新后的英雄，道具，士兵
        $thingsList = $sampleService->query("SELECT * FROM user_thing where uid = '$uid'");
        $hero = array();
        $soldier = array();
        $props = array();
        foreach ($thingsList as $key => $value) {
            array_push($hero, $value[hero]);
            array_push($soldier, $value[soldier]);
            array_push($props, $value[props]);
        }

        //查询更新后的用户数据
        $finllyresult = $sampleService->query("SELECT * FROM user WHERE id = '$uid'");

        //合并数据
        $finllyresult[0]['hero'] = $hero;
        $finllyresult[0]['soldier'] = $soldier;
        $finllyresult[0]['props'] = $props;

        return $finllyresult[0];
    }

    public function test()
    {

        $re = $this->exists(code_EkoJDXUG);

        //die(t);
        echo $re;
        die($re);
    }
}