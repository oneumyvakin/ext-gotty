<?php
// Copyright 1999-2016. Parallels IP Holdings GmbH.
class Modules_CustomButtons_CustomButtons extends pm_Hook_CustomButtons
{

    public function getButtons()
    {
        $buttons = [[
            'place' => self::PLACE_DOMAIN,
            'title' => 'System Shell',
            'description' => 'Access to System Shell',
            'icon' => pm_Context::getBaseUrl() . 'images/icon.png',
            'link' => pm_Context::getActionUrl('index'),
            'contextParams' => true,
            'visibility' => function($options) {
                pm_Log::debug("mark");
                pm_Log::debug(print_r($options, true));
                if (isset($options['dom_id'])) {
                    $domain = pm_Domain::getByDomainId($options['dom_id']);
                    $sysUser = $domain->getSysUserLogin();
                    $shell = pm_Bootstrap::getDbAdapter()->fetchOne("select shell from sys_users where login='${sysUser}'");
                    if ($shell == '/bin/false') {
                        return false;
                    }
                    return true;
                }
                return false;
            },
        ]];

        return $buttons;
    }

}
