<?php
// Copyright 1999-2016. Parallels IP Holdings GmbH.

class Modules_Gotty_Task_Execute extends pm_LongTask_Task // Since Plesk 17.0
{
    const UID = 'execute';
    public $trackProgress = false;
    public $hidden = true;

    public function run()
    {
        $sysUser = $this->getParam('sysUser', 'none');
        $subscriptionPath = $this->getParam('subscriptionPath', 'none');
        $configPath = $this->getParam('configPath', 'none');
        $shell = $this->getParam('shell', 'none');
        $gottyPath = $this->getParam('gottyPath', 'none');

        $args = [
            $sysUser,
            'exec',
            $subscriptionPath,
            $gottyPath,
            '--once',
            '--config',
            $configPath,
            '-w',
            $shell,
        ];
        $err = pm_ApiCli::callSbin('filemng', $args, pm_ApiCli::RESULT_FULL);
        if ($err['code'] <> 0) {
            throw new pm_Exception("Failed to execute gotty: filemng " . print_r($args, true) . " with: " . print_r($err, true));
        }
        pm_Log::debug('Gotty session end');
        pm_Log::debug('Delete temporary Gotty config');
        pm_ApiCli::callSbin('filemng', [$sysUser, 'rm', $configPath], pm_ApiCli::RESULT_FULL);
        pm_Log::debug('Gotty config is deleted');
    }


}