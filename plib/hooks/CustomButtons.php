<?php
// Copyright 1999-2016. Parallels IP Holdings GmbH.
class Modules_Gotty_CustomButtons extends pm_Hook_CustomButtons
{

    public function getButtons()
    {
        $buttons = [[
            'place' => self::PLACE_DOMAIN_PROPERTIES,
            'title' => 'System Shell',
            'description' => 'Access to System Shell',
            'icon' => pm_Context::getBaseUrl() . 'images/icon.png',
            'link' => pm_Context::getActionUrl('index', 'shell'),
            'contextParams' => true,
            'visibility' => function($options) {
                if (isset($options['site_id'])) {
                    $domain = pm_Domain::getByDomainId($options['site_id']);
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
